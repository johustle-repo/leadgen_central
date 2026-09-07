<?php

namespace App\Http\Controllers;

use App\Jobs\SyncGmailReplies;
use App\Models\AuditLog;
use App\Models\GmailConnection;
use App\Models\User;
use App\Services\GmailOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
            return redirect($this->connectionHomeUrl($request))->with('toast', ['type' => 'error', 'message' => 'Gmail access was not approved.']);
        }

        $request->validate(['code' => ['required', 'string']]);

        try {
            $tokens = $gmail->exchangeCode($request->string('code')->toString());
            $profile = $gmail->profile((string) $tokens['access_token']);
        } catch (Throwable $exception) {
            report($exception);

            return redirect($this->connectionHomeUrl($request))->with('toast', ['type' => 'error', 'message' => 'Gmail connection failed. Please try again or contact an administrator.']);
        }

        $existing = GmailConnection::query()->whereBelongsTo($request->user())->first();
        $refreshToken = (string) ($tokens['refresh_token'] ?? $existing->refresh_token ?? '');
        if ($refreshToken === '') {
            return redirect($this->connectionHomeUrl($request))->with('toast', ['type' => 'error', 'message' => 'Google did not provide offline access. Disconnect the app in Google and connect again.']);
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

        return redirect($this->connectionHomeUrl($request))->with('toast', ['type' => 'success', 'message' => 'Gmail connected. Initial reply synchronization was queued.']);
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

    /**
     * Super Administrator triggers a sync for another user's connected mailbox
     * (e.g. an agent's), without needing access to that person's Google account.
     */
    public function syncFor(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('sync-agent-gmail');

        $connection = GmailConnection::query()->whereBelongsTo($user)->first();
        if ($connection === null) {
            return back()->with('toast', ['type' => 'error', 'message' => "{$user->name} has not connected a Gmail account."]);
        }

        SyncGmailReplies::dispatch($connection->id);
        $this->audit($request, 'gmail.synced_by_admin', $connection, "Queued a synchronization for {$connection->gmail_address} (owned by {$user->name}).");

        return back()->with('toast', ['type' => 'success', 'message' => "Synchronization for {$user->name}'s Gmail was queued."]);
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

        return redirect($this->connectionHomeUrl($request))->with('toast', ['type' => 'success', 'message' => 'Gmail disconnected. Existing matched replies were preserved.']);
    }

    /**
     * Where to send a user after connecting/disconnecting their own mailbox:
     * Super Administrators manage it from the Email Replies inbox, everyone
     * else (Email Replies is off-limits to them) uses their profile settings.
     */
    private function connectionHomeUrl(Request $request): string
    {
        return $request->user()->isSuperAdministrator()
            ? route('email-replies.index')
            : route('profile.edit');
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
