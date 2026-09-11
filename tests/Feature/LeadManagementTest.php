<?php

use App\Models\AuditLog;
use App\Models\Lead;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('loads the displayed company count with the create and edit forms', function () {
    $agent = User::factory()->create();
    $leads = Lead::factory()->count(2)->for($agent, 'agent')->create([
        'company_name' => 'Acme Ventures', 'normalized_company_name' => 'acme ventures',
    ]);
    Lead::factory()->create(['normalized_company_name' => 'acme ventures']);

    $this->actingAs($agent)->get(route('leads.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('companyContactCount.company', 'Acme Ventures')
            ->where('companyContactCount.count', 2));
    $this->get(route('leads.edit', $leads->first()))
        ->assertInertia(fn (Assert $page) => $page->where('companyContactCount.count', 2));
});

it('refreshes the count from the company currently typed in the form without returning form defaults', function () {
    $agent = User::factory()->create();
    $otherAgent = User::factory()->create();
    Lead::factory()->for($agent, 'agent')->create(['normalized_company_name' => 'new company']);
    Lead::factory()->for($otherAgent, 'agent')->create(['normalized_company_name' => 'new company']);

    $this->actingAs($agent)->get(route('leads.create', ['company_name' => 'NEW Company', 'agent_id' => $otherAgent->id]), [
        'X-Inertia-Partial-Component' => 'leads/form',
        'X-Inertia-Partial-Data' => 'companyContactCount',
    ])->assertInertia(fn (Assert $page) => $page
        ->where('companyContactCount.company', 'NEW Company')
        ->where('companyContactCount.count', 1)
        ->missing('defaults'));
});

it('counts company contacts for the current agent using normalized names and excluding archived leads', function () {
    $agent = User::factory()->create();
    $otherAgent = User::factory()->create();
    Lead::factory()->count(2)->for($agent, 'agent')->create(['normalized_company_name' => 'acme ventures']);
    Lead::factory()->for($otherAgent, 'agent')->create(['normalized_company_name' => 'acme ventures']);
    Lead::factory()->for($agent, 'agent')->create(['normalized_company_name' => 'acme ventures', 'deleted_at' => now()]);
    Lead::factory()->for($agent, 'agent')->create(['normalized_company_name' => 'other company']);

    $this->actingAs($agent)->getJson(route('leads.company-contact-count', ['company_name' => ' ACME   Ventures! ', 'agent_id' => $otherAgent->id]))
        ->assertJson(['count' => 2]);
});

it('returns zero contacts for a new or empty company', function (string $company) {
    $this->actingAs(User::factory()->create())
        ->getJson(route('leads.company-contact-count', ['company_name' => $company]))
        ->assertJson(['count' => 0]);
})->with(['New Company', '']);

it('requires authentication to count company contacts', function () {
    $this->getJson(route('leads.company-contact-count'))->assertUnauthorized();
});

it('lets administrators count contacts for the selected lead owner', function () {
    $agent = User::factory()->create();
    Lead::factory()->for($agent, 'agent')->create(['normalized_company_name' => 'acme']);

    $this->actingAs(User::factory()->administrator()->create())
        ->getJson(route('leads.company-contact-count', ['company_name' => 'Acme', 'agent_id' => $agent->id]))
        ->assertJson(['count' => 1]);
});

it('includes contacts outside the current list filter in the company count', function () {
    $agent = User::factory()->create();
    Lead::factory()->for($agent, 'agent')->create(['normalized_company_name' => 'acme', 'lead_date' => '2026-09-07']);
    Lead::factory()->for($agent, 'agent')->create(['normalized_company_name' => 'acme', 'lead_date' => '2026-09-06']);
    Lead::factory()->create(['normalized_company_name' => 'acme']);

    $this->actingAs($agent)->get(route('leads.index', ['date' => '2026-09-07']))
        ->assertInertia(fn (Assert $page) => $page->has('leads.data', 1)->where('leads.data.0.company_contact_count', 2));
});

it('creates a manual lead owned by the authenticated agent', function () {
    $agent = User::factory()->create();
    $response = $this->actingAs($agent)->post(route('leads.store'), ['lead_date' => '2026-08-25', 'company_name' => 'Acme Ventures', 'website' => 'acme.test', 'contact_person' => 'Ada', 'email' => 'hello@acme.test', 'country_code' => 'us', 'city' => 'Austin', 'import_trades' => 'Machinery', 'linkedin_url' => 'https://linkedin.com/company/acme', 'data_source' => 'Tendata/Lusha', 'source_url' => 'https://example.com/acme']);
    $response->assertRedirect(route('leads.create'))->assertSessionHas('toast', [
        'type' => 'success',
        'message' => 'Lead saved successfully.',
    ]);
    $this->assertDatabaseHas('leads', ['agent_id' => $agent->id, 'lead_date' => '2026-08-25 00:00:00', 'company_name' => 'Acme Ventures', 'contact_person' => 'Ada', 'country_code' => 'US', 'city' => 'Austin', 'import_trades' => 'Machinery', 'data_source' => 'Tendata/Lusha', 'source_url' => 'https://example.com/acme', 'source' => 'manual', 'created_by' => $agent->id]);
    expect(Lead::firstOrFail()->lead_code)->toStartWith('LD-');
    $this->getJson(route('leads.company-contact-count', ['company_name' => 'Acme Ventures']))->assertJson(['count' => 1]);
});

it('normalizes manually entered contact names to title case', function (string $contactName) {
    $agent = User::factory()->create();

    $response = $this->actingAs($agent)->post(route('leads.store'), [
        'company_name' => 'Acme Ventures',
        'contact_person' => $contactName,
        'email' => 'jonathan@acme.test',
    ]);

    $response->assertRedirect(route('leads.create'));
    $this->assertDatabaseHas('leads', [
        'agent_id' => $agent->id,
        'contact_person' => 'Jonathan Quiles',
    ]);
})->with([
    'lowercase name' => 'jonathan quiles',
    'uppercase name' => 'JONATHAN QUILES',
]);

it('prefills a new lead from the users latest entry while clearing contact details', function () {
    $this->travelTo('2026-08-26 10:00:00');
    $agent = User::factory()->create();
    $latestLead = Lead::factory()->for($agent, 'agent')->create([
        'lead_date' => '2026-08-24',
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
        ->where('defaults.lead_date', '2026-08-24')
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

it('prefills todays date when the user has no previous lead', function () {
    $this->travelTo('2026-08-26 10:00:00');
    $agent = User::factory()->create();

    $response = $this->actingAs($agent)->get(route('leads.create'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('leads/form')
        ->where('defaults.lead_date', '2026-08-26'));
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

it('no longer limits an agent to ten contacts from the same company while the cap is disabled', function () {
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

    $response->assertSessionDoesntHaveErrors('company_name');
    expect(Lead::query()->whereBelongsTo($agent, 'agent')->count())->toBe(11);
});

it('rejects a manually added lead whose email already exists for another agent and names the owner', function () {
    $existingOwner = User::factory()->create(['name' => 'Fiona Ley Maramba']);
    $existingLead = Lead::factory()->for($existingOwner, 'agent')->create([
        'email' => 'shared@acme.test',
        'lead_date' => '2026-08-01',
        'created_by' => $existingOwner->id,
    ]);
    $agent = User::factory()->create();

    $response = $this->actingAs($agent)->post(route('leads.store'), [
        'company_name' => 'Duplicate Contact Co',
        'contact_person' => 'Duplicate Contact',
        'email' => 'Shared@Acme.test',
    ]);

    $response->assertSessionHasErrors([
        'email' => "This email is already saved as lead {$existingLead->lead_code}, captured on Aug 1, 2026, owned by Fiona Ley Maramba.",
    ]);
    expect(Lead::query()->whereBelongsTo($agent, 'agent')->count())->toBe(0);
});

it('omits the owner name from the duplicate email message when the agent already owns the lead', function () {
    $agent = User::factory()->create();
    $existingLead = Lead::factory()->for($agent, 'agent')->create([
        'email' => 'mine@acme.test',
        'lead_date' => '2026-08-01',
        'created_by' => $agent->id,
    ]);

    $response = $this->actingAs($agent)->post(route('leads.store'), [
        'company_name' => 'Duplicate Contact Co',
        'contact_person' => 'Duplicate Contact',
        'email' => 'mine@acme.test',
    ]);

    $response->assertSessionHasErrors([
        'email' => "This email is already saved as lead {$existingLead->lead_code}, captured on Aug 1, 2026.",
    ]);
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

it('filters leads by one lead date', function () {
    $agent = User::factory()->create();
    $matching = Lead::factory()->for($agent, 'agent')->create([
        'lead_date' => '2026-08-25',
        'company_name' => 'Matching Date Company',
        'created_by' => $agent->id,
    ]);
    Lead::factory()->for($agent, 'agent')->create([
        'lead_date' => '2026-08-24',
        'company_name' => 'Other Date Company',
        'created_by' => $agent->id,
    ]);

    $response = $this->actingAs($agent)->get(route('leads.index', ['date' => '2026-08-25']));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('leads/index')
        ->where('filters.date', '2026-08-25')
        ->has('leads.data', 1)
        ->where('leads.data.0.id', $matching->id));
});

it('prefills todays date in the leads filter', function () {
    $this->travelTo('2026-09-01 10:00:00');
    $agent = User::factory()->create();

    $response = $this->actingAs($agent)->get(route('leads.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('leads/index')
        ->where('filters.date', '2026-09-01'));
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

it('records a change history entry when a lead is updated and surfaces it on the edit form', function () {
    $agent = User::factory()->create();
    $lead = Lead::factory()->for($agent, 'agent')->create(['company_name' => 'Original Company', 'website' => 'original.test', 'created_by' => $agent->id]);

    $this->actingAs($agent)->put(route('leads.update', $lead), ['company_name' => 'Updated Company', 'website' => 'original.test']);

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $agent->id,
        'action' => 'leads.updated',
        'auditable_type' => 'lead',
        'auditable_id' => $lead->id,
    ]);

    $this->actingAs($agent)->get(route('leads.edit', $lead))
        ->assertInertia(fn (Assert $page) => $page
            ->has('changeHistory', 1)
            ->where('changeHistory.0.description', 'Updated lead fields.')
            ->where('changeHistory.0.metadata.changes.company_name.old', 'Original Company')
            ->where('changeHistory.0.metadata.changes.company_name.new', 'Updated Company')
            ->missing('changeHistory.0.metadata.changes.website'));
});

it('does not record a change history entry when nothing actually changed', function () {
    $agent = User::factory()->create();
    $lead = Lead::factory()->for($agent, 'agent')->create(['company_name' => 'Same Company', 'created_by' => $agent->id]);

    $this->actingAs($agent)->put(route('leads.update', $lead), ['company_name' => 'Same Company']);

    $this->assertDatabaseMissing('audit_logs', [
        'auditable_type' => 'lead',
        'auditable_id' => $lead->id,
    ]);
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

it('renders leads without an assigned owner', function () {
    $administrator = User::factory()->administrator()->create();
    $formerAgent = User::factory()->create();
    $lead = Lead::factory()->for($formerAgent, 'agent')->create();
    $formerAgent->delete();

    $this->actingAs($administrator)->get(route('leads.index'))->assertInertia(fn (Assert $page) => $page
        ->component('leads/index')
        ->where('leads.data.0.id', $lead->id)
        ->where('leads.data.0.agent', null));
});

it('exposes the agent roster to administrators and sub-administrators only', function () {
    $administrator = User::factory()->administrator()->create();
    $subAdministrator = User::factory()->subAdministrator()->create();
    $agent = User::factory()->create();
    User::factory()->create(['name' => 'Zoe Agent']);

    $this->actingAs($administrator)->get(route('leads.index'))->assertInertia(fn (Assert $page) => $page
        ->component('leads/index')
        ->has('agents', 4));
    $this->actingAs($subAdministrator)->get(route('leads.index'))->assertInertia(fn (Assert $page) => $page
        ->component('leads/index')
        ->has('agents', 4));
    $this->actingAs($agent)->get(route('leads.index'))->assertInertia(fn (Assert $page) => $page
        ->component('leads/index')
        ->has('agents', 0));
});

it('lets an administrator filter leads by agent', function () {
    $administrator = User::factory()->administrator()->create();
    $firstAgent = User::factory()->create();
    $secondAgent = User::factory()->create();
    $wanted = Lead::factory()->for($firstAgent, 'agent')->create(['company_name' => 'Wanted Company']);
    Lead::factory()->for($secondAgent, 'agent')->create(['company_name' => 'Other Agent Company']);

    $this->actingAs($administrator)->get(route('leads.index', ['agent_id' => $firstAgent->id]))
        ->assertOk()
        ->assertSee($wanted->company_name)
        ->assertDontSee('Other Agent Company');
});

it('lets an administrator sort leads by agent name', function () {
    $administrator = User::factory()->administrator()->create();
    $agentA = User::factory()->create(['name' => 'Aaron Agent']);
    $agentZ = User::factory()->create(['name' => 'Zack Agent']);
    Lead::factory()->for($agentZ, 'agent')->create();
    Lead::factory()->for($agentA, 'agent')->create();

    $this->actingAs($administrator)->get(route('leads.index', ['sort' => 'agent', 'direction' => 'asc']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('leads/index')
            ->where('leads.data.0.agent.name', 'Aaron Agent')
            ->where('leads.data.1.agent.name', 'Zack Agent'));
});

it('sorts across every agents leads before paginating, not just the leads already on the page', function (string $direction, string $expected) {
    $administrator = User::factory()->administrator()->create();
    $agentA = User::factory()->create();
    $agentB = User::factory()->create();
    $leads = [
        'oldest' => Lead::factory()->for($agentA, 'agent')->create(['created_at' => '2026-01-01 00:00:00']),
        'newest' => Lead::factory()->for($agentB, 'agent')->create(['created_at' => '2026-06-01 00:00:00']),
    ];
    Lead::factory()->count(8)->for($agentA, 'agent')->create(['created_at' => '2026-03-01 00:00:00']);

    $this->actingAs($administrator)->get(route('leads.index', ['sort' => 'created_at', 'direction' => $direction, 'per_page' => 10]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('leads.data', 10)
            ->where('leads.data.0.id', $leads[$expected]->id));
})->with([
    'ascending shows the oldest lead across every agent first' => ['asc', 'oldest'],
    'descending shows the newest lead across every agent first' => ['desc', 'newest'],
]);

it('exposes bulk lead deletion only to administrators', function () {
    $administrator = User::factory()->administrator()->create();
    $agent = User::factory()->create();

    $this->actingAs($administrator)->get(route('leads.index'))->assertInertia(fn (Assert $page) => $page
        ->component('leads/index')
        ->where('canBulkDelete', true));
    $this->actingAs($agent)->get(route('leads.index'))->assertInertia(fn (Assert $page) => $page
        ->component('leads/index')
        ->where('canBulkDelete', false));
});

it('allows an administrator to bulk delete selected leads and records an audit event', function () {
    $administrator = User::factory()->administrator()->create();
    $leads = Lead::factory()->count(2)->create();

    $response = $this->actingAs($administrator)
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.10', 'HTTP_USER_AGENT' => 'Audit Test Browser'])
        ->delete(route('leads.bulk-destroy'), ['lead_ids' => $leads->modelKeys()]);

    $response->assertRedirect(route('leads.index'))->assertSessionHas('toast', [
        'type' => 'success',
        'message' => '2 lead(s) deleted successfully.',
    ]);
    $leads->each(fn (Lead $lead) => $this->assertSoftDeleted($lead));
    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $administrator->id,
        'action' => 'lead.bulk_deleted',
        'auditable_type' => 'lead',
        'auditable_id' => $leads->first()->id,
        'ip_address' => '203.0.113.10',
    ]);
    expect(AuditLog::firstOrFail()->metadata)->toMatchArray([
        'lead_ids' => $leads->modelKeys(),
        'count' => 2,
    ]);
});

it('prevents non-administrators from deleting leads', function (string $role) {
    $user = $role === 'agent'
        ? User::factory()->create()
        : User::factory()->subAdministrator()->create();
    $lead = Lead::factory()->for($user, 'agent')->create();

    $this->actingAs($user)->delete(route('leads.bulk-destroy'), ['lead_ids' => [$lead->id]])->assertForbidden();
    $this->actingAs($user)->delete(route('leads.destroy', $lead))->assertForbidden();

    $this->assertNotSoftDeleted($lead);
    $this->assertDatabaseCount('audit_logs', 0);
})->with(['agent', 'sub-administrator']);

it('requires at least one valid lead for bulk deletion', function () {
    $administrator = User::factory()->administrator()->create();

    $this->actingAs($administrator)
        ->delete(route('leads.bulk-destroy'), ['lead_ids' => []])
        ->assertSessionHasErrors('lead_ids');

    $this->assertDatabaseCount('audit_logs', 0);
});

it('points the edit form to the next contact at the same company for the same agent, wrapping at the end', function () {
    $agent = User::factory()->create();
    $first = Lead::factory()->for($agent, 'agent')->create([
        'normalized_company_name' => 'acme ventures', 'created_at' => '2026-08-01 00:00:00',
    ]);
    $second = Lead::factory()->for($agent, 'agent')->create([
        'normalized_company_name' => 'acme ventures', 'created_at' => '2026-08-02 00:00:00',
    ]);
    Lead::factory()->for($agent, 'agent')->create(['normalized_company_name' => 'other company']);
    Lead::factory()->create(['normalized_company_name' => 'acme ventures', 'created_at' => '2026-07-01 00:00:00']);

    $this->actingAs($agent)->get(route('leads.edit', $first))
        ->assertInertia(fn (Assert $page) => $page->where('nextLeadId', $second->id));

    $this->actingAs($agent)->get(route('leads.edit', $second))
        ->assertInertia(fn (Assert $page) => $page->where('nextLeadId', $first->id));
});

it('hides the next lead button when the contact has no siblings at the same company', function () {
    $agent = User::factory()->create();
    $lonelyLead = Lead::factory()->for($agent, 'agent')->create(['normalized_company_name' => 'solo company']);

    $this->actingAs($agent)->get(route('leads.edit', $lonelyLead))
        ->assertInertia(fn (Assert $page) => $page->where('nextLeadId', null));
});

it('propagates import trades, data source, and link to every other contact at the same company', function () {
    $agent = User::factory()->create();
    $lead = Lead::factory()->for($agent, 'agent')->create([
        'company_name' => 'Acme Ventures', 'normalized_company_name' => 'acme ventures',
        'import_trades' => 'Old Trades', 'data_source' => 'Manual', 'source_url' => 'https://old.example.com',
    ]);
    $sibling = Lead::factory()->for($agent, 'agent')->create([
        'normalized_company_name' => 'acme ventures', 'email' => 'sibling@acme.test',
        'import_trades' => 'Old Trades', 'data_source' => 'Manual', 'source_url' => 'https://old.example.com',
    ]);
    $otherCompany = Lead::factory()->for($agent, 'agent')->create(['normalized_company_name' => 'other company']);
    $otherAgentSameCompany = Lead::factory()->create(['normalized_company_name' => 'acme ventures']);

    $this->actingAs($agent)->put(route('leads.update', $lead), [
        'company_name' => 'Acme Ventures',
        'import_trades' => 'New Trades',
        'data_source' => 'Lusha',
        'source_url' => 'https://new.example.com',
    ]);

    $this->assertDatabaseHas('leads', [
        'id' => $sibling->id,
        'import_trades' => 'New Trades',
        'data_source' => 'Lusha',
        'source_url' => 'https://new.example.com',
        'email' => 'sibling@acme.test',
    ]);
    $this->assertDatabaseHas('leads', ['id' => $otherCompany->id, 'import_trades' => null, 'data_source' => null, 'source_url' => null]);
    $this->assertDatabaseHas('leads', ['id' => $otherAgentSameCompany->id, 'import_trades' => null, 'data_source' => null, 'source_url' => null]);
    $this->assertDatabaseHas('audit_logs', [
        'auditable_type' => 'lead',
        'auditable_id' => $sibling->id,
        'description' => 'Synced from another contact at Acme Ventures.',
    ]);
});

it('does not propagate fields other than import trades, data source, and link', function () {
    $agent = User::factory()->create();
    $lead = Lead::factory()->for($agent, 'agent')->create([
        'company_name' => 'Acme Ventures', 'normalized_company_name' => 'acme ventures', 'website' => 'https://old.example.com',
    ]);
    $sibling = Lead::factory()->for($agent, 'agent')->create([
        'normalized_company_name' => 'acme ventures', 'website' => 'https://sibling.example.com',
    ]);

    $this->actingAs($agent)->put(route('leads.update', $lead), [
        'company_name' => 'Acme Ventures',
        'website' => 'https://new.example.com',
    ]);

    $this->assertDatabaseHas('leads', ['id' => $sibling->id, 'website' => 'https://sibling.example.com']);
});

it('combines enhanced search filters and reaches every agents leads while searching', function () {
    $agent = User::factory()->create();
    $otherAgent = User::factory()->create();
    $matching = Lead::factory()->for($agent, 'agent')->create(['company_name' => 'Target Manufacturing', 'website_domain' => 'target.test', 'country' => 'United States', 'status' => 'qualified_lead', 'validation_status' => 'verified', 'created_by' => $agent->id]);
    $colleagueMatch = Lead::factory()->for($otherAgent, 'agent')->create(['company_name' => 'Target Manufacturing Secret', 'website_domain' => 'target.test', 'country' => 'United States', 'status' => 'qualified_lead', 'validation_status' => 'verified', 'created_by' => $otherAgent->id]);

    $response = $this->actingAs($agent)->get(route('leads.index', ['search' => 'target.test', 'status' => 'qualified_lead', 'validation_status' => 'verified', 'country' => 'United States']));

    // Searching reaches every agent's leads, but only the agent's own lead
    // is editable.
    $response->assertOk()->assertSee($matching->company_name)->assertSee('Target Manufacturing Secret');
    $leadsById = collect($response->viewData('page')['props']['leads']['data'])->keyBy('id');
    expect($leadsById[$matching->id]['can_update'])->toBeTrue();
    expect($leadsById[$colleagueMatch->id]['can_update'])->toBeFalse();

    // Browsing without a search term stays scoped to the agent's own leads.
    $this->actingAs($agent)->get(route('leads.index'))
        ->assertOk()->assertSee($matching->company_name)->assertDontSee('Target Manufacturing Secret');
});
