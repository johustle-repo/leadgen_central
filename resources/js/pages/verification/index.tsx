import { Form, Head, Link } from '@inertiajs/react';
import {
    Download,
    FileText,
    Plus,
    Search,
    Sparkles,
    UserCheck,
} from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
                <PageHeader
                    title="Lead verification"
                    description="Search contacts, classify opportunities, keep supporting documents, and export a sales-ready Possible Leads list."
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Button asChild>
                                <Link href={possibleLeads.create()}>
                                    <Plus />
                                    Add Possible Lead
                                </Link>
                            </Button>
                            <Button asChild variant="outline">
                                <a href={exportUrl}>
                                    <Download />
                                    Export possible leads
                                </a>
                            </Button>
                        </div>
                    }
                />

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
                        <div
                            key={label}
                            className="rounded-xl border bg-card p-4 shadow-sm"
                        >
                            <div className="flex items-center justify-between gap-3">
                                <div>
                                    <p className="text-sm text-muted-foreground">
                                        {label}
                                    </p>
                                    <p className="mt-1 text-2xl font-semibold">
                                        {value}
                                    </p>
                                </div>
                                <div className="rounded-lg bg-primary/10 p-2 text-primary">
                                    <Icon className="size-5" />
                                </div>
                            </div>
                        </div>
                    ))}
                </div>

                <div className="rounded-xl border bg-card p-4">
                    <Form
                        {...index.form()}
                        className="flex flex-col gap-3 lg:flex-row"
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
                    <div className="mt-4 flex flex-wrap gap-2">
                        {statuses.map(([value, label]) => (
                            <Button
                                key={value}
                                asChild
                                variant={
                                    filters.status === value
                                        ? 'default'
                                        : 'outline'
                                }
                                size="sm"
                            >
                                <Link
                                    href={index({
                                        query: {
                                            status: value || undefined,
                                            search: filters.search || undefined,
                                        },
                                    })}
                                >
                                    {label}
                                </Link>
                            </Button>
                        ))}
                    </div>
                </div>

                <div className="overflow-hidden rounded-xl border bg-card">
                    <div className="flex items-center justify-between border-b px-4 py-3 text-sm text-muted-foreground">
                        <span>
                            Showing {leads.from ?? 0}–{leads.to ?? 0} of{' '}
                            {leads.total} contacts
                        </span>
                        {filters.search && (
                            <span>Results for “{filters.search}”</span>
                        )}
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/60 text-left">
                                <tr>
                                    <th className="p-3">Company</th>
                                    <th className="p-3">Contact</th>
                                    <th className="p-3">Location</th>
                                    <th className="p-3">Owner</th>
                                    <th className="p-3">Status</th>
                                    <th className="p-3">Activity</th>
                                    <th className="p-3" />
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {leads.data.map((lead) => (
                                    <tr
                                        key={lead.id}
                                        className="transition-colors hover:bg-muted/30"
                                    >
                                        <td className="p-3">
                                            <p className="font-medium">
                                                {lead.company_name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {lead.website_domain ||
                                                    lead.lead_code}
                                            </p>
                                        </td>
                                        <td className="p-3">
                                            <p>
                                                {lead.contact_person ||
                                                    'No contact name'}
                                            </p>
                                            <p className="max-w-64 truncate text-xs text-muted-foreground">
                                                {lead.position ||
                                                    lead.email ||
                                                    '—'}
                                            </p>
                                        </td>
                                        <td className="p-3">
                                            {[lead.city, lead.country]
                                                .filter(Boolean)
                                                .join(', ') || '—'}
                                            <p className="text-xs text-muted-foreground">
                                                {lead.timezone}
                                            </p>
                                        </td>
                                        <td className="p-3">
                                            {lead.agent?.name || 'Unassigned'}
                                        </td>
                                        <td className="p-3">
                                            <StatusBadge value={lead.status} />
                                        </td>
                                        <td className="p-3 text-xs text-muted-foreground">
                                            {lead.structured_notes_count} notes
                                            · {lead.attachments_count} files
                                        </td>
                                        <td className="p-3">
                                            <div className="flex justify-end gap-2">
                                                {lead.status !==
                                                    'possible_lead' && (
                                                    <Form
                                                        {...markPossible.form(
                                                            lead.id,
                                                        )}
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
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {!leads.data.length && (
                            <div className="p-12 text-center">
                                <Search className="mx-auto size-8 text-muted-foreground" />
                                <p className="mt-3 font-medium">
                                    No matching contacts
                                </p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Try a broader search or another verification
                                    status.
                                </p>
                            </div>
                        )}
                    </div>
                </div>
                <Pagination links={leads.links} />
            </div>
        </>
    );
}
VerificationIndex.layout = {
    breadcrumbs: [{ title: 'Verification', href: index() }],
};
