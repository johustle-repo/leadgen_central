<?php

use App\Models\Lead;
use App\Models\User;

it('neutralizes spreadsheet formulas and audits lead exports', function () {
    $agent = User::factory()->create();
    Lead::factory()->for($agent, 'agent')->create([
        'company_name' => '=HYPERLINK("https://attacker.test")',
        'contact_person' => '+cmd|calc',
        'email' => 'safe@example.com',
        'created_by' => $agent->id,
    ]);

    $response = $this->actingAs($agent)
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.8'])
        ->get(route('leads.download-raw'));

    $response->assertOk()->assertDownload();
    expect($response->streamedContent())
        ->toContain("'=HYPERLINK")
        ->toContain("'+cmd|calc")
        ->not->toContain(',=HYPERLINK');
    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $agent->id,
        'action' => 'leads.raw_exported',
        'ip_address' => '203.0.113.8',
    ]);
});

it('rate limits repeated lead data exports per user', function () {
    $agent = User::factory()->create();

    foreach (range(1, 10) as $attempt) {
        $this->actingAs($agent)->get(route('leads.download-raw'))->assertOk();
    }

    $this->actingAs($agent)->get(route('leads.download-raw'))->assertTooManyRequests();
});

it('requires recent password confirmation before changing system settings', function () {
    $administrator = User::factory()->administrator()->create();

    $this->actingAs($administrator)
        ->put(route('system-settings.update'), [])
        ->assertRedirect(route('password.confirm'));
});
