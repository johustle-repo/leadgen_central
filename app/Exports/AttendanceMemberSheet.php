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
use PhpOffice\PhpSpreadsheet\Style\Fill;

class AttendanceMemberSheet implements Export, FromArray, WithEvents, WithTitle
{
    private const HEADER_ROWS = 4;

    /** @var array<int, 'rest_day'|'holiday'> */
    private array $rowFills = [];

    /**
     * @param  array{user: User, days: list<array{date: CarbonInterface, time_in: CarbonInterface|null, time_out: CarbonInterface|null, worked_minutes: int, status: string, late_minutes: int, holiday_label: string|null}>}  $period
     */
    public function __construct(
        private readonly array $period,
        private readonly string $sheetTitle,
    ) {}

    public function title(): string
    {
        return $this->sheetTitle;
    }

    public function array(): array
    {
        $user = $this->period['user'];

        $rows = [
            [$user->name],
            [$user->role->label()],
            [],
            ['Date', 'Day', 'Time In', 'Time Out', 'Daily Total Hours', 'Notes'],
        ];

        $this->rowFills = [];

        foreach ($this->period['days'] as $index => $day) {
            $isRestDay = $day['status'] === 'holiday' && str_contains(strtolower((string) $day['holiday_label']), 'rest');
            $isHoliday = $day['status'] === 'holiday' && ! $isRestDay;
            $placeholder = $isRestDay ? 'Rest Day' : ($isHoliday ? 'Holiday' : null);

            $rows[] = [
                $day['date']->format('Y-m-d'),
                $day['date']->format('l'),
                $placeholder ?? ($day['time_in']?->format('H:i') ?? ''),
                $placeholder ?? ($day['time_out']?->format('H:i') ?? ''),
                Attendance::formatMinutes($day['worked_minutes']),
                $day['holiday_label'] ?? '',
            ];

            if ($isRestDay || $isHoliday) {
                $this->rowFills[self::HEADER_ROWS + 1 + $index] = $isRestDay ? 'rest_day' : 'holiday';
            }
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
                $sheet->getStyle('A'.self::HEADER_ROWS.':F'.self::HEADER_ROWS)->getFont()->setBold(true);

                foreach ($this->rowFills as $rowNumber => $type) {
                    $color = $type === 'rest_day' ? 'FFE699' : 'C6E0B4';
                    $sheet->getStyle("A{$rowNumber}:F{$rowNumber}")
                        ->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()
                        ->setRGB($color);

                    if ($type === 'holiday') {
                        $sheet->getStyle("F{$rowNumber}")->getAlignment()->setWrapText(true);
                    }
                }
            },
        ];
    }
}
