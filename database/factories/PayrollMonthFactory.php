<?php

namespace Database\Factories;

use App\Models\PayrollMonth;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollMonth>
 */
class PayrollMonthFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'month' => fake()->dateTimeBetween('-1 year')->format('Y-m-01'),
            'usd_to_php_rate' => fake()->randomFloat(2, 55, 62),
            'internet_php' => fake()->randomFloat(2, 1500, 2500),
            'electricity_php' => fake()->randomFloat(2, 5000, 9000),
            'water_php' => fake()->randomFloat(2, 0, 1500),
        ];
    }
}
