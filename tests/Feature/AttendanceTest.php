<?php

use App\Models\Attendance;
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

    $response = $this->actingAs($superAdministrator)->post(route('attendance.import'), ['file' => $file]);

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

    $this->actingAs($superAdministrator)->post(route('attendance.import'), ['file' => $file]);

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

    $this->actingAs($superAdministrator)->post(route('attendance.import'), ['file' => $file]);

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

    $response = $this->actingAs($superAdministrator)->post(route('attendance.import'), ['file' => $file]);

    $response->assertSessionHas('toast.type', 'warning');
    $errors = session('importErrors');
    expect($errors)->toHaveCount(3);
    expect(Attendance::where('user_id', $staff->id)->count())->toBe(1);
});

it('forbids a regular administrator from importing attendance', function () {
    $administrator = User::factory()->administrator()->create();
    $file = UploadedFile::fake()->createWithContent('history.json', json_encode([]));

    $this->actingAs($administrator)->post(route('attendance.import'), ['file' => $file])->assertForbidden();
});
