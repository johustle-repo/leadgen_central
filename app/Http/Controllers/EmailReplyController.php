<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateEmailReplyRequest;
use App\Models\AuditLog;
use App\Models\EmailReply;
use App\Models\GmailConnection;
use App\Services\EmailReplyTextExtractor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EmailReplyController extends Controller
{
    public function index(Request $request, EmailReplyTextExtractor $replyText): Response
    {
        Gate::authorize('viewAny', EmailReply::class);
        $user = $request->user();
        $authorizedReplies = EmailReply::query()
            ->when(! $user->canViewAllLeads(), fn ($query) => $query->whereBelongsTo($user, 'agent'));
        $query = (clone $authorizedReplies)
            ->select(['id', 'agent_id', 'lead_id', 'sender_name', 'sender_email', 'subject', 'body_preview', 'body_text', 'classification', 'classification_reason', 'is_read', 'received_at'])
            ->with(['agent:id,name', 'lead:id,lead_code,company_name,contact_person,email'])
            ->when($request->boolean('unread'), fn ($query) => $query->where('is_read', false))
            ->when($request->string('classification')->toString(), fn ($query, string $classification) => $query->where('classification', $classification))
            ->when($request->string('search')->trim()->toString(), function ($query, string $search): void {
                $query->where(fn ($query) => $query->where('sender_email', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhereHas('lead', fn ($lead) => $lead->where('company_name', 'like', "%{$search}%")));
            });

        $replies = $query->latest('received_at')->paginate(20)->withQueryString();
        $replies->through(fn (EmailReply $reply): array => [
            ...$reply->toArray(),
            'actual_reply' => $replyText->extract($reply->body_text ?? $reply->body_preview ?? ''),
        ]);

        return Inertia::render('email-replies/index', [
            'replies' => $replies,
            'filters' => $request->only(['search', 'classification', 'unread']),
            'connection' => GmailConnection::query()->whereBelongsTo($user)->first(['id', 'gmail_address', 'status', 'last_synced_at', 'last_error']),
            'summary' => [
                'unread' => (clone $authorizedReplies)->where('is_read', false)->count(),
                'possible' => (clone $authorizedReplies)->where('classification', 'possible_lead')->count(),
                'needs_review' => (clone $authorizedReplies)->where('classification', 'needs_review')->count(),
            ],
        ]);
    }

    public function update(UpdateEmailReplyRequest $request, EmailReply $emailReply): RedirectResponse
    {
        $data = $request->validated();
        if (isset($data['classification'])) {
            $data['classification_reason'] = "Updated manually by {$request->user()->name}.";
        }
        $emailReply->update($data);
        AuditLog::query()->create([
            'user_id' => $request->user()->id,
            'action' => 'email_reply.updated',
            'auditable_type' => 'email_reply',
            'auditable_id' => $emailReply->id,
            'description' => "Updated the reply from {$emailReply->sender_email}.",
            'metadata' => $data,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Reply updated successfully.']);
    }
}
