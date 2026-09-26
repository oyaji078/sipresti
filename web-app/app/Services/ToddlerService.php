<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Toddler;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ToddlerService
{
    public function __construct(private readonly AuditService $audit) {}

    public function create(array $data, User $user): Toddler
    {
        return DB::transaction(function () use ($data, $user): Toddler {
            // Kunci tabel saat mengambil kode terakhir agar dua permintaan
            // bersamaan tidak menghasilkan kode yang sama.
            $data['code'] = Toddler::nextCode();
            $data['created_by'] = $user->id;

            $toddler = Toddler::create($data);

            $this->audit->log(
                AuditLog::ACTION_CREATE,
                'toddler',
                $toddler->id,
                'Menambah data balita '.$toddler->code,
                $user,
            );

            return $toddler;
        });
    }

    public function update(Toddler $toddler, array $data, User $user): Toddler
    {
        return DB::transaction(function () use ($toddler, $data, $user): Toddler {
            $toddler->update($data);

            $this->audit->log(
                AuditLog::ACTION_UPDATE,
                'toddler',
                $toddler->id,
                'Memperbarui data balita '.$toddler->code,
                $user,
            );

            return $toddler;
        });
    }

    /**
     * @return array{deleted: bool, message: string}
     */
    public function delete(Toddler $toddler, User $user): array
    {
        if ($toddler->measurements()->exists()) {
            return [
                'deleted' => false,
                'message' => 'Balita '.$toddler->code.' tidak dapat dihapus karena masih memiliki data pemeriksaan.',
            ];
        }

        $code = $toddler->code;

        DB::transaction(function () use ($toddler, $user, $code): void {
            $id = $toddler->id;
            $toddler->delete();

            $this->audit->log(
                AuditLog::ACTION_DELETE,
                'toddler',
                $id,
                'Menghapus data balita '.$code,
                $user,
            );
        });

        return ['deleted' => true, 'message' => 'Data balita '.$code.' berhasil dihapus.'];
    }

    /** Daftar desa yang benar-benar ada di database, untuk isian filter. */
    public function villages(): array
    {
        return Toddler::query()
            ->whereNotNull('village')
            ->where('village', '!=', '')
            ->distinct()
            ->orderBy('village')
            ->pluck('village')
            ->all();
    }

    /** Daftar posyandu yang benar-benar ada di database, untuk isian filter. */
    public function posyandus(): array
    {
        return Toddler::query()
            ->whereNotNull('posyandu')
            ->where('posyandu', '!=', '')
            ->distinct()
            ->orderBy('posyandu')
            ->pluck('posyandu')
            ->all();
    }
}
