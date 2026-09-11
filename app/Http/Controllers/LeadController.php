<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkDeleteLeadsRequest;
use App\Http\Requests\DownloadRawLeadsRequest;
use App\Http\Requests\StoreLeadRequest;
use App\Http\Requests\UpdateLeadRequest;
use App\Models\AuditLog;
use App\Models\Lead;
use App\Models\UploadBatch;
use App\Models\User;
use App\Services\CsvCellSanitizer;
use App\Services\LeadBulkDeletion;
use App\Services\LeadCreator;
use App\Services\LeadNormalizationService;
use App\Services\TimezoneReferenceResolver;
use App\UserRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeadController extends Controller
{
    public function companyContactCount(Request $request, LeadNormalizationService $normalizer): JsonResponse
    {
        Gate::authorize('viewAny', Lead::class);
        $company = $normalizer->normalize(['company_name' => $request->string('company_name')->toString()]);
        $agentId = $request->user()->canViewAllLeads()
            ? $request->integer('agent_id', $request->user()->id)
            : $request->user()->id;

        return response()->json(['count' => $company['normalized_company_name'] === '' ? 0 : Lead::query()
            ->where('agent_id', $agentId)
            ->where('normalized_company_name', $company['normalized_company_name'])
            ->count()]);
    }

    /** @return array{company: string, agentId: string, count: int} */
    private function formCompanyContactCount(Request $request, string $company, ?int $agentId): array
    {
        $company = $request->string('company_name', $company)->toString();
        $agentId = $request->user()->canViewAllLeads()
            ? $request->integer('agent_id', $agentId ?? $request->user()->id)
            : $request->user()->id;
        $normalized = app(LeadNormalizationService::class)->normalize(['company_name' => $company]);

        return ['company' => $company, 'agentId' => (string) $agentId, 'count' => $normalized['normalized_company_name'] === '' ? 0 : Lead::query()
            ->where('agent_id', $agentId)
            ->where('normalized_company_name', $normalized['normalized_company_name'])
            ->count()];
    }

    /**
     * Download authorized leads from the selected date range in the raw file format.
     */
    public function downloadRaw(DownloadRawLeadsRequest $request): StreamedResponse
    {
        return $this->downloadCsv($request, false);
    }

    /**
     * Download clean, validated leads from the selected date range.
     */
    public function downloadCleaned(
        DownloadRawLeadsRequest $request,
        TimezoneReferenceResolver $timezoneReferences,
    ): StreamedResponse {
        return $this->downloadCsv($request, true, $timezoneReferences);
    }

    private function downloadCsv(
        DownloadRawLeadsRequest $request,
        bool $cleaned,
        ?TimezoneReferenceResolver $timezoneReferences = null,
    ): StreamedResponse {
        $dates = $request->validated();
        $user = $request->user();
        $query = Lead::query()
            ->select(['id', 'lead_date', 'company_name', 'website', 'contact_person', 'email', 'country', 'raw_country', 'country_code', 'city', 'raw_city', 'import_trades', 'linkedin_url', 'data_source', 'source_url'])
            ->when(! $user->canViewAllLeads(), fn ($query) => $query->whereBelongsTo($user, 'agent'))
            ->when($cleaned, fn ($query) => $query->whereNotIn('status', ['duplicate', 'validation_error']))
            ->when($dates['date_from'] ?? null, fn ($query, string $date) => $query->whereDate('lead_date', '>=', $date))
            ->when($dates['date_to'] ?? null, fn ($query, string $date) => $query->whereDate('lead_date', '<=', $date))
            ->orderBy('id');

        AuditLog::query()->create([
            'user_id' => $user->id,
            'action' => $cleaned ? 'leads.cleaned_exported' : 'leads.raw_exported',
            'auditable_type' => 'lead',
            'description' => $cleaned ? 'Downloaded a cleaned lead export.' : 'Downloaded a raw lead export.',
            'metadata' => $dates,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $csv = app(CsvCellSanitizer::class);

        return response()->streamDownload(function () use ($query, $cleaned, $timezoneReferences, $csv): void {
            $stream = fopen('php://output', 'wb');
            if (! is_resource($stream)) {
                return;
            }

            $headers = ['Date', 'Company', 'Website', 'First Name', 'Email', 'Country', 'City', 'Import Trades', 'LinkedIn', 'Sources of Data', 'Link'];
            fputcsv($stream, $headers, escape: '');
            foreach ($query->cursor() as $lead) {
                $country = $lead->country_code ?: $lead->country;
                $city = $lead->city;

                if ($cleaned && $timezoneReferences !== null) {
                    $rawCountry = $lead->raw_country ?: $country;
                    $reference = $timezoneReferences->resolveByCountryCode($lead->country_code ?: $rawCountry);
                    $country = $reference?->reference_country_code ?: $rawCountry;
                    $city = $reference?->reference_capital ?: ($lead->raw_city ?: $city);
                }

                $values = [$lead->lead_date?->format('m/d/Y'), $lead->company_name, $lead->website, $lead->contact_person, $lead->email, $country, $city, $lead->import_trades, $lead->linkedin_url, $lead->data_source, $lead->source_url];
                fputcsv($stream, $csv->sanitizeRow($values), escape: '');
            }
            fclose($stream);
        }, $this->downloadFilename($dates, $cleaned ? 'Cleaned' : 'Raw'), ['Content-Type' => 'text/csv']);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Lead::class);
        $user = $request->user();
        $columns = ['id', 'lead_code', 'agent_id', 'upload_batch_id', 'company_name', 'website', 'website_domain', 'city', 'country', 'country_code', 'contact_person', 'position', 'email', 'phone', 'industry', 'status', 'validation_status', 'source', 'lead_date', 'created_at'];
        $query = Lead::query()->select(array_map(fn (string $column): string => "leads.{$column}", $columns))->with(['agent:id,name', 'uploadBatch:id,batch_code']);
        if ($user->isSuperAdministrator()) {
            $query->withCount(['emailReplies', 'emailReplies as unread_email_replies_count' => fn ($query) => $query->where('is_read', false)]);
        }
        $search = $request->string('search')->trim()->toString();
        // A search reaches every lead in the database regardless of who owns
        // it, so an agent can find a company even if it belongs to a
        // colleague. Browsing without a search term stays scoped to the
        // agent's own leads, and editing another agent's lead is still
        // blocked by the update policy.
        if (! $user->canViewAllLeads() && $search === '') {
            $query->whereBelongsTo($user, 'agent');
        }
        if ($search !== '') {
            // Company names imported from CSVs often carry stray spacing,
            // punctuation, or casing that defeats a literal LIKE match, so
            // also compare against the same normalized form used to store
            // normalized_company_name.
            $normalizedSearch = Str::of($search)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->toString();
            $query->where(fn ($q) => $q->where('lead_code', 'like', "%{$search}%")->orWhere('company_name', 'like', "%{$search}%")->orWhere('normalized_company_name', 'like', "%{$normalizedSearch}%")->orWhere('website', 'like', "%{$search}%")->orWhere('website_domain', 'like', "%{$search}%")->orWhere('contact_person', 'like', "%{$search}%")->orWhere('position', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")->orWhere('city', 'like', "%{$search}%")->orWhere('state_province', 'like', "%{$search}%")->orWhere('country', 'like', "%{$search}%")->orWhere('country_code', 'like', "%{$search}%")->orWhere('industry', 'like', "%{$search}%")->orWhere('business_type', 'like', "%{$search}%")->orWhere('linkedin_url', 'like', "%{$search}%")->orWhere('import_trades', 'like', "%{$search}%")->orWhere('data_source', 'like', "%{$search}%")->orWhere('source_url', 'like', "%{$search}%")->orWhere('notes', 'like', "%{$search}%")->orWhereHas('agent', fn ($agent) => $agent->where('name', 'like', "%{$search}%"))->orWhereHas('uploadBatch', fn ($batch) => $batch->where('batch_code', 'like', "%{$search}%")));
        }
        foreach (['status', 'source', 'country'] as $filter) {
            if ($value = $request->string($filter)->toString()) {
                $query->where($filter, $value);
            }
        }
        foreach (['validation_status', 'agent_id', 'upload_batch_id'] as $filter) {
            if ($value = $request->string($filter)->toString()) {
                $query->where($filter, $value);
            }
        }
        if ($duplicate = $request->string('duplicate_status')->toString()) {
            $query->where(fn ($query) => $query->whereHas('duplicateMatchesAsIncoming', fn ($match) => $match->where('match_type', $duplicate))->orWhereHas('duplicateMatchesAsExisting', fn ($match) => $match->where('match_type', $duplicate)));
        }
        if ($date = $request->string('date')->toString()) {
            $query->whereDate('lead_date', $date);
        }
        $sort = in_array($request->string('sort')->toString(), ['company_name', 'city', 'country', 'status', 'source', 'agent', 'created_at'], true) ? $request->string('sort')->toString() : 'created_at';
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';
        $requestedPerPage = $request->integer('per_page', 10);
        $perPage = in_array($requestedPerPage, [10, 25, 50, 100], true) ? $requestedPerPage : 10;

        if ($sort === 'agent') {
            $query->leftJoin('users as lead_sort_agents', 'lead_sort_agents.id', '=', 'leads.agent_id')
                ->orderBy('lead_sort_agents.name', $direction);
        } else {
            $query->orderBy("leads.{$sort}", $direction);
        }

        $query->addSelect(['company_contact_count' => DB::table('leads as company_contacts')
            ->selectRaw('count(*)')
            ->whereColumn('company_contacts.agent_id', 'leads.agent_id')
            ->whereColumn('company_contacts.normalized_company_name', 'leads.normalized_company_name')
            ->whereNull('company_contacts.deleted_at')]);
        $leads = $query->paginate($perPage)->withQueryString();
        $leads->through(fn (Lead $lead): array => [
            ...$lead->toArray(),
            'can_update' => $user->can('update', $lead),
            'can_send_email' => $lead->agent_id === $user->id && filter_var($lead->email, FILTER_VALIDATE_EMAIL) !== false,
        ]);

        return Inertia::render('leads/index', ['leads' => $leads, 'filters' => [...$request->only(['search', 'status', 'source', 'country', 'validation_status', 'agent_id', 'upload_batch_id', 'duplicate_status', 'sort', 'direction']), 'date' => $request->string('date')->toString() ?: today()->toDateString(), 'per_page' => (string) $perPage], 'canBulkDelete' => $user->isAdministrator(), 'agents' => $user->canViewAllLeads() ? User::query()->orderBy('name')->get(['id', 'name']) : [], 'batches' => $user->canViewAllLeads() ? UploadBatch::query()->latest()->limit(200)->get(['id', 'batch_code']) : []]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', Lead::class);
        $latestLead = Lead::query()
            ->whereBelongsTo($request->user(), 'creator')
            ->latest('id')
            ->first(['id', 'agent_id', 'lead_date', 'company_name', 'website', 'country_code', 'city', 'import_trades', 'data_source', 'source_url']);

        $defaults = [
            ...($latestLead?->only(['agent_id', 'company_name', 'website', 'country_code', 'city', 'import_trades', 'data_source', 'source_url']) ?? []),
            'lead_date' => $latestLead?->lead_date?->toDateString() ?? today()->toDateString(),
            'contact_person' => '',
            'email' => '',
            'linkedin_url' => '',
        ];

        return Inertia::render('leads/form', ['companyContactCount' => fn (): array => $this->formCompanyContactCount($request, $defaults['company_name'] ?? '', $defaults['agent_id'] ?? null), 'lead' => null, 'defaults' => $defaults, 'formVersion' => $latestLead === null ? 0 : $latestLead->id, 'agents' => $request->user()->canViewAllLeads() ? User::where('role', UserRole::Agent)->where('status', 'active')->orderBy('name')->get(['id', 'name']) : []]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreLeadRequest $request, LeadCreator $creator): RedirectResponse
    {
        $data = $request->validated();
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $owner = $actor->canViewAllLeads() && isset($data['agent_id']) ? User::query()->whereKey($data['agent_id'])->firstOrFail() : $actor;
        $creator->create($data, $owner, $actor);

        return redirect()->route('leads.create')->with('toast', ['type' => 'success', 'message' => 'Lead saved successfully.']);
    }

    /**
     * Display the specified resource.
     */
    public function show(Lead $lead): RedirectResponse
    {
        Gate::authorize('view', $lead);

        return redirect()->route('leads.edit', $lead);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, Lead $lead): Response
    {
        Gate::authorize('update', $lead);

        $changeHistory = AuditLog::query()
            ->where('auditable_type', 'lead')
            ->where('auditable_id', $lead->id)
            ->with('user:id,name')
            ->latest()
            ->limit(50)
            ->get(['id', 'user_id', 'description', 'metadata', 'created_at']);

        $user = $request->user();
        $nextLead = Lead::query()
            ->when(! $user->canViewAllLeads(), fn ($query) => $query->whereBelongsTo($user, 'agent'))
            ->where(fn ($query) => $query
                ->where('created_at', '<', $lead->created_at)
                ->orWhere(fn ($query) => $query->where('created_at', $lead->created_at)->where('id', '<', $lead->id)))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first(['id']);

        return Inertia::render('leads/form', ['companyContactCount' => fn (): array => $this->formCompanyContactCount($request, $lead->company_name, $lead->agent_id), 'lead' => $lead, 'defaults' => [], 'formVersion' => $lead->id, 'agents' => $request->user()->canViewAllLeads() ? User::where('role', UserRole::Agent)->where('status', 'active')->orderBy('name')->get(['id', 'name']) : [], 'changeHistory' => $changeHistory, 'nextLeadId' => $nextLead?->id]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateLeadRequest $request, Lead $lead): RedirectResponse
    {
        $data = $request->validated();
        unset($data['agent_id']);
        // Snapshot the pre-update values for exactly the fields being written, so the
        // audit entry below can pair each changed field with what it used to be -
        // getOriginal() is unreliable for this once update() has already synced it.
        $original = $lead->only(array_keys($data));
        $lead->update([...$data, 'updated_by' => $request->user()->id]);

        $changes = collect($lead->getChanges())
            ->except(['updated_at', 'updated_by'])
            ->mapWithKeys(fn (mixed $new, string $field): array => [$field => ['old' => $original[$field] ?? null, 'new' => $new]])
            ->all();
        if ($changes !== []) {
            AuditLog::query()->create([
                'user_id' => $request->user()->id,
                'action' => 'leads.updated',
                'auditable_type' => 'lead',
                'auditable_id' => $lead->id,
                'description' => 'Updated lead fields.',
                'metadata' => ['changes' => $changes],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        $this->syncCompanyWideFields($request, $lead, $changes);

        return back()->with('toast', ['type' => 'success', 'message' => 'Lead updated successfully.']);
    }

    /**
     * Import trades, sources of data, and the link are attributes of the
     * company, not the individual contact, so editing one contact's value
     * keeps every other contact at the same company (for the same agent) in
     * sync instead of requiring the agent to repeat the edit on each one.
     *
     * @param  array<string, array{old: mixed, new: mixed}>  $changes
     */
    private function syncCompanyWideFields(Request $request, Lead $lead, array $changes): void
    {
        $syncFields = ['import_trades', 'data_source', 'source_url'];
        $syncChanges = array_intersect_key($changes, array_flip($syncFields));
        if ($syncChanges === [] || $lead->normalized_company_name === '') {
            return;
        }

        $syncData = collect($syncChanges)->map(fn (array $change): mixed => $change['new'])->all();
        $siblings = Lead::query()
            ->where('agent_id', $lead->agent_id)
            ->where('normalized_company_name', $lead->normalized_company_name)
            ->where('id', '!=', $lead->id)
            ->get(array_merge(['id'], $syncFields));

        foreach ($siblings as $sibling) {
            $siblingOriginal = $sibling->only($syncFields);
            $sibling->forceFill([...$syncData, 'updated_by' => $request->user()->id])->save();

            $siblingChanges = collect($sibling->getChanges())
                ->only($syncFields)
                ->mapWithKeys(fn (mixed $new, string $field): array => [$field => ['old' => $siblingOriginal[$field] ?? null, 'new' => $new]])
                ->all();
            if ($siblingChanges !== []) {
                AuditLog::query()->create([
                    'user_id' => $request->user()->id,
                    'action' => 'leads.updated',
                    'auditable_type' => 'lead',
                    'auditable_id' => $sibling->id,
                    'description' => "Synced from another contact at {$lead->company_name}.",
                    'metadata' => ['changes' => $siblingChanges],
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            }
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Lead $lead): RedirectResponse
    {
        Gate::authorize('delete', $lead);
        $lead->delete();

        return redirect()->route('leads.index')->with('toast', ['type' => 'success', 'message' => 'Lead archived.']);
    }

    public function bulkDestroy(BulkDeleteLeadsRequest $request, LeadBulkDeletion $deletion): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $deletedCount = $deletion->delete($request->validated('lead_ids'), $actor, $request->ip(), $request->userAgent());

        return redirect()->route('leads.index')->with('toast', [
            'type' => 'success',
            'message' => "{$deletedCount} lead(s) deleted successfully.",
        ]);
    }

    /** @param array{date_from?: string, date_to?: string} $dates */
    private function downloadFilename(array $dates, string $type): string
    {
        $from = isset($dates['date_from']) ? Date::parse($dates['date_from'])->format('m-d-Y') : null;
        $to = isset($dates['date_to']) ? Date::parse($dates['date_to'])->format('m-d-Y') : null;

        return match (true) {
            $from !== null && $from === $to => "{$from}-Leads-{$type}.csv",
            $from !== null && $to !== null => "{$from}-to-{$to}-Leads-{$type}.csv",
            $from !== null => "From-{$from}-Leads-{$type}.csv",
            $to !== null => "Through-{$to}-Leads-{$type}.csv",
            default => "All-Leads-{$type}.csv",
        };
    }
}
