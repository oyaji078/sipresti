<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'status',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
        ];
    }

    // ------------------------------------------------------------- relations
    public function toddlers(): HasMany
    {
        return $this->hasMany(Toddler::class, 'created_by');
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(Measurement::class, 'created_by');
    }

    public function predictions(): HasMany
    {
        return $this->hasMany(Prediction::class, 'predicted_by');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function importHistories(): HasMany
    {
        return $this->hasMany(ImportHistory::class);
    }

    // --------------------------------------------------------------- helpers
    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN;
    }

    public function isPetugasGizi(): bool
    {
        return $this->role === UserRole::PETUGAS_GIZI;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::ACTIVE;
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        $letters = array_map(static fn (string $p): string => mb_strtoupper(mb_substr($p, 0, 1)), array_slice($parts, 0, 2));

        return implode('', $letters) ?: 'U';
    }

    // ----------------------------------------------------------------- scopes
    public function scopeActive($query)
    {
        return $query->where('status', UserStatus::ACTIVE->value);
    }
}
