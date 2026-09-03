<?php

namespace App\Services;

use App\Jobs\ProcessUploadBatch;
use App\Models\UploadBatch;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class UploadBatchCreator
{
    public function __construct(private CsvHeaderMapper $mapper) {}

    public function createForMapping(UploadedFile $file, User $owner, string $duplicateHandling): UploadBatch
    {
        $fileDetails = $this->inspect($file, 'file', false);

        return $this->createBatch($file, $fileDetails, $owner, $duplicateHandling);
    }

    /**
     * @param  list<UploadedFile>  $files
     * @return Collection<int, UploadBatch>
     */
    public function createAndQueueMany(array $files, User $owner, string $duplicateHandling): Collection
    {
        $inspectedFiles = collect($files)->map(
            fn (UploadedFile $file, int $index): array => $this->inspect($file, "files.{$index}", true),
        );
        $storedFilenames = collect();

        try {
            $batches = DB::transaction(function () use ($files, $inspectedFiles, $owner, $duplicateHandling, $storedFilenames): Collection {
                return collect($files)->map(function (UploadedFile $file, int $index) use ($inspectedFiles, $owner, $duplicateHandling, $storedFilenames): UploadBatch {
                    return $this->createBatch($file, $inspectedFiles->get($index), $owner, $duplicateHandling, $storedFilenames);
                });
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedFilenames->all());

            throw $exception;
        }

        $batches->each(fn (UploadBatch $batch) => ProcessUploadBatch::dispatch($batch->id));

        return $batches;
    }

    /** @return array{headers: list<string>, mapping: array<string, string|null>} */
    private function inspect(UploadedFile $file, string $errorKey, bool $companyMappingRequired): array
    {
        $stream = fopen($file->getRealPath(), 'rb');
        $headers = $stream ? fgetcsv($stream, escape: '') : false;
        if (is_resource($stream)) {
            fclose($stream);
        }

        $namedHeaders = is_array($headers)
            ? array_values(array_filter(array_map('strval', $headers), fn (string $header): bool => trim($header) !== ''))
            : [];

        if (! is_array($headers) || $namedHeaders === []) {
            throw ValidationException::withMessages([$errorKey => "{$file->getClientOriginalName()} must contain a readable header row."]);
        }
        // Only named columns are compared. Spreadsheet exports routinely pad every row with
        // trailing empty columns, and those all collapse into the same "" entry - so checking
        // the full header count against array_unique() reported a duplicate on files whose
        // real headings were distinct. Blank columns are never mapped (see the mapping form
        // and UploadBatchController::process), so they cannot collide downstream either.
        if (count($namedHeaders) !== count(array_unique($namedHeaders))) {
            throw ValidationException::withMessages([$errorKey => "{$file->getClientOriginalName()} contains duplicate column headers."]);
        }

        $stringHeaders = array_map('strval', $headers);
        $mapping = $this->mapper->map($stringHeaders);
        if ($companyMappingRequired && ! in_array('company_name', $mapping, true)) {
            throw ValidationException::withMessages([$errorKey => "{$file->getClientOriginalName()} must contain a recognizable Company column."]);
        }

        return ['headers' => $stringHeaders, 'mapping' => $mapping];
    }

    /**
     * @param  array{headers: list<string>, mapping: array<string, string|null>}  $fileDetails
     * @param  Collection<int, string>|null  $storedFilenames
     */
    private function createBatch(UploadedFile $file, array $fileDetails, User $owner, string $duplicateHandling, ?Collection $storedFilenames = null): UploadBatch
    {
        $storedFilename = $file->store('lead-imports');
        if (! is_string($storedFilename)) {
            throw new RuntimeException('The uploaded lead file could not be stored.');
        }
        $storedFilenames?->push($storedFilename);

        return UploadBatch::query()->create([
            'user_id' => $owner->id,
            'original_filename' => $file->getClientOriginalName(),
            'stored_filename' => $storedFilename,
            'file_size' => $file->getSize(),
            'headers' => $fileDetails['headers'],
            'column_mapping' => $fileDetails['mapping'],
            'duplicate_handling' => $duplicateHandling,
        ]);
    }
}
