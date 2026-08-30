<?php

use App\Models\Lead;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('lets a sub-administrator classify a lead and records status history', function () {
    $reviewer = User::factory()->subAdministrator()->create();
    $lead = Lead::factory()->create(['status' => 'raw']);

    $response = $this->actingAs($reviewer)->put(route('verification.update', $lead), ['status' => 'qualified_lead', 'company_name' => $lead->company_name, 'remarks' => 'Verified contact and company.']);

    $response->assertRedirect();
    $this->assertDatabaseHas('leads', ['id' => $lead->id, 'status' => 'qualified_lead', 'validation_status' => 'verified', 'verified_by' => $reviewer->id]);
    $this->assertDatabaseHas('lead_status_histories', ['lead_id' => $lead->id, 'old_status' => 'raw', 'new_status' => 'qualified_lead', 'changed_by' => $reviewer->id]);
});

it('prevents agents from using the verification workspace', function () {
    $agent = User::factory()->create();
    $lead = Lead::factory()->create();

    $this->actingAs($agent)->get(route('verification.show', $lead))->assertForbidden();
    $this->actingAs($agent)->put(route('verification.update', $lead), ['status' => 'qualified_lead', 'company_name' => $lead->company_name])->assertForbidden();
});

it('forwards qualified leads and preserves forwarding and status history', function () {
    $reviewer = User::factory()->subAdministrator()->create();
    $lead = Lead::factory()->create(['status' => 'qualified_lead']);

    $response = $this->actingAs($reviewer)->post(route('leads.forwardings.store', $lead), ['recipient_name' => 'Sales Team', 'recipient_email' => 'sales@example.com', 'remarks' => 'Ready for outreach.']);

    $response->assertRedirect();
    $this->assertDatabaseHas('lead_forwardings', ['lead_id' => $lead->id, 'forwarded_by' => $reviewer->id, 'recipient_email' => 'sales@example.com']);
    $this->assertDatabaseHas('lead_status_histories', ['lead_id' => $lead->id, 'old_status' => 'qualified_lead', 'new_status' => 'forwarded']);
});

it('stores structured notes with their author and timestamp', function () {
    $agent = User::factory()->create();
    $lead = Lead::factory()->for($agent, 'agent')->create(['created_by' => $agent->id]);

    $response = $this->actingAs($agent)->post(route('leads.notes.store', $lead), ['note' => 'Confirmed the contact email.', 'note_type' => 'verification']);

    $response->assertRedirect();
    $this->assertDatabaseHas('lead_notes', ['lead_id' => $lead->id, 'user_id' => $agent->id, 'note' => 'Confirmed the contact email.', 'note_type' => 'verification']);
});

it('does not forward a lead before it is qualified', function () {
    $reviewer = User::factory()->subAdministrator()->create();
    $lead = Lead::factory()->create(['status' => 'possible_lead']);

    $response = $this->actingAs($reviewer)->post(route('leads.forwardings.store', $lead), ['recipient_email' => 'sales@example.com']);

    $response->assertSessionHasErrors('lead');
    $this->assertDatabaseCount('lead_forwardings', 0);
});

it('renders verification records after their owner account is deleted', function () {
    $reviewer = User::factory()->subAdministrator()->create();
    $formerAgent = User::factory()->create();
    $lead = Lead::factory()->for($formerAgent, 'agent')->create(['status' => 'needs_review']);
    $formerAgent->delete();

    $this->actingAs($reviewer)->get(route('verification.index'))->assertInertia(fn (Assert $page) => $page
        ->component('verification/index')
        ->where('leads.data.0.id', $lead->id)
        ->where('leads.data.0.agent', null));
    $this->actingAs($reviewer)->get(route('verification.show', $lead))->assertInertia(fn (Assert $page) => $page
        ->component('verification/show')
        ->where('lead.id', $lead->id)
        ->where('lead.agent', null));
});
