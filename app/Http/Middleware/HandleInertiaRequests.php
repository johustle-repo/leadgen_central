<?php

namespace App\Http\Middleware;

use App\Models\EmailReply;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'flash' => ['toast' => fn () => $request->session()->get('toast')],
            'notificationCounts' => [
                'unread_email_replies' => function () use ($request): int {
                    $user = $request->user();
                    if ($user === null) {
                        return 0;
                    }

                    return EmailReply::query()
                        ->when(! $user->canViewAllLeads(), fn ($query) => $query->whereBelongsTo($user, 'agent'))
                        ->where('is_read', false)
                        ->count();
                },
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'impersonator' => function () use ($request): ?array {
                $impersonatorId = $request->session()->get('impersonator_id');
                if (! $impersonatorId) {
                    return null;
                }

                return User::query()->find((int) $impersonatorId)?->only(['id', 'name']);
            },
        ];
    }
}
