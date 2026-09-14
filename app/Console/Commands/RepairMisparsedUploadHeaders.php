<?php

namespace App\Console\Commands;

use App\Jobs\ProcessUploadBatch;
use App\Models\UploadBatch;
use App\Services\CsvDelimiterDetector;
use App\Services\CsvHeaderMapper;
use App\UploadBatchStatus;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('uploads:repair-headers {ids?* : Specific upload batch IDs to repair; defaults to every pending batch with no Company Name column mapped}')]
#[Description('Re-detect the CSV delimiter and rebuild the column mapping for batches whose header was misread as a single column')]
class RepairMisparsedUploadHeaders extends Command
{
    public function handle(CsvHeaderMapper $mapper, CsvDelimiterDetector $delimiters): int
    {
        $ids = array_map('intval', $this->argument('ids'));

        $batches = $ids !== []
            ? UploadBatch::query()->whereKey($ids)->get()
            : UploadBatch::query()
                ->where('processing_status', UploadBatchStatus::Pending)
                ->get()
                ->filter(fn (UploadBatch $batch): bool => ! in_array('company_name', $batch->column_mapping ?? [], true));

        if ($batches->isEmpty()) {
            $this->info('No batches needed repair.');

            return self::SUCCESS;
        }

        $repaired = 0;
        foreach ($batches as $batch) {
            $stream = Storage::disk('local')->readStream($batch->stored_filename);
            if ($stream === null) {
                $this->components->warn("Batch {$batch->id}: stored file is missing, skipped.");

                continue;
            }

            $headers = fgetcsv($stream, separator: $delimiters->detect($stream), escape: '');
            fclose($stream);

            if (! is_array($headers)) {
                $this->components->warn("Batch {$batch->id}: header row could not be read, skipped.");

                continue;
            }

            $stringHeaders = array_map('strval', $headers);
            $mapping = $mapper->map($stringHeaders);

            if (! in_array('company_name', $mapping, true)) {
                $this->components->warn("Batch {$batch->id}: still no Company Name column after re-parsing, needs manual remapping.");

                continue;
            }

            $batch->update(['headers' => $stringHeaders, 'column_mapping' => $mapping]);
            ProcessUploadBatch::dispatch($batch->id);
            $repaired++;
            $this->components->info("Batch {$batch->id}: header repaired and requeued.");
        }

        $this->newLine();
        $this->info("Repaired {$repaired} of {$batches->count()} batch(es).");

        return self::SUCCESS;
    }
}
