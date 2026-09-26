<?php

declare(strict_types=1);

namespace Tests;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Measurement;
use App\Models\Toddler;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Carbon;

abstract class TestCase extends BaseTestCase
{
    protected function admin(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => UserRole::ADMIN,
            'status' => UserStatus::ACTIVE,
        ], $attributes));
    }

    protected function petugas(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => UserRole::PETUGAS_GIZI,
            'status' => UserStatus::ACTIVE,
        ], $attributes));
    }

    protected function toddlerFor(User $user, array $attributes = []): Toddler
    {
        return Toddler::factory()->create(array_merge([
            'created_by' => $user->id,
            'birth_date' => Carbon::now()->subMonths(30)->toDateString(),
            'gender' => 'L',
        ], $attributes));
    }

    protected function measurementFor(Toddler $toddler, User $user, array $attributes = []): Measurement
    {
        $date = Carbon::parse($attributes['measurement_date'] ?? Carbon::now()->subDays(5)->toDateString());

        return Measurement::factory()->create(array_merge([
            'toddler_id' => $toddler->id,
            'created_by' => $user->id,
            'measurement_date' => $date->toDateString(),
            'age_months' => Measurement::calculateAgeMonths($toddler->birth_date, $date),
            'weight_kg' => 11.4,
            'height_cm' => 86.5,
        ], $attributes));
    }
}
