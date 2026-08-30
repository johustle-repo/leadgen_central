<?php

namespace Database\Factories;

use App\Models\UploadBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UploadBatch>
 */
class UploadBatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'original_filename' => 'leads.csv', 'stored_filename' => 'lead-imports/test.csv', 'file_size' => 100, 'headers' => ['Company Name'], 'column_mapping' => ['Company Name' => 'company_name'], 'processing_status' => 'completed'];
    }
}
