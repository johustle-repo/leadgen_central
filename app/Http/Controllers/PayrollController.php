<?php

namespace App\Http\Controllers;

use App\EmploymentType;
use App\Exports\AttendanceSummarySheet;
use App\Http\Requests\UpdatePayrollAdjustmentRequest;
use App\Http\Requests\UpdatePayrollMemberRequest;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\PayrollAdjustment;
use App\Models\User;
use App\Services\PayrollCalculator;
use App\Services\PayrollSalaryImportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PayrollController extends Controller
{
    public function index(Request $request, PayrollCalculator $calculator): Response
    {
        Gate::authorize('manage-payroll');

        return Inertia::render('payroll/index', [
            'payroll' => $calculator->forMonth($this->resolveMonthStart($request)),
            'employmentTypes' => array_map(fn (EmploymentType $type): array => ['value' => $type->value, 'label' => $type->label()], EmploymentType::cases()),
        ]);
    }

    public function updateMember(UpdatePayrollMemberRequest $request, User $user): RedirectResponse
    {
        $previous = $user->only(['monthly_salary', 'employment_type', 'joined_on']);

        $user->forceFill($request->validated())->save();

        AuditLog::query()->create([
            'user_id' => $request->user()->id,
            'action' => 'payroll.member_updated',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'description' => "Updated payroll details for {$user->name}.",
            'metadata' => [
                'previous' => [
                    'monthly_salary' => $previous['monthly_salary'],
                    'employment_type' => $previous['employment_type']->value,
                    'joined_on' => $previous['joined_on']?->toDateString(),
                ],
                'current' => $request->validated(),
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => "Payroll details saved for {$user->name}."]);
    }

    /**
     * Sets or clears one member's category and paid days for one month.
     */
    public function updateAdjustment(UpdatePayrollAdjustmentRequest $request, string $month, User $user): RedirectResponse
    {
        $monthStart = Carbon::createFromFormat('Y-m-d', "{$month}-01")->startOfDay();
        $employmentType = $request->validated('employment_type');

        PayrollAdjustment::setFor(
            $user,
            $monthStart,
            $employmentType === null ? null : EmploymentType::from($employmentType),
            $request->validated('paid_days'),
        );

        AuditLog::query()->create([
            'user_id' => $request->user()->id,
            'action' => 'payroll.adjustment_updated',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'description' => "Updated {$user->name}'s payroll for ".$monthStart->format('F Y').'.',
            'metadata' => ['month' => $monthStart->toDateString(), ...$request->validated()],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => "Saved {$user->name}'s ".$monthStart->format('F Y').' payroll.']);
    }

    /**
     * Sets salary, category and join date for many members at once from
     * an uploaded JSON file.
     */
    public function importSalaries(Request $request, PayrollSalaryImportService $service): RedirectResponse
    {
        Gate::authorize('manage-payroll');

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:json', 'max:1024'],
        ]);

        $result = $service->import($validated['file']);

        AuditLog::query()->create([
            'user_id' => $request->user()->id,
            'action' => 'payroll.salaries_imported',
            'auditable_type' => 'payroll',
            'auditable_id' => null,
            'description' => "Imported payroll details for {$result['updated']} member(s).",
            'metadata' => ['total' => $result['total'], 'updated' => $result['updated'], 'skipped' => count($result['errors'])],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()
            ->with('toast', [
                'type' => $result['errors'] === [] ? 'success' : 'warning',
                'message' => "Updated {$result['updated']} of {$result['total']} member(s)".($result['errors'] === [] ? '.' : '; '.count($result['errors']).' skipped.'),
            ])
            ->with('importErrors', array_slice($result['errors'], 0, 30));
    }

    /**
     * The payroll page as a printable PDF: salary per hour, per day and
     * per month for each member, with the total salary.
     */
    public function exportPdf(Request $request, PayrollCalculator $calculator): HttpResponse
    {
        Gate::authorize('manage-payroll');

        $monthStart = $this->resolveMonthStart($request);
        $payroll = $calculator->forMonth($monthStart);

        AuditLog::query()->create([
            'user_id' => $request->user()->id,
            'action' => 'payroll.exported_pdf',
            'auditable_type' => 'payroll',
            'auditable_id' => null,
            'description' => 'Downloaded a payroll PDF export.',
            'metadata' => ['period' => $monthStart->toDateString(), 'member_count' => count($payroll['members'])],
        ]);

        return Pdf::loadView('payroll.export', [
            'payroll' => $payroll,
            'headerRows' => [
                [AttendanceSummarySheet::APPROVER_NAME."'s Team PH Payroll - Month of ".$monthStart->format('F Y')],
                ['Period', $monthStart->format('F Y')],
                ['Generated at', Attendance::now()->format('M j, Y g:i A')],
            ],
            'paidHoursPerDay' => PayrollCalculator::PAID_HOURS_PER_DAY,
            'approverName' => AttendanceSummarySheet::APPROVER_NAME,
            'approverPosition' => AttendanceSummarySheet::APPROVER_POSITION,
            'signatureDataUri' => 'data:image/png;base64,'.base64_encode((string) file_get_contents(resource_path(AttendanceSummarySheet::SIGNATURE_PATH))),
            'logoDataUri' => 'data:image/png;base64,'.base64_encode((string) file_get_contents(resource_path('images/attendance-report-logo.png'))),
        ])
            ->setPaper('a4', 'landscape')
            ->download('Payroll_'.$monthStart->format('F_Y').'.pdf');
    }

    private function resolveMonthStart(Request $request): CarbonInterface
    {
        $month = $request->string('month')->toString();

        return preg_match('/^\d{4}-\d{2}$/', $month) === 1
            ? Carbon::createFromFormat('Y-m-d', "{$month}-01")->startOfDay()
            : Attendance::now()->startOfMonth();
    }
}
