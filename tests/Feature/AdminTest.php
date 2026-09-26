<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\ModelVersion;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------- user management
    public function test_admin_dapat_menambah_pengguna(): void
    {
        $this->actingAs($this->admin())->post('/admin/users', [
            'name' => 'Petugas Baru',
            'username' => 'petugas-baru',
            'email' => 'baru@puskesmas.test',
            'role' => 'PETUGAS_GIZI',
            'status' => 'ACTIVE',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertRedirect('/admin/users');

        $user = User::where('username', 'petugas-baru')->firstOrFail();

        $this->assertSame(UserRole::PETUGAS_GIZI, $user->role);
        $this->assertNotSame('rahasia123', $user->password, 'Kata sandi wajib di-hash.');
        $this->assertTrue(password_verify('rahasia123', $user->password));
        $this->assertDatabaseHas('audit_logs', ['action' => AuditLog::ACTION_USER_CREATE]);
    }

    public function test_username_dan_email_duplikat_ditolak(): void
    {
        $admin = $this->admin();
        $existing = $this->petugas(['username' => 'dipakai', 'email' => 'dipakai@test.local']);

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Pengguna Lain',
            'username' => $existing->username,
            'email' => $existing->email,
            'role' => 'PETUGAS_GIZI',
            'status' => 'ACTIVE',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertSessionHasErrors(['username', 'email']);
    }

    public function test_kata_sandi_lemah_ditolak(): void
    {
        $this->actingAs($this->admin())->post('/admin/users', [
            'name' => 'Pengguna Lemah',
            'username' => 'lemah',
            'email' => 'lemah@test.local',
            'role' => 'PETUGAS_GIZI',
            'status' => 'ACTIVE',
            'password' => 'abc',
            'password_confirmation' => 'abc',
        ])->assertSessionHasErrors('password');
    }

    public function test_admin_dapat_menonaktifkan_dan_mengaktifkan_pengguna(): void
    {
        $admin = $this->admin();
        $target = $this->petugas();

        $this->actingAs($admin)->patch("/admin/users/{$target->id}/status")->assertRedirect();
        $this->assertSame(UserStatus::INACTIVE, $target->fresh()->status);

        $this->actingAs($admin)->patch("/admin/users/{$target->id}/status")->assertRedirect();
        $this->assertSame(UserStatus::ACTIVE, $target->fresh()->status);
    }

    public function test_admin_tidak_dapat_menonaktifkan_dirinya_sendiri(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->patch("/admin/users/{$admin->id}/status")
            ->assertSessionHas('error');

        $this->assertSame(UserStatus::ACTIVE, $admin->fresh()->status);
    }

    public function test_administrator_aktif_terakhir_tidak_dapat_diturunkan_perannya(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put("/admin/users/{$admin->id}", [
            'name' => $admin->name,
            'username' => $admin->username,
            'email' => $admin->email,
            'role' => 'PETUGAS_GIZI',
            'status' => 'ACTIVE',
        ])->assertSessionHas('error');

        $this->assertSame(UserRole::ADMIN, $admin->fresh()->role);
    }

    public function test_reset_kata_sandi_menghasilkan_sandi_baru(): void
    {
        $admin = $this->admin();
        $target = $this->petugas();
        $lama = $target->password;

        $this->actingAs($admin)
            ->patch("/admin/users/{$target->id}/reset-password")
            ->assertRedirect()
            ->assertSessionHas('warning');

        $this->assertNotSame($lama, $target->fresh()->password);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditLog::ACTION_USER_UPDATE]);
    }

    // ------------------------------------------------------------ model info
    public function test_halaman_model_menampilkan_tanda_hubung_saat_metrik_belum_ada(): void
    {
        ModelVersion::create(['version' => 'XGB-1.0.0', 'algorithm' => 'XGBoost', 'is_active' => true]);

        $this->actingAs($this->admin())->get('/admin/models')
            ->assertOk()
            ->assertSee('XGB-1.0.0')
            ->assertSee('Metrik hanya terisi bila ML API mengirimkan hasil training yang sebenarnya', false);
    }

    public function test_sinkronisasi_model_menyimpan_metrik_nyata_dari_ml_api(): void
    {
        Http::fake([
            '*/api/health' => Http::response(['success' => true, 'model_loaded' => true, 'model_version' => 'XGB-1.2.0']),
            '*/api/v1/model/info' => Http::response([
                'success' => true,
                'data' => [
                    'model_version' => 'XGB-1.2.0',
                    'algorithm' => 'XGBoost',
                    'feature_order' => ['gender', 'age_months', 'weight_kg', 'height_cm'],
                    'trained_at' => '2026-09-20T10:00:00+00:00',
                    'checksum' => 'sha256:abc',
                    'dataset_size' => 1500,
                    'training_size' => 1200,
                    'testing_size' => 300,
                    'metrics' => ['accuracy' => 0.7433, 'precision' => 0.785, 'recall' => 0.822, 'f1_score' => 0.8031],
                ],
            ]),
        ]);

        $this->actingAs($this->admin())->post('/admin/models/sync')->assertRedirect();

        $model = ModelVersion::where('version', 'XGB-1.2.0')->firstOrFail();

        $this->assertSame(0.7433, $model->accuracy);
        $this->assertSame(0.785, $model->precision_score);
        $this->assertSame(0.822, $model->recall);
        $this->assertSame(0.8031, $model->f1_score);
        $this->assertSame(1500, $model->dataset_size);
        $this->assertTrue($model->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditLog::ACTION_MODEL_SYNC]);
    }

    public function test_sinkronisasi_tanpa_metrik_tidak_mengarang_angka(): void
    {
        Http::fake([
            '*/api/health' => Http::response(['success' => true, 'model_loaded' => true, 'model_version' => 'XGB-1.3.0']),
            '*/api/v1/model/info' => Http::response([
                'success' => true,
                'data' => [
                    'model_version' => 'XGB-1.3.0',
                    'algorithm' => 'XGBoost',
                    'metrics' => ['accuracy' => null, 'precision' => null, 'recall' => null, 'f1_score' => null],
                ],
            ]),
        ]);

        $this->actingAs($this->admin())->post('/admin/models/sync')->assertRedirect();

        $model = ModelVersion::where('version', 'XGB-1.3.0')->firstOrFail();

        $this->assertNull($model->accuracy);
        $this->assertNull($model->f1_score);
        $this->assertSame('-', $model->metricLabel($model->accuracy));
    }

    public function test_hanya_satu_model_aktif(): void
    {
        ModelVersion::create(['version' => 'XGB-1.0.0', 'is_active' => true]);

        Http::fake([
            '*/api/health' => Http::response(['success' => true, 'model_loaded' => true, 'model_version' => 'XGB-2.0.0']),
            '*/api/v1/model/info' => Http::response([
                'success' => true,
                'data' => ['model_version' => 'XGB-2.0.0', 'algorithm' => 'XGBoost', 'metrics' => []],
            ]),
        ]);

        $this->actingAs($this->admin())->post('/admin/models/sync');

        $this->assertSame(1, ModelVersion::where('is_active', true)->count());
        $this->assertSame('XGB-2.0.0', ModelVersion::where('is_active', true)->value('version'));
    }

    public function test_sinkronisasi_gagal_saat_ml_api_mati(): void
    {
        Http::fake([
            '*/api/health' => Http::response([], 500),
            '*/api/v1/model/info' => fn () => throw new \Illuminate\Http\Client\ConnectionException('unreachable'),
        ]);

        $this->actingAs($this->admin())
            ->post('/admin/models/sync')
            ->assertSessionHas('error');

        $this->assertDatabaseCount('model_versions', 0);
    }

    // ---------------------------------------------------------- audit & setting
    public function test_audit_log_dapat_difilter(): void
    {
        $admin = $this->admin();
        $petugas = $this->petugas();

        $this->actingAs($petugas)->post('/balita', [
            'name' => 'Anak Audit',
            'birth_date' => now()->subYear()->toDateString(),
            'gender' => 'L',
        ]);

        $this->actingAs($admin)->get('/admin/audit-logs?action=CREATE')
            ->assertOk()
            ->assertSee('Menambah data balita');

        $this->actingAs($admin)->get('/admin/audit-logs?action=DELETE')
            ->assertOk()
            ->assertDontSee('Menambah data balita');
    }

    public function test_pengaturan_dapat_disimpan(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put('/admin/settings', [
            'settings' => [
                'app.instansi' => 'Puskesmas Lepak',
                'app.alamat' => 'Desa Lepak, Sakra Timur',
                'app.kepala' => 'dr. Contoh',
                'app.nip_kepala' => '19800101',
                'report.footer' => 'Dicetak dari SIPRESTI',
            ],
        ])->assertRedirect();

        $this->assertSame('Puskesmas Lepak', Setting::get('app.instansi'));
        $this->assertSame('dr. Contoh', Setting::get('app.kepala'));
        $this->assertDatabaseHas('audit_logs', ['action' => AuditLog::ACTION_SETTING_UPDATE]);
    }

    public function test_halaman_pengaturan_tidak_membocorkan_kunci_ml_api(): void
    {
        Http::fake(['*/api/health' => Http::response(['success' => true, 'model_loaded' => true, 'model_version' => 'XGB-1.0.0'])]);

        $this->actingAs($this->admin())->get('/admin/settings')
            ->assertOk()
            ->assertSee('Terisi')
            ->assertDontSee(config('sipresti.ml.key'))
            ->assertDontSee(config('sipresti.ml.url'));
    }

    // --------------------------------------------------------------- profil
    public function test_pengguna_dapat_mengubah_kata_sandi_sendiri(): void
    {
        $user = $this->petugas(['password' => bcrypt('lamaSekali1')]);

        $this->actingAs($user)->put('/profil/kata-sandi', [
            'current_password' => 'lamaSekali1',
            'password' => 'baruSekali1',
            'password_confirmation' => 'baruSekali1',
        ])->assertRedirect();

        $this->assertTrue(password_verify('baruSekali1', $user->fresh()->password));
    }

    public function test_ubah_kata_sandi_dengan_sandi_lama_salah_ditolak(): void
    {
        $user = $this->petugas(['password' => bcrypt('lamaSekali1')]);

        $this->actingAs($user)->put('/profil/kata-sandi', [
            'current_password' => 'salahTotal1',
            'password' => 'baruSekali1',
            'password_confirmation' => 'baruSekali1',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(password_verify('lamaSekali1', $user->fresh()->password));
    }
}
