<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Toddler extends Model
{
    /** @use HasFactory<\Database\Factories\ToddlerFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'nik',
        'name',
        'birth_date',
        'gender',
        'mother_name',
        'address',
        'village',
        'posyandu',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
        ];
    }

    // ------------------------------------------------------------- relations
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(Measurement::class);
    }

    public function latestMeasurement(): HasOne
    {
        return $this->hasOne(Measurement::class)->latestOfMany('measurement_date');
    }

    public function predictions(): HasMany
    {
        return $this->hasMany(Prediction::class);
    }

    public function latestPrediction(): HasOne
    {
        return $this->hasOne(Prediction::class)->latestOfMany('predicted_at');
    }

    // --------------------------------------------------------------- helpers
    public function genderLabel(): string
    {
        return $this->gender === 'L' ? 'Laki-laki' : 'Perempuan';
    }

    /** Usia saat ini dalam bulan, untuk tampilan daftar (bukan untuk histori). */
    public function currentAgeMonths(): int
    {
        return (int) $this->birth_date->diffInMonths(now());
    }

    public function ageLabel(?CarbonInterface $at = null): string
    {
        $months = $at
            ? (int) $this->birth_date->diffInMonths($at)
            : $this->currentAgeMonths();

        $years = intdiv($months, 12);
        $rest = $months % 12;

        if ($years === 0) {
            return $rest.' bulan';
        }

        return $rest === 0 ? $years.' tahun' : $years.' tahun '.$rest.' bulan';
    }

    /** NIK disamarkan agar tidak tampil utuh pada tabel daftar. */
    public function maskedNik(): string
    {
        if (blank($this->nik)) {
            return '-';
        }

        $nik = (string) $this->nik;
        $length = mb_strlen($nik);

        if ($length <= 6) {
            return str_repeat('*', $length);
        }

        return mb_substr($nik, 0, 4).str_repeat('*', $length - 8).mb_substr($nik, -4);
    }

    // ----------------------------------------------------------------- scopes
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $term = trim($term);

        return $query->where(function (Builder $q) use ($term): void {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%")
                ->orWhere('nik', 'like', "%{$term}%")
                ->orWhere('mother_name', 'like', "%{$term}%");
        });
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['gender'] ?? null, fn (Builder $q, $v) => $q->where('gender', $v))
            ->when($filters['village'] ?? null, fn (Builder $q, $v) => $q->where('village', $v))
            ->when($filters['posyandu'] ?? null, fn (Builder $q, $v) => $q->where('posyandu', $v));
    }

    /**
     * Kode balita berurutan: BLT-000001.
     * Dihitung dari kode terbesar yang ada, bukan dari jumlah baris,
     * agar penghapusan data tidak menyebabkan kode bentrok.
     */
    public static function nextCode(): string
    {
        $last = static::query()
            ->where('code', 'like', 'BLT-%')
            ->orderByDesc('code')
            ->value('code');

        $number = $last ? (int) substr($last, 4) : 0;

        return sprintf('BLT-%06d', $number + 1);
    }
}
