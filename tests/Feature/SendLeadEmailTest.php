<?php

use App\Models\GmailConnection;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Storage::disk('local')->put('brochures/duscaff.pdf', 'sample brochure content');
    config([
        'services.google.brochure_path' => Storage::disk('local')->path('brochures/duscaff.pdf'),
        'services.google.brochure_name' => 'DUSCAFF brochure.pdf',
    ]);
});

it('allows an agent to send the DUSCAFF email to their lead with the brochure attached', function () {
    $agent = User::factory()->create();
    $lead = Lead::factory()->for($agent, 'agent')->create([
        'contact_person' => 'Ericka Bradbury',
        'email' => 'ericka@example.com',
    ]);
    GmailConnection::factory()->for($agent, 'user')->create([
        'gmail_address' => 'agent@example.com',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://gmail.googleapis.com/gmail/v1/users/me/messages/send' => Http::response(['id' => 'gmail-message-123']),
    ]);

    $response = $this->actingAs($agent)->post(route('leads.send-email', $lead), [
        'subject' => 'Competitive Scaffolding Materials from DUSCAFF',
        'body' => 'Hi Ericka Bradbury, welcome to DUSCAFF.',
    ]);

    $response->assertRedirect()->assertSessionHas('toast.message', 'Email sent successfully to ericka@example.com.');
    Http::assertSent(function (Request $request): bool {
        $rawMessage = base64_decode(strtr((string) $request->data()['raw'], '-_', '+/'));

        return $request->url() === 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send'
            && str_contains($rawMessage, 'To: ericka@example.com')
            && str_contains($rawMessage, base64_encode('Hi Ericka Bradbury, welcome to DUSCAFF.'))
            && str_contains($rawMessage, 'filename="DUSCAFF brochure.pdf"')
            && str_contains($rawMessage, base64_encode('sample brochure content'));
    });
    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $agent->id,
        'action' => 'lead.email_sent',
        'auditable_id' => $lead->id,
    ]);
});

it('prevents an agent from sending email to another agents lead', function () {
    $agent = User::factory()->create();
    $lead = Lead::factory()->create();
    Http::preventStrayRequests();

    $response = $this->actingAs($agent)->post(route('leads.send-email', $lead), [
        'subject' => 'DUSCAFF',
        'body' => 'Message',
    ]);

    $response->assertForbidden();
    Http::assertNothingSent();
    $this->assertDatabaseCount('audit_logs', 0);
});

it('requires a connected Gmail account before sending', function () {
    $agent = User::factory()->create();
    $lead = Lead::factory()->for($agent, 'agent')->create();
    Http::preventStrayRequests();

    $response = $this->actingAs($agent)->post(route('leads.send-email', $lead), [
        'subject' => 'DUSCAFF',
        'body' => 'Message',
    ]);

    $response->assertRedirect()->assertSessionHas('toast.message', 'Connect Gmail before sending an email.');
    Http::assertNothingSent();
    $this->assertDatabaseCount('audit_logs', 0);
});
