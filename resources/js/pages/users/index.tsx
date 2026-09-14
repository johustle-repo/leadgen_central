import { Head, Link, router } from '@inertiajs/react';
import {
    Crown,
    Eraser,
    Pencil,
    Plus,
    Search,
    SlidersHorizontal,
    Trash2,
    UserRoundCog,
    UsersRound,
} from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import { FilterBar } from '@/components/filter-bar';
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
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { create, destroy, edit, impersonate, index } from '@/routes/users';
import { clear as clearRecords } from '@/routes/users/records';

const ALL_ROLES = '__all__';

type User = {
    id: number;
    name: string;
    email: string;
    role: string;
    team: string | null;
    status: string;
    created_at: string;
    leads_count: number;
    upload_batches_count: number;
    gmail_status: string | null;
    gmail_error: string | null;
    can_delete: boolean;
    can_impersonate: boolean;
    can_clear_records: boolean;
};
type PaginatedUsers = {
    data: User[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

function GmailStatusCell({ user }: { user: User }) {
    if (!user.gmail_status) {
        return <span className="text-xs text-muted-foreground">Not connected</span>;
    }

    return (
        <div>
            <StatusBadge value={user.gmail_status} />
            {user.gmail_error && (
                <p
                    className="mt-1 max-w-48 truncate text-xs text-destructive"
                    title={user.gmail_error}
                >
                    {user.gmail_error}
                </p>
            )}
        </div>
    );
}

function UserActions({ user }: { user: User }) {
    return (
        <div className="flex justify-end gap-2">
            <Button asChild size="sm" variant="outline">
                <Link href={edit(user.id)}>
                    <Pencil />
                    Edit
                </Link>
            </Button>
            {user.can_impersonate && (
                <Dialog>
                    <DialogTrigger asChild>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            className="border-amber-500/30 bg-amber-500/10 text-amber-700 hover:bg-amber-500/15 hover:text-amber-800 dark:text-amber-300 dark:hover:text-amber-200"
                        >
                            <UserRoundCog />
                            Log in as
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogTitle>Log in as {user.name}?</DialogTitle>
                        <DialogDescription>
                            You&apos;ll see the app exactly as they do. A
                            banner lets you return to your own account at any
                            time. This is recorded in the audit log.
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
            )}
            {user.can_clear_records && (
                <Dialog>
                    <DialogTrigger asChild>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            className="border-rose-500/30 bg-rose-500/10 text-rose-700 hover:bg-rose-500/15 hover:text-rose-800 dark:text-rose-300 dark:hover:text-rose-200"
                        >
                            <Eraser />
                            Clear records
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogTitle>
                            Clear {user.name}&apos;s records?
                        </DialogTitle>
                        <DialogDescription>
                            This deletes all{' '}
                            {user.leads_count.toLocaleString()} lead(s) and{' '}
                            {user.upload_batches_count.toLocaleString()}{' '}
                            upload record(s) (including the raw files) owned
                            by {user.name}. The upload history cannot be
                            recovered afterward. Their user account is not
                            affected.
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

                                    router.delete(
                                        clearRecords.url(user.id),
                                        { preserveScroll: true },
                                    );
                                }}
                            >
                                Clear records
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            )}
            {user.can_delete && (
                <Dialog>
                    <DialogTrigger asChild>
                        <Button type="button" size="sm" variant="destructive">
                            <Trash2 />
                            Delete
                        </Button>
                    </DialogTrigger>
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
                                    })
                                }
                            >
                                Delete user
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            )}
        </div>
    );
}

function UsersSection({
    title,
    users,
    emptyDescription,
}: {
    title: string;
    users: PaginatedUsers;
    emptyDescription: string;
}) {
    return (
        <section className="flex flex-col gap-3">
            <h2 className="px-1 text-sm font-semibold text-muted-foreground">
                {title}
            </h2>
            {users.data.length ? (
                <>
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead>User</TableHead>
                                <TableHead>Role</TableHead>
                                <TableHead>Team</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead align="right">
                                    Total leads
                                </TableHead>
                                <TableHead>Gmail</TableHead>
                                <TableHead>Created</TableHead>
                                <TableHead align="right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {users.data.map((user) => (
                                <TableRow key={user.id}>
                                    <TableCell>
                                        <Link
                                            href={edit(user.id)}
                                            className="font-medium hover:underline"
                                        >
                                            {user.name}
                                        </Link>
                                        <div className="text-xs text-muted-foreground">
                                            {user.email}
                                        </div>
                                    </TableCell>
                                    <TableCell className="capitalize">
                                        <span className="inline-flex items-center gap-1.5">
                                            {user.role ===
                                                'super_administrator' && (
                                                <Crown className="size-3.5 shrink-0 fill-amber-500 text-amber-500" />
                                            )}
                                            {user.role.replaceAll('_', ' ')}
                                        </span>
                                    </TableCell>
                                    <TableCell>{user.team || '—'}</TableCell>
                                    <TableCell>
                                        <StatusBadge value={user.status} />
                                    </TableCell>
                                    <TableCell
                                        align="right"
                                        className="font-medium"
                                    >
                                        {user.leads_count.toLocaleString()}
                                    </TableCell>
                                    <TableCell>
                                        <GmailStatusCell user={user} />
                                    </TableCell>
                                    <TableCell>
                                        {new Date(
                                            user.created_at,
                                        ).toLocaleDateString()}
                                    </TableCell>
                                    <TableCell align="right">
                                        <UserActions user={user} />
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                    <Pagination links={users.links} />
                </>
            ) : (
                <div className="rounded-xl border bg-card">
                    <EmptyState
                        icon={UsersRound}
                        title={`No ${title.toLowerCase()} match your filters`}
                        description={emptyDescription}
                    />
                </div>
            )}
        </section>
    );
}

export default function UsersIndex({
    administrators,
    agents,
    filters,
}: {
    administrators: PaginatedUsers | null;
    agents: PaginatedUsers | null;
    filters: Record<string, string>;
}) {
    const [roleFilter, setRoleFilter] = useState(filters.role || ALL_ROLES);

    const search = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get(
            index.url(),
            Object.fromEntries(new FormData(event.currentTarget)),
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Users" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <HeaderActionsPortal>
                    <Button asChild size="sm">
                        <Link href={create()}>
                            <Plus />
                            Add user
                        </Link>
                    </Button>
                </HeaderActionsPortal>
                <FilterBar
                    as="form"
                    onSubmit={search}
                    icon={SlidersHorizontal}
                    label="Filters"
                >
                    <div className="flex flex-col gap-1.5 sm:col-span-2">
                        <label
                            htmlFor="users-search"
                            className="text-xs text-muted-foreground"
                        >
                            Search
                        </label>
                        <div className="relative">
                            <Search className="absolute top-2.5 left-3 size-4 text-muted-foreground" />
                            <Input
                                id="users-search"
                                name="search"
                                defaultValue={filters.search}
                                placeholder="Search name or email…"
                                className="pl-9"
                            />
                        </div>
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <label
                            htmlFor="users-role"
                            className="text-xs text-muted-foreground"
                        >
                            Role
                        </label>
                        <input
                            type="hidden"
                            name="role"
                            value={roleFilter === ALL_ROLES ? '' : roleFilter}
                        />
                        <Select
                            value={roleFilter}
                            onValueChange={setRoleFilter}
                        >
                            <SelectTrigger id="users-role">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL_ROLES}>
                                    All roles
                                </SelectItem>
                                <SelectItem value="administrator">
                                    Administrator
                                </SelectItem>
                                <SelectItem value="sub_administrator">
                                    Sub-Administrator
                                </SelectItem>
                                <SelectItem value="agent">Agent</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="flex flex-col justify-end">
                        <Button type="submit" variant="secondary">
                            Apply filters
                        </Button>
                    </div>
                </FilterBar>
                {administrators && (
                    <UsersSection
                        title="Administrators"
                        users={administrators}
                        emptyDescription="Try a broader search or another status."
                    />
                )}
                {agents && (
                    <UsersSection
                        title="Agents"
                        users={agents}
                        emptyDescription="Try a broader search or another status."
                    />
                )}
            </div>
        </>
    );
}
UsersIndex.layout = { breadcrumbs: [{ title: 'Users', href: index() }] };
