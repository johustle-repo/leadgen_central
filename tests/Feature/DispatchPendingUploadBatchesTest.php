<?php

use App\Jobs\ProcessUploadBatch;
use App\Models\UploadBatch;
use Illuminate\Support\Facades\Queue;

it('restores missing queue jobs for stale pending uploads', function () {
    Queue::fake();
    $pendingBatch = UploadBatch::factory()->create([
        'column_mapping' => ['Company' => 'company_name'],
        'processing_status' => 'pending',
        'created_at' => now()->subMinutes(3),
    ]);
    UploadBatch::factory()->create([
        'column_mapping' => ['Company' => 'company_name'],
        'processing_status' => 'completed',
        'created_at' => now()->subMinutes(3),
    ]);

    $this->artisan('uploads:dispatch-pending')
        ->expectsOutput('Dispatched 1 pending upload batch(es).')
        ->assertSuccessful();

    Queue::assertPushed(ProcessUploadBatch::class, fn (ProcessUploadBatch $job): bool => $job->uploadBatchId === $pendingBatch->id);
    Queue::assertPushed(ProcessUploadBatch::class, 1);
});

it('does not process fresh or unmapped pending uploads', function () {
    Queue::fake();
    UploadBatch::factory()->create([
        'column_mapping' => ['Company' => 'company_name'],
        'processing_status' => 'pending',
    ]);
    UploadBatch::factory()->create([
        'column_mapping' => ['Email' => 'email'],
        'processing_status' => 'pending',
        'created_at' => now()->subMinutes(3),
    ]);

    $this->artisan('uploads:dispatch-pending')
        ->expectsOutput('Dispatched 0 pending upload batch(es).')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});
