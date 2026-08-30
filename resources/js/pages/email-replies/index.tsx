import { Form, Head, router, usePoll } from '@inertiajs/react';
import {
    AlertCircle,
    CheckCircle2,
    Inbox,
    Mail,
    RefreshCw,
    Sparkles,
    Unplug,
} from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { index, update } from '@/routes/email-replies';
import { connect, disconnect, sync } from '@/routes/gmail';

type Classification =
    'possible_lead' | 'not_lead' | 'needs_review' | 'automatic_reply';

type Reply = {
    id: number;
    sender_name: string | null;
    sender_email: string;
    subject: string | null;
    body_preview: string | null;
    body_text: string | null;
    actual_reply: string;
    classification: Classification;
    classification_reason: string | null;
    is_read: boolean;
    received_at: string;
    agent: { name: string } | null;
    lead: {
        id: number;
        lead_code: string;
        company_name: string;
        contact_person: string | null;
        email: string | null;
    } | null;
};

type Props = {
    replies: {
        data: Reply[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: Record<string, string>;
    connection: {
        id: number;
        gmail_address: string;
        status: string;
        last_synced_at: string | null;
        last_error: string | null;
    } | null;
    summary: { unread: number; possible: number; needs_review: number };
};

const classificationStyles: Record<Classification, string> = {
    possible_lead:
        'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    not_lead: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
    needs_review:
        'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    automatic_reply:
        'bg-slate-100 text-slate-700 dark:bg-slate-900 dark:text-slate-300',
};

function formatManilaDateTime(value: string): string {
    const date = new Date(value);
    const manilaTime = new Date(date.getTime() + 8 * 60 * 60 * 1000);
    const datePart = [
        String(manilaTime.getUTCMonth() + 1).padStart(2, '0'),
        String(manilaTime.getUTCDate()).padStart(2, '0'),
        manilaTime.getUTCFullYear(),
    ].join('/');
    const timePart = [
        String(manilaTime.getUTCHours()).padStart(2, '0'),
        String(manilaTime.getUTCMinutes()).padStart(2, '0'),
        String(manilaTime.getUTCSeconds()).padStart(2, '0'),
    ].join(':');

    return `${datePart}, ${timePart} PHT`;
}

function ClassificationBadge({ value }: { value: Classification }) {
    return (
        <span
            className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold capitalize ${classificationStyles[value]}`}
        >
            {value.replaceAll('_', ' ')}
        </span>
    );
}

export default function EmailRepliesIndex({
    replies,
    filters,
    connection,
    summary,
}: Props) {
    usePoll(30000, {
        only: ['replies', 'connection', 'summary'],
    });

    const filter = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get(
            index.url(),
            Object.fromEntries(new FormData(event.currentTarget)),
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Email Replies" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Email Replies"
                    description="Review Gmail replies matched securely to existing lead email addresses."
                />

                <Card className="overflow-hidden border-cyan-500/20 bg-gradient-to-br from-card to-cyan-500/5">
                    <CardContent className="flex flex-col justify-between gap-5 p-5 md:flex-row md:items-center">
                        <div className="flex items-start gap-4">
                            <div className="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-cyan-500/12 text-cyan-600 dark:text-cyan-300">
                                <Mail className="size-5" />
                            </div>
                            <div>
                                <p className="font-semibold">
                                    {connection
                                        ? connection.gmail_address
                                        : 'Connect an agent Gmail mailbox'}
                                </p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {connection
                                        ? `Status: ${connection.status.replaceAll('_', ' ')}${connection.last_synced_at ? ` · Last synced ${formatManilaDateTime(connection.last_synced_at)}` : ''}`
                                        : 'Only messages from email addresses belonging to your leads are saved.'}
                                </p>
                                {connection?.last_error && (
                                    <p className="mt-2 text-sm text-red-600 dark:text-red-400">
                                        {connection.last_error}
                                    </p>
                                )}
                            </div>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {connection ? (
                                <>
                                    <Form {...sync.form()}>
                                        {({ processing }) => (
                                            <Button
                                                type="submit"
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
                                                variant="outline"
                                                disabled={processing}
                                            >
                                                <Unplug />
                                                Disconnect
                                            </Button>
                                        )}
                                    </Form>
                                </>
                            ) : (
                                <Form {...connect.form()}>
                                    {({ processing }) => (
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            <Mail />
                                            Connect Gmail
                                        </Button>
                                    )}
                                </Form>
                            )}
                        </div>
                    </CardContent>
                </Card>

                <div className="grid gap-4 sm:grid-cols-3">
                    {[
                        {
                            label: 'Unread replies',
                            value: summary.unread,
                            icon: Inbox,
                            color: 'text-cyan-600 dark:text-cyan-300',
                        },
                        {
                            label: 'Possible leads',
                            value: summary.possible,
                            icon: Sparkles,
                            color: 'text-emerald-600 dark:text-emerald-300',
                        },
                        {
                            label: 'Needs review',
                            value: summary.needs_review,
                            icon: AlertCircle,
                            color: 'text-amber-600 dark:text-amber-300',
                        },
                    ].map((item) => {
                        const Icon = item.icon;

                        return (
                            <Card key={item.label}>
                                <CardContent className="flex items-center justify-between gap-4 p-5">
                                    <div>
                                        <p className="text-sm text-muted-foreground">
                                            {item.label}
                                        </p>
                                        <p className="mt-1 text-3xl font-bold">
                                            {item.value.toLocaleString('en-US')}
                                        </p>
                                    </div>
                                    <Icon className={`size-6 ${item.color}`} />
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>

                <form
                    onSubmit={filter}
                    className="grid gap-3 rounded-xl border bg-card p-4 md:grid-cols-4"
                >
                    <Input
                        name="search"
                        defaultValue={filters.search}
                        placeholder="Search sender, subject, or company…"
                        className="md:col-span-2"
                    />
                    <select
                        name="classification"
                        defaultValue={filters.classification}
                        className="h-9 rounded-md border bg-background px-3 text-sm"
                        aria-label="Reply classification"
                    >
                        <option value="">All classifications</option>
                        <option value="possible_lead">Possible lead</option>
                        <option value="not_lead">Not lead</option>
                        <option value="needs_review">Needs review</option>
                        <option value="automatic_reply">Automatic reply</option>
                    </select>
                    <div className="flex gap-2">
                        <label className="flex flex-1 items-center gap-2 rounded-md border px-3 text-sm">
                            <input
                                type="checkbox"
                                name="unread"
                                value="1"
                                defaultChecked={filters.unread === '1'}
                            />
                            Unread only
                        </label>
                        <Button type="submit" variant="secondary">
                            Apply
                        </Button>
                    </div>
                </form>

                <div className="grid gap-4">
                    {replies.data.map((reply) => (
                        <Card
                            key={reply.id}
                            className={
                                reply.is_read
                                    ? ''
                                    : 'border-cyan-500/30 bg-cyan-500/3'
                            }
                        >
                            <CardHeader className="gap-3 pb-3">
                                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-start">
                                    <div>
                                        <CardTitle className="text-base">
                                            {reply.subject || '(No subject)'}
                                        </CardTitle>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            {reply.sender_name
                                                ? `${reply.sender_name} · `
                                                : ''}
                                            {reply.sender_email} ·{' '}
                                            {formatManilaDateTime(
                                                reply.received_at,
                                            )}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        {!reply.is_read && (
                                            <span className="size-2 rounded-full bg-cyan-400" />
                                        )}
                                        <ClassificationBadge
                                            value={reply.classification}
                                        />
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <div className="rounded-xl border bg-background/60 p-4">
                                    <p className="text-sm leading-6 whitespace-pre-wrap">
                                        {reply.actual_reply ||
                                            reply.body_preview ||
                                            'No readable message body.'}
                                    </p>
                                </div>
                                <div className="flex flex-col justify-between gap-3 border-t pt-4 md:flex-row md:items-center">
                                    <div className="text-sm">
                                        <p className="font-medium">
                                            {reply.lead
                                                ? `${reply.lead.company_name} · ${reply.lead.lead_code}`
                                                : 'Deleted lead · Reply retained'}
                                        </p>
                                        <p className="text-muted-foreground">
                                            Owner:{' '}
                                            {reply.agent?.name || 'Unassigned'}{' '}
                                            · {reply.classification_reason}
                                        </p>
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        {!reply.is_read && (
                                            <Form {...update.form(reply.id)}>
                                                <input
                                                    type="hidden"
                                                    name="is_read"
                                                    value="1"
                                                />
                                                <Button
                                                    type="submit"
                                                    size="sm"
                                                    variant="outline"
                                                >
                                                    <CheckCircle2 /> Mark read
                                                </Button>
                                            </Form>
                                        )}
                                        {(
                                            [
                                                'possible_lead',
                                                'not_lead',
                                                'needs_review',
                                            ] as Classification[]
                                        ).map((classification) => (
                                            <Form
                                                key={classification}
                                                {...update.form(reply.id)}
                                            >
                                                <input
                                                    type="hidden"
                                                    name="classification"
                                                    value={classification}
                                                />
                                                <input
                                                    type="hidden"
                                                    name="is_read"
                                                    value="1"
                                                />
                                                <Button
                                                    type="submit"
                                                    size="sm"
                                                    variant={
                                                        reply.classification ===
                                                        classification
                                                            ? 'secondary'
                                                            : 'outline'
                                                    }
                                                >
                                                    {classification
                                                        .replaceAll('_', ' ')
                                                        .replace(
                                                            /^./,
                                                            (value) =>
                                                                value.toUpperCase(),
                                                        )}
                                                </Button>
                                            </Form>
                                        ))}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                    {!replies.data.length && (
                        <Card>
                            <CardContent className="flex flex-col items-center gap-3 p-12 text-center">
                                <Inbox className="size-8 text-muted-foreground" />
                                <div>
                                    <p className="font-medium">
                                        No matched replies yet
                                    </p>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Connect Gmail and synchronize to check
                                        for messages from your lead email
                                        addresses.
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </div>
                <Pagination links={replies.links} />
            </div>
        </>
    );
}

EmailRepliesIndex.layout = {
    breadcrumbs: [{ title: 'Email Replies', href: index() }],
};
