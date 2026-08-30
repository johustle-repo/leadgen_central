<?php

namespace App\Http\Controllers;

use App\Http\Requests\ForwardLeadRequest;
use App\Models\Lead;
use App\Services\LeadForwardingService;
use Illuminate\Http\RedirectResponse;

class LeadForwardingController extends Controller
{
    public function store(ForwardLeadRequest $request, Lead $lead, LeadForwardingService $service): RedirectResponse
    {
        $service->forward($lead, $request->validated(), $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => 'Lead forwarded successfully.']);
    }
}
