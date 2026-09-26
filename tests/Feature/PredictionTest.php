<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ModelVersion;
use App\Models\Prediction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Integrasi Hostinger -> Vercel. Panggilan HTTP di-fake, tetapi seluruh
 * penanganan respons, penyimpanan, dan penolakan hasil palsu diuji sungguhan.
 */
class PredictionTest extends TestCase
{
    use RefreshDatabase;

    private function fakeHealthy(): void
    {
        Http::fake([
            '*/api/health' => Http::response([
                'success' => true,
                'service' => 'SIPRESTI ML API',
                'model_loaded' => true,
                'model_version' => 'XGB-1.0.0',
            ]),
        ]);
    }

    public function test_prediksi_berhasil_disimpan_dengan_data_dari_model(): void
    {
        $petugas = $this->petugas();
        $toddler = $this->toddlerFor($petugas, ['birth_date' => '2024-03-15', 'gender' => 'L']);
        $measurement = $this->measurementFor($toddler, $petugas, [
            'measurement_date' => '2026-09-20',
            'weight_kg' => 11.4,
            'height_cm' => 86.5,
        ]);

        Http::fake([
            '*/api/health' => Http::response(['success' => true, 'model_loaded' => true, 'model_version' => 'XGB-1.0.0']),
            '*/api/v1/predict' => Http::response([
                'success' => true,
                'request_id' => 'diabaikan-karena-hostinger-memakai-miliknya',
                'data' => [
                    'prediction_class' => 0,
                    'prediction_label' => 'BERISIKO',
                    'probability' => 0.8174,
                    'model_version' => 'XGB-1.0.0',
                ],
            ]),
        ]);

        $this->actingAs($petugas)
            ->post('/prediksi', ['measurement_id' => $measurement->id])
            ->assertRedirect();

        $prediction = Prediction::first();

        $this->assertNotNull($prediction);
        $this->assertSame('BERISIKO', $prediction->prediction_label);
        $this->assertSame('0', $prediction->prediction_class);
        $this->assertSame(0.8174, $prediction->probability);
        $this->assertSame('XGB-1.0.0', $prediction->modelVersion->version);
        $this->assertSame($petugas->id, $prediction->predicted_by);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditLog::ACTION_PREDICT]);
    }

    public function test_payload_ke_ml_api_hanya_memuat_empat_fitur_tanpa_identitas(): void
    {
        $petugas = $this->petugas();
        $toddler = $this->toddlerFor($petugas, [
            'name' => 'Aisyah Putri',
            'nik' => '5203010101230001',
            'mother_name' => 'Ibu Aisyah',
            'address' => 'Dusun Contoh',
            'gender' => 'P',
            'birth_date' => '2024-03-15',
        ]);
        $measurement = $this->measurementFor($toddler, $petugas, [
            'measurement_date' => '2026-09-20',
            'weight_kg' => 11.4,
            'height_cm' => 86.5,
        ]);

        Http::fake([
            '*/api/health' => Http::response(['success' => true, 'model_loaded' => true, 'model_version' => 'XGB-1.0.0']),
            '*/api/v1/predict' => Http::response([
                'success' => true,
                'data' => [
                    'prediction_class' => 1,
                    'prediction_label' => 'TIDAK_BERISIKO',
                    'probability' => 0.71,
                    'model_version' => 'XGB-1.0.0',
                ],
            ]),
        ]);

        $this->actingAs($petugas)->post('/prediksi', ['measurement_id' => $measurement->id]);

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), '/api/v1/predict')) {
                return false;
            }

            $body = $request->data();

            // Hanya fitur + request_id yang boleh terkirim.
            $this->assertEqualsCanonicalizing(
                ['request_id', 'gender', 'age_months', 'weight_kg', 'height_cm'],
                array_keys($body)
            );

            $this->assertSame('P', $body['gender']);
            $this->assertSame(30, $body['age_months']);
            $this->assertSame(11.4, $body['weight_kg']);
            $this->assertSame(86.5, $body['height_cm']);

            // Identitas tidak boleh muncul di manapun dalam badan permintaan.
            $raw = (string) $request->body();
            foreach (['Aisyah', '5203010101230001', 'Ibu Aisyah', 'Dusun Contoh', 'BLT-'] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $raw);
            }

            return true;
        });
    }

    public function test_api_key_dikirim_pada_header(): void
    {
        $petugas = $this->petugas();
        $toddler = $this->toddlerFor($petugas);
        $measurement = $this->measurementFor($toddler, $petugas);

        Http::fake([
            '*/api/health' => Http::response(['success' => true, 'model_loaded' => true, 'model_version' => 'XGB-1.0.0']),
            '*/api/v1/predict' => Http::response([
                'success' => true,
                'data' => ['prediction_class' => 1, 'prediction_label' => 'TIDAK_BERISIKO', 'probability' => 0.6, 'model_version' => 'XGB-1.0.0'],
            ]),
        ]);

        $this->actingAs($petugas)->post('/prediksi', ['measurement_id' => $measurement->id]);

        Http::assertSent(fn (Request $r) => ! str_contains($r->url(), '/api/v1/predict')
            || $r->hasHeader('X-API-Key', config('sipresti.ml.key')));
    }

    public function test_snapshot_input_tidak_berubah_walau_pemeriksaan_diperbarui(): void
    {
        $petugas = $this->petugas();
        $toddler = $this->toddlerFor($petugas, ['birth_date' => '2024-03-15']);
        $measurement = $this->measurementFor($toddler, $petugas, [
            'measurement_date' => '2026-09-20',
            'weight_kg' => 11.4,
            'height_cm' => 86.5,
        ]);

        Http::fake([
            '*/api/health' => Http::response(['success' => true, 'model_loaded' => true, 'model_version' => 'XGB-1.0.0']),
            '*/api/v1/predict' => Http::response([
                'success' => true,
                'data' => ['prediction_class' => 0, 'prediction_label' => 'BERISIKO', 'probability' => 0.8, 'model_version' => 'XGB-1.0.0'],
            ]),
        ]);

        $this->actingAs($petugas)->post('/prediksi', ['measurement_id' => $measurement->id]);

        $this->actingAs($petugas)->put("/pemeriksaan/{$measurement->id}", [
            'measurement_date' => '2026-09-20',
            'weight_kg' => 15.9,
            'height_cm' => 99.9,
        ]);

        $snapshot = Prediction::first()->input_snapshot_json;

        $this->assertSame(11.4, $snapshot['weight_kg']);
        $this->assertSame(86.5, $snapshot['height_cm']);
    }

    public function test_request_id_unik_mencegah_duplikasi(): void
    {
        $petugas = $this->petugas();
        $toddler = $this->toddlerFor($petugas);
        $measurement = $this->measurementFor($toddler, $petugas);

        Http::fake([
            '*/api/health' => Http::response(['success' => true, 'model_loaded' => true, 'model_version' => 'XGB-1.0.0']),
            '*/api/v1/predict' => Http::response([
                'success' => true,
                'data' => ['prediction_class' => 0, 'prediction_label' => 'BERISIKO', 'probability' => 0.8, 'model_version' => 'XGB-1.0.0'],
            ]),
        ]);

        $this->actingAs($petugas)->post('/prediksi', ['measurement_id' => $measurement->id]);
        $first = Prediction::first();

        $this->actingAs($petugas)->post('/prediksi', ['measurement_id' => $measurement->id]);

        $ids = Prediction::pluck('request_id')->all();
        $this->assertCount(2, $ids);
        $this->assertSame($ids, array_unique($ids), 'request_id harus unik untuk setiap permintaan');
        $this->assertNotSame($first->request_id, Prediction::latest('id')->first()->request_id);
    }

    public function test_ml_api_timeout_tidak_menyimpan_hasil_palsu(): void
    {
        $petugas = $this->petugas();
        $toddler = $this->toddlerFor($petugas);
        $measurement = $this->measurementFor($toddler, $petugas);

        Http::fake([
            '*/api/health' => Http::response(['success' => true, 'model_loaded' => true, 'model_version' => 'XGB-1.0.0']),
            '*/api/v1/predict' => fn () => throw new \Illuminate\Http\Client\ConnectionException('Connection timed out'),
        ]);

        $this->actingAs($petugas)
            ->post('/prediksi', ['measurement_id' => $measurement->id])
            ->assertRedirect()
            ->assertSessionHas('error', 'Layanan prediksi sedang tidak tersedia. Silakan coba kembali.');

        $this->assertDatabaseCount('predictions', 0);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditLog::ACTION_PREDICT_FAILED]);
    }

    public function test_ml_api_unauthorized_ditangani_tanpa_menyimpan(): void
    {
        $petugas = $this->petugas();
        $toddler = $this->toddlerFor($petugas);
        $measurement = $this->measurementFor($toddler, $petugas);

        Http::fake([
            '*/api/health' => Http::response(['success' => true, 'model_loaded' => true, 'model_version' => 'XGB-1.0.0']),
            '*/api/v1/predict' => Http::response(['success' => false, 'error' => ['code' => 'UNAUTHORIZED', 'message' => 'API key tidak valid.']], 401),
        ]);

        $this->actingAs($petugas)
            ->post('/prediksi', ['measurement_id' => $measurement->id])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('predictions', 0);
    }

    public function test_model_belum_tersedia_ditangani_tanpa_menyimpan(): void
    {
        $petugas = $this->petugas();
        $toddler = $this->toddlerFor($petugas);
        $measurement = $this->measurementFor($toddler, $petugas);

        Http::fake([
            '*/api/health' => Http::response(['success' => true, 'model_loaded' => false, 'model_version' => null], 503),
            '*/api/v1/predict' => Http::response(['success' => false, 'error' => ['code' => 'MODEL_NOT_READY', 'message' => 'Model XGBoost belum tersedia.']], 503),
        ]);

        $this->actingAs($petugas)
            ->post('/prediksi', ['measurement_id' => $measurement->id])
            ->assertSessionHas('error', 'Model prediksi belum tersedia.');

        $this->assertDatabaseCount('predictions', 0);
    }

    public function test_probability_di_luar_rentang_ditolak(): void
    {
        $petugas = $this->petugas();
        $toddler = $this->toddlerFor($petugas);
        $measurement = $this->measurementFor($toddler, $petugas);

        Http::fake([
            '*/api/health' => Http::response(['success' => true, 'model_loaded' => true, 'model_version' => 'XGB-1.0.0']),
            '*/api/v1/predict' => Http::response([
                'success' => true,
                'data' => ['prediction_class' => 0, 'prediction_label' => 'BERISIKO', 'probability' => 8.5, 'model_version' => 'XGB-1.0.0'],
            ]),
        ]);

        $this->actingAs($petugas)
            ->post('/prediksi', ['measurement_id' => $measurement->id])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('predictions', 0);
    }

    public function test_respons_tidak_lengkap_ditolak(): void
    {
        $petugas = $this->petugas();
        $toddler = $this->toddlerFor($petugas);
        $measurement = $this->measurementFor($toddler, $petugas);

        Http::fake([
            '*/api/health' => Http::response(['success' => true, 'model_loaded' => true, 'model_version' => 'XGB-1.0.0']),
            '*/api/v1/predict' => Http::response(['success' => true, 'data' => ['prediction_label' => 'BERISIKO']]),
        ]);

        $this->actingAs($petugas)
            ->post('/prediksi', ['measurement_id' => $measurement->id])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('predictions', 0);
    }

    public function test_halaman_hasil_menampilkan_disclaimer_dan_bukan_kata_diagnosis(): void
    {
        $petugas = $this->petugas();
        $toddler = $this->toddlerFor($petugas);
        $measurement = $this->measurementFor($toddler, $petugas);

        Http::fake([
            '*/api/health' => Http::response(['success' => true, 'model_loaded' => true, 'model_version' => 'XGB-1.0.0']),
            '*/api/v1/predict' => Http::response([
                'success' => true,
                'data' => ['prediction_class' => 0, 'prediction_label' => 'BERISIKO', 'probability' => 0.8174, 'model_version' => 'XGB-1.0.0'],
            ]),
        ]);

        $this->actingAs($petugas)->post('/prediksi', ['measurement_id' => $measurement->id]);

        $this->actingAs($petugas)->get('/prediksi/'.Prediction::first()->id)
            ->assertOk()
            ->assertSee('Prediksi Model')
            ->assertSee('Probabilitas Keluaran Model')
            ->assertSee('81,74%')
            ->assertSee('tidak menggantikan pemeriksaan')
            ->assertDontSee('Diagnosis');
    }

    public function test_versi_model_dicatat_walau_belum_ada_di_katalog(): void
    {
        $petugas = $this->petugas();
        $toddler = $this->toddlerFor($petugas);
        $measurement = $this->measurementFor($toddler, $petugas);

        $this->assertDatabaseCount('model_versions', 0);

        Http::fake([
            '*/api/health' => Http::response(['success' => true, 'model_loaded' => true, 'model_version' => 'XGB-2.1.0']),
            '*/api/v1/predict' => Http::response([
                'success' => true,
                'data' => ['prediction_class' => 0, 'prediction_label' => 'BERISIKO', 'probability' => 0.8, 'model_version' => 'XGB-2.1.0'],
            ]),
        ]);

        $this->actingAs($petugas)->post('/prediksi', ['measurement_id' => $measurement->id]);

        $model = ModelVersion::first();
        $this->assertSame('XGB-2.1.0', $model->version);
        // Metrik tidak boleh dikarang saat versi didaftarkan otomatis.
        $this->assertNull($model->accuracy);
        $this->assertNull($model->precision_score);
        $this->assertNull($model->recall);
        $this->assertNull($model->f1_score);
    }

    public function test_prediksi_tanpa_pemeriksaan_ditolak(): void
    {
        $this->actingAs($this->petugas())
            ->post('/prediksi', ['measurement_id' => 999999])
            ->assertSessionHasErrors('measurement_id');
    }
}
