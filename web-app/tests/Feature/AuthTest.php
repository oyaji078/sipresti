<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_login_dapat_diakses(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('MASUK', false)
            ->assertSee('SIPRESTI');
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_login_berhasil_dengan_username(): void
    {
        $user = $this->petugas(['username' => 'petugas1', 'password' => Hash::make('rahasia123')]);

        $this->post('/login', ['username' => 'petugas1', 'password' => 'rahasia123'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditLog::ACTION_LOGIN, 'user_id' => $user->id]);
    }

    public function test_login_berhasil_dengan_email(): void
    {
        $user = $this->petugas(['email' => 'gizi@puskesmas.test', 'password' => Hash::make('rahasia123')]);

        $this->post('/login', ['username' => 'gizi@puskesmas.test', 'password' => 'rahasia123'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_gagal_dengan_kata_sandi_salah(): void
    {
        $this->petugas(['username' => 'petugas1', 'password' => Hash::make('rahasia123')]);

        $this->post('/login', ['username' => 'petugas1', 'password' => 'salah'])
            ->assertSessionHasErrors('username');

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => AuditLog::ACTION_LOGIN_FAILED]);
    }

    public function test_login_gagal_untuk_akun_tidak_terdaftar(): void
    {
        $this->post('/login', ['username' => 'tidak-ada', 'password' => 'apapun'])
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_input_login_kosong_ditolak(): void
    {
        $this->post('/login', ['username' => '', 'password' => ''])
            ->assertSessionHasErrors(['username', 'password']);
    }

    public function test_akun_nonaktif_tidak_dapat_login(): void
    {
        $this->petugas([
            'username' => 'nonaktif',
            'password' => Hash::make('rahasia123'),
            'status' => UserStatus::INACTIVE,
        ]);

        $this->post('/login', ['username' => 'nonaktif', 'password' => 'rahasia123'])
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_akun_yang_dinonaktifkan_saat_sesi_aktif_langsung_dikeluarkan(): void
    {
        $user = $this->petugas();

        $this->actingAs($user)->get('/dashboard')->assertOk();

        $user->update(['status' => UserStatus::INACTIVE]);

        $this->actingAs($user)->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_logout_menghentikan_sesi(): void
    {
        $user = $this->petugas();

        $this->actingAs($user)->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => AuditLog::ACTION_LOGOUT, 'user_id' => $user->id]);
    }

    public function test_percobaan_login_dibatasi_setelah_lima_kali_gagal(): void
    {
        $this->petugas(['username' => 'petugas1', 'password' => Hash::make('rahasia123')]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['username' => 'petugas1', 'password' => 'salah']);
        }

        $response = $this->post('/login', ['username' => 'petugas1', 'password' => 'rahasia123']);

        $response->assertSessionHasErrors('username');
        $this->assertStringContainsString(
            'Terlalu banyak percobaan login',
            (string) session('errors')->first('username')
        );
        $this->assertGuest();
    }
}
