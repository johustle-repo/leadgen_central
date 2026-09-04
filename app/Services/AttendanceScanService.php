<?php

namespace App\Services;

use App\AccountStatus;
use App\AttendanceEntryType;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class AttendanceScanService
{
    private const QR_PREFIX = 'attendance:';

    private const DUPLICATE_SCAN_WINDOW_MINUTES = 2;

    /**
     * Resolve a scanned QR value to a user and record the requested entry type,
     * enforcing time in/out sequencing for the current day.
     */
    public function record(string $scannedValue, AttendanceEntryType $entryType, User $performedBy): Attendance
    {
        $user = $this->resolveUser($scannedValue);

        $now = Carbon::now();

        $this->assertNoRecentDuplicate($user, $entryType, $now);
        $this->assertSequenceIsValid($user, $entryType, $now);

        $attendance = Attendance::query()->create([
            'user_id' => $user->id,
            'recorded_at' => $now,
            'entry_type' => $entryType,
            'source' => 'qr_scan',
        ]);

        AuditLog::query()->create([
            'user_id' => $performedBy->id,
            'action' => 'attendance.scan',
            'auditable_type' => 'attendance',
            'auditable_id' => $attendance->id,
            'description' => "Recorded {$entryType->label()} for {$user->name}.",
            'metadata' => ['target_user_id' => $user->id, 'entry_type' => $entryType->value],
        ]);

        return $attendance;
    }

    private function resolveUser(string $scannedValue): User
    {
        $token = str_starts_with($scannedValue, self::QR_PREFIX)
            ? substr($scannedValue, strlen(self::QR_PREFIX))
            : $scannedValue;

        $user = User::query()->where('qr_token', $token)->first();

        if (! $user instanceof User) {
            throw ValidationException::withMessages(['code' => 'QR code not recognized.']);
        }

        if ($user->status !== AccountStatus::Active) {
            throw ValidationException::withMessages(['code' => "{$user->name} is inactive and cannot be recorded."]);
        }

        return $user;
    }

    private function assertNoRecentDuplicate(User $user, AttendanceEntryType $entryType, Carbon $now): void
    {
        $recentDuplicate = Attendance::query()
            ->where('user_id', $user->id)
            ->where('entry_type', $entryType)
            ->where('recorded_at', '>=', $now->copy()->subMinutes(self::DUPLICATE_SCAN_WINDOW_MINUTES))
            ->exists();

        if ($recentDuplicate) {
            throw ValidationException::withMessages(['code' => "{$user->name} was already scanned moments ago."]);
        }
    }

    private function assertSequenceIsValid(User $user, AttendanceEntryType $entryType, Carbon $now): void
    {
        $today = $now->copy()->startOfDay();

        $lastEntry = Attendance::query()
            ->where('user_id', $user->id)
            ->whereBetween('recorded_at', [$today, $today->copy()->endOfDay()])
            ->orderByDesc('recorded_at')
            ->first();

        if ($entryType === AttendanceEntryType::TimeIn) {
            if ($lastEntry?->entry_type === AttendanceEntryType::TimeIn) {
                throw ValidationException::withMessages(['code' => "{$user->name} is already timed in."]);
            }

            if ($lastEntry?->entry_type === AttendanceEntryType::TimeOut) {
                throw ValidationException::withMessages(['code' => "{$user->name} already completed today's attendance cycle."]);
            }

            return;
        }

        if ($lastEntry === null || $lastEntry->entry_type !== AttendanceEntryType::TimeIn) {
            throw ValidationException::withMessages(['code' => "{$user->name} must time in before timing out."]);
        }
    }
}
