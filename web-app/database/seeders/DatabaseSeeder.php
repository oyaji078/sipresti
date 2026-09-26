<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\MeasurementSource;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Measurement;
use App\Models\Setting;
use App\Models\Toddler;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Seeder PENGEMBANGAN.
 *
 * Seluruh data balita di sini adalah DATA SINTETIS, bukan data pasien nyata.
 * Seeder menolak berjalan di environment production.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command->error('Seeder pengembangan tidak boleh dijalankan di production.');
            $this->command->line('Gunakan: php artisan sipresti:create-admin');

            return;
        }

        $this->command->warn('Menanam DATA SINTETIS untuk pengembangan. Bukan data pasien nyata.');

        $this->seedSettings();
        [$admin, $petugas] = $this->seedUsers();
        $this->seedToddlers($petugas);

        $this->command->newLine();
        $this->command->info('Akun pengembangan:');
        $this->command->table(['Peran', 'Username', 'Kata Sandi'], [
            [$admin->role->value, $admin->username, $this->devPassword()],
            [$petugas->role->value, $petugas->username, $this->devPassword()],
        ]);
        $this->command->warn('Kata sandi di atas hanya untuk pengembangan lokal.');
    }

    private function devPassword(): string
    {
        // Diambil dari .env agar tidak ada kata sandi tetap di dalam kode.
        return (string) env('SEED_PASSWORD', 'sipresti2026');
    }

    private function seedSettings(): void
    {
        $defaults = [
            'app.instansi' => config('sipresti.instansi'),
            'app.alamat' => 'Desa Lepak, Kecamatan Sakra Timur, Kabupaten Lombok Timur',
            'app.kepala' => '',
            'app.nip_kepala' => '',
            'report.footer' => 'Dicetak dari SIPRESTI',
        ];

        foreach ($defaults as $key => $value) {
            Setting::firstOrCreate(['setting_key' => $key], ['setting_value' => $value]);
        }
    }

    /** @return array{0: User, 1: User} */
    private function seedUsers(): array
    {
        $password = $this->devPassword();

        $admin = User::updateOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Administrator SIPRESTI',
                'email' => 'admin@sipresti.local',
                'password' => $password,
                'role' => UserRole::ADMIN,
                'status' => UserStatus::ACTIVE,
            ]
        );

        $petugas = User::updateOrCreate(
            ['username' => 'petugas'],
            [
                'name' => 'Petugas Gizi Puskesmas Lepak',
                'email' => 'petugas@sipresti.local',
                'password' => $password,
                'role' => UserRole::PETUGAS_GIZI,
                'status' => UserStatus::ACTIVE,
            ]
        );

        return [$admin, $petugas];
    }

    private function seedToddlers(User $petugas): void
    {
        if (Toddler::query()->exists()) {
            $this->command->line('Data balita sudah ada, dilewati.');

            return;
        }

        $names = [
            ['Aisyah Putri', 'P'], ['Muhammad Rafi', 'L'], ['Nadia Salsabila', 'P'],
            ['Ahmad Fauzan', 'L'], ['Zahra Aulia', 'P'], ['Farhan Maulana', 'L'],
            ['Kayla Ramadhani', 'P'], ['Bagas Pratama', 'L'], ['Syifa Khairunnisa', 'P'],
            ['Rizky Ardiansyah', 'L'], ['Naura Humaira', 'P'], ['Daffa Alfarizi', 'L'],
        ];

        $villages = ['Lepak', 'Lepak Timur', 'Surabaya', 'Menceh'];
        $posyandus = ['Melati', 'Mawar', 'Anggrek', 'Dahlia'];

        foreach ($names as $i => [$name, $gender]) {
            $birthDate = Carbon::now()->subMonths(random_int(8, 52))->startOfDay();

            $toddler = Toddler::create([
                'code' => Toddler::nextCode(),
                'nik' => '52' . Str::padLeft((string) random_int(1, 99999999999999), 14, '0'),
                'name' => $name,
                'birth_date' => $birthDate->toDateString(),
                'gender' => $gender,
                'mother_name' => 'Ibu '.Str::before($name, ' '),
                'address' => 'DATA SINTETIS - PENGEMBANGAN',
                'village' => $villages[$i % count($villages)],
                'posyandu' => $posyandus[$i % count($posyandus)],
                'created_by' => $petugas->id,
            ]);

            // Tiga kali penimbangan dengan jarak sekitar dua bulan.
            $weight = round(random_int(60, 110) / 10, 1);
            $height = round(random_int(600, 900) / 10, 1);

            for ($n = 2; $n >= 0; $n--) {
                $date = Carbon::now()->subMonths($n * 2)->startOfDay();

                if ($date->lt($birthDate)) {
                    continue;
                }

                Measurement::create([
                    'toddler_id' => $toddler->id,
                    'measurement_date' => $date->toDateString(),
                    'age_months' => Measurement::calculateAgeMonths($birthDate, $date),
                    'weight_kg' => $weight,
                    'height_cm' => $height,
                    'source' => MeasurementSource::MANUAL,
                    'created_by' => $petugas->id,
                ]);

                $weight = round($weight + random_int(2, 6) / 10, 1);
                $height = round($height + random_int(5, 15) / 10, 1);
            }
        }

        $this->command->info('Ditanam: '.Toddler::count().' balita, '.Measurement::count().' pemeriksaan (sintetis).');
        $this->command->line('Tidak ada data prediksi yang ditanam — prediksi hanya boleh berasal dari model sungguhan.');
    }
}
