<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\MlApiException;
use App\Models\AuditLog;
use App\Models\Measurement;
use App\Models\ModelVersion;
use App\Models\Prediction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Orkestrasi prediksi: ambil feature, panggil ML API, simpan hasil.
 *
 * Bila ML API gagal, TIDAK ada baris prediksi yang disimpan. Sistem tidak
 * pernah membuat hasil pengganti.
 */
class PredictionService
{
    public function __construct(
        private readonly MlPredictionService $ml,
        private readonly AuditService $audit,
    ) {}

    /**
     * @throws MlApiException
     */
    public function run(Measurement $measurement, User $user): Prediction
    {
        $measurement->loadMissing('toddler');

        $features = $this->ml->buildFeatures($measurement);
        $requestId = (string) Str::uuid();

        // Panggilan jaringan sengaja dilakukan DI LUAR transaction, agar
        // koneksi database tidak tertahan selama menunggu ML API.
        try {
            $result = $this->ml->predict($features, $requestId);
        } catch (MlApiException $e) {
            $this->audit->log(
                AuditLog::ACTION_PREDICT_FAILED,
                'measurement',
                $measurement->id,
                'Prediksi gagal ('.$e->errorCode.') untuk balita '.$measurement->toddler->code,
                $user,
            );

            throw $e;
        }

        $modelVersion = $this->resolveModelVersion($result['model_version']);

        return DB::transaction(function () use ($measurement, $user, $features, $result, $modelVersion): Prediction {
            $prediction = $this->persist($measurement, $user, $features, $result, $modelVersion);

            $this->audit->log(
                AuditLog::ACTION_PREDICT,
                'prediction',
                $prediction->id,
                sprintf(
                    'Prediksi balita %s, model %s, hasil %s',
                    $measurement->toddler->code,
                    $result['model_version'] ?? '-',
                    $result['prediction_label'],
                ),
                $user,
            );

            return $prediction;
        });
    }

    /** Pemeriksaan yang belum pernah diprediksi. */
    public function pendingQuery(): Builder
    {
        return Measurement::query()->whereDoesntHave('predictions');
    }

    /**
     * Prediksi banyak pemeriksaan sekaligus. Hanya hasil yang benar-benar
     * dikembalikan model yang disimpan; item yang gagal dilewati.
     *
     * @param  Collection<int, Measurement>  $measurements
     * @return array{success:int, failed:int, errors:array<string,int>}
     *
     * @throws MlApiException bila layanan belum dikonfigurasi
     */
    public function runBatch(Collection $measurements, User $user): array
    {
        $measurements->loadMissing('toddler');

        $features = $measurements
            ->mapWithKeys(fn (Measurement $m): array => [$m->id => $this->ml->buildFeatures($m)])
            ->all();

        $results = $this->ml->predictMany($features);

        $success = 0;
        $errors = [];

        DB::transaction(function () use ($measurements, $user, $features, $results, &$success, &$errors): void {
            $versions = [];

            foreach ($measurements as $measurement) {
                $result = $results[$measurement->id] ?? null;

                if (! is_array($result)) {
                    $message = $result instanceof MlApiException ? $result->getMessage() : 'Tidak ada respons dari layanan prediksi.';
                    $errors[$message] = ($errors[$message] ?? 0) + 1;

                    continue;
                }

                $versionKey = (string) $result['model_version'];
                $versions[$versionKey] ??= $this->resolveModelVersion($result['model_version']);

                $this->persist($measurement, $user, $features[$measurement->id], $result, $versions[$versionKey]);
                $success++;
            }
        });

        $failed = array_sum($errors);

        $this->audit->log(
            $success > 0 ? AuditLog::ACTION_PREDICT : AuditLog::ACTION_PREDICT_FAILED,
            'prediction',
            null,
            sprintf('Prediksi massal: %d berhasil, %d gagal', $success, $failed),
            $user,
        );

        return ['success' => $success, 'failed' => $failed, 'errors' => $errors];
    }

    private function persist(Measurement $measurement, User $user, array $features, array $result, ?ModelVersion $modelVersion): Prediction
    {
        return Prediction::create([
            'toddler_id' => $measurement->toddler_id,
            'measurement_id' => $measurement->id,
            'model_version_id' => $modelVersion?->id,
            'prediction_class' => $result['prediction_class'],
            'prediction_label' => $result['prediction_label'],
            'probability' => $result['probability'],
            // Snapshot feature yang benar-benar dipakai, agar histori tidak
            // ikut berubah bila data pemeriksaan disunting kemudian.
            'input_snapshot_json' => $features,
            'request_id' => $result['request_id'],
            'predicted_by' => $user->id,
            'predicted_at' => now(),
        ]);
    }

    /**
     * Cocokkan versi model dari respons dengan katalog lokal.
     * Bila belum tercatat, versi didaftarkan tanpa metrik — metrik hanya
     * boleh terisi dari hasil training sebenarnya lewat sinkronisasi.
     */
    private function resolveModelVersion(?string $version): ?ModelVersion
    {
        if (blank($version)) {
            return null;
        }

        return ModelVersion::firstOrCreate(
            ['version' => $version],
            ['algorithm' => 'XGBoost', 'is_active' => true],
        );
    }
}
