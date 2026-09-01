<?php

namespace Database\Factories;

use App\Models\Lead;
use App\Models\LeadAttachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadAttachment>
 */
class LeadAttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'uploaded_by' => User::factory(),
            'disk' => 'local',
            'path' => 'lead-attachments/example.pdf',
            'original_name' => 'company-profile.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'label' => 'Company profile',
        ];
    }
}
