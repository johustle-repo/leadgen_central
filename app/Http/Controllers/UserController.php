<?php

namespace App\Http\Controllers;

use App\AccountStatus;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);
        $query = User::query()
            ->select(['id', 'name', 'email', 'role', 'team', 'status', 'created_at'])
            ->with(['emailSequences:id,user_id,is_active'])
            ->withCount(['leads', 'emailReplies']);
        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
        }
        foreach (['role', 'status'] as $filter) {
            if ($value = $request->string($filter)->toString()) {
                $query->where($filter, $value);
            }
        }

        $users = $query->latest()->paginate(15)->withQueryString();
        $users->through(fn (User $user): array => [
            ...$user->only(['id', 'name', 'email', 'role', 'team', 'status', 'created_at']),
            'leads_count' => $user->leads_count,
            'email_replies_count' => $user->email_replies_count,
            'email_sequence_enabled' => $user->emailSequences->isEmpty()
                ? true
                : $user->emailSequences->first()->is_active,
            'can_delete' => $request->user()->can('delete', $user),
        ]);

        return Inertia::render('users/index', ['users' => $users, 'filters' => $request->only(['search', 'role', 'status'])]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        Gate::authorize('create', User::class);

        return Inertia::render('users/form', ['managedUser' => null, 'roles' => UserRole::cases(), 'statuses' => AccountStatus::cases()]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        User::create($request->validated());

        return redirect()->route('users.index')->with('toast', ['type' => 'success', 'message' => 'User created successfully.']);
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user): RedirectResponse
    {
        return redirect()->route('users.edit', $user);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user): Response
    {
        Gate::authorize('update', $user);

        return Inertia::render('users/form', ['managedUser' => $user, 'roles' => UserRole::cases(), 'statuses' => AccountStatus::cases()]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }
        if ($user->is($request->user()) && ($data['status'] !== 'active' || $data['role'] !== 'administrator')) {
            return back()->withErrors(['status' => 'You cannot deactivate or demote your own administrator account.']);
        }
        $user->update($data);

        return back()->with('toast', ['type' => 'success', 'message' => 'User updated successfully.']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);
        $user->delete();

        return redirect()->route('users.index')->with('toast', ['type' => 'success', 'message' => "{$user->name} was deleted successfully."]);
    }
}
