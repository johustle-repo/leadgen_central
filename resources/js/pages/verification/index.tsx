import { Head, Link } from '@inertiajs/react';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { index, show } from '@/routes/verification';

type Lead = {
    id: number;
    lead_code: string;
    company_name: string;
    website_domain: string | null;
    city: string | null;
    country: string | null;
    timezone: string | null;
    status: string;
    validation_status: string;
    structured_notes_count: number;
    agent: { name: string } | null;
};

export default function VerificationIndex({
    leads,
    status,
}: {
    leads: {
        data: Lead[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    status: string;
}) {
    return (
        <>
            <Head title="Verification" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Lead verification"
                    description="Review, correct, classify, and forward leads at high volume."
                />
                <div className="flex flex-wrap gap-2">
                    {[
                        '',
                        'needs_review',
                        'possible_lead',
                        'qualified_lead',
                        'not_a_lead',
                        'forwarded',
                    ].map((value) => (
                        <Button
                            key={value}
                            asChild
                            variant={status === value ? 'default' : 'outline'}
                            size="sm"
                        >
                            <Link
                                href={index({
                                    query: { status: value || undefined },
                                })}
                            >
                                {value
                                    ? value.replaceAll('_', ' ')
                                    : 'Review queue'}
                            </Link>
                        </Button>
                    ))}
                </div>
                <div className="overflow-hidden rounded-xl border bg-card">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/60 text-left">
                                <tr>
                                    <th className="p-3">Company</th>
                                    <th className="p-3">Location</th>
                                    <th className="p-3">Owner</th>
                                    <th className="p-3">Status</th>
                                    <th className="p-3">Notes</th>
                                    <th className="p-3" />
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {leads.data.map((lead) => (
                                    <tr key={lead.id}>
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
                                        <td className="p-3">
                                            {lead.structured_notes_count}
                                        </td>
                                        <td className="p-3 text-right">
                                            <Button asChild size="sm">
                                                <Link href={show(lead.id)}>
                                                    Review
                                                </Link>
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {!leads.data.length && (
                            <p className="p-12 text-center text-muted-foreground">
                                No leads in this queue.
                            </p>
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
