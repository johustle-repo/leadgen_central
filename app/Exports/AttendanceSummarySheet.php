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
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Monthly roll-up sheet laid out like the team's hand-made attendance
 * summary: title, period block, totals block, member table, then the
 * signed approval block.
 */
class AttendanceSummarySheet implements Export, FromArray, WithEvents, WithTitle
{
    private const TABLE_HEADER_ROW = 10;

    public const APPROVER_NAME = 'Elmar B. Noche';

    public const APPROVER_POSITION = 'Team Leader';

    public const SIGNATURE_PATH = 'images/attendance-approver-signature.png';

    /** @var array{name: string, size: int, bold: bool} */
    public const BOLD_FONT = ['name' => 'Arial', 'size' => 10, 'bold' => true];

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
            $totals = self::memberTotals($period['days']);

            $totalDays += $totals['attendance_days'];
            $totalLogs += $totals['log_count'];
            $totalMinutes += $totals['worked_minutes'];

            $memberRows[] = [
                $user->employee_code ?? '',
                $user->name,
                self::positionLabel($user),
                $totals['attendance_days'],
                $totals['log_count'],
                Attendance::formatMinutes($totals['worked_minutes']),
            ];
        }

        return [
            ...self::reportHeaderRows($this->start),
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

                self::styleReportFrame($sheet, self::COLUMN_WIDTHS, $lastRow);
                $sheet->getRowDimension(1)->setRowHeight(34.5);
                $sheet->getStyle('B8')->getFont()->applyFromArray(self::BOLD_FONT);
                $sheet->getStyle('A'.self::TABLE_HEADER_ROW.':F'.self::TABLE_HEADER_ROW)->getFont()->applyFromArray(self::BOLD_FONT);

                $sheet->getRowDimension($lastRow - 2)->setRowHeight(37.5);
                $sheet->getStyle('A'.($lastRow - 1))->getFont()->applyFromArray(self::BOLD_FONT);
                $sheet->getStyle('A'.($lastRow - 1).':A'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                self::addSignature($sheet, 'A'.($lastRow - 4), 45, 16);
            },
        ];
    }

    /**
     * Title, period and generated-at rows shared by every sheet in the
     * workbook and the PDF. "Generated at" is the Philippine wall-clock
     * time of the export, matching how attendance times are recorded.
     *
     * @return list<list<string>>
     */
    public static function reportHeaderRows(CarbonInterface $month): array
    {
        return [
            [self::APPROVER_NAME."'s Team PH Attendance Summary - TimeIn/TimeOut-Month of ".$month->format('F Y')],
            ['Period', $month->format('F Y')],
            ['Generated at', Attendance::now()->format('M j, Y g:i A')],
        ];
    }

    /**
     * Attendance days count every day with a time in plus paid rest days and holidays.
     *
     * @param  list<array{time_in: CarbonInterface|null, time_out: CarbonInterface|null, worked_minutes: int, status: string}>  $days
     * @return array{attendance_days: int, log_count: int, worked_minutes: int}
     */
    public static function memberTotals(array $days): array
    {
        return [
            'attendance_days' => count(array_filter($days, fn (array $day): bool => $day['time_in'] !== null || $day['status'] === 'holiday')),
            'log_count' => array_sum(array_map(fn (array $day): int => ($day['time_in'] !== null ? 1 : 0) + ($day['time_out'] !== null ? 1 : 0), $days)),
            'worked_minutes' => array_sum(array_column($days, 'worked_minutes')),
        ];
    }

    public static function isApprover(User $user): bool
    {
        return $user->name === self::APPROVER_NAME;
    }

    /**
     * Summary-table position wording: the team leader keeps their title, everyone else is a team member.
     */
    public static function positionLabel(User $user): string
    {
        return self::isApprover($user) ? self::APPROVER_POSITION : 'Team Member';
    }

    /**
     * Column widths, thin borders around every used cell, the merged bold
     * title and the bold period value.
     *
     * @param  array<string, float>  $columnWidths
     */
    public static function styleReportFrame(Worksheet $sheet, array $columnWidths, int $lastRow): void
    {
        foreach ($columnWidths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $lastColumn = array_key_last($columnWidths);

        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $sheet->mergeCells('A1:F1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => self::BOLD_FONT,
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle('B2')->getFont()->applyFromArray(self::BOLD_FONT);
    }

    public static function addSignature(Worksheet $sheet, string $coordinates, int $offsetX, int $offsetY): void
    {
        $signature = new Drawing;
        $signature->setName('Approver Signature');
        $signature->setPath(resource_path(self::SIGNATURE_PATH));
        $signature->setCoordinates($coordinates);
        $signature->setOffsetX($offsetX);
        $signature->setOffsetY($offsetY);
        $signature->setResizeProportional(false);
        $signature->setWidth(153);
        $signature->setHeight(141);
        $signature->setWorksheet($sheet);
    }
}
