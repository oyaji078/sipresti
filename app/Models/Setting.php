<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = [
        'setting_key',
        'setting_value',
        'type',
        'group_name',
        'description',
        'updated_by',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('sipresti.settings'));
        static::deleted(fn () => Cache::forget('sipresti.settings'));
    }

    /** @return array<string, string|null> */
    public static function allValues(): array
    {
        return Cache::rememberForever(
            'sipresti.settings',
            fn () => static::query()->pluck('setting_value', 'setting_key')->all()
        );
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::allValues()[$key] ?? $default;
    }

    public static function put(string $key, mixed $value, ?int $userId = null): void
    {
        static::updateOrCreate(
            ['setting_key' => $key],
            ['setting_value' => $value, 'updated_by' => $userId]
        );
    }
}
