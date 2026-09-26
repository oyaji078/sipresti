<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportHistory extends Model
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_PREVIEW = 'PREVIEW';
    public const STATUS_COMMITTED = 'COMMITTED';
    public const STATUS_FAILED = 'FAILED';

    /** Jumlah balita lama yang profilnya diperbarui saat commit (tidak disimpan). */
    public int $toddlersUpdated = 0;

    protected $fillable = [
        'user_id',
        'original_name',
        'stored_name',
        'file_hash',
        'total_rows',
        'success_rows',
        'failed_rows',
        'duplicate_rows',
        'invalid_rows',
        'column_mapping_json',
        'error_report_json',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'column_mapping_json' => 'array',
            'error_report_json' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(Measurement::class);
    }

    public function badgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_COMMITTED => 'text-bg-success',
            self::STATUS_FAILED => 'text-bg-danger',
            self::STATUS_PREVIEW => 'text-bg-warning',
            default => 'text-bg-secondary',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_COMMITTED => 'Berhasil',
            self::STATUS_FAILED => 'Gagal',
            self::STATUS_PREVIEW => 'Menunggu Konfirmasi',
            default => 'Diproses',
        };
    }
}
