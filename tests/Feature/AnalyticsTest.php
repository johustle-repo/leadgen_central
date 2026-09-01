<?php

use App\Models\EmailReply;
use App\Models\Lead;
use App\Models\UploadBatch;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('redirects guests from analytics to login', function () {
    $this->get(route('analytics.index'))->assertRedirect(route('login'));
});

it('shows an agent analytics only for their owned leads and replies', function () {
    $this->travelTo('2026-09-01 12:00:00');
    $agent = User::factory()->create();
    $otherAgent = User::factory()->create();
    $qualified = Lead::factory()->for($agent, 'agent')->create([
        'status' => 'qualified_lead',
        'data_source' => 'Tendata',
        'country_code' => 'US',
        'created_by' => $agent->id,
        'created_at' => '2026-08-30 10:00:00',
    ]);
    Lead::factory()->for($agent, 'agent')->create([
        'status' => 'raw',
        'data_source' => 'Lusha',
        'country_code' => 'CA',
        'created_by' => $agent->id,
        'created_at' => '2026-08-31 10:00:00',
    ]);
    Lead::factory()->for($otherAgent, 'agent')->create([
        'status' => 'qualified_lead',
        'created_by' => $otherAgent->id,
        'created_at' => '2026-08-31 10:00:00',
    ]);
    EmailReply::factory()->create([
        'agent_id' => $agent->id,
        'lead_id' => $qualified->id,
        'classification' => 'interested',
        'received_at' => '2026-08-31 11:00:00',
    ]);
    EmailReply::factory()->create([
        'agent_id' => $otherAgent->id,
        'classification' => 'not_interested',
        'received_at' => '2026-08-31 11:00:00',
    ]);
    UploadBatch::factory()->for($agent)->create(['duplicate_rows' => 3, 'created_at' => '2026-08-31 09:00:00']);

    $response = $this->actingAs($agent)->get(route('analytics.index', ['period' => '7_days']));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('analytics/index')
        ->where('period', '7_days')
        ->where('summary.total_leads', 2)
        ->where('summary.qualified_leads', 1)
        ->where('summary.qualification_rate', 50)
        ->where('summary.replies', 1)
        ->where('summary.interested_replies', 1)
        ->where('summary.duplicates', 3)
        ->has('dailyActivity', 7)
        ->where('agentPerformance', []));
});

it('shows administrator agent performance without leaking records outside the selected period', function () {
    $this->travelTo('2026-09-01 12:00:00');
    $administrator = User::factory()->administrator()->create();
    $agent = User::factory()->create(['name' => 'Analytics Agent']);
    Lead::factory()->count(2)->for($agent, 'agent')->create([
        'status' => 'qualified_lead',
        'created_by' => $agent->id,
        'created_at' => '2026-08-30 10:00:00',
    ]);
    Lead::factory()->for($agent, 'agent')->create([
        'created_by' => $agent->id,
        'created_at' => '2026-06-01 10:00:00',
    ]);

    $response = $this->actingAs($administrator)->get(route('analytics.index', ['period' => '7_days']));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('summary.total_leads', 2)
        ->where('agentPerformance.0.name', 'Analytics Agent')
        ->where('agentPerformance.0.leads', 2)
        ->where('agentPerformance.0.qualified', 2)
        ->where('agentPerformance.0.qualification_rate', 100));
});

it('validates a complete chronological custom analytics range', function () {
    $agent = User::factory()->create();

    $this->actingAs($agent)
        ->get(route('analytics.index', ['period' => 'custom', 'date_from' => '2026-09-02', 'date_to' => '2026-09-01']))
        ->assertSessionHasErrors('date_to');
});
