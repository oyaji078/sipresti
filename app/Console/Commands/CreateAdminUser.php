<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Membuat akun administrator.
 *
 * Kata sandi tidak pernah ditulis di dalam kode. Diambil dari ADMIN_PASSWORD
 * pada .env, atau dibuat acak lalu ditampilkan satu kali di terminal.
 */
class CreateAdminUser extends Command
{
    protected $signature = 'sipresti:create-admin
                            {--name= : Nama lengkap}
                            {--username= : Username login}
                            {--email= : Alamat email}
                            {--password= : Kata sandi (kosongkan agar dibuat acak)}
                            {--force : Perbarui akun bila username sudah ada}';

    protected $description = 'Membuat atau memperbarui akun administrator SIPRESTI';

    public function handle(): int
    {
        $name = $this->option('name') ?: env('ADMIN_NAME', 'Administrator SIPRESTI');
        $username = $this->option('username') ?: env('ADMIN_USERNAME', 'admin');
        $email = $this->option('email') ?: env('ADMIN_EMAIL', 'admin@sipresti.local');

        $password = $this->option('password') ?: env('ADMIN_PASSWORD');
        $generated = false;

        if (blank($password)) {
            $password = Str::password(14, true, true, false, false);
            $generated = true;
        }

        $existing = User::where('username', $username)->orWhere('email', $email)->first();

        if ($existing && ! $this->option('force')) {
            $this->error("Akun dengan username '{$username}' atau email '{$email}' sudah ada.");
            $this->line('Gunakan --force untuk memperbarui akun tersebut.');

            return self::FAILURE;
        }

        $user = User::updateOrCreate(
            ['username' => $username],
            [
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'role' => UserRole::ADMIN,
                'status' => UserStatus::ACTIVE,
            ]
        );

        $this->newLine();
        $this->info('Akun administrator siap digunakan.');
        $this->table(['Kolom', 'Nilai'], [
            ['Nama', $user->name],
            ['Username', $user->username],
            ['Email', $user->email],
            ['Peran', $user->role->value],
            ['Status', $user->status->value],
        ]);

        if ($generated) {
            $this->newLine();
            $this->warn('Kata sandi dibuat otomatis (hanya ditampilkan sekali):');
            $this->line('    '.$password);
            $this->newLine();
            $this->comment('Segera ganti kata sandi setelah login pertama.');
        }

        return self::SUCCESS;
    }
}
