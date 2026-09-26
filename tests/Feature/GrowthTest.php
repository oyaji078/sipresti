<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Measurement;
use App\Models\Prediction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GrowthTest extends TestCase
{
    use RefreshDatabase;

    private function predict(Measurement $measurement, User $user, string $label): void
    {
        Prediction::create([
            'toddler_id' => $measurement->toddler_id,
            'measurement_id' => $measurement->id,
            'prediction_class' => $label === 'BERISIKO' ? '1' : '0',
            'prediction_label' => $label,
            'probability' => 0.8,
            'input_snapshot_json' => [],
            'request_id' => (string) Str::uuid(),
            'predicted_by' => $user->id,
            'predicted_at' => $measurement->measurement_date,
        ]);
    }

    public function test_perkembangan_membandingkan_dua_pemeriksaan_terakhir(): void
    {
        $petugas = $this->petugas();

        $naik = $this->toddlerFor($petugas, ['name' => 'Anak Naik', 'birth_date' => '2024-01-01']);
        $this->measurementFor($naik, $petugas, ['measurement_date' => '2026-06-10', 'weight_kg' => 10, 'height_cm' => 80]);
        $this->measurementFor($naik, $petugas, ['measurement_date' => '2026-07-10', 'weight_kg' => 10.5, 'height_cm' => 81]);

        $turun = $this->toddlerFor($petugas, ['name' => 'Anak Turun', 'birth_date' => '2024-01-01']);
        $lama = $this->measurementFor($turun, $petugas, ['measurement_date' => '2026-06-10', 'weight_kg' => 11, 'height_cm' => 82]);
        $baru = $this->measurementFor($turun, $petugas, ['measurement_date' => '2026-07-10', 'weight_kg' => 10.8, 'height_cm' => 82.5]);
        $this->predict($lama, $petugas, 'TIDAK BERISIKO');
        $this->predict($baru, $petugas, 'BERISIKO');

        $this->actingAs($petugas)->get('/perkembangan')
            ->assertOk()
            ->assertSeeInOrder(['Anak Naik', 'BB Naik', 'Anak Turun', 'BB Turun', 'Memburuk'])
            ->assertViewHas('summary', fn (array $s) => $s['compared'] === 2 && $s['weight_up'] === 1
                && $s['weight_not_up'] === 1 && $s['worse'] === 1);

        $this->actingAs($petugas)->get('/perkembangan?weight=tidak_naik')
            ->assertSee('Anak Turun')
            ->assertDontSee('Anak Naik');
    }

    public function test_admin_juga_dapat_melihat_perkembangan(): void
    {
        $this->actingAs($this->admin())->get('/perkembangan')->assertOk();
    }
}
