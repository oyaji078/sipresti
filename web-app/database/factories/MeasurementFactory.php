<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MeasurementSource;
use App\Models\Measurement;
use App\Models\Toddler;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Measurement>
 */
class MeasurementFactory extends Factory
{
    protected $model = Measurement::class;

    public function definition(): array
    {
        return [
            'toddler_id' => Toddler::factory(),
            'measurement_date' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'age_months' => fake()->numberBetween(1, 59),
            'weight_kg' => fake()->randomFloat(2, 4, 20),
            'height_cm' => fake()->randomFloat(1, 50, 110),
            'source' => MeasurementSource::MANUAL,
            'created_by' => User::factory(),
        ];
    }

    public function configure(): static
    {
        // Usia selalu diselaraskan dengan tanggal lahir balita dan tanggal
        // pemeriksaan, supaya data uji tidak kontradiktif.
        return $this->afterMaking(function (Measurement $measurement): void {
            $toddler = $measurement->toddler ?? Toddler::find($measurement->toddler_id);

            if ($toddler) {
                $date = Carbon::parse($measurement->measurement_date);

                if ($date->lt($toddler->birth_date)) {
                    $date = $toddler->birth_date->copy()->addMonths(2);
                    $measurement->measurement_date = $date->toDateString();
                }

                $measurement->age_months = Measurement::calculateAgeMonths($toddler->birth_date, $date);
            }
        });
    }
}
