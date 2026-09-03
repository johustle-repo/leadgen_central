<?php

use App\Models\Lead;
use App\Models\UploadBatch;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->has('stats', 8)
        ->hasAll([
            'stats.total_leads',
            'stats.unique_leads',
            'stats.qualified_leads',
            'stats.qualification_rate',
            'stats.duplicates_flagged',
            'stats.data_issues',
            'stats.unread_replies',
            'stats.possible_reply_leads',
        ]));
});

it('counts unique leads as distinct companies rather than summing batch accepted-row counts', function () {
    // Regression test: "Unique leads" used to sum UploadBatch::new_leads, but that column
    // also counts rows that updated an *existing* lead (a re-uploaded manual lead, or
    // "update missing fields" duplicate handling) rather than inserting one, so the sum
    // could exceed the actual number of Lead rows - visibly, "Unique leads" showing higher
    // than "Total leads" on the dashboard.
    $agent = User::factory()->create();
    Lead::factory()->for($agent, 'agent')->create(['company_name' => 'Acme Inc', 'normalized_company_name' => 'acme inc']);
    Lead::factory()->for($agent, 'agent')->create(['company_name' => 'Acme Inc', 'normalized_company_name' => 'acme inc']);
    Lead::factory()->for($agent, 'agent')->create(['company_name' => 'Globex Corp', 'normalized_company_name' => 'globex corp']);
    // A batch whose accepted-row sum would overcount unique companies if it were still used.
    UploadBatch::factory()->for($agent)->create(['new_leads' => 50]);

    $response = $this->actingAs($agent)->get(route('dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('stats.total_leads', 3)
        ->where('stats.unique_leads', 2));
});

it('renders historical dashboard records after their owner is deleted', function () {
    $administrator = User::factory()->administrator()->create();
    $deletedAgent = User::factory()->create(['name' => 'Former Agent']);
    Lead::factory()->for($deletedAgent, 'agent')->create(['company_name' => 'Historical Lead']);
    UploadBatch::factory()->for($deletedAgent)->create(['original_filename' => 'historical.csv']);
    $deletedAgent->delete();

    $response = $this->actingAs($administrator)->get(route('dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('recentLeads.0.company_name', 'Historical Lead')
        ->where('recentLeads.0.agent.name', 'Former Agent')
        ->where('recentBatches.0.original_filename', 'historical.csv')
        ->where('recentBatches.0.user.name', 'Former Agent'));
});
