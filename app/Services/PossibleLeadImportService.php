<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class PossibleLeadImportService
{
    /** @var list<string> */
    private const OVERWRITE_FIELDS = ['website', 'city', 'country', 'country_code', 'contact_person', 'position', 'email', 'phone', 'industry'];

    public function __construct(
        private CsvHeaderMapper $mapper,
        private CsvDelimiterDetector $delimiters,
        private CsvEncodingSanitizer $encoding,
        private LeadNormalizationService $normalizer,
        private LeadCreator $creator,
        private LeadVerificationService $verification,
    ) {}

    /**
     * Matches each row of a list of possible leads (by email, then by company
     * name) against leads already in the database: a match gets its data
     * refreshed and is marked Possible Lead; anything with no match is
     * created fresh, directly as a Possible Lead, owned by $defaultOwner.
     *
     * @return array{updated: int, created: int, ambiguous: list<string>, skipped: list<string>}
     */
    public function import(UploadedFile $file, User $actor, User $defaultOwner): array
    {
        $stream = fopen($file->getRealPath(), 'rb');
        if ($stream === false) {
            throw ValidationException::withMessages(['file' => 'The file could not be read.']);
        }
        $delimiter = $this->delimiters->detect($stream);
        $headers = fgetcsv($stream, separator: $delimiter, escape: '');
        if (! is_array($headers)) {
            fclose($stream);

            throw ValidationException::withMessages(['file' => 'The file must contain a readable header row.']);
        }
        $headers = $this->encoding->sanitizeRow(array_map('strval', $headers));
        $mapping = $this->mapper->map($headers);
        if (! in_array('company_name', $mapping, true)) {
            fclose($stream);

            throw ValidationException::withMessages(['file' => 'The file must contain a recognizable Company column.']);
        }

        $updated = 0;
        $created = 0;
        $ambiguous = [];
        $skipped = [];
        $previousLead = null;

        while (($values = fgetcsv($stream, separator: $delimiter, escape: '')) !== false) {
            $values = $this->encoding->sanitizeRow(array_map('strval', $values));
            if ($this->isBlankRow($values)) {
                continue;
            }
            $raw = [];
            foreach ($headers as $index => $header) {
                $raw[$header] = $values[$index] ?? null;
            }
            $processed = [];
            foreach ($mapping as $header => $field) {
                if ($field !== null && $field !== '') {
                    $processed[$field] = trim((string) ($raw[$header] ?? '')) ?: null;
                }
            }

            $companyName = $processed['company_name'] ?? null;
            if (blank($companyName)) {
                // Some exports repeat a company across several lines to list
                // an extra product or an extra contact rather than repeating
                // every column, leaving Company Name blank on those rows -
                // fold them into whichever lead this import just touched
                // instead of discarding them as rows with no company.
                if ($previousLead !== null && filled($processed['product_requested'] ?? null)) {
                    try {
                        $previousLead = $this->mergeContinuationRow($previousLead, $processed);
                    } catch (Throwable $exception) {
                        report($exception);
                        $skipped[] = "Continuation row for {$previousLead->company_name} (could not be saved: {$exception->getMessage()})";
                    }
                } else {
                    $skipped[] = 'A row with no Company Name';
                }

                continue;
            }

            try {
                $matches = $this->findMatches($processed);

                if ($matches->count() > 1) {
                    $ambiguous[] = $companyName;
                    $previousLead = null;

                    continue;
                }

                if ($matches->count() === 1) {
                    $previousLead = $this->updateExisting($matches->first(), $processed, $actor);
                    $updated++;

                    continue;
                }

                $previousLead = $this->createNew($processed, $defaultOwner, $actor);
                $created++;
            } catch (Throwable $exception) {
                report($exception);
                $skipped[] = "{$companyName} (could not be saved: {$exception->getMessage()})";
                $previousLead = null;
            }
        }
        fclose($stream);

        return ['updated' => $updated, 'created' => $created, 'ambiguous' => $ambiguous, 'skipped' => $skipped];
    }

    /**
     * @param  array<string, mixed>  $processed
     * @return Collection<int, Lead>
     */
    private function findMatches(array $processed): Collection
    {
        $email = filled($processed['email'] ?? null) ? Str::lower(trim((string) $processed['email'])) : null;
        if ($email !== null) {
            $byEmail = Lead::query()->where('email', $email)->get();
            if ($byEmail->isNotEmpty()) {
                return $byEmail;
            }
        }

        $normalizedCompany = $this->normalizer->normalize(['company_name' => $processed['company_name']])['normalized_company_name'];

        return Lead::query()->where('normalized_company_name', $normalizedCompany)->get();
    }

    /** @param  array<string, mixed>  $processed */
    private function updateExisting(Lead $lead, array $processed, User $actor): Lead
    {
        $updates = [];
        foreach (self::OVERWRITE_FIELDS as $field) {
            if (filled($processed[$field] ?? null)) {
                $updates[$field] = $processed[$field];
            }
        }

        $product = trim((string) ($processed['product_requested'] ?? ''));
        if ($product !== '') {
            $existing = array_filter(array_map('trim', explode(',', (string) $lead->product_requested)));
            if (! in_array($product, $existing, true)) {
                $existing[] = $product;
            }
            $updates['product_requested'] = implode(', ', $existing);
        }

        $note = trim((string) ($processed['notes'] ?? ''));
        if ($note !== '' && ! str_contains((string) $lead->notes, $note)) {
            $updates['notes'] = trim(($lead->notes !== null ? $lead->notes."\n" : '').$note);
        }

        return $this->verification->verify($lead, [
            ...$updates,
            'status' => 'possible_lead',
            'remarks' => 'Updated and marked as a possible lead via bulk import by '.$actor->name.'.',
        ], $actor);
    }

    /** @param  array<string, mixed>  $processed */
    private function createNew(array $processed, User $owner, User $actor): Lead
    {
        $lead = $this->creator->create($processed, $owner, $actor);
        $this->verification->verify($lead, [
            'status' => 'possible_lead',
            'remarks' => 'Added as a possible lead via bulk import by '.$actor->name.'.',
        ], $actor);

        return $lead->refresh();
    }

    /** @param  array<string, mixed>  $processed */
    private function mergeContinuationRow(Lead $lead, array $processed): Lead
    {
        $updates = [];
        $product = trim((string) ($processed['product_requested'] ?? ''));
        if ($product !== '') {
            $existing = array_filter(array_map('trim', explode(',', (string) $lead->product_requested)));
            if (! in_array($product, $existing, true)) {
                $existing[] = $product;
            }
            $updates['product_requested'] = implode(', ', $existing);
        }

        $contactPerson = trim((string) ($processed['contact_person'] ?? ''));
        $email = trim((string) ($processed['email'] ?? ''));
        $contactNote = 'Additional contact from import: '.trim($contactPerson.' <'.$email.'>');
        if ((($contactPerson !== '' && $contactPerson !== $lead->contact_person) || ($email !== '' && $email !== $lead->email))
            && ! str_contains((string) $lead->notes, $contactNote)) {
            $updates['notes'] = trim(($lead->notes !== null ? $lead->notes."\n" : '').$contactNote);
        }

        if ($updates !== []) {
            $lead->update($updates);
        }

        return $lead;
    }

    /** @param  list<string|null>  $values */
    private function isBlankRow(array $values): bool
    {
        foreach ($values as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
