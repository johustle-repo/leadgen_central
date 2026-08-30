<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewDuplicateRequest;
use App\Models\DuplicateMatch;
use App\Models\LeadStatusHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DuplicateReviewController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->canViewAllLeads(), 403);
        $matches = DuplicateMatch::query()->with(['incomingLead.agent:id,name', 'existingLead.agent:id,name', 'uploadRow:id,raw_data'])->latest()->paginate(20);

        return Inertia::render('duplicates/index', ['matches' => $matches]);
    }

    public function update(ReviewDuplicateRequest $request, DuplicateMatch $duplicateMatch): RedirectResponse
    {
        $action = $request->validated('action');
        $status = $action === 'confirm_duplicate' ? 'confirmed' : ($action === 'not_duplicate' ? 'cleared' : 'keep_both');
        $duplicateMatch->update(['status' => $status, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        if ($duplicateMatch->incomingLead && $action === 'confirm_duplicate') {
            $lead = $duplicateMatch->incomingLead;
            $oldStatus = $lead->status->value;
            $lead->update(['status' => 'duplicate', 'updated_by' => $request->user()->id]);
            LeadStatusHistory::query()->create(['lead_id' => $lead->id, 'old_status' => $oldStatus, 'new_status' => 'duplicate', 'changed_by' => $request->user()->id, 'remarks' => 'Confirmed through duplicate review.']);
        }

        return back()->with('toast', ['type' => 'success', 'message' => 'Duplicate review updated.']);
    }
}
