<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLeadNoteRequest;
use App\Models\Lead;
use Illuminate\Http\RedirectResponse;

class LeadNoteController extends Controller
{
    public function store(StoreLeadNoteRequest $request, Lead $lead): RedirectResponse
    {
        $lead->structuredNotes()->create([...$request->validated(), 'user_id' => $request->user()->id]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Note added.']);
    }
}
