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
