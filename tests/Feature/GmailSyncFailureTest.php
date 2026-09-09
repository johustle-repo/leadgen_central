<?php

use App\Exceptions\GmailReauthorizationRequiredException;
use App\Jobs\SyncGmailReplies;
use App\Models\GmailConnection;
use App\Services\GmailOAuthService;
use App\Services\GmailReplySynchronizer;
use Illuminate\Support\Facades\Http;

it('turns a revoked refresh token into a clear reauthorization error instead of the raw Google response', function () {
    Http::preventStrayRequests();
    Http::fake(['https://oauth2.googleapis.com/token' => Http::response([
        'error' => 'invalid_grant',
        'error_description' => 'Token has been expired or revoked.',
    ], 400)]);
    $connection = GmailConnection::factory()->create(['token_expires_at' => now()->subHour()]);

    expect(fn () => app(GmailOAuthService::class)->accessToken($connection))
        ->toThrow(GmailReauthorizationRequiredException::class, 'Gmail access was revoked or expired. Reconnect this Gmail account to resume syncing.');
});

it('marks a connection expired with a reconnect message when the refresh token was revoked', function () {
    $connection = GmailConnection::factory()->create(['status' => 'active']);

    (new SyncGmailReplies($connection->id))->failed(new GmailReauthorizationRequiredException);

    expect($connection->refresh())
        ->status->toBe('expired')
        ->last_error->toBe('Gmail access was revoked or expired. Reconnect this Gmail account to resume syncing.');
});

it('reports other sync failures with a friendly message instead of the raw exception', function () {
    $connection = GmailConnection::factory()->create(['status' => 'active']);

    (new SyncGmailReplies($connection->id))->failed(new RuntimeException('HTTP request returned status code 500: {"error":"internal"}'));

    expect($connection->refresh())
        ->status->toBe('error')
        ->last_error->toBe('Gmail synchronization failed. Try again, or reconnect this account if the problem continues.');
});

it('retries a previously failed connection instead of silently skipping it', function () {
    Http::preventStrayRequests();
    Http::fake(['https://gmail.googleapis.com/gmail/v1/users/me/messages*' => Http::response(['messages' => []])]);
    $connection = GmailConnection::factory()->create([
        'status' => 'error',
        'last_error' => 'Gmail synchronization failed. Try again, or reconnect this account if the problem continues.',
        'token_expires_at' => now()->addHour(),
    ]);

    (new SyncGmailReplies($connection->id))->handle(app(GmailReplySynchronizer::class));

    expect($connection->refresh())
        ->status->toBe('active')
        ->last_error->toBeNull();
});

it('does nothing when the connection no longer exists', function () {
    (new SyncGmailReplies(999999))->handle(app(GmailReplySynchronizer::class));

    expect(true)->toBeTrue();
});
