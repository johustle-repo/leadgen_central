<?php

use App\Models\SystemSetting;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('shows the configured upload limit to administrators', function () {
    $administrator = User::factory()->administrator()->create();
    SystemSetting::factory()->create(['key' => 'csv_max_kilobytes', 'value' => '10240']);

    $this->actingAs($administrator)->get(route('system-settings.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('system-settings/edit')
            ->where('settings.csv_max_kilobytes', 10240));
});

it('updates the upload limit', function () {
    $administrator = User::factory()->administrator()->create();

    $response = $this->actingAs($administrator)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->put(route('system-settings.update'), [
            'csv_max_kilobytes' => 8192,
        ]);

    $response->assertRedirect()->assertSessionHas('toast.message', 'Settings updated.');
    $this->assertDatabaseHas('system_settings', ['key' => 'csv_max_kilobytes', 'value' => '8192']);
});
