<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    public const ACTION_LOGIN = 'LOGIN';
    public const ACTION_LOGOUT = 'LOGOUT';
    public const ACTION_LOGIN_FAILED = 'LOGIN_FAILED';
    public const ACTION_CREATE = 'CREATE';
    public const ACTION_UPDATE = 'UPDATE';
    public const ACTION_DELETE = 'DELETE';
    public const ACTION_IMPORT = 'IMPORT';
    public const ACTION_PREDICT = 'PREDICT';
    public const ACTION_PREDICT_FAILED = 'PREDICT_FAILED';
    public const ACTION_USER_CREATE = 'USER_CREATE';
    public const ACTION_USER_UPDATE = 'USER_UPDATE';
    public const ACTION_SETTING_UPDATE = 'SETTING_UPDATE';
    public const ACTION_MODEL_SYNC = 'MODEL_SYNC';

    protected $fillable = [
        'user_id',
        'action',
        'entity',
        'entity_id',
        'description',
        'ip_address',
        'user_agent',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function badgeClass(): string
    {
        return match ($this->action) {
            self::ACTION_LOGIN, self::ACTION_CREATE, self::ACTION_USER_CREATE => 'text-bg-success',
            self::ACTION_UPDATE, self::ACTION_USER_UPDATE, self::ACTION_SETTING_UPDATE => 'text-bg-warning',
            self::ACTION_DELETE, self::ACTION_LOGIN_FAILED, self::ACTION_PREDICT_FAILED => 'text-bg-danger',
            self::ACTION_PREDICT => 'text-bg-primary',
            self::ACTION_IMPORT, self::ACTION_MODEL_SYNC => 'text-bg-info',
            default => 'text-bg-secondary',
        };
    }

    /** @return array<int, string> */
    public static function actions(): array
    {
        return [
            self::ACTION_LOGIN,
            self::ACTION_LOGOUT,
            self::ACTION_LOGIN_FAILED,
            self::ACTION_CREATE,
            self::ACTION_UPDATE,
            self::ACTION_DELETE,
            self::ACTION_IMPORT,
            self::ACTION_PREDICT,
            self::ACTION_PREDICT_FAILED,
            self::ACTION_USER_CREATE,
            self::ACTION_USER_UPDATE,
            self::ACTION_SETTING_UPDATE,
            self::ACTION_MODEL_SYNC,
        ];
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['action'] ?? null, fn (Builder $q, $v) => $q->where('action', $v))
            ->when($filters['user_id'] ?? null, fn (Builder $q, $v) => $q->where('user_id', $v))
            ->when($filters['date_from'] ?? null, fn (Builder $q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['date_to'] ?? null, fn (Builder $q, $v) => $q->whereDate('created_at', '<=', $v));
    }
}
