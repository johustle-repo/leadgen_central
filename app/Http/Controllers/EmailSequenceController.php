<?php

namespace App\Http\Controllers;

use App\Http\Requests\EnrollLeadInSequenceRequest;
use App\Http\Requests\SaveEmailSequenceRequest;
use App\Http\Requests\ToggleUserEmailSequenceRequest;
use App\Models\AuditLog;
use App\Models\EmailSequence;
use App\Models\EmailSequenceEnrollment;
use App\Models\GmailConnection;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EmailSequenceController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Lead::class);
        $user = $request->user();
        $sequence = EmailSequence::query()->whereBelongsTo($user)->first();
        $enrollments = EmailSequenceEnrollment::query()
            ->whereBelongsTo($user, 'agent')
            ->with(['lead:id,contact_person,email,company_name', 'messages:id,email_sequence_enrollment_id,step_number,sent_at'])
            ->latest('started_at')
            ->paginate(20);

        return Inertia::render('email-sequences/index', [
            'sequence' => [
                'name' => $sequence?->name ?? 'DUSCAFF 7-Day Outreach',
                'is_active' => $sequence?->is_active ?? true,
                'steps' => $sequence?->steps ?? EmailSequence::defaultSteps(),
            ],
            'enrollments' => $enrollments,
            'availableLeads' => Lead::query()
                ->whereBelongsTo($user, 'agent')
                ->whereNotNull('email')
                ->whereDoesntHave('emailSequenceEnrollments', fn ($query) => $query->whereIn('status', ['active', 'pending']))
                ->latest('id')
                ->limit(250)
                ->get(['id', 'contact_person', 'email', 'company_name']),
            'gmailConnected' => GmailConnection::query()->whereBelongsTo($user)->where('status', 'active')->exists(),
            'summary' => [
                'active' => EmailSequenceEnrollment::query()->whereBelongsTo($user, 'agent')->where('status', 'active')->count(),
                'replied' => EmailSequenceEnrollment::query()->whereBelongsTo($user, 'agent')->where('status', 'replied')->count(),
                'completed' => EmailSequenceEnrollment::query()->whereBelongsTo($user, 'agent')->where('status', 'completed')->count(),
            ],
        ]);
    }

    public function update(SaveEmailSequenceRequest $request): RedirectResponse
    {
        EmailSequence::query()->updateOrCreate(
            ['user_id' => $request->user()->id],
            $request->validated(),
        );

        return back()->with('toast', ['type' => 'success', 'message' => 'Email sequence saved successfully.']);
    }

    public function toggleForUser(ToggleUserEmailSequenceRequest $request, User $user): RedirectResponse
    {
        $sequence = EmailSequence::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['name' => 'DUSCAFF 7-Day Outreach', 'steps' => EmailSequence::defaultSteps()],
        );
        $sequence->update(['is_active' => $request->boolean('is_active')]);

        $state = $sequence->is_active ? 'enabled' : 'paused';

        return back()->with('toast', ['type' => 'success', 'message' => "Email sequence {$state} for {$user->name}."]);
    }

    public function enroll(EnrollLeadInSequenceRequest $request): RedirectResponse
    {
        $user = $request->user();
        if (! GmailConnection::query()->whereBelongsTo($user)->where('status', 'active')->exists()) {
            return back()->with('toast', ['type' => 'error', 'message' => 'Connect Gmail before enrolling leads.']);
        }

        $lead = Lead::query()->whereBelongsTo($user, 'agent')->findOrFail($request->integer('lead_id'));
        $sequence = EmailSequence::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['name' => 'DUSCAFF 7-Day Outreach', 'steps' => EmailSequence::defaultSteps(), 'is_active' => true],
        );
        if (! $sequence->is_active) {
            return back()->with('toast', ['type' => 'error', 'message' => 'Enable the email sequence before enrolling leads.']);
        }
        $enrollment = EmailSequenceEnrollment::query()->updateOrCreate(
            ['email_sequence_id' => $sequence->id, 'lead_id' => $lead->id],
            ['agent_id' => $user->id, 'status' => 'active', 'current_step' => 0, 'started_at' => now(), 'next_send_at' => now(), 'stopped_at' => null, 'stop_reason' => null, 'last_error' => null],
        );
        $enrollment->messages()->delete();
        AuditLog::query()->create([
            'user_id' => $user->id,
            'action' => 'email_sequence.enrolled',
            'auditable_type' => 'lead',
            'auditable_id' => $lead->id,
            'description' => "Enrolled {$lead->email} in {$sequence->name}.",
            'metadata' => ['email_sequence_id' => $sequence->id],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => "{$lead->email} was enrolled. The first email will send shortly."]);
    }

    public function cancel(Request $request, EmailSequenceEnrollment $enrollment): RedirectResponse
    {
        abort_unless($enrollment->agent_id === $request->user()->id, 404);
        $enrollment->update(['status' => 'cancelled', 'next_send_at' => null, 'stopped_at' => now(), 'stop_reason' => 'Cancelled by agent']);

        return back()->with('toast', ['type' => 'success', 'message' => 'Sequence stopped for this lead.']);
    }
}
