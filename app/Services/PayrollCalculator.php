<?php

namespace App\Services;

use App\EmploymentType;
use App\Exports\AttendanceBackupExport;
use App\Exports\AttendanceSummarySheet;
use App\Models\Attendance;
use App\Models\Holiday;
use App\Models\PayrollMonth;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Monthly payroll from attendance, following the team's payroll sheets:
 * pay = monthly salary / days in the month * paid days.
 *
 * Every calendar day from the member's join date is paid, Sundays and
 * holidays included, minus absent workdays (a non-holiday day already
 * past with no time in). Part-time members are paid for their worked
 * hours instead: paid days = worked hours / 8. The hourly rate is the
 * day rate over an 8-hour day.
 */
class PayrollCalculator
{
    public const PAID_HOURS_PER_DAY = 8;

    /**
     * @return array{
     *     month: string,
     *     month_label: string,
     *     days_in_month: int,
     *     in_progress: bool,
     *     members: list<array{user_id: int, name: string, employee_code: string|null, position: string, employment_type: string, employment_type_label: string, joined_on: string|null, monthly_salary: float|null, day_rate: float|null, hour_rate: float|null, employed_days: int, absent_days: int, worked_days: int, worked_minutes: int, paid_days: float, pay: float|null}>,
     *     utilities: array{usd_to_php_rate: float|null, items: list<array{key: string, label: string, php: float, usd: float|null}>, total_usd: float},
     *     totals: array{salary: float, service: float, to_pay: float, missing_salary_count: int},
     * }
     */
    public function forMonth(CarbonInterface $month): array
    {
        $monthStart = $month->copy()->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $daysInMonth = $monthStart->daysInMonth;

        $members = [];
        foreach ((new AttendanceBackupExport($monthStart, $monthEnd))->payrollPeriods() as $period) {
            $member = $this->memberPayroll($period['user'], $period['days'], $daysInMonth, $monthEnd);

            if ($member !== null) {
                $members[] = $member;
            }
        }

        $utilities = $this->utilities($monthStart);
        $salaryTotal = round(array_sum(array_map(fn (array $member): float => $member['pay'] ?? 0.0, $members)), 2);

        return [
            'month' => $monthStart->format('Y-m'),
            'month_label' => $monthStart->format('F Y'),
            'days_in_month' => $daysInMonth,
            'in_progress' => Attendance::now()->between($monthStart, $monthEnd),
            'members' => $members,
            'utilities' => $utilities,
            'totals' => [
                'salary' => $salaryTotal,
                'service' => $utilities['total_usd'],
                'to_pay' => round($salaryTotal + $utilities['total_usd'], 2),
                'missing_salary_count' => count(array_filter($members, fn (array $member): bool => $member['monthly_salary'] === null)),
            ],
        ];
    }

    /**
     * @param  list<array{date: CarbonInterface, time_in: CarbonInterface|null, time_out: CarbonInterface|null, worked_minutes: int, status: string}>  $days
     * @return array{user_id: int, name: string, employee_code: string|null, position: string, employment_type: string, employment_type_label: string, joined_on: string|null, monthly_salary: float|null, day_rate: float|null, hour_rate: float|null, employed_days: int, absent_days: int, worked_days: int, worked_minutes: int, paid_days: float, pay: float|null}|null
     */
    private function memberPayroll(User $user, array $days, int $daysInMonth, CarbonInterface $monthEnd): ?array
    {
        $joinDate = $user->payrollJoinDate();

        if ($joinDate !== null && $joinDate->greaterThan($monthEnd)) {
            return null;
        }

        $today = Attendance::now()->startOfDay();
        $employedDays = array_values(array_filter($days, fn (array $day): bool => $joinDate === null || $day['date']->greaterThanOrEqualTo($joinDate)));
        $absentDays = array_filter($employedDays, fn (array $day): bool => $day['status'] !== 'holiday' && $day['time_in'] === null && $day['date']->lessThan($today));
        $workedDays = array_filter($employedDays, fn (array $day): bool => $day['time_in'] !== null);
        $workedMinutes = array_sum(array_column($workedDays, 'worked_minutes'));

        $paidDays = $user->employment_type === EmploymentType::PartTime
            ? round($workedMinutes / Holiday::PAID_WORK_MINUTES, 2)
            : (float) (count($employedDays) - count($absentDays));

        $salary = $user->monthly_salary === null ? null : (float) $user->monthly_salary;

        return [
            'user_id' => $user->id,
            'name' => $user->name,
            'employee_code' => $user->employee_code,
            'position' => AttendanceSummarySheet::positionLabel($user),
            'employment_type' => $user->employment_type->value,
            'employment_type_label' => $user->employment_type->label(),
            'joined_on' => $joinDate?->toDateString(),
            'monthly_salary' => $salary,
            'day_rate' => $salary === null ? null : round($salary / $daysInMonth, 2),
            'hour_rate' => $salary === null ? null : round($salary / $daysInMonth / self::PAID_HOURS_PER_DAY, 2),
            'employed_days' => count($employedDays),
            'absent_days' => count($absentDays),
            'worked_days' => count($workedDays),
            'worked_minutes' => $workedMinutes,
            'paid_days' => $paidDays,
            'pay' => $salary === null ? null : round($salary * $paidDays / $daysInMonth, 2),
        ];
    }

    /**
     * @return array{usd_to_php_rate: float|null, items: list<array{key: string, label: string, php: float, usd: float|null}>, total_usd: float}
     */
    private function utilities(CarbonInterface $monthStart): array
    {
        $payrollMonth = PayrollMonth::query()->whereDate('month', $monthStart->toDateString())->first();
        $rate = $payrollMonth?->usd_to_php_rate === null ? null : (float) $payrollMonth->usd_to_php_rate;

        $items = array_map(function (string $key) use ($payrollMonth, $rate): array {
            $php = (float) ($payrollMonth->{$key.'_php'} ?? 0);

            return [
                'key' => $key,
                'label' => ucfirst($key),
                'php' => $php,
                'usd' => $rate === null || $rate <= 0 ? null : round($php / $rate, 2),
            ];
        }, ['internet', 'electricity', 'water']);

        return [
            'usd_to_php_rate' => $rate,
            'items' => $items,
            'total_usd' => round(array_sum(array_map(fn (array $item): float => $item['usd'] ?? 0.0, $items)), 2),
        ];
    }
}
