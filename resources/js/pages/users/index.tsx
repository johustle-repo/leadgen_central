import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, Search, Trash2 } from 'lucide-react';
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
import { create, destroy, edit, index } from '@/routes/users';
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
                <form
                    onSubmit={search}
                    className="grid gap-3 rounded-xl border bg-card p-4 sm:grid-cols-4"
                >
                    <div className="relative sm:col-span-2">
                        <Search className="absolute top-2.5 left-3 size-4 text-muted-foreground" />
                        <Input
                            name="search"
                            defaultValue={filters.search}
                            placeholder="Search name or email…"
                            className="pl-9"
                        />
                    </div>
                    <select
                        name="role"
                        defaultValue={filters.role}
                        className="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="">All roles</option>
                        <option value="administrator">Administrator</option>
                        <option value="sub_administrator">
                            Sub-Administrator
                        </option>
                        <option value="agent">Agent</option>
                    </select>
                    <Button type="submit" variant="secondary">
                        Apply filters
                    </Button>
                </form>
                <div className="overflow-hidden rounded-xl border bg-card">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/60 text-left">
                                <tr>
                                    <th className="p-3">User</th>
                                    <th className="p-3">Role</th>
                                    <th className="p-3">Team</th>
                                    <th className="p-3">Status</th>
                                    <th className="p-3 text-center">
                                        Total leads
                                    </th>
                                    <th className="p-3 text-center">Replies</th>
                                    <th className="p-3">Sequence</th>
                                    <th className="p-3">Created</th>
                                    <th className="p-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {users.data.map((user) => (
                                    <tr key={user.id}>
                                        <td className="p-3">
                                            <Link
                                                href={edit(user.id)}
                                                className="font-medium hover:underline"
                                            >
                                                {user.name}
                                            </Link>
                                            <div className="text-xs text-muted-foreground">
                                                {user.email}
                                            </div>
                                        </td>
                                        <td className="p-3 capitalize">
                                            {user.role.replaceAll('_', ' ')}
                                        </td>
                                        <td className="p-3">
                                            {user.team || '—'}
                                        </td>
                                        <td className="p-3">
                                            <StatusBadge value={user.status} />
                                        </td>
                                        <td className="p-3 text-center font-medium tabular-nums">
                                            {user.leads_count.toLocaleString()}
                                        </td>
                                        <td className="p-3 text-center font-medium tabular-nums">
                                            {user.email_replies_count.toLocaleString()}
                                        </td>
                                        <td className="p-3">
                                            <button
                                                type="button"
                                                role="switch"
                                                aria-checked={
                                                    user.email_sequence_enabled
                                                }
                                                aria-label={`${user.email_sequence_enabled ? 'Pause' : 'Enable'} email sequence for ${user.name}`}
                                                onClick={() =>
                                                    toggleSequence(user)
                                                }
                                                className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none ${user.email_sequence_enabled ? 'bg-emerald-500' : 'bg-muted-foreground/35'}`}
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
                                        </td>
                                        <td className="p-3">
                                            {new Date(
                                                user.created_at,
                                            ).toLocaleDateString()}
                                        </td>
                                        <td className="p-3 text-right">
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
                                                                Delete{' '}
                                                                {user.name}?
                                                            </DialogTitle>
                                                            <DialogDescription>
                                                                This removes the
                                                                user from active
                                                                access. Their
                                                                historical leads
                                                                and replies
                                                                remain stored.
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
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
                <Pagination links={users.links} />
            </div>
        </>
    );
}
UsersIndex.layout = { breadcrumbs: [{ title: 'Users', href: index() }] };
