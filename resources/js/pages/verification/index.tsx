import { Form, Head, Link, router } from '@inertiajs/react';
import {
    ChevronRight,
    Download,
    Paperclip,
    Plus,
    Search,
    SlidersHorizontal,
    Sparkles,
    Trash2,
    Upload,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
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
import { destroy } from '@/routes/leads';
import {
    index,
    possible as markPossible,
    owner as updateOwner,
    show,
} from '@/routes/verification';
import possibleLeads from '@/routes/verification/possible-leads';

type Agent = { id: number; name: string };
type Lead = {
    id: number;
    lead_code: string;
    company_name: string;
    website_domain: string | null;
    contact_person: string | null;
    position: string | null;
    email: string | null;
    phone: string | null;
    city: string | null;
    country: string | null;
    timezone: string | null;
    product_requested: string | null;
    status: string;
    validation_status: string;
    created_at: string;
    attachments_count: number;
    agent_id: number | null;
    agent: { name: string } | null;
};
type Filters = { status: string; search: string; agent_id: string };
type Summary = {
    possible_leads: number;
    qualified_leads: number;
    documents: number;
};
const statuses = [
    ['needs_review', 'Needs review'],
    ['possible_lead', 'Possible leads'],
    ['qualified_lead', 'Qualified leads'],
    ['not_a_lead', 'Not a lead'],
    ['forwarded', 'Forwarded'],
] as const;
const ALL_AGENTS = '__all__';
const relativeFormat = new Intl.RelativeTimeFormat(undefined, {
    numeric: 'auto',
});

function addedLabel(value: string): string {
    const days = Math.round(
        (new Date(value).getTime() - Date.now()) / 86400000,
    );

    return Math.abs(days) < 30
        ? relativeFormat.format(days, 'day')
        : new Date(value).toLocaleDateString(undefined, {
              month: 'short',
              day: 'numeric',
              year: 'numeric',
          });
}

export default function VerificationIndex({
    leads,
    filters,
    summary,
    statusCounts,
    agents,
    owners,
    canDelete,
}: {
    leads: {
        data: Lead[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: Filters;
    summary: Summary;
    statusCounts: Partial<Record<string, number>>;
    agents: Agent[];
    owners: Agent[];
    canDelete: boolean;
}) {
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [agentFilter, setAgentFilter] = useState(
        filters.agent_id || ALL_AGENTS,
    );
    const activeStatus = filters.search
        ? ''
        : filters.status || 'possible_lead';
    // Carried into Review so Next/Previous stay within this same list.
    const queueQuery = filters.search
        ? { search: filters.search }
        : {
              status: activeStatus,
              agent_id: filters.agent_id || undefined,
          };
    const exportUrl = possibleLeads.export.url({
        query: { search: filters.search || undefined },
    });

    // Reaches across every lead in the database on its own, ignoring the
    // status tab and agent filter, rather than searching only within
    // whichever narrower view happens to be selected.
    const searchAllLeads = () => {
        setAgentFilter(ALL_AGENTS);
        router.get(
            index.url(),
            { search: searchTerm },
            { preserveState: true, replace: true },
        );
    };

    const applyAgentFilter = (value: string) => {
        setAgentFilter(value);
        router.get(
            index.url(),
            {
                status: filters.status || undefined,
                agent_id: value === ALL_AGENTS ? undefined : value,
            },
            { preserveState: true, replace: true },
        );
    };

    const applyStatusFilter = (value: string) => {
        router.get(
            index.url(),
            {
                status: value,
                agent_id: agentFilter === ALL_AGENTS ? undefined : agentFilter,
            },
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Lead Review" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <HeaderActionsPortal>
                    <Button asChild size="sm">
                        <Link href={possibleLeads.create()}>
                            <Plus />
                            Add Possible Lead
                        </Link>
                    </Button>
                    <Button asChild size="sm" variant="outline">
                        <Link href={possibleLeads.import()}>
                            <Upload />
                            Import Possible Leads
                        </Link>
                    </Button>
                    <Button asChild size="sm" variant="outline">
                        <a href={exportUrl}>
                            <Download />
                            Export possible leads
                        </a>
                    </Button>
                </HeaderActionsPortal>

                <nav
                    aria-label="Lead categories"
                    className="flex gap-1 overflow-x-auto border-b"
                >
                    {statuses.map(([value, label]) => (
                        <button
                            key={value}
                            type="button"
                            aria-current={
                                activeStatus === value ? 'page' : undefined
                            }
                            onClick={() => applyStatusFilter(value)}
                            className={`-mb-px flex items-center gap-2 border-b-2 px-3 py-2.5 text-sm whitespace-nowrap ${activeStatus === value ? 'border-primary font-semibold text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground'}`}
                        >
                            {label}
                            <span className="rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground tabular-nums">
                                {(statusCounts[value] ?? 0).toLocaleString()}
                            </span>
                        </button>
                    ))}
                </nav>

                <FilterBar
                    as="div"
                    icon={SlidersHorizontal}
                    label="Filters"
                    gridClassName="grid-cols-1"
                    hint="Search (or press Enter) looks across every lead in the database, ignoring the category tabs and agent filter."
                >
                    <div className="flex flex-col gap-3 lg:flex-row lg:items-end">
                        <div className="flex min-w-0 flex-1 flex-col gap-1.5">
                            <label
                                htmlFor="verification-search"
                                className="text-xs text-muted-foreground"
                            >
                                Search
                            </label>
                            <div className="relative">
                                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    id="verification-search"
                                    name="search"
                                    value={searchTerm}
                                    onChange={(event) =>
                                        setSearchTerm(event.target.value)
                                    }
                                    onKeyDown={(event) => {
                                        if (event.key === 'Enter') {
                                            event.preventDefault();
                                            searchAllLeads();
                                        }
                                    }}
                                    placeholder="Search contact, email, company, location, owner, or lead code..."
                                    className="pr-24 pl-9"
                                />
                                <Button
                                    type="button"
                                    size="sm"
                                    onClick={searchAllLeads}
                                    className="absolute top-1/2 right-1 h-7 -translate-y-1/2"
                                >
                                    <Search className="size-3.5" />
                                    Search
                                </Button>
                            </div>
                        </div>
                        {agents.length > 0 && (
                            <div className="flex flex-col gap-1.5 lg:w-56">
                                <label
                                    htmlFor="verification-agent"
                                    className="text-xs text-muted-foreground"
                                >
                                    Agent
                                </label>
                                <Select
                                    value={agentFilter}
                                    onValueChange={applyAgentFilter}
                                >
                                    <SelectTrigger
                                        id="verification-agent"
                                        className="w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={ALL_AGENTS}>
                                            All agents
                                        </SelectItem>
                                        {agents.map((agent) => (
                                            <SelectItem
                                                key={agent.id}
                                                value={String(agent.id)}
                                            >
                                                {agent.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        )}
                        {(filters.search ||
                            filters.status ||
                            filters.agent_id) && (
                            <Button asChild type="button" variant="outline">
                                <Link href={index()}>Clear</Link>
                            </Button>
                        )}
                    </div>
                </FilterBar>

                <div className="flex flex-wrap items-center justify-between gap-2 px-1 text-sm text-muted-foreground">
                    <p>
                        Showing {leads.from ?? 0}–{leads.to ?? 0} of{' '}
                        {leads.total.toLocaleString()} contacts
                        {filters.search && ` for “${filters.search}”`}
                    </p>
                    <p className="flex items-center gap-1.5 text-xs">
                        <Paperclip className="size-3.5" />
                        {summary.documents.toLocaleString()} documents on
                        possible leads
                    </p>
                </div>
                {leads.data.length ? (
                    <Table className="text-xs">
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead>Company</TableHead>
                                <TableHead>Contact</TableHead>
                                <TableHead>Location</TableHead>
                                <TableHead>Product requested</TableHead>
                                <TableHead>Owner</TableHead>
                                <TableHead>Docs</TableHead>
                                <TableHead>Added</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>
                                    <span className="sr-only">Actions</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {leads.data.map((lead) => (
                                <TableRow key={lead.id}>
                                    <TableCell>
                                        <Link
                                            href={show.url(lead.id, {
                                                query: queueQuery,
                                            })}
                                            className="font-medium hover:underline"
                                        >
                                            {lead.company_name}
                                        </Link>
                                        <p className="text-xs text-muted-foreground">
                                            {lead.website_domain ||
                                                lead.lead_code}
                                        </p>
                                    </TableCell>
                                    <TableCell>
                                        <p>
                                            {lead.contact_person ||
                                                'No contact name'}
                                        </p>
                                        <p className="max-w-64 truncate text-xs text-muted-foreground">
                                            {lead.position || lead.email || '—'}
                                        </p>
                                    </TableCell>
                                    <TableCell>
                                        {[lead.city, lead.country]
                                            .filter(Boolean)
                                            .join(', ') || '—'}
                                        <p className="text-xs text-muted-foreground">
                                            {lead.timezone}
                                        </p>
                                    </TableCell>
                                    <TableCell className="max-w-48 truncate">
                                        {lead.product_requested || '—'}
                                    </TableCell>
                                    <TableCell>
                                        {owners.length > 0 ? (
                                            <Select
                                                value={String(
                                                    lead.agent_id ?? '',
                                                )}
                                                onValueChange={(value) =>
                                                    router.put(
                                                        updateOwner.url(
                                                            lead.id,
                                                        ),
                                                        { agent_id: value },
                                                        {
                                                            preserveScroll: true,
                                                            preserveState: true,
                                                        },
                                                    )
                                                }
                                            >
                                                <SelectTrigger
                                                    size="sm"
                                                    className="w-40 text-xs"
                                                    aria-label={`Owner of ${lead.company_name}`}
                                                >
                                                    <SelectValue placeholder="Unassigned">
                                                        {lead.agent?.name ||
                                                            'Unassigned'}
                                                    </SelectValue>
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {owners.map((owner) => (
                                                        <SelectItem
                                                            key={owner.id}
                                                            value={String(
                                                                owner.id,
                                                            )}
                                                        >
                                                            {owner.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        ) : (
                                            lead.agent?.name || 'Unassigned'
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        {lead.attachments_count > 0 ? (
                                            <span className="inline-flex items-center gap-1 text-foreground">
                                                <Paperclip className="size-3.5 text-primary" />
                                                {lead.attachments_count}
                                            </span>
                                        ) : (
                                            <span className="text-muted-foreground">
                                                —
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell
                                        className="whitespace-nowrap text-muted-foreground"
                                        title={new Date(
                                            lead.created_at,
                                        ).toLocaleString()}
                                    >
                                        {addedLabel(lead.created_at)}
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge value={lead.status} />
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex justify-end gap-2">
                                            {lead.status !==
                                                'possible_lead' && (
                                                <Form
                                                    {...markPossible.form(
                                                        lead.id,
                                                    )}
                                                    options={{
                                                        preserveState: true,
                                                    }}
                                                    onSuccess={() =>
                                                        toast.success(
                                                            'Lead marked as possible.',
                                                        )
                                                    }
                                                >
                                                    {({ processing }) => (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            disabled={
                                                                processing
                                                            }
                                                        >
                                                            <Sparkles />
                                                            Possible
                                                        </Button>
                                                    )}
                                                </Form>
                                            )}
                                            <Button asChild size="sm">
                                                <Link
                                                    href={show.url(lead.id, {
                                                        query: queueQuery,
                                                    })}
                                                >
                                                    Review
                                                    <ChevronRight />
                                                </Link>
                                            </Button>
                                            {canDelete && (
                                                <Dialog>
                                                    <DialogTrigger asChild>
                                                        <Button
                                                            type="button"
                                                            size="icon"
                                                            variant="ghost"
                                                            className="size-8 text-muted-foreground hover:text-destructive"
                                                            aria-label={`Delete ${lead.company_name}`}
                                                        >
                                                            <Trash2 />
                                                        </Button>
                                                    </DialogTrigger>
                                                    <DialogContent>
                                                        <DialogTitle>
                                                            Delete{' '}
                                                            {lead.company_name}?
                                                        </DialogTitle>
                                                        <DialogDescription>
                                                            This removes the
                                                            lead from active
                                                            lists. It can be
                                                            restored by an
                                                            administrator if
                                                            needed.
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
                                                                            lead.id,
                                                                        ),
                                                                        {
                                                                            preserveScroll: true,
                                                                        },
                                                                    )
                                                                }
                                                            >
                                                                Delete lead
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
                            icon={Search}
                            title="No matching contacts"
                            description="Try a broader search or another verification status."
                        />
                    </div>
                )}
                <Pagination links={leads.links} />
            </div>
        </>
    );
}
VerificationIndex.layout = {
    breadcrumbs: [{ title: 'Lead Review', href: index() }],
};
