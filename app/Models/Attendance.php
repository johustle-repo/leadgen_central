<?php

namespace App\Models;

use App\AttendanceEntryType;
use Carbon\CarbonInterface;
use Database\Factories\AttendanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

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

    public const MORNING_START_HOUR = 8;

    public const MORNING_END_HOUR = 12;

    public const AFTERNOON_START_HOUR = 13;

    public const AFTERNOON_END_HOUR = 17;

    public const NIGHT_START_HOUR = 18;

    public const NIGHT_END_HOUR = 21;

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
     * The current moment expressed as Philippine wall-clock digits, stored
     * under the app's UTC-labeled `recorded_at` column verbatim - the same
     * "raw digits = local time" convention used for manually-typed entries,
     * so QR scans and manual edits land on the same clock and calendar day.
     */
    public static function now(): CarbonInterface
    {
        return Carbon::parse(Carbon::now('Asia/Manila')->format('Y-m-d H:i:s'));
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

    /**
     * Clamp a scan interval to a fixed window and return the overlap in minutes.
     */
    public static function overlapMinutes(CarbonInterface $timeIn, CarbonInterface $timeOut, CarbonInterface $windowStart, CarbonInterface $windowEnd): int
    {
        $start = $timeIn->greaterThan($windowStart) ? $timeIn : $windowStart;
        $end = $timeOut->lessThan($windowEnd) ? $timeOut : $windowEnd;

        if ($end->lessThanOrEqualTo($start)) {
            return 0;
        }

        return (int) $start->diffInMinutes($end);
    }

    /**
     * Billable minutes for a single time-in/time-out pair, clipped to the
     * morning and afternoon windows (lunch excluded) plus the night window
     * when the user is night-shift eligible and the scan fully brackets it
     * - no partial night credit.
     */
    public static function workedMinutesFor(?CarbonInterface $timeIn, ?CarbonInterface $timeOut, bool $nightShiftEligible): int
    {
        if ($timeIn === null || $timeOut === null) {
            return 0;
        }

        $morningStart = $timeIn->copy()->setTime(self::MORNING_START_HOUR, 0);
        $morningEnd = $timeIn->copy()->setTime(self::MORNING_END_HOUR, 0);
        $afternoonStart = $timeIn->copy()->setTime(self::AFTERNOON_START_HOUR, 0);
        $afternoonEnd = $timeIn->copy()->setTime(self::AFTERNOON_END_HOUR, 0);

        $minutes = self::overlapMinutes($timeIn, $timeOut, $morningStart, $morningEnd)
            + self::overlapMinutes($timeIn, $timeOut, $afternoonStart, $afternoonEnd);

        if ($nightShiftEligible) {
            $nightStart = $timeIn->copy()->setTime(self::NIGHT_START_HOUR, 0);
            $nightEnd = $timeIn->copy()->setTime(self::NIGHT_END_HOUR, 0);

            if ($timeIn->lessThanOrEqualTo($nightStart) && $timeOut->greaterThanOrEqualTo($nightEnd)) {
                $minutes += self::overlapMinutes($timeIn, $timeOut, $nightStart, $nightEnd);
            }
        }

        return $minutes;
    }

    public static function formatMinutes(int $minutes): string
    {
        return sprintf('%dh %02dm', intdiv($minutes, 60), $minutes % 60);
    }
}
