<?php

use App\Jobs\SyncGmailReplies;
use App\Models\GmailConnection;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('redirects an authenticated agent to Google with readonly Gmail access', function () {
    config()->set('services.google', [
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
        'redirect_uri' => 'http://localhost/integrations/gmail/callback',
    ]);
    $agent = User::factory()->create();

    $response = $this->actingAs($agent)->post(route('gmail.connect'));

    $response->assertRedirectContains('accounts.google.com/o/oauth2/v2/auth');
    $response->assertSessionHas('gmail_oauth_state');
});

it('stores encrypted OAuth credentials and queues the initial synchronization', function () {
    config()->set('services.google', [
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
        'redirect_uri' => 'http://localhost/integrations/gmail/callback',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'new-access-token',
            'refresh_token' => 'new-refresh-token',
            'expires_in' => 3600,
        ]),
        'https://gmail.googleapis.com/gmail/v1/users/me/profile' => Http::response([
            'emailAddress' => 'agent@gmail.com',
            'historyId' => '1234',
        ]),
    ]);
    Queue::fake([SyncGmailReplies::class]);
    $agent = User::factory()->create();

    $response = $this->actingAs($agent)->withSession(['gmail_oauth_state' => 'valid-state'])->get(route('gmail.callback', [
        'state' => 'valid-state',
        'code' => 'authorization-code',
    ]));

    $response->assertRedirectToRoute('email-replies.index');
    $connection = GmailConnection::query()->whereBelongsTo($agent)->firstOrFail();
    expect($connection->gmail_address)->toBe('agent@gmail.com')
        ->and($connection->access_token)->toBe('new-access-token')
        ->and($connection->refresh_token)->toBe('new-refresh-token')
        ->and($connection->toArray())->not->toHaveKeys(['access_token', 'refresh_token']);
    Queue::assertPushed(SyncGmailReplies::class, fn (SyncGmailReplies $job): bool => $job->gmailConnectionId === $connection->id);
    $this->assertDatabaseHas('audit_logs', ['user_id' => $agent->id, 'action' => 'gmail.connected']);
});

it('rejects a callback whose OAuth state does not match the session', function () {
    $agent = User::factory()->create();

    $this->actingAs($agent)->withSession(['gmail_oauth_state' => 'expected'])->get(route('gmail.callback', [
        'state' => 'forged',
        'code' => 'authorization-code',
    ]))->assertForbidden();

    $this->assertDatabaseCount('gmail_connections', 0);
});
