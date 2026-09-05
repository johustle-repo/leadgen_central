<?php

use App\Models\Attendance;
use Illuminate\Support\Carbon;

it('credits morning and afternoon overlap but excludes the lunch gap', function () {
    $timeIn = Carbon::parse('2026-04-01 08:00:00');
    $timeOut = Carbon::parse('2026-04-01 17:00:00');

    expect(Attendance::workedMinutesFor($timeIn, $timeOut, false))->toBe(8 * 60);
});

it('clips worked minutes to the shift windows for a late arrival and early departure', function () {
    $timeIn = Carbon::parse('2026-04-01 09:30:00');
    $timeOut = Carbon::parse('2026-04-01 16:00:00');

    // 09:30-12:00 (150m) + 13:00-16:00 (180m)
    expect(Attendance::workedMinutesFor($timeIn, $timeOut, false))->toBe(150 + 180);
});

it('returns zero worked minutes when there is no time out yet', function () {
    expect(Attendance::workedMinutesFor(Carbon::parse('2026-04-01 08:00:00'), null, false))->toBe(0);
});

it('does not credit the night window unless the user is night-shift eligible', function () {
    $timeIn = Carbon::parse('2026-04-01 08:00:00');
    $timeOut = Carbon::parse('2026-04-01 21:00:00');

    expect(Attendance::workedMinutesFor($timeIn, $timeOut, false))->toBe(8 * 60);
});

it('credits the night window only when the scan fully brackets it', function () {
    $timeIn = Carbon::parse('2026-04-01 08:00:00');

    // Fully brackets 18:00-21:00.
    expect(Attendance::workedMinutesFor($timeIn, Carbon::parse('2026-04-01 21:00:00'), true))
        ->toBe(8 * 60 + 3 * 60);

    // Leaves before the night window closes: no partial night credit.
    expect(Attendance::workedMinutesFor($timeIn, Carbon::parse('2026-04-01 20:00:00'), true))
        ->toBe(8 * 60);
});

it('formats minutes as hours and minutes', function () {
    expect(Attendance::formatMinutes(495))->toBe('8h 15m');
    expect(Attendance::formatMinutes(60))->toBe('1h 00m');
});
