<?php

use App\Models\Lead;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('finds a lead by search regardless of stray pagination or other query params', function () {
    $agent = User::factory()->create();
    $lead = Lead::factory()->for($agent, 'agent')->create([
        'company_name' => 'Stoddart  Group', // double space, as CSV imports sometimes carry
        'normalized_company_name' => 'stoddart group',
        'created_by' => $agent->id,
        'lead_date' => now()->subDays(5),
    ]);

    $response = $this->actingAs($agent)->get(route('leads.index', [
        'search' => 'Stoddart Group',
        'per_page' => '100',
    ]));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('leads.data.0.id', $lead->id));
});

it('matches a normalized company name even with a trailing period and different casing', function () {
    $agent = User::factory()->create();
    $lead = Lead::factory()->for($agent, 'agent')->create([
        'company_name' => 'STODDART GROUP.',
        'normalized_company_name' => 'stoddart group',
        'created_by' => $agent->id,
    ]);

    $response = $this->actingAs($agent)->get(route('leads.index', ['search' => 'stoddart group']));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('leads.data.0.id', $lead->id));
});

it('lets an agent search across every agents leads but not browse or edit them by default', function () {
    $agent = User::factory()->create();
    $colleague = User::factory()->create();
    $colleagueLead = Lead::factory()->for($colleague, 'agent')->create([
        'company_name' => 'Colleague Owned Co',
        'normalized_company_name' => 'colleague owned co',
        'created_by' => $colleague->id,
    ]);

    // The default, unfiltered listing stays scoped to the agent's own leads.
    $this->actingAs($agent)->get(route('leads.index'))
        ->assertInertia(fn (Assert $page) => $page->where('leads.data', []));

    // Searching reaches the colleague's lead too, but it can't be edited.
    $response = $this->actingAs($agent)->get(route('leads.index', ['search' => 'Colleague Owned Co']));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('leads.data.0.id', $colleagueLead->id)
        ->where('leads.data.0.can_update', false));
});
