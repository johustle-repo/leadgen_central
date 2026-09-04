<?php

use App\Models\AuditLog;
use App\Models\User;

it('redirects guests from impersonation routes to login', function () {
    $agent = User::factory()->create();

    $this->post(route('users.impersonate', $agent))->assertRedirect(route('login'));
    $this->delete(route('impersonate.stop'))->assertRedirect(route('login'));
});

it('lets a super administrator log in as another user', function () {
    $superAdministrator = User::factory()->superAdministrator()->create();
    $agent = User::factory()->create(['name' => 'Target Agent']);

    $response = $this->actingAs($superAdministrator)->post(route('users.impersonate', $agent));

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($agent);
    $this->assertDatabaseHas(AuditLog::class, [
        'user_id' => $superAdministrator->id,
        'action' => 'user.impersonation_started',
        'auditable_id' => $agent->id,
    ]);
});

it('forbids a regular administrator from impersonating anyone', function () {
    $administrator = User::factory()->administrator()->create();
    $agent = User::factory()->create();

    $this->actingAs($administrator)->post(route('users.impersonate', $agent))->assertForbidden();
    $this->assertAuthenticatedAs($administrator);
});

it('forbids a super administrator from impersonating themselves', function () {
    $superAdministrator = User::factory()->superAdministrator()->create();

    $this->actingAs($superAdministrator)->post(route('users.impersonate', $superAdministrator))->assertForbidden();
});

it('forbids impersonating another super administrator', function () {
    $superAdministrator = User::factory()->superAdministrator()->create();
    $otherSuperAdministrator = User::factory()->superAdministrator()->create();

    $this->actingAs($superAdministrator)->post(route('users.impersonate', $otherSuperAdministrator))->assertForbidden();
    $this->assertAuthenticatedAs($superAdministrator);
});

it('forbids impersonating an inactive account', function () {
    $superAdministrator = User::factory()->superAdministrator()->create();
    $inactiveAgent = User::factory()->inactive()->create();

    $this->actingAs($superAdministrator)->post(route('users.impersonate', $inactiveAgent))->assertForbidden();
});

it('refuses to stack a second impersonation session', function () {
    $originalSuperAdministrator = User::factory()->superAdministrator()->create();
    $currentSuperAdministrator = User::factory()->superAdministrator()->create();
    $target = User::factory()->create();

    $response = $this->actingAs($currentSuperAdministrator)
        ->withSession(['impersonator_id' => $originalSuperAdministrator->id])
        ->post(route('users.impersonate', $target));

    $response->assertRedirect();
    $this->assertAuthenticatedAs($currentSuperAdministrator);
});

it('lets a super administrator return to their own account', function () {
    $superAdministrator = User::factory()->superAdministrator()->create();
    $agent = User::factory()->create(['name' => 'Target Agent']);

    $this->actingAs($superAdministrator)->post(route('users.impersonate', $agent));
    $this->assertAuthenticatedAs($agent);

    $response = $this->delete(route('impersonate.stop'));

    $response->assertRedirect(route('users.index'));
    $this->assertAuthenticatedAs($superAdministrator);
    $this->assertDatabaseHas(AuditLog::class, [
        'user_id' => $superAdministrator->id,
        'action' => 'user.impersonation_ended',
        'auditable_id' => $agent->id,
    ]);
});

it('gives a friendly error when stopping impersonation that never started', function () {
    $superAdministrator = User::factory()->superAdministrator()->create();

    $response = $this->actingAs($superAdministrator)->delete(route('impersonate.stop'));

    $response->assertRedirect();
    $this->assertAuthenticatedAs($superAdministrator);
});
