<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ImpersonationController extends Controller
{
    /**
     * Start impersonating another user. Restricted to Super Administrators,
     * who cannot impersonate themselves, another Super Administrator, or an
     * inactive account, and cannot stack impersonation sessions.
     */
    public function store(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('impersonate', $user);
        $actingUser = $request->user();
        abort_unless($actingUser instanceof User, 401);

        if ($request->session()->has('impersonator_id')) {
            return back()->with('toast', ['type' => 'error', 'message' => 'End your current impersonation session before starting another.']);
        }

        $request->session()->put('impersonator_id', $actingUser->id);
        Auth::login($user);

        AuditLog::query()->create([
            'user_id' => $actingUser->id,
            'action' => 'user.impersonation_started',
            'auditable_type' => 'user',
            'auditable_id' => $user->id,
            'description' => "Started impersonating {$user->name} ({$user->email}).",
            'metadata' => ['target_user_id' => $user->id, 'target_email' => $user->email],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('dashboard')->with('toast', ['type' => 'success', 'message' => "You're now viewing as {$user->name}."]);
    }

    /**
     * End the current impersonation session and return to the acting
     * Super Administrator's own account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $impersonatorId = $request->session()->get('impersonator_id');
        if (! $impersonatorId) {
            return back()->with('toast', ['type' => 'error', 'message' => 'You are not impersonating anyone.']);
        }

        $impersonator = User::query()->findOrFail((int) $impersonatorId);
        $impersonated = $request->user();
        abort_unless($impersonated instanceof User, 401);

        $request->session()->forget('impersonator_id');
        Auth::login($impersonator);

        AuditLog::query()->create([
            'user_id' => $impersonator->id,
            'action' => 'user.impersonation_ended',
            'auditable_type' => 'user',
            'auditable_id' => $impersonated->id,
            'description' => "Stopped impersonating {$impersonated->name}.",
            'metadata' => ['target_user_id' => $impersonated->id],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('users.index')->with('toast', ['type' => 'success', 'message' => 'Returned to your account.']);
    }
}
