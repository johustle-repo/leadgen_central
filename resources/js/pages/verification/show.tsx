import { Form, Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    ClipboardCopy,
    Download,
    ExternalLink,
    FileText,
    FolderOpen,
    ImageIcon,
    Globe,
    History,
    Linkedin,
    ListChecks,
    Mail,
    Paperclip,
    Send,
    StickyNote,
    Trash2,
    Upload,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { toast } from 'sonner';
import { formatLabel } from '@/components/database-charts';
import { HeaderActionsPortal } from '@/components/header-actions';
import InputError from '@/components/input-error';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { COUNTRY_CAPITALS } from '@/lib/country-capitals';
import { destroy as destroyLead } from '@/routes/leads';
import {
    destroy as destroyAttachment,
    download as downloadAttachment,
    store as storeAttachment,
} from '@/routes/leads/attachments';
import { store as storeForwarding } from '@/routes/leads/forwardings';
import { store as storeNote } from '@/routes/leads/notes';
import { index, show, update } from '@/routes/verification';

type User = { id: number; name: string };
type Note = {
    id: number;
    note: string;
    note_type: string | null;
    created_at: string;
    user: User | null;
};
type History = {
    id: number;
    old_status: string | null;
    new_status: string;
    remarks: string | null;
    created_at: string;
    changer: User | null;
};
type Forwarding = {
    id: number;
    recipient_name: string | null;
    recipient_email: string | null;
    team: string | null;
    remarks: string | null;
    forwarded_at: string;
    forwarder: User | null;
};
type Attachment = {
    id: number;
    original_name: string;
    label: string | null;
    mime_type: string;
    file_size: number;
    created_at: string;
    uploader: User | null;
};
type Lead = Record<string, string | number | null | object[]> & {
    id: number;
    lead_code: string;
    company_name: string;
    status: string;
    validation_status: string;
    agent: User | null;
    structured_notes: Note[];
    status_history: History[];
    forwardings: Forwarding[];
    attachments: Attachment[];
    upload_batch: { batch_code: string } | null;
};
type Queue = {
    status: string;
    agent_id: number | null;
    search: string;
    total: number;
    position: number | null;
};
const QUEUE_LABELS: Record<string, string> = {
    needs_review: 'Needs review',
    possible_lead: 'Possible leads',
    qualified_lead: 'Qualified leads',
    not_a_lead: 'Not a lead',
    forwarded: 'Forwarded',
};

/** The list-page filters a lead was opened from, carried between leads. */
function queueQuery(queue: Queue | null): Record<string, string | number> {
    if (queue === null) {
        return {};
    }

    if (queue.search !== '') {
        return { search: queue.search };
    }

    return queue.agent_id === null
        ? { status: queue.status }
        : { status: queue.status, agent_id: queue.agent_id };
}

function externalUrl(value: string): string {
    return /^https?:\/\//i.test(value) ? value : `https://${value}`;
}

function QuickLink({
    href,
    icon: Icon,
    children,
}: {
    href: string;
    icon: typeof Globe;
    children: ReactNode;
}) {
    return (
        <a
            href={href}
            target={href.startsWith('mailto:') ? undefined : '_blank'}
            rel="noreferrer noopener"
            className="inline-flex max-w-full items-center gap-1.5 rounded-full border bg-background px-2.5 py-1 text-xs text-foreground hover:border-primary hover:text-primary"
        >
            <Icon className="size-3.5 shrink-0" />
            <span className="truncate">{children}</span>
        </a>
    );
}

const SELECT_COUNTRY = '__select__';
const COUNTRY_OPTIONS = Object.entries(COUNTRY_CAPITALS)
    .map(([code, { name }]) => ({ code, name }))
    .sort((a, b) => a.name.localeCompare(b.name));

const fieldsBeforeCountry = [
    ['company_name', 'Company Name'],
    ['website', 'Website'],
    ['website_domain', 'Domain'],
    ['city', 'City'],
] as const;
const fieldsAfterCountry = [
    ['timezone', 'Timezone'],
    ['contact_person', 'Contact Person'],
    ['position', 'Position'],
    ['email', 'Email'],
    ['secondary_email', 'Secondary Email'],
    ['phone', 'Phone'],
    ['product_requested', 'Product Requested'],
] as const;

/** Mirrors StoreLeadAttachmentRequest's allowed types and size limit. */
const DOCUMENT_ACCEPT =
    '.pdf,.csv,.xls,.xlsx,.doc,.docx,.jpg,.jpeg,.png,.webp,.gif';
const DOCUMENT_MAX_BYTES = 20 * 1024 * 1024;

/**
 * Coloured, drag-and-drop file picker that keeps the real file input (so the
 * surrounding Inertia Form still submits it) and previews images before they
 * are uploaded.
 */
function DocumentPicker({
    onChange,
}: {
    onChange: (hasFile: boolean) => void;
}) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [file, setFile] = useState<File | null>(null);
    const [dragging, setDragging] = useState(false);
    const [tooLarge, setTooLarge] = useState(false);
    const preview = useMemo(
        () =>
            file && file.type.startsWith('image/')
                ? URL.createObjectURL(file)
                : null,
        [file],
    );

    useEffect(
        () => () => {
            if (preview) {
                URL.revokeObjectURL(preview);
            }
        },
        [preview],
    );

    const choose = (files: FileList | null) => {
        const chosen = files?.[0] ?? null;

        if (chosen && chosen.size > DOCUMENT_MAX_BYTES) {
            if (inputRef.current) {
                inputRef.current.value = '';
            }

            setTooLarge(true);
            setFile(null);
            onChange(false);

            return;
        }

        setTooLarge(false);
        setFile(chosen);
        onChange(chosen !== null);
    };

    const clear = () => {
        if (inputRef.current) {
            inputRef.current.value = '';
        }

        choose(null);
    };

    return (
        <div>
            <label
                onDragOver={(event) => {
                    event.preventDefault();
                    setDragging(true);
                }}
                onDragLeave={() => setDragging(false)}
                onDrop={(event) => {
                    event.preventDefault();
                    setDragging(false);

                    if (
                        inputRef.current &&
                        event.dataTransfer.files.length > 0
                    ) {
                        inputRef.current.files = event.dataTransfer.files;
                        choose(event.dataTransfer.files);
                    }
                }}
                className={`flex cursor-pointer flex-col items-center gap-2 rounded-lg border-2 border-dashed p-4 text-center transition-colors ${dragging ? 'border-primary bg-primary/15' : 'border-primary/30 bg-primary/5 hover:border-primary/60 hover:bg-primary/10'}`}
            >
                <input
                    ref={inputRef}
                    name="attachment"
                    type="file"
                    required
                    accept={DOCUMENT_ACCEPT}
                    className="sr-only"
                    onChange={(event) => choose(event.target.files)}
                />
                {file ? (
                    <div className="flex w-full items-center gap-3 text-left">
                        {preview ? (
                            <img
                                src={preview}
                                alt=""
                                className="size-12 shrink-0 rounded-md border object-cover"
                            />
                        ) : (
                            <span className="flex size-12 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
                                <FileText className="size-6" />
                            </span>
                        )}
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-sm font-medium">
                                {file.name}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {fileSize(file.size)} · click to change
                            </p>
                        </div>
                        <button
                            type="button"
                            onClick={(event) => {
                                event.preventDefault();
                                clear();
                            }}
                            className="rounded-md p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                            aria-label="Remove selected file"
                        >
                            <X className="size-4" />
                        </button>
                    </div>
                ) : (
                    <>
                        <span className="inline-flex items-center gap-2 rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground shadow-xs">
                            <FolderOpen className="size-4" />
                            Choose file
                        </span>
                        <span className="text-xs text-muted-foreground">
                            or drag and drop it here
                        </span>
                        <span className="text-[11px] text-muted-foreground">
                            PDF, Word, Excel, CSV, JPG, PNG, WEBP or GIF · up to
                            20 MB
                        </span>
                    </>
                )}
            </label>
            {tooLarge && (
                <p className="mt-1 text-xs text-destructive">
                    That file is larger than 20 MB.
                </p>
            )}
        </div>
    );
}

const fileSize = (bytes: number) =>
    bytes >= 1024 * 1024
        ? `${(bytes / 1024 / 1024).toFixed(1)} MB`
        : `${Math.max(1, Math.round(bytes / 1024))} KB`;

// AP-style month abbreviations (e.g. "Sept. 14, 2026") rather than the
// three-letter "Sep" Intl.DateTimeFormat's short form would give.
const AP_STYLE_MONTHS = [
    'Jan.',
    'Feb.',
    'March',
    'April',
    'May',
    'June',
    'July',
    'Aug.',
    'Sept.',
    'Oct.',
    'Nov.',
    'Dec.',
];
const formatApStyleDate = (value: string) => {
    if (!value) {
        return '';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    // UTC getters, not local ones: a date-only value serializes as midnight
    // UTC, which local getters can roll back to the previous day in any
    // timezone behind UTC.
    return `${AP_STYLE_MONTHS[date.getUTCMonth()]} ${date.getUTCDate()}, ${date.getUTCFullYear()}`;
};

/**
 * Owns its own Select state, keyed by lead id at the call site: Inertia
 * re-renders this same page component in place (rather than remounting it)
 * when navigating to a different lead via Next/Previous, so a plain
 * useState here would keep showing the previous lead's country and, if
 * saved without being touched, silently wipe the new lead's country and
 * country_code.
 */
function CountryField({
    lead,
    errors,
}: {
    lead: Lead;
    errors: Record<string, string | undefined>;
}) {
    const initialCountryCode = String(lead.country_code ?? '').toUpperCase();
    const [countryCode, setCountryCode] = useState(
        COUNTRY_CAPITALS[initialCountryCode]
            ? initialCountryCode
            : SELECT_COUNTRY,
    );

    return (
        <div>
            <Label htmlFor="country">Country</Label>
            <input
                type="hidden"
                name="country"
                value={
                    countryCode === SELECT_COUNTRY
                        ? ''
                        : COUNTRY_CAPITALS[countryCode].name
                }
            />
            <input
                type="hidden"
                name="country_code"
                value={countryCode === SELECT_COUNTRY ? '' : countryCode}
            />
            <Select value={countryCode} onValueChange={setCountryCode}>
                <SelectTrigger id="country" className="mt-2 w-full">
                    <SelectValue placeholder="Select a country" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={SELECT_COUNTRY}>
                        Select a country
                    </SelectItem>
                    {COUNTRY_OPTIONS.map(({ code, name }) => (
                        <SelectItem key={code} value={code}>
                            {name}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <p className="mt-1 text-xs text-muted-foreground">
                Country code is set automatically from this selection.
            </p>
            <InputError className="mt-1" message={errors.country} />
        </div>
    );
}

export default function VerificationShow({
    lead,
    previousId,
    nextId,
    queue,
    reviewers,
    agents,
    canDelete,
}: {
    lead: Lead;
    previousId: number | null;
    nextId: number | null;
    queue: Queue | null;
    reviewers: User[];
    agents: User[];
    canDelete: boolean;
}) {
    const copyDetails = async () => {
        const field = (name: string) => {
            const value = lead[name];

            return value === null || value === undefined ? '' : String(value);
        };
        // The country code is the reliable, structured value - convert it to
        // the country's full name and its capital (used here as the
        // representative city) rather than whatever free-text country/city
        // values happen to be stored, falling back to those only when the
        // code isn't set or isn't recognized.
        const countryInfo =
            COUNTRY_CAPITALS[field('country_code').toUpperCase()];
        const countryName = countryInfo?.name || field('country');
        const capitalCity =
            countryInfo?.capital || field('raw_city') || field('city');
        const details = [
            `Name of Contact: ${field('contact_person')}`,
            `email: ${field('email')}`,
            `Company Name: ${field('company_name')}`,
            `Website: ${field('website')}`,
            `Country: ${countryName}`,
            `City/Capital: ${capitalCity}`,
            `Import Trades: ${field('import_trades')}`,
            `Product Requested: ${field('product_requested')}`,
            `LinkedIn: ${field('linkedin_url')}`,
            `Source of Leads: ${field('data_source')}`,
            `Source Link: ${field('source_url')}`,
            `Date Uploaded in Reply.io: ${formatApStyleDate(field('lead_date'))}`,
        ].join('\n');

        try {
            await navigator.clipboard.writeText(details);
            toast.success('Contact details copied to clipboard.');
        } catch {
            toast.error('Could not copy details. Please try again.');
        }
    };

    const [pickerKey, setPickerKey] = useState(0);
    const [documentLeadId, setDocumentLeadId] = useState<number | null>(null);
    const query = queueQuery(queue);
    const previousUrl = previousId ? show.url(previousId, { query }) : null;
    const nextUrl = nextId ? show.url(nextId, { query }) : null;
    const queueLabel =
        queue === null
            ? 'All leads'
            : queue.search !== ''
              ? `Search “${queue.search}”`
              : [
                    QUEUE_LABELS[queue.status] ?? formatLabel(queue.status),
                    queue.agent_id === null
                        ? null
                        : (agents.find((agent) => agent.id === queue.agent_id)
                              ?.name ?? 'Selected agent'),
                ]
                    .filter(Boolean)
                    .join(' · ');
    const text = (name: string) => {
        const value = lead[name];

        return typeof value === 'string' ? value.trim() : '';
    };
    const leadDate = text('lead_date');

    useEffect(() => {
        const navigate = (event: KeyboardEvent) => {
            const target = event.target as HTMLElement | null;

            if (
                !event.altKey ||
                target?.closest('input, textarea, select, [contenteditable]')
            ) {
                return;
            }

            const url =
                event.key === 'ArrowLeft'
                    ? previousUrl
                    : event.key === 'ArrowRight'
                      ? nextUrl
                      : null;

            if (url) {
                event.preventDefault();
                router.visit(url);
            }
        };
        window.addEventListener('keydown', navigate);

        return () => window.removeEventListener('keydown', navigate);
    }, [previousUrl, nextUrl]);

    return (
        <>
            <Head title={`Verify ${lead.company_name}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <HeaderActionsPortal>
                    <Button size="sm" onClick={copyDetails}>
                        <ClipboardCopy />
                        Copy details
                    </Button>
                    {canDelete && (
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
                                    Delete {lead.company_name}?
                                </DialogTitle>
                                <DialogDescription>
                                    This removes the lead from active lists. It
                                    can be restored by an administrator if
                                    needed.
                                </DialogDescription>
                                <DialogFooter>
                                    <DialogClose asChild>
                                        <Button variant="secondary">
                                            Cancel
                                        </Button>
                                    </DialogClose>
                                    <Form {...destroyLead.form(lead.id)}>
                                        {({ processing }) => (
                                            <Button
                                                type="submit"
                                                variant="destructive"
                                                disabled={processing}
                                            >
                                                Delete lead
                                            </Button>
                                        )}
                                    </Form>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                    )}
                </HeaderActionsPortal>
                <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border bg-card px-4 py-2.5 shadow-xs">
                    <div className="flex min-w-0 items-center gap-3 text-sm">
                        <Link
                            href={index.url({ query })}
                            className="inline-flex items-center gap-1.5 font-medium text-primary hover:underline"
                        >
                            <ListChecks className="size-4" />
                            Back to list
                        </Link>
                        <span className="truncate text-muted-foreground">
                            {queueLabel}
                            {queue !== null &&
                                (queue.position !== null
                                    ? ` · Lead ${queue.position.toLocaleString()} of ${queue.total.toLocaleString()}`
                                    : ` · No longer in this list · ${queue.total.toLocaleString()} remaining`)}
                        </span>
                    </div>
                    <div className="flex items-center gap-2">
                        <span className="hidden text-xs text-muted-foreground md:inline">
                            Alt + ← / → to move between leads
                        </span>
                        {previousUrl ? (
                            <Button asChild variant="outline" size="sm">
                                <Link href={previousUrl}>
                                    <ArrowLeft />
                                    Previous
                                </Link>
                            </Button>
                        ) : (
                            <Button variant="outline" size="sm" disabled>
                                <ArrowLeft />
                                Previous
                            </Button>
                        )}
                        {nextUrl ? (
                            <Button asChild variant="outline" size="sm">
                                <Link href={nextUrl}>
                                    Next
                                    <ArrowRight />
                                </Link>
                            </Button>
                        ) : (
                            <Button variant="outline" size="sm" disabled>
                                Next
                                <ArrowRight />
                            </Button>
                        )}
                    </div>
                </div>
                <div className="rounded-lg border bg-card p-4 shadow-xs">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div className="min-w-0">
                            <h1 className="text-xl font-semibold tracking-tight break-words">
                                {lead.company_name}
                            </h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {[
                                    lead.lead_code,
                                    `Owner: ${lead.agent?.name ?? 'Unassigned'}`,
                                    lead.upload_batch
                                        ? `Batch ${lead.upload_batch.batch_code}`
                                        : 'Added manually',
                                    leadDate
                                        ? `Lead date ${formatApStyleDate(leadDate)}`
                                        : null,
                                    text('data_source') || null,
                                ]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <StatusBadge value={lead.status} />
                            <StatusBadge value={lead.validation_status} />
                        </div>
                    </div>
                    {(text('website') ||
                        text('email') ||
                        text('linkedin_url') ||
                        text('source_url')) && (
                        <div className="mt-3 flex flex-wrap gap-2">
                            {text('website') && (
                                <QuickLink
                                    href={externalUrl(text('website'))}
                                    icon={Globe}
                                >
                                    {text('website_domain') || text('website')}
                                </QuickLink>
                            )}
                            {text('email') && (
                                <QuickLink
                                    href={`mailto:${text('email')}`}
                                    icon={Mail}
                                >
                                    {text('email')}
                                </QuickLink>
                            )}
                            {text('linkedin_url') && (
                                <QuickLink
                                    href={externalUrl(text('linkedin_url'))}
                                    icon={Linkedin}
                                >
                                    LinkedIn
                                </QuickLink>
                            )}
                            {text('source_url') && (
                                <QuickLink
                                    href={externalUrl(text('source_url'))}
                                    icon={ExternalLink}
                                >
                                    Source link
                                </QuickLink>
                            )}
                        </div>
                    )}
                </div>
                <div className="grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)]">
                    <Form key={lead.id} {...update.form(lead.id)}>
                        {({ errors, processing }) => (
                            <Card>
                                <CardHeader className="flex-row items-center justify-between">
                                    <CardTitle>Lead details</CardTitle>
                                    <CardDescription>
                                        Correct the details, choose a
                                        classification, then save.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    {queue !== null && (
                                        <>
                                            <input
                                                type="hidden"
                                                name="queue_status"
                                                value={queue.status}
                                            />
                                            <input
                                                type="hidden"
                                                name="queue_agent_id"
                                                value={queue.agent_id ?? ''}
                                            />
                                            <input
                                                type="hidden"
                                                name="queue_search"
                                                value={queue.search}
                                            />
                                        </>
                                    )}
                                    <div className="grid gap-4 md:grid-cols-2">
                                        {fieldsBeforeCountry.map(
                                            ([name, label]) => (
                                                <div key={name}>
                                                    <Label htmlFor={name}>
                                                        {label}
                                                    </Label>
                                                    <Input
                                                        id={name}
                                                        name={name}
                                                        defaultValue={String(
                                                            lead[name] ?? '',
                                                        )}
                                                        readOnly={
                                                            name ===
                                                            'website_domain'
                                                        }
                                                        className="mt-2"
                                                    />
                                                    <InputError
                                                        className="mt-1"
                                                        message={errors[name]}
                                                    />
                                                </div>
                                            ),
                                        )}
                                        <CountryField
                                            lead={lead}
                                            errors={errors}
                                        />
                                        {fieldsAfterCountry.map(
                                            ([name, label]) => (
                                                <div key={name}>
                                                    <Label htmlFor={name}>
                                                        {label}
                                                    </Label>
                                                    <Input
                                                        id={name}
                                                        name={name}
                                                        defaultValue={String(
                                                            lead[name] ?? '',
                                                        )}
                                                        className="mt-2"
                                                    />
                                                    <InputError
                                                        className="mt-1"
                                                        message={errors[name]}
                                                    />
                                                </div>
                                            ),
                                        )}
                                    </div>
                                    <div className="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                        {agents.length > 0 && (
                                            <div>
                                                <Label htmlFor="agent_id">
                                                    Owner
                                                </Label>
                                                <Select
                                                    name="agent_id"
                                                    defaultValue={String(
                                                        lead.agent_id ?? '',
                                                    )}
                                                >
                                                    <SelectTrigger
                                                        id="agent_id"
                                                        className="mt-2 w-full"
                                                    >
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {agents.map((agent) => (
                                                            <SelectItem
                                                                key={agent.id}
                                                                value={String(
                                                                    agent.id,
                                                                )}
                                                            >
                                                                {agent.name}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                <InputError
                                                    className="mt-1"
                                                    message={errors.agent_id}
                                                />
                                            </div>
                                        )}
                                        <div>
                                            <Label htmlFor="status">
                                                Classification
                                            </Label>
                                            <Select
                                                name="status"
                                                defaultValue={lead.status}
                                            >
                                                <SelectTrigger
                                                    id="status"
                                                    className="mt-2 w-full"
                                                >
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="needs_review">
                                                        Needs Review
                                                    </SelectItem>
                                                    <SelectItem value="possible_lead">
                                                        Possible Lead
                                                    </SelectItem>
                                                    <SelectItem value="qualified_lead">
                                                        Qualified Lead
                                                    </SelectItem>
                                                    <SelectItem value="not_a_lead">
                                                        Not a Lead
                                                    </SelectItem>
                                                    <SelectItem value="duplicate">
                                                        Duplicate
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div>
                                            <Label htmlFor="replied_at">
                                                Date Replied (Reply.io)
                                            </Label>
                                            <Input
                                                id="replied_at"
                                                name="replied_at"
                                                type="date"
                                                defaultValue={String(
                                                    lead.replied_at ?? '',
                                                ).slice(0, 10)}
                                                className="mt-2"
                                            />
                                            <InputError
                                                className="mt-1"
                                                message={errors.replied_at}
                                            />
                                        </div>
                                        <div>
                                            <Label htmlFor="remarks">
                                                Verification remarks
                                            </Label>
                                            <Input
                                                id="remarks"
                                                name="remarks"
                                                placeholder="Why this classification?"
                                                className="mt-2"
                                            />
                                        </div>
                                    </div>
                                    <div className="mt-5 flex flex-wrap items-center justify-end gap-2">
                                        <p className="mr-auto text-xs text-muted-foreground">
                                            {nextId
                                                ? 'Next saves this lead and opens the next one in this list.'
                                                : 'This is the last lead in this list.'}
                                        </p>
                                        <Button
                                            type="submit"
                                            name="intent"
                                            value="save"
                                            variant="outline"
                                            disabled={processing}
                                        >
                                            Save
                                        </Button>
                                        <Button
                                            type="submit"
                                            name="intent"
                                            value="save_next"
                                            disabled={processing}
                                        >
                                            Next
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </Form>
                    <aside className="flex flex-col gap-4">
                        <Card>
                            <CardHeader className="flex-row items-start justify-between">
                                <div className="flex items-center gap-3">
                                    <div className="rounded-lg bg-primary/10 p-2 text-primary">
                                        <Paperclip className="size-5" />
                                    </div>
                                    <div>
                                        <CardTitle>Contact documents</CardTitle>
                                        <CardDescription>
                                            Private PDF, Word, Excel, CSV or
                                            image files, up to 20 MB each.
                                        </CardDescription>
                                    </div>
                                </div>
                                <span className="rounded-full bg-muted px-2 py-1 text-xs">
                                    {lead.attachments.length}
                                </span>
                            </CardHeader>
                            <CardContent>
                                {lead.status === 'possible_lead' ? (
                                    <Form
                                        {...storeAttachment.form(lead.id)}
                                        resetOnSuccess
                                        onSuccess={() => {
                                            setPickerKey((key) => key + 1);
                                            setDocumentLeadId(null);
                                        }}
                                        className="mt-4 space-y-3"
                                    >
                                        {({ errors, processing, progress }) => (
                                            <>
                                                <Input
                                                    name="label"
                                                    placeholder="Document label (optional)"
                                                />
                                                <DocumentPicker
                                                    key={`${lead.id}-${pickerKey}`}
                                                    onChange={(hasFile) =>
                                                        setDocumentLeadId(
                                                            hasFile
                                                                ? lead.id
                                                                : null,
                                                        )
                                                    }
                                                />
                                                <InputError
                                                    className="text-xs"
                                                    message={errors.attachment}
                                                />
                                                {progress && (
                                                    <div className="h-1.5 overflow-hidden rounded-full bg-muted">
                                                        <div
                                                            className="h-full bg-primary transition-all"
                                                            style={{
                                                                width: `${progress.percentage}%`,
                                                            }}
                                                        />
                                                    </div>
                                                )}
                                                <Button
                                                    size="sm"
                                                    className="w-full"
                                                    disabled={
                                                        processing ||
                                                        documentLeadId !==
                                                            lead.id
                                                    }
                                                >
                                                    <Upload />
                                                    {processing
                                                        ? 'Uploading…'
                                                        : 'Attach document'}
                                                </Button>
                                            </>
                                        )}
                                    </Form>
                                ) : (
                                    <p className="mt-4 rounded-lg bg-muted/50 p-3 text-xs text-muted-foreground">
                                        Mark this contact as a Possible Lead
                                        before attaching supporting documents.
                                    </p>
                                )}
                                <div className="mt-4 space-y-2">
                                    {lead.attachments.map((attachment) => (
                                        <div
                                            key={attachment.id}
                                            className="flex items-center gap-3 rounded-lg border p-3"
                                        >
                                            {attachment.mime_type.startsWith(
                                                'image/',
                                            ) ? (
                                                <ImageIcon className="size-5 shrink-0 text-primary" />
                                            ) : (
                                                <FileText className="size-5 shrink-0 text-primary" />
                                            )}
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-sm font-medium">
                                                    {attachment.label ||
                                                        attachment.original_name}
                                                </p>
                                                <p className="truncate text-xs text-muted-foreground">
                                                    {attachment.original_name} ·{' '}
                                                    {fileSize(
                                                        attachment.file_size,
                                                    )}{' '}
                                                    ·{' '}
                                                    {attachment.uploader
                                                        ?.name ||
                                                        'Deleted user'}
                                                </p>
                                            </div>
                                            <Button
                                                asChild
                                                size="icon"
                                                variant="ghost"
                                            >
                                                <a
                                                    href={downloadAttachment.url(
                                                        {
                                                            lead: lead.id,
                                                            leadAttachment:
                                                                attachment.id,
                                                        },
                                                    )}
                                                    aria-label="Download document"
                                                >
                                                    <Download />
                                                </a>
                                            </Button>
                                            <Form
                                                {...destroyAttachment.form({
                                                    lead: lead.id,
                                                    leadAttachment:
                                                        attachment.id,
                                                })}
                                            >
                                                {({ processing }) => (
                                                    <Button
                                                        size="icon"
                                                        variant="ghost"
                                                        disabled={processing}
                                                        aria-label="Remove document"
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                )}
                                            </Form>
                                        </div>
                                    ))}
                                    {!lead.attachments.length && (
                                        <p className="py-3 text-center text-xs text-muted-foreground">
                                            No supporting documents attached
                                            yet.
                                        </p>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <div className="flex items-center gap-3">
                                    <div className="rounded-lg bg-primary/10 p-2 text-primary">
                                        <StickyNote className="size-5" />
                                    </div>
                                    <div>
                                        <CardTitle>Notes</CardTitle>
                                        <CardDescription>
                                            Internal notes for this lead.
                                        </CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <Form
                                    {...storeNote.form(lead.id)}
                                    className="flex flex-col gap-3"
                                >
                                    {({ processing }) => (
                                        <>
                                            <textarea
                                                name="note"
                                                rows={3}
                                                required
                                                className="w-full rounded-md border bg-background p-3 text-sm"
                                                placeholder="Add verification note…"
                                            />
                                            <Select
                                                name="note_type"
                                                defaultValue="verification"
                                            >
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="verification">
                                                        Verification
                                                    </SelectItem>
                                                    <SelectItem value="correction">
                                                        Correction
                                                    </SelectItem>
                                                    <SelectItem value="general">
                                                        General
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                            <Button
                                                size="sm"
                                                disabled={processing}
                                            >
                                                Add note
                                            </Button>
                                        </>
                                    )}
                                </Form>
                                <div className="mt-4 flex flex-col gap-3">
                                    {lead.structured_notes.map((note) => (
                                        <div
                                            key={note.id}
                                            className="rounded-lg bg-muted/50 p-3 text-sm"
                                        >
                                            <p>{note.note}</p>
                                            <p className="mt-2 text-xs text-muted-foreground">
                                                {note.user?.name ||
                                                    'Deleted user'}{' '}
                                                ·{' '}
                                                {new Date(
                                                    note.created_at,
                                                ).toLocaleString()}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <div className="flex items-center gap-3">
                                    <div className="rounded-lg bg-primary/10 p-2 text-primary">
                                        <Send className="size-5" />
                                    </div>
                                    <div>
                                        <CardTitle>
                                            Forward qualified lead
                                        </CardTitle>
                                        <CardDescription>
                                            Send this lead to another team or
                                            contact.
                                        </CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <Form
                                    {...storeForwarding.form(lead.id)}
                                    className="flex flex-col gap-3"
                                >
                                    {({ errors, processing }) => (
                                        <>
                                            <Input
                                                name="recipient_name"
                                                placeholder="Recipient name"
                                            />
                                            <Input
                                                name="recipient_email"
                                                type="email"
                                                placeholder="Recipient email"
                                            />
                                            <Input
                                                name="team"
                                                placeholder="Team"
                                            />
                                            <textarea
                                                name="remarks"
                                                rows={2}
                                                className="rounded-md border bg-background p-3 text-sm"
                                                placeholder="Remarks"
                                            />
                                            <InputError message={errors.lead} />
                                            {lead.status !==
                                                'qualified_lead' && (
                                                <p className="rounded-md bg-muted/50 p-2 text-xs text-muted-foreground">
                                                    Classify this lead as a
                                                    Qualified Lead and save
                                                    before forwarding it.
                                                </p>
                                            )}
                                            <Button
                                                size="sm"
                                                disabled={
                                                    processing ||
                                                    lead.status !==
                                                        'qualified_lead'
                                                }
                                            >
                                                Forward
                                            </Button>
                                        </>
                                    )}
                                </Form>
                                {reviewers.length > 0 && (
                                    <p className="mt-2 text-xs text-muted-foreground">
                                        Authorized reviewers:{' '}
                                        {reviewers
                                            .map((user) => user.name)
                                            .join(', ')}
                                    </p>
                                )}
                                <div className="mt-4 flex flex-col gap-3">
                                    {lead.forwardings.map((item) => (
                                        <div
                                            key={item.id}
                                            className="rounded-lg bg-muted/50 p-3 text-sm"
                                        >
                                            <p>
                                                {item.recipient_name ||
                                                    item.team ||
                                                    item.recipient_email}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {item.forwarder?.name ||
                                                    'Deleted user'}{' '}
                                                ·{' '}
                                                {new Date(
                                                    item.forwarded_at,
                                                ).toLocaleString()}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <div className="flex items-center gap-3">
                                    <div className="rounded-lg bg-primary/10 p-2 text-primary">
                                        <History className="size-5" />
                                    </div>
                                    <div>
                                        <CardTitle>Status history</CardTitle>
                                        <CardDescription>
                                            Every status change recorded for
                                            this lead.
                                        </CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div className="flex flex-col gap-3">
                                    {lead.status_history.length === 0 && (
                                        <p className="text-xs text-muted-foreground">
                                            No status changes yet.
                                        </p>
                                    )}
                                    {lead.status_history.map((item) => (
                                        <div
                                            key={item.id}
                                            className="border-l-2 pl-3 text-sm"
                                        >
                                            <p className="flex flex-wrap items-center gap-1.5">
                                                {item.old_status ? (
                                                    <StatusBadge
                                                        value={item.old_status}
                                                    />
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        Created
                                                    </span>
                                                )}
                                                <ArrowRight className="size-3.5 text-muted-foreground" />
                                                <StatusBadge
                                                    value={item.new_status}
                                                />
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {item.changer?.name ||
                                                    'Deleted user'}{' '}
                                                ·{' '}
                                                {new Date(
                                                    item.created_at,
                                                ).toLocaleString()}
                                            </p>
                                            {item.remarks && (
                                                <p className="mt-1 text-muted-foreground">
                                                    {item.remarks}
                                                </p>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    </aside>
                </div>
            </div>
        </>
    );
}
VerificationShow.layout = {
    breadcrumbs: [{ title: 'Lead Review', href: index() }],
};
