<?php

namespace Database\Factories;

use App\Models\DuplicateMatch;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DuplicateMatch>
 */
class DuplicateMatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['existing_lead_id' => Lead::factory(), 'match_type' => 'possible', 'match_score' => 80, 'matched_fields' => ['similar_company'], 'status' => 'pending'];
    }
}
