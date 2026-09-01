<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLeadAttachmentRequest;
use App\Models\AuditLog;
use App\Models\Lead;
use App\Models\LeadAttachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeadAttachmentController extends Controller
{
    public function store(StoreLeadAttachmentRequest $request, Lead $lead): RedirectResponse
    {
        /** @var UploadedFile $file */
        $file = $request->file('attachment');
        $path = $file->store("lead-attachments/{$lead->id}", 'local');
        $attachment = $lead->attachments()->create([
            'uploaded_by' => $request->user()->id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'file_size' => $file->getSize(),
            'label' => $request->validated('label'),
        ]);
        $this->audit($request, $attachment, 'uploaded');

        return back()->with('toast', ['type' => 'success', 'message' => 'Contact document uploaded successfully.']);
    }

    public function download(Request $request, Lead $lead, LeadAttachment $leadAttachment): StreamedResponse
    {
        Gate::authorize('view', $lead);
        abort_unless($request->user()->canViewAllLeads() && $leadAttachment->lead_id === $lead->id, 403);
        abort_unless(Storage::disk($leadAttachment->disk)->exists($leadAttachment->path), 404);

        $this->audit($request, $leadAttachment, 'downloaded');

        return Storage::disk($leadAttachment->disk)->download($leadAttachment->path, $this->downloadName($leadAttachment));
    }

    public function destroy(Request $request, Lead $lead, LeadAttachment $leadAttachment): RedirectResponse
    {
        Gate::authorize('view', $lead);
        abort_unless($request->user()->canViewAllLeads() && $leadAttachment->lead_id === $lead->id, 403);
        Storage::disk($leadAttachment->disk)->delete($leadAttachment->path);
        $this->audit($request, $leadAttachment, 'deleted');
        $leadAttachment->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Contact document removed.']);
    }

    private function audit(Request $request, LeadAttachment $attachment, string $action): void
    {
        AuditLog::query()->create([
            'user_id' => $request->user()->id,
            'action' => "lead_attachment.{$action}",
            'auditable_type' => 'lead_attachment',
            'auditable_id' => $attachment->id,
            'description' => ucfirst($action)." {$attachment->original_name} for lead {$attachment->lead_id}.",
            'metadata' => ['lead_id' => $attachment->lead_id, 'file_name' => $attachment->original_name],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    private function downloadName(LeadAttachment $attachment): string
    {
        $name = preg_replace('/[^A-Za-z0-9._-]/', '-', Str::ascii(basename($attachment->original_name)));

        return filled($name) ? $name : 'contact-document';
    }
}
