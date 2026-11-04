<?php

namespace App\Services;

use App\EmploymentType;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Bulk-sets payroll details from a JSON file: a list of members, or an
 * object with a "members" list, each with monthly_salary, joined_on and
 * an optional employment_type. Members are matched by employee_code,
 * then email, then name (ignoring middle initials). Rows that can't be
 * matched or don't validate are skipped and reported.
 */
class PayrollSalaryImportService
{
    /**
     * @return array{total: int, updated: int, errors: list<string>}
     */
    public function import(UploadedFile $file): array
    {
        $decoded = json_decode((string) file_get_contents($file->getRealPath()), true);
        $rows = is_array($decoded) && is_array($decoded['members'] ?? null) ? $decoded['members'] : $decoded;

        if (! is_array($rows) || ! array_is_list($rows)) {
            return ['total' => 0, 'updated' => 0, 'errors' => ['The file must be a JSON list of members.']];
        }

        $users = User::query()->get(['id', 'name', 'email', 'employee_code', 'monthly_salary', 'employment_type', 'joined_on']);
        $usersByCode = $users->filter(fn (User $user): bool => $user->employee_code !== null)->keyBy(fn (User $user): string => strtolower(trim($user->employee_code)));
        $usersByEmail = $users->keyBy(fn (User $user): string => strtolower(trim($user->email)));
        $usersByName = $users->keyBy(fn (User $user): string => AttendanceImportService::normalizeNameForMatch($user->name));

        $updated = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 1;

            if (! is_array($row)) {
                $errors[] = "Row {$rowNumber}: not a valid member.";

                continue;
            }

            $user = $this->matchUser($row, $usersByCode, $usersByEmail, $usersByName);
            $label = $row['name'] ?? $row['employee_code'] ?? $row['email'] ?? 'unknown';

            if (! $user instanceof User) {
                $errors[] = "Row {$rowNumber}: no matching user for {$label}.";

                continue;
            }

            $validator = Validator::make($row, [
                'monthly_salary' => ['required', 'numeric', 'min:0', 'max:1000000'],
                'employment_type' => ['nullable', Rule::enum(EmploymentType::class)],
                'joined_on' => ['required', 'date'],
            ]);

            if ($validator->fails()) {
                $errors[] = "Row {$rowNumber} ({$label}): ".implode(' ', $validator->errors()->all());

                continue;
            }

            $validated = $validator->validated();

            $user->forceFill([
                'monthly_salary' => $validated['monthly_salary'],
                'employment_type' => $validated['employment_type'] ?? $user->employment_type,
                'joined_on' => $validated['joined_on'],
            ])->save();

            $updated++;
        }

        return ['total' => count($rows), 'updated' => $updated, 'errors' => $errors];
    }

    /**
     * @param  array<mixed>  $row
     * @param  Collection<string, User>  $usersByCode
     * @param  Collection<string, User>  $usersByEmail
     * @param  Collection<string, User>  $usersByName
     */
    private function matchUser(array $row, Collection $usersByCode, Collection $usersByEmail, Collection $usersByName): ?User
    {
        $code = $row['employee_code'] ?? null;
        $email = $row['email'] ?? null;
        $name = $row['name'] ?? null;

        return (is_string($code) && $code !== '' ? $usersByCode->get(strtolower(trim($code))) : null)
            ?? (is_string($email) && $email !== '' ? $usersByEmail->get(strtolower(trim($email))) : null)
            ?? (is_string($name) && $name !== '' ? $usersByName->get(AttendanceImportService::normalizeNameForMatch($name)) : null);
    }
}
