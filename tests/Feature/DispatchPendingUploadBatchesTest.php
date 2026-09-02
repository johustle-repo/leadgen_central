<?php

use App\Jobs\ProcessUploadBatch;
use App\Models\UploadBatch;
use App\UploadBatchStatus;
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

it('unsticks a stale pending upload through the scheduler alone, without a queue:work process', function () {
    // Regression test: routes/console.php must run every recurring task through
    // Schedule::call()/Artisan::call() (in-process) rather than Schedule::command()
    // (which shells out via proc_open). Shared hosts like Hostinger disable
    // proc_open, so a command-string schedule silently never runs even though cron
    // correctly fires `schedule:run` every minute, leaving uploads stuck "pending"
    // forever. This asserts the batch is picked up by `schedule:run` alone.
    $batch = UploadBatch::factory()->create([
        'column_mapping' => ['Company' => 'company_name'],
        'processing_status' => 'pending',
        'stored_filename' => 'lead-imports/does-not-exist.csv',
        'created_at' => now()->subMinutes(3),
    ]);

    $this->artisan('schedule:run')->assertSuccessful();

    expect($batch->fresh()->processing_status)->not->toBe(UploadBatchStatus::Pending);
});
