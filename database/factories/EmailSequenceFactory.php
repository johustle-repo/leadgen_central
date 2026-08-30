<?php

namespace Database\Factories;

use App\Models\EmailSequence;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailSequence>
 */
class EmailSequenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'name' => fake()->unique()->words(3, true), 'steps' => EmailSequence::defaultSteps(), 'is_active' => true];
    }
}
