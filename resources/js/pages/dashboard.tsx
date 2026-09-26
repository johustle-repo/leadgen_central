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
import { HeaderActionsPortal } from '@/components/header-actions';
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
                            {selectedLabel} · compared with {previousLabel} ·{' '}
                            {auth.user.role === 'agent'
                                ? 'Your records only'
                                : 'All records'}
                        </p>
                        {Object.keys(errors).length > 0 && (
                            <p
                                role="alert"
                                className="mt-2 text-sm text-destructive"
                            >
                                {Object.values(errors).join(' ')}
                            </p>
                        )}
                    </div>
                </header>

                <div className="relative overflow-hidden rounded-xl border border-primary/20 bg-primary/5 p-4">
                    <div className="absolute inset-y-0 left-0 w-1 bg-primary" />
                    <p className="pl-3 text-sm leading-relaxed font-medium text-foreground">
                        {summarize(data)}
                    </p>
                </div>

                <Section
                    title="Database overview"
                    note="Records created in the selected period."
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
                    note="Uploads in the selected period · full breakdown on Reports."
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
                    note={`Records added per ${data.growth.granularity}.`}
                >
                    <Card>
                        <CardContent className="pt-6">
                            <GrowthChart growth={data.growth} />
                        </CardContent>
                    </Card>
                </Section>

                <Section
                    title="Recent database activity"
                    note="Latest records in the selected period."
                >
                    <Card>
                        <CardHeader className="flex-row items-center justify-between">
                            <CardTitle>Recent records</CardTitle>
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
