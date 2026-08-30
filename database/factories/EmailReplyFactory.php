<?php

namespace Database\Factories;

use App\EmailReplyClassification;
use App\Models\EmailReply;
use App\Models\GmailConnection;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailReply>
 */
class EmailReplyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gmail_connection_id' => GmailConnection::factory(),
            'agent_id' => User::factory(),
            'lead_id' => Lead::factory(),
            'gmail_message_id' => fake()->unique()->uuid(),
            'gmail_thread_id' => fake()->uuid(),
            'sender_name' => fake()->name(),
            'sender_email' => fake()->safeEmail(),
            'subject' => fake()->sentence(),
            'body_preview' => fake()->sentence(),
            'body_text' => fake()->paragraph(),
            'classification' => EmailReplyClassification::NeedsReview,
            'classification_reason' => 'The reply needs manual review.',
            'is_read' => false,
            'received_at' => now(),
        ];
    }
}
