import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    Crown,
    Eraser,
    Mail,
    MoreHorizontal,
    Pencil,
    Plus,
    Radio,
    Search,
    ShieldCheck,
    Sparkles,
    Trash2,
    TrendingUp,
    UserRoundCog,
    UserRoundX,
    UsersRound,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { KpiCard } from '@/components/bi-visuals';
import { EmptyState } from '@/components/empty-state';
import { HeaderActionsPortal } from '@/components/header-actions';
import { Pagination } from '@/components/pagination';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { create, destroy, edit, impersonate, index } from '@/routes/users';
import { clear as clearRecords } from '@/routes/users/records';

const ALL_STATUSES = '__all__';
/** Mirrors UserController::IDLE_DAYS. */
const IDLE_DAYS = 7;

type User = {
    id: number;
    name: string;
    email: string;
    avatar: string | null;
    role: string;
    team: string | null;
    status: string;
    created_at: string;
    leads_count: number;
    possible_leads_count: number;
    qualified_leads_count: number;
    recent_leads_count: number;
    upload_batches_count: number;
    errors_count: number;
    error_rate: number | null;
    last_lead_at: string | null;
    last_seen_at: string | null;
    is_online: boolean;
    has_gmail: boolean;
    can_delete: boolean;
    can_impersonate: boolean;
    can_clear_records: boolean;
};
type PaginatedUsers = {
    data: User[];
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
};
type Summary = {
    users: number;
    active: number;
    inactive: number;
    agents: number;
    administrators: number;
    online: number;
    idle_agents: number;
    recent_leads: number;
    possible_leads: number;
};
type Filters = {
    search?: string;
    role?: string;
    status?: string;
    sort: string;
    direction: 'asc' | 'desc';
};
type Tab = 'agents' | 'administrators';

const relativeFormat = new Intl.RelativeTimeFormat(undefined, {
    numeric: 'auto',
});

function relativeTime(value: string | null): string {
    if (value === null) {
        return 'Never';
    }

    const seconds = (new Date(value).getTime() - Date.now()) / 1000;
    const units: Array<[Intl.RelativeTimeFormatUnit, number]> = [
        ['year', 31536000],
        ['month', 2592000],
        ['week', 604800],
        ['day', 86400],
        ['hour', 3600],
        ['minute', 60],
    ];

    for (const [unit, size] of units) {
        if (Math.abs(seconds) >= size) {
            return relativeFormat.format(Math.round(seconds / size), unit);
        }
    }

    return 'Just now';
}

function isIdle(user: User): boolean {
    return (
        user.status === 'active' &&
        (user.last_lead_at === null ||
            Date.now() - new Date(user.last_lead_at).getTime() >
                IDLE_DAYS * 86400000)
    );
}

function initials(name: string): string {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('');
}

function UserIdentity({ user }: { user: User }) {
    return (
        <div className="flex min-w-0 items-center gap-3">
            <span className="relative flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary">
                {user.avatar ? (
                    <img
                        src={user.avatar}
                        alt=""
                        className="size-9 rounded-full object-cover"
                    />
                ) : (
                    initials(user.name)
                )}
                {user.is_online && (
                    <span
                        className="absolute -right-0.5 -bottom-0.5 size-3 rounded-full border-2 border-card bg-success"
                        title="Online now"
                    >
                        <span className="sr-only">Online now</span>
                    </span>
                )}
            </span>
            <div className="min-w-0">
                <Link
                    href={edit(user.id)}
                    className="flex items-center gap-1.5 font-medium hover:underline"
                >
                    {user.role === 'super_administrator' && (
                        <Crown className="size-3.5 shrink-0 fill-amber-500 text-amber-500" />
                    )}
                    <span className="truncate">{user.name}</span>
                </Link>
                <p className="truncate text-xs text-muted-foreground">
                    {user.email}
                </p>
            </div>
        </div>
    );
}

type PendingAction = 'impersonate' | 'clear' | 'delete' | null;

function UserActions({ user }: { user: User }) {
    const [pending, setPending] = useState<PendingAction>(null);
    const hasMenu =
        user.can_impersonate || user.can_clear_records || user.can_delete;
    const close = () => setPending(null);

    return (
        <div className="flex items-center justify-end gap-1.5">
            <Button asChild size="sm" variant="outline" className="h-8">
                <Link href={edit(user.id)}>
                    <Pencil />
                    Edit
                </Link>
            </Button>
            {!hasMenu && <span className="size-8" aria-hidden="true" />}
            {hasMenu && (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            size="icon"
                            variant="ghost"
                            className="size-8"
                            aria-label={`More actions for ${user.name}`}
                        >
                            <MoreHorizontal />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="w-48">
                        {user.can_impersonate && (
                            <DropdownMenuItem
                                onSelect={() => setPending('impersonate')}
                            >
                                <UserRoundCog />
                                Log in as {user.name.split(' ')[0]}
                            </DropdownMenuItem>
                        )}
                        {user.can_clear_records && (
                            <DropdownMenuItem
                                onSelect={() => setPending('clear')}
                            >
                                <Eraser />
                                Clear records
                            </DropdownMenuItem>
                        )}
                        {user.can_delete && (
                            <>
                                {(user.can_impersonate ||
                                    user.can_clear_records) && (
                                    <DropdownMenuSeparator />
                                )}
                                <DropdownMenuItem
                                    variant="destructive"
                                    onSelect={() => setPending('delete')}
                                >
                                    <Trash2 />
                                    Delete user
                                </DropdownMenuItem>
                            </>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            )}

            <Dialog
                open={pending === 'impersonate'}
                onOpenChange={(open) => !open && close()}
            >
                <DialogContent>
                    <DialogTitle>Log in as {user.name}?</DialogTitle>
                    <DialogDescription>
                        You&apos;ll see the app exactly as they do. A banner
                        lets you return to your own account at any time. This is
                        recorded in the audit log.
                    </DialogDescription>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="secondary">Cancel</Button>
                        </DialogClose>
                        <Button
                            type="button"
                            onClick={() =>
                                router.post(impersonate.url(user.id))
                            }
                        >
                            <UserRoundCog />
                            Log in as {user.name}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={pending === 'clear'}
                onOpenChange={(open) => !open && close()}
            >
                <DialogContent>
                    <DialogTitle>Clear {user.name}&apos;s records?</DialogTitle>
                    <DialogDescription>
                        This deletes all {user.leads_count.toLocaleString()}{' '}
                        lead(s) and {user.upload_batches_count.toLocaleString()}{' '}
                        upload record(s) (including the raw files) owned by{' '}
                        {user.name}. The upload history cannot be recovered
                        afterward. Their user account is not affected.
                    </DialogDescription>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="secondary">Cancel</Button>
                        </DialogClose>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={() => {
                                if (
                                    !window.confirm(
                                        `Last chance: this permanently deletes ${user.leads_count.toLocaleString()} lead(s) and ${user.upload_batches_count.toLocaleString()} upload record(s) owned by ${user.name}. This cannot be undone. Continue?`,
                                    )
                                ) {
                                    return;
                                }

                                router.delete(clearRecords.url(user.id), {
                                    preserveScroll: true,
                                    onFinish: close,
                                });
                            }}
                        >
                            Clear records
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={pending === 'delete'}
                onOpenChange={(open) => !open && close()}
            >
                <DialogContent>
                    <DialogTitle>Delete {user.name}?</DialogTitle>
                    <DialogDescription>
                        This removes the user from active access. Their
                        historical leads and replies remain stored.
                    </DialogDescription>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="secondary">Cancel</Button>
                        </DialogClose>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={() =>
                                router.delete(destroy.url(user.id), {
                                    preserveScroll: true,
                                    onFinish: close,
                                })
                            }
                        >
                            Delete user
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}

function SortHeader({
    column,
    label,
    filters,
    align = 'left',
}: {
    column: string;
    label: string;
    filters: Filters;
    align?: 'left' | 'right';
}) {
    const active = filters.sort === column;
    const Icon = active
        ? filters.direction === 'desc'
            ? ArrowDown
            : ArrowUp
        : ArrowUpDown;

    return (
        <th
            scope="col"
            className={cn(
                'px-3 py-3 font-medium whitespace-nowrap',
                align === 'right' && 'text-right',
            )}
            aria-sort={
                active
                    ? filters.direction === 'desc'
                        ? 'descending'
                        : 'ascending'
                    : 'none'
            }
        >
            <button
                type="button"
                className={cn(
                    'inline-flex items-center gap-1 hover:text-foreground',
                    active && 'text-foreground',
                )}
                onClick={() =>
                    router.get(
                        index.url(),
                        {
                            ...filters,
                            sort: column,
                            direction:
                                active && filters.direction === 'desc'
                                    ? 'asc'
                                    : column === 'name'
                                      ? 'asc'
                                      : 'desc',
                        },
                        { preserveState: true, preserveScroll: true },
                    )
                }
            >
                {label}
                <Icon
                    className={cn('size-3', !active && 'opacity-40')}
                    aria-hidden="true"
                />
            </button>
        </th>
    );
}

function PlainHeader({
    children,
    align = 'left',
}: {
    children: ReactNode;
    align?: 'left' | 'right';
}) {
    return (
        <th
            scope="col"
            className={cn(
                'px-3 py-3 font-medium whitespace-nowrap',
                align === 'right' && 'text-right',
            )}
        >
            {children}
        </th>
    );
}

function ErrorRate({ user }: { user: User }) {
    if (user.error_rate === null) {
        return <span className="text-muted-foreground">—</span>;
    }

    const tone =
        user.error_rate >= 20
            ? 'text-destructive'
            : user.error_rate >= 10
              ? 'text-warning'
              : 'text-muted-foreground';

    return (
        <span
            className={cn('font-medium', tone)}
            title={`${user.errors_count.toLocaleString()} rejected, error or duplicate rows`}
        >
            {user.error_rate}%
        </span>
    );
}

function AgentsTable({ users, filters }: { users: User[]; filters: Filters }) {
    const maxLeads = Math.max(...users.map((user) => user.leads_count), 1);

    return (
        <table className="w-full min-w-[68rem] text-left text-sm">
            <thead className="border-b bg-muted/40 text-xs text-muted-foreground">
                <tr>
                    <SortHeader column="name" label="Agent" filters={filters} />
                    <PlainHeader>Team</PlainHeader>
                    <PlainHeader>Status</PlainHeader>
                    <SortHeader
                        column="leads_count"
                        label="Total leads"
                        filters={filters}
                        align="right"
                    />
                    <SortHeader
                        column="possible_leads_count"
                        label="Possible"
                        filters={filters}
                        align="right"
                    />
                    <SortHeader
                        column="recent_leads_count"
                        label="Last 30 days"
                        filters={filters}
                        align="right"
                    />
                    <PlainHeader align="right">Error rate</PlainHeader>
                    <PlainHeader>Gmail</PlainHeader>
                    <SortHeader
                        column="last_lead_at"
                        label="Last lead"
                        filters={filters}
                    />
                    <SortHeader
                        column="last_seen_at"
                        label="Last seen"
                        filters={filters}
                    />
                    <PlainHeader align="right">
                        <span className="sr-only">Actions</span>
                    </PlainHeader>
                </tr>
            </thead>
            <tbody className="divide-y">
                {users.map((user) => (
                    <tr key={user.id} className="hover:bg-muted/40">
                        <td className="px-3 py-3">
                            <UserIdentity user={user} />
                        </td>
                        <td className="px-3 py-3">{user.team || '—'}</td>
                        <td className="px-3 py-3">
                            <StatusBadge value={user.status} />
                        </td>
                        <td className="px-3 py-3 text-right">
                            <span className="relative ml-auto flex h-6 w-28 items-center justify-end">
                                <span
                                    className="absolute inset-y-1 left-0 rounded-r-[3px] bg-chart-1/20"
                                    style={{
                                        width: `${(user.leads_count / maxLeads) * 100}%`,
                                    }}
                                    aria-hidden="true"
                                />
                                <span className="relative font-semibold tabular-nums">
                                    {user.leads_count.toLocaleString()}
                                </span>
                            </span>
                        </td>
                        <td className="px-3 py-3 text-right tabular-nums">
                            {user.possible_leads_count.toLocaleString()}
                        </td>
                        <td className="px-3 py-3 text-right tabular-nums">
                            {user.recent_leads_count.toLocaleString()}
                        </td>
                        <td className="px-3 py-3 text-right tabular-nums">
                            <ErrorRate user={user} />
                        </td>
                        <td className="px-3 py-3">
                            {user.has_gmail ? (
                                <span className="inline-flex items-center gap-1 text-xs text-success">
                                    <Mail className="size-3.5" />
                                    Connected
                                </span>
                            ) : (
                                <span className="text-xs text-muted-foreground">
                                    Not connected
                                </span>
                            )}
                        </td>
                        <td className="px-3 py-3 whitespace-nowrap">
                            <span
                                title={
                                    user.last_lead_at
                                        ? new Date(
                                              user.last_lead_at,
                                          ).toLocaleString()
                                        : undefined
                                }
                            >
                                {relativeTime(user.last_lead_at)}
                            </span>
                            {isIdle(user) && (
                                <span className="ml-2 rounded-full bg-warning/15 px-1.5 py-0.5 text-[10px] font-medium text-warning">
                                    Idle
                                </span>
                            )}
                        </td>
                        <td className="px-3 py-3 whitespace-nowrap text-muted-foreground">
                            {user.is_online ? (
                                <span className="font-medium text-success">
                                    Online now
                                </span>
                            ) : (
                                relativeTime(user.last_seen_at)
                            )}
                        </td>
                        <td className="px-3 py-3">
                            <UserActions user={user} />
                        </td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

function AdministratorsTable({
    users,
    filters,
}: {
    users: User[];
    filters: Filters;
}) {
    return (
        <table className="w-full min-w-[56rem] text-left text-sm">
            <thead className="border-b bg-muted/40 text-xs text-muted-foreground">
                <tr>
                    <SortHeader
                        column="name"
                        label="Administrator"
                        filters={filters}
                    />
                    <PlainHeader>Role</PlainHeader>
                    <PlainHeader>Team</PlainHeader>
                    <PlainHeader>Status</PlainHeader>
                    <SortHeader
                        column="leads_count"
                        label="Leads owned"
                        filters={filters}
                        align="right"
                    />
                    <SortHeader
                        column="last_seen_at"
                        label="Last seen"
                        filters={filters}
                    />
                    <SortHeader
                        column="created_at"
                        label="Member since"
                        filters={filters}
                    />
                    <PlainHeader align="right">
                        <span className="sr-only">Actions</span>
                    </PlainHeader>
                </tr>
            </thead>
            <tbody className="divide-y">
                {users.map((user) => (
                    <tr key={user.id} className="hover:bg-muted/40">
                        <td className="px-3 py-3">
                            <UserIdentity user={user} />
                        </td>
                        <td className="px-3 py-3">
                            <span className="inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-xs capitalize">
                                <ShieldCheck className="size-3 text-primary" />
                                {user.role.replaceAll('_', ' ')}
                            </span>
                        </td>
                        <td className="px-3 py-3">{user.team || '—'}</td>
                        <td className="px-3 py-3">
                            <StatusBadge value={user.status} />
                        </td>
                        <td className="px-3 py-3 text-right font-medium tabular-nums">
                            {user.leads_count.toLocaleString()}
                        </td>
                        <td className="px-3 py-3 whitespace-nowrap text-muted-foreground">
                            {user.is_online ? (
                                <span className="font-medium text-success">
                                    Online now
                                </span>
                            ) : (
                                relativeTime(user.last_seen_at)
                            )}
                        </td>
                        <td className="px-3 py-3 whitespace-nowrap text-muted-foreground">
                            {new Date(user.created_at).toLocaleDateString(
                                undefined,
                                {
                                    year: 'numeric',
                                    month: 'short',
                                    day: 'numeric',
                                },
                            )}
                        </td>
                        <td className="px-3 py-3">
                            <UserActions user={user} />
                        </td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

function initialTab(administrators: PaginatedUsers | null): Tab {
    if (typeof window !== 'undefined') {
        const params = new URLSearchParams(window.location.search);

        if (
            params.get('tab') === 'administrators' ||
            params.has('admins_page')
        ) {
            return administrators ? 'administrators' : 'agents';
        }
    }

    return 'agents';
}

export default function UsersIndex({
    administrators,
    agents,
    summary,
    filters,
}: {
    administrators: PaginatedUsers | null;
    agents: PaginatedUsers | null;
    summary: Summary;
    filters: Filters;
}) {
    const [statusFilter, setStatusFilter] = useState(
        filters.status || ALL_STATUSES,
    );
    const [tab, setTab] = useState<Tab>(() => initialTab(administrators));
    const activeTab: Tab =
        tab === 'administrators' && administrators
            ? 'administrators'
            : agents
              ? 'agents'
              : 'administrators';
    const current = activeTab === 'agents' ? agents : administrators;
    const hasFilters = Boolean(filters.search || filters.status);

    useEffect(() => {
        const url = new URL(window.location.href);
        url.searchParams.set('tab', activeTab);
        window.history.replaceState(window.history.state, '', url);
    }, [activeTab]);

    const search = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        router.get(
            index.url(),
            {
                search: String(form.get('search') ?? ''),
                status: statusFilter === ALL_STATUSES ? '' : statusFilter,
                role: filters.role ?? '',
                sort: filters.sort,
                direction: filters.direction,
                tab: activeTab,
            },
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Users" />
            <HeaderActionsPortal>
                <Button asChild size="sm">
                    <Link href={create()}>
                        <Plus />
                        Add user
                    </Link>
                </Button>
            </HeaderActionsPortal>
            <div className="flex min-w-0 flex-1 flex-col gap-4 bg-muted/40 p-4 md:p-6">
                <header>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Team &amp; access
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Manage accounts, see who is active, and spot agents who
                        need attention.
                    </p>
                </header>

                <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3 2xl:grid-cols-6">
                    <KpiCard
                        label="Team members"
                        value={summary.users}
                        footnote={`${summary.active.toLocaleString()} active · ${summary.inactive.toLocaleString()} inactive`}
                        icon={UsersRound}
                    />
                    <KpiCard
                        label="Agents"
                        value={summary.agents}
                        footnote={`${summary.administrators.toLocaleString()} administrators`}
                        icon={ShieldCheck}
                    />
                    <KpiCard
                        label="Online now"
                        value={summary.online}
                        footnote="Active in the last 5 minutes"
                        icon={Radio}
                    />
                    <KpiCard
                        label="Idle agents"
                        value={summary.idle_agents}
                        footnote={`Active, no new leads in ${IDLE_DAYS} days`}
                        icon={UserRoundX}
                    />
                    <KpiCard
                        label="Leads · last 30 days"
                        value={summary.recent_leads}
                        footnote="Owned by team members"
                        icon={TrendingUp}
                    />
                    <KpiCard
                        label="Possible leads"
                        value={summary.possible_leads}
                        footnote="Current status, all time"
                        icon={Sparkles}
                    />
                </div>

                <section className="flex min-w-0 flex-col rounded-lg border bg-card shadow-xs">
                    <div className="flex flex-wrap items-end justify-between gap-3 border-b px-4 pt-3">
                        <nav
                            role="tablist"
                            aria-label="User lists"
                            className="-mb-px flex gap-1"
                        >
                            {(
                                [
                                    ['agents', 'Agents', agents],
                                    [
                                        'administrators',
                                        'Administrators',
                                        administrators,
                                    ],
                                ] as const
                            ).map(
                                ([key, label, list]) =>
                                    list && (
                                        <button
                                            key={key}
                                            type="button"
                                            role="tab"
                                            aria-selected={activeTab === key}
                                            onClick={() => setTab(key)}
                                            className={cn(
                                                'flex items-center gap-2 border-b-2 px-3 py-2.5 text-sm',
                                                activeTab === key
                                                    ? 'border-primary font-semibold text-foreground'
                                                    : 'border-transparent text-muted-foreground hover:text-foreground',
                                            )}
                                        >
                                            {label}
                                            <span className="rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground tabular-nums">
                                                {list.total.toLocaleString()}
                                            </span>
                                        </button>
                                    ),
                            )}
                        </nav>
                        <form
                            onSubmit={search}
                            className="flex flex-wrap items-center gap-2 pb-3"
                        >
                            <div className="relative">
                                <Search className="absolute top-2 left-2.5 size-4 text-muted-foreground" />
                                <Input
                                    name="search"
                                    defaultValue={filters.search}
                                    placeholder="Search name or email…"
                                    aria-label="Search users"
                                    className="h-8 w-56 pl-8"
                                />
                            </div>
                            <Select
                                value={statusFilter}
                                onValueChange={setStatusFilter}
                            >
                                <SelectTrigger
                                    size="sm"
                                    className="w-32"
                                    aria-label="Status"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL_STATUSES}>
                                        All statuses
                                    </SelectItem>
                                    <SelectItem value="active">
                                        Active
                                    </SelectItem>
                                    <SelectItem value="inactive">
                                        Inactive
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <Button type="submit" size="sm" variant="secondary">
                                Apply
                            </Button>
                            {hasFilters && (
                                <Button
                                    asChild
                                    size="sm"
                                    variant="ghost"
                                    className="text-muted-foreground"
                                >
                                    <Link
                                        href={index.url({
                                            query: { tab: activeTab },
                                        })}
                                    >
                                        <X />
                                        Clear
                                    </Link>
                                </Button>
                            )}
                        </form>
                    </div>

                    {current && current.data.length > 0 ? (
                        <>
                            <div className="overflow-x-auto">
                                {activeTab === 'agents' ? (
                                    <AgentsTable
                                        users={current.data}
                                        filters={filters}
                                    />
                                ) : (
                                    <AdministratorsTable
                                        users={current.data}
                                        filters={filters}
                                    />
                                )}
                            </div>
                            {current.links.length > 3 && (
                                <div className="border-t px-4 py-3">
                                    <Pagination links={current.links} />
                                </div>
                            )}
                        </>
                    ) : (
                        <EmptyState
                            icon={UsersRound}
                            title={`No ${activeTab} match your filters`}
                            description="Try a broader search or another status."
                        />
                    )}
                </section>
                <p className="text-xs text-muted-foreground">
                    Error rate = rejected, error and duplicate rows ÷ rows
                    uploaded. Idle = active agent with no new leads in the last{' '}
                    {IDLE_DAYS} days.
                </p>
            </div>
        </>
    );
}
UsersIndex.layout = { breadcrumbs: [{ title: 'Users', href: index() }] };
