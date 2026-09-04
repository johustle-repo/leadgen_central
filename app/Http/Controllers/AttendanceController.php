<?php

namespace App\Http\Controllers;

use App\AttendanceEntryType;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\User;
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
    public function index(): Response
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

        $records = Attendance::query()
            ->with('user:id,name')
            ->orderByDesc('recorded_at')
            ->limit(100)
            ->get()
            ->map(fn (Attendance $attendance): array => [
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

        return Inertia::render('attendance/index', [
            'users' => $users,
            'records' => $records,
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
