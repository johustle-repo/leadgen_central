<?php

namespace App\Exports;

use App\Models\Attendance;
use App\Models\User;
use App\UserRole;
use Carbon\CarbonInterface;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

/**
 * Monthly roll-up sheet laid out like the team's hand-made attendance
 * summary: title, period block, totals block, member table, then the
 * signed approval block.
 */
class AttendanceSummarySheet implements Export, FromArray, WithEvents, WithTitle
{
    private const TABLE_HEADER_ROW = 10;

    private const APPROVER_NAME = 'Elmar B. Noche';

    private const APPROVER_POSITION = 'Team Leader';

    /** @var array<string, float> */
    private const COLUMN_WIDTHS = ['A' => 35.71, 'B' => 28.43, 'C' => 14.0, 'D' => 16.0, 'E' => 15.71, 'F' => 11.0];

    /**
     * @param  list<array{user: User, days: list<array{date: CarbonInterface, time_in: CarbonInterface|null, time_out: CarbonInterface|null, worked_minutes: int, status: string, late_minutes: int, holiday_label: string|null}>}>  $periods
     */
    public function __construct(
        private readonly array $periods,
        private readonly CarbonInterface $start,
    ) {}

    public function title(): string
    {
        return 'Attendance Summary';
    }

    public function array(): array
    {
        $memberRows = [];
        $totalDays = 0;
        $totalLogs = 0;
        $totalMinutes = 0;

        foreach ($this->periods as $period) {
            $user = $period['user'];
            $days = $period['days'];

            $attendanceDays = count(array_filter($days, fn (array $day): bool => $day['time_in'] !== null));
            $logCount = array_sum(array_map(fn (array $day): int => ($day['time_in'] !== null ? 1 : 0) + ($day['time_out'] !== null ? 1 : 0), $days));
            $workedMinutes = array_sum(array_column($days, 'worked_minutes'));

            $totalDays += $attendanceDays;
            $totalLogs += $logCount;
            $totalMinutes += $workedMinutes;

            $memberRows[] = [
                $user->employee_code ?? '',
                $user->name,
                $user->role === UserRole::Agent ? 'Team Member' : $user->role->label(),
                $attendanceDays,
                $logCount,
                Attendance::formatMinutes($workedMinutes),
            ];
        }

        return [
            [self::APPROVER_NAME."'s Team PH Attendance Summary - TimeIn/TimeOut-Month of ".$this->start->format('F Y')],
            ['Period', $this->start->format('F Y')],
            ['Generated at', now()->format('M j, Y g:i A')],
            [''],
            ['Total Members', count($this->periods)],
            ['Total Attendance Days', $totalDays],
            ['Total Attendance Logs', $totalLogs],
            ['Total Hours', Attendance::formatMinutes($totalMinutes)],
            [''],
            ['Employee Code', 'Name', 'Position', 'Attendance Days', 'Attendance Logs', 'Total Hours'],
            ...$memberRows,
            [''],
            [''],
            ['Approved and verified by:'],
            [''],
            [self::APPROVER_NAME],
            [self::APPROVER_POSITION],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();
                $signatureRow = $lastRow - 4;
                $boldFont = ['name' => 'Arial', 'size' => 10, 'bold' => true];

                foreach (self::COLUMN_WIDTHS as $column => $width) {
                    $sheet->getColumnDimension($column)->setWidth($width);
                }

                $sheet->getStyle("A1:F{$lastRow}")->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);

                $sheet->mergeCells('A1:F1');
                $sheet->getRowDimension(1)->setRowHeight(34.5);
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => $boldFont,
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                $sheet->getStyle('B2')->getFont()->applyFromArray($boldFont);
                $sheet->getStyle('B8')->getFont()->applyFromArray($boldFont);
                $sheet->getStyle('A'.self::TABLE_HEADER_ROW.':F'.self::TABLE_HEADER_ROW)->getFont()->applyFromArray($boldFont);

                $sheet->getRowDimension($lastRow - 2)->setRowHeight(37.5);
                $sheet->getStyle('A'.($lastRow - 1))->getFont()->applyFromArray($boldFont);
                $sheet->getStyle('A'.($lastRow - 1).':A'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $signature = new Drawing;
                $signature->setName('Approver Signature');
                $signature->setPath(resource_path('images/attendance-approver-signature.png'));
                $signature->setCoordinates("A{$signatureRow}");
                $signature->setOffsetX(45);
                $signature->setOffsetY(16);
                $signature->setResizeProportional(false);
                $signature->setWidth(153);
                $signature->setHeight(141);
                $signature->setWorksheet($sheet);
            },
        ];
    }
}
