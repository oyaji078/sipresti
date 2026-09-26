<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Prediction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardReportTest extends TestCase
{
    use RefreshDatabase;

    private function buatPrediksi(string $label, array $overrides = []): Prediction
    {
        $petugas = $overrides['user'] ?? $this->petugas();
        $toddler = $overrides['toddler'] ?? $this->toddlerFor($petugas);
        $measurement = $this->measurementFor($toddler, $petugas, [
            'measurement_date' => $overrides['date'] ?? Carbon::now()->subDays(random_int(1, 20))->toDateString(),
        ]);

        return Prediction::create([
            'toddler_id' => $toddler->id,
            'measurement_id' => $measurement->id,
            'model_version_id' => null,
            'prediction_class' => $label === 'BERISIKO' ? '0' : '1',
            'prediction_label' => $label,
            'probability' => 0.75,
            'input_snapshot_json' => [
                'gender' => $toddler->gender,
                'age_months' => $measurement->age_months,
                'weight_kg' => (float) $measurement->weight_kg,
                'height_cm' => (float) $measurement->height_cm,
            ],
            'request_id' => (string) Str::uuid(),
            'predicted_by' => $petugas->id,
            'predicted_at' => $overrides['predicted_at'] ?? Carbon::now(),
        ]);
    }

    public function test_dashboard_kosong_menampilkan_angka_nol_bukan_data_contoh(): void
    {
        $this->actingAs($this->petugas())
            ->get('/api/internal/dashboard/stats')
            ->assertOk()
            ->assertJson([
                'counters' => [
                    'toddlers' => 0,
                    'measurements' => 0,
                    'predictions' => 0,
                    'at_risk' => 0,
                    'not_at_risk' => 0,
                ],
                'distribution' => [],
            ]);
    }

    public function test_dashboard_menghitung_dari_database(): void
    {
        $this->buatPrediksi('BERISIKO');
        $this->buatPrediksi('BERISIKO');
        $this->buatPrediksi('TIDAK_BERISIKO');

        $response = $this->actingAs($this->petugas())->get('/api/internal/dashboard/stats')->assertOk();

        $counters = $response->json('counters');

        $this->assertSame(3, $counters['toddlers']);
        $this->assertSame(3, $counters['measurements']);
        $this->assertSame(3, $counters['predictions']);
        $this->assertSame(2, $counters['at_risk']);
        $this->assertSame(1, $counters['not_at_risk']);

        $this->assertSame(['BERISIKO' => 2, 'TIDAK_BERISIKO' => 1], $response->json('distribution'));
    }

    public function test_tren_bulanan_memiliki_dua_belas_titik(): void
    {
        $this->buatPrediksi('BERISIKO');

        $response = $this->actingAs($this->petugas())->get('/api/internal/dashboard/stats')->assertOk();

        $this->assertCount(12, $response->json('measurement_trend.labels'));
        $this->assertCount(12, $response->json('measurement_trend.values'));
        $this->assertCount(12, $response->json('prediction_trend.values'));
    }

    public function test_halaman_dashboard_menampilkan_kartu_statistik(): void
    {
        $this->buatPrediksi('BERISIKO');

        $this->actingAs($this->petugas())->get('/dashboard')
            ->assertOk()
            ->assertSee('Total Balita')
            ->assertSee('Total Pemeriksaan')
            ->assertSee('Total Prediksi')
            ->assertSee('Berisiko')
            ->assertSee('Tidak Berisiko');
    }

    public function test_data_grafik_pertumbuhan_berurut_menurut_tanggal(): void
    {
        $petugas = $this->petugas();
        $toddler = $this->toddlerFor($petugas, ['birth_date' => '2024-01-10']);

        $this->measurementFor($toddler, $petugas, ['measurement_date' => '2026-09-10', 'weight_kg' => 12.5, 'height_cm' => 90.0]);
        $this->measurementFor($toddler, $petugas, ['measurement_date' => '2026-03-10', 'weight_kg' => 11.0, 'height_cm' => 86.0]);

        $response = $this->actingAs($petugas)->get("/api/internal/balita/{$toddler->id}/growth")->assertOk();

        // assertEquals, bukan assertSame: JSON mengembalikan 11.0 sebagai int 11.
        $this->assertEquals([11.0, 12.5], $response->json('weight'));
        $this->assertEquals([86.0, 90.0], $response->json('height'));
        $this->assertSame([26, 32], $response->json('age'));
    }

    public function test_monitoring_dapat_difilter_berdasarkan_hasil(): void
    {
        $petugas = $this->petugas();
        $berisiko = $this->toddlerFor($petugas, ['name' => 'Anak Berisiko']);
        $aman = $this->toddlerFor($petugas, ['name' => 'Anak Aman']);

        $this->buatPrediksi('BERISIKO', ['user' => $petugas, 'toddler' => $berisiko]);
        $this->buatPrediksi('TIDAK_BERISIKO', ['user' => $petugas, 'toddler' => $aman]);

        $this->actingAs($petugas)->get('/monitoring?result=risiko')
            ->assertOk()->assertSee('Anak Berisiko')->assertDontSee('Anak Aman');

        $this->actingAs($petugas)->get('/monitoring?result=tidak')
            ->assertOk()->assertSee('Anak Aman')->assertDontSee('Anak Berisiko');
    }

    public function test_laporan_menampilkan_tiga_jenis(): void
    {
        $this->buatPrediksi('BERISIKO');
        $petugas = $this->petugas();

        foreach (['balita', 'pemeriksaan', 'prediksi'] as $jenis) {
            $this->actingAs($petugas)->get("/laporan?jenis={$jenis}")->assertOk();
        }
    }

    public function test_laporan_jenis_tidak_dikenal_jatuh_ke_laporan_balita(): void
    {
        $this->actingAs($this->petugas())
            ->get('/laporan?jenis=tidak-ada')
            ->assertOk()
            ->assertSee('Laporan Data Balita');
    }

    public function test_ekspor_csv_mengikuti_filter(): void
    {
        $petugas = $this->petugas();
        $this->toddlerFor($petugas, ['name' => 'Anak Lelaki', 'gender' => 'L']);
        $this->toddlerFor($petugas, ['name' => 'Anak Perempuan', 'gender' => 'P']);

        $response = $this->actingAs($petugas)->get('/laporan/ekspor?jenis=balita&gender=L');
        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();

        $this->assertStringContainsString('Anak Lelaki', $csv);
        $this->assertStringNotContainsString('Anak Perempuan', $csv);
    }

    public function test_halaman_cetak_memuat_kop_dan_periode(): void
    {
        $this->buatPrediksi('BERISIKO');

        $this->actingAs($this->petugas())
            ->get('/laporan/cetak?jenis=prediksi')
            ->assertOk()
            ->assertSee('LAPORAN HASIL PREDIKSI')
            ->assertSee('Periode')
            ->assertSee('tidak menggantikan pemeriksaan');
    }

    public function test_filter_laporan_prediksi_berdasarkan_tanggal(): void
    {
        $petugas = $this->petugas();
        $lama = $this->toddlerFor($petugas, ['name' => 'Prediksi Lama']);
        $baru = $this->toddlerFor($petugas, ['name' => 'Prediksi Baru']);

        $this->buatPrediksi('BERISIKO', [
            'user' => $petugas, 'toddler' => $lama,
            'predicted_at' => Carbon::now()->subMonths(6),
        ]);
        $this->buatPrediksi('BERISIKO', ['user' => $petugas, 'toddler' => $baru]);

        $this->actingAs($petugas)
            ->get('/laporan?jenis=prediksi&date_from='.Carbon::now()->subDays(7)->toDateString())
            ->assertOk()
            ->assertSee('Prediksi Baru')
            ->assertDontSee('Prediksi Lama');
    }
}
