<?php

namespace App\Actions\Fortify;

use App\AccountStatus;
use App\Models\AuditLog;
use App\Models\User;
use App\UserRole;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class EnsureSingleAgentSession
{
    public function __invoke(Request $request, Closure $next): mixed
    {
        $user = User::query()
            ->where('email', $request->string('email')->lower()->toString())
            ->first();

        if (! $this->isValidAgentLogin($user, $request) || ! $this->hasActiveSession($user, $request)) {
            return $next($request);
        }

        if (! $request->boolean('force_login')) {
            throw ValidationException::withMessages([
                'active_session' => 'This account is already logged in on another browser.',
            ]);
        }

        $removedSessions = DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        AuditLog::query()->create([
            'user_id' => $user->id,
            'action' => 'agent.session_replaced',
            'auditable_type' => 'user',
            'auditable_id' => $user->id,
            'description' => 'Logged out an existing browser session and continued login.',
            'metadata' => ['removed_sessions' => $removedSessions],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $next($request);
    }

    private function isValidAgentLogin(?User $user, Request $request): bool
    {
        return $user !== null
            && $user->role === UserRole::Agent
            && $user->status === AccountStatus::Active
            && Hash::check($request->string('password')->toString(), $user->password);
    }

    private function hasActiveSession(User $user, Request $request): bool
    {
        $activeSince = now()->subMinutes((int) config('session.lifetime', 120))->timestamp;

        return DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->where('id', '!=', $request->session()->getId())
            ->where('last_activity', '>=', $activeSince)
            ->exists();
    }
}
