<?php

use App\Models\EmailReply;
use App\Models\EmailSequence;
use App\Models\EmailSequenceEnrollment;
use App\Models\GmailConnection;
use App\Models\Lead;
use App\Models\User;
use App\Services\EmailSequenceProcessor;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

it('sends the initial personalized email and schedules day three', function () {
    $this->travelTo('2026-08-27 09:00:00');
    Storage::fake('local');
    Storage::disk('local')->put('brochures/duscaff.pdf', 'brochure');
    config([
        'services.google.brochure_path' => Storage::disk('local')->path('brochures/duscaff.pdf'),
        'services.google.brochure_name' => 'DUSCAFF brochure.pdf',
    ]);
    $agent = User::factory()->create();
    $lead = Lead::factory()->for($agent, 'agent')->create(['contact_person' => 'Ericka Bradbury', 'company_name' => 'ABC Scaffolding', 'email' => 'ericka@example.com']);
    $sequence = EmailSequence::factory()->for($agent)->create();
    $enrollment = EmailSequenceEnrollment::factory()->for($sequence, 'sequence')->for($lead)->create(['agent_id' => $agent->id]);
    GmailConnection::factory()->for($agent)->create(['gmail_address' => 'agent@example.com']);
    Http::preventStrayRequests();
    Http::fake(['https://gmail.googleapis.com/gmail/v1/users/me/messages/send' => Http::response(['id' => 'sent-1', 'threadId' => 'thread-1'])]);

    app(EmailSequenceProcessor::class)->processDue();

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send');
    $this->assertDatabaseHas('email_sequence_messages', ['email_sequence_enrollment_id' => $enrollment->id, 'step_number' => 1, 'gmail_message_id' => 'sent-1']);
    expect($enrollment->messages()->firstOrFail()->body)->toStartWith('Hi Ericka,');
    $this->assertDatabaseHas('email_sequence_enrollments', ['id' => $enrollment->id, 'status' => 'active', 'current_step' => 1, 'next_send_at' => '2026-08-29 09:00:00']);
});

it('stops without sending when the enrolled lead has replied', function () {
    $agent = User::factory()->create();
    $lead = Lead::factory()->for($agent, 'agent')->create();
    $sequence = EmailSequence::factory()->for($agent)->create();
    $enrollment = EmailSequenceEnrollment::factory()->for($sequence, 'sequence')->for($lead)->create(['agent_id' => $agent->id, 'started_at' => now()->subHour()]);
    $connection = GmailConnection::factory()->for($agent)->create();
    EmailReply::factory()->for($connection, 'gmailConnection')->for($agent, 'agent')->for($lead)->create(['received_at' => now()]);
    Http::preventStrayRequests();

    app(EmailSequenceProcessor::class)->processDue();

    Http::assertNothingSent();
    $this->assertDatabaseHas('email_sequence_enrollments', ['id' => $enrollment->id, 'status' => 'replied', 'stop_reason' => 'Lead replied']);
});

it('does not send while the sequence is disabled', function () {
    $agent = User::factory()->create();
    $lead = Lead::factory()->for($agent, 'agent')->create();
    $sequence = EmailSequence::factory()->for($agent)->create(['is_active' => false]);
    $enrollment = EmailSequenceEnrollment::factory()->for($sequence, 'sequence')->for($lead)->create(['agent_id' => $agent->id]);
    GmailConnection::factory()->for($agent)->create();
    Http::preventStrayRequests();

    $processed = app(EmailSequenceProcessor::class)->processDue();

    expect($processed)->toBe(0);
    Http::assertNothingSent();
    $this->assertDatabaseHas('email_sequence_enrollments', ['id' => $enrollment->id, 'status' => 'active', 'current_step' => 0]);
});
