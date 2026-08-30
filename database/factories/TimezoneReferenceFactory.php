<?php

namespace Database\Factories;

use App\Models\TimezoneReference;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimezoneReference>
 */
class TimezoneReferenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'country' => fake()->unique()->country(),
            'original_country_code' => fake()->unique()->countryCode(),
            'reference_country_code' => fake()->countryCode(),
            'reference_capital' => fake()->city(),
        ];
    }
}
