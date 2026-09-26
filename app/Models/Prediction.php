<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prediction extends Model
{
    /** @use HasFactory<\Database\Factories\PredictionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'toddler_id',
        'measurement_id',
        'model_version_id',
        'prediction_class',
        'prediction_label',
        'probability',
        'input_snapshot_json',
        'request_id',
        'predicted_by',
        'predicted_at',
    ];

    protected function casts(): array
    {
        return [
            'input_snapshot_json' => 'array',
            'probability' => 'float',
            'predicted_at' => 'datetime',
        ];
    }

    // ------------------------------------------------------------- relations
    public function toddler(): BelongsTo
    {
        return $this->belongsTo(Toddler::class);
    }

    public function measurement(): BelongsTo
    {
        return $this->belongsTo(Measurement::class);
    }

    public function modelVersion(): BelongsTo
    {
        return $this->belongsTo(ModelVersion::class);
    }

    public function predictor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'predicted_by');
    }

    // --------------------------------------------------------------- helpers
    /**
     * Label dianggap "berisiko" bila memuat kata RISIKO/STUNTING dan bukan
     * bentuk negatifnya. Label sebenarnya berasal dari label_mapping model,
     * jadi pencocokan dibuat toleran alih-alih mengunci satu nilai tertentu.
     */
    public function isAtRisk(): bool
    {
        $label = mb_strtoupper((string) $this->prediction_label);

        if (str_contains($label, 'TIDAK') || str_contains($label, 'NORMAL')) {
            return false;
        }

        return str_contains($label, 'RISIKO') || str_contains($label, 'STUNTING');
    }

    public function statusLabel(): string
    {
        return $this->isAtRisk() ? 'Berisiko Stunting' : 'Tidak Terindikasi Risiko';
    }

    public function statusClass(): string
    {
        return $this->isAtRisk() ? 'danger' : 'success';
    }

    public function statusIcon(): string
    {
        return $this->isAtRisk() ? 'bi-exclamation-triangle-fill' : 'bi-shield-check';
    }

    public function probabilityPercent(): string
    {
        return number_format($this->probability * 100, 2, ',', '.').'%';
    }

    // ----------------------------------------------------------------- scopes
    public function scopeAtRisk(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->where('prediction_label', 'like', '%RISIKO%')
                ->orWhere('prediction_label', 'like', '%STUNTING%');
        })->where(function (Builder $q): void {
            $q->where('prediction_label', 'not like', '%TIDAK%')
                ->where('prediction_label', 'not like', '%NORMAL%');
        });
    }

    public function scopeNotAtRisk(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->where('prediction_label', 'like', '%TIDAK%')
                ->orWhere('prediction_label', 'like', '%NORMAL%');
        });
    }
}
