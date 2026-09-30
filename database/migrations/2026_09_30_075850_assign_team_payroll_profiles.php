<?php

use App\Models\User;
use App\Services\AttendanceImportService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;

return new class extends Migration
{
    /**
     * Employee code, sub name and email from the team's August 2026
     * attendance workbook.
     *
     * @var array<string, array{employee_code: string, alias_name: string, alias_email: string}>
     */
    private const PROFILES = [
        'Elmar B. Noche' => ['employee_code' => 'DUS-001', 'alias_name' => 'Alexander Bennett', 'alias_email' => 'a.bennett@duscaff.com'],
        'Jonathan F. Quiles' => ['employee_code' => 'DUS-002', 'alias_name' => 'James Whitaker', 'alias_email' => 'j.whitaker@duscaff.com'],
        'Dexter L. Javelosa' => ['employee_code' => 'DUS-003', 'alias_name' => 'Daniel Hoffman', 'alias_email' => 'd.hoffman@duscaff.com'],
        'Rosielyn F. Laron' => ['employee_code' => 'DUS-004', 'alias_name' => 'Isabella Rossi', 'alias_email' => 'i.rossi@duscaff.com'],
        'Maria Lorena Sheen P. Velasco' => ['employee_code' => 'DUS-005', 'alias_name' => 'Charlotte Fischer', 'alias_email' => 'c.fischer@duscaff.com'],
        'Fiona Ley Maramba' => ['employee_code' => 'DUS-006', 'alias_name' => 'Amelia Dubois', 'alias_email' => 'a.dubois@duscaff.com'],
        'Haryll L. Caido' => ['employee_code' => 'DUS-008', 'alias_name' => 'Emily Carter', 'alias_email' => 'e.carter@duscaff.com'],
    ];

    /**
     * Gives each team member their workbook employee code, sub name and
     * email, matching names the same way attendance imports do. A code
     * another account already holds is left alone so the unique index
     * never trips; the sub name and email are still applied.
     */
    public function up(): void
    {
        $usersByName = $this->usersByName();

        foreach (self::PROFILES as $name => $profile) {
            $user = $usersByName->get(AttendanceImportService::normalizeNameForMatch($name));

            if (! $user instanceof User) {
                continue;
            }

            $codeTaken = User::withTrashed()->where('employee_code', $profile['employee_code'])->whereKeyNot($user->id)->exists();

            $user->forceFill($codeTaken ? array_diff_key($profile, ['employee_code' => true]) : $profile)->saveQuietly();
        }
    }

    /**
     * Clears the codes this migration assigned. Sub names and emails are
     * kept since earlier values are not recorded.
     */
    public function down(): void
    {
        User::withTrashed()->whereIn('employee_code', array_column(self::PROFILES, 'employee_code'))->update(['employee_code' => null]);
    }

    /**
     * @return Collection<string, User>
     */
    private function usersByName(): Collection
    {
        return User::query()->get(['id', 'name', 'employee_code', 'alias_name', 'alias_email'])
            ->keyBy(fn (User $user): string => AttendanceImportService::normalizeNameForMatch($user->name));
    }
};
