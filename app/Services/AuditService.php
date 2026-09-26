<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Pencatat jejak aktivitas pengguna.
 *
 * Deskripsi sengaja dibuat ringkas dan tidak memuat data sensitif balita
 * (NIK, alamat, nama ibu) maupun feature prediksi secara lengkap.
 */
class AuditService
{
    public function __construct(private readonly Request $request) {}

    public function log(
        string $action,
        ?string $entity = null,
        ?int $entityId = null,
        ?string $description = null,
        ?User $user = null,
    ): void {
        $user ??= $this->request->user();

        try {
            AuditLog::create([
                'user_id' => $user?->id,
                'action' => $action,
                'entity' => $entity,
                'entity_id' => $entityId,
                'description' => $description ? mb_substr($description, 0, 255) : null,
                'ip_address' => $this->request->ip(),
                'user_agent' => mb_substr((string) $this->request->userAgent(), 0, 255),
            ]);
        } catch (\Throwable $e) {
            // Kegagalan audit tidak boleh menggagalkan aksi utama pengguna,
            // tetapi tetap harus terlihat di log aplikasi.
            Log::error('Gagal menulis audit log', [
                'action' => $action,
                'entity' => $entity,
                'entity_id' => $entityId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function login(User $user): void
    {
        $this->log(AuditLog::ACTION_LOGIN, 'user', $user->id, 'Login berhasil', $user);
    }

    public function loginFailed(string $identifier): void
    {
        $this->log(
            AuditLog::ACTION_LOGIN_FAILED,
            'user',
            null,
            'Percobaan login gagal untuk: '.mb_substr($identifier, 0, 60)
        );
    }

    public function logout(User $user): void
    {
        $this->log(AuditLog::ACTION_LOGOUT, 'user', $user->id, 'Logout', $user);
    }
}
