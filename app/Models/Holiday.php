<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property CarbonInterface $holiday_date
 * @property string $name
 * @property string $country_code
 * @property string $type
 * @property string|null $notes
 * @property bool|null $is_automatic Set only on an unsaved, synthesized Sunday rest day; see HolidayService.
 */
class Holiday extends Model
{
    /**
     * Flat number of minutes (8 hours) every holiday/rest day is guaranteed
     * as worked-hours credit, regardless of actual attendance scans.
     */
    public const PAID_WORK_MINUTES = 480;

    protected $fillable = ['holiday_date', 'name', 'country_code', 'type', 'notes'];

    protected function casts(): array
    {
        return [
            'holiday_date' => 'date',
        ];
    }

    public static function paidWorkHoursLabel(): string
    {
        return sprintf('%dh %02dm', intdiv(self::PAID_WORK_MINUTES, 60), self::PAID_WORK_MINUTES % 60);
    }
}
