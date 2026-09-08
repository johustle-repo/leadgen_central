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

    $response->assertRedirectToRoute('profile.edit');
    $connection = GmailConnection::query()->whereBelongsTo($agent)->firstOrFail();
    expect($connection->gmail_address)->toBe('agent@gmail.com')
        ->and($connection->access_token)->toBe('new-access-token')
        ->and($connection->refresh_token)->toBe('new-refresh-token')
        ->and($connection->toArray())->not->toHaveKeys(['access_token', 'refresh_token']);
    Queue::assertPushed(SyncGmailReplies::class, fn (SyncGmailReplies $job): bool => $job->gmailConnectionId === $connection->id);
    $this->assertDatabaseHas('audit_logs', ['user_id' => $agent->id, 'action' => 'gmail.connected']);
});

it('shows a friendly error instead of crashing when Google token exchange fails', function () {
    config()->set('services.google', [
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
        'redirect_uri' => 'http://localhost/integrations/gmail/callback',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response([], 500),
    ]);
    $agent = User::factory()->create();

    $response = $this->actingAs($agent)->withSession(['gmail_oauth_state' => 'valid-state'])->get(route('gmail.callback', [
        'state' => 'valid-state',
        'code' => 'authorization-code',
    ]));

    $response->assertRedirectToRoute('profile.edit')->assertSessionHas('toast', [
        'type' => 'error',
        'message' => 'Gmail connection failed. Please try again or contact an administrator.',
    ]);
    $this->assertDatabaseCount('gmail_connections', 0);
});

it('sends a super administrator back to the email replies inbox after connecting', function () {
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
            'emailAddress' => 'super@gmail.com',
            'historyId' => '1234',
        ]),
    ]);
    Queue::fake([SyncGmailReplies::class]);
    $superAdministrator = User::factory()->superAdministrator()->create();

    $response = $this->actingAs($superAdministrator)->withSession(['gmail_oauth_state' => 'valid-state'])->get(route('gmail.callback', [
        'state' => 'valid-state',
        'code' => 'authorization-code',
    ]));

    $response->assertRedirectToRoute('email-replies.index');
});

it('rejects a callback whose OAuth state does not match the session', function () {
    $agent = User::factory()->create();

    $this->actingAs($agent)->withSession(['gmail_oauth_state' => 'expected'])->get(route('gmail.callback', [
        'state' => 'forged',
        'code' => 'authorization-code',
    ]))->assertForbidden();

    $this->assertDatabaseCount('gmail_connections', 0);
});

it('lets a super administrator queue a sync for an agents connected mailbox', function () {
    Queue::fake([SyncGmailReplies::class]);
    $superAdministrator = User::factory()->superAdministrator()->create();
    $agent = User::factory()->create();
    $connection = GmailConnection::factory()->for($agent)->create();

    $response = $this->actingAs($superAdministrator)->post(route('gmail.sync-for', $agent));

    $response->assertRedirect();
    Queue::assertPushed(SyncGmailReplies::class, fn (SyncGmailReplies $job): bool => $job->gmailConnectionId === $connection->id);
    $this->assertDatabaseHas('audit_logs', ['user_id' => $superAdministrator->id, 'action' => 'gmail.synced_by_admin']);
});

it('forbids a non-super-administrator from syncing another users mailbox', function () {
    $administrator = User::factory()->administrator()->create();
    $agent = User::factory()->create();
    GmailConnection::factory()->for($agent)->create();

    $this->actingAs($administrator)->post(route('gmail.sync-for', $agent))->assertForbidden();
});

it('lets a super administrator start the OAuth flow on behalf of an agent', function () {
    config()->set('services.google', [
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
        'redirect_uri' => 'http://localhost/integrations/gmail/callback',
    ]);
    $superAdministrator = User::factory()->superAdministrator()->create();
    $agent = User::factory()->create();

    $response = $this->actingAs($superAdministrator)->post(route('gmail.connect-for', $agent));

    $response->assertRedirectContains('accounts.google.com/o/oauth2/v2/auth');
    $response->assertSessionHas('gmail_oauth_state');
    $response->assertSessionHas('gmail_oauth_user_id', $agent->id);
});

it('forbids a non-super-administrator from connecting Gmail on behalf of another user', function () {
    $administrator = User::factory()->administrator()->create();
    $agent = User::factory()->create();

    $this->actingAs($administrator)->post(route('gmail.connect-for', $agent))->assertForbidden();
});

it('saves the connection under the target agent when a super administrator completes the OAuth flow for them', function () {
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
            'emailAddress' => 'dexter@gmail.com',
            'historyId' => '1234',
        ]),
    ]);
    Queue::fake([SyncGmailReplies::class]);
    $superAdministrator = User::factory()->superAdministrator()->create();
    $agent = User::factory()->create(['name' => 'Dexter']);

    $response = $this->actingAs($superAdministrator)
        ->withSession(['gmail_oauth_state' => 'valid-state', 'gmail_oauth_user_id' => $agent->id])
        ->get(route('gmail.callback', ['state' => 'valid-state', 'code' => 'authorization-code']));

    $response->assertRedirectToRoute('email-replies.index');
    $connection = GmailConnection::query()->whereBelongsTo($agent)->firstOrFail();
    expect($connection->gmail_address)->toBe('dexter@gmail.com');
    $this->assertDatabaseCount('gmail_connections', 1);
    $this->assertDatabaseHas('audit_logs', ['user_id' => $superAdministrator->id, 'action' => 'gmail.connected_by_admin']);
});
