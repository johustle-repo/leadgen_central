import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    Building2,
    Copy,
    Database,
    Globe2,
    Mail,
    ShieldCheck,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import {
    changeLabel,
    formatLabel,
    GrowthChart,
    percent,
    summarize,
} from '@/components/database-charts';
import { Section } from '@/components/report-section';
import { StatTile } from '@/components/stat-tile';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dashboard } from '@/routes';
import { edit as leadEdit, index as leadsIndex } from '@/routes/leads';
import { index as reportIndex } from '@/routes/report';
import type { Auth } from '@/types';
import type { DatabaseAnalytics, Overview } from '@/types/database-analytics';

type Props = {
    stats: Record<string, number>;
    period: string;
    filters: { date_from: string; date_to: string };
    databaseAnalytics: DatabaseAnalytics;
    recentBatches: Array<{
        id: number;
        batch_code: string;
        original_filename: string;
        processing_status: string;
        total_rows: number;
        created_at: string;
        user: { name: string } | null;
    }>;
    recentLeads: Array<{
        id: number;
        lead_code: string;
        company_name: string;
        status: string;
        created_at: string;
        agent: { name: string } | null;
    }>;
};

const PERIODS = {
    today: 'Today',
    week: 'This Week',
    last_week: 'Last Week',
    month: 'This Month',
    last_month: 'Last Month',
    '30_days': 'Last 30 Days',
    quarter: 'This Quarter',
    custom: 'Custom Range',
};
const OVERVIEW_LABELS: Record<keyof Overview, string> = {
    records: 'Contact records',
    companies: 'Unique companies',
    emails: 'Unique email addresses',
    countries: 'Countries represented',
    cities: 'City labels',
    provinces: 'State / province labels',
    sources: 'Data sources',
    uploads: 'Upload batches',
};

export default function Dashboard({
    period,
    filters,
    databaseAnalytics: data,
    recentLeads,
}: Props) {
    const { auth, errors } = usePage<{
        auth: Auth;
        errors: Record<string, string>;
    }>().props;
    const [selectedPeriod, setSelectedPeriod] = useState(period);
    const [granularity, setGranularity] = useState(data.growth.granularity);
    const [processing, setProcessing] = useState(false);
    const isCustom = selectedPeriod === 'custom';
    const selectedLabel = `${filters.date_from} – ${filters.date_to}`;
    const previousLabel = `${data.previous_period.from} – ${data.previous_period.to}`;
    const quality = data.quality;
    const dateLabel = (value: string) =>
        new Intl.DateTimeFormat(undefined, {
            timeZone: data.timezone,
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        }).format(new Date(value));

    function applyPeriod(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        router.get(
            dashboard.url(),
            {
                period: selectedPeriod,
                granularity,
                ...(isCustom
                    ? {
                          date_from: form.get('date_from') as string,
                          date_to: form.get('date_to') as string,
                      }
                    : {}),
            },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
            },
        );
    }

    return (
        <>
            <Head title="Database dashboard" />
            <div className="flex min-w-0 flex-1 flex-col gap-8 p-4 md:p-6">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="mb-1 text-xs font-medium tracking-wider text-primary uppercase">
                            LeadGen Central
                        </p>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Database intelligence
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Size, composition, quality and growth ·{' '}
                            {auth.user.role === 'agent'
                                ? 'Your records only'
                                : 'All-owner database scope'}
                        </p>
                    </div>
                </header>

                <form
                    onSubmit={applyPeriod}
                    className="rounded-xl border bg-card p-4"
                    aria-busy={processing}
                >
                    <div className="grid items-end gap-3 sm:grid-cols-2 xl:grid-cols-5">
                        <div className="space-y-1.5">
                            <label
                                htmlFor="dashboard-period"
                                className="text-xs font-medium"
                            >
                                Reporting period
                            </label>
                            <Select
                                value={selectedPeriod}
                                onValueChange={setSelectedPeriod}
                            >
                                <SelectTrigger
                                    id="dashboard-period"
                                    className="w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(PERIODS).map(
                                        ([value, label]) => (
                                            <SelectItem
                                                key={value}
                                                value={value}
                                            >
                                                {label}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1.5">
                            <label
                                htmlFor="date-from"
                                className="text-xs font-medium"
                            >
                                From
                            </label>
                            <Input
                                key={`from-${filters.date_from}`}
                                id="date-from"
                                name="date_from"
                                type="date"
                                defaultValue={filters.date_from}
                                disabled={!isCustom}
                                required={isCustom}
                            />
                        </div>
                        <div className="space-y-1.5">
                            <label
                                htmlFor="date-to"
                                className="text-xs font-medium"
                            >
                                To
                            </label>
                            <Input
                                key={`to-${filters.date_to}`}
                                id="date-to"
                                name="date_to"
                                type="date"
                                defaultValue={filters.date_to}
                                disabled={!isCustom}
                                required={isCustom}
                            />
                        </div>
                        <div className="space-y-1.5">
                            <label
                                htmlFor="growth-interval"
                                className="text-xs font-medium"
                            >
                                Growth interval
                            </label>
                            <Select
                                value={granularity}
                                onValueChange={(value) =>
                                    setGranularity(value as typeof granularity)
                                }
                            >
                                <SelectTrigger
                                    id="growth-interval"
                                    className="w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {['day', 'week', 'month'].map((value) => (
                                        <SelectItem key={value} value={value}>
                                            {formatLabel(value)}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Updating…' : 'Apply period'}
                        </Button>
                    </div>
                    {Object.keys(errors).length > 0 && (
                        <div
                            role="alert"
                            className="mt-3 text-sm text-destructive"
                        >
                            {Object.values(errors).join(' ')}
                        </div>
                    )}
                    <p className="mt-3 text-xs text-muted-foreground">
                        Showing {selectedLabel} · {data.timezone}. Comparisons
                        use the preceding equal-length period: {previousLabel}.
                        Custom ranges support up to ten years.
                    </p>
                </form>

                <div className="relative overflow-hidden rounded-xl border border-primary/20 bg-primary/5 p-4">
                    <div className="absolute inset-y-0 left-0 w-1 bg-primary" />
                    <p className="pl-3 text-sm leading-relaxed font-medium text-foreground">
                        {summarize(data)}
                    </p>
                </div>

                <Section
                    title="Database overview"
                    note={`Records created ${selectedLabel}. Current values and classifications; soft-deleted leads excluded.`}
                >
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        {(
                            [
                                { key: 'records', icon: Database },
                                { key: 'companies', icon: Building2 },
                                { key: 'emails', icon: Mail },
                                { key: 'countries', icon: Globe2 },
                            ] as const
                        ).map(({ key, icon }) => (
                            <StatTile
                                key={key}
                                label={OVERVIEW_LABELS[key]}
                                value={data.overview[key]}
                                icon={icon}
                                detail={
                                    <>
                                        <span>
                                            {changeLabel(data.changes[key])}
                                        </span>
                                        <span className="mt-1 block">
                                            All time:{' '}
                                            {data.all_time[
                                                key
                                            ].toLocaleString()}
                                        </span>
                                    </>
                                }
                            />
                        ))}
                    </div>
                </Section>

                <Section
                    title="Data quality"
                    note={`Uploads created ${selectedLabel} · full breakdown and trends are on Reports.`}
                >
                    <div className="grid gap-4 sm:grid-cols-3">
                        <StatTile
                            label="Acceptance rate"
                            value={percent(quality.accepted_rate)}
                            icon={ShieldCheck}
                            tone="text-success"
                            detail={`${quality.accepted.toLocaleString()} of ${quality.processed.toLocaleString()} processed rows`}
                        />
                        <StatTile
                            label="Duplicates"
                            value={quality.duplicates}
                            icon={Copy}
                            tone="text-warning"
                            detail={`${percent(quality.duplicates_rate)} duplicate rate`}
                        />
                        <StatTile
                            label="Rows with issues"
                            value={quality.issues}
                            icon={AlertTriangle}
                            tone="text-destructive"
                            detail={`${percent(quality.rejected_rate)} rejected · ${percent(quality.errors_rate)} error rate`}
                        />
                    </div>
                </Section>

                <Section
                    title="Database growth"
                    note={`Additions during ${selectedLabel}, grouped by ${data.growth.granularity}. This is not a cumulative sales funnel.`}
                >
                    <Card>
                        <CardContent className="pt-6">
                            <div className="mb-6 grid gap-4 sm:grid-cols-3">
                                {(
                                    ['records', 'companies', 'emails'] as const
                                ).map((key) => (
                                    <div key={key}>
                                        <p className="text-xs text-muted-foreground">
                                            {key === 'records'
                                                ? 'Records added'
                                                : `First-seen ${key}`}
                                        </p>
                                        <p className="text-2xl font-semibold tabular-nums">
                                            {data.growth.totals[
                                                key
                                            ].toLocaleString()}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {changeLabel(
                                                data.growth.totals[
                                                    `${key}_change`
                                                ],
                                            )}
                                        </p>
                                    </div>
                                ))}
                            </div>
                            <GrowthChart growth={data.growth} />
                            <p className="mt-4 text-xs text-muted-foreground">
                                First-seen companies and emails use their
                                earliest surviving record within your authorized
                                scope. Existing companies with new contacts do
                                not count as newly added companies. Partial
                                weeks/months include only selected dates.
                                Historical deletions, ownership changes and
                                edits cannot be reconstructed as a historical
                                database snapshot.
                            </p>
                        </CardContent>
                    </Card>
                </Section>

                <Section
                    title="Companies and contacts"
                    note={`Top 15 companies by contact records created ${selectedLabel}. Shared company names do not imply duplicate contacts.`}
                >
                    <Card>
                        <CardContent className="overflow-x-auto pt-6">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr>
                                        <th className="p-3">Company group</th>
                                        <th className="p-3 text-right">
                                            Contact records
                                        </th>
                                        <th className="p-3 text-right">
                                            Unique emails
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {data.companies.map((row) => (
                                        <tr key={row.label}>
                                            <td className="p-3 uppercase">
                                                {row.label}
                                            </td>
                                            <td className="p-3 text-right tabular-nums">
                                                {row.contacts.toLocaleString()}
                                            </td>
                                            <td className="p-3 text-right tabular-nums">
                                                {row.emails.toLocaleString()}
                                            </td>
                                        </tr>
                                    ))}
                                    {data.companies.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={3}
                                                className="p-6 text-center text-muted-foreground"
                                            >
                                                No named companies in this
                                                period.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                            <p className="mt-4 text-xs text-muted-foreground">
                                Company groups use normalized names, not a
                                verified company identifier. Same-name
                                businesses may be grouped together. A reliable
                                unique-person count requires a stable contact
                                identity beyond an email address.
                            </p>
                        </CardContent>
                    </Card>
                </Section>

                <Section
                    title="Recent database activity"
                    note={`Latest records and uploads created ${selectedLabel}.`}
                >
                    <Card>
                        <CardHeader className="flex-row items-center justify-between">
                            <CardTitle>
                                Recent records · selected period
                            </CardTitle>
                            <Link
                                href={leadsIndex()}
                                className="text-xs text-primary hover:underline"
                            >
                                All leads
                            </Link>
                        </CardHeader>
                        <CardContent className="divide-y">
                            {recentLeads.map((lead) => (
                                <Link
                                    key={lead.id}
                                    href={leadEdit(lead.id)}
                                    className="flex flex-wrap items-center justify-between gap-3 py-3 hover:text-primary"
                                >
                                    <div className="min-w-0">
                                        <p className="font-medium break-words">
                                            {lead.company_name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {lead.lead_code} ·{' '}
                                            {lead.agent?.name ?? 'Deleted user'}{' '}
                                            · {dateLabel(lead.created_at)}
                                        </p>
                                    </div>
                                    <StatusBadge value={lead.status} />
                                </Link>
                            ))}
                            {recentLeads.length === 0 && (
                                <p className="py-6 text-sm text-muted-foreground">
                                    No records in this period.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                    <Link
                        href={reportIndex()}
                        className="text-sm text-primary hover:underline"
                    >
                        Open detailed lead reports and exports
                    </Link>
                </Section>
            </div>
        </>
    );
}

Dashboard.layout = { breadcrumbs: [{ title: 'Dashboard', href: dashboard() }] };
