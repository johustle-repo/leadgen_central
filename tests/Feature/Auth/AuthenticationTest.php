<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('agent is warned when the account already has an active session', function () {
    $user = User::factory()->create();
    createActiveSessionFor($user);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
    $response->assertInvalid([
        'active_session' => 'This account is already logged in on another browser.',
    ]);
    $this->assertDatabaseHas('sessions', ['id' => 'existing-agent-session', 'user_id' => $user->id]);
});

test('active session warning is not disclosed when the password is invalid', function () {
    $user = User::factory()->create();
    createActiveSessionFor($user);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
    $response->assertInvalid(['email' => trans('auth.failed')]);
    $response->assertValid('active_session');
});

test('agent can continue login and invalidate the other browser session', function () {
    $user = User::factory()->create();
    createActiveSessionFor($user);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
        'force_login' => true,
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertDatabaseMissing('sessions', ['id' => 'existing-agent-session']);
    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $user->id,
        'action' => 'agent.session_replaced',
        'auditable_id' => $user->id,
    ]);
});

test('administrator accounts may have more than one active session', function () {
    $user = User::factory()->administrator()->create();
    createActiveSessionFor($user);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertDatabaseHas('sessions', ['id' => 'existing-agent-session', 'user_id' => $user->id]);
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $response->assertSessionHas('login.id', $user->id);
    $this->assertGuest();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});

test('users are rate limited', function () {
    $user = User::factory()->create();

    RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertTooManyRequests();
});

function createActiveSessionFor(User $user): void
{
    DB::table('sessions')->insert([
        'id' => 'existing-agent-session',
        'user_id' => $user->id,
        'ip_address' => '192.0.2.1',
        'user_agent' => 'Existing browser',
        'payload' => 'test-session-payload',
        'last_activity' => now()->timestamp,
    ]);
}
