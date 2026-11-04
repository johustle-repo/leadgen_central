<?php

namespace App\Services;

use App\EmploymentType;
use App\Exports\AttendanceBackupExport;
use App\Exports\AttendanceSummarySheet;
use App\Models\Attendance;
use App\Models\Holiday;
use App\Models\PayrollAdjustment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Monthly payroll from attendance, following the team's payroll sheets:
 * pay = monthly salary / days in the month * paid days.
 *
 * Full-time paid days in a full month are every calendar day, Sundays
 * and holidays included, minus absent workdays (a non-holiday day
 * already past with no time in). In the month a member joins, rest days
 * are not paid, so only the days they actually worked (plus holidays)
 * count. Part-time paid days are hours worked / 8. A payroll adjustment
 * can set the category or the paid days for one member in one month.
 */
class PayrollCalculator
{
    public const PAID_HOURS_PER_DAY = 8;

    public function __construct(private readonly HolidayService $holidays) {}

    /**
     * @return array{
     *     month: string,
     *     month_label: string,
     *     days_in_month: int,
     *     in_progress: bool,
     *     members: list<array{user_id: int, name: string, employee_code: string|null, position: string, employment_type: string, employment_type_label: string, default_employment_type: string, joined_on: string|null, joined_this_month: bool, monthly_salary: float|null, day_rate: float|null, hour_rate: float|null, employed_days: int, absent_days: int, worked_days: int, worked_minutes: int, paid_days: float, paid_days_adjusted: bool, adjustment: array{employment_type: string|null, paid_days: float|null}, pay: float|null, unrounded_pay: float|null}>,
     *     totals: array{salary: float, missing_salary_count: int},
     * }
     */
    public function forMonth(CarbonInterface $month): array
    {
        $monthStart = $month->copy()->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $daysInMonth = $monthStart->daysInMonth;

        $restDates = collect($this->holidays->forPeriod($monthStart, $monthEnd))
            ->filter(fn (?Holiday $holiday): bool => $holiday?->type === 'rest_day')
            ->keys()
            ->flip();

        $adjustments = PayrollAdjustment::query()
            ->whereDate('month', $monthStart->toDateString())
            ->get()
            ->keyBy('user_id');

        $members = [];
        foreach ((new AttendanceBackupExport($monthStart, $monthEnd))->payrollPeriods() as $period) {
            $member = $this->memberPayroll($period['user'], $period['days'], $daysInMonth, $monthStart, $monthEnd, $restDates, $adjustments->get($period['user']->id));

            if ($member !== null) {
                $members[] = $member;
            }
        }

        return [
            'month' => $monthStart->format('Y-m'),
            'month_label' => $monthStart->format('F Y'),
            'days_in_month' => $daysInMonth,
            'in_progress' => Attendance::now()->between($monthStart, $monthEnd),
            'members' => $members,
            'totals' => [
                'salary' => round(array_sum(array_map(fn (array $member): float => $member['unrounded_pay'] ?? 0.0, $members)), 2),
                'missing_salary_count' => count(array_filter($members, fn (array $member): bool => $member['monthly_salary'] === null)),
            ],
        ];
    }

    /**
     * @param  list<array{date: CarbonInterface, time_in: CarbonInterface|null, time_out: CarbonInterface|null, worked_minutes: int, status: string}>  $days
     * @param  Collection<string, int>  $restDates
     * @return array{user_id: int, name: string, employee_code: string|null, position: string, employment_type: string, employment_type_label: string, default_employment_type: string, joined_on: string|null, joined_this_month: bool, monthly_salary: float|null, day_rate: float|null, hour_rate: float|null, employed_days: int, absent_days: int, worked_days: int, worked_minutes: int, paid_days: float, paid_days_adjusted: bool, adjustment: array{employment_type: string|null, paid_days: float|null}, pay: float|null, unrounded_pay: float|null}|null
     */
    private function memberPayroll(User $user, array $days, int $daysInMonth, CarbonInterface $monthStart, CarbonInterface $monthEnd, Collection $restDates, ?PayrollAdjustment $adjustment): ?array
    {
        $joinDate = $user->payrollJoinDate();

        if ($joinDate !== null && $joinDate->greaterThan($monthEnd)) {
            return null;
        }

        $joinedThisMonth = $joinDate !== null && $joinDate->greaterThan($monthStart);
        $today = Attendance::now()->startOfDay();
        $employedDays = array_values(array_filter($days, fn (array $day): bool => $joinDate === null || $day['date']->greaterThanOrEqualTo($joinDate)));
        $absentDays = array_filter($employedDays, fn (array $day): bool => $day['status'] !== 'holiday' && $day['time_in'] === null && $day['date']->lessThan($today));
        $unpaidRestDays = $joinedThisMonth
            ? array_filter($employedDays, fn (array $day): bool => $day['time_in'] === null && $restDates->has($day['date']->toDateString()))
            : [];
        $workedDays = array_filter($employedDays, fn (array $day): bool => $day['time_in'] !== null);
        $workedMinutes = array_sum(array_column($workedDays, 'worked_minutes'));

        $employmentType = $adjustment->employment_type ?? $user->employment_type;
        $adjustedPaidDays = $adjustment?->paid_days === null ? null : (float) $adjustment->paid_days;

        $paidDays = match (true) {
            $adjustedPaidDays !== null => $adjustedPaidDays,
            $employmentType === EmploymentType::PartTime => round($workedMinutes / Holiday::PAID_WORK_MINUTES, 3),
            default => (float) (count($employedDays) - count($absentDays) - count($unpaidRestDays)),
        };

        $salary = $user->monthly_salary === null ? null : (float) $user->monthly_salary;

        return [
            'user_id' => $user->id,
            'name' => $user->name,
            'employee_code' => $user->employee_code,
            'position' => AttendanceSummarySheet::positionLabel($user),
            'employment_type' => $employmentType->value,
            'employment_type_label' => $employmentType->label(),
            'default_employment_type' => $user->employment_type->value,
            'joined_on' => $joinDate?->toDateString(),
            'joined_this_month' => $joinedThisMonth,
            'monthly_salary' => $salary,
            'day_rate' => $salary === null ? null : round($salary / $daysInMonth, 2),
            'hour_rate' => $salary === null ? null : round($salary / $daysInMonth / self::PAID_HOURS_PER_DAY, 2),
            'employed_days' => count($employedDays),
            'absent_days' => count($absentDays),
            'worked_days' => count($workedDays),
            'worked_minutes' => $workedMinutes,
            'paid_days' => $paidDays,
            'paid_days_adjusted' => $adjustedPaidDays !== null,
            'adjustment' => [
                'employment_type' => $adjustment?->employment_type?->value,
                'paid_days' => $adjustedPaidDays,
            ],
            'pay' => $salary === null ? null : round($salary * $paidDays / $daysInMonth, 2),
            'unrounded_pay' => $salary === null ? null : $salary * $paidDays / $daysInMonth,
        ];
    }
}
