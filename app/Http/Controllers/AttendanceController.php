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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
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

        $summaryDate = $date ? Carbon::parse($date) : now();
        $activeUsers = User::query()->where('status', 'active')->orderBy('name')->get();
        $dailySummary = array_map(fn (array $row): array => [
            'user_id' => $row['user_id'],
            'user_name' => $row['user_name'],
            'time_in' => $row['time_in']?->toIso8601String(),
            'time_out' => $row['time_out']?->toIso8601String(),
            'worked_minutes_label' => Attendance::formatMinutes($row['worked_minutes']),
            'status' => $row['status'],
            'holiday_label' => $row['holiday_label'],
        ], $summaryService->buildForDate($summaryDate, $activeUsers));

        return Inertia::render('attendance/index', [
            'users' => $users,
            'records' => $records,
            'summary' => $summary,
            'dailySummary' => $dailySummary,
            'dailySummaryDate' => $summaryDate->toDateString(),
            'filters' => $request->only(['search', 'entry_type', 'date']),
        ]);
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

        $attendances = Attendance::query()
            ->with('user:id,name,role')
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

        $month = $request->string('month')->toString();
        $period = $month !== '' ? Carbon::parse($month)->startOfMonth() : now()->startOfMonth();

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
}
