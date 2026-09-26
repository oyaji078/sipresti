<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Toddler;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Toddler>
 */
class ToddlerFactory extends Factory
{
    protected $model = Toddler::class;

    public function definition(): array
    {
        $gender = fake()->randomElement(['L', 'P']);

        return [
            'code' => Toddler::nextCode(),
            'nik' => fake()->unique()->numerify('################'),
            'name' => fake()->firstName($gender === 'L' ? 'male' : 'female').' '.fake()->lastName(),
            'birth_date' => fake()->dateTimeBetween('-5 years', '-6 months')->format('Y-m-d'),
            'gender' => $gender,
            'mother_name' => fake()->name('female'),
            'address' => 'DATA SINTETIS - PENGEMBANGAN',
            'village' => fake()->randomElement(['Lepak', 'Lepak Timur', 'Surabaya', 'Surabaya Utara', 'Menceh']),
            'posyandu' => fake()->randomElement(['Melati', 'Mawar', 'Anggrek', 'Dahlia', 'Kenanga']),
            'created_by' => User::factory(),
        ];
    }
}
