<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MeasurementSource;
use App\Models\AuditLog;
use App\Models\Measurement;
use App\Models\Toddler;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MeasurementService
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * Usia dihitung dari tanggal lahir dan TANGGAL PEMERIKSAAN, lalu disimpan
     * sebagai snapshot. Tidak pernah dari tanggal hari ini.
     */
    public function resolveAgeMonths(Toddler $toddler, string $measurementDate): int
    {
        return Measurement::calculateAgeMonths(
            $toddler->birth_date,
            Carbon::parse($measurementDate),
        );
    }

    public function create(array $data, User $user): Measurement
    {
        $toddler = Toddler::findOrFail($data['toddler_id']);

        return DB::transaction(function () use ($data, $user, $toddler): Measurement {
            $data['age_months'] = $this->resolveAgeMonths($toddler, $data['measurement_date']);
            $data['source'] = MeasurementSource::MANUAL->value;
            $data['created_by'] = $user->id;

            $measurement = Measurement::create($data);

            $this->audit->log(
                AuditLog::ACTION_CREATE,
                'measurement',
                $measurement->id,
                'Menambah pemeriksaan balita '.$toddler->code.' tanggal '.$measurement->measurement_date->format('d/m/Y'),
                $user,
            );

            return $measurement;
        });
    }

    public function update(Measurement $measurement, array $data, User $user): Measurement
    {
        return DB::transaction(function () use ($measurement, $data, $user): Measurement {
            $toddler = $measurement->toddler;
            $data['age_months'] = $this->resolveAgeMonths($toddler, $data['measurement_date']);

            $measurement->update($data);

            $this->audit->log(
                AuditLog::ACTION_UPDATE,
                'measurement',
                $measurement->id,
                'Memperbarui pemeriksaan balita '.$toddler->code.' tanggal '.$measurement->measurement_date->format('d/m/Y'),
                $user,
            );

            return $measurement;
        });
    }

    /**
     * @return array{deleted: bool, message: string}
     */
    public function delete(Measurement $measurement, User $user): array
    {
        if ($measurement->predictions()->exists()) {
            return [
                'deleted' => false,
                'message' => 'Pemeriksaan ini tidak dapat dihapus karena sudah memiliki hasil prediksi.',
            ];
        }

        $code = $measurement->toddler->code;
        $date = $measurement->measurement_date->format('d/m/Y');

        DB::transaction(function () use ($measurement, $user, $code, $date): void {
            $id = $measurement->id;
            $measurement->delete();

            $this->audit->log(
                AuditLog::ACTION_DELETE,
                'measurement',
                $id,
                'Menghapus pemeriksaan balita '.$code.' tanggal '.$date,
                $user,
            );
        });

        return ['deleted' => true, 'message' => 'Data pemeriksaan berhasil dihapus.'];
    }
}
