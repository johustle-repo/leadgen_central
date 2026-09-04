<?php

namespace App\Services;

use App\AttendanceEntryType;
use App\Models\Attendance;
use App\Models\Holiday;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Throwable;

/**
 * Imports historical attendance records from a JSON file. Handles this
 * app's own nested export shape ({ users: [ { name, email,
 * attendance_days: [ { is_holiday, logs: [...] } ] } ] }), a flat array
 * of records, or a phpMyAdmin "table" export among other entries.
 * Records are matched to an existing user by email (preferred) or exact
 * name, and rows that can't be resolved are skipped and reported rather
 * than aborting the whole import. Re-importing the same file is safe:
 * matching attendance rows and holidays are left alone, not duplicated.
 */
class AttendanceImportService
{
    public function import(UploadedFile $file): AttendanceImportResult
    {
        $decoded = json_decode((string) file_get_contents($file->getRealPath()), true);

        if (! is_array($decoded)) {
            return new AttendanceImportResult(0, 0, 0, 0, ['The file is not valid JSON.']);
        }

        [$records, $holidayCandidates] = $this->extractRecords($decoded);

        $users = User::query()->get(['id', 'name', 'email']);
        $usersByEmail = $users->keyBy(fn (User $user): string => strtolower(trim($user->email)));
        $usersByName = $users->keyBy(fn (User $user): string => $this->normalizeNameForMatch($user->name));

        $imported = 0;
        $duplicates = 0;
        $errors = [];

        foreach ($records as $index => $record) {
            if (! is_array($record)) {
                $errors[] = 'Row '.($index + 1).': not a valid record.';

                continue;
            }

            try {
                $attributes = $this->normalizeRecord($record, $index, $usersByEmail, $usersByName);
            } catch (InvalidArgumentException $exception) {
                $errors[] = $exception->getMessage();

                continue;
            }

            $attendance = Attendance::query()->firstOrCreate(
                [
                    'user_id' => $attributes['user_id'],
                    'entry_type' => $attributes['entry_type'],
                    'recorded_at' => $attributes['recorded_at'],
                ],
                ['source' => $attributes['source']],
            );

            if ($attendance->wasRecentlyCreated) {
                $imported++;
            } else {
                $duplicates++;
            }
        }

        $holidaysImported = $this->importHolidays($holidayCandidates);

        return new AttendanceImportResult(count($records), $imported, $duplicates, $holidaysImported, $errors);
    }

    /**
     * @param  array<int|string, mixed>  $decoded
     * @return array{0: list<mixed>, 1: list<array{date: string, name: string, type: string, notes: ?string}>}
     */
    private function extractRecords(array $decoded): array
    {
        if (is_array($decoded['users'] ?? null)) {
            return $this->flattenNestedExport(array_values($decoded['users']));
        }

        if (array_is_list($decoded)) {
            $first = $decoded[0] ?? null;

            if (is_array($first) && ! array_key_exists('type', $first)) {
                return [$decoded, []];
            }

            foreach ($decoded as $entry) {
                if (is_array($entry) && ($entry['type'] ?? null) === 'table' && is_array($entry['data'] ?? null)) {
                    return [array_values($entry['data']), []];
                }
            }
        }

        return [array_values($decoded), []];
    }

    /**
     * Flatten this app's own nested export shape into flat attendance log
     * records plus deduplicated holiday-day candidates. A holiday day has
     * a single log with entry_type "holiday" and no real time in/out, so
     * it's routed to the holidays table instead of the attendance log.
     *
     * @param  list<mixed>  $users
     * @return array{0: list<array<string, mixed>>, 1: list<array{date: string, name: string, type: string, notes: ?string}>}
     */
    private function flattenNestedExport(array $users): array
    {
        $records = [];
        $holidays = [];

        foreach ($users as $user) {
            if (! is_array($user)) {
                continue;
            }

            $email = $user['email'] ?? null;
            $name = $user['name'] ?? null;
            $days = $user['attendance_days'] ?? [];

            if (! is_array($days)) {
                continue;
            }

            foreach ($days as $day) {
                if (! is_array($day)) {
                    continue;
                }

                if ($day['is_holiday'] ?? false) {
                    $date = $day['date'] ?? null;
                    if (is_string($date) && $date !== '') {
                        $holidays[$date] = [
                            'date' => $date,
                            'name' => is_string($day['holiday_name'] ?? null) ? $day['holiday_name'] : 'Holiday',
                            'type' => is_string($day['holiday_type_label'] ?? null) ? $day['holiday_type_label'] : 'regular',
                            'notes' => is_string($day['holiday_notes'] ?? null) ? $day['holiday_notes'] : null,
                        ];
                    }

                    continue;
                }

                $logs = $day['logs'] ?? [];
                if (! is_array($logs)) {
                    continue;
                }

                foreach ($logs as $log) {
                    if (! is_array($log)) {
                        continue;
                    }

                    $records[] = [
                        'email' => $email,
                        'name' => $name,
                        'entry_type' => $log['entry_type'] ?? null,
                        'recorded_at' => $log['recorded_at'] ?? null,
                    ];
                }
            }
        }

        return [$records, array_values($holidays)];
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  Collection<string, User>  $usersByEmail
     * @param  Collection<string, User>  $usersByName
     * @return array{user_id: int, recorded_at: Carbon, entry_type: AttendanceEntryType, source: string}
     */
    private function normalizeRecord(array $record, int $index, Collection $usersByEmail, Collection $usersByName): array
    {
        $row = $index + 1;
        $email = $record['email'] ?? $record['user_email'] ?? null;
        $name = $record['name'] ?? $record['user_name'] ?? null;

        $user = null;
        if (is_string($email) && $email !== '') {
            $user = $usersByEmail->get(strtolower(trim($email)));
        }
        if (! $user instanceof User && is_string($name) && $name !== '') {
            $user = $usersByName->get($this->normalizeNameForMatch($name));
        }
        if (! $user instanceof User) {
            throw new InvalidArgumentException("Row {$row}: no matching user for ".($name ?: $email ?: 'unknown').'.');
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

    /**
     * Normalizes a name for matching by dropping middle initials (a bare
     * letter, with or without a trailing period) so e.g. "Jonathan F.
     * Quiles" matches an existing user stored as "Jonathan Quiles".
     */
    private function normalizeNameForMatch(string $name): string
    {
        $tokens = preg_split('/\s+/', trim($name)) ?: [];
        $tokens = array_filter(
            $tokens,
            fn (string $token): bool => mb_strlen(rtrim($token, '.')) > 1,
        );

        return strtolower(implode(' ', $tokens));
    }

    /**
     * @param  list<array{date: string, name: string, type: string, notes: ?string}>  $candidates
     */
    private function importHolidays(array $candidates): int
    {
        $imported = 0;

        foreach ($candidates as $candidate) {
            try {
                $holidayDate = Carbon::parse($candidate['date']);
            } catch (Throwable) {
                continue;
            }

            // A plain `=` lookup on holiday_date is unreliable across
            // database engines/casts (a stored value can carry a
            // "00:00:00" time suffix even for a date-only column, e.g.
            // on SQLite), so match by date component instead of exact
            // string equality.
            $exists = Holiday::query()
                ->whereDate('holiday_date', $holidayDate->toDateString())
                ->where('country_code', 'PH')
                ->exists();

            if ($exists) {
                continue;
            }

            try {
                Holiday::query()->create([
                    'holiday_date' => $holidayDate->toDateString(),
                    'country_code' => 'PH',
                    'name' => $candidate['name'],
                    'type' => $candidate['type'],
                    'notes' => $candidate['notes'],
                ]);
                $imported++;
            } catch (Throwable) {
                // Another row for this date/country slipped in between the
                // existence check and the insert; treat it as already imported.
            }
        }

        return $imported;
    }
}
