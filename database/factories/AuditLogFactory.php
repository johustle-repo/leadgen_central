<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
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
            'action' => 'upload_batch.deleted',
            'auditable_type' => 'upload_batch',
            'auditable_id' => fake()->numberBetween(1, 1000),
            'description' => 'Deleted an upload batch.',
            'metadata' => ['batch_code' => 'UPL-TEST-00001'],
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }
}
