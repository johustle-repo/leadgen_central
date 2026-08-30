<?php

use App\Models\EmailSequence;
use App\Models\GmailConnection;
use App\Models\Lead;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('shows the reply io style sequence and enrolls an owned lead', function () {
    $agent = User::factory()->create();
    $lead = Lead::factory()->for($agent, 'agent')->create(['email' => 'buyer@example.com']);
    GmailConnection::factory()->for($agent)->create();

    $this->actingAs($agent)->get(route('email-sequences.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('email-sequences/index')
            ->has('sequence.steps', 3)
            ->where('sequence.steps.0.day', 1)
            ->where('sequence.steps.1.day', 3)
            ->where('sequence.steps.2.day', 7));

    $response = $this->actingAs($agent)->post(route('email-sequences.enroll'), ['lead_id' => $lead->id]);

    $response->assertRedirect()->assertSessionHas('toast.message', 'buyer@example.com was enrolled. The first email will send shortly.');
    $this->assertDatabaseHas('email_sequence_enrollments', ['lead_id' => $lead->id, 'agent_id' => $agent->id, 'status' => 'active', 'current_step' => 0]);
    $this->assertDatabaseHas('audit_logs', ['user_id' => $agent->id, 'action' => 'email_sequence.enrolled', 'auditable_id' => $lead->id]);
});

it('does not allow an agent to enroll another agents lead', function () {
    $agent = User::factory()->create();
    $otherLead = Lead::factory()->create();
    GmailConnection::factory()->for($agent)->create();

    $response = $this->actingAs($agent)->post(route('email-sequences.enroll'), ['lead_id' => $otherLead->id]);

    $response->assertSessionHasErrors('lead_id');
    $this->assertDatabaseCount('email_sequence_enrollments', 0);
});

it('saves customized sequence messages', function () {
    $agent = User::factory()->create();
    $steps = EmailSequence::defaultSteps();
    $steps[1]['subject'] = 'Our customized follow-up';

    $response = $this->actingAs($agent)->put(route('email-sequences.update'), [
        'name' => 'DUSCAFF Sales Sequence',
        'is_active' => true,
        'steps' => $steps,
    ]);

    $response->assertRedirect()->assertSessionHas('toast.message', 'Email sequence saved successfully.');
    $this->assertDatabaseHas('email_sequences', ['user_id' => $agent->id, 'name' => 'DUSCAFF Sales Sequence']);
    expect(EmailSequence::query()->firstOrFail()->steps[1]['subject'])->toBe('Our customized follow-up');
});
