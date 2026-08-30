<?php

namespace App\Http\Controllers;

use App\Http\Requests\VerifyLeadRequest;
use App\Models\Lead;
use App\Models\User;
use App\Services\LeadVerificationService;
use App\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class VerificationController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->canViewAllLeads(), 403);
        $query = Lead::query()->with('agent:id,name')->withCount('structuredNotes')->latest();
        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        } else {
            $query->whereIn('status', ['raw', 'validated', 'needs_review', 'possible_lead']);
        }

        return Inertia::render('verification/index', ['leads' => $query->paginate(20)->withQueryString(), 'status' => $status]);
    }

    public function show(Request $request, Lead $lead): Response
    {
        abort_unless($request->user()->canViewAllLeads(), 403);
        Gate::authorize('view', $lead);
        $lead->load(['agent:id,name', 'uploadBatch:id,batch_code', 'structuredNotes.user:id,name', 'statusHistory.changer:id,name', 'forwardings.forwarder:id,name']);

        return Inertia::render('verification/show', ['lead' => $lead, 'previousId' => Lead::query()->where('id', '<', $lead->id)->max('id'), 'nextId' => Lead::query()->where('id', '>', $lead->id)->min('id'), 'reviewers' => User::query()->whereIn('role', [UserRole::Administrator, UserRole::SubAdministrator])->where('status', 'active')->get(['id', 'name'])]);
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
}
