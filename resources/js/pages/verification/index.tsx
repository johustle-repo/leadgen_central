import { Form, Head, Link } from '@inertiajs/react';
import {
    Download,
    FileText,
    Plus,
    Search,
    SlidersHorizontal,
    Sparkles,
    UserCheck,
} from 'lucide-react';
import { toast } from 'sonner';
import { EmptyState } from '@/components/empty-state';
import { FilterBar } from '@/components/filter-bar';
import { FilterTabs } from '@/components/filter-tabs';
import { HeaderActionsPortal } from '@/components/header-actions';
import { Pagination } from '@/components/pagination';
import { StatTile } from '@/components/stat-tile';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index, possible as markPossible, show } from '@/routes/verification';
import possibleLeads from '@/routes/verification/possible-leads';

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
    status: string;
    validation_status: string;
    structured_notes_count: number;
    attachments_count: number;
    agent: { name: string } | null;
};
type Filters = { status: string; search: string };
type Summary = {
    review_queue: number;
    possible_leads: number;
    qualified_leads: number;
    documents: number;
};
const statuses = [
    ['', 'Review queue'],
    ['needs_review', 'Needs review'],
    ['possible_lead', 'Possible leads'],
    ['qualified_lead', 'Qualified leads'],
    ['not_a_lead', 'Not a lead'],
    ['forwarded', 'Forwarded'],
] as const;

export default function VerificationIndex({
    leads,
    filters,
    summary,
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
}) {
    const exportUrl = possibleLeads.export.url({
        query: { search: filters.search || undefined },
    });

    return (
        <>
            <Head title="Verification" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <HeaderActionsPortal>
                    <Button asChild size="sm">
                        <Link href={possibleLeads.create()}>
                            <Plus />
                            Add Possible Lead
                        </Link>
                    </Button>
                    <Button asChild size="sm" variant="outline">
                        <a href={exportUrl}>
                            <Download />
                            Export possible leads
                        </a>
                    </Button>
                </HeaderActionsPortal>

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    {(
                        [
                            ['Review queue', summary.review_queue, Search],
                            [
                                'Possible leads',
                                summary.possible_leads,
                                Sparkles,
                            ],
                            ['Qualified', summary.qualified_leads, UserCheck],
                            ['Documents', summary.documents, FileText],
                        ] as const
                    ).map(([label, value, Icon]) => (
                        <StatTile
                            key={label}
                            label={label}
                            value={value}
                            icon={Icon}
                        />
                    ))}
                </div>

                <FilterBar as="div" icon={SlidersHorizontal} label="Filters">
                    <Form
                        {...index.form()}
                        className="flex flex-col gap-3 sm:col-span-2 lg:col-span-4 lg:flex-row"
                    >
                        <div className="relative flex-1">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                name="search"
                                defaultValue={filters.search}
                                placeholder="Search contact, email, company, phone, location, owner, or lead code..."
                                className="pl-9"
                            />
                        </div>
                        {filters.status && (
                            <input
                                type="hidden"
                                name="status"
                                value={filters.status}
                            />
                        )}
                        <Button type="submit">Search contacts</Button>
                        {(filters.search || filters.status) && (
                            <Button asChild type="button" variant="outline">
                                <Link href={index()}>Clear</Link>
                            </Button>
                        )}
                    </Form>
                    <div className="sm:col-span-2 lg:col-span-4">
                        <FilterTabs
                            tabs={statuses.map(([value, label]) => ({
                                label,
                                href: index({
                                    query: {
                                        status: value || undefined,
                                        search: filters.search || undefined,
                                    },
                                }),
                                active: filters.status === value,
                            }))}
                        />
                    </div>
                </FilterBar>

                <p className="px-1 text-sm text-muted-foreground">
                    Showing {leads.from ?? 0}–{leads.to ?? 0} of {leads.total}{' '}
                    contacts
                    {filters.search && ` for “${filters.search}”`}
                </p>
                {leads.data.length ? (
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead>Company</TableHead>
                                <TableHead>Contact</TableHead>
                                <TableHead>Location</TableHead>
                                <TableHead>Owner</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Activity</TableHead>
                                <TableHead>
                                    <span className="sr-only">Actions</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {leads.data.map((lead) => (
                                <TableRow key={lead.id}>
                                    <TableCell>
                                        <p className="font-medium">
                                            {lead.company_name}
                                        </p>
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
                                    <TableCell>
                                        {lead.agent?.name || 'Unassigned'}
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge value={lead.status} />
                                    </TableCell>
                                    <TableCell className="text-xs text-muted-foreground">
                                        {lead.structured_notes_count} notes ·{' '}
                                        {lead.attachments_count} files
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
                                                <Link href={show(lead.id)}>
                                                    Review
                                                </Link>
                                            </Button>
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
    breadcrumbs: [{ title: 'Verification', href: index() }],
};
