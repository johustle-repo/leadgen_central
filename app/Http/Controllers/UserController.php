<?php

namespace App\Http\Controllers;

use App\AccountStatus;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Services\AgentRecordsCleaner;
use App\UserRole;
use Illuminate\Database\Eloquent\Builder;
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
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->toString();
        $role = $request->string('role')->toString();

        // Administrators and agents are shown as two separate lists rather
        // than one flat table, so the "Role" filter narrows to whichever
        // list(s) it applies to instead of just filtering rows within one
        // combined table.
        $administrators = null;
        if ($role === '' || in_array($role, [UserRole::Administrator->value, UserRole::SubAdministrator->value], true)) {
            $adminQuery = $this->baseUserQuery($search, $status)
                ->whereIn('role', [UserRole::Administrator, UserRole::SubAdministrator]);
            if ($role !== '') {
                $adminQuery->where('role', $role);
            }
            $administrators = $adminQuery->latest()->paginate(15, pageName: 'admins_page')->withQueryString();
            $administrators->through(fn (User $user): array => $this->mapUser($request, $user));
        }

        $agents = null;
        if ($role === '' || $role === UserRole::Agent->value) {
            $agents = $this->baseUserQuery($search, $status)
                ->where('role', UserRole::Agent)
                ->latest()
                ->paginate(15, pageName: 'agents_page')
                ->withQueryString();
            $agents->through(fn (User $user): array => $this->mapUser($request, $user));
        }

        return Inertia::render('users/index', ['administrators' => $administrators, 'agents' => $agents, 'filters' => $request->only(['search', 'role', 'status'])]);
    }

    /** @return Builder<User> */
    private function baseUserQuery(string $search, string $status): Builder
    {
        $query = User::query()
            ->select(['id', 'name', 'email', 'role', 'team', 'status', 'created_at'])
            ->where('role', '!=', UserRole::SuperAdministrator)
            ->withCount(['leads', 'uploadBatches'])
            ->withSum('uploadBatches as rejected_rows_sum', 'rejected_rows')
            ->withSum('uploadBatches as error_rows_sum', 'error_rows')
            ->withSum('uploadBatches as duplicate_rows_sum', 'duplicate_rows');
        if ($search !== '') {
            $query->where(fn (Builder $q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
        }
        if ($status !== '') {
            $query->where('status', $status);
        }

        return $query;
    }

    /** @return array<string, mixed> */
    private function mapUser(Request $request, User $user): array
    {
        return [
            ...$user->only(['id', 'name', 'email', 'role', 'team', 'status', 'created_at']),
            'leads_count' => $user->leads_count,
            'upload_batches_count' => $user->upload_batches_count,
            'errors_count' => (int) $user->rejected_rows_sum + (int) $user->error_rows_sum + (int) $user->duplicate_rows_sum,
            'can_delete' => $request->user()->can('delete', $user),
            'can_impersonate' => $request->user()->can('impersonate', $user),
            'can_clear_records' => $request->user()->can('clearRecords', $user),
        ];
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', User::class);

        return Inertia::render('users/form', ['managedUser' => null, 'roles' => $request->user()->assignableRoles(), 'statuses' => AccountStatus::cases()]);
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
    public function edit(Request $request, User $user): Response
    {
        Gate::authorize('update', $user);
        $roles = $request->user()->assignableRoles();
        if (! in_array($user->role, $roles, true)) {
            $roles[] = $user->role;
        }

        return Inertia::render('users/form', ['managedUser' => $user, 'roles' => $roles, 'statuses' => AccountStatus::cases()]);
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
        if ($user->is($request->user()) && ($data['status'] !== 'active' || $data['role'] !== $user->role->value)) {
            return back()->withErrors(['status' => 'You cannot deactivate or change your own role.']);
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

    /**
     * Wipe a user's leads and upload history without deleting their account.
     */
    public function clearRecords(Request $request, User $user, AgentRecordsCleaner $cleaner): RedirectResponse
    {
        Gate::authorize('clearRecords', $user);
        $result = $cleaner->clear($user, $request->user(), $request->ip(), $request->userAgent());

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Cleared {$result['leads']} lead(s) and {$result['uploads']} upload record(s) for {$user->name}.",
        ]);
    }
}
