<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MeasurementSource;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Measurement extends Model
{
    /** @use HasFactory<\Database\Factories\MeasurementFactory> */
    use HasFactory;

    protected $fillable = [
        'toddler_id',
        'measurement_date',
        'age_months',
        'weight_kg',
        'height_cm',
        'muac_cm',
        'source',
        'import_history_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'measurement_date' => 'date',
            'age_months' => 'integer',
            'weight_kg' => 'decimal:2',
            'height_cm' => 'decimal:1',
            'muac_cm' => 'decimal:1',
            'source' => MeasurementSource::class,
        ];
    }

    // ------------------------------------------------------------- relations
    public function toddler(): BelongsTo
    {
        return $this->belongsTo(Toddler::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function importHistory(): BelongsTo
    {
        return $this->belongsTo(ImportHistory::class);
    }

    public function predictions(): HasMany
    {
        return $this->hasMany(Prediction::class);
    }

    // --------------------------------------------------------------- helpers
    /**
     * Usia dalam bulan penuh antara tanggal lahir dan tanggal pemeriksaan.
     * Sengaja TIDAK memakai tanggal hari ini, agar data histori tidak bergeser.
     */
    public static function calculateAgeMonths(CarbonInterface $birthDate, CarbonInterface $measurementDate): int
    {
        if ($measurementDate->lt($birthDate)) {
            return 0;
        }

        return (int) $birthDate->copy()->startOfDay()->diffInMonths($measurementDate->copy()->startOfDay());
    }

    public function ageLabel(): string
    {
        $years = intdiv($this->age_months, 12);
        $months = $this->age_months % 12;

        if ($years === 0) {
            return $months.' bulan';
        }

        return $months === 0 ? $years.' tahun' : $years.' tahun '.$months.' bulan';
    }

    public function hasPrediction(): bool
    {
        return $this->predictions()->exists();
    }
}
