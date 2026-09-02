<?php

namespace App\Http\Controllers;

use App\Jobs\SyncGmailReplies;
use App\Models\AuditLog;
use App\Models\GmailConnection;
use App\Services\GmailOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class GmailConnectionController extends Controller
{
    public function connect(Request $request, GmailOAuthService $gmail): Response
    {
        $state = Str::random(64);
        $request->session()->put('gmail_oauth_state', $state);

        return Inertia::location($gmail->authorizationUrl($state));
    }

    public function callback(Request $request, GmailOAuthService $gmail): RedirectResponse
    {
        $expectedState = (string) $request->session()->pull('gmail_oauth_state', '');
        abort_unless($expectedState !== '' && hash_equals($expectedState, $request->string('state')->toString()), 403);

        if ($request->filled('error')) {
            return redirect()->route('email-replies.index')->with('toast', ['type' => 'error', 'message' => 'Gmail access was not approved.']);
        }

        $request->validate(['code' => ['required', 'string']]);

        try {
            $tokens = $gmail->exchangeCode($request->string('code')->toString());
            $profile = $gmail->profile((string) $tokens['access_token']);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('email-replies.index')->with('toast', ['type' => 'error', 'message' => 'Gmail connection failed. Please try again or contact an administrator.']);
        }

        $existing = GmailConnection::query()->whereBelongsTo($request->user())->first();
        $refreshToken = (string) ($tokens['refresh_token'] ?? $existing->refresh_token ?? '');
        if ($refreshToken === '') {
            return redirect()->route('email-replies.index')->with('toast', ['type' => 'error', 'message' => 'Google did not provide offline access. Disconnect the app in Google and connect again.']);
        }

        $connection = GmailConnection::query()->updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'gmail_address' => (string) $profile['emailAddress'],
                'access_token' => (string) $tokens['access_token'],
                'refresh_token' => $refreshToken,
                'token_expires_at' => now()->addSeconds((int) ($tokens['expires_in'] ?? 3600)),
                'history_id' => (string) ($profile['historyId'] ?? ''),
                'status' => 'active',
                'last_error' => null,
            ],
        );
        $this->audit($request, 'gmail.connected', $connection, "Connected Gmail mailbox {$connection->gmail_address}.");
        SyncGmailReplies::dispatch($connection->id);

        return redirect()->route('email-replies.index')->with('toast', ['type' => 'success', 'message' => 'Gmail connected. Initial reply synchronization was queued.']);
    }

    public function sync(Request $request): RedirectResponse
    {
        $connection = GmailConnection::query()->whereBelongsTo($request->user())->first();
        if ($connection === null) {
            return back()->with('toast', ['type' => 'error', 'message' => 'Connect Gmail before synchronizing replies.']);
        }

        SyncGmailReplies::dispatch($connection->id);

        return back()->with('toast', ['type' => 'success', 'message' => 'Gmail reply synchronization was queued.']);
    }

    public function disconnect(Request $request, GmailOAuthService $gmail): RedirectResponse
    {
        $connection = GmailConnection::query()->whereBelongsTo($request->user())->first();
        if ($connection === null) {
            return back();
        }

        try {
            $gmail->revoke($connection);
        } catch (Throwable $exception) {
            report($exception);
        }
        $this->audit($request, 'gmail.disconnected', $connection, "Disconnected Gmail mailbox {$connection->gmail_address}.");
        $connection->delete();

        return redirect()->route('email-replies.index')->with('toast', ['type' => 'success', 'message' => 'Gmail disconnected. Existing matched replies were preserved.']);
    }

    private function audit(Request $request, string $action, GmailConnection $connection, string $description): void
    {
        AuditLog::query()->create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'auditable_type' => 'gmail_connection',
            'auditable_id' => $connection->id,
            'description' => $description,
            'metadata' => ['gmail_address' => $connection->gmail_address],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
