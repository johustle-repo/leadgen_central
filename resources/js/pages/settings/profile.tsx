import { Form, Head, usePage } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import { Mail, RefreshCw, Unplug, UserRound } from 'lucide-react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/delete-user';
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
import { connect, disconnect, sync } from '@/routes/gmail';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import type { Auth } from '@/types';

type PageProps = {
    auth: Auth;
};

type GmailConnection = {
    id: number;
    gmail_address: string;
    status: string;
    last_synced_at: string | null;
    last_error: string | null;
};

export default function Profile({
    mustVerifyEmail,
    status,
    gmailConnection,
}: {
    mustVerifyEmail: boolean;
    status?: string;
    gmailConnection: GmailConnection | null;
}) {
    const { auth } = usePage<PageProps>().props;

    return (
        <>
            <Head title="Profile settings" />

            <h1 className="sr-only">Profile settings</h1>

            <Form
                {...ProfileController.update.form()}
                options={{
                    preserveScroll: true,
                }}
            >
                {({ processing, errors }) => (
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-3">
                                <div className="rounded-lg bg-primary/10 p-2 text-primary">
                                    <UserRound className="size-5" />
                                </div>
                                <div>
                                    <CardTitle>Profile</CardTitle>
                                    <CardDescription>
                                        Update your name and email address.
                                    </CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>

                                <Input
                                    id="name"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.name}
                                    name="name"
                                    required
                                    autoComplete="name"
                                    placeholder="Full name"
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.name}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">Email address</Label>

                                <Input
                                    id="email"
                                    type="email"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.email}
                                    name="email"
                                    required
                                    autoComplete="username"
                                    placeholder="Email address"
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.email}
                                />
                            </div>

                            {mustVerifyEmail &&
                                auth.user.email_verified_at === null && (
                                    <div>
                                        <p className="-mt-4 text-sm text-muted-foreground">
                                            Your email address is unverified.{' '}
                                            <Link
                                                href={send()}
                                                as="button"
                                                className="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                                            >
                                                Click here to re-send the
                                                verification email.
                                            </Link>
                                        </p>

                                        {status ===
                                            'verification-link-sent' && (
                                            <div className="mt-2 text-sm font-medium text-success">
                                                A new verification link has been
                                                sent to your email address.
                                            </div>
                                        )}
                                    </div>
                                )}

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="update-profile-button"
                                >
                                    Save
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </Form>

            <Card>
                <CardHeader>
                    <div className="flex items-center gap-3">
                        <div className="rounded-lg bg-primary/10 p-2 text-primary">
                            <Mail className="size-5" />
                        </div>
                        <div>
                            <CardTitle>Gmail connection</CardTitle>
                            <CardDescription>
                                Connect your Gmail mailbox so replies to your
                                outreach can be captured automatically.
                            </CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent className="space-y-4">
                    {gmailConnection ? (
                        <>
                            <div className="text-sm">
                                <p className="font-medium">
                                    {gmailConnection.gmail_address}
                                </p>
                                <p className="text-muted-foreground">
                                    {gmailConnection.last_synced_at
                                        ? `Last synced ${gmailConnection.last_synced_at}`
                                        : 'Not synced yet'}
                                </p>
                                {gmailConnection.last_error && (
                                    <p className="mt-1 text-destructive">
                                        {gmailConnection.last_error}
                                    </p>
                                )}
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <Form {...sync.form()}>
                                    {({ processing }) => (
                                        <Button
                                            type="submit"
                                            size="sm"
                                            disabled={processing}
                                        >
                                            <RefreshCw
                                                className={
                                                    processing
                                                        ? 'animate-spin'
                                                        : ''
                                                }
                                            />
                                            Sync now
                                        </Button>
                                    )}
                                </Form>
                                <Form {...disconnect.form()}>
                                    {({ processing }) => (
                                        <Button
                                            type="submit"
                                            size="sm"
                                            variant="outline"
                                            disabled={processing}
                                        >
                                            <Unplug />
                                            Disconnect
                                        </Button>
                                    )}
                                </Form>
                            </div>
                        </>
                    ) : (
                        <Form {...connect.form()}>
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    size="sm"
                                    disabled={processing}
                                >
                                    <Mail />
                                    Connect Gmail
                                </Button>
                            )}
                        </Form>
                    )}
                </CardContent>
            </Card>

            <DeleteUser />
        </>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: 'Profile settings',
            href: edit(),
        },
    ],
};
