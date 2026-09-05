<?php

namespace App\Http\Controllers;

use App\AttendanceEntryType;
use App\Exports\AttendanceBackupExport;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AttendanceDaySummaryService;
use App\Services\AttendanceImportService;
use App\Services\AttendanceScanService;
use App\Services\HolidayService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttendanceController extends Controller
{
    public function index(Request $request, AttendanceDaySummaryService $summaryService): Response
    {
        Gate::authorize('manage-attendance');

        $users = User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'role', 'team', 'status', 'qr_token'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
                'team' => $user->team,
                'status' => $user->status,
                'qr_value' => $user->qr_value,
            ]);

        $query = Attendance::query()->with('user:id,name');
        if ($search = $request->string('search')->trim()->toString()) {
            $query->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%"));
        }
        if ($entryType = $request->string('entry_type')->toString()) {
            $query->where('entry_type', $entryType);
        }
        if ($date = $request->string('date')->toString()) {
            $query->whereDate('recorded_at', $date);
        }

        $records = $query->orderByDesc('recorded_at')->paginate(20)->withQueryString();
        $records->through(fn (Attendance $attendance): array => [
            'id' => $attendance->id,
            'user_id' => $attendance->user_id,
            'user_name' => $attendance->user?->name,
            'entry_type' => $attendance->entry_type,
            'recorded_at' => $attendance->recorded_at->toIso8601String(),
            ...(
                $attendance->entry_type === AttendanceEntryType::TimeIn
                    ? Attendance::lateStatusFor($attendance->recorded_at)
                    : ['status' => null, 'late_minutes' => 0]
            ),
        ]);

        $today = now()->startOfDay();
        $summary = [
            'total_records' => Attendance::query()->count(),
            'time_ins_today' => Attendance::query()->where('entry_type', AttendanceEntryType::TimeIn)->whereDate('recorded_at', $today)->count(),
            'late_today' => Attendance::query()
                ->where('entry_type', AttendanceEntryType::TimeIn)
                ->whereDate('recorded_at', $today)
                ->get()
                ->filter(fn (Attendance $attendance): bool => Attendance::lateStatusFor($attendance->recorded_at)['status'] === 'late')
                ->count(),
            'active_staff' => User::query()->where('status', 'active')->count(),
        ];

        $monthStart = $this->resolveMonthStart($request);
        $calendarMonthEnd = $monthStart->copy()->endOfMonth();
        $endOfToday = now()->endOfDay();
        $monthEnd = $calendarMonthEnd->greaterThan($endOfToday) ? $endOfToday : $calendarMonthEnd;
        $activeUsers = User::query()->where('status', 'active')->orderBy('name')->get();

        $monthlyAttendance = array_map(fn (array $period): array => [
            'user_id' => $period['user']->id,
            'user_name' => $period['user']->name,
            'role_label' => $period['user']->role->label(),
            'days' => array_map(fn (array $day): array => [
                'date' => $day['date']->toDateString(),
                'time_in' => $day['time_in']?->toIso8601String(),
                'time_out' => $day['time_out']?->toIso8601String(),
                'worked_minutes' => $day['worked_minutes'],
                'worked_minutes_label' => Attendance::formatMinutes($day['worked_minutes']),
                'status' => $day['status'],
                'holiday_label' => $day['holiday_label'],
            ], $period['days']),
        ], $summaryService->buildForPeriod($monthStart, $monthEnd, $activeUsers));

        return Inertia::render('attendance/index', [
            'users' => $users,
            'records' => $records,
            'summary' => $summary,
            'monthlyAttendance' => $monthlyAttendance,
            'selectedMonth' => $monthStart->format('Y-m'),
            'filters' => $request->only(['search', 'entry_type', 'date']),
        ]);
    }

    private function resolveMonthStart(Request $request): CarbonInterface
    {
        $month = $request->string('month')->toString();

        return $month !== '' ? Carbon::parse($month)->startOfMonth() : now()->startOfMonth();
    }

    public function scan(Request $request, AttendanceScanService $service): RedirectResponse
    {
        Gate::authorize('manage-attendance');

        $validated = $request->validate([
            'code' => ['required', 'string'],
            'entry_type' => ['required', 'string', 'in:time_in,time_out'],
        ]);

        $performedBy = $request->user();
        abort_unless($performedBy instanceof User, 401);

        $attendance = $service->record(
            $validated['code'],
            AttendanceEntryType::from($validated['entry_type']),
            $performedBy,
        );

        $attendance->loadMissing('user:id,name');

        return back()->with('toast', [
            'type' => 'success',
            'message' => "{$attendance->entry_type->label()} recorded for {$attendance->user?->name}.",
        ]);
    }

    public function importJson(Request $request, AttendanceImportService $service): RedirectResponse
    {
        Gate::authorize('manage-attendance');
        $performedBy = $request->user();
        abort_unless($performedBy instanceof User, 401);

        $validated = $request->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'mimes:json', 'max:10240'],
        ]);

        $total = 0;
        $imported = 0;
        $duplicates = 0;
        $holidaysImported = 0;
        $errors = [];

        foreach ($validated['files'] as $file) {
            $result = $service->import($file);
            $total += $result->total;
            $imported += $result->imported;
            $duplicates += $result->duplicates;
            $holidaysImported += $result->holidaysImported;
            foreach ($result->errors as $error) {
                $errors[] = count($validated['files']) > 1 ? "{$file->getClientOriginalName()} — {$error}" : $error;
            }
        }

        AuditLog::query()->create([
            'user_id' => $performedBy->id,
            'action' => 'attendance.imported',
            'auditable_type' => 'attendance',
            'auditable_id' => null,
            'description' => "Imported {$imported} attendance record(s) and {$holidaysImported} holiday(s) from ".count($validated['files']).' file(s).',
            'metadata' => ['imported' => $imported, 'duplicates' => $duplicates, 'holidays_imported' => $holidaysImported, 'skipped' => count($errors), 'total' => $total, 'files' => count($validated['files'])],
        ]);

        if ($total === 0) {
            return back()->with('toast', ['type' => 'error', 'message' => 'Those file(s) had no readable records.']);
        }

        $messageParts = ["Imported {$imported} attendance record(s)"];
        if ($duplicates > 0) {
            $messageParts[] = "{$duplicates} already existed";
        }
        if ($holidaysImported > 0) {
            $messageParts[] = "{$holidaysImported} holiday(s) added";
        }
        if ($errors !== []) {
            $messageParts[] = count($errors).' row(s) skipped';
        }

        return back()
            ->with('toast', ['type' => $errors === [] ? 'success' : 'warning', 'message' => implode('; ', $messageParts).'.'])
            ->with('importErrors', array_slice($errors, 0, 30));
    }

    public function exportPdf(Request $request, AttendanceDaySummaryService $summaryService, HolidayService $holidayService): HttpResponse
    {
        Gate::authorize('manage-attendance');

        $monthStart = $this->resolveMonthStart($request);
        $monthEnd = $monthStart->copy()->endOfMonth();

        $attendances = Attendance::query()
            ->with('user:id,name,role')
            ->whereBetween('recorded_at', [$monthStart, $monthEnd])
            ->orderByDesc('recorded_at')
            ->limit(500)
            ->get();

        $holidaysByDate = $holidayService->forDates(
            $attendances->map(fn (Attendance $attendance): string => $attendance->recorded_at->toDateString())->unique()->values()->all(),
        );

        $records = $attendances->map(function (Attendance $attendance) use ($summaryService, $holidaysByDate): array {
            $holiday = $holidaysByDate[$attendance->recorded_at->toDateString()] ?? null;

            $totalHoursLabel = null;
            if ($attendance->entry_type === AttendanceEntryType::TimeOut && $attendance->user instanceof User) {
                $day = $summaryService->buildForUserAndDate($attendance->user, $attendance->recorded_at, $holiday);
                $totalHoursLabel = Attendance::formatMinutes($day['worked_minutes']);
            }

            $statusLabel = $holiday !== null
                ? $holiday->name
                : ($attendance->entry_type === AttendanceEntryType::TimeIn
                    ? ucfirst(str_replace('_', ' ', Attendance::lateStatusFor($attendance->recorded_at)['status']))
                    : null);

            return [
                'user_name' => $attendance->user?->name,
                'role_label' => $attendance->user?->role?->label(),
                'entry_label' => $attendance->entry_type->label(),
                'recorded_at' => $attendance->recorded_at->format('Y-m-d H:i'),
                'status_label' => $statusLabel,
                'total_hours_label' => $totalHoursLabel,
            ];
        });

        $performedBy = $request->user();
        abort_unless($performedBy instanceof User, 401);

        AuditLog::query()->create([
            'user_id' => $performedBy->id,
            'action' => 'attendance.exported_pdf',
            'auditable_type' => 'attendance',
            'auditable_id' => null,
            'description' => 'Downloaded an attendance PDF export.',
            'metadata' => ['record_count' => $records->count()],
        ]);

        return Pdf::loadView('attendance.export', ['records' => $records])
            ->download('Attendance-Report-'.now()->format('Y-m-d').'.pdf');
    }

    public function exportExcel(Request $request): BinaryFileResponse
    {
        Gate::authorize('manage-attendance');

        $period = $this->resolveMonthStart($request);

        $performedBy = $request->user();
        abort_unless($performedBy instanceof User, 401);

        AuditLog::query()->create([
            'user_id' => $performedBy->id,
            'action' => 'attendance.exported_excel',
            'auditable_type' => 'attendance',
            'auditable_id' => null,
            'description' => 'Downloaded an attendance Excel export.',
            'metadata' => ['period' => $period->toDateString()],
        ]);

        return Excel::download(
            new AttendanceBackupExport($period->copy()->startOfMonth(), $period->copy()->endOfMonth()),
            'Attendance_'.$period->format('F_Y').'.xlsx',
        );
    }

    /**
     * Super-admin correction of a single Time In/Out slot for one user on
     * one day: creates it if missing, updates it if present, or deletes it
     * when `recorded_at` is submitted empty. Keyed by (user, date,
     * entry type) rather than an attendance ID so the UI can offer an
     * editable cell even for a day with no record yet.
     */
    public function updateEntry(Request $request, User $user, string $date, string $entryType): RedirectResponse
    {
        Gate::authorize('manage-attendance');

        $validated = $request->validate([
            'recorded_at' => ['nullable', 'date'],
        ]);

        $entryTypeEnum = AttendanceEntryType::from($entryType);
        $day = Carbon::parse($date)->startOfDay();

        $performedBy = $request->user();
        abort_unless($performedBy instanceof User, 401);

        $existing = Attendance::query()
            ->where('user_id', $user->id)
            ->where('entry_type', $entryTypeEnum)
            ->whereBetween('recorded_at', [$day, $day->copy()->endOfDay()])
            ->first();

        if ($validated['recorded_at'] === null) {
            $existing?->delete();

            AuditLog::query()->create([
                'user_id' => $performedBy->id,
                'action' => 'attendance.manual_edit',
                'auditable_type' => 'attendance',
                'auditable_id' => null,
                'description' => "Cleared {$entryTypeEnum->label()} for {$user->name} on {$day->toDateString()}.",
                'metadata' => ['target_user_id' => $user->id, 'date' => $day->toDateString(), 'entry_type' => $entryTypeEnum->value, 'action' => 'cleared'],
            ]);

            return back()->with('toast', ['type' => 'success', 'message' => "{$entryTypeEnum->label()} cleared for {$user->name}."]);
        }

        $recordedAt = Carbon::parse($validated['recorded_at']);

        if (! $recordedAt->isSameDay($day)) {
            throw ValidationException::withMessages(['recorded_at' => "The time must fall on {$day->toDateString()}."]);
        }

        $counterpartType = $entryTypeEnum === AttendanceEntryType::TimeIn ? AttendanceEntryType::TimeOut : AttendanceEntryType::TimeIn;
        $counterpart = Attendance::query()
            ->where('user_id', $user->id)
            ->where('entry_type', $counterpartType)
            ->whereBetween('recorded_at', [$day, $day->copy()->endOfDay()])
            ->first();

        if ($entryTypeEnum === AttendanceEntryType::TimeOut) {
            if (! $counterpart instanceof Attendance) {
                throw ValidationException::withMessages(['recorded_at' => "{$user->name} must have a Time In before a Time Out."]);
            }
            if ($recordedAt->lessThanOrEqualTo($counterpart->recorded_at)) {
                throw ValidationException::withMessages(['recorded_at' => 'Time Out must be after Time In.']);
            }
        } elseif ($counterpart instanceof Attendance && $recordedAt->greaterThanOrEqualTo($counterpart->recorded_at)) {
            throw ValidationException::withMessages(['recorded_at' => 'Time In must be before Time Out.']);
        }

        if ($existing instanceof Attendance) {
            $existing->update(['recorded_at' => $recordedAt, 'source' => 'manual_adjustment']);
        } else {
            Attendance::query()->create([
                'user_id' => $user->id,
                'recorded_at' => $recordedAt,
                'entry_type' => $entryTypeEnum,
                'source' => 'manual_adjustment',
            ]);
        }

        AuditLog::query()->create([
            'user_id' => $performedBy->id,
            'action' => 'attendance.manual_edit',
            'auditable_type' => 'attendance',
            'auditable_id' => null,
            'description' => "Set {$entryTypeEnum->label()} for {$user->name} on {$day->toDateString()} to {$recordedAt->format('H:i')}.",
            'metadata' => ['target_user_id' => $user->id, 'date' => $day->toDateString(), 'entry_type' => $entryTypeEnum->value, 'action' => $existing instanceof Attendance ? 'updated' : 'created'],
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => "{$entryTypeEnum->label()} saved for {$user->name}."]);
    }
}
