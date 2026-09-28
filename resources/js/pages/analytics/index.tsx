import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    Building2,
    CheckCircle2,
    Copy,
    Database,
    Globe2,
    Layers,
    Mail,
    MapPinOff,
    SearchCheck,
    Sparkles,
    Upload,
    XCircle,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { BarList, KpiCard, Visual } from '@/components/bi-visuals';
import {
    ChartLegend,
    ChartTooltip,
    formatLabel,
    GrowthChart,
    percent,
    summarize,
} from '@/components/database-charts';
import { HeaderActionsPortal } from '@/components/header-actions';
import { countryLabel, LeadDemographics } from '@/components/lead-demographics';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useReportRefresh } from '@/hooks/use-report-refresh';
import { leadDrilldownUrl, uploadDrilldownUrl } from '@/lib/lead-drilldown';
import {
    exportMethod as reportExport,
    exportPdf as reportExportPdf,
    index as reportIndex,
} from '@/routes/report';
import type { Auth } from '@/types';
import type { DatabaseReport } from '@/types/database-report';

type Props = {
    period: string;
    filters: { date_from: string; date_to: string };
    databaseReport: DatabaseReport;
    uploadTimingHeatmap: { day: string; hours: number[] }[];
    reportVersion: string;
};
type Tab = 'overview' | 'demographics' | 'quality' | 'agents';

const periods: Record<string, string> = {
    today: 'Today',
    week: 'This Week',
    last_week: 'Last Week',
    month: 'This Month',
    last_month: 'Last Month',
    '30_days': 'Last 30 Days',
    quarter: 'This Quarter',
    custom: 'Custom Range',
    '7_days': 'Last 7 Days',
    '90_days': 'Last 90 Days',
};
const QUALITY_SERIES = [
    'duplicates',
    'rejected',
    'errors',
    'location_issues',
] as const;
const SOURCE_RATE_SERIES = [
    { key: 'duplicates_rate', label: 'Duplicate rate' },
    { key: 'rejected_rate', label: 'Rejection rate' },
    { key: 'errors_rate', label: 'Error rate' },
] as const;

function sharePercent(value: number, total: number): number | null {
    return total > 0 ? Math.round((1000 * value) / total) / 10 : null;
}

function ReportTable({
    headings,
    rows,
}: {
    headings: string[];
    rows: ReactNode[][];
}) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full text-left text-xs">
                <thead className="border-b text-muted-foreground">
                    <tr>
                        {headings.map((heading, index) => (
                            <th
                                className={`py-2 pr-4 font-medium whitespace-nowrap ${index > 0 ? 'text-right' : ''}`}
                                key={heading}
                                scope="col"
                            >
                                {heading}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y">
                    {rows.map((row, index) => (
                        <tr key={index} className="hover:bg-muted/50">
                            {row.map((cell, column) => (
                                <td
                                    className={`py-2 pr-4 tabular-nums ${column > 0 ? 'text-right' : 'font-medium'}`}
                                    key={column}
                                >
                                    {cell}
                                </td>
                            ))}
                        </tr>
                    ))}
                    {rows.length === 0 && (
                        <tr>
                            <td
                                colSpan={headings.length}
                                className="py-6 text-center text-muted-foreground"
                            >
                                No data for this period.
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}

function DataBar({ value, max }: { value: number; max: number }) {
    return (
        <span className="relative ml-auto flex h-5 w-28 items-center justify-end">
            <span
                className="absolute inset-y-0.5 left-0 rounded-r-[3px] bg-chart-1/20"
                style={{ width: `${max > 0 ? (value / max) * 100 : 0}%` }}
                aria-hidden="true"
            />
            <span className="relative">{value.toLocaleString()}</span>
        </span>
    );
}

function initialTab(): Tab {
    if (typeof window === 'undefined') {
        return 'overview';
    }

    const hash = window.location.hash.slice(1);

    return ['overview', 'demographics', 'quality', 'agents'].includes(hash)
        ? (hash as Tab)
        : 'overview';
}

export default function Analytics({
    period,
    filters,
    databaseReport: data,
    uploadTimingHeatmap,
    reportVersion,
}: Props) {
    const { auth, errors } = usePage<{
        auth: Auth;
        errors: Record<string, string>;
    }>().props;
    const [selectedPeriod, setSelectedPeriod] = useState(period);
    const [granularity, setGranularity] = useState(data.growth.granularity);
    const [mode, setMode] = useState<'count' | 'rate'>('count');
    const [processing, setProcessing] = useState(false);
    useReportRefresh(reportVersion);
    const [tab, setTab] = useState<Tab>(initialTab);
    const selected = `${filters.date_from} – ${filters.date_to}`;
    const quality = data.quality;
    const appliedQuery = {
        period,
        ...filters,
        granularity: data.growth.granularity,
    };
    const possibleLeads = data.demographics.rows.reduce(
        (sum, row) => sum + row.possible,
        0,
    );
    const possibleByAgent = new Map<number, number>();
    data.demographics.rows.forEach((row) => {
        if (row.agent_id !== null) {
            possibleByAgent.set(
                row.agent_id,
                (possibleByAgent.get(row.agent_id) ?? 0) + row.possible,
            );
        }
    });
    const tabs: Array<{ key: Tab; label: string }> = [
        { key: 'overview', label: 'Overview' },
        { key: 'demographics', label: 'Demographics' },
        { key: 'quality', label: 'Data quality' },
        ...(data.can_compare_agents
            ? [{ key: 'agents' as const, label: 'Agents' }]
            : []),
    ];
    const activeTab = tabs.some((item) => item.key === tab) ? tab : 'overview';

    useEffect(() => {
        window.history.replaceState(
            window.history.state,
            '',
            `${window.location.pathname}${window.location.search}#${activeTab}`,
        );
    }, [activeTab]);

    function applyPeriod(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        router.get(
            reportIndex.url(),
            {
                period: selectedPeriod,
                granularity,
                ...(selectedPeriod === 'custom'
                    ? {
                          date_from: String(form.get('date_from')),
                          date_to: String(form.get('date_to')),
                      }
                    : {}),
            },
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
            },
        );
    }

    return (
        <>
            <Head title="Lead reports" />
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
                            size="sm"
                            className="w-36"
                            aria-label="Reporting period"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {Object.entries(periods)
                                .filter(
                                    ([key]) =>
                                        !['7_days', '90_days'].includes(key) ||
                                        period === key,
                                )
                                .map(([value, label]) => (
                                    <SelectItem key={value} value={value}>
                                        {label}
                                    </SelectItem>
                                ))}
                        </SelectContent>
                    </Select>
                    {selectedPeriod === 'custom' && (
                        <>
                            <Input
                                name="date_from"
                                type="date"
                                aria-label="From date"
                                defaultValue={filters.date_from}
                                required
                                className="h-8 w-36"
                            />
                            <Input
                                name="date_to"
                                type="date"
                                aria-label="Through date"
                                defaultValue={filters.date_to}
                                required
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
                            size="sm"
                            className="w-28"
                            aria-label="Group growth and quality by"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="day">Daily</SelectItem>
                            <SelectItem value="week">Weekly</SelectItem>
                            <SelectItem value="month">Monthly</SelectItem>
                        </SelectContent>
                    </Select>
                    <Button type="submit" size="sm" disabled={processing}>
                        {processing ? 'Applying…' : 'Apply'}
                    </Button>
                </form>
            </HeaderActionsPortal>
            <div className="flex min-w-0 flex-1 flex-col gap-4 bg-muted/40 p-4 md:p-6">
                <header className="accent-banner flex flex-wrap items-end justify-between gap-3 rounded-lg px-5 py-4 shadow-xs">
                    <div className="min-w-0">
                        <h1 className="text-xl font-semibold tracking-tight">
                            Lead reports
                        </h1>
                        <p className="mt-1 text-sm opacity-90">
                            {summarize(data)}
                        </p>
                        <p className="mt-1 text-xs opacity-80">
                            {selected} · vs {data.previous_period.from} –{' '}
                            {data.previous_period.to} ·{' '}
                            {auth.user.role === 'agent'
                                ? 'Your data only'
                                : 'All records'}{' '}
                            · {data.timezone}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button
                            variant="secondary"
                            size="sm"
                            asChild
                            className="bg-card text-foreground hover:bg-card/90"
                        >
                            <a
                                href={reportExport.url({
                                    query: appliedQuery,
                                })}
                            >
                                Export CSV
                            </a>
                        </Button>
                        <Button
                            variant="secondary"
                            size="sm"
                            asChild
                            className="bg-card text-foreground hover:bg-card/90"
                        >
                            <a
                                href={reportExportPdf.url({
                                    query: appliedQuery,
                                })}
                            >
                                Export PDF
                            </a>
                        </Button>
                    </div>
                </header>
                {Object.entries(errors).map(([key, message]) => (
                    <p
                        role="alert"
                        className="text-sm text-destructive"
                        key={key}
                    >
                        {message}
                    </p>
                ))}

                <nav
                    role="tablist"
                    aria-label="Report pages"
                    className="flex gap-1 overflow-x-auto border-b"
                >
                    {tabs.map((item) => (
                        <button
                            key={item.key}
                            type="button"
                            role="tab"
                            id={`tab-${item.key}`}
                            aria-selected={activeTab === item.key}
                            aria-controls={`panel-${item.key}`}
                            onClick={() => setTab(item.key)}
                            className={`-mb-px border-b-2 px-4 py-2 text-sm whitespace-nowrap transition-colors ${activeTab === item.key ? 'border-primary font-semibold text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground'}`}
                        >
                            {item.label}
                        </button>
                    ))}
                </nav>

                <div
                    role="tabpanel"
                    id={`panel-${activeTab}`}
                    aria-labelledby={`tab-${activeTab}`}
                    className="flex flex-col gap-4"
                >
                    {activeTab === 'overview' && (
                        <>
                            <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                                <KpiCard
                                    label="Records added"
                                    value={data.overview.records}
                                    change={data.changes.records}
                                    footnote={`${data.all_time.records.toLocaleString()} all time`}
                                    icon={Database}
                                    trend={data.growth.points.map((point) => ({
                                        date: point.date,
                                        value: point.records,
                                    }))}
                                    trendLabel="Records added"
                                    href={leadDrilldownUrl(filters, '', {})}
                                />
                                <KpiCard
                                    label="Unique companies"
                                    value={data.overview.companies}
                                    change={data.changes.companies}
                                    footnote={`${data.all_time.companies.toLocaleString()} all time`}
                                    icon={Building2}
                                    trend={data.growth.points.map((point) => ({
                                        date: point.date,
                                        value: point.companies,
                                    }))}
                                    trendLabel="First-seen companies"
                                />
                                <KpiCard
                                    label="Unique emails"
                                    value={data.overview.emails}
                                    change={data.changes.emails}
                                    footnote={`${data.all_time.emails.toLocaleString()} all time`}
                                    icon={Mail}
                                    trend={data.growth.points.map((point) => ({
                                        date: point.date,
                                        value: point.emails,
                                    }))}
                                    trendLabel="First-seen emails"
                                />
                                <KpiCard
                                    label="Possible leads"
                                    value={possibleLeads}
                                    href={leadDrilldownUrl(filters, '', {
                                        status: 'possible_lead',
                                    })}
                                    footnote={`${percent(sharePercent(possibleLeads, data.overview.records))} of records`}
                                    icon={Sparkles}
                                />
                                <KpiCard
                                    label="Countries"
                                    value={data.overview.countries}
                                    change={data.changes.countries}
                                    icon={Globe2}
                                />
                                <KpiCard
                                    label="Data sources"
                                    value={data.overview.sources}
                                    icon={Layers}
                                />
                                <KpiCard
                                    label="Upload batches"
                                    value={data.overview.uploads}
                                    change={data.changes.uploads}
                                    icon={Upload}
                                    href={uploadDrilldownUrl(filters)}
                                />
                                <KpiCard
                                    label="Duplicates detected"
                                    value={quality.duplicates}
                                    footnote="Exact and possible duplicate rows"
                                    icon={Copy}
                                    href={uploadDrilldownUrl(
                                        filters,
                                        'duplicates',
                                    )}
                                />
                            </div>
                            <div className="grid gap-4 lg:grid-cols-12">
                                <Visual
                                    title="Database growth"
                                    subtitle={`Per ${data.growth.granularity} · first-seen companies and emails count their earliest record`}
                                    className="lg:col-span-8"
                                >
                                    <GrowthChart growth={data.growth} />
                                </Visual>
                                <Visual
                                    title="Lead status"
                                    subtitle="Current classification, not a funnel"
                                    className="lg:col-span-4"
                                >
                                    <BarList
                                        rows={data.distributions.statuses.map(
                                            (row) => ({
                                                ...row,
                                                href: leadDrilldownUrl(
                                                    filters,
                                                    row.label,
                                                    { status: row.label },
                                                ),
                                            }),
                                        )}
                                    />
                                </Visual>
                            </div>
                            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                <Visual
                                    title="Data sources"
                                    subtitle="Click a bar to list its leads"
                                >
                                    <BarList
                                        rows={data.distributions.sources.map(
                                            (row) => ({
                                                ...row,
                                                href: leadDrilldownUrl(
                                                    filters,
                                                    row.label,
                                                    { source_group: row.label },
                                                ),
                                            }),
                                        )}
                                    />
                                </Visual>
                                <Visual
                                    title="Top countries"
                                    subtitle="See Demographics for regions and cities"
                                >
                                    <BarList
                                        rows={data.distributions.countries.map(
                                            (row) => ({
                                                ...row,
                                                label: countryLabel(row.label),
                                                href: leadDrilldownUrl(
                                                    filters,
                                                    row.label,
                                                    {
                                                        country_group:
                                                            row.label,
                                                    },
                                                ),
                                            }),
                                        )}
                                    />
                                </Visual>
                                <Visual
                                    title="Top companies"
                                    subtitle={`By contact records · ${data.company_analysis.unnamed_records.toLocaleString()} unnamed records excluded`}
                                >
                                    <BarList
                                        rows={data.companies.map((row) => ({
                                            label: row.label,
                                            value: row.contacts,
                                            href: leadDrilldownUrl(
                                                filters,
                                                row.label,
                                                { company: row.label },
                                            ),
                                        }))}
                                        uppercase
                                        empty="No named companies in this period."
                                    />
                                </Visual>
                                {data.show_industries && (
                                    <Visual
                                        title="Industries"
                                        subtitle={`${percent(data.industry_coverage)} of records have a known industry`}
                                    >
                                        <BarList
                                            rows={data.distributions.industries.map(
                                                (row) => ({
                                                    ...row,
                                                    href: leadDrilldownUrl(
                                                        filters,
                                                        row.label,
                                                        {
                                                            industry: row.label,
                                                        },
                                                    ),
                                                }),
                                            )}
                                        />
                                    </Visual>
                                )}
                            </div>
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-3 sm:gap-4">
                                <KpiCard
                                    label="Average contacts per company"
                                    value={
                                        data.company_analysis
                                            .average_contacts ?? 'N/A'
                                    }
                                    icon={Building2}
                                />
                                <KpiCard
                                    label="Companies with one contact"
                                    value={data.company_analysis.single_contact}
                                    icon={Building2}
                                />
                                <KpiCard
                                    label="Companies with multiple contacts"
                                    value={
                                        data.company_analysis.multiple_contacts
                                    }
                                    footnote="Repeated names are not automatically duplicates"
                                    icon={Building2}
                                />
                            </div>
                        </>
                    )}

                    {activeTab === 'demographics' && (
                        <LeadDemographics
                            demographics={data.demographics}
                            showAgents={data.can_compare_agents}
                            period={filters}
                        />
                    )}

                    {activeTab === 'quality' && (
                        <>
                            <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3 2xl:grid-cols-6">
                                <KpiCard
                                    label="Accepted"
                                    value={quality.accepted}
                                    footnote={`${percent(quality.accepted_rate)} · includes review`}
                                    icon={CheckCircle2}
                                    href={uploadDrilldownUrl(
                                        filters,
                                        'accepted',
                                    )}
                                />
                                <KpiCard
                                    label="Needs review"
                                    value={quality.needs_review}
                                    footnote="Included in accepted"
                                    icon={SearchCheck}
                                    href={uploadDrilldownUrl(
                                        filters,
                                        'needs_review',
                                    )}
                                />
                                <KpiCard
                                    label="Duplicates"
                                    value={quality.duplicates}
                                    footnote={percent(quality.duplicates_rate)}
                                    icon={Copy}
                                    href={uploadDrilldownUrl(
                                        filters,
                                        'duplicates',
                                    )}
                                />
                                <KpiCard
                                    label="Rejected"
                                    value={quality.rejected}
                                    footnote={percent(quality.rejected_rate)}
                                    icon={XCircle}
                                    href={uploadDrilldownUrl(
                                        filters,
                                        'rejected',
                                    )}
                                />
                                <KpiCard
                                    label="Errors"
                                    value={quality.errors}
                                    footnote={percent(quality.errors_rate)}
                                    icon={AlertTriangle}
                                    href={uploadDrilldownUrl(filters, 'errors')}
                                />
                                <KpiCard
                                    label="Location issues"
                                    value={quality.location_issues}
                                    footnote={percent(
                                        quality.location_issues_rate,
                                    )}
                                    icon={MapPinOff}
                                    href={uploadDrilldownUrl(
                                        filters,
                                        'location_issues',
                                    )}
                                />
                            </div>
                            <Visual
                                title="Data quality trend"
                                subtitle="Grouped by upload date · outcomes can overlap"
                                action={
                                    <div
                                        className="inline-flex rounded-md border bg-muted/40 p-0.5"
                                        role="radiogroup"
                                        aria-label="Display quality as"
                                    >
                                        {(['count', 'rate'] as const).map(
                                            (value) => (
                                                <button
                                                    key={value}
                                                    type="button"
                                                    role="radio"
                                                    aria-checked={
                                                        mode === value
                                                    }
                                                    onClick={() =>
                                                        setMode(value)
                                                    }
                                                    className={`rounded px-3 py-1 text-xs capitalize ${mode === value ? 'bg-card font-semibold text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'}`}
                                                >
                                                    {value}
                                                </button>
                                            ),
                                        )}
                                    </div>
                                }
                            >
                                <div
                                    className="h-64 min-w-0"
                                    role="img"
                                    aria-label={`Data quality trend by ${mode}, one bar group per period; exact values in the table below`}
                                >
                                    <ResponsiveContainer
                                        width="100%"
                                        height="100%"
                                    >
                                        <BarChart
                                            data={data.quality_trend}
                                            barGap={2}
                                            barCategoryGap="20%"
                                            accessibilityLayer
                                        >
                                            <CartesianGrid
                                                vertical={false}
                                                stroke="var(--border)"
                                            />
                                            <XAxis
                                                dataKey="date"
                                                tick={{
                                                    fill: 'var(--muted-foreground)',
                                                    fontSize: 11,
                                                }}
                                                minTickGap={40}
                                            />
                                            <YAxis
                                                unit={
                                                    mode === 'rate' ? '%' : ''
                                                }
                                                allowDecimals={mode === 'rate'}
                                                tick={{
                                                    fill: 'var(--muted-foreground)',
                                                    fontSize: 11,
                                                }}
                                                width={45}
                                            />
                                            <Tooltip
                                                content={ChartTooltip}
                                                cursor={{
                                                    fill: 'var(--muted)',
                                                    opacity: 0.4,
                                                }}
                                            />
                                            {QUALITY_SERIES.map(
                                                (key, index) => (
                                                    <Bar
                                                        key={key}
                                                        dataKey={
                                                            mode === 'rate'
                                                                ? `${key}_rate`
                                                                : key
                                                        }
                                                        name={formatLabel(key)}
                                                        fill={`var(--color-chart-${index + 1})`}
                                                        maxBarSize={20}
                                                        radius={[4, 4, 0, 0]}
                                                        isAnimationActive={
                                                            false
                                                        }
                                                    />
                                                ),
                                            )}
                                        </BarChart>
                                    </ResponsiveContainer>
                                </div>
                                <ChartLegend
                                    items={QUALITY_SERIES.map((key, index) => ({
                                        key,
                                        label: formatLabel(key),
                                        color: `var(--color-chart-${index + 1})`,
                                    }))}
                                />
                                <details className="mt-3 text-xs">
                                    <summary className="cursor-pointer text-muted-foreground">
                                        View data
                                    </summary>
                                    <div className="mt-2">
                                        <ReportTable
                                            headings={[
                                                'Period start',
                                                'Processed',
                                                ...QUALITY_SERIES.map(
                                                    formatLabel,
                                                ),
                                            ]}
                                            rows={data.quality_trend.map(
                                                (point) => [
                                                    point.date,
                                                    point.processed,
                                                    ...QUALITY_SERIES.map(
                                                        (key) =>
                                                            mode === 'rate'
                                                                ? percent(
                                                                      point[
                                                                          `${key}_rate`
                                                                      ],
                                                                  )
                                                                : point[key],
                                                    ),
                                                ],
                                            )}
                                        />
                                    </div>
                                </details>
                            </Visual>
                            <div className="grid gap-4 lg:grid-cols-12">
                                <Visual
                                    title="Upload volume"
                                    subtitle="Selected-period uploads"
                                    className="lg:col-span-4"
                                    action={
                                        <Link
                                            href={uploadDrilldownUrl(filters)}
                                            className="shrink-0 text-xs text-primary hover:underline"
                                        >
                                            View uploads
                                        </Link>
                                    }
                                >
                                    <dl className="grid grid-cols-2 gap-4 text-xs">
                                        {[
                                            ['Uploads', data.overview.uploads],
                                            [
                                                'Rows submitted',
                                                quality.submitted_rows,
                                            ],
                                            [
                                                'Observed rows',
                                                quality.observed_rows,
                                            ],
                                            [
                                                'Pending rows',
                                                quality.observed_rows -
                                                    quality.processed,
                                            ],
                                            [
                                                'Processed rows',
                                                quality.processed,
                                            ],
                                            [
                                                'Average batch size',
                                                quality.average_batch_size ??
                                                    'N/A',
                                            ],
                                        ].map(([label, value]) => (
                                            <div key={label}>
                                                <dt className="text-muted-foreground">
                                                    {label}
                                                </dt>
                                                <dd className="mt-0.5 text-lg font-semibold text-foreground">
                                                    {typeof value === 'number'
                                                        ? value.toLocaleString()
                                                        : value}
                                                </dd>
                                            </div>
                                        ))}
                                    </dl>
                                </Visual>
                                <Visual
                                    title="Row outcomes"
                                    subtitle="Share of processed rows · outcomes can overlap"
                                    className="lg:col-span-4"
                                >
                                    <BarList
                                        rows={
                                            quality.processed > 0
                                                ? [
                                                      {
                                                          label: 'accepted',
                                                          value:
                                                              quality.accepted_rate ??
                                                              0,
                                                          href: uploadDrilldownUrl(
                                                              filters,
                                                              'accepted',
                                                          ),
                                                      },
                                                      {
                                                          label: 'duplicates',
                                                          value:
                                                              quality.duplicates_rate ??
                                                              0,
                                                          href: uploadDrilldownUrl(
                                                              filters,
                                                              'duplicates',
                                                          ),
                                                      },
                                                      {
                                                          label: 'rejected',
                                                          value:
                                                              quality.rejected_rate ??
                                                              0,
                                                          href: uploadDrilldownUrl(
                                                              filters,
                                                              'rejected',
                                                          ),
                                                      },
                                                      {
                                                          label: 'errors',
                                                          value:
                                                              quality.errors_rate ??
                                                              0,
                                                          href: uploadDrilldownUrl(
                                                              filters,
                                                              'errors',
                                                          ),
                                                      },
                                                      {
                                                          label: 'clean',
                                                          value:
                                                              quality.clean_rate ??
                                                              0,
                                                      },
                                                  ]
                                                : []
                                        }
                                        format={(value) => percent(value)}
                                        scaleMax={100}
                                        empty="No processed rows in this period."
                                    />
                                </Visual>
                                <Visual
                                    title="Rates by source"
                                    subtitle="Import rows with a processed outcome"
                                    className="lg:col-span-4"
                                >
                                    <div
                                        className="h-52 min-w-0"
                                        role="img"
                                        aria-label="Duplicate, rejection and error rate by source; exact values in the source table below"
                                    >
                                        <ResponsiveContainer
                                            width="100%"
                                            height="100%"
                                        >
                                            <BarChart
                                                data={data.source_quality.filter(
                                                    (source) =>
                                                        source.processed > 0,
                                                )}
                                                barGap={2}
                                                barCategoryGap="20%"
                                                accessibilityLayer
                                            >
                                                <CartesianGrid
                                                    vertical={false}
                                                    stroke="var(--border)"
                                                />
                                                <XAxis
                                                    dataKey="label"
                                                    tick={{
                                                        fill: 'var(--muted-foreground)',
                                                        fontSize: 11,
                                                    }}
                                                />
                                                <YAxis
                                                    unit="%"
                                                    width={40}
                                                    tick={{
                                                        fill: 'var(--muted-foreground)',
                                                        fontSize: 11,
                                                    }}
                                                />
                                                <Tooltip
                                                    content={ChartTooltip}
                                                    cursor={{
                                                        fill: 'var(--muted)',
                                                        opacity: 0.4,
                                                    }}
                                                />
                                                {SOURCE_RATE_SERIES.map(
                                                    (series, index) => (
                                                        <Bar
                                                            key={series.key}
                                                            dataKey={series.key}
                                                            name={series.label}
                                                            fill={`var(--color-chart-${index + 1})`}
                                                            maxBarSize={24}
                                                            radius={[
                                                                4, 4, 0, 0,
                                                            ]}
                                                            isAnimationActive={
                                                                false
                                                            }
                                                        />
                                                    ),
                                                )}
                                            </BarChart>
                                        </ResponsiveContainer>
                                    </div>
                                    <ChartLegend
                                        items={SOURCE_RATE_SERIES.map(
                                            (series, index) => ({
                                                key: series.key,
                                                label: series.label,
                                                color: `var(--color-chart-${index + 1})`,
                                            }),
                                        )}
                                    />
                                </Visual>
                            </div>
                            <Visual
                                title="Source quality"
                                subtitle="Total records use current lead sources; import outcomes use saved source snapshots, so the two are distinct populations"
                            >
                                <ReportTable
                                    headings={[
                                        'Source',
                                        'Total records',
                                        'Import rows',
                                        'Processed rows',
                                        'Accepted rows',
                                        'Duplicate rate',
                                        'Rejection rate',
                                        'Error rate',
                                    ]}
                                    rows={data.source_quality.map((source) => [
                                        <Link
                                            key="source"
                                            href={
                                                leadDrilldownUrl(
                                                    filters,
                                                    source.label,
                                                    {
                                                        source_group:
                                                            source.label,
                                                    },
                                                ) ?? '#'
                                            }
                                            className="hover:underline"
                                        >
                                            {source.label}
                                        </Link>,
                                        source.records.toLocaleString(),
                                        source.observed_rows.toLocaleString(),
                                        source.processed.toLocaleString(),
                                        source.accepted.toLocaleString(),
                                        percent(source.duplicates_rate),
                                        percent(source.rejected_rate),
                                        percent(source.errors_rate),
                                    ])}
                                />
                            </Visual>
                            {uploadTimingHeatmap.length > 0 && (
                                <Visual
                                    title="Upload timing"
                                    subtitle={`Batches by weekday and hour · ${data.timezone}`}
                                >
                                    <div className="overflow-x-auto">
                                        <table className="w-full text-center text-xs">
                                            <thead>
                                                <tr>
                                                    <th
                                                        scope="col"
                                                        className="text-left font-medium text-muted-foreground"
                                                    >
                                                        Day / hour
                                                    </th>
                                                    {Array.from(
                                                        { length: 24 },
                                                        (_, hour) => (
                                                            <th
                                                                key={hour}
                                                                className="min-w-7 p-1 font-medium text-muted-foreground"
                                                                scope="col"
                                                            >
                                                                {hour}
                                                            </th>
                                                        ),
                                                    )}
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {uploadTimingHeatmap.map(
                                                    (row) => {
                                                        const max = Math.max(
                                                            ...uploadTimingHeatmap.flatMap(
                                                                (day) =>
                                                                    day.hours,
                                                            ),
                                                            1,
                                                        );

                                                        return (
                                                            <tr key={row.day}>
                                                                <th
                                                                    scope="row"
                                                                    className="p-1.5 text-left font-medium"
                                                                >
                                                                    {row.day}
                                                                </th>
                                                                {row.hours.map(
                                                                    (
                                                                        count,
                                                                        hour,
                                                                    ) => (
                                                                        <td
                                                                            key={
                                                                                hour
                                                                            }
                                                                            title={`${row.day} ${hour}:00 · ${count} uploads`}
                                                                            className={`border-2 border-card p-1 tabular-nums ${count > 0 ? 'font-medium text-foreground' : 'bg-muted/40 text-muted-foreground'}`}
                                                                            style={
                                                                                count >
                                                                                0
                                                                                    ? {
                                                                                          backgroundColor: `color-mix(in oklab, var(--color-chart-1) ${Math.round(15 + (count / max) * 55)}%, transparent)`,
                                                                                      }
                                                                                    : undefined
                                                                            }
                                                                        >
                                                                            {count ||
                                                                                '·'}
                                                                        </td>
                                                                    ),
                                                                )}
                                                            </tr>
                                                        );
                                                    },
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                </Visual>
                            )}
                        </>
                    )}

                    {activeTab === 'agents' && (
                        <>
                            <div className="grid gap-4 lg:grid-cols-2">
                                <Visual
                                    title="Records by agent"
                                    subtitle="Currently owned records added in the period"
                                >
                                    <BarList
                                        rows={data.contribution.map(
                                            (agent) => ({
                                                label: agent.name,
                                                value: agent.records,
                                                href: leadDrilldownUrl(
                                                    filters,
                                                    agent.name,
                                                    {
                                                        agent: String(agent.id),
                                                    },
                                                ),
                                            }),
                                        )}
                                    />
                                </Visual>
                                <Visual
                                    title="Possible leads by agent"
                                    subtitle="Current status is Possible lead"
                                >
                                    <BarList
                                        rows={data.contribution
                                            .map((agent) => ({
                                                label: agent.name,
                                                value:
                                                    possibleByAgent.get(
                                                        agent.id,
                                                    ) ?? 0,
                                                href: leadDrilldownUrl(
                                                    filters,
                                                    agent.name,
                                                    {
                                                        agent: String(agent.id),
                                                        status: 'possible_lead',
                                                    },
                                                ),
                                            }))
                                            .filter((row) => row.value > 0)
                                            .sort((a, b) => b.value - a.value)}
                                        empty="No possible leads in this period."
                                    />
                                </Visual>
                            </div>
                            <Visual
                                title="Agent contribution"
                                subtitle="Top 20 agents · upload quality follows the uploader · data quality rate = accepted rows with no recorded issue ÷ processed rows"
                            >
                                <ReportTable
                                    headings={[
                                        'Agent',
                                        'Records added',
                                        'Possible leads',
                                        'Unique companies',
                                        'Uploads',
                                        'Avg batch size',
                                        'Duplicate rate',
                                        'Rejection rate',
                                        'Error rate',
                                        'Data quality rate',
                                    ]}
                                    rows={data.contribution.map((agent) => [
                                        <Link
                                            key="agent"
                                            href={
                                                leadDrilldownUrl(
                                                    filters,
                                                    agent.name,
                                                    {
                                                        agent: String(agent.id),
                                                    },
                                                ) ?? '#'
                                            }
                                            className="hover:underline"
                                        >
                                            {agent.name}
                                        </Link>,
                                        <DataBar
                                            key="records"
                                            value={agent.records}
                                            max={Math.max(
                                                ...data.contribution.map(
                                                    (row) => row.records,
                                                ),
                                            )}
                                        />,
                                        (
                                            possibleByAgent.get(agent.id) ?? 0
                                        ).toLocaleString(),
                                        agent.companies.toLocaleString(),
                                        agent.uploads.toLocaleString(),
                                        agent.average_batch_size ?? 'N/A',
                                        percent(agent.duplicates_rate),
                                        percent(agent.rejected_rate),
                                        percent(agent.errors_rate),
                                        percent(agent.clean_rate),
                                    ])}
                                />
                            </Visual>
                        </>
                    )}
                </div>
            </div>
        </>
    );
}

Analytics.layout = {
    breadcrumbs: [{ title: 'Lead Reports', href: reportIndex() }],
};
