<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Otorisasi wajib ditegakkan di backend, bukan hanya dengan menyembunyikan
 * menu. Test ini memanggil route secara langsung.
 */
class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0:string}> */
    public static function ruteKhususPetugas(): array
    {
        return [
            'form tambah balita' => ['/balita-tambah'],
            'form tambah pemeriksaan' => ['/pemeriksaan-tambah'],
            'form prediksi' => ['/prediksi-baru'],
            'import' => ['/import'],
            'monitoring' => ['/monitoring'],
            'laporan' => ['/laporan'],
        ];
    }

    /** @return array<string, array{0:string}> */
    public static function ruteKhususAdmin(): array
    {
        return [
            'daftar pengguna' => ['/admin/users'],
            'tambah pengguna' => ['/admin/users/create'],
            'model xgboost' => ['/admin/models'],
            'metrics model' => ['/admin/model-metrics'],
            'audit log' => ['/admin/audit-logs'],
            'pengaturan' => ['/admin/settings'],
        ];
    }

    #[DataProvider('ruteKhususAdmin')]
    public function test_petugas_gizi_tidak_boleh_masuk_area_admin(string $route): void
    {
        $this->actingAs($this->petugas())->get($route)->assertForbidden();
    }

    #[DataProvider('ruteKhususPetugas')]
    public function test_admin_tidak_boleh_menulis_data_klinis(string $route): void
    {
        $this->actingAs($this->admin())->get($route)->assertForbidden();
    }

    #[DataProvider('ruteKhususAdmin')]
    public function test_admin_boleh_masuk_area_admin(string $route): void
    {
        $this->actingAs($this->admin())->get($route)->assertOk();
    }

    #[DataProvider('ruteKhususPetugas')]
    public function test_petugas_boleh_masuk_menu_operasional(string $route): void
    {
        $this->actingAs($this->petugas())->get($route)->assertOk();
    }

    public function test_kedua_peran_dapat_melihat_daftar_balita_dan_pemeriksaan(): void
    {
        foreach ([$this->admin(), $this->petugas()] as $user) {
            $this->actingAs($user)->get('/balita')->assertOk();
            $this->actingAs($user)->get('/pemeriksaan')->assertOk();
            $this->actingAs($user)->get('/prediksi')->assertOk();
            $this->actingAs($user)->get('/dashboard')->assertOk();
            $this->actingAs($user)->get('/profil')->assertOk();
        }
    }

    public function test_admin_tidak_dapat_menyimpan_balita_lewat_post(): void
    {
        $this->actingAs($this->admin())
            ->post('/balita', [
                'name' => 'Percobaan Admin',
                'birth_date' => now()->subYear()->toDateString(),
                'gender' => 'L',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('toddlers', 0);
    }

    public function test_admin_tidak_dapat_menjalankan_prediksi(): void
    {
        $petugas = $this->petugas();
        $toddler = $this->toddlerFor($petugas);
        $measurement = $this->measurementFor($toddler, $petugas);

        $this->actingAs($this->admin())
            ->post('/prediksi', ['measurement_id' => $measurement->id])
            ->assertForbidden();

        $this->assertDatabaseCount('predictions', 0);
    }

    public function test_petugas_tidak_dapat_mengubah_pengguna(): void
    {
        $target = $this->petugas();

        $this->actingAs($this->petugas())
            ->put("/admin/users/{$target->id}", [
                'name' => 'Diubah',
                'username' => 'diubah',
                'email' => 'diubah@test.local',
                'role' => 'ADMIN',
                'status' => 'ACTIVE',
            ])
            ->assertForbidden();

        $this->assertSame($target->name, $target->fresh()->name);
    }
}
