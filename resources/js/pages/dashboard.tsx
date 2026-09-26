import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Building2,
    Database,
    Globe2,
    Mail,
    ShieldCheck,
    Upload,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import {
    BarList,
    CATEGORICAL,
    Donut,
    KpiCard,
    TrendArea,
    Visual,
} from '@/components/bi-visuals';
import { formatLabel, percent, summarize } from '@/components/database-charts';
import { HeaderActionsPortal } from '@/components/header-actions';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
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
import type { DatabaseAnalytics } from '@/types/database-analytics';

type Props = {
    period: string;
    filters: { date_from: string; date_to: string };
    databaseAnalytics: DatabaseAnalytics;
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

const ENTRY_METHOD_COLORS: Record<string, string> = {
    csv: CATEGORICAL[0],
    manual: CATEGORICAL[1],
    scraper: CATEGORICAL[2],
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
    const trendOf = (key: 'records' | 'companies' | 'emails') =>
        data.growth.points.map((point) => ({
            date: point.date,
            value: point[key],
        }));
    const dateLabel = (value: string) =>
        new Intl.DateTimeFormat(undefined, {
            timeZone: data.timezone,
            month: 'short',
            day: 'numeric',
        }).format(new Date(value));
    const qualityRows = [
        { label: 'accepted', value: quality.accepted_rate ?? 0 },
        {
            label: 'needs_review',
            value:
                quality.processed > 0
                    ? Math.round(
                          (quality.needs_review / quality.processed) * 1000,
                      ) / 10
                    : 0,
        },
        { label: 'duplicates', value: quality.duplicates_rate ?? 0 },
        { label: 'rejected', value: quality.rejected_rate ?? 0 },
        { label: 'errors', value: quality.errors_rate ?? 0 },
    ];

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
            <HeaderActionsPortal>
                <form
                    onSubmit={applyPeriod}
                    className="flex flex-wrap items-center gap-2"
                    aria-busy={processing}
                >
                    <Select
                        value={selectedPeriod}
                        onValueChange={setSelectedPeriod}
                    >
                        <SelectTrigger
                            id="dashboard-period"
                            size="sm"
                            className="w-36"
                            aria-label="Reporting period"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {Object.entries(PERIODS).map(([value, label]) => (
                                <SelectItem key={value} value={value}>
                                    {label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    {isCustom && (
                        <>
                            <Input
                                key={`from-${filters.date_from}`}
                                id="date-from"
                                name="date_from"
                                type="date"
                                aria-label="From date"
                                defaultValue={filters.date_from}
                                required={isCustom}
                                className="h-8 w-36"
                            />
                            <Input
                                key={`to-${filters.date_to}`}
                                id="date-to"
                                name="date_to"
                                type="date"
                                aria-label="To date"
                                defaultValue={filters.date_to}
                                required={isCustom}
                                className="h-8 w-36"
                            />
                        </>
                    )}
                    <Select
                        value={granularity}
                        onValueChange={(value) =>
                            setGranularity(value as typeof granularity)
                        }
                    >
                        <SelectTrigger
                            id="growth-interval"
                            size="sm"
                            className="w-28"
                            aria-label="Growth interval"
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
                    <Button type="submit" size="sm" disabled={processing}>
                        {processing ? 'Updating…' : 'Apply'}
                    </Button>
                </form>
            </HeaderActionsPortal>
            <div className="flex min-w-0 flex-1 flex-col gap-4 bg-muted/40 p-4 md:p-6">
                <header className="flex flex-wrap items-end justify-between gap-3 rounded-lg bg-primary px-5 py-4 text-primary-foreground shadow-xs">
                    <div className="min-w-0">
                        <h1 className="text-xl font-semibold tracking-tight">
                            Database intelligence
                        </h1>
                        <p className="mt-1 text-sm opacity-90">
                            {summarize(data)}
                        </p>
                    </div>
                    <p className="text-xs opacity-80">
                        {selectedLabel} · vs {previousLabel} ·{' '}
                        {auth.user.role === 'agent'
                            ? 'Your records only'
                            : 'All records'}
                    </p>
                </header>
                {Object.keys(errors).length > 0 && (
                    <p role="alert" className="text-sm text-destructive">
                        {Object.values(errors).join(' ')}
                    </p>
                )}

                <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3 2xl:grid-cols-6">
                    <KpiCard
                        label="Contact records"
                        value={data.overview.records}
                        change={data.changes.records}
                        footnote={`${data.all_time.records.toLocaleString()} all time`}
                        icon={Database}
                        trend={trendOf('records')}
                        trendLabel="Records added"
                    />
                    <KpiCard
                        label="Unique companies"
                        value={data.overview.companies}
                        change={data.changes.companies}
                        footnote={`${data.all_time.companies.toLocaleString()} all time`}
                        icon={Building2}
                        trend={trendOf('companies')}
                        trendLabel="First-seen companies"
                    />
                    <KpiCard
                        label="Unique emails"
                        value={data.overview.emails}
                        change={data.changes.emails}
                        footnote={`${data.all_time.emails.toLocaleString()} all time`}
                        icon={Mail}
                        trend={trendOf('emails')}
                        trendLabel="First-seen emails"
                    />
                    <KpiCard
                        label="Countries"
                        value={data.overview.countries}
                        change={data.changes.countries}
                        footnote={`${data.all_time.countries.toLocaleString()} all time`}
                        icon={Globe2}
                    />
                    <KpiCard
                        label="Upload batches"
                        value={data.overview.uploads}
                        change={data.changes.uploads}
                        footnote={`${data.all_time.uploads.toLocaleString()} all time`}
                        icon={Upload}
                    />
                    <KpiCard
                        label="Acceptance rate"
                        value={percent(quality.accepted_rate)}
                        footnote={`${quality.accepted.toLocaleString()} of ${quality.processed.toLocaleString()} rows`}
                        icon={ShieldCheck}
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-12">
                    <Visual
                        title="Records added over time"
                        subtitle={`Per ${data.growth.granularity}`}
                        className="lg:col-span-8"
                    >
                        <TrendArea
                            points={trendOf('records')}
                            seriesLabel="Records added"
                        />
                    </Visual>
                    <Visual
                        title="Records by entry method"
                        className="lg:col-span-4"
                    >
                        <Donut
                            rows={data.distributions.entry_methods}
                            centerLabel="records"
                            colors={ENTRY_METHOD_COLORS}
                        />
                    </Visual>
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <Visual title="Leads by status">
                        <BarList
                            rows={data.distributions.statuses.slice(0, 8)}
                        />
                    </Visual>
                    <Visual title="Top countries">
                        <BarList
                            rows={data.distributions.countries.slice(0, 8)}
                            uppercase
                        />
                    </Visual>
                    <Visual title="Top companies" subtitle="By contact records">
                        <BarList
                            rows={data.companies.slice(0, 8).map((row) => ({
                                label: row.label,
                                value: row.contacts,
                            }))}
                            uppercase
                            empty="No named companies in this period."
                        />
                    </Visual>
                </div>

                <div className="grid gap-4 lg:grid-cols-12">
                    <Visual
                        title="Upload quality"
                        subtitle="Share of processed rows; outcomes can overlap"
                        className="lg:col-span-4"
                    >
                        <BarList
                            rows={quality.processed > 0 ? qualityRows : []}
                            format={(value) => percent(value)}
                            scaleMax={100}
                            empty="No processed upload rows in this period."
                        />
                    </Visual>
                    <Visual
                        title={
                            data.can_compare_agents
                                ? 'Records by owner'
                                : 'Records by data source'
                        }
                        className="lg:col-span-3"
                    >
                        <BarList
                            rows={(data.can_compare_agents
                                ? data.distributions.owners
                                : data.distributions.sources
                            ).slice(0, 8)}
                        />
                    </Visual>
                    <Visual
                        title="Recent records"
                        className="lg:col-span-5"
                        action={
                            <Link
                                href={leadsIndex()}
                                className="shrink-0 text-xs text-primary hover:underline"
                            >
                                All leads
                            </Link>
                        }
                    >
                        {recentLeads.length === 0 ? (
                            <p className="py-6 text-sm text-muted-foreground">
                                No records in this period.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-xs">
                                    <thead className="border-b text-muted-foreground">
                                        <tr>
                                            <th className="py-2 pr-3 font-medium">
                                                Company
                                            </th>
                                            <th className="py-2 pr-3 font-medium">
                                                Owner
                                            </th>
                                            <th className="py-2 pr-3 font-medium">
                                                Created
                                            </th>
                                            <th className="py-2 font-medium">
                                                Status
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {recentLeads.map((lead) => (
                                            <tr
                                                key={lead.id}
                                                className="hover:bg-muted/50"
                                            >
                                                <td className="max-w-40 py-2 pr-3">
                                                    <Link
                                                        href={leadEdit(lead.id)}
                                                        className="block truncate font-medium hover:text-primary"
                                                    >
                                                        {lead.company_name}
                                                    </Link>
                                                </td>
                                                <td className="py-2 pr-3 whitespace-nowrap text-muted-foreground">
                                                    {lead.agent?.name ??
                                                        'Deleted user'}
                                                </td>
                                                <td className="py-2 pr-3 whitespace-nowrap text-muted-foreground">
                                                    {dateLabel(lead.created_at)}
                                                </td>
                                                <td className="py-2">
                                                    <StatusBadge
                                                        value={lead.status}
                                                    />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Visual>
                </div>

                <Link
                    href={reportIndex()}
                    className="self-start text-sm text-primary hover:underline"
                >
                    Open detailed lead reports and exports
                </Link>
            </div>
        </>
    );
}

Dashboard.layout = { breadcrumbs: [{ title: 'Dashboard', href: dashboard() }] };
