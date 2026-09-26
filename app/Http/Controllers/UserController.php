<?php

namespace App\Http\Controllers;

use App\AccountStatus;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\LeadStatus;
use App\Models\Lead;
use App\Models\User;
use App\Services\AgentRecordsCleaner;
use App\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /** Columns the user list can be ordered by. */
    private const SORTABLE = ['name', 'created_at', 'leads_count', 'possible_leads_count', 'recent_leads_count', 'last_lead_at', 'last_seen_at'];

    /** A user whose session was active this recently counts as online. */
    private const ONLINE_MINUTES = 5;

    /** An active agent with no new leads for this many days counts as idle. */
    private const IDLE_DAYS = 7;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->toString();
        $role = $request->string('role')->toString();
        $sort = in_array($request->string('sort')->toString(), self::SORTABLE, true) ? $request->string('sort')->toString() : 'created_at';
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';

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
            $administrators = $this->sorted($adminQuery, $sort, $direction)->paginate(15, pageName: 'admins_page')->withQueryString();
            $administrators->through(fn (User $user): array => $this->mapUser($request, $user));
        }

        $agents = null;
        if ($role === '' || $role === UserRole::Agent->value) {
            $agents = $this->sorted($this->baseUserQuery($search, $status)->where('role', UserRole::Agent), $sort, $direction)
                ->paginate(15, pageName: 'agents_page')
                ->withQueryString();
            $agents->through(fn (User $user): array => $this->mapUser($request, $user));
        }

        return Inertia::render('users/index', [
            'administrators' => $administrators,
            'agents' => $agents,
            'summary' => $this->summary(),
            'filters' => [...$request->only(['search', 'role', 'status']), 'sort' => $sort, 'direction' => $direction],
        ]);
    }

    /**
     * @param  Builder<User>  $query
     * @param  'asc'|'desc'  $direction
     * @return Builder<User>
     */
    private function sorted(Builder $query, string $sort, string $direction): Builder
    {
        return $query->orderBy($sort, $direction)->orderByDesc('id');
    }

    /**
     * Team-wide headline figures for the list, independent of the search
     * and status filters so they always describe the whole team.
     *
     * @return array{users: int, active: int, inactive: int, agents: int, administrators: int, online: int, idle_agents: int, recent_leads: int, possible_leads: int}
     */
    private function summary(): array
    {
        $users = User::query()->where('role', '!=', UserRole::SuperAdministrator);
        $counts = (clone $users)->toBase()->selectRaw('COUNT(*) as users, COUNT(CASE WHEN status = ? THEN 1 END) as active, COUNT(CASE WHEN role = ? THEN 1 END) as agents', [AccountStatus::Active->value, UserRole::Agent->value])->first();
        $activeAgents = (clone $users)->where('role', UserRole::Agent)->where('status', AccountStatus::Active);
        $leads = Lead::query()->whereIn('agent_id', (clone $users)->select('id'))->toBase()
            ->selectRaw('COUNT(CASE WHEN created_at >= ? THEN 1 END) as recent, COUNT(CASE WHEN status = ? THEN 1 END) as possible', [now()->subDays(30), LeadStatus::PossibleLead->value])->first();

        return [
            'users' => (int) $counts->users,
            'active' => (int) $counts->active,
            'inactive' => (int) $counts->users - (int) $counts->active,
            'agents' => (int) $counts->agents,
            'administrators' => (int) $counts->users - (int) $counts->agents,
            'online' => DB::table(config('session.table'))->where('last_activity', '>=', now()->subMinutes(self::ONLINE_MINUTES)->getTimestamp())
                ->whereIn('user_id', (clone $users)->select('id'))->distinct()->count('user_id'),
            'idle_agents' => (clone $activeAgents)->whereDoesntHave('leads', fn (Builder $query) => $query->where('created_at', '>=', now()->subDays(self::IDLE_DAYS)))->count(),
            'recent_leads' => (int) $leads->recent,
            'possible_leads' => (int) $leads->possible,
        ];
    }

    /** @return Builder<User> */
    private function baseUserQuery(string $search, string $status): Builder
    {
        $query = User::query()
            ->select(['id', 'name', 'email', 'role', 'team', 'status', 'created_at'])
            ->selectSub(DB::table(config('session.table'))->selectRaw('MAX(last_activity)')->whereColumn('user_id', 'users.id'), 'last_seen_at')
            ->where('role', '!=', UserRole::SuperAdministrator)
            ->withCount([
                'leads',
                'uploadBatches',
                'leads as possible_leads_count' => fn (Builder $query) => $query->where('status', LeadStatus::PossibleLead),
                'leads as qualified_leads_count' => fn (Builder $query) => $query->where('status', LeadStatus::QualifiedLead),
                'leads as recent_leads_count' => fn (Builder $query) => $query->where('created_at', '>=', now()->subDays(30)),
            ])
            ->withMax('leads as last_lead_at', 'created_at')
            ->withExists(['gmailConnections as has_gmail' => fn (Builder $query) => $query->where('status', 'active')])
            ->withSum('uploadBatches as submitted_rows_sum', 'total_rows')
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
        $errors = (int) $user->rejected_rows_sum + (int) $user->error_rows_sum + (int) $user->duplicate_rows_sum;
        $submittedRows = (int) $user->submitted_rows_sum;
        $lastSeen = $user->getAttribute('last_seen_at');
        $lastLead = $user->getAttribute('last_lead_at');

        return [
            ...$user->only(['id', 'name', 'email', 'role', 'team', 'status', 'created_at']),
            'leads_count' => $user->leads_count,
            'possible_leads_count' => (int) $user->getAttribute('possible_leads_count'),
            'qualified_leads_count' => (int) $user->getAttribute('qualified_leads_count'),
            'recent_leads_count' => (int) $user->getAttribute('recent_leads_count'),
            'upload_batches_count' => $user->upload_batches_count,
            'errors_count' => $errors,
            'error_rate' => $submittedRows > 0 ? round(100 * $errors / $submittedRows, 1) : null,
            'last_lead_at' => $lastLead === null ? null : Carbon::parse($lastLead)->toIso8601String(),
            'last_seen_at' => $lastSeen === null ? null : Carbon::createFromTimestamp((int) $lastSeen)->toIso8601String(),
            'is_online' => $lastSeen !== null && (int) $lastSeen >= now()->subMinutes(self::ONLINE_MINUTES)->getTimestamp(),
            'has_gmail' => (bool) $user->getAttribute('has_gmail'),
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
