<?php

namespace App\Services;

use App\LeadSource;
use App\LeadStatus;
use App\Models\DuplicateLog;
use App\Models\DuplicateMatch;
use App\Models\Lead;
use App\Models\UploadBatch;
use App\Models\UploadRow;
use App\UploadBatchStatus;
use App\UploadRowStatus;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class UploadBatchProcessor
{
    public function __construct(
        private LeadCreator $leadCreator,
        private LeadNormalizationService $normalizer,
        private LocationMatchingService $locations,
        private DuplicateDetectionService $duplicates,
    ) {}

    public function process(UploadBatch $batch): void
    {
        $batch->loadMissing('user');
        $batch->update(['processing_status' => UploadBatchStatus::Processing, 'started_at' => now(), 'failure_message' => null]);
        $stream = Storage::disk('local')->readStream($batch->stored_filename);
        if ($stream === null) {
            throw new RuntimeException('The uploaded file could not be read.');
        }
        $headers = fgetcsv($stream, escape: '');
        if (! is_array($headers)) {
            throw new RuntimeException('The CSV file is empty.');
        }
        $rowNumber = 1;
        while (($values = fgetcsv($stream, escape: '')) !== false) {
            $rowNumber++;
            // A row where every cell is blank (a stray blank line, or a run of
            // empty-but-comma-padded cells from an Excel export) carries no
            // data to reject - it isn't a lead that failed validation, so it
            // shouldn't be created, counted, or reported as a data issue.
            if ($this->isBlankRow($values)) {
                UploadRow::query()->where('upload_batch_id', $batch->id)->where('row_number', $rowNumber)->delete();

                continue;
            }
            $raw = [];
            foreach ($headers as $index => $header) {
                $raw[(string) $header] = $values[$index] ?? null;
            }
            $row = UploadRow::query()->firstOrCreate(['upload_batch_id' => $batch->id, 'row_number' => $rowNumber], ['raw_data' => $raw]);
            if ($row->processing_status !== UploadRowStatus::Pending) {
                continue;
            }
            if (count($values) !== count($headers)) {
                $row->update(['processing_status' => UploadRowStatus::Rejected, 'error_category' => 'structural', 'error_message' => 'The row column count does not match the CSV header.']);

                continue;
            }
            $processed = $this->mappedData($batch, $raw);
            // LinkedIn and the source link are both optional and never block a
            // row: a missing or malformed value in either should never cost a
            // lead its place - it's still stored as submitted.
            $validator = Validator::make($processed, ['lead_date' => ['nullable', 'date'], 'company_name' => ['required', 'string', 'max:255'], 'website' => ['nullable', 'string', 'max:255'], 'country_code' => ['nullable', 'string', 'size:2'], 'email' => ['nullable', 'email', 'max:255'], 'linkedin_url' => ['nullable', 'string', 'max:255'], 'source_url' => ['nullable', 'string', 'max:255']]);
            if ($validator->fails()) {
                $row->update(['processed_data' => $processed, 'processing_status' => UploadRowStatus::Rejected, 'error_category' => 'validation', 'error_message' => $validator->errors()->first()]);

                continue;
            }
            try {
                $normalized = $this->normalizer->normalize($processed);
                $location = $this->locations->match($processed['country_code'] ?? $processed['country'] ?? null, $processed['city'] ?? null);
                $normalized = [...$normalized, ...$location->leadAttributes($processed['city'] ?? null, $processed['country'] ?? ($processed['country_code'] ?? null))];
                // Duplicate detection runs on the row's true submitted values, before
                // any blank field is defaulted below - a shared "N/A" placeholder must
                // never make two otherwise-unrelated blank rows look like duplicates
                // of each other.
                $match = $this->duplicates->find($normalized);
                if ($match && $match['type'] === 'exact') {
                    if ($this->isManualCleaningRoundTrip($batch, $match['lead'])) {
                        $this->cleanExistingManualLead($batch, $row, $processed, $normalized, $match['lead']);

                        continue;
                    }
                    if ($batch->duplicate_handling === 'update_missing' && $match['lead']->agent_id === $batch->user_id) {
                        $this->updateMissingLeadValues($batch, $row, $processed, $normalized, $match['lead']);

                        continue;
                    }

                    $this->recordExactDuplicate($batch, $row, $processed, $match);

                    continue;
                }
                $lead = $this->leadCreator->create($this->applyFieldDefaults($processed), $batch->user, $batch->user, $batch);
                if ($match) {
                    $duplicate = DuplicateMatch::query()->create(['incoming_lead_id' => $lead->id, 'upload_row_id' => $row->id, 'existing_lead_id' => $match['lead']->id, 'match_type' => 'possible', 'match_score' => $match['score'], 'matched_fields' => $match['fields'], 'status' => 'pending']);
                    $lead->update(['status' => 'needs_review', 'validation_status' => 'needs_review']);
                    $row->update(['processed_data' => $processed, 'processing_status' => UploadRowStatus::NeedsReview, 'error_category' => 'possible_duplicate', 'error_message' => 'Possible duplicate requires review.', 'lead_id' => $lead->id, 'duplicate_match_id' => $duplicate->id]);

                    continue;
                }
                // A completed, non-duplicate row is always accepted - location match
                // quality no longer gates review; only a duplicate contact does.
                $row->update(['processed_data' => $processed, 'processing_status' => UploadRowStatus::Accepted, 'error_category' => null, 'error_message' => null, 'lead_id' => $lead->id]);
            } catch (ValidationException $exception) {
                $row->update(['processed_data' => $processed, 'processing_status' => UploadRowStatus::Rejected, 'error_category' => 'company_contact_limit', 'error_message' => $exception->errors()['company_name'][0] ?? 'The company contact limit was exceeded.']);
            } catch (Throwable $exception) {
                report($exception);
                $row->update(['processed_data' => $processed, 'processing_status' => UploadRowStatus::Error, 'error_category' => 'processing', 'error_message' => 'The row could not be saved.']);
            }
        }
        fclose($stream);
        $this->updateSummary($batch);
    }

    /** @param list<string|null> $values */
    private function isBlankRow(array $values): bool
    {
        foreach ($values as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, string|null> $raw
     * @return array<string, mixed>
     */
    private function mappedData(UploadBatch $batch, array $raw): array
    {
        $processed = [];
        foreach ($batch->column_mapping ?? [] as $header => $field) {
            if ($field !== null && $field !== '') {
                $processed[$field] = trim((string) ($raw[$header] ?? '')) ?: null;
            }
        }
        if (isset($processed['country']) && preg_match('/^[A-Za-z]{2}$/', (string) $processed['country']) === 1) {
            $processed['country_code'] = strtoupper((string) $processed['country']);
            unset($processed['country']);
        }
        $processed['lead_date'] = $this->resolveLeadDate($processed['lead_date'] ?? null, $batch);
        // A source link over the validator's 255-character limit would otherwise
        // reject an entire row over one long tracking URL - shortening it keeps
        // the row (and the rest of its data) instead of losing it.
        if (isset($processed['source_url']) && mb_strlen((string) $processed['source_url']) > 255) {
            $processed['source_url'] = mb_substr((string) $processed['source_url'], 0, 255);
        }

        return $processed;
    }

    /**
     * Fills in the blanks a completed-but-partial row is still allowed to have.
     * Applied only to the data a new lead is actually created from - never to
     * the values duplicate detection compares, and never to email or lead_date,
     * which already have their own null-handling and would otherwise break
     * (an "N/A" email fails format validation; a fake shared value would also
     * make unrelated blank-contact rows look like duplicates of each other).
     *
     * @param  array<string, mixed>  $processed
     * @return array<string, mixed>
     */
    private function applyFieldDefaults(array $processed): array
    {
        foreach (['website', 'contact_person', 'city', 'linkedin_url', 'data_source', 'source_url'] as $field) {
            if (blank($processed[$field] ?? null)) {
                $processed[$field] = 'N/A';
            }
        }
        if (blank($processed['country'] ?? null) && blank($processed['country_code'] ?? null)) {
            $processed['country'] = 'N/A';
        }
        if (blank($processed['import_trades'] ?? null)) {
            $processed['import_trades'] = '0';
        }

        return $processed;
    }

    /**
     * Every imported lead gets a date: an explicit column value wins, then a
     * date embedded in the filename, and finally the date the file itself
     * was uploaded - so lead_date is never left blank.
     */
    private function resolveLeadDate(mixed $value, UploadBatch $batch): ?string
    {
        $dateValue = trim((string) $value);
        if ($dateValue !== '') {
            foreach (['Y-m-d', 'm/d/Y', 'm-d-Y', 'd/m/Y', 'd-m-Y'] as $format) {
                try {
                    $date = Date::createFromFormat($format, $dateValue);
                    if ($date !== null && $date->format($format) === $dateValue) {
                        return $date->toDateString();
                    }
                } catch (Throwable) {
                    continue;
                }
            }

            return $dateValue;
        }

        return $this->dateFromFilename($batch->original_filename) ?? $batch->created_at?->toDateString();
    }

    private function dateFromFilename(string $filename): ?string
    {
        if (preg_match('/(?<!\d)(\d{2})[-_](\d{2})[-_](\d{4})(?!\d)/', $filename, $matches) === 1) {
            try {
                return Date::createFromFormat('m-d-Y', "{$matches[1]}-{$matches[2]}-{$matches[3]}")?->toDateString();
            } catch (Throwable) {
                return null;
            }
        }

        if (preg_match('/(?<!\d)(\d{4})[-_](\d{2})[-_](\d{2})(?!\d)/', $filename, $matches) === 1) {
            try {
                return Date::createFromFormat('Y-m-d', "{$matches[1]}-{$matches[2]}-{$matches[3]}")?->toDateString();
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $processed
     * @param  array{lead: Lead, type: string, score: int, fields: list<string>}  $match
     */
    private function recordExactDuplicate(UploadBatch $batch, UploadRow $row, array $processed, array $match): void
    {
        $duplicate = DuplicateMatch::query()->create(['upload_row_id' => $row->id, 'existing_lead_id' => $match['lead']->id, 'match_type' => 'exact', 'match_score' => $match['score'], 'matched_fields' => $match['fields'], 'status' => 'confirmed']);
        DuplicateLog::query()->create(['uploading_agent_id' => $batch->user_id, 'original_lead_id' => $match['lead']->id, 'original_owner_id' => $match['lead']->agent_id, 'upload_batch_id' => $batch->id, 'upload_row_id' => $row->id, 'duplicate_match_id' => $duplicate->id, 'detection_reason' => implode(', ', $match['fields'])]);
        $row->update(['processed_data' => $processed, 'processing_status' => UploadRowStatus::Duplicate, 'error_category' => 'exact_duplicate', 'error_message' => 'Exact duplicate of '.$match['lead']->lead_code.'. Original ownership retained.', 'lead_id' => $match['lead']->id, 'duplicate_match_id' => $duplicate->id]);
    }

    private function isManualCleaningRoundTrip(UploadBatch $batch, Lead $lead): bool
    {
        return $lead->agent_id === $batch->user_id && $lead->source === LeadSource::Manual;
    }

    /** @param array<string, mixed> $processed
     * @param  array<string, mixed>  $normalized
     */
    private function cleanExistingManualLead(UploadBatch $batch, UploadRow $row, array $processed, array $normalized, Lead $lead): void
    {
        $lead->update([...$normalized, 'status' => LeadStatus::Validated, 'updated_by' => $batch->user_id]);
        $row->update([
            'processed_data' => $processed,
            'processing_status' => UploadRowStatus::Accepted,
            'error_category' => null,
            'error_message' => null,
            'lead_id' => $lead->id,
            'duplicate_match_id' => null,
        ]);
    }

    /** @param array<string, mixed> $processed
     * @param  array<string, mixed>  $normalized
     */
    private function updateMissingLeadValues(UploadBatch $batch, UploadRow $row, array $processed, array $normalized, Lead $lead): void
    {
        $attributes = collect($normalized)
            ->only(['lead_date', 'website', 'original_website', 'website_domain', 'address', 'city', 'raw_city', 'state_province', 'country', 'raw_country', 'country_code', 'canonical_city_id', 'canonical_country_id', 'timezone', 'industry', 'business_type', 'contact_person', 'position', 'email', 'phone', 'linkedin_url', 'import_trades', 'data_source', 'source_url', 'notes'])
            ->filter(fn (mixed $value, string $field): bool => filled($value) && ($field === 'lead_date' || blank($lead->getAttribute($field))))
            ->all();

        $lead->update([...$attributes, 'updated_by' => $batch->user_id]);
        $row->update([
            'processed_data' => $processed,
            'processing_status' => UploadRowStatus::Accepted,
            'error_category' => null,
            'error_message' => null,
            'lead_id' => $lead->id,
            'duplicate_match_id' => null,
        ]);
    }

    private function updateSummary(UploadBatch $batch): void
    {
        $rows = $batch->rows();
        $total = (clone $rows)->count();
        $accepted = (clone $rows)->whereIn('processing_status', [UploadRowStatus::Accepted, UploadRowStatus::NeedsReview])->count();
        $exact = (clone $rows)->where('error_category', 'exact_duplicate')->count();
        $possible = (clone $rows)->where('error_category', 'possible_duplicate')->count();
        $invalid = (clone $rows)->where('processing_status', UploadRowStatus::Rejected)->count();
        $location = (clone $rows)->where('error_category', 'location')->count();
        $errors = (clone $rows)->where('processing_status', UploadRowStatus::Error)->count();
        $batch->update(['total_rows' => $total, 'new_leads' => $accepted, 'valid_leads' => (clone $rows)->where('processing_status', UploadRowStatus::Accepted)->count(), 'accepted_rows' => $accepted, 'rejected_rows' => $invalid, 'invalid_rows' => $invalid, 'location_error_rows' => $location, 'duplicate_rows' => $exact + $possible, 'exact_duplicate_rows' => $exact, 'possible_duplicate_rows' => $possible, 'error_rows' => $errors, 'processing_status' => UploadBatchStatus::Completed, 'completed_at' => now()]);
    }
}
