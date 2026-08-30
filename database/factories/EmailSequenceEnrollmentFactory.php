<?php

namespace Database\Factories;

use App\Models\EmailSequence;
use App\Models\EmailSequenceEnrollment;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailSequenceEnrollment>
 */
class EmailSequenceEnrollmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email_sequence_id' => EmailSequence::factory(),
            'lead_id' => Lead::factory(),
            'agent_id' => fn (array $attributes) => Lead::query()->findOrFail($attributes['lead_id'])->agent_id,
            'status' => 'active',
            'current_step' => 0,
            'started_at' => now(),
            'next_send_at' => now(),
        ];
    }
}
