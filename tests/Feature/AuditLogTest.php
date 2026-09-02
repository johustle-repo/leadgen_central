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

it('allows an administrator to bulk delete selected upload histories', function () {
    Storage::fake('local');
    $administrator = User::factory()->administrator()->create();
    $owner = User::factory()->create();
    $completedBatch = UploadBatch::factory()->for($owner)->create([
        'stored_filename' => 'lead-imports/completed.csv',
        'processing_status' => 'completed',
    ]);
    $failedBatch = UploadBatch::factory()->for($owner)->create([
        'stored_filename' => 'lead-imports/failed.csv',
        'processing_status' => 'failed',
    ]);
    $lead = Lead::factory()->for($owner, 'agent')->for($completedBatch, 'uploadBatch')->create();
    Storage::disk('local')->put($completedBatch->stored_filename, "Company\nAcme\n");
    Storage::disk('local')->put($failedBatch->stored_filename, "Company\nFailed\n");

    $response = $this->actingAs($administrator)->delete(route('uploads.bulk-destroy'), [
        'upload_batch_ids' => [$completedBatch->id, $failedBatch->id],
    ]);

    $response->assertRedirect(route('uploads.index'))->assertSessionHas('toast', [
        'type' => 'success',
        'message' => '2 upload histories deleted successfully.',
    ]);
    $this->assertModelMissing($completedBatch);
    $this->assertModelMissing($failedBatch);
    $this->assertModelExists($lead);
    expect($lead->refresh()->upload_batch_id)->toBeNull();
    Storage::disk('local')->assertMissing($completedBatch->stored_filename);
    Storage::disk('local')->assertMissing($failedBatch->stored_filename);
    $this->assertDatabaseCount('audit_logs', 2);
});

it('prevents non-administrators from bulk deleting upload history', function () {
    $agent = User::factory()->create();
    $batch = UploadBatch::factory()->for($agent)->create(['processing_status' => 'completed']);

    $this->actingAs($agent)->delete(route('uploads.bulk-destroy'), [
        'upload_batch_ids' => [$batch->id],
    ])->assertForbidden();

    $this->assertModelExists($batch);
});

it('deletes every deletable upload when select_all is set, ignoring pagination', function () {
    Storage::fake('local');
    $administrator = User::factory()->administrator()->create();
    $completedBatches = UploadBatch::factory()->count(3)->create(['processing_status' => 'completed']);
    $failedBatch = UploadBatch::factory()->create(['processing_status' => 'failed']);
    $processingBatch = UploadBatch::factory()->create(['processing_status' => 'processing']);
    $pendingBatch = UploadBatch::factory()->create(['processing_status' => 'pending']);

    $response = $this->actingAs($administrator)->delete(route('uploads.bulk-destroy'), [
        'select_all' => true,
    ]);

    $response->assertRedirect(route('uploads.index'))->assertSessionHas('toast', [
        'type' => 'success',
        'message' => '4 upload histories deleted successfully.',
    ]);
    $completedBatches->each(fn (UploadBatch $batch) => $this->assertModelMissing($batch));
    $this->assertModelMissing($failedBatch);
    $this->assertModelExists($processingBatch);
    $this->assertModelExists($pendingBatch);
});

it('rejects select_all bulk delete from a non-administrator', function () {
    $agent = User::factory()->create();
    $batch = UploadBatch::factory()->for($agent)->create(['processing_status' => 'completed']);

    $this->actingAs($agent)->delete(route('uploads.bulk-destroy'), [
        'select_all' => true,
    ])->assertForbidden();

    $this->assertModelExists($batch);
});

it('does not partially bulk delete when a selected upload is still processing', function () {
    $administrator = User::factory()->administrator()->create();
    $completedBatch = UploadBatch::factory()->create(['processing_status' => 'completed']);
    $processingBatch = UploadBatch::factory()->create(['processing_status' => 'processing']);

    $this->actingAs($administrator)->delete(route('uploads.bulk-destroy'), [
        'upload_batch_ids' => [$completedBatch->id, $processingBatch->id],
    ])->assertForbidden();

    $this->assertModelExists($completedBatch);
    $this->assertModelExists($processingBatch);
    $this->assertDatabaseCount('audit_logs', 0);
});

it('allows only administrators to view audit logs', function () {
    $administrator = User::factory()->administrator()->create();
    $agent = User::factory()->create();
    AuditLog::factory()->for($administrator)->create();

    $this->actingAs($administrator)->get(route('audit-logs.index'))->assertOk();
    $this->actingAs($agent)->get(route('audit-logs.index'))->assertForbidden();
});
