<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            // Harus lolos aturan alpha_dash: huruf, angka, tanda hubung, garis bawah.
            'username' => fake()->unique()->bothify('petugas_####??'),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::PETUGAS_GIZI,
            'status' => UserStatus::ACTIVE,
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => UserRole::ADMIN]);
    }

    public function petugas(): static
    {
        return $this->state(fn () => ['role' => UserRole::PETUGAS_GIZI]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => UserStatus::INACTIVE]);
    }
}
