import { Form, Head } from '@inertiajs/react';
import { IdCard } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index, store, update } from '@/routes/users';
type User = {
    id: number;
    name: string;
    email: string;
    role: string;
    team: string | null;
    status: string;
};
export default function UserForm({
    managedUser,
    roles,
    statuses,
}: {
    managedUser: User | null;
    roles: string[];
    statuses: string[];
}) {
    return (
        <>
            <Head title={managedUser ? 'Edit User' : 'Add User'} />
            <div className="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-6 p-4 md:p-6">
                <Form
                    {...(managedUser
                        ? update.form(managedUser.id)
                        : store.form())}
                >
                    {({ errors, processing }) => (
                        <Card>
                            <CardHeader>
                                <div className="flex items-center gap-3">
                                    <div className="rounded-lg bg-primary/10 p-2 text-primary">
                                        <IdCard className="size-5" />
                                    </div>
                                    <div>
                                        <CardTitle>Account details</CardTitle>
                                        <CardDescription>
                                            Identity, access role, team, and
                                            account status.
                                        </CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="grid gap-5 sm:grid-cols-2">
                                {[
                                    ['name', 'Name', 'text'],
                                    ['email', 'Email', 'email'],
                                    ['team', 'Team (optional)', 'text'],
                                    [
                                        'password',
                                        managedUser
                                            ? 'New password (optional)'
                                            : 'Password',
                                        'password',
                                    ],
                                    [
                                        'password_confirmation',
                                        'Confirm password',
                                        'password',
                                    ],
                                ].map(([name, label, type]) => (
                                    <div
                                        key={name}
                                        className={
                                            name === 'name' || name === 'email'
                                                ? 'sm:col-span-2'
                                                : ''
                                        }
                                    >
                                        <Label htmlFor={name}>{label}</Label>
                                        <Input
                                            id={name}
                                            name={name}
                                            type={type}
                                            defaultValue={
                                                name === 'password' ||
                                                name === 'password_confirmation'
                                                    ? ''
                                                    : String(
                                                          managedUser?.[
                                                              name as keyof User
                                                          ] ?? '',
                                                      )
                                            }
                                            required={
                                                (!managedUser &&
                                                    (name === 'password' ||
                                                        name ===
                                                            'password_confirmation')) ||
                                                name === 'name' ||
                                                name === 'email'
                                            }
                                            className="mt-2"
                                        />
                                        <InputError
                                            className="mt-1"
                                            message={errors[name]}
                                        />
                                    </div>
                                ))}
                                <div>
                                    <Label htmlFor="role">Role</Label>
                                    <Select
                                        name="role"
                                        defaultValue={
                                            managedUser?.role ?? 'agent'
                                        }
                                    >
                                        <SelectTrigger
                                            id="role"
                                            className="mt-2 w-full"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {roles.map((role) => (
                                                <SelectItem
                                                    key={role}
                                                    value={role}
                                                    className="capitalize"
                                                >
                                                    {role.replaceAll('_', ' ')}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div>
                                    <Label htmlFor="status">Status</Label>
                                    <Select
                                        name="status"
                                        defaultValue={
                                            managedUser?.status ?? 'active'
                                        }
                                    >
                                        <SelectTrigger
                                            id="status"
                                            className="mt-2 w-full"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {statuses.map((status) => (
                                                <SelectItem
                                                    key={status}
                                                    value={status}
                                                >
                                                    {status}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        className="mt-1"
                                        message={errors.status}
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="sm:col-span-2"
                                >
                                    {processing ? 'Saving…' : 'Save user'}
                                </Button>
                            </CardContent>
                        </Card>
                    )}
                </Form>
            </div>
        </>
    );
}
UserForm.layout = { breadcrumbs: [{ title: 'Users', href: index() }] };
