<?php

namespace Database\Factories;

use App\AttendanceEntryType;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
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
            'recorded_at' => now(),
            'entry_type' => AttendanceEntryType::TimeIn,
            'source' => 'qr_scan',
        ];
    }

    public function timeOut(): static
    {
        return $this->state(fn (): array => ['entry_type' => AttendanceEntryType::TimeOut]);
    }
}
