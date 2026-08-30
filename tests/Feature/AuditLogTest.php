<?php

use App\Models\AuditLog;
use App\Models\Lead;
use App\Models\UploadBatch;
use App\Models\UploadRow;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

it('allows an administrator to delete upload history and records the audit event', function () {
    Storage::fake('local');
    $administrator = User::factory()->administrator()->create();
    $owner = User::factory()->create();
    $batch = UploadBatch::factory()->for($owner)->create(['stored_filename' => 'lead-imports/delete-me.csv', 'total_rows' => 1, 'accepted_rows' => 1]);
    Storage::disk('local')->put($batch->stored_filename, "Company\nAcme\n");
    $lead = Lead::factory()->for($owner, 'agent')->for($batch, 'uploadBatch')->create();
    UploadRow::factory()->for($batch)->for($lead)->create();

    $response = $this->actingAs($administrator)
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.10', 'HTTP_USER_AGENT' => 'Audit Test Browser'])
        ->delete(route('uploads.destroy', $batch));

    $response->assertRedirect(route('uploads.index'))->assertSessionHas('toast');
    $this->assertModelMissing($batch);
    $this->assertModelExists($lead);
    expect($lead->refresh()->upload_batch_id)->toBeNull();
    Storage::disk('local')->assertMissing('lead-imports/delete-me.csv');
    $this->assertDatabaseMissing('upload_rows', ['upload_batch_id' => $batch->id]);
    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $administrator->id,
        'action' => 'upload_batch.deleted',
        'auditable_type' => 'upload_batch',
        'auditable_id' => $batch->id,
        'ip_address' => '203.0.113.10',
    ]);
    expect(AuditLog::firstOrFail()->metadata)->toMatchArray(['batch_code' => $batch->batch_code, 'owner_id' => $owner->id]);
});

it('prevents non-administrators from deleting upload history', function () {
    Storage::fake('local');
    $agent = User::factory()->create();
    $batch = UploadBatch::factory()->for($agent)->create();

    $this->actingAs($agent)->delete(route('uploads.destroy', $batch))->assertForbidden();

    $this->assertModelExists($batch);
    $this->assertDatabaseCount('audit_logs', 0);
});

it('prevents deletion while an upload is processing', function () {
    $administrator = User::factory()->administrator()->create();
    $batch = UploadBatch::factory()->create(['processing_status' => 'processing']);

    $this->actingAs($administrator)->delete(route('uploads.destroy', $batch))->assertForbidden();

    $this->assertModelExists($batch);
});

it('allows only administrators to view audit logs', function () {
    $administrator = User::factory()->administrator()->create();
    $agent = User::factory()->create();
    AuditLog::factory()->for($administrator)->create();

    $this->actingAs($administrator)->get(route('audit-logs.index'))->assertOk();
    $this->actingAs($agent)->get(route('audit-logs.index'))->assertForbidden();
});
