<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkDeleteLeadsRequest;
use App\Http\Requests\DownloadRawLeadsRequest;
use App\Http\Requests\StoreLeadRequest;
use App\Http\Requests\UpdateLeadRequest;
use App\Models\Lead;
use App\Models\UploadBatch;
use App\Models\User;
use App\Services\LeadBulkDeletion;
use App\Services\LeadCreator;
use App\Services\LocationMatchingService;
use App\Services\TimezoneReferenceResolver;
use App\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeadController extends Controller
{
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
        LocationMatchingService $locations,
        TimezoneReferenceResolver $timezoneReferences,
    ): StreamedResponse {
        return $this->downloadCsv($request, true, $locations, $timezoneReferences);
    }

    private function downloadCsv(
        DownloadRawLeadsRequest $request,
        bool $cleaned,
        ?LocationMatchingService $locations = null,
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

        return response()->streamDownload(function () use ($query, $cleaned, $locations, $timezoneReferences): void {
            $stream = fopen('php://output', 'wb');
            if (! is_resource($stream)) {
                return;
            }

            $headers = ['Date', 'Company', 'Website', 'First Name', 'Email', 'Country', 'City', 'Import Trades', 'LinkedIn', 'Sources of Data', 'Link'];
            fputcsv($stream, $headers, escape: '');
            $locationCache = [];
            foreach ($query->cursor() as $lead) {
                $country = $lead->country_code ?: $lead->country;
                $city = $lead->city;

                if ($cleaned && $locations !== null && $timezoneReferences !== null) {
                    $rawCountry = $lead->raw_country ?: $country;
                    $rawCity = $lead->raw_city ?: $city;
                    $reference = $timezoneReferences->resolveByCountryCode($lead->country_code ?: $rawCountry);
                    $cleanCountry = $reference?->reference_country_code ?: $rawCountry;
                    $cleanCity = $reference?->reference_capital ?: $rawCity;
                    $cacheKey = "{$cleanCountry}|{$cleanCity}";
                    $locationCache[$cacheKey] ??= $locations->match($cleanCountry, $cleanCity)->leadAttributes($cleanCity, $cleanCountry);
                    $country = $locationCache[$cacheKey]['country_code'] ?: $locationCache[$cacheKey]['country'];
                    $city = $locationCache[$cacheKey]['city'];
                }

                $values = [$lead->lead_date?->format('m/d/Y'), $lead->company_name, $lead->website, $lead->contact_person, $lead->email, $country, $city, $lead->import_trades, $lead->linkedin_url, $lead->data_source, $lead->source_url];
                fputcsv($stream, $values, escape: '');
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
        $query = Lead::query()->select(['id', 'lead_code', 'agent_id', 'upload_batch_id', 'company_name', 'website', 'website_domain', 'city', 'country', 'country_code', 'contact_person', 'position', 'email', 'phone', 'industry', 'status', 'validation_status', 'source', 'created_at'])->with(['agent:id,name', 'uploadBatch:id,batch_code'])->withCount(['emailReplies', 'emailReplies as unread_email_replies_count' => fn ($query) => $query->where('is_read', false)]);
        if (! $user->canViewAllLeads()) {
            $query->whereBelongsTo($user, 'agent');
        }
        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(fn ($q) => $q->where('company_name', 'like', "%{$search}%")->orWhere('website', 'like', "%{$search}%")->orWhere('website_domain', 'like', "%{$search}%")->orWhere('contact_person', 'like', "%{$search}%")->orWhere('position', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")->orWhere('city', 'like', "%{$search}%")->orWhere('country', 'like', "%{$search}%")->orWhere('industry', 'like', "%{$search}%")->orWhereHas('agent', fn ($agent) => $agent->where('name', 'like', "%{$search}%"))->orWhereHas('uploadBatch', fn ($batch) => $batch->where('batch_code', 'like', "%{$search}%")));
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
        $sort = in_array($request->string('sort')->toString(), ['company_name', 'city', 'country', 'status', 'source', 'created_at'], true) ? $request->string('sort')->toString() : 'created_at';
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';
        $requestedPerPage = $request->integer('per_page', 10);
        $perPage = in_array($requestedPerPage, [10, 25, 50, 100], true) ? $requestedPerPage : 10;

        $leads = $query->orderBy($sort, $direction)->paginate($perPage)->withQueryString();
        $leads->through(fn (Lead $lead): array => [
            ...$lead->toArray(),
            'can_update' => $user->can('update', $lead),
            'can_send_email' => $lead->agent_id === $user->id && filter_var($lead->email, FILTER_VALIDATE_EMAIL) !== false,
        ]);

        return Inertia::render('leads/index', ['leads' => $leads, 'filters' => [...$request->only(['search', 'status', 'source', 'country', 'validation_status', 'agent_id', 'upload_batch_id', 'duplicate_status', 'date', 'sort', 'direction']), 'per_page' => (string) $perPage], 'canBulkDelete' => $user->isAdministrator(), 'agents' => $user->canViewAllLeads() ? User::query()->orderBy('name')->get(['id', 'name']) : [], 'batches' => $user->canViewAllLeads() ? UploadBatch::query()->latest()->limit(200)->get(['id', 'batch_code']) : []]);
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

        return Inertia::render('leads/form', ['lead' => null, 'defaults' => $defaults, 'formVersion' => $latestLead === null ? 0 : $latestLead->id, 'agents' => $request->user()->canViewAllLeads() ? User::where('role', UserRole::Agent)->where('status', 'active')->orderBy('name')->get(['id', 'name']) : []]);
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

        return Inertia::render('leads/form', ['lead' => $lead, 'defaults' => [], 'formVersion' => $lead->id, 'agents' => $request->user()->canViewAllLeads() ? User::where('role', UserRole::Agent)->where('status', 'active')->orderBy('name')->get(['id', 'name']) : []]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateLeadRequest $request, Lead $lead): RedirectResponse
    {
        $data = $request->validated();
        unset($data['agent_id']);
        $lead->update([...$data, 'updated_by' => $request->user()->id]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Lead updated successfully.']);
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
