<?php

use App\EmailReplyClassification;
use App\Models\EmailSequence;
use App\Models\EmailSequenceEnrollment;
use App\Models\GmailConnection;
use App\Models\Lead;
use App\Models\User;
use App\Services\EmailReplyClassifier;
use App\Services\GmailReplySynchronizer;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('saves and classifies only Gmail messages sent by an agents lead', function () {
    Http::preventStrayRequests();
    $agent = User::factory()->create();
    $lead = Lead::factory()->for($agent, 'agent')->create([
        'email' => 'prospect@example.com',
        'company_name' => 'Interested Company',
    ]);
    $connection = GmailConnection::factory()->for($agent)->create([
        'gmail_address' => 'agent@gmail.com',
        'token_expires_at' => now()->addHour(),
    ]);
    $sequence = EmailSequence::factory()->for($agent)->create();
    $enrollment = EmailSequenceEnrollment::factory()->for($sequence, 'sequence')->for($lead)->create([
        'agent_id' => $agent->id,
        'started_at' => now()->subDays(7),
    ]);
    $body = rtrim(strtr(base64_encode('Yes, I am interested. Please send pricing and schedule a call.'), '+/', '-_'), '=');
    Http::fake(fn (Request $request) => str_contains($request->url(), '/messages/msg-1')
        ? Http::response([
            'id' => 'msg-1',
            'threadId' => 'thread-1',
            'historyId' => '2000',
            'internalDate' => '1787792400000',
            'snippet' => 'Yes, I am interested.',
            'payload' => [
                'mimeType' => 'text/plain',
                'headers' => [
                    ['name' => 'From', 'value' => 'Prospect Person <prospect@example.com>'],
                    ['name' => 'Subject', 'value' => 'Re: Introduction'],
                ],
                'body' => ['data' => $body],
            ],
        ])
        : Http::response(['messages' => [['id' => 'msg-1']]]));

    $created = app(GmailReplySynchronizer::class)->sync($connection);

    expect($created)->toBe(1);
    $this->assertDatabaseHas('email_replies', [
        'agent_id' => $agent->id,
        'lead_id' => $lead->id,
        'gmail_message_id' => 'msg-1',
        'sender_email' => 'prospect@example.com',
        'classification' => 'interested',
        'is_read' => false,
    ]);
    $this->assertDatabaseHas('email_sequence_enrollments', [
        'id' => $enrollment->id,
        'status' => 'replied',
        'stop_reason' => 'Lead replied',
    ]);
    Http::assertSentCount(2);
});

it('does not save unrelated personal inbox messages', function () {
    Http::preventStrayRequests();
    $agent = User::factory()->create();
    GmailConnection::factory()->for($agent)->create([
        'gmail_address' => 'agent@gmail.com',
        'token_expires_at' => now()->addHour(),
    ]);
    $connection = GmailConnection::query()->whereBelongsTo($agent)->firstOrFail();
    Http::fake(fn (Request $request) => str_contains($request->url(), '/messages/unmatched')
        ? Http::response([
            'id' => 'unmatched',
            'threadId' => 'thread-2',
            'internalDate' => '1787792400000',
            'payload' => [
                'mimeType' => 'text/plain',
                'headers' => [['name' => 'From', 'value' => 'Friend <friend@example.com>']],
                'body' => ['data' => 'SGVsbG8'],
            ],
        ])
        : Http::response(['messages' => [['id' => 'unmatched']]]));

    $created = app(GmailReplySynchronizer::class)->sync($connection);

    expect($created)->toBe(0);
    $this->assertDatabaseCount('email_replies', 0);
});

it('classifies reply intent without a paid AI service', function (string $subject, string $body, EmailReplyClassification $expected) {
    $result = app(EmailReplyClassifier::class)->classify($subject, $body);

    expect($result['classification'])->toBe($expected);
})->with([
    'bounce' => ['Delivery Status Notification', 'Recipient address rejected. Delivery failed.', EmailReplyClassification::Bounce],
    'interested' => ['Re: Scaffolding', 'I am interested. Please send your pricing.', EmailReplyClassification::Interested],
    'not interested' => ['Re: Scaffolding', 'No thanks, we are not interested.', EmailReplyClassification::NotInterested],
    'not now' => ['Re: Scaffolding', 'Not right now. Please check back later.', EmailReplyClassification::NotNow],
    'do not contact' => ['Re: Scaffolding', 'Please remove me and do not contact me again.', EmailReplyClassification::DoNotContact],
]);

it('stores the complete message but previews and classifies only the actual reply', function () {
    Http::preventStrayRequests();
    $agent = User::factory()->create();
    $lead = Lead::factory()->for($agent, 'agent')->create(['email' => 'prospect@example.com']);
    $connection = GmailConnection::factory()->for($agent)->create([
        'gmail_address' => 'agent@gmail.com',
        'token_expires_at' => now()->addHour(),
    ]);
    $messageBody = "Thanks for your email!\nPlease send your pricing.\n\nOn Thu, Aug 27, 2026 at 2:05 PM <agent@gmail.com> wrote:\n> Original sales email";
    $encodedBody = rtrim(strtr(base64_encode($messageBody), '+/', '-_'), '=');
    Http::fake(fn (Request $request) => str_contains($request->url(), '/messages/reply-1')
        ? Http::response([
            'id' => 'reply-1',
            'threadId' => 'thread-1',
            'internalDate' => '1787792400000',
            'payload' => [
                'mimeType' => 'text/plain',
                'headers' => [
                    ['name' => 'From', 'value' => 'Prospect <prospect@example.com>'],
                    ['name' => 'Subject', 'value' => 'Re: Scaffolding'],
                ],
                'body' => ['data' => $encodedBody],
            ],
        ])
        : Http::response(['messages' => [['id' => 'reply-1']]]));

    app(GmailReplySynchronizer::class)->sync($connection);

    $this->assertDatabaseHas('email_replies', [
        'lead_id' => $lead->id,
        'body_preview' => "Thanks for your email!\nPlease send your pricing.",
        'body_text' => $messageBody,
        'classification' => 'interested',
    ]);
});
