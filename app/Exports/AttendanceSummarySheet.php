<?php

namespace App\Exports;

use App\Models\Attendance;
use App\Models\User;
use Carbon\CarbonInterface;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

class AttendanceSummarySheet implements Export, FromArray, WithEvents, WithTitle
{
    /**
     * @param  list<array{user: User, days: list<array{date: CarbonInterface, time_in: CarbonInterface|null, time_out: CarbonInterface|null, worked_minutes: int, status: string, late_minutes: int, holiday_label: string|null}>}>  $periods
     */
    public function __construct(
        private readonly array $periods,
        private readonly CarbonInterface $start,
        private readonly CarbonInterface $end,
    ) {}

    public function title(): string
    {
        return 'Attendance Summary';
    }

    public function array(): array
    {
        $rows = [
            ['LeadGen Central — Attendance Summary'],
            [$this->start->format('F j, Y').' – '.$this->end->format('F j, Y')],
            ['Generated '.now()->format('Y-m-d H:i')],
            [],
            ['Name', 'Role', 'Attendance Days', 'Attendance Logs', 'Total Hours'],
        ];

        foreach ($this->periods as $period) {
            $user = $period['user'];
            $days = $period['days'];

            $attendanceDays = count(array_filter($days, fn (array $day): bool => $day['time_in'] !== null));
            $logCount = array_sum(array_map(fn (array $day): int => ($day['time_in'] !== null ? 1 : 0) + ($day['time_out'] !== null ? 1 : 0), $days));
            $totalMinutes = array_sum(array_column($days, 'worked_minutes'));

            $rows[] = [
                $user->name,
                $user->role->label(),
                $attendanceDays,
                $logCount,
                Attendance::formatMinutes($totalMinutes),
            ];
        }

        $rows[] = [];
        $rows[] = ['Approved and verified by:'];
        $rows[] = ['Elmar B. Noche'];
        $rows[] = ['Team Leader'];

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A5:E5')->getFont()->setBold(true);

                $highestRow = $sheet->getHighestRow();
                $sheet->getStyle('A'.($highestRow - 2).':A'.$highestRow)->getFont()->setBold(true);
            },
        ];
    }
}
