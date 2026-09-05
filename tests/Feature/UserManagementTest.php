<?php

use App\Models\EmailReply;
use App\Models\Lead;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('allows administrators to create users', function () {
    $administrator = User::factory()->administrator()->create();
    $response = $this->actingAs($administrator)->post(route('users.store'), ['name' => 'Lead Agent', 'email' => 'agent@example.com', 'password' => 'password', 'password_confirmation' => 'password', 'role' => 'agent', 'status' => 'active', 'team' => 'North']);
    $response->assertRedirect(route('users.index'));
    $this->assertDatabaseHas('users', ['email' => 'agent@example.com', 'role' => 'agent', 'status' => 'active']);
});

it('saves an employee code and alias name/email when creating a user', function () {
    $administrator = User::factory()->administrator()->create();

    $this->actingAs($administrator)->post(route('users.store'), [
        'name' => 'Lead Agent', 'email' => 'agent@example.com', 'password' => 'password', 'password_confirmation' => 'password',
        'role' => 'agent', 'status' => 'active',
        'employee_code' => 'DUS-010', 'alias_name' => 'Alex Bennett', 'alias_email' => 'a.bennett@example.com',
    ])->assertRedirect(route('users.index'));

    $this->assertDatabaseHas('users', [
        'email' => 'agent@example.com',
        'employee_code' => 'DUS-010',
        'alias_name' => 'Alex Bennett',
        'alias_email' => 'a.bennett@example.com',
    ]);
});

it('rejects a duplicate employee code', function () {
    $administrator = User::factory()->administrator()->create();
    User::factory()->create(['employee_code' => 'DUS-020']);

    $this->actingAs($administrator)->post(route('users.store'), [
        'name' => 'Lead Agent', 'email' => 'agent2@example.com', 'password' => 'password', 'password_confirmation' => 'password',
        'role' => 'agent', 'status' => 'active', 'employee_code' => 'DUS-020',
    ])->assertSessionHasErrors('employee_code');
});

it('excludes super administrators from the user list', function () {
    $administrator = User::factory()->administrator()->create();
    $superAdministrator = User::factory()->superAdministrator()->create(['name' => 'Hidden Super Admin']);
    $agent = User::factory()->create(['name' => 'Visible Agent']);

    $response = $this->actingAs($administrator)->get(route('users.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('users.data', fn ($data) => collect($data)->pluck('name')->doesntContain('Hidden Super Admin')
            && collect($data)->pluck('name')->contains('Visible Agent')));
});

it('shows each users total leads and replies', function () {
    $administrator = User::factory()->administrator()->create();
    $agent = User::factory()->create(['created_at' => now()->addMinute()]);
    $leads = Lead::factory(3)->for($agent, 'agent')->create();
    EmailReply::factory(2)->for($agent, 'agent')->for($leads->first())->create();

    $response = $this->actingAs($administrator)->get(route('users.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('users.data.0.id', $agent->id)
        ->where('users.data.0.leads_count', 3)
        ->where('users.data.0.email_replies_count', 2));
});

it('forbids agents from user management', function () {
    $agent = User::factory()->create();
    $this->actingAs($agent)->get(route('users.index'))->assertForbidden();
    $this->actingAs($agent)->post(route('users.store'), ['name' => 'Unauthorized'])->assertForbidden();
});

it('allows administrators to soft delete another user', function () {
    $administrator = User::factory()->administrator()->create();
    $agent = User::factory()->create();

    $response = $this->actingAs($administrator)->delete(route('users.destroy', $agent));

    $response->assertRedirect(route('users.index'))->assertSessionHas('toast.message', "{$agent->name} was deleted successfully.");
    $this->assertSoftDeleted($agent);
});

it('forbids administrators from deleting themselves', function () {
    $administrator = User::factory()->administrator()->create();

    $this->actingAs($administrator)->delete(route('users.destroy', $administrator))->assertForbidden();

    expect($administrator->fresh()->trashed())->toBeFalse();
});

it('forbids agents from deleting users', function () {
    $agent = User::factory()->create();
    $otherAgent = User::factory()->create();

    $this->actingAs($agent)->delete(route('users.destroy', $otherAgent))->assertForbidden();

    expect($otherAgent->fresh()->trashed())->toBeFalse();
});

it('allows administrators to switch each users email sequence', function () {
    $administrator = User::factory()->administrator()->create();
    $agent = User::factory()->create();

    $this->actingAs($administrator)->get(route('users.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('users.data.0.email_sequence_enabled', true));

    $response = $this->actingAs($administrator)->patch(route('users.email-sequence.toggle', $agent), [
        'is_active' => false,
    ]);

    $response->assertRedirect()->assertSessionHas('toast.message', "Email sequence paused for {$agent->name}.");
    $this->assertDatabaseHas('email_sequences', ['user_id' => $agent->id, 'is_active' => false]);
});

it('forbids agents from switching another users email sequence', function () {
    $agent = User::factory()->create();
    $otherAgent = User::factory()->create();

    $this->actingAs($agent)->patch(route('users.email-sequence.toggle', $otherAgent), [
        'is_active' => false,
    ])->assertForbidden();

    $this->assertDatabaseCount('email_sequences', 0);
});

it('does not authenticate inactive users', function () {
    $inactive = User::factory()->inactive()->create();
    $this->post(route('login.store'), ['email' => $inactive->email, 'password' => 'password'])->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('allows a super administrator to create an administrator account', function () {
    $superAdministrator = User::factory()->superAdministrator()->create();

    $response = $this->actingAs($superAdministrator)->post(route('users.store'), ['name' => 'New Admin', 'email' => 'new-admin@example.com', 'password' => 'password', 'password_confirmation' => 'password', 'role' => 'administrator', 'status' => 'active']);

    $response->assertRedirect(route('users.index'));
    $this->assertDatabaseHas('users', ['email' => 'new-admin@example.com', 'role' => 'administrator']);
});

it('forbids a regular administrator from creating an administrator or super administrator account', function () {
    $administrator = User::factory()->administrator()->create();

    $this->actingAs($administrator)->post(route('users.store'), ['name' => 'New Admin', 'email' => 'new-admin@example.com', 'password' => 'password', 'password_confirmation' => 'password', 'role' => 'administrator', 'status' => 'active'])
        ->assertSessionHasErrors('role');
    $this->actingAs($administrator)->post(route('users.store'), ['name' => 'New Super', 'email' => 'new-super@example.com', 'password' => 'password', 'password_confirmation' => 'password', 'role' => 'super_administrator', 'status' => 'active'])
        ->assertSessionHasErrors('role');
    $this->assertDatabaseMissing('users', ['email' => 'new-admin@example.com']);
    $this->assertDatabaseMissing('users', ['email' => 'new-super@example.com']);
});

it('forbids a regular administrator from editing or deleting another administrator', function () {
    $administrator = User::factory()->administrator()->create();
    $otherAdministrator = User::factory()->administrator()->create();

    $this->actingAs($administrator)->get(route('users.edit', $otherAdministrator))->assertForbidden();
    $this->actingAs($administrator)->put(route('users.update', $otherAdministrator), ['name' => 'Changed', 'email' => $otherAdministrator->email, 'role' => 'administrator', 'status' => 'active'])->assertForbidden();
    $this->actingAs($administrator)->delete(route('users.destroy', $otherAdministrator))->assertForbidden();
    expect($otherAdministrator->fresh()->trashed())->toBeFalse();
});

it('allows a super administrator to edit and delete another administrator', function () {
    $superAdministrator = User::factory()->superAdministrator()->create();
    $administrator = User::factory()->administrator()->create();

    $this->actingAs($superAdministrator)->put(route('users.update', $administrator), ['name' => 'Renamed Admin', 'email' => $administrator->email, 'role' => 'administrator', 'status' => 'active'])
        ->assertRedirect();
    $this->assertDatabaseHas('users', ['id' => $administrator->id, 'name' => 'Renamed Admin']);

    $this->actingAs($superAdministrator)->delete(route('users.destroy', $administrator))->assertRedirect(route('users.index'));
    $this->assertSoftDeleted($administrator);
});

it('forbids a super administrator from deleting themselves', function () {
    $superAdministrator = User::factory()->superAdministrator()->create();

    $this->actingAs($superAdministrator)->delete(route('users.destroy', $superAdministrator))->assertForbidden();

    expect($superAdministrator->fresh()->trashed())->toBeFalse();
});

it('lets an administrator save their own profile without tripping the self role-change guard', function () {
    $administrator = User::factory()->administrator()->create(['name' => 'Old Name']);

    $response = $this->actingAs($administrator)->put(route('users.update', $administrator), ['name' => 'New Name', 'email' => $administrator->email, 'role' => 'administrator', 'status' => 'active']);

    $response->assertSessionDoesntHaveErrors();
    $this->assertDatabaseHas('users', ['id' => $administrator->id, 'name' => 'New Name']);
});

it('blocks an administrator from changing their own role', function () {
    $administrator = User::factory()->administrator()->create();

    $response = $this->actingAs($administrator)->put(route('users.update', $administrator), ['name' => $administrator->name, 'email' => $administrator->email, 'role' => 'sub_administrator', 'status' => 'active']);

    $response->assertSessionHasErrors('status');
    $this->assertDatabaseHas('users', ['id' => $administrator->id, 'role' => 'administrator']);
});

it('allows administrators to manage sub-administrators and agents', function () {
    $administrator = User::factory()->administrator()->create();
    $subAdministrator = User::factory()->subAdministrator()->create();

    $this->actingAs($administrator)->put(route('users.update', $subAdministrator), ['name' => 'Updated Sub', 'email' => $subAdministrator->email, 'role' => 'sub_administrator', 'status' => 'active'])
        ->assertRedirect();
    $this->assertDatabaseHas('users', ['id' => $subAdministrator->id, 'name' => 'Updated Sub']);
});
