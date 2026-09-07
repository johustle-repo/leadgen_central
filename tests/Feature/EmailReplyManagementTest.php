<?php

use App\Models\EmailReply;
use App\Models\GmailConnection;
use App\Models\Lead;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('blocks agents, administrators, and sub-administrators from the email replies inbox', function (?string $factoryState) {
    $factory = User::factory();
    $user = ($factoryState === null ? $factory : $factory->{$factoryState}())->create();

    $this->actingAs($user)->get(route('email-replies.index'))->assertForbidden();
})->with([
    'agent (default role)' => null,
    'administrator',
    'subAdministrator',
]);

it('shows a super administrator every agents replies', function () {
    $superAdministrator = User::factory()->superAdministrator()->create();
    $agent = User::factory()->create();
    $otherAgent = User::factory()->create();
    $lead = Lead::factory()->for($agent, 'agent')->create();
    $otherLead = Lead::factory()->for($otherAgent, 'agent')->create();
    $connection = GmailConnection::factory()->for($agent)->create();
    $otherConnection = GmailConnection::factory()->for($otherAgent)->create();
    EmailReply::factory()->for($connection, 'gmailConnection')->for($agent, 'agent')->for($lead)->create();
    EmailReply::factory()->for($otherConnection, 'gmailConnection')->for($otherAgent, 'agent')->for($otherLead)->create();

    $response = $this->actingAs($superAdministrator)->get(route('email-replies.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('email-replies/index')
        ->has('replies.data', 2)
        ->has('agentGmailConnections', 2));
});

it('filters replies by classification date and search text', function () {
    $this->travelTo('2026-09-01 12:00:00');
    $superAdministrator = User::factory()->superAdministrator()->create();
    $agent = User::factory()->create();
    $lead = Lead::factory()->for($agent, 'agent')->create(['company_name' => 'Target Scaffolding']);
    $connection = GmailConnection::factory()->for($agent)->create();
    $matching = EmailReply::factory()->for($connection, 'gmailConnection')->for($agent, 'agent')->for($lead)->create([
        'classification' => 'interested',
        'subject' => 'Pricing request',
        'received_at' => now(),
    ]);
    EmailReply::factory()->for($connection, 'gmailConnection')->for($agent, 'agent')->for($lead)->create([
        'classification' => 'not_now',
        'subject' => 'Pricing request',
        'received_at' => now(),
    ]);
    EmailReply::factory()->for($connection, 'gmailConnection')->for($agent, 'agent')->for($lead)->create([
        'classification' => 'interested',
        'subject' => 'Pricing request',
        'received_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($superAdministrator)->get(route('email-replies.index', [
        'classification' => 'interested',
        'date' => '2026-09-01',
        'search' => 'Target',
    ]));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('email-replies/index')
        ->has('replies.data', 1)
        ->where('replies.data.0.id', $matching->id)
        ->where('filters.classification', 'interested')
        ->where('filters.date', '2026-09-01'));
});

it('shares the unread reply count for the sidebar badge only with a super administrator', function () {
    $this->travelTo('2026-09-01 12:00:00');
    $superAdministrator = User::factory()->superAdministrator()->create();
    $agent = User::factory()->create();
    $otherAgent = User::factory()->create();
    $lead = Lead::factory()->for($agent, 'agent')->create();
    $otherLead = Lead::factory()->for($otherAgent, 'agent')->create();
    $connection = GmailConnection::factory()->for($agent)->create();
    $otherConnection = GmailConnection::factory()->for($otherAgent)->create();
    EmailReply::factory()->for($connection, 'gmailConnection')->for($agent, 'agent')->for($lead)->create(['received_at' => now(), 'is_read' => true]);
    EmailReply::factory()->for($connection, 'gmailConnection')->for($agent, 'agent')->for($lead)->create(['received_at' => now()->subDay(), 'is_read' => false]);
    EmailReply::factory()->for($otherConnection, 'gmailConnection')->for($otherAgent, 'agent')->for($otherLead)->create(['received_at' => now(), 'is_read' => false]);

    $this->actingAs($superAdministrator)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('notificationCounts.unread_email_replies', 2));

    $this->actingAs($agent)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('notificationCounts.unread_email_replies', 0));
});

it('allows a super administrator to update any agents reply', function () {
    $superAdministrator = User::factory()->superAdministrator()->create();
    $agent = User::factory()->create();
    $lead = Lead::factory()->for($agent, 'agent')->create();
    $connection = GmailConnection::factory()->for($agent)->create();
    $reply = EmailReply::factory()->for($connection, 'gmailConnection')->for($agent, 'agent')->for($lead)->create();

    $response = $this->actingAs($superAdministrator)->put(route('email-replies.update', $reply), [
        'classification' => 'possible_lead',
        'is_read' => true,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('email_replies', ['id' => $reply->id, 'classification' => 'possible_lead', 'is_read' => true]);
    $this->assertDatabaseHas('audit_logs', ['user_id' => $superAdministrator->id, 'action' => 'email_reply.updated', 'auditable_id' => $reply->id]);
});

it('forbids an agent from updating a reply even their own', function () {
    $agent = User::factory()->create();
    $lead = Lead::factory()->for($agent, 'agent')->create();
    $connection = GmailConnection::factory()->for($agent)->create();
    $reply = EmailReply::factory()->for($connection, 'gmailConnection')->for($agent, 'agent')->for($lead)->create();

    $this->actingAs($agent)->put(route('email-replies.update', $reply), [
        'classification' => 'possible_lead',
        'is_read' => true,
    ])->assertForbidden();
});

it('allows a super administrator to mark every unread reply as read', function () {
    $superAdministrator = User::factory()->superAdministrator()->create();
    $firstAgent = User::factory()->create();
    $secondAgent = User::factory()->create();
    $firstLead = Lead::factory()->for($firstAgent, 'agent')->create();
    $secondLead = Lead::factory()->for($secondAgent, 'agent')->create();
    $firstConnection = GmailConnection::factory()->for($firstAgent)->create();
    $secondConnection = GmailConnection::factory()->for($secondAgent)->create();
    $firstReply = EmailReply::factory()->for($firstConnection, 'gmailConnection')->for($firstAgent, 'agent')->for($firstLead)->create(['is_read' => false]);
    $secondReply = EmailReply::factory()->for($secondConnection, 'gmailConnection')->for($secondAgent, 'agent')->for($secondLead)->create(['is_read' => false]);

    $response = $this->actingAs($superAdministrator)->put(route('email-replies.mark-all-read'));

    $response->assertRedirect()->assertSessionHas('toast.message', '2 replies marked as read.');
    expect($firstReply->fresh()->is_read)->toBeTrue()
        ->and($secondReply->fresh()->is_read)->toBeTrue();
});

it('forbids an administrator from marking all unread replies as read', function () {
    $administrator = User::factory()->administrator()->create();

    $this->actingAs($administrator)->put(route('email-replies.mark-all-read'))->assertForbidden();
});

it('shows only the actual reply without the quoted outreach message', function () {
    $superAdministrator = User::factory()->superAdministrator()->create();
    $agent = User::factory()->create();
    $lead = Lead::factory()->for($agent, 'agent')->create();
    $connection = GmailConnection::factory()->for($agent)->create();
    $reply = EmailReply::factory()->for($connection, 'gmailConnection')->for($agent, 'agent')->for($lead)->create([
        'body_text' => "Can you send your product prices?\n\nOn Thu, Aug 27, 2026 at 2:05 PM <agent@gmail.com> wrote:\n> Original outreach",
    ]);

    $response = $this->actingAs($superAdministrator)->get(route('email-replies.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('replies.data.0.id', $reply->id)
        ->where('replies.data.0.actual_reply', 'Can you send your product prices?'));
});

it('renders a retained reply after its lead is deleted', function () {
    $superAdministrator = User::factory()->superAdministrator()->create();
    $agent = User::factory()->create();
    $lead = Lead::factory()->for($agent, 'agent')->create();
    $connection = GmailConnection::factory()->for($agent)->create();
    $reply = EmailReply::factory()->for($connection, 'gmailConnection')->for($agent, 'agent')->for($lead)->create();
    $lead->delete();

    $this->actingAs($superAdministrator)->get(route('email-replies.index'))->assertInertia(fn (Assert $page) => $page
        ->component('email-replies/index')
        ->where('replies.data.0.id', $reply->id)
        ->where('replies.data.0.lead', null));
});
