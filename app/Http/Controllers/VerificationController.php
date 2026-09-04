<?php

namespace App\Http\Controllers;

use App\Http\Requests\FilterVerificationRequest;
use App\Http\Requests\MarkPossibleLeadRequest;
use App\Http\Requests\StorePossibleLeadRequest;
use App\Http\Requests\VerifyLeadRequest;
use App\Models\AuditLog;
use App\Models\Lead;
use App\Models\LeadAttachment;
use App\Models\User;
use App\Services\CsvCellSanitizer;
use App\Services\LeadCreator;
use App\Services\LeadVerificationService;
use App\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VerificationController extends Controller
{
    public function index(FilterVerificationRequest $request): Response
    {
        $filters = $request->validated();
        $search = trim((string) ($filters['search'] ?? ''));
        $query = $this->searchQuery($search)
            ->with('agent:id,name')
            ->withCount(['structuredNotes', 'attachments'])
            ->latest();
        if ($status = $filters['status'] ?? null) {
            $query->where('status', $status);
        } else {
            $query->whereIn('status', ['raw', 'validated', 'needs_review', 'possible_lead']);
        }

        $summary = [
            'review_queue' => (clone $this->searchQuery($search))->whereIn('status', ['raw', 'validated', 'needs_review', 'possible_lead'])->count(),
            'possible_leads' => (clone $this->searchQuery($search))->where('status', 'possible_lead')->count(),
            'qualified_leads' => (clone $this->searchQuery($search))->where('status', 'qualified_lead')->count(),
            'documents' => LeadAttachment::query()->whereIn('lead_id', $this->searchQuery($search)->where('status', 'possible_lead')->select('id'))->count(),
        ];

        return Inertia::render('verification/index', [
            'leads' => $query->paginate(20)->withQueryString(),
            'filters' => ['status' => $status ?? '', 'search' => $search],
            'summary' => $summary,
        ]);
    }

    public function createPossible(Request $request): Response
    {
        abort_unless($request->user()->canViewAllLeads(), 403);

        return Inertia::render('verification/possible-create', [
            'agents' => User::query()->where('role', UserRole::Agent)->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'defaults' => ['lead_date' => today()->toDateString(), 'data_source' => 'Manual'],
        ]);
    }

    public function storePossible(StorePossibleLeadRequest $request, LeadCreator $creator, LeadVerificationService $verification): RedirectResponse
    {
        $data = $request->validated();
        $owner = User::query()->whereKey($data['agent_id'])->firstOrFail();
        $lead = $creator->create($data, $owner, $request->user());
        $verification->verify($lead, [
            ...$lead->only(['company_name', 'website', 'city', 'country', 'country_code', 'timezone', 'contact_person', 'position', 'email', 'phone', 'industry']),
            'status' => 'possible_lead',
            'remarks' => 'Added directly to Possible Leads by '.$request->user()->name.'.',
        ], $request->user());

        return redirect()->route('verification.show', $lead)->with('toast', ['type' => 'success', 'message' => 'Possible lead added successfully.']);
    }

    public function show(Request $request, Lead $lead): Response
    {
        abort_unless($request->user()->canViewAllLeads(), 403);
        Gate::authorize('view', $lead);
        $lead->load(['agent:id,name', 'uploadBatch:id,batch_code', 'structuredNotes.user:id,name', 'statusHistory.changer:id,name', 'forwardings.forwarder:id,name', 'attachments.uploader:id,name']);

        return Inertia::render('verification/show', ['lead' => $lead, 'previousId' => Lead::query()->where('id', '<', $lead->id)->max('id'), 'nextId' => Lead::query()->where('id', '>', $lead->id)->min('id'), 'reviewers' => User::query()->whereIn('role', [UserRole::SuperAdministrator, UserRole::Administrator, UserRole::SubAdministrator])->where('status', 'active')->get(['id', 'name'])]);
    }

    public function update(VerifyLeadRequest $request, Lead $lead, LeadVerificationService $service): RedirectResponse
    {
        $data = $request->validated();
        unset($data['intent']);
        $service->verify($lead, $data, $request->user());
        if ($request->validated('intent') === 'save_next') {
            $nextId = Lead::query()->where('id', '>', $lead->id)->min('id');
            if ($nextId) {
                return redirect()->route('verification.show', $nextId)->with('toast', ['type' => 'success', 'message' => 'Lead verified.']);
            }
        }

        return back()->with('toast', ['type' => 'success', 'message' => 'Lead verification saved.']);
    }

    public function markPossible(MarkPossibleLeadRequest $request, Lead $lead, LeadVerificationService $service): RedirectResponse
    {
        $service->verify($lead, [
            ...$lead->only(['company_name', 'website', 'city', 'country', 'country_code', 'timezone', 'contact_person', 'position', 'email', 'phone', 'industry']),
            'status' => 'possible_lead',
            'remarks' => $request->validated('remarks') ?? 'Marked as a possible lead from the verification queue.',
        ], $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => 'Contact saved to Possible Leads.']);
    }

    public function exportPossible(FilterVerificationRequest $request, CsvCellSanitizer $csv): StreamedResponse
    {
        $search = trim((string) ($request->validated('search') ?? ''));
        $query = $this->searchQuery($search)
            ->where('status', 'possible_lead')
            ->with('agent:id,name')
            ->withCount('attachments')
            ->orderBy('company_name')
            ->orderBy('id');

        AuditLog::query()->create([
            'user_id' => $request->user()->id,
            'action' => 'possible_leads.exported',
            'auditable_type' => 'lead',
            'description' => 'Downloaded the Possible Leads contact list.',
            'metadata' => ['search' => $search],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->streamDownload(function () use ($query, $csv): void {
            $stream = fopen('php://output', 'wb');
            if (! is_resource($stream)) {
                return;
            }
            fputcsv($stream, ['Lead Code', 'Date', 'Company', 'Contact Person', 'Position', 'Email', 'Phone', 'Website', 'City', 'Country', 'Industry', 'Owner', 'Documents'], escape: '');
            foreach ($query->cursor() as $lead) {
                fputcsv($stream, $csv->sanitizeRow([$lead->lead_code, $lead->lead_date?->format('m/d/Y'), $lead->company_name, $lead->contact_person, $lead->position, $lead->email, $lead->phone, $lead->website, $lead->city, $lead->country_code ?: $lead->country, $lead->industry, $lead->agent?->name, $lead->attachments_count]), escape: '');
            }
            fclose($stream);
        }, 'Possible-Leads-'.today()->format('m-d-Y').'.csv', ['Content-Type' => 'text/csv']);
    }

    /** @return Builder<Lead> */
    private function searchQuery(string $search): Builder
    {
        return $this->applySearch(Lead::query(), $search);
    }

    /**
     * @param  Builder<Lead>  $query
     * @return Builder<Lead>
     */
    private function applySearch(Builder $query, string $search): Builder
    {
        return $query->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
            $query->where('lead_code', 'like', "%{$search}%")
                ->orWhere('company_name', 'like', "%{$search}%")
                ->orWhere('contact_person', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('website', 'like', "%{$search}%")
                ->orWhere('website_domain', 'like', "%{$search}%")
                ->orWhere('city', 'like', "%{$search}%")
                ->orWhere('country', 'like', "%{$search}%")
                ->orWhereHas('agent', fn (Builder $agent) => $agent->where('name', 'like', "%{$search}%"));
        }));
    }
}
