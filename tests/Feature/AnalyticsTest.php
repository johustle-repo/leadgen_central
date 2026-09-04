<?php

use App\Models\AuditLog;
use App\Models\EmailReply;
use App\Models\Lead;
use App\Models\UploadBatch;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('redirects guests from analytics to login', function () {
    $this->get(route('analytics.index'))->assertRedirect(route('login'));
});

it('redirects guests from the analytics report export to login', function () {
    $this->get(route('analytics.export'))->assertRedirect(route('login'));
});

it('downloads a report CSV scoped to the agents own leads and logs the export', function () {
    $this->travelTo('2026-09-01 12:00:00');
    $agent = User::factory()->create();
    $otherAgent = User::factory()->create();
    Lead::factory()->for($agent, 'agent')->create([
        'status' => 'qualified_lead',
        'data_source' => 'Tendata',
        'created_by' => $agent->id,
        'created_at' => '2026-08-30 10:00:00',
    ]);
    Lead::factory()->for($otherAgent, 'agent')->create([
        'status' => 'qualified_lead',
        'data_source' => 'Lusha',
        'created_by' => $otherAgent->id,
        'created_at' => '2026-08-30 10:00:00',
    ]);

    $response = $this->actingAs($agent)->get(route('analytics.export', ['period' => '7_days']));

    $response->assertOk()->assertDownload('Analytics-Report-2026-08-26-to-2026-09-01.csv');
    expect($response->streamedContent())
        ->toContain('"Report period","2026-08-26 to 2026-09-01"')
        ->toContain('Summary')
        ->toContain('"Leads created",1')
        ->toContain('"Qualified leads",1')
        ->toContain('"Lead status"')
        ->toContain('Tendata')
        ->not->toContain('Lusha')
        ->not->toContain('Agent performance');
    $this->assertDatabaseHas(AuditLog::class, [
        'user_id' => $agent->id,
        'action' => 'analytics.exported',
    ]);
});

it('includes the agent performance section in an administrators report export', function () {
    $this->travelTo('2026-09-01 12:00:00');
    $administrator = User::factory()->administrator()->create();
    $agent = User::factory()->create(['name' => 'Export Agent']);
    Lead::factory()->for($agent, 'agent')->create([
        'status' => 'qualified_lead',
        'created_by' => $agent->id,
        'created_at' => '2026-08-30 10:00:00',
    ]);

    $response = $this->actingAs($administrator)->get(route('analytics.export', ['period' => '7_days']));

    $response->assertOk();
    expect($response->streamedContent())
        ->toContain('Agent performance')
        ->toContain('Export Agent');
});

it('rejects a report export when the custom end date precedes the start date', function () {
    $agent = User::factory()->create();

    $this->actingAs($agent)
        ->get(route('analytics.export', ['period' => 'custom', 'date_from' => '2026-09-02', 'date_to' => '2026-09-01']))
        ->assertSessionHasErrors('date_to');
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
        ->where('agentPerformance', [])
        ->where('funnel', [])
        ->where('funnelExcluded', [])
        ->where('dataQualityTrend', [])
        ->where('uploadTimingHeatmap', [])
        ->where('industries', []));
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

it('shows administrators the lead funnel, data quality trend, upload timing heatmap, and industries', function () {
    $this->travelTo('2026-09-01 12:00:00');
    $administrator = User::factory()->administrator()->create();
    $agent = User::factory()->create(['name' => 'Pattern Agent']);
    Lead::factory()->count(2)->for($agent, 'agent')->create(['status' => 'raw', 'industry' => 'Manufacturing', 'created_by' => $agent->id, 'created_at' => '2026-08-30 10:00:00']);
    Lead::factory()->for($agent, 'agent')->create(['status' => 'possible_lead', 'industry' => 'Manufacturing', 'created_by' => $agent->id, 'created_at' => '2026-08-30 10:00:00']);
    Lead::factory()->count(2)->for($agent, 'agent')->create(['status' => 'qualified_lead', 'industry' => 'Logistics', 'created_by' => $agent->id, 'created_at' => '2026-08-30 10:00:00']);
    Lead::factory()->for($agent, 'agent')->create(['status' => 'forwarded', 'industry' => 'Logistics', 'created_by' => $agent->id, 'created_at' => '2026-08-30 10:00:00']);
    Lead::factory()->for($agent, 'agent')->create(['status' => 'duplicate', 'industry' => 'Logistics', 'created_by' => $agent->id, 'created_at' => '2026-08-30 10:00:00']);
    UploadBatch::factory()->for($agent)->create([
        'total_rows' => 100,
        'duplicate_rows' => 10,
        'rejected_rows' => 5,
        'location_error_rows' => 2,
        'created_at' => '2026-08-31 09:00:00',
    ]);

    $response = $this->actingAs($administrator)->get(route('analytics.index', ['period' => '7_days']));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('funnel.0.stage', 'Raw')
        ->where('funnel.0.count', 6)
        ->where('funnel.1.stage', 'Validated')
        ->where('funnel.1.count', 4)
        ->where('funnel.2.stage', 'Possible Lead')
        ->where('funnel.2.count', 4)
        ->where('funnel.3.stage', 'Qualified Lead')
        ->where('funnel.3.count', 3)
        ->where('funnel.4.stage', 'Forwarded')
        ->where('funnel.4.count', 1)
        ->where('funnelExcluded.0.label', 'duplicate')
        ->where('funnelExcluded.0.value', 1)
        ->has('dataQualityTrend', 7)
        ->where('dataQualityTrend.5.duplicate_rate', 10)
        ->where('dataQualityTrend.5.error_rate', 5)
        ->where('dataQualityTrend.5.location_error_rate', 2)
        ->has('uploadTimingHeatmap', 7)
        ->where('industries.0.label', 'Logistics')
        ->where('industries.0.value', 4)
        ->where('agentPerformance.0.name', 'Pattern Agent')
        ->where('agentPerformance.0.uploads', 1)
        ->where('agentPerformance.0.avg_batch_size', 100)
        ->where('agentPerformance.0.duplicate_rate', 10)
        ->where('agentPerformance.0.error_rate', 5));
});

it('validates a complete chronological custom analytics range', function () {
    $agent = User::factory()->create();

    $this->actingAs($agent)
        ->get(route('analytics.index', ['period' => 'custom', 'date_from' => '2026-09-02', 'date_to' => '2026-09-01']))
        ->assertSessionHasErrors('date_to');
});
