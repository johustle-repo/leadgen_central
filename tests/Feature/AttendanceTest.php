<?php

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Holiday;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

it('allows only the super administrator to view attendance', function () {
    $superAdministrator = User::factory()->superAdministrator()->create();
    $administrator = User::factory()->administrator()->create();
    $agent = User::factory()->create();

    $this->actingAs($superAdministrator)->get(route('attendance.index'))->assertOk();
    $this->actingAs($administrator)->get(route('attendance.index'))->assertForbidden();
    $this->actingAs($agent)->get(route('attendance.index'))->assertForbidden();
});

it('records a time in then time out scan in sequence', function () {
    $superAdministrator = User::factory()->superAdministrator()->create();
    $staff = User::factory()->create();

    $this->actingAs($superAdministrator)
        ->post(route('attendance.scan'), ['code' => $staff->qr_value, 'entry_type' => 'time_in'])
        ->assertRedirect()
        ->assertSessionHas('toast.type', 'success');

    $this->actingAs($superAdministrator)
        ->post(route('attendance.scan'), ['code' => $staff->qr_value, 'entry_type' => 'time_out'])
        ->assertRedirect()
        ->assertSessionHas('toast.type', 'success');

    expect(Attendance::where('user_id', $staff->id)->count())->toBe(2);
});

it('rejects a second time in for the same day', function () {
    $superAdministrator = User::factory()->superAdministrator()->create();
    $staff = User::factory()->create();

    Attendance::factory()->for($staff)->create(['recorded_at' => now()->subHours(2)]);

    $this->actingAs($superAdministrator)
        ->post(route('attendance.scan'), ['code' => $staff->qr_value, 'entry_type' => 'time_in'])
        ->assertSessionHasErrors('code');

    expect(Attendance::where('user_id', $staff->id)->count())->toBe(1);
});

it('rejects a time out without an open time in', function () {
    $superAdministrator = User::factory()->superAdministrator()->create();
    $staff = User::factory()->create();

    $this->actingAs($superAdministrator)
        ->post(route('attendance.scan'), ['code' => $staff->qr_value, 'entry_type' => 'time_out'])
        ->assertSessionHasErrors('code');

    expect(Attendance::where('user_id', $staff->id)->count())->toBe(0);
});

it('exports attendance records as a pdf', function () {
    $superAdministrator = User::factory()->superAdministrator()->create();
    $staff = User::factory()->create();
    Attendance::factory()->for($staff)->create();

    $response = $this->actingAs($superAdministrator)->get(route('attendance.export-pdf'));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('application/pdf');
});

it('filters and paginates attendance records and reports summary stats', function () {
    $superAdministrator = User::factory()->superAdministrator()->create();
    $matchingStaff = User::factory()->create(['name' => 'Filter Target']);
    $otherStaff = User::factory()->create(['name' => 'Someone Else']);
    Attendance::factory()->for($matchingStaff)->create(['entry_type' => 'time_in', 'recorded_at' => now()->startOfDay()->addHours(8)]);
    Attendance::factory()->for($otherStaff)->create(['entry_type' => 'time_in', 'recorded_at' => now()->startOfDay()->addHours(8)]);

    $response = $this->actingAs($superAdministrator)->get(route('attendance.index', ['search' => 'Filter Target']));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('attendance/index')
        ->has('records.data', 1)
        ->where('records.data.0.user_name', 'Filter Target')
        ->where('summary.total_records', 2)
        ->where('summary.time_ins_today', 2));
});

it('imports attendance records from a flat JSON array, matching staff by email', function () {
    $superAdministrator = User::factory()->superAdministrator()->create();
    $staff = User::factory()->create(['email' => 'staff@example.test']);

    $file = UploadedFile::fake()->createWithContent('history.json', json_encode([
        ['email' => 'STAFF@example.test', 'entry_type' => 'time_in', 'recorded_at' => '2026-01-05 08:03:00'],
        ['email' => 'staff@example.test', 'entry_type' => 'time_out', 'recorded_at' => '2026-01-05 17:10:00'],
    ]));

    $response = $this->actingAs($superAdministrator)->post(route('attendance.import'), ['files' => [$file]]);

    $response->assertRedirect()->assertSessionHas('toast.type', 'success');
    expect(Attendance::where('user_id', $staff->id)->count())->toBe(2);
    $this->assertDatabaseHas('attendances', ['user_id' => $staff->id, 'entry_type' => 'time_in', 'source' => 'import']);
});

it('matches an import row by name when no email is given', function () {
    $superAdministrator = User::factory()->superAdministrator()->create();
    $staff = User::factory()->create(['name' => 'Import Match Staff']);

    $file = UploadedFile::fake()->createWithContent('history.json', json_encode([
        ['name' => 'Import Match Staff', 'type' => 'in', 'timestamp' => '2026-01-05 08:00:00'],
    ]));

    $this->actingAs($superAdministrator)->post(route('attendance.import'), ['files' => [$file]]);

    $this->assertDatabaseHas('attendances', ['user_id' => $staff->id, 'entry_type' => 'time_in']);
});

it('reads records nested under a phpMyAdmin-style table export', function () {
    $superAdministrator = User::factory()->superAdministrator()->create();
    $staff = User::factory()->create(['email' => 'nested@example.test']);

    $payload = [
        ['type' => 'header', 'version' => '5.2.1'],
        ['type' => 'database', 'name' => 'duscaff'],
        ['type' => 'table', 'name' => 'attendances', 'data' => [
            ['email' => 'nested@example.test', 'entry_type' => 'time_in', 'recorded_at' => '2026-01-06 08:00:00'],
        ]],
    ];
    $file = UploadedFile::fake()->createWithContent('export.json', json_encode($payload));

    $this->actingAs($superAdministrator)->post(route('attendance.import'), ['files' => [$file]]);

    expect(Attendance::where('user_id', $staff->id)->count())->toBe(1);
});

it('skips rows it cannot match or parse and reports why', function () {
    $superAdministrator = User::factory()->superAdministrator()->create();
    $staff = User::factory()->create(['email' => 'known@example.test']);

    $file = UploadedFile::fake()->createWithContent('history.json', json_encode([
        ['email' => 'known@example.test', 'entry_type' => 'time_in', 'recorded_at' => '2026-01-05 08:00:00'],
        ['email' => 'unknown@example.test', 'entry_type' => 'time_in', 'recorded_at' => '2026-01-05 08:00:00'],
        ['email' => 'known@example.test', 'entry_type' => 'sideways', 'recorded_at' => '2026-01-05 08:00:00'],
        ['email' => 'known@example.test', 'entry_type' => 'time_out', 'recorded_at' => 'not-a-date'],
    ]));

    $response = $this->actingAs($superAdministrator)->post(route('attendance.import'), ['files' => [$file]]);

    $response->assertSessionHas('toast.type', 'warning');
    $errors = session('importErrors');
    expect($errors)->toHaveCount(3);
    expect(Attendance::where('user_id', $staff->id)->count())->toBe(1);
});

it('imports the nested export format, routing holiday days to the holidays table', function () {
    $superAdministrator = User::factory()->superAdministrator()->create();
    $staff = User::factory()->create(['name' => 'Elmar B. Noche', 'email' => 'elmar@leadgen.test']);

    $payload = [
        'generated_at' => '2026-09-04T22:12:00+08:00',
        'period' => ['year' => 2026, 'month' => 4, 'label' => 'April 2026'],
        'users' => [
            [
                'id' => 3,
                'name' => 'Elmar B. Noche',
                'sub_name' => 'Alexander Bennett',
                'email' => 'a.bennett@duscaff.com',
                'attendance_days' => [
                    [
                        'date' => '2026-04-01',
                        'is_holiday' => false,
                        'logs' => [
                            ['id' => 1, 'entry_type' => 'time_in', 'recorded_at' => '2026-04-01T07:45:57+08:00'],
                            ['id' => 2, 'entry_type' => 'time_out', 'recorded_at' => '2026-04-01T17:14:00+08:00'],
                        ],
                    ],
                    [
                        'date' => '2026-04-09',
                        'is_holiday' => true,
                        'holiday_name' => 'Araw ng Kagitingan',
                        'holiday_type_label' => 'Regular Holiday',
                        'holiday_notes' => 'Day of Valor in the Philippines.',
                        'logs' => [
                            ['id' => 0, 'entry_type' => 'holiday', 'recorded_at' => '2026-04-09T00:00:00+08:00'],
                        ],
                    ],
                ],
            ],
        ],
    ];
    $file = UploadedFile::fake()->createWithContent('attendance-backup-2026-04.json', json_encode($payload));

    $response = $this->actingAs($superAdministrator)->post(route('attendance.import'), ['files' => [$file]]);

    $response->assertSessionHas('toast.type', 'success');
    expect(Attendance::where('user_id', $staff->id)->count())->toBe(2);
    expect(Holiday::query()->whereDate('holiday_date', '2026-04-09')->where('name', 'Araw ng Kagitingan')->where('country_code', 'PH')->exists())->toBeTrue();
    $this->assertDatabaseMissing('attendances', ['entry_type' => 'holiday']);
});

it('does not duplicate attendance or holidays when the same file is imported twice', function () {
    $superAdministrator = User::factory()->superAdministrator()->create();
    User::factory()->create(['email' => 'staff@example.test']);

    $payload = [
        'users' => [
            [
                'name' => 'Someone',
                'email' => 'staff@example.test',
                'attendance_days' => [
                    ['date' => '2026-04-01', 'is_holiday' => false, 'logs' => [
                        ['entry_type' => 'time_in', 'recorded_at' => '2026-04-01T07:45:00+08:00'],
                    ]],
                    ['date' => '2026-04-09', 'is_holiday' => true, 'holiday_name' => 'Araw ng Kagitingan', 'holiday_type_label' => 'Regular Holiday', 'logs' => [
                        ['entry_type' => 'holiday', 'recorded_at' => '2026-04-09T00:00:00+08:00'],
                    ]],
                ],
            ],
        ],
    ];
    $file = fn () => UploadedFile::fake()->createWithContent('history.json', json_encode($payload));

    $this->actingAs($superAdministrator)->post(route('attendance.import'), ['files' => [$file()]]);
    $second = $this->actingAs($superAdministrator)->post(route('attendance.import'), ['files' => [$file()]]);

    $second->assertSessionHas('toast.message', 'Imported 0 attendance record(s); 1 already existed.');
    expect(Attendance::query()->count())->toBe(1);
    expect(Holiday::query()->count())->toBe(1);
});

it('imports multiple files in one request and aggregates the results', function () {
    $superAdministrator = User::factory()->superAdministrator()->create();
    $staff = User::factory()->create(['email' => 'multi@example.test']);

    $fileA = UploadedFile::fake()->createWithContent('a.json', json_encode([
        ['email' => 'multi@example.test', 'entry_type' => 'time_in', 'recorded_at' => '2026-04-01 08:00:00'],
    ]));
    $fileB = UploadedFile::fake()->createWithContent('b.json', json_encode([
        ['email' => 'multi@example.test', 'entry_type' => 'time_out', 'recorded_at' => '2026-04-01 17:00:00'],
    ]));

    $response = $this->actingAs($superAdministrator)->post(route('attendance.import'), ['files' => [$fileA, $fileB]]);

    $response->assertSessionHas('toast.message', 'Imported 2 attendance record(s).');
    expect(Attendance::where('user_id', $staff->id)->count())->toBe(2);
});

it('forbids a regular administrator from importing attendance', function () {
    $administrator = User::factory()->administrator()->create();
    $file = UploadedFile::fake()->createWithContent('history.json', json_encode([]));

    $this->actingAs($administrator)->post(route('attendance.import'), ['files' => [$file]])->assertForbidden();
});

it('organizes attendance by month and agent, marking an automatic Sunday rest day', function () {
    // Named so `orderBy('name')` puts the Sunday staff member at a known index.
    $superAdministrator = User::factory()->superAdministrator()->create(['name' => 'AAA Admin']);
    User::factory()->create(['name' => 'ZZZ Sunday Staff', 'status' => 'active']);

    $response = $this->actingAs($superAdministrator)->get(route('attendance.index', ['month' => '2026-04']));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('attendance/index')
        ->where('selectedMonth', '2026-04')
        ->has('monthlyAttendance', 2)
        ->where('monthlyAttendance.1.user_name', 'ZZZ Sunday Staff')
        ->has('monthlyAttendance.1.days', 30)
        // 2026-04-05 (index 4) is a Sunday with no seeded holiday row.
        ->where('monthlyAttendance.1.days.4.date', '2026-04-05')
        ->where('monthlyAttendance.1.days.4.status', 'holiday')
        ->where('monthlyAttendance.1.days.4.holiday_label', 'Sunday Rest Day'));
});

it('exports the attendance backup workbook for the super administrator only', function () {
    $superAdministrator = User::factory()->superAdministrator()->create();
    $administrator = User::factory()->administrator()->create();
    $staff = User::factory()->create();
    Attendance::factory()->for($staff)->create();

    $this->actingAs($administrator)->get(route('attendance.export-excel'))->assertForbidden();

    $response = $this->actingAs($superAdministrator)->get(route('attendance.export-excel'));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('spreadsheet');
});

it('lets the super administrator fill in a missing time in', function () {
    $superAdministrator = User::factory()->superAdministrator()->create();
    $staff = User::factory()->create();

    $response = $this->actingAs($superAdministrator)->put(route('attendance.update-entry', [
        'user' => $staff->id,
        'date' => '2026-04-01',
        'entryType' => 'time_in',
    ]), ['recorded_at' => '2026-04-01 08:05:00']);

    $response->assertRedirect()->assertSessionHas('toast.type', 'success');
    $this->assertDatabaseHas('attendances', [
        'user_id' => $staff->id,
        'entry_type' => 'time_in',
        'source' => 'manual_adjustment',
    ]);
    expect(AuditLog::query()->where('action', 'attendance.manual_edit')->exists())->toBeTrue();
});

it('lets the super administrator edit an existing time out', function () {
    $superAdministrator = User::factory()->superAdministrator()->create();
    $staff = User::factory()->create();
    Attendance::factory()->for($staff)->create(['recorded_at' => '2026-04-01 08:00:00', 'entry_type' => 'time_in']);
    $timeOut = Attendance::factory()->for($staff)->create(['recorded_at' => '2026-04-01 17:00:00', 'entry_type' => 'time_out']);

    $this->actingAs($superAdministrator)->put(route('attendance.update-entry', [
        'user' => $staff->id,
        'date' => '2026-04-01',
        'entryType' => 'time_out',
    ]), ['recorded_at' => '2026-04-01 18:30:00'])->assertRedirect();

    $timeOut->refresh();
    expect($timeOut->recorded_at->format('H:i'))->toBe('18:30');
    expect($timeOut->source)->toBe('manual_adjustment');
});

it('rejects a time out with no time in yet', function () {
    $superAdministrator = User::factory()->superAdministrator()->create();
    $staff = User::factory()->create();

    $this->actingAs($superAdministrator)->put(route('attendance.update-entry', [
        'user' => $staff->id,
        'date' => '2026-04-01',
        'entryType' => 'time_out',
    ]), ['recorded_at' => '2026-04-01 17:00:00'])->assertSessionHasErrors('recorded_at');

    $this->assertDatabaseMissing('attendances', ['user_id' => $staff->id]);
});

it('rejects a time in set after the existing time out', function () {
    $superAdministrator = User::factory()->superAdministrator()->create();
    $staff = User::factory()->create();
    Attendance::factory()->for($staff)->create(['recorded_at' => '2026-04-01 17:00:00', 'entry_type' => 'time_out']);

    $this->actingAs($superAdministrator)->put(route('attendance.update-entry', [
        'user' => $staff->id,
        'date' => '2026-04-01',
        'entryType' => 'time_in',
    ]), ['recorded_at' => '2026-04-01 18:00:00'])->assertSessionHasErrors('recorded_at');
});

it('clears an attendance entry when recorded_at is submitted empty', function () {
    $superAdministrator = User::factory()->superAdministrator()->create();
    $staff = User::factory()->create();
    Attendance::factory()->for($staff)->create(['recorded_at' => '2026-04-01 08:00:00', 'entry_type' => 'time_in']);

    $this->actingAs($superAdministrator)->put(route('attendance.update-entry', [
        'user' => $staff->id,
        'date' => '2026-04-01',
        'entryType' => 'time_in',
    ]), ['recorded_at' => ''])->assertRedirect()->assertSessionHas('toast.type', 'success');

    $this->assertDatabaseMissing('attendances', ['user_id' => $staff->id]);
});

it('forbids a regular administrator from editing attendance entries', function () {
    $administrator = User::factory()->administrator()->create();
    $staff = User::factory()->create();

    $this->actingAs($administrator)->put(route('attendance.update-entry', [
        'user' => $staff->id,
        'date' => '2026-04-01',
        'entryType' => 'time_in',
    ]), ['recorded_at' => '2026-04-01 08:00:00'])->assertForbidden();
});
