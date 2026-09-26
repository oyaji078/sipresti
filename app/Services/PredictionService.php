<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\MlApiException;
use App\Models\AuditLog;
use App\Models\Measurement;
use App\Models\ModelVersion;
use App\Models\Prediction;
use App\Models\User;
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
            $prediction = Prediction::create([
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
