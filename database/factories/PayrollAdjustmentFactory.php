<?php

namespace Database\Factories;

use App\Models\PayrollAdjustment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollAdjustment>
 */
class PayrollAdjustmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'month' => fake()->dateTimeBetween('-1 year')->format('Y-m-01'),
            'employment_type' => null,
            'paid_days' => null,
        ];
    }
}
