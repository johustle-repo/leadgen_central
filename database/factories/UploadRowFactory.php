<?php

namespace Database\Factories;

use App\Models\UploadBatch;
use App\Models\UploadRow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UploadRow>
 */
class UploadRowFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['upload_batch_id' => UploadBatch::factory(), 'row_number' => 2, 'raw_data' => ['Company Name' => fake()->company()], 'processing_status' => 'pending'];
    }
}
