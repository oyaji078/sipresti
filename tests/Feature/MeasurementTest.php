<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MeasurementSource;
use App\Models\Measurement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MeasurementTest extends TestCase
{
    use RefreshDatabase;

    public function test_tambah_pemeriksaan_dan_usia_dihitung_dari_tanggal_pemeriksaan(): void
    {
        $petugas = $this->petugas();
        $toddler = $this->toddlerFor($petugas, ['birth_date' => '2024-03-15']);

        $this->actingAs($petugas)->post('/pemeriksaan', [
            'toddler_id' => $toddler->id,
            'measurement_date' => '2026-09-20',
            'weight_kg' => 11.4,
            'height_cm' => 86.5,
        ])->assertRedirect();

        $measurement = Measurement::first();

        // 15 Mar 2024 -> 20 Sep 2026 = 30 bulan penuh.
        $this->assertSame(30, $measurement->age_months);
        $this->assertSame(MeasurementSource::MANUAL, $measurement->source);
        $this->assertSame($petugas->id, $measurement->created_by);
    }

    public function test_usia_tidak_dihitung_dari_tanggal_hari_ini(): void
    {
        $petugas = $this->petugas();
        $toddler = $this->toddlerFor($petugas, ['birth_date' => Carbon::now()->subMonths(40)->toDateString()]);

        // Pemeriksaan dua tahun lalu: usia saat itu harus 40 - 24 = 16 bulan.
        $this->actingAs($petugas)->post('/pemeriksaan', [
            'toddler_id' => $toddler->id,
            'measurement_date' => Carbon::now()->subMonths(24)->toDateString(),
            'weight_kg' => 9.0,
            'height_cm' => 76.0,
        ]);

        $this->assertSame(16, Measurement::first()->age_months);
    }

    public function test_perhitungan_usia_membulatkan_ke_bawah(): void
    {
        $birth = Carbon::parse('2025-01-31');

        $this->assertSame(0, Measurement::calculateAgeMonths($birth, Carbon::parse('2025-02-15')));
        $this->assertSame(1, Measurement::calculateAgeMonths($birth, Carbon::parse('2025-03-01')));
        $this->assertSame(12, Measurement::calculateAgeMonths($birth, Carbon::parse('2026-02-01')));
        $this->assertSame(0, Measurement::calculateAgeMonths($birth, Carbon::parse('2024-01-01')));
    }

    public function test_tanggal_pemeriksaan_sebelum_tanggal_lahir_ditolak(): void
    {
        $petugas = $this->petugas();
        $toddler = $this->toddlerFor($petugas, ['birth_date' => '2025-06-01']);

        $this->actingAs($petugas)->post('/pemeriksaan', [
            'toddler_id' => $toddler->id,
            'measurement_date' => '2025-01-01',
            'weight_kg' => 8.0,
            'height_cm' => 70.0,
        ])->assertSessionHasErrors('measurement_date');

        $this->assertDatabaseCount('measurements', 0);
    }

    public function test_tanggal_pemeriksaan_masa_depan_ditolak(): void
    {
        $petugas = $this->petugas();
        $toddler = $this->toddlerFor($petugas);

        $this->actingAs($petugas)->post('/pemeriksaan', [
            'toddler_id' => $toddler->id,
            'measurement_date' => Carbon::now()->addMonth()->toDateString(),
            'weight_kg' => 11.0,
            'height_cm' => 85.0,
        ])->assertSessionHasErrors('measurement_date');
    }

    public function test_berat_negatif_ditolak(): void
    {
        $petugas = $this->petugas();
        $toddler = $this->toddlerFor($petugas);

        $this->actingAs($petugas)->post('/pemeriksaan', [
            'toddler_id' => $toddler->id,
            'measurement_date' => Carbon::now()->toDateString(),
            'weight_kg' => -5,
            'height_cm' => 85.0,
        ])->assertSessionHasErrors('weight_kg');
    }

    public function test_berat_dan_tinggi_di_luar_batas_teknis_ditolak(): void
    {
        $petugas = $this->petugas();
        $toddler = $this->toddlerFor($petugas);

        $this->actingAs($petugas)->post('/pemeriksaan', [
            'toddler_id' => $toddler->id,
            'measurement_date' => Carbon::now()->toDateString(),
            'weight_kg' => 250,
            'height_cm' => 400,
        ])->assertSessionHasErrors(['weight_kg', 'height_cm']);
    }

    public function test_isian_bukan_angka_ditolak(): void
    {
        $petugas = $this->petugas();
        $toddler = $this->toddlerFor($petugas);

        $this->actingAs($petugas)->post('/pemeriksaan', [
            'toddler_id' => $toddler->id,
            'measurement_date' => Carbon::now()->toDateString(),
            'weight_kg' => 'sebelas',
            'height_cm' => 'delapan puluh',
        ])->assertSessionHasErrors(['weight_kg', 'height_cm']);
    }

    public function test_pemeriksaan_ganda_pada_tanggal_sama_ditolak(): void
    {
        $petugas = $this->petugas();
        $toddler = $this->toddlerFor($petugas);
        $date = Carbon::now()->subDays(3)->toDateString();

        $this->measurementFor($toddler, $petugas, ['measurement_date' => $date]);

        $this->actingAs($petugas)->post('/pemeriksaan', [
            'toddler_id' => $toddler->id,
            'measurement_date' => $date,
            'weight_kg' => 12.0,
            'height_cm' => 88.0,
        ])->assertSessionHasErrors('measurement_date');

        $this->assertDatabaseCount('measurements', 1);
    }

    public function test_ubah_pemeriksaan_menghitung_ulang_usia(): void
    {
        $petugas = $this->petugas();
        $toddler = $this->toddlerFor($petugas, ['birth_date' => '2024-03-15']);
        $measurement = $this->measurementFor($toddler, $petugas, ['measurement_date' => '2026-03-15']);

        $this->assertSame(24, $measurement->age_months);

        $this->actingAs($petugas)->put("/pemeriksaan/{$measurement->id}", [
            'measurement_date' => '2026-09-20',
            'weight_kg' => 12.0,
            'height_cm' => 88.0,
        ])->assertRedirect();

        $this->assertSame(30, $measurement->fresh()->age_months);
    }

    public function test_hapus_pemeriksaan_tanpa_prediksi_berhasil(): void
    {
        $petugas = $this->petugas();
        $toddler = $this->toddlerFor($petugas);
        $measurement = $this->measurementFor($toddler, $petugas);

        $this->actingAs($petugas)->delete("/pemeriksaan/{$measurement->id}")->assertRedirect();

        $this->assertDatabaseCount('measurements', 0);
    }

    public function test_satu_balita_dapat_memiliki_banyak_pemeriksaan(): void
    {
        $petugas = $this->petugas();
        $toddler = $this->toddlerFor($petugas, ['birth_date' => '2024-01-10']);

        foreach (['2026-03-10', '2026-06-10', '2026-09-10'] as $date) {
            $this->measurementFor($toddler, $petugas, ['measurement_date' => $date]);
        }

        $this->assertSame(3, $toddler->measurements()->count());
        $this->assertSame([26, 29, 32], $toddler->measurements()->orderBy('measurement_date')->pluck('age_months')->all());
    }

    public function test_paginasi_daftar_pemeriksaan_memakai_markup_bootstrap(): void
    {
        $petugas = $this->petugas();
        $toddler = $this->toddlerFor($petugas, ['birth_date' => '2024-01-01']);

        foreach (range(1, 16) as $day) {
            $this->measurementFor($toddler, $petugas, ['measurement_date' => sprintf('2026-08-%02d', $day)]);
        }

        $this->actingAs($petugas)
            ->get('/pemeriksaan')
            ->assertOk()
            ->assertSee('class="page-link"', false)
            ->assertDontSee('<svg class="w-5 h-5"', false);
    }
}
