<?php

namespace App\Exports;

use App\Models\User;
use App\Services\AttendanceDaySummaryService;
use App\Services\AttendanceImportService;
use App\UserRole;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Monthly attendance backup workbook: one "Attendance Summary" roll-up
 * sheet plus one sheet per active payroll member (the agents and their
 * team leader) with a daily breakdown, ordered by employee code.
 */
class AttendanceBackupExport implements Export, WithMultipleSheets
{
    /**
     * Agents left off the Philippine payroll because they do not work
     * from the Philippines.
     *
     * @var list<string>
     */
    private const NON_PAYROLL_NAMES = ['Amea Sofia Veloria'];

    public function __construct(private readonly CarbonInterface $start, private readonly CarbonInterface $end) {}

    public function sheets(): array
    {
        $periods = $this->payrollPeriods();

        $sheets = [new AttendanceSummarySheet($periods, $this->start)];

        $usedTitles = [];
        foreach ($periods as $period) {
            $sheets[] = new AttendanceMemberSheet($period, $this->uniqueSheetTitle($period['user']->name, $usedTitles), $this->start);
        }

        return $sheets;
    }

    /**
     * Each payroll member's day-by-day attendance for the month. Shared by
     * the Excel workbook and the PDF report so both list the same people.
     *
     * @return list<array{user: User, days: list<array{date: CarbonInterface, time_in: CarbonInterface|null, time_out: CarbonInterface|null, worked_minutes: int, status: string, late_minutes: int, holiday_label: string|null}>}>
     */
    public function payrollPeriods(): array
    {
        $excludedNames = array_map(AttendanceImportService::normalizeNameForMatch(...), self::NON_PAYROLL_NAMES);

        $users = User::query()
            ->where('status', 'active')
            ->where(fn (Builder $payroll) => $payroll->where('role', UserRole::Agent)->orWhere('name', AttendanceSummarySheet::APPROVER_NAME))
            ->orderByRaw('employee_code is null')
            ->orderBy('employee_code')
            ->orderBy('name')
            ->get()
            ->reject(fn (User $user): bool => in_array(AttendanceImportService::normalizeNameForMatch($user->name), $excludedNames, true))
            ->values();

        return app(AttendanceDaySummaryService::class)->buildForPeriod($this->start, $this->end, $users);
    }

    /**
     * Strips characters Excel forbids in sheet names, truncates to the
     * 31-character limit, and de-duplicates collisions with " (2)", " (3)", ...
     *
     * @param  list<string>  $usedTitles
     */
    private function uniqueSheetTitle(string $name, array &$usedTitles): string
    {
        $clean = trim((string) preg_replace('/[:\\\\\/\?\*\[\]]/', '', $name));
        $clean = mb_substr($clean, 0, 31);

        $candidate = $clean;
        $suffix = 2;
        while (in_array($candidate, $usedTitles, true)) {
            $suffixText = " ({$suffix})";
            $candidate = mb_substr($clean, 0, 31 - mb_strlen($suffixText)).$suffixText;
            $suffix++;
        }

        $usedTitles[] = $candidate;

        return $candidate;
    }
}
