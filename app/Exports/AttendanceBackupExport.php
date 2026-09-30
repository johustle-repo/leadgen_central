<?php

namespace App\Exports;

use App\Models\User;
use App\Services\AttendanceDaySummaryService;
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
    public function __construct(private readonly CarbonInterface $start, private readonly CarbonInterface $end) {}

    public function sheets(): array
    {
        $users = User::query()
            ->where('status', 'active')
            ->where(fn (Builder $payroll) => $payroll->where('role', UserRole::Agent)->orWhere('name', AttendanceSummarySheet::APPROVER_NAME))
            ->orderByRaw('employee_code is null')
            ->orderBy('employee_code')
            ->orderBy('name')
            ->get();
        $periods = app(AttendanceDaySummaryService::class)->buildForPeriod($this->start, $this->end, $users);

        $sheets = [new AttendanceSummarySheet($periods, $this->start)];

        $usedTitles = [];
        foreach ($periods as $period) {
            $sheets[] = new AttendanceMemberSheet($period, $this->uniqueSheetTitle($period['user']->name, $usedTitles), $this->start);
        }

        return $sheets;
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
