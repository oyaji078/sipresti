<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModelVersion extends Model
{
    /** @use HasFactory<\Database\Factories\ModelVersionFactory> */
    use HasFactory;

    protected $fillable = [
        'version',
        'algorithm',
        'artifact_name',
        'trained_at',
        'dataset_size',
        'training_size',
        'testing_size',
        'accuracy',
        'precision_score',
        'recall',
        'f1_score',
        'parameters_json',
        'features_json',
        'checksum',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'trained_at' => 'datetime',
            'parameters_json' => 'array',
            'features_json' => 'array',
            'is_active' => 'boolean',
            'accuracy' => 'float',
            'precision_score' => 'float',
            'recall' => 'float',
            'f1_score' => 'float',
        ];
    }

    public function predictions(): HasMany
    {
        return $this->hasMany(Prediction::class);
    }

    public static function active(): ?self
    {
        return static::query()->where('is_active', true)->first();
    }

    /**
     * Format metrik untuk tampilan. Mengembalikan '-' bila metrik belum ada,
     * karena angka metrik dilarang dikarang.
     */
    public function metricLabel(?float $value): string
    {
        return $value === null ? '-' : number_format($value * 100, 2, ',', '.').'%';
    }

    public function hasMetrics(): bool
    {
        return $this->accuracy !== null
            || $this->precision_score !== null
            || $this->recall !== null
            || $this->f1_score !== null;
    }
}
