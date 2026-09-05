<?php

namespace App\Services;

use App\Models\Holiday;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Resolves whether a date is a holiday or rest day, real database rows plus
 * an automatic Sunday rest day that requires zero seeding: every Sunday
 * with no explicit holiday row is treated as a rest day system-wide.
 */
class HolidayService
{
    /**
     * @param  array<int, CarbonInterface|string>  $dates
     * @return array<string, Holiday|null> keyed by Y-m-d
     */
    public function forDates(array $dates, string $countryCode = 'PH'): array
    {
        $dateStrings = collect($dates)
            ->map(fn (CarbonInterface|string $date): string => Carbon::parse($date)->toDateString())
            ->unique()
            ->values();

        // A plain `whereIn`/`whereBetween` on `holiday_date` is unreliable
        // across database engines/casts (a stored value can carry a
        // "00:00:00" time suffix even for a date-only column, e.g. on
        // SQLite, which breaks lexical string comparison against a bare
        // "Y-m-d" bound). The table is tiny, so just fetch every row for
        // the country and match by the cast Carbon's date component instead.
        $rows = Holiday::query()
            ->where('country_code', $countryCode)
            ->get()
            ->keyBy(fn (Holiday $holiday): string => $holiday->holiday_date->toDateString());

        $result = [];
        foreach ($dateStrings as $dateString) {
            $result[$dateString] = $rows->get($dateString) ?? $this->synthesizeSunday($dateString, $countryCode);
        }

        return $result;
    }

    public function forDate(CarbonInterface|string $date, string $countryCode = 'PH'): ?Holiday
    {
        $dateString = Carbon::parse($date)->toDateString();

        return $this->forDates([$dateString], $countryCode)[$dateString] ?? null;
    }

    /**
     * @return array<string, Holiday|null> keyed by Y-m-d
     */
    public function forPeriod(CarbonInterface $start, CarbonInterface $end, string $countryCode = 'PH'): array
    {
        $dates = [];
        $cursor = $start->copy()->startOfDay();
        $last = $end->copy()->startOfDay();

        while ($cursor->lessThanOrEqualTo($last)) {
            $dates[] = $cursor->toDateString();
            $cursor = $cursor->copy()->addDay();
        }

        return $this->forDates($dates, $countryCode);
    }

    private function synthesizeSunday(string $dateString, string $countryCode): ?Holiday
    {
        $date = Carbon::parse($dateString);

        if (! $date->isSunday()) {
            return null;
        }

        $holiday = new Holiday([
            'holiday_date' => $dateString,
            'name' => 'Sunday Rest Day',
            'country_code' => $countryCode,
            'type' => 'rest_day',
            'notes' => 'Automatically set for Sundays.',
        ]);

        $holiday->is_automatic = true;

        return $holiday;
    }
}
