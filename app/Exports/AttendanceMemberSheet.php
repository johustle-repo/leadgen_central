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
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * One member's daily breakdown laid out like the team's hand-made sheet:
 * shared title block, member profile and totals, the day table, then the
 * signed approval block.
 */
class AttendanceMemberSheet implements Export, FromArray, WithEvents, WithTitle
{
    private const TABLE_HEADER_ROW = 15;

    /** @var array<string, float> */
    private const COLUMN_WIDTHS = ['A' => 35.71, 'B' => 22.86, 'C' => 14.86, 'D' => 16.0, 'E' => 29.71, 'F' => 49.14, 'G' => 84.86];

    /** @var array<'workday'|'rest_day'|'holiday', string> */
    private const ROW_FILLS = ['workday' => 'FFFFFF', 'rest_day' => 'FFE699', 'holiday' => 'A9D18E'];

    /** @var list<'workday'|'rest_day'|'holiday'> */
    private array $dayTypes = [];

    /**
     * @param  array{user: User, days: list<array{date: CarbonInterface, time_in: CarbonInterface|null, time_out: CarbonInterface|null, worked_minutes: int, status: string, late_minutes: int, holiday_label: string|null}>}  $period
     */
    public function __construct(
        private readonly array $period,
        private readonly string $sheetTitle,
        private readonly CarbonInterface $start,
    ) {}

    public function title(): string
    {
        return $this->sheetTitle;
    }

    public function array(): array
    {
        $user = $this->period['user'];
        $totals = AttendanceSummarySheet::memberTotals($this->period['days']);

        $rows = [
            ...AttendanceSummarySheet::reportHeaderRows($this->start),
            [''],
            ['Name', $user->name],
            ['Sub Name', $user->alias_name ?? ''],
            ['Email', $user->alias_email ?? ''],
            ['Role', AttendanceSummarySheet::isApprover($user) ? AttendanceSummarySheet::APPROVER_POSITION : 'Member'],
            ['Employee Code', $user->employee_code ?? ''],
            ['Status', ucfirst($user->status->value)],
            ['Attendance Days', $totals['attendance_days']],
            ['Attendance Logs', $totals['log_count']],
            ['Member Total Hours', Attendance::formatMinutes($totals['worked_minutes'])],
            [''],
            ['Date', 'Day', 'Time In', 'Time Out', 'Daily Total Hours', 'Logs', ''],
        ];

        $this->dayTypes = [];

        foreach ($this->period['days'] as $day) {
            $isRestDay = $day['status'] === 'holiday' && str_contains(strtolower((string) $day['holiday_label']), 'rest');
            $isHoliday = $day['status'] === 'holiday' && ! $isRestDay;
            $timeIn = $day['time_in']?->format('H:i');
            $timeOut = $day['time_out']?->format('H:i');

            $placeholder = match (true) {
                $isRestDay => 'Rest Day',
                $isHoliday => 'Holiday',
                default => null,
            };

            $logs = match (true) {
                $isRestDay => 'Rest Day - '.$day['holiday_label'],
                $isHoliday => '',
                default => implode(', ', array_filter([
                    $timeIn !== null ? "Time In - {$timeIn}" : null,
                    $timeOut !== null ? "Time Out - {$timeOut}" : null,
                ])),
            };

            $rows[] = [
                $day['date']->format('M d, Y'),
                $day['date']->format('l'),
                $placeholder ?? $timeIn ?? '',
                $placeholder ?? $timeOut ?? '',
                Attendance::formatMinutes($day['worked_minutes']),
                $logs,
                $isHoliday ? (string) $day['holiday_label'] : '',
            ];

            $this->dayTypes[] = $isRestDay ? 'rest_day' : ($isHoliday ? 'holiday' : 'workday');
        }

        return [
            ...$rows,
            [''],
            ['Approved and verified by:'],
            [''],
            [AttendanceSummarySheet::APPROVER_NAME],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();

                AttendanceSummarySheet::styleReportFrame($sheet, self::COLUMN_WIDTHS, $lastRow);
                $sheet->getStyle('B5')->getFont()->applyFromArray(AttendanceSummarySheet::BOLD_FONT);
                $sheet->getStyle('B13')->getFont()->applyFromArray(AttendanceSummarySheet::BOLD_FONT);
                $sheet->getStyle('A'.self::TABLE_HEADER_ROW.':G'.self::TABLE_HEADER_ROW)->getFont()->applyFromArray(AttendanceSummarySheet::BOLD_FONT);

                foreach ($this->dayTypes as $index => $type) {
                    $rowNumber = self::TABLE_HEADER_ROW + 1 + $index;
                    $sheet->getStyle("A{$rowNumber}:G{$rowNumber}")
                        ->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()
                        ->setRGB(self::ROW_FILLS[$type]);
                }

                $sheet->getRowDimension($lastRow - 1)->setRowHeight(46.5);
                $sheet->getStyle("A{$lastRow}")->applyFromArray([
                    'font' => AttendanceSummarySheet::BOLD_FONT,
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                AttendanceSummarySheet::addSignature($sheet, 'A'.($lastRow - 2), 34, 10);
            },
        ];
    }
}
