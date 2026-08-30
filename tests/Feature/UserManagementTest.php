<?php

use App\Models\EmailReply;
use App\Models\Lead;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('allows administrators to create users', function () {
    $administrator = User::factory()->administrator()->create();
    $response = $this->actingAs($administrator)->post(route('users.store'), ['name' => 'Lead Agent', 'email' => 'agent@example.com', 'password' => 'password', 'password_confirmation' => 'password', 'role' => 'agent', 'status' => 'active', 'team' => 'North']);
    $response->assertRedirect(route('users.index'));
    $this->assertDatabaseHas('users', ['email' => 'agent@example.com', 'role' => 'agent', 'status' => 'active']);
});

it('shows each users total leads and replies', function () {
    $administrator = User::factory()->administrator()->create();
    $agent = User::factory()->create(['created_at' => now()->addMinute()]);
    $leads = Lead::factory(3)->for($agent, 'agent')->create();
    EmailReply::factory(2)->for($agent, 'agent')->for($leads->first())->create();

    $response = $this->actingAs($administrator)->get(route('users.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('users.data.0.id', $agent->id)
        ->where('users.data.0.leads_count', 3)
        ->where('users.data.0.email_replies_count', 2));
});

it('forbids agents from user management', function () {
    $agent = User::factory()->create();
    $this->actingAs($agent)->get(route('users.index'))->assertForbidden();
    $this->actingAs($agent)->post(route('users.store'), ['name' => 'Unauthorized'])->assertForbidden();
});

it('allows administrators to soft delete another user', function () {
    $administrator = User::factory()->administrator()->create();
    $agent = User::factory()->create();

    $response = $this->actingAs($administrator)->delete(route('users.destroy', $agent));

    $response->assertRedirect(route('users.index'))->assertSessionHas('toast.message', "{$agent->name} was deleted successfully.");
    $this->assertSoftDeleted($agent);
});

it('forbids administrators from deleting themselves', function () {
    $administrator = User::factory()->administrator()->create();

    $this->actingAs($administrator)->delete(route('users.destroy', $administrator))->assertForbidden();

    expect($administrator->fresh()->trashed())->toBeFalse();
});

it('forbids agents from deleting users', function () {
    $agent = User::factory()->create();
    $otherAgent = User::factory()->create();

    $this->actingAs($agent)->delete(route('users.destroy', $otherAgent))->assertForbidden();

    expect($otherAgent->fresh()->trashed())->toBeFalse();
});

it('allows administrators to switch each users email sequence', function () {
    $administrator = User::factory()->administrator()->create();
    $agent = User::factory()->create();

    $this->actingAs($administrator)->get(route('users.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('users.data.0.email_sequence_enabled', true));

    $response = $this->actingAs($administrator)->patch(route('users.email-sequence.toggle', $agent), [
        'is_active' => false,
    ]);

    $response->assertRedirect()->assertSessionHas('toast.message', "Email sequence paused for {$agent->name}.");
    $this->assertDatabaseHas('email_sequences', ['user_id' => $agent->id, 'is_active' => false]);
});

it('forbids agents from switching another users email sequence', function () {
    $agent = User::factory()->create();
    $otherAgent = User::factory()->create();

    $this->actingAs($agent)->patch(route('users.email-sequence.toggle', $otherAgent), [
        'is_active' => false,
    ])->assertForbidden();

    $this->assertDatabaseCount('email_sequences', 0);
});

it('does not authenticate inactive users', function () {
    $inactive = User::factory()->inactive()->create();
    $this->post(route('login.store'), ['email' => $inactive->email, 'password' => 'password'])->assertSessionHasErrors('email');
    $this->assertGuest();
});
