<?php

use App\Models\EmailReply;
use App\Models\GmailConnection;
use App\Models\Lead;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('shows agents only the replies matched to their own leads', function () {
    $agent = User::factory()->create();
    $otherAgent = User::factory()->create();
    $lead = Lead::factory()->for($agent, 'agent')->create();
    $otherLead = Lead::factory()->for($otherAgent, 'agent')->create();
    $connection = GmailConnection::factory()->for($agent)->create();
    $otherConnection = GmailConnection::factory()->for($otherAgent)->create();
    $ownReply = EmailReply::factory()->for($connection, 'gmailConnection')->for($agent, 'agent')->for($lead)->create();
    EmailReply::factory()->for($otherConnection, 'gmailConnection')->for($otherAgent, 'agent')->for($otherLead)->create();

    $response = $this->actingAs($agent)->get(route('email-replies.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('email-replies/index')
        ->has('replies.data', 1)
        ->where('replies.data.0.id', $ownReply->id)
        ->where('connection.gmail_address', $connection->gmail_address));
});

it('allows an agent to confirm the classification of their own reply', function () {
    $agent = User::factory()->create();
    $lead = Lead::factory()->for($agent, 'agent')->create();
    $connection = GmailConnection::factory()->for($agent)->create();
    $reply = EmailReply::factory()->for($connection, 'gmailConnection')->for($agent, 'agent')->for($lead)->create();

    $response = $this->actingAs($agent)->put(route('email-replies.update', $reply), [
        'classification' => 'possible_lead',
        'is_read' => true,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('email_replies', ['id' => $reply->id, 'classification' => 'possible_lead', 'is_read' => true]);
    $this->assertDatabaseHas('audit_logs', ['user_id' => $agent->id, 'action' => 'email_reply.updated', 'auditable_id' => $reply->id]);
});

it('shows only the actual reply without the quoted outreach message', function () {
    $agent = User::factory()->create();
    $lead = Lead::factory()->for($agent, 'agent')->create();
    $connection = GmailConnection::factory()->for($agent)->create();
    $reply = EmailReply::factory()->for($connection, 'gmailConnection')->for($agent, 'agent')->for($lead)->create([
        'body_text' => "Can you send your product prices?\n\nOn Thu, Aug 27, 2026 at 2:05 PM <agent@gmail.com> wrote:\n> Original outreach",
    ]);

    $response = $this->actingAs($agent)->get(route('email-replies.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('replies.data.0.id', $reply->id)
        ->where('replies.data.0.actual_reply', 'Can you send your product prices?'));
});

it('renders a retained reply after its lead is deleted', function () {
    $administrator = User::factory()->administrator()->create();
    $agent = User::factory()->create();
    $lead = Lead::factory()->for($agent, 'agent')->create();
    $connection = GmailConnection::factory()->for($agent)->create();
    $reply = EmailReply::factory()->for($connection, 'gmailConnection')->for($agent, 'agent')->for($lead)->create();
    $lead->delete();

    $this->actingAs($administrator)->get(route('email-replies.index'))->assertInertia(fn (Assert $page) => $page
        ->component('email-replies/index')
        ->where('replies.data.0.id', $reply->id)
        ->where('replies.data.0.lead', null));
});

it('forbids an agent from changing another agents reply', function () {
    $agent = User::factory()->create();
    $otherAgent = User::factory()->create();
    $lead = Lead::factory()->for($otherAgent, 'agent')->create();
    $connection = GmailConnection::factory()->for($otherAgent)->create();
    $reply = EmailReply::factory()->for($connection, 'gmailConnection')->for($otherAgent, 'agent')->for($lead)->create();

    $this->actingAs($agent)->put(route('email-replies.update', $reply), [
        'classification' => 'not_lead',
    ])->assertForbidden();

    expect($reply->fresh()->classification->value)->toBe('needs_review');
});
