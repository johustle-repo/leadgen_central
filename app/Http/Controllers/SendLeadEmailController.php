<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendLeadEmailRequest;
use App\Models\AuditLog;
use App\Models\GmailConnection;
use App\Models\Lead;
use App\Services\GmailMessageBuilder;
use App\Services\GmailOAuthService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use RuntimeException;
use Throwable;

class SendLeadEmailController extends Controller
{
    public function __invoke(
        SendLeadEmailRequest $request,
        Lead $lead,
        GmailOAuthService $gmail,
        GmailMessageBuilder $messages,
    ): RedirectResponse {
        $connection = GmailConnection::query()
            ->whereBelongsTo($request->user())
            ->where('status', 'active')
            ->first();

        if ($connection === null) {
            return back()->with('toast', ['type' => 'error', 'message' => 'Connect Gmail before sending an email.']);
        }

        if (! is_string($lead->email) || filter_var($lead->email, FILTER_VALIDATE_EMAIL) === false) {
            return back()->with('toast', ['type' => 'error', 'message' => 'This lead does not have a valid email address.']);
        }

        try {
            $validated = $request->validated();
            $rawMessage = $messages->build(
                $connection->gmail_address,
                $lead->email,
                $validated['subject'],
                $validated['body'],
                $this->brochurePath(),
                (string) config('services.google.brochure_name'),
            );
            $sent = $gmail->sendMessage($connection, $rawMessage);
        } catch (RequestException $exception) {
            report($exception);
            $message = $exception->response->forbidden()
                ? 'Reconnect Gmail to grant email sending access, then try again.'
                : 'Gmail could not send the email. Please try again.';

            return back()->with('toast', ['type' => 'error', 'message' => $message]);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('toast', ['type' => 'error', 'message' => 'The email could not be sent. Check the brochure configuration and try again.']);
        }

        AuditLog::query()->create([
            'user_id' => $request->user()->id,
            'action' => 'lead.email_sent',
            'auditable_type' => 'lead',
            'auditable_id' => $lead->id,
            'description' => "Sent a DUSCAFF outreach email to {$lead->email}.",
            'metadata' => ['gmail_message_id' => $sent['id'] ?? null, 'recipient' => $lead->email],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => "Email sent successfully to {$lead->email}."]);
    }

    private function brochurePath(): string
    {
        $path = config('services.google.brochure_path');
        if (! is_string($path) || ! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('The configured DUSCAFF brochure is unavailable.');
        }

        return $path;
    }
}
