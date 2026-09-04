<?php

namespace App\Services;

use App\AttendanceEntryType;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Throwable;

/**
 * Imports historical attendance records from a JSON file, tolerant of a
 * few common export shapes: a flat array of records, or a phpMyAdmin
 * "table" export ({"type": "table", "data": [...]}) among other entries.
 * Records are matched to an existing user by email (preferred) or exact
 * name, and rows that can't be resolved are skipped and reported rather
 * than aborting the whole import.
 */
class AttendanceImportService
{
    public function import(UploadedFile $file): AttendanceImportResult
    {
        $decoded = json_decode((string) file_get_contents($file->getRealPath()), true);

        if (! is_array($decoded)) {
            return new AttendanceImportResult(0, 0, ['The file is not valid JSON.']);
        }

        $records = $this->extractRecords($decoded);
        $imported = 0;
        $errors = [];

        foreach ($records as $index => $record) {
            if (! is_array($record)) {
                $errors[] = 'Row '.($index + 1).': not a valid record.';

                continue;
            }

            try {
                Attendance::query()->create($this->normalizeRecord($record, $index));
                $imported++;
            } catch (InvalidArgumentException $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        return new AttendanceImportResult(count($records), $imported, $errors);
    }

    /**
     * @param  array<int|string, mixed>  $decoded
     * @return list<mixed>
     */
    private function extractRecords(array $decoded): array
    {
        if (array_is_list($decoded)) {
            $first = $decoded[0] ?? null;

            if (is_array($first) && ! array_key_exists('type', $first)) {
                return $decoded;
            }

            foreach ($decoded as $entry) {
                if (is_array($entry) && ($entry['type'] ?? null) === 'table' && is_array($entry['data'] ?? null)) {
                    return array_values($entry['data']);
                }
            }
        }

        return array_values($decoded);
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array{user_id: int, recorded_at: Carbon, entry_type: AttendanceEntryType, source: string}
     */
    private function normalizeRecord(array $record, int $index): array
    {
        $row = $index + 1;
        $email = $record['email'] ?? $record['user_email'] ?? null;
        $name = $record['name'] ?? $record['user_name'] ?? null;

        $user = null;
        if (is_string($email) && $email !== '') {
            $user = User::query()->whereRaw('LOWER(email) = ?', [strtolower(trim($email))])->first();
        }
        if (! $user instanceof User && is_string($name) && $name !== '') {
            $user = User::query()->whereRaw('LOWER(name) = ?', [strtolower(trim($name))])->first();
        }
        if (! $user instanceof User) {
            throw new InvalidArgumentException("Row {$row}: no matching user for ".($email ?: $name ?: 'unknown').'.');
        }

        $rawType = strtolower(trim((string) ($record['entry_type'] ?? $record['type'] ?? '')));
        $entryType = match (true) {
            in_array($rawType, ['time_in', 'time in', 'in', 'timein'], true) => AttendanceEntryType::TimeIn,
            in_array($rawType, ['time_out', 'time out', 'out', 'timeout'], true) => AttendanceEntryType::TimeOut,
            default => null,
        };
        if ($entryType === null) {
            throw new InvalidArgumentException("Row {$row}: unrecognized entry type \"{$rawType}\".");
        }

        $rawDate = $record['recorded_at'] ?? $record['timestamp'] ?? $record['time'] ?? $record['datetime'] ?? null;
        if (! is_string($rawDate) && ! is_int($rawDate)) {
            throw new InvalidArgumentException("Row {$row}: missing a recorded_at/timestamp value.");
        }

        try {
            $recordedAt = Carbon::parse((string) $rawDate);
        } catch (Throwable) {
            throw new InvalidArgumentException("Row {$row}: unparseable date \"{$rawDate}\".");
        }

        return [
            'user_id' => $user->id,
            'recorded_at' => $recordedAt,
            'entry_type' => $entryType,
            'source' => is_string($record['source'] ?? null) ? $record['source'] : 'import',
        ];
    }
}
