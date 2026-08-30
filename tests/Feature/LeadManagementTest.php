<?php

use App\Models\Lead;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('creates a manual lead owned by the authenticated agent', function () {
    $agent = User::factory()->create();
    $response = $this->actingAs($agent)->post(route('leads.store'), ['lead_date' => '2026-08-25', 'company_name' => 'Acme Ventures', 'website' => 'acme.test', 'contact_person' => 'Ada', 'email' => 'hello@acme.test', 'country_code' => 'us', 'city' => 'Austin', 'import_trades' => 'Machinery', 'linkedin_url' => 'https://linkedin.com/company/acme', 'data_source' => 'Tendata/Lusha', 'source_url' => 'https://example.com/acme']);
    $response->assertRedirect(route('leads.create'))->assertSessionHas('toast', [
        'type' => 'success',
        'message' => 'Lead saved successfully.',
    ]);
    $this->assertDatabaseHas('leads', ['agent_id' => $agent->id, 'lead_date' => '2026-08-25 00:00:00', 'company_name' => 'Acme Ventures', 'contact_person' => 'Ada', 'country_code' => 'US', 'city' => 'Austin', 'import_trades' => 'Machinery', 'data_source' => 'Tendata/Lusha', 'source_url' => 'https://example.com/acme', 'source' => 'manual', 'created_by' => $agent->id]);
    expect(Lead::firstOrFail()->lead_code)->toStartWith('LD-');
});

it('prefills a new lead from the users latest entry while clearing contact details', function () {
    $this->travelTo('2026-08-26 10:00:00');
    $agent = User::factory()->create();
    $latestLead = Lead::factory()->for($agent, 'agent')->create([
        'company_name' => 'Acme Ventures',
        'website' => 'https://acme.test',
        'contact_person' => 'Ada',
        'email' => 'ada@acme.test',
        'country_code' => 'US',
        'city' => 'Austin',
        'import_trades' => 'Machinery',
        'linkedin_url' => 'https://linkedin.com/in/ada',
        'data_source' => 'Tendata/Lusha',
        'source_url' => 'https://example.com/acme',
        'created_by' => $agent->id,
    ]);

    $response = $this->actingAs($agent)->get(route('leads.create'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('leads/form')
        ->where('formVersion', $latestLead->id)
        ->where('defaults.lead_date', '2026-08-26')
        ->where('defaults.company_name', 'Acme Ventures')
        ->where('defaults.website', 'https://acme.test')
        ->where('defaults.country_code', 'US')
        ->where('defaults.city', 'Austin')
        ->where('defaults.import_trades', 'Machinery')
        ->where('defaults.data_source', 'Tendata/Lusha')
        ->where('defaults.source_url', 'https://example.com/acme')
        ->where('defaults.contact_person', '')
        ->where('defaults.email', '')
        ->where('defaults.linkedin_url', ''));
});

it('rejects a lead without a company name', function () {
    $agent = User::factory()->create();
    $response = $this->actingAs($agent)->post(route('leads.store'), ['email' => 'hello@example.com']);
    $response->assertSessionHasErrors('company_name');
    $this->assertDatabaseCount('leads', 0);
});

it('rejects a manual lead with an unsupported data source', function () {
    $agent = User::factory()->create();

    $response = $this->actingAs($agent)->post(route('leads.store'), ['company_name' => 'Acme Ventures', 'data_source' => 'Unsupported']);

    $response->assertSessionHasErrors('data_source');
    $this->assertDatabaseCount('leads', 0);
});

it('limits an agent to ten contacts from the same company', function () {
    $agent = User::factory()->create();
    Lead::factory()->count(10)->for($agent, 'agent')->create([
        'company_name' => 'Acme Ventures',
        'normalized_company_name' => 'acme ventures',
        'created_by' => $agent->id,
    ]);

    $response = $this->actingAs($agent)->post(route('leads.store'), [
        'company_name' => '  ACME   Ventures ',
        'contact_person' => 'Eleventh Contact',
        'email' => 'eleventh@acme.test',
    ]);

    $response->assertSessionHasErrors(['company_name' => 'An agent can have a maximum of 10 contacts for the same company.']);
    expect(Lead::query()->whereBelongsTo($agent, 'agent')->count())->toBe(10);
});

it('supports a safe lead quantity filter', function () {
    $agent = User::factory()->create();
    Lead::factory()->count(12)->for($agent, 'agent')->create(['created_by' => $agent->id]);

    $response = $this->actingAs($agent)->get(route('leads.index', ['per_page' => 10]));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('leads/index')
        ->has('leads.data', 10)
        ->where('leads.per_page', 10)
        ->where('filters.per_page', '10'));
});

it('prevents agents from viewing another agents lead', function () {
    $agent = User::factory()->create();
    $otherLead = Lead::factory()->create();
    $this->actingAs($agent)->get(route('leads.edit', $otherLead))->assertForbidden();
});

it('allows a lead owner to edit and update their own lead', function () {
    $agent = User::factory()->create();
    $lead = Lead::factory()->for($agent, 'agent')->create(['company_name' => 'Original Company', 'created_by' => $agent->id]);

    $this->actingAs($agent)->get(route('leads.edit', $lead))->assertOk();
    $response = $this->actingAs($agent)->put(route('leads.update', $lead), ['company_name' => 'Updated Company']);

    $response->assertRedirect();
    $this->assertDatabaseHas('leads', ['id' => $lead->id, 'agent_id' => $agent->id, 'company_name' => 'Updated Company', 'updated_by' => $agent->id]);
});

it('prevents an agent from updating another agents lead', function () {
    $agent = User::factory()->create();
    $otherAgent = User::factory()->create();
    $lead = Lead::factory()->for($otherAgent, 'agent')->create(['company_name' => 'Protected Company', 'created_by' => $otherAgent->id]);

    $this->actingAs($agent)->put(route('leads.update', $lead), ['company_name' => 'Unauthorized Change'])->assertForbidden();

    $this->assertDatabaseHas('leads', ['id' => $lead->id, 'company_name' => 'Protected Company']);
});

it('only returns an agents own leads', function () {
    $agent = User::factory()->create();
    $otherAgent = User::factory()->create();
    $own = Lead::factory()->for($agent, 'agent')->create(['company_name' => 'Owned Company', 'created_by' => $agent->id]);
    $other = Lead::factory()->for($otherAgent, 'agent')->create(['company_name' => 'Other Company', 'created_by' => $otherAgent->id]);
    $this->actingAs($agent)->get(route('leads.index'))->assertOk()->assertSee($own->company_name)->assertDontSee($other->company_name);
});

it('lets administrators view all leads', function () {
    $own = Lead::factory()->create(['company_name' => 'First Company']);
    $other = Lead::factory()->create(['company_name' => 'Second Company']);
    $administrator = User::factory()->administrator()->create();
    $this->actingAs($administrator)->get(route('leads.index'))->assertOk()->assertSee($own->company_name)->assertSee($other->company_name);
});

it('combines enhanced search filters while preserving agent ownership restrictions', function () {
    $agent = User::factory()->create();
    $otherAgent = User::factory()->create();
    $matching = Lead::factory()->for($agent, 'agent')->create(['company_name' => 'Target Manufacturing', 'website_domain' => 'target.test', 'country' => 'United States', 'status' => 'qualified_lead', 'validation_status' => 'verified', 'created_by' => $agent->id]);
    Lead::factory()->for($otherAgent, 'agent')->create(['company_name' => 'Target Manufacturing Secret', 'website_domain' => 'target.test', 'country' => 'United States', 'status' => 'qualified_lead', 'validation_status' => 'verified', 'created_by' => $otherAgent->id]);

    $response = $this->actingAs($agent)->get(route('leads.index', ['search' => 'target.test', 'status' => 'qualified_lead', 'validation_status' => 'verified', 'country' => 'United States']));

    $response->assertOk()->assertSee($matching->company_name)->assertDontSee('Target Manufacturing Secret');
});
