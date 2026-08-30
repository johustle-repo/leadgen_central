<?php

namespace Database\Factories;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['agent_id' => User::factory(), 'source' => 'manual', 'company_name' => fake()->company(), 'website' => fake()->url(), 'city' => fake()->city(), 'country' => fake()->country(), 'contact_person' => fake()->name(), 'email' => fake()->companyEmail(), 'status' => 'raw', 'created_by' => fn (array $attributes) => $attributes['agent_id']];
    }
}
