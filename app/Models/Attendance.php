<?php

namespace App\Models;

use App\AttendanceEntryType;
use Carbon\CarbonInterface;
use Database\Factories\AttendanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property CarbonInterface $recorded_at
 * @property AttendanceEntryType $entry_type
 * @property string $source
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class Attendance extends Model
{
    /** @use HasFactory<AttendanceFactory> */
    use HasFactory;

    public const OFFICE_START_HOUR = 8;

    public const OFFICE_END_HOUR = 17;

    protected $fillable = ['user_id', 'recorded_at', 'entry_type', 'source'];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'entry_type' => AttendanceEntryType::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Determine the late status for a given time-in moment.
     *
     * @return array{status: 'no_time_in'|'on_time'|'late', late_minutes: int}
     */
    public static function lateStatusFor(?CarbonInterface $timeIn): array
    {
        if ($timeIn === null) {
            return ['status' => 'no_time_in', 'late_minutes' => 0];
        }

        $cutoff = $timeIn->copy()->setTime(self::OFFICE_START_HOUR, 0);

        if ($timeIn->lessThanOrEqualTo($cutoff)) {
            return ['status' => 'on_time', 'late_minutes' => 0];
        }

        return ['status' => 'late', 'late_minutes' => (int) $cutoff->diffInMinutes($timeIn)];
    }
}
