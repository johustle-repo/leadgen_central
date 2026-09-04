<?php

namespace App\Http\Controllers;

use App\AttendanceEntryType;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AttendanceImportService;
use App\Services\AttendanceScanService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function index(Request $request): Response
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

        return Inertia::render('attendance/index', [
            'users' => $users,
            'records' => $records,
            'summary' => $summary,
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
            'file' => ['required', 'file', 'mimes:json', 'max:10240'],
        ]);

        $result = $service->import($validated['file']);

        AuditLog::query()->create([
            'user_id' => $performedBy->id,
            'action' => 'attendance.imported',
            'auditable_type' => 'attendance',
            'auditable_id' => null,
            'description' => "Imported {$result->imported} attendance record(s) from JSON.",
            'metadata' => ['imported' => $result->imported, 'skipped' => count($result->errors), 'total' => $result->total],
        ]);

        if ($result->total === 0) {
            return back()->with('toast', ['type' => 'error', 'message' => 'That file had no readable records.']);
        }

        $message = $result->errors === []
            ? "Imported {$result->imported} attendance record(s)."
            : "Imported {$result->imported} of {$result->total} record(s); ".count($result->errors).' skipped.';

        return back()
            ->with('toast', ['type' => $result->errors === [] ? 'success' : 'warning', 'message' => $message])
            ->with('importErrors', array_slice($result->errors, 0, 30));
    }

    public function exportPdf(Request $request): HttpResponse
    {
        Gate::authorize('manage-attendance');

        $records = Attendance::query()
            ->with('user:id,name,role')
            ->orderByDesc('recorded_at')
            ->limit(500)
            ->get()
            ->map(fn (Attendance $attendance): array => [
                'user_name' => $attendance->user?->name,
                'role_label' => $attendance->user?->role?->label(),
                'entry_label' => $attendance->entry_type->label(),
                'recorded_at' => $attendance->recorded_at->format('Y-m-d H:i'),
                'status_label' => $attendance->entry_type === AttendanceEntryType::TimeIn
                    ? ucfirst(str_replace('_', ' ', Attendance::lateStatusFor($attendance->recorded_at)['status']))
                    : null,
            ]);

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
}
