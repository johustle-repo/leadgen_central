<?php

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia as Assert;

it('shows the configured upload limit to administrators', function () {
    $administrator = User::factory()->administrator()->create();
    SystemSetting::factory()->create(['key' => 'csv_max_kilobytes', 'value' => '10240']);
    SystemSetting::factory()->create(['key' => 'csv_max_files', 'value' => '25']);

    $this->actingAs($administrator)->get(route('system-settings.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('system-settings/edit')
            ->where('settings.csv_max_kilobytes', 10240)
            ->where('settings.csv_max_files', 25));
});

it('updates the upload limit', function () {
    $administrator = User::factory()->administrator()->create();

    $response = $this->actingAs($administrator)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->put(route('system-settings.update'), [
            'csv_max_kilobytes' => 8192,
            'csv_max_files' => 40,
        ]);

    $response->assertRedirect()->assertSessionHas('toast.message', 'Settings updated.');
    $this->assertDatabaseHas('system_settings', ['key' => 'csv_max_kilobytes', 'value' => '8192']);
    $this->assertDatabaseHas('system_settings', ['key' => 'csv_max_files', 'value' => '40']);
});

it('shows a super administrator the site as online and manageable', function () {
    $superAdministrator = User::factory()->superAdministrator()->create();

    $this->actingAs($superAdministrator)->get(route('system-settings.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('maintenance.active', false)
            ->where('maintenance.can_manage', true));
});

it('hides maintenance management from a regular administrator', function () {
    $administrator = User::factory()->administrator()->create();

    $this->actingAs($administrator)->get(route('system-settings.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('maintenance.can_manage', false));
});

it('lets a super administrator enable maintenance mode', function () {
    $superAdministrator = User::factory()->superAdministrator()->create();
    Artisan::shouldReceive('call')->once()->with('down')->andReturn(0);

    $response = $this->actingAs($superAdministrator)
        ->post(route('system-settings.maintenance.enable'));

    $response->assertRedirect()->assertSessionHas('toast.message', 'Maintenance mode enabled.');
    $this->assertDatabaseHas('audit_logs', ['user_id' => $superAdministrator->id, 'action' => 'system.maintenance_enabled']);
});

it('lets a super administrator disable maintenance mode', function () {
    $superAdministrator = User::factory()->superAdministrator()->create();
    Artisan::shouldReceive('call')->once()->with('up')->andReturn(0);

    $response = $this->actingAs($superAdministrator)
        ->delete(route('system-settings.maintenance.disable'));

    $response->assertRedirect()->assertSessionHas('toast.message', 'Maintenance mode disabled.');
    $this->assertDatabaseHas('audit_logs', ['user_id' => $superAdministrator->id, 'action' => 'system.maintenance_disabled']);
});

it('forbids a regular administrator from toggling maintenance mode', function () {
    $administrator = User::factory()->administrator()->create();

    $this->actingAs($administrator)
        ->post(route('system-settings.maintenance.enable'))
        ->assertForbidden();

    $this->actingAs($administrator)
        ->delete(route('system-settings.maintenance.disable'))
        ->assertForbidden();
});

it('forbids agents from toggling maintenance mode', function () {
    $agent = User::factory()->create();

    $this->actingAs($agent)
        ->post(route('system-settings.maintenance.enable'))
        ->assertForbidden();
});
