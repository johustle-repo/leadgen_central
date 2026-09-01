import { Form, Head, router, usePoll } from '@inertiajs/react';
import {
    AlertCircle,
    CheckCheck,
    CheckCircle2,
    ChevronRight,
    Inbox,
    Mail,
    MailOpen,
    RefreshCw,
    Sparkles,
    Unplug,
} from 'lucide-react';
import { useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { index, markAllRead, update } from '@/routes/email-replies';
import { connect, disconnect, sync } from '@/routes/gmail';

type Classification =
    | 'bounce'
    | 'interested'
    | 'not_interested'
    | 'not_now'
    | 'do_not_contact'
    | 'possible_lead'
    | 'not_lead'
    | 'needs_review'
    | 'retired'
    | 'out_of_office'
    | 'automatic_reply';

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
    bounce: 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300',
    interested:
        'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    not_interested: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
    not_now: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300',
    do_not_contact:
        'bg-fuchsia-100 text-fuchsia-800 dark:bg-fuchsia-950 dark:text-fuchsia-300',
    possible_lead:
        'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    not_lead: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
    needs_review:
        'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    retired:
        'bg-orange-100 text-orange-800 dark:bg-orange-950 dark:text-orange-300',
    out_of_office:
        'bg-violet-100 text-violet-800 dark:bg-violet-950 dark:text-violet-300',
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

const manualClassifications: Classification[] = [
    'interested',
    'not_interested',
    'not_now',
    'do_not_contact',
    'bounce',
    'needs_review',
];

export default function EmailRepliesIndex({
    replies,
    filters,
    connection,
    summary,
}: Props) {
    const [selectedReplyId, setSelectedReplyId] = useState<number | null>(null);
    const selectedReply =
        replies.data.find((reply) => reply.id === selectedReplyId) ?? null;

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
                            <Form {...markAllRead.form()}>
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        disabled={
                                            processing || summary.unread === 0
                                        }
                                    >
                                        <CheckCheck />
                                        Mark all as read
                                    </Button>
                                )}
                            </Form>
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
                            label: 'Interested replies',
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
                    className="grid gap-3 rounded-xl border bg-card p-4 md:grid-cols-5"
                >
                    <Input
                        name="search"
                        defaultValue={filters.search}
                        placeholder="Search sender, subject, or company…"
                        className="md:col-span-2"
                    />
                    <Input
                        name="date"
                        type="date"
                        defaultValue={filters.date}
                        aria-label="Reply date"
                    />
                    <select
                        name="classification"
                        defaultValue={filters.classification}
                        className="h-9 rounded-md border bg-background px-3 text-sm"
                        aria-label="Reply classification"
                    >
                        <option value="">All classifications</option>
                        <option value="interested">Interested</option>
                        <option value="not_interested">Not interested</option>
                        <option value="not_now">Not now</option>
                        <option value="do_not_contact">Do not contact</option>
                        <option value="bounce">Bounce</option>
                        <option value="retired">Retired / left company</option>
                        <option value="out_of_office">Out of office</option>
                        <option value="needs_review">Needs review</option>
                        <option value="automatic_reply">Automatic reply</option>
                        <option value="possible_lead">
                            Possible lead (legacy)
                        </option>
                        <option value="not_lead">Not lead (legacy)</option>
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

                <Card className="overflow-hidden">
                    {!!replies.data.length && (
                        <div className="hidden grid-cols-[minmax(180px,0.8fr)_minmax(0,1.8fr)_auto] gap-5 border-b bg-muted/30 px-5 py-2.5 text-xs font-semibold tracking-wide text-muted-foreground uppercase md:grid">
                            <span>Sender</span>
                            <span>Message</span>
                            <span className="pr-8">Classification</span>
                        </div>
                    )}
                    <div className="divide-y">
                        {replies.data.map((reply) => (
                            <button
                                key={reply.id}
                                type="button"
                                onClick={() => setSelectedReplyId(reply.id)}
                                className={`group grid w-full gap-3 px-4 py-4 text-left transition-colors hover:bg-accent/60 focus-visible:bg-accent/60 focus-visible:outline-none md:grid-cols-[minmax(180px,0.8fr)_minmax(0,1.8fr)_auto] md:items-center md:gap-5 md:px-5 ${reply.is_read ? 'bg-card' : 'bg-cyan-500/5'}`}
                            >
                                <div className="flex min-w-0 items-center gap-3">
                                    <span
                                        className={`size-2 shrink-0 rounded-full ${reply.is_read ? 'bg-transparent' : 'bg-cyan-400 shadow-[0_0_10px_rgba(34,211,238,0.75)]'}`}
                                    />
                                    <div className="min-w-0">
                                        <p
                                            className={`truncate text-sm ${reply.is_read ? 'font-medium' : 'font-bold'}`}
                                        >
                                            {reply.sender_name ||
                                                reply.sender_email}
                                        </p>
                                        <p className="truncate text-xs text-muted-foreground">
                                            {reply.sender_email}
                                        </p>
                                    </div>
                                </div>
                                <div className="min-w-0 pl-5 md:pl-0">
                                    <p
                                        className={`truncate text-sm ${reply.is_read ? 'font-medium' : 'font-semibold'}`}
                                    >
                                        {reply.subject || '(No subject)'}
                                    </p>
                                    <p className="mt-1 truncate text-sm text-muted-foreground">
                                        {reply.actual_reply ||
                                            reply.body_preview ||
                                            'No readable message body.'}
                                    </p>
                                </div>
                                <div className="flex items-center justify-between gap-3 pl-5 md:min-w-52 md:justify-end md:pl-0">
                                    <div className="flex flex-col items-start gap-1.5 md:items-end">
                                        <ClassificationBadge
                                            value={reply.classification}
                                        />
                                        <span className="text-xs whitespace-nowrap text-muted-foreground">
                                            {formatManilaDateTime(
                                                reply.received_at,
                                            )}
                                        </span>
                                    </div>
                                    <ChevronRight className="size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:text-foreground" />
                                </div>
                            </button>
                        ))}
                    </div>
                    {!replies.data.length && (
                        <CardContent className="flex flex-col items-center gap-3 p-12 text-center">
                            <Inbox className="size-8 text-muted-foreground" />
                            <div>
                                <p className="font-medium">
                                    No matched replies yet
                                </p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Connect Gmail and synchronize to check for
                                    messages from your lead email addresses.
                                </p>
                            </div>
                        </CardContent>
                    )}
                </Card>
                <Pagination links={replies.links} />
            </div>

            <Dialog
                open={selectedReply !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setSelectedReplyId(null);
                    }
                }}
            >
                {selectedReply && (
                    <DialogContent className="max-h-[88vh] overflow-y-auto sm:max-w-3xl">
                        <DialogHeader className="pr-8">
                            <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                                <div className="flex min-w-0 items-start gap-3">
                                    <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-cyan-500/12 text-cyan-600 dark:text-cyan-300">
                                        <MailOpen className="size-5" />
                                    </div>
                                    <div className="min-w-0">
                                        <DialogTitle className="leading-snug">
                                            {selectedReply.subject ||
                                                '(No subject)'}
                                        </DialogTitle>
                                        <DialogDescription className="mt-1">
                                            {selectedReply.sender_name
                                                ? `${selectedReply.sender_name} · `
                                                : ''}
                                            {selectedReply.sender_email} ·{' '}
                                            {formatManilaDateTime(
                                                selectedReply.received_at,
                                            )}
                                        </DialogDescription>
                                    </div>
                                </div>
                                <div className="flex shrink-0 items-center gap-2">
                                    <span className="text-xs text-muted-foreground">
                                        Classification
                                    </span>
                                    <ClassificationBadge
                                        value={selectedReply.classification}
                                    />
                                </div>
                            </div>
                        </DialogHeader>

                        <div className="rounded-xl border bg-muted/20 p-5">
                            <p className="text-sm leading-7 whitespace-pre-wrap">
                                {selectedReply.actual_reply ||
                                    selectedReply.body_preview ||
                                    'No readable message body.'}
                            </p>
                        </div>

                        <div className="grid gap-1 rounded-xl border p-4 text-sm">
                            <p className="font-semibold">
                                {selectedReply.lead
                                    ? `${selectedReply.lead.company_name} · ${selectedReply.lead.lead_code}`
                                    : 'Deleted lead · Reply retained'}
                            </p>
                            <p className="text-muted-foreground">
                                Owner:{' '}
                                {selectedReply.agent?.name || 'Unassigned'}
                            </p>
                            <p className="text-muted-foreground">
                                {selectedReply.classification_reason}
                            </p>
                        </div>

                        <div className="flex flex-wrap gap-2 border-t pt-4">
                            {!selectedReply.is_read && (
                                <Form {...update.form(selectedReply.id)}>
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
                            {manualClassifications.map((classification) => (
                                <Form
                                    key={classification}
                                    {...update.form(selectedReply.id)}
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
                                            selectedReply.classification ===
                                            classification
                                                ? 'secondary'
                                                : 'outline'
                                        }
                                    >
                                        {classification
                                            .replaceAll('_', ' ')
                                            .replace(/^./, (value) =>
                                                value.toUpperCase(),
                                            )}
                                    </Button>
                                </Form>
                            ))}
                        </div>
                    </DialogContent>
                )}
            </Dialog>
        </>
    );
}

EmailRepliesIndex.layout = {
    breadcrumbs: [{ title: 'Email Replies', href: index() }],
};
