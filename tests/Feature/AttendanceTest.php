<?php

use App\Models\Attendance;
use App\Models\User;

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
