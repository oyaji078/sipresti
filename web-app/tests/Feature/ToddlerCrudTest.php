<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Toddler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToddlerCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_tambah_balita_valid(): void
    {
        $petugas = $this->petugas();

        $response = $this->actingAs($petugas)->post('/balita', [
            'name' => 'Aisyah Putri',
            'nik' => '5203010101230001',
            'birth_date' => now()->subMonths(24)->toDateString(),
            'gender' => 'P',
            'mother_name' => 'Ibu Aisyah',
            'village' => 'Lepak',
            'posyandu' => 'Melati',
        ]);

        $toddler = Toddler::first();

        $response->assertRedirect(route('balita.show', $toddler));
        $this->assertSame('Aisyah Putri', $toddler->name);
        $this->assertSame('BLT-000001', $toddler->code);
        $this->assertSame($petugas->id, $toddler->created_by);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditLog::ACTION_CREATE,
            'entity' => 'toddler',
            'entity_id' => $toddler->id,
        ]);
    }

    public function test_kode_balita_berurutan(): void
    {
        $petugas = $this->petugas();

        foreach (['Anak A', 'Anak B', 'Anak C'] as $name) {
            $this->actingAs($petugas)->post('/balita', [
                'name' => $name,
                'birth_date' => now()->subMonths(20)->toDateString(),
                'gender' => 'L',
            ]);
        }

        $this->assertSame(
            ['BLT-000001', 'BLT-000002', 'BLT-000003'],
            Toddler::orderBy('id')->pluck('code')->all()
        );
    }

    public function test_tambah_balita_tanpa_nama_ditolak(): void
    {
        $this->actingAs($this->petugas())
            ->post('/balita', ['name' => '', 'birth_date' => now()->subYear()->toDateString(), 'gender' => 'L'])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('toddlers', 0);
    }

    public function test_tanggal_lahir_masa_depan_ditolak(): void
    {
        $this->actingAs($this->petugas())
            ->post('/balita', [
                'name' => 'Anak Masa Depan',
                'birth_date' => now()->addYear()->toDateString(),
                'gender' => 'L',
            ])
            ->assertSessionHasErrors('birth_date');

        $this->assertDatabaseCount('toddlers', 0);
    }

    public function test_nik_bukan_enam_belas_digit_ditolak(): void
    {
        $this->actingAs($this->petugas())
            ->post('/balita', [
                'name' => 'Anak NIK Pendek',
                'nik' => '123',
                'birth_date' => now()->subYear()->toDateString(),
                'gender' => 'L',
            ])
            ->assertSessionHasErrors('nik');
    }

    public function test_nik_duplikat_ditolak(): void
    {
        $petugas = $this->petugas();
        $this->toddlerFor($petugas, ['nik' => '5203010101230001']);

        $this->actingAs($petugas)
            ->post('/balita', [
                'name' => 'Anak Kedua',
                'nik' => '5203010101230001',
                'birth_date' => now()->subYear()->toDateString(),
                'gender' => 'P',
            ])
            ->assertSessionHasErrors('nik');
    }

    public function test_jenis_kelamin_di_luar_pilihan_ditolak(): void
    {
        $this->actingAs($this->petugas())
            ->post('/balita', [
                'name' => 'Anak Uji',
                'birth_date' => now()->subYear()->toDateString(),
                'gender' => 'X',
            ])
            ->assertSessionHasErrors('gender');
    }

    public function test_ubah_balita(): void
    {
        $petugas = $this->petugas();
        $toddler = $this->toddlerFor($petugas, ['name' => 'Nama Lama']);

        $this->actingAs($petugas)->put("/balita/{$toddler->id}", [
            'name' => 'Nama Baru',
            'nik' => $toddler->nik,
            'birth_date' => $toddler->birth_date->toDateString(),
            'gender' => $toddler->gender,
            'village' => 'Menceh',
        ])->assertRedirect(route('balita.show', $toddler));

        $this->assertSame('Nama Baru', $toddler->fresh()->name);
        $this->assertSame('Menceh', $toddler->fresh()->village);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditLog::ACTION_UPDATE, 'entity' => 'toddler']);
    }

    public function test_hapus_balita_tanpa_pemeriksaan_berhasil(): void
    {
        $petugas = $this->petugas();
        $toddler = $this->toddlerFor($petugas);

        $this->actingAs($petugas)->delete("/balita/{$toddler->id}")->assertRedirect(route('balita.index'));

        $this->assertDatabaseCount('toddlers', 0);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditLog::ACTION_DELETE, 'entity' => 'toddler']);
    }

    public function test_hapus_balita_yang_punya_pemeriksaan_ditolak(): void
    {
        $petugas = $this->petugas();
        $toddler = $this->toddlerFor($petugas);
        $this->measurementFor($toddler, $petugas);

        $this->actingAs($petugas)
            ->delete("/balita/{$toddler->id}")
            ->assertRedirect(route('balita.show', $toddler))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('toddlers', 1);
    }

    public function test_pencarian_dan_filter_daftar_balita(): void
    {
        $petugas = $this->petugas();
        $this->toddlerFor($petugas, ['name' => 'Aisyah Putri', 'gender' => 'P', 'village' => 'Lepak']);
        $this->toddlerFor($petugas, ['name' => 'Bagas Pratama', 'gender' => 'L', 'village' => 'Menceh']);

        $this->actingAs($petugas)->get('/balita?q=Aisyah')
            ->assertOk()->assertSee('Aisyah Putri')->assertDontSee('Bagas Pratama');

        $this->actingAs($petugas)->get('/balita?gender=L')
            ->assertOk()->assertSee('Bagas Pratama')->assertDontSee('Aisyah Putri');

        $this->actingAs($petugas)->get('/balita?village=Lepak')
            ->assertOk()->assertSee('Aisyah Putri')->assertDontSee('Bagas Pratama');
    }

    public function test_nik_disamarkan_pada_daftar(): void
    {
        $petugas = $this->petugas();
        $this->toddlerFor($petugas, ['nik' => '5203010101239999']);

        $this->actingAs($petugas)->get('/balita')
            ->assertOk()
            ->assertDontSee('5203010101239999')
            ->assertSee('5203********9999');
    }

    public function test_halaman_detail_menampilkan_data_balita(): void
    {
        $petugas = $this->petugas();
        $toddler = $this->toddlerFor($petugas, ['name' => 'Zahra Aulia']);
        $this->measurementFor($toddler, $petugas);

        $this->actingAs($petugas)->get("/balita/{$toddler->id}")
            ->assertOk()
            ->assertSee('Zahra Aulia')
            ->assertSee($toddler->code)
            ->assertSee('Grafik Pertumbuhan');
    }
}
