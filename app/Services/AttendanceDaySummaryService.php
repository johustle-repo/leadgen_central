<?php

namespace App\Services;

use App\AttendanceEntryType;
use App\Models\Attendance;
use App\Models\Holiday;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Combines a user's raw attendance scans for a day with holiday/rest-day
 * rules: forces status to "holiday" regardless of clock time, and floors
 * worked minutes at the flat holiday pay rate. Single source of truth for
 * the attendance index page, PDF export, and Excel export.
 */
class AttendanceDaySummaryService
{
    public function __construct(private readonly HolidayService $holidays) {}

    /**
     * @return array{time_in: CarbonInterface|null, time_out: CarbonInterface|null, worked_minutes: int, status: string, late_minutes: int, holiday_label: string|null}
     */
    public function buildForUserAndDate(User $user, CarbonInterface $date, ?Holiday $holiday = null): array
    {
        $day = Carbon::parse($date)->startOfDay();

        $records = Attendance::query()
            ->where('user_id', $user->id)
            ->whereBetween('recorded_at', [$day, $day->copy()->endOfDay()])
            ->orderBy('recorded_at')
            ->get();

        $timeIn = $records->firstWhere('entry_type', AttendanceEntryType::TimeIn)?->recorded_at;
        $timeOut = $records->firstWhere('entry_type', AttendanceEntryType::TimeOut)?->recorded_at;

        return $this->summarize($timeIn, $timeOut, (bool) $user->night_shift_eligible, $holiday);
    }

    /**
     * @param  Collection<int, User>  $users
     * @return list<array{user: User, days: list<array{date: CarbonInterface, time_in: CarbonInterface|null, time_out: CarbonInterface|null, worked_minutes: int, status: string, late_minutes: int, holiday_label: string|null}>}>
     */
    public function buildForPeriod(CarbonInterface $start, CarbonInterface $end, Collection $users): array
    {
        $rangeStart = $start->copy()->startOfDay();
        $rangeEnd = $end->copy()->endOfDay();

        // One query for every user across the whole range instead of one
        // query per user per day - buildForPeriod is called on every index
        // page load now, not just on an occasional Excel download.
        $recordsByUserAndDate = Attendance::query()
            ->whereIn('user_id', $users->pluck('id'))
            ->whereBetween('recorded_at', [$rangeStart, $rangeEnd])
            ->orderBy('recorded_at')
            ->get()
            ->groupBy(fn (Attendance $attendance): string => $attendance->user_id.'|'.$attendance->recorded_at->toDateString());

        $holidaysByDate = $this->holidays->forPeriod($rangeStart, $rangeEnd);

        $periods = [];
        foreach ($users as $user) {
            $days = [];
            $cursor = $rangeStart->copy();
            $last = $rangeEnd->copy()->startOfDay();

            while ($cursor->lessThanOrEqualTo($last)) {
                $dateString = $cursor->toDateString();
                $dayRecords = $recordsByUserAndDate->get($user->id.'|'.$dateString) ?? collect();
                $timeIn = $dayRecords->firstWhere('entry_type', AttendanceEntryType::TimeIn)?->recorded_at;
                $timeOut = $dayRecords->firstWhere('entry_type', AttendanceEntryType::TimeOut)?->recorded_at;
                $holiday = $holidaysByDate[$dateString] ?? null;

                $days[] = [
                    'date' => $cursor->copy(),
                    ...$this->summarize($timeIn, $timeOut, (bool) $user->night_shift_eligible, $holiday),
                ];

                $cursor = $cursor->copy()->addDay();
            }

            $periods[] = ['user' => $user, 'days' => $days];
        }

        return $periods;
    }

    /**
     * @return array{time_in: CarbonInterface|null, time_out: CarbonInterface|null, worked_minutes: int, status: string, late_minutes: int, holiday_label: string|null}
     */
    private function summarize(?CarbonInterface $timeIn, ?CarbonInterface $timeOut, bool $nightShiftEligible, ?Holiday $holiday): array
    {
        if ($holiday !== null) {
            $status = 'holiday';
            $lateMinutes = 0;
            $holidayLabel = $holiday->name;
        } else {
            ['status' => $status, 'late_minutes' => $lateMinutes] = Attendance::lateStatusFor($timeIn);
            $holidayLabel = null;
        }

        $workedMinutes = Attendance::workedMinutesFor($timeIn, $timeOut, $nightShiftEligible);

        if ($holiday !== null) {
            $workedMinutes = max($workedMinutes, Holiday::PAID_WORK_MINUTES);
        }

        return [
            'time_in' => $timeIn,
            'time_out' => $timeOut,
            'worked_minutes' => $workedMinutes,
            'status' => $status,
            'late_minutes' => $lateMinutes,
            'holiday_label' => $holidayLabel,
        ];
    }
}
