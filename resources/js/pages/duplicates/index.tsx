import { Form, Head } from '@inertiajs/react';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { index, update } from '@/routes/duplicates';

type Lead = {
    id: number;
    lead_code: string;
    company_name: string;
    website: string | null;
    website_domain: string | null;
    email: string | null;
    city: string | null;
    country: string | null;
    created_at: string;
    agent: { name: string } | null;
};
type Match = {
    id: number;
    match_type: string;
    match_score: number | null;
    matched_fields: string[];
    status: string;
    existing_lead: Lead | null;
    incoming_lead: Lead | null;
    upload_row: { raw_data: Record<string, string> } | null;
};

export default function DuplicateIndex({
    matches,
}: {
    matches: {
        data: Match[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
}) {
    return (
        <>
            <Head title="Duplicate Review" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Duplicate review"
                    description="Compare submissions while preserving original lead ownership."
                />
                <div className="flex flex-col gap-4">
                    {matches.data.map((match) => (
                        <section
                            key={match.id}
                            className="rounded-xl border bg-card p-5"
                        >
                            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                                <div className="flex items-center gap-2">
                                    <StatusBadge value={match.match_type} />
                                    <StatusBadge value={match.status} />
                                    <span className="text-sm text-muted-foreground">
                                        Score {match.match_score ?? '—'} ·{' '}
                                        {match.matched_fields.join(', ')}
                                    </span>
                                </div>
                            </div>
                            <div className="grid gap-4 lg:grid-cols-2">
                                <LeadPanel
                                    title="Incoming submission"
                                    lead={match.incoming_lead}
                                    raw={match.upload_row?.raw_data}
                                />
                                <LeadPanel
                                    title="Existing owner record"
                                    lead={match.existing_lead}
                                />
                            </div>
                            <Form
                                {...update.form(match.id)}
                                className="mt-4 flex flex-wrap justify-end gap-2"
                            >
                                {({ processing }) => (
                                    <>
                                        {[
                                            ['not_duplicate', 'Not duplicate'],
                                            ['keep_both', 'Keep both'],
                                            [
                                                'confirm_duplicate',
                                                'Confirm duplicate',
                                            ],
                                        ].map(([value, label]) => (
                                            <Button
                                                key={value}
                                                name="action"
                                                value={value}
                                                type="submit"
                                                variant={
                                                    value ===
                                                    'confirm_duplicate'
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                                disabled={processing}
                                            >
                                                {label}
                                            </Button>
                                        ))}
                                    </>
                                )}
                            </Form>
                        </section>
                    ))}
                    {!matches.data.length && (
                        <p className="rounded-xl border p-12 text-center text-muted-foreground">
                            No duplicate matches found.
                        </p>
                    )}
                </div>
                <Pagination links={matches.links} />
            </div>
        </>
    );
}
function LeadPanel({
    title,
    lead,
    raw,
}: {
    title: string;
    lead?: Lead | null;
    raw?: Record<string, string>;
}) {
    return (
        <div className="rounded-lg border bg-muted/20 p-4">
            <h2 className="mb-3 font-semibold">{title}</h2>
            {lead ? (
                <dl className="grid gap-2 text-sm">
                    <div>
                        <dt className="text-muted-foreground">Company</dt>
                        <dd>{lead.company_name}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">
                            Domain / Email
                        </dt>
                        <dd>{lead.website_domain || lead.email || '—'}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Location</dt>
                        <dd>
                            {[lead.city, lead.country]
                                .filter(Boolean)
                                .join(', ') || '—'}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Owner</dt>
                        <dd>{lead.agent?.name || 'Unassigned'}</dd>
                    </div>
                </dl>
            ) : (
                <dl className="grid gap-2 text-sm">
                    {Object.entries(raw ?? {})
                        .filter(([, value]) => value)
                        .map(([key, value]) => (
                            <div key={key}>
                                <dt className="text-muted-foreground">{key}</dt>
                                <dd>{value}</dd>
                            </div>
                        ))}
                </dl>
            )}
        </div>
    );
}
DuplicateIndex.layout = {
    breadcrumbs: [{ title: 'Duplicate Review', href: index() }],
};
