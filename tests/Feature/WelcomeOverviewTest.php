<?php

use App\Models\Lead;
use App\Models\UploadBatch;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests get no real lead overview on the welcome page', function () {
    Lead::factory()->count(2)->create();

    $this->get(route('home'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('welcome')
        ->where('overview', null));
});

test('administrators see live totals for every lead on the welcome page', function () {
    $this->travelTo(now()->setDate(2026, 9, 29)->setTime(12, 0));
    $administrator = User::factory()->administrator()->create();
    Lead::factory()->count(2)->create(['validation_status' => 'validated']);
    Lead::factory()->create(['validation_status' => 'verified', 'verified_at' => now()]);
    Lead::factory()->create(['created_at' => now()->subMonths(3)]);
    UploadBatch::factory()->create(['processing_status' => 'completed', 'completed_at' => now()->subMinute()]);

    $this->actingAs($administrator)->get(route('home'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('welcome')
        ->where('overview.stats.total', 4)
        ->where('overview.stats.total_change', 300)
        ->where('overview.stats.validated', 3)
        ->where('overview.stats.validated_rate', 75)
        ->where('overview.stats.this_month', 3)
        ->where('overview.stats.this_month_change', null)
        ->has('overview.growth', 8)
        ->where('overview.growth.7.leads', 3)
        ->has('overview.activity', 3));
});

test('agents only see their own leads on the welcome page', function () {
    $agent = User::factory()->create();
    Lead::factory()->count(2)->for($agent, 'agent')->create();
    Lead::factory()->count(5)->create();

    $this->actingAs($agent)->get(route('home'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('overview.stats.total', 2)
        ->where('overview.growth.7.leads', 2));
});

test('inactive users get no lead overview on the welcome page', function () {
    $user = User::factory()->administrator()->inactive()->create();
    Lead::factory()->create();

    $this->actingAs($user)->get(route('home'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('overview', null));
});
