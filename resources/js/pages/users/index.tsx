import { Head, Link, router } from '@inertiajs/react';
import {
    Crown,
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
import { PageHeader } from '@/components/page-header';
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

const ALL_ROLES = '__all__';
import { toggle } from '@/routes/users/email-sequence';
type User = {
    id: number;
    name: string;
    email: string;
    role: string;
    team: string | null;
    status: string;
    created_at: string;
    leads_count: number;
    email_replies_count: number;
    email_sequence_enabled: boolean;
    can_delete: boolean;
    can_impersonate: boolean;
};
export default function UsersIndex({
    users,
    filters,
}: {
    users: {
        data: User[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: Record<string, string>;
}) {
    const [roleFilter, setRoleFilter] = useState(filters.role || ALL_ROLES);

    const toggleSequence = (user: User) => {
        router.patch(
            toggle.url(user.id),
            { is_active: !user.email_sequence_enabled },
            { preserveScroll: true },
        );
    };

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
                <PageHeader
                    title="User management"
                    description="Create accounts and control roles and access status."
                    actions={
                        <Button asChild>
                            <Link href={create()}>
                                <Plus />
                                Add user
                            </Link>
                        </Button>
                    }
                />
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
                                <SelectItem value="super_administrator">
                                    Super Administrator
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
                {users.data.length ? (
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead>User</TableHead>
                                <TableHead>Role</TableHead>
                                <TableHead>Team</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead align="right">Total leads</TableHead>
                                <TableHead align="right">Replies</TableHead>
                                <TableHead>Sequence</TableHead>
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
                                    <TableCell
                                        align="right"
                                        className="font-medium"
                                    >
                                        {user.email_replies_count.toLocaleString()}
                                    </TableCell>
                                    <TableCell>
                                        <button
                                            type="button"
                                            role="switch"
                                            aria-checked={
                                                user.email_sequence_enabled
                                            }
                                            aria-label={`${user.email_sequence_enabled ? 'Pause' : 'Enable'} email sequence for ${user.name}`}
                                            onClick={() => toggleSequence(user)}
                                            className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none ${user.email_sequence_enabled ? 'bg-success' : 'bg-muted-foreground/35'}`}
                                        >
                                            <span
                                                className={`inline-block size-4 rounded-full bg-white shadow-sm transition-transform ${user.email_sequence_enabled ? 'translate-x-6' : 'translate-x-1'}`}
                                            />
                                        </button>
                                        <span className="ml-2 align-top text-xs text-muted-foreground">
                                            {user.email_sequence_enabled
                                                ? 'Active'
                                                : 'Paused'}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        {new Date(
                                            user.created_at,
                                        ).toLocaleDateString()}
                                    </TableCell>
                                    <TableCell align="right">
                                        <div className="flex justify-end gap-2">
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="outline"
                                            >
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
                                                        <DialogTitle>
                                                            Log in as{' '}
                                                            {user.name}?
                                                        </DialogTitle>
                                                        <DialogDescription>
                                                            You&apos;ll see the
                                                            app exactly as they
                                                            do. A banner lets
                                                            you return to your
                                                            own account at any
                                                            time. This is
                                                            recorded in the
                                                            audit log.
                                                        </DialogDescription>
                                                        <DialogFooter>
                                                            <DialogClose
                                                                asChild
                                                            >
                                                                <Button variant="secondary">
                                                                    Cancel
                                                                </Button>
                                                            </DialogClose>
                                                            <Button
                                                                type="button"
                                                                onClick={() =>
                                                                    router.post(
                                                                        impersonate.url(
                                                                            user.id,
                                                                        ),
                                                                    )
                                                                }
                                                            >
                                                                <UserRoundCog />
                                                                Log in as{' '}
                                                                {user.name}
                                                            </Button>
                                                        </DialogFooter>
                                                    </DialogContent>
                                                </Dialog>
                                            )}
                                            {user.can_delete && (
                                                <Dialog>
                                                    <DialogTrigger asChild>
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="destructive"
                                                        >
                                                            <Trash2 />
                                                            Delete
                                                        </Button>
                                                    </DialogTrigger>
                                                    <DialogContent>
                                                        <DialogTitle>
                                                            Delete {user.name}?
                                                        </DialogTitle>
                                                        <DialogDescription>
                                                            This removes the
                                                            user from active
                                                            access. Their
                                                            historical leads and
                                                            replies remain
                                                            stored.
                                                        </DialogDescription>
                                                        <DialogFooter>
                                                            <DialogClose
                                                                asChild
                                                            >
                                                                <Button variant="secondary">
                                                                    Cancel
                                                                </Button>
                                                            </DialogClose>
                                                            <Button
                                                                type="button"
                                                                variant="destructive"
                                                                onClick={() =>
                                                                    router.delete(
                                                                        destroy.url(
                                                                            user.id,
                                                                        ),
                                                                        {
                                                                            preserveScroll: true,
                                                                        },
                                                                    )
                                                                }
                                                            >
                                                                Delete user
                                                            </Button>
                                                        </DialogFooter>
                                                    </DialogContent>
                                                </Dialog>
                                            )}
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                ) : (
                    <div className="rounded-xl border bg-card">
                        <EmptyState
                            icon={UsersRound}
                            title="No users match your filters"
                            description="Try a broader search or another role."
                        />
                    </div>
                )}
                <Pagination links={users.links} />
            </div>
        </>
    );
}
UsersIndex.layout = { breadcrumbs: [{ title: 'Users', href: index() }] };
