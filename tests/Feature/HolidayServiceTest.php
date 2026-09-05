<?php

use App\Models\Attendance;
use App\Models\Holiday;
use App\Models\User;
use App\Services\AttendanceDaySummaryService;
use App\Services\HolidayService;
use Illuminate\Support\Carbon;

it('returns null for an ordinary weekday with no holiday row', function () {
    $service = app(HolidayService::class);

    expect($service->forDate(Carbon::parse('2026-04-06')))->toBeNull(); // a Monday
});

it('automatically synthesizes an unsaved Sunday rest day with no seeding', function () {
    $service = app(HolidayService::class);

    $holiday = $service->forDate(Carbon::parse('2026-04-05')); // a Sunday

    expect($holiday)->not->toBeNull();
    expect($holiday->name)->toBe('Sunday Rest Day');
    expect($holiday->type)->toBe('rest_day');
    expect($holiday->is_automatic)->toBeTrue();
    expect($holiday->exists)->toBeFalse();
    expect(Holiday::query()->count())->toBe(0);
});

it('prefers a real holiday row over the automatic Sunday synthesis', function () {
    Holiday::query()->create([
        'holiday_date' => '2026-04-05', // a Sunday
        'country_code' => 'PH',
        'name' => 'Special Company Event',
        'type' => 'special_non_working',
    ]);

    $holiday = app(HolidayService::class)->forDate(Carbon::parse('2026-04-05'));

    expect($holiday->name)->toBe('Special Company Event');
    expect($holiday->is_automatic ?? false)->toBeFalse();
});

it('forces the day status to holiday and floors worked minutes at 8 hours with no scans', function () {
    $staff = User::factory()->create();
    $holiday = Holiday::query()->create([
        'holiday_date' => '2026-04-09',
        'country_code' => 'PH',
        'name' => 'Araw ng Kagitingan',
        'type' => 'regular',
    ]);

    $day = app(AttendanceDaySummaryService::class)->buildForUserAndDate($staff, Carbon::parse('2026-04-09'), $holiday);

    expect($day['status'])->toBe('holiday');
    expect($day['holiday_label'])->toBe('Araw ng Kagitingan');
    expect($day['worked_minutes'])->toBe(Holiday::PAID_WORK_MINUTES);
});

it('floors worked minutes at 8 hours on a holiday even with a partial scan', function () {
    $staff = User::factory()->create();
    Attendance::factory()->for($staff)->create(['recorded_at' => '2026-04-09 08:00:00', 'entry_type' => 'time_in']);
    Attendance::factory()->for($staff)->create(['recorded_at' => '2026-04-09 10:00:00', 'entry_type' => 'time_out']);
    $holiday = Holiday::query()->create(['holiday_date' => '2026-04-09', 'country_code' => 'PH', 'name' => 'Company Holiday', 'type' => 'regular']);

    $day = app(AttendanceDaySummaryService::class)->buildForUserAndDate($staff, Carbon::parse('2026-04-09'), $holiday);

    expect($day['worked_minutes'])->toBe(Holiday::PAID_WORK_MINUTES);
});

it('never marks a holiday as late, no matter the clock-in time', function () {
    $staff = User::factory()->create();
    Attendance::factory()->for($staff)->create(['recorded_at' => '2026-04-09 11:45:00', 'entry_type' => 'time_in']);
    $holiday = Holiday::query()->create(['holiday_date' => '2026-04-09', 'country_code' => 'PH', 'name' => 'Company Holiday', 'type' => 'regular']);

    $day = app(AttendanceDaySummaryService::class)->buildForUserAndDate($staff, Carbon::parse('2026-04-09'), $holiday);

    expect($day['status'])->toBe('holiday');
    expect($day['late_minutes'])->toBe(0);
});

it('batches a whole period for multiple users without losing per-day accuracy', function () {
    $alice = User::factory()->create(['name' => 'Alice']);
    $bob = User::factory()->create(['name' => 'Bob']);

    Attendance::factory()->for($alice)->create(['recorded_at' => '2026-04-01 08:00:00', 'entry_type' => 'time_in']);
    Attendance::factory()->for($alice)->create(['recorded_at' => '2026-04-01 17:00:00', 'entry_type' => 'time_out']);
    Attendance::factory()->for($bob)->create(['recorded_at' => '2026-04-02 09:00:00', 'entry_type' => 'time_in']);

    $periods = app(AttendanceDaySummaryService::class)->buildForPeriod(
        Carbon::parse('2026-04-01'),
        Carbon::parse('2026-04-03'),
        collect([$alice, $bob]),
    );

    expect($periods)->toHaveCount(2);

    $alicePeriod = collect($periods)->firstWhere('user.id', $alice->id);
    $alicePeriod['days'] = collect($alicePeriod['days'])->keyBy(fn (array $day): string => $day['date']->toDateString());
    expect($alicePeriod['days']['2026-04-01']['time_in'])->not->toBeNull();
    expect($alicePeriod['days']['2026-04-01']['worked_minutes'])->toBe(8 * 60);
    expect($alicePeriod['days']['2026-04-02']['time_in'])->toBeNull();

    $bobPeriod = collect($periods)->firstWhere('user.id', $bob->id);
    $bobPeriod['days'] = collect($bobPeriod['days'])->keyBy(fn (array $day): string => $day['date']->toDateString());
    expect($bobPeriod['days']['2026-04-02']['time_in'])->not->toBeNull();
    expect($bobPeriod['days']['2026-04-01']['time_in'])->toBeNull();
});
