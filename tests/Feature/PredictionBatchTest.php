<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Measurement;
use App\Models\Prediction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PredictionBatchTest extends TestCase
{
    use RefreshDatabase;

    private function okResponse(): array
    {
        return [
            'success' => true,
            'data' => [
                'prediction_class' => 1,
                'prediction_label' => 'BERISIKO',
                'probability' => 0.73,
                'model_version' => 'XGB-1.0.0',
            ],
        ];
    }

    /** @return array<int, Measurement> */
    private function measurements(User $petugas, int $count): array
    {
        $toddler = $this->toddlerFor($petugas, ['birth_date' => '2024-01-01']);

        return array_map(
            fn (int $i) => $this->measurementFor($toddler, $petugas, ['measurement_date' => '2026-0'.($i + 1).'-10']),
            range(0, $count - 1),
        );
    }

    public function test_prediksi_massal_menyimpan_semua_pemeriksaan_yang_belum_diprediksi(): void
    {
        $petugas = $this->petugas();
        $this->measurements($petugas, 3);
        Http::fake(['*/api/v1/predict' => Http::response($this->okResponse())]);

        $this->actingAs($petugas)
            ->postJson('/prediksi-massal', ['after_id' => 0])
            ->assertOk()
            ->assertJson(['success' => 3, 'failed' => 0, 'remaining' => 0, 'done' => true]);

        $this->assertSame(3, Prediction::count());
        $this->assertSame(3, Prediction::distinct()->count('request_id'));
        $this->assertSame('BERISIKO', Prediction::first()->prediction_label);
        Http::assertSentCount(3);
        Http::assertSent(fn (Request $r) => $r->hasHeader('X-API-Key') && ! isset($r['name']) && ! isset($r['nik']));
        $this->assertDatabaseHas('audit_logs', ['action' => AuditLog::ACTION_PREDICT, 'description' => 'Prediksi massal: 3 berhasil, 0 gagal']);
    }

    public function test_pemeriksaan_yang_sudah_diprediksi_dilewati(): void
    {
        $petugas = $this->petugas();
        [$first] = $this->measurements($petugas, 2);
        Http::fake(['*/api/v1/predict' => Http::response($this->okResponse())]);

        $this->actingAs($petugas)->post('/prediksi', ['measurement_id' => $first->id]);
        Http::assertSentCount(1);

        $this->actingAs($petugas)
            ->postJson('/prediksi-massal', ['after_id' => 0])
            ->assertJson(['success' => 1, 'done' => true]);

        $this->assertSame(2, Prediction::count());
        $this->assertSame(1, Prediction::where('measurement_id', $first->id)->count());
    }

    public function test_item_gagal_tidak_disimpan_dan_dilaporkan(): void
    {
        $petugas = $this->petugas();
        $this->measurements($petugas, 2);
        Http::fake(['*/api/v1/predict' => Http::sequence()
            ->push($this->okResponse())
            ->push(['success' => false, 'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'usia di luar rentang']], 422),
        ]);

        $response = $this->actingAs($petugas)
            ->postJson('/prediksi-massal', ['after_id' => 0])
            ->assertOk()
            ->assertJson(['success' => 1, 'failed' => 1]);

        $this->assertSame(1, Prediction::count());
        $this->assertSame(
            ['Data pemeriksaan ditolak layanan prediksi: usia di luar rentang' => 1],
            $response->json('errors'),
        );
    }

    public function test_tanpa_data_tertunda_langsung_selesai(): void
    {
        Http::fake();

        $this->actingAs($this->petugas())
            ->postJson('/prediksi-massal', ['after_id' => 0])
            ->assertJson(['success' => 0, 'failed' => 0, 'done' => true]);

        Http::assertNothingSent();
    }

    public function test_layanan_belum_dikonfigurasi_menghentikan_proses(): void
    {
        config(['sipresti.ml.url' => '']);
        $this->app->forgetInstance(\App\Services\MlPredictionService::class);

        $petugas = $this->petugas();
        $this->measurements($petugas, 1);

        $this->actingAs($petugas)
            ->postJson('/prediksi-massal', ['after_id' => 0])
            ->assertStatus(503)
            ->assertJsonStructure(['message']);

        $this->assertSame(0, Prediction::count());
    }

    public function test_admin_tidak_dapat_menjalankan_prediksi_massal(): void
    {
        $this->actingAs($this->admin())->get('/prediksi-massal')->assertForbidden();
        $this->actingAs($this->admin())->postJson('/prediksi-massal')->assertForbidden();
    }

    public function test_halaman_menampilkan_jumlah_belum_diprediksi(): void
    {
        $petugas = $this->petugas();
        $this->measurements($petugas, 2);
        Http::fake(['*/api/health' => Http::response(['success' => true, 'model_loaded' => true, 'model_version' => 'XGB-1.0.0'])]);

        $this->actingAs($petugas)
            ->get('/prediksi-massal')
            ->assertOk()
            ->assertSee('Belum Diprediksi')
            ->assertSee('Mulai Prediksi Semua Data');
    }
}
