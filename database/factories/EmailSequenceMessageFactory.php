<?php

namespace Database\Factories;

use App\Models\EmailSequenceEnrollment;
use App\Models\EmailSequenceMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailSequenceMessage>
 */
class EmailSequenceMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email_sequence_enrollment_id' => EmailSequenceEnrollment::factory(),
            'step_number' => 1,
            'gmail_message_id' => fake()->uuid(),
            'subject' => fake()->sentence(),
            'body' => fake()->paragraph(),
            'sent_at' => now(),
        ];
    }
}
