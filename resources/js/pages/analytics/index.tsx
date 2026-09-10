import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
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
import {
    changeLabel,
    ChartLegend,
    ChartTooltip,
    DistributionChart,
    formatLabel,
    GrowthChart,
    percent,
    summarize,
} from '@/components/database-charts';
import { Section } from '@/components/report-section';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
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
    summary: {
        replies: number;
        interested_replies: number;
        reply_rate: number;
    };
    replyClassifications: { label: string; value: number }[];
};
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
const selectClass =
    'h-9 rounded-md border border-input bg-background px-3 text-sm';
const regions = new Intl.DisplayNames(['en'], { type: 'region' });
function countryName(value: string) {
    return /^[A-Z]{2}$/.test(value)
        ? (regions.of(value) ?? value)
        : formatLabel(value);
}
function Metric({
    label,
    value,
    note,
}: {
    label: string;
    value: number | string | null;
    note?: string;
}) {
    return (
        <Card>
            <CardContent className="pt-5">
                <p className="text-xs text-muted-foreground">{label}</p>
                <p className="mt-2 text-2xl font-semibold tabular-nums">
                    {value === null
                        ? 'N/A'
                        : typeof value === 'number'
                          ? value.toLocaleString()
                          : value}
                </p>
                {note && (
                    <p className="mt-2 text-xs text-muted-foreground">{note}</p>
                )}
            </CardContent>
        </Card>
    );
}
function ReportTable({
    headings,
    rows,
}: {
    headings: string[];
    rows: ReactNode[][];
}) {
    return (
        <div className="overflow-x-auto rounded-xl border bg-card">
            <table className="w-full text-left text-sm">
                <thead className="bg-muted/50">
                    <tr>
                        {headings.map((h) => (
                            <th
                                className="px-4 py-3 whitespace-nowrap"
                                key={h}
                                scope="col"
                            >
                                {h}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y">
                    {rows.map((row, index) => (
                        <tr key={index}>
                            {row.map((cell, column) => (
                                <td
                                    className="px-4 py-3 tabular-nums"
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
                                className="p-6 text-center text-muted-foreground"
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

export default function Analytics({
    period,
    filters,
    databaseReport: data,
    uploadTimingHeatmap,
    summary,
    replyClassifications,
}: Props) {
    const { auth, errors } = usePage<{
        auth: Auth;
        errors: Record<string, string>;
    }>().props;
    const [selectedPeriod, setSelectedPeriod] = useState(period);
    const [granularity, setGranularity] = useState(data.growth.granularity);
    const [mode, setMode] = useState<'count' | 'rate'>('count');
    const [processing, setProcessing] = useState(false);
    const selected = `${filters.date_from} – ${filters.date_to}`;
    const quality = data.quality;
    const appliedQuery = {
        period,
        ...filters,
        granularity: data.growth.granularity,
        ...data.geographic_detail.filters,
    };
    const qualitySeries = [
        'duplicates',
        'rejected',
        'errors',
        'location_issues',
    ] as const;
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
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
            },
        );
    }
    function drill(country = '', province = '', city = '') {
        router.get(
            reportIndex.url(),
            {
                ...appliedQuery,
                geo_country: country,
                geo_province: province,
                geo_city: city,
            },
            { preserveScroll: true },
        );
    }
    const geo = data.geographic_detail;

    return (
        <>
            <Head title="Database reports" />
            <div className="flex min-w-0 flex-1 flex-col gap-8 p-4 md:p-6">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Database intelligence reports
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Representation, quality, growth and contribution ·{' '}
                            {auth.user.role === 'agent'
                                ? 'Your data only'
                                : 'All-owner data'}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <a
                                href={reportExport.url({
                                    query: appliedQuery,
                                })}
                            >
                                Export CSV
                            </a>
                        </Button>
                        <Button variant="outline" asChild>
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
                <Section
                    title="Reporting period"
                    note={`All panels follow ${selected}, except values explicitly labeled All time. Reporting timezone: ${data.timezone}.`}
                >
                    <form
                        onSubmit={applyPeriod}
                        className="flex flex-wrap items-end gap-3 rounded-xl border bg-card p-4"
                        aria-busy={processing}
                    >
                        <label className="flex flex-col gap-1.5 text-xs">
                            Period
                            <select
                                className={selectClass}
                                value={selectedPeriod}
                                onChange={(e) =>
                                    setSelectedPeriod(e.target.value)
                                }
                            >
                                {Object.entries(periods)
                                    .filter(
                                        ([key]) =>
                                            !['7_days', '90_days'].includes(
                                                key,
                                            ) || period === key,
                                    )
                                    .map(([value, label]) => (
                                        <option key={value} value={value}>
                                            {label}
                                        </option>
                                    ))}
                            </select>
                        </label>
                        <label className="flex flex-col gap-1.5 text-xs">
                            Group growth and quality by
                            <select
                                className={selectClass}
                                value={granularity}
                                onChange={(e) =>
                                    setGranularity(
                                        e.target.value as typeof granularity,
                                    )
                                }
                            >
                                <option value="day">Daily</option>
                                <option value="week">Weekly</option>
                                <option value="month">Monthly</option>
                            </select>
                        </label>
                        {selectedPeriod === 'custom' && (
                            <>
                                <label className="flex flex-col gap-1.5 text-xs">
                                    From
                                    <Input
                                        name="date_from"
                                        type="date"
                                        defaultValue={filters.date_from}
                                        required
                                    />
                                </label>
                                <label className="flex flex-col gap-1.5 text-xs">
                                    Through
                                    <Input
                                        name="date_to"
                                        type="date"
                                        defaultValue={filters.date_to}
                                        required
                                    />
                                </label>
                            </>
                        )}
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Applying…' : 'Apply'}
                        </Button>
                        {Object.entries(errors).map(([key, message]) => (
                            <p
                                role="alert"
                                className="w-full text-sm text-destructive"
                                key={key}
                            >
                                {message}
                            </p>
                        ))}
                    </form>
                </Section>

                <div className="relative overflow-hidden rounded-xl border border-primary/20 bg-primary/5 p-4">
                    <div className="absolute inset-y-0 left-0 w-1 bg-primary" />
                    <p className="pl-3 text-sm leading-relaxed font-medium text-foreground">
                        {summarize(data)}
                    </p>
                </div>

                <Section
                    title="Database summary"
                    note={`Selected period · ${selected}. Counts exclude soft-deleted leads. Unique emails are addresses, not verified individual people.`}
                >
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <Metric
                            label="Records added · selected period"
                            value={data.overview.records}
                            note={`${data.all_time.records.toLocaleString()} total records · All time`}
                        />
                        <Metric
                            label="Unique companies · selected period"
                            value={data.overview.companies}
                            note={`${data.all_time.companies.toLocaleString()} · All time`}
                        />
                        <Metric
                            label="Unique emails · selected period"
                            value={data.overview.emails}
                            note={`${data.all_time.emails.toLocaleString()} · All time`}
                        />
                        <Metric
                            label="Duplicates detected · selected period"
                            value={quality.duplicates}
                            note="Exact and possible duplicate import rows"
                        />
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <Metric
                            label="Countries represented"
                            value={data.overview.countries}
                        />
                        <Metric
                            label="Raw data sources represented"
                            value={data.overview.sources}
                        />
                        <Metric
                            label="Upload batches"
                            value={data.overview.uploads}
                        />
                        <Metric
                            label="Import rows with issues"
                            value={quality.issues}
                            note="Each affected row counted once"
                        />
                    </div>
                </Section>
                <Section
                    title="Database growth"
                    note={`Selected period compared with ${data.previous_period.from} – ${data.previous_period.to}. Company and email growth uses first-seen identities across surviving, scoped records.`}
                >
                    <div className="grid gap-3 sm:grid-cols-3">
                        {(['records', 'companies', 'emails'] as const).map(
                            (key) => (
                                <Metric
                                    key={key}
                                    label={`${key === 'records' ? 'Contact records' : `First-seen ${key}`} added`}
                                    value={data.growth.totals[key]}
                                    note={changeLabel(
                                        data.growth.totals[`${key}_change`],
                                    )}
                                />
                            ),
                        )}
                    </div>
                    <Card>
                        <CardContent className="pt-6">
                            <GrowthChart growth={data.growth} />
                        </CardContent>
                    </Card>
                </Section>
                <Section
                    title="Database distribution"
                    note={`Share of period-created records · ${selected}. Classifications describe current status; they do not imply sequential conversion.`}
                >
                    <div className="grid items-start gap-4 lg:grid-cols-3">
                        <DistributionChart
                            title="Country distribution"
                            rows={data.distributions.countries.map((row) => ({
                                ...row,
                                label: countryName(row.label),
                            }))}
                            description="Country codes take priority; name-only historical values may remain separate."
                        />
                        <DistributionChart
                            title="Data sources"
                            rows={data.distributions.sources}
                            description="Conservative analytical grouping; original source values are preserved."
                        />
                        <DistributionChart
                            title="Database classification distribution"
                            rows={data.distributions.statuses}
                        />
                    </div>
                    {data.show_industries && (
                        <DistributionChart
                            title="Industry distribution"
                            rows={data.distributions.industries}
                            description={`${percent(data.industry_coverage)} of period records have a known industry. Shown when coverage reaches 20%.`}
                        />
                    )}
                </Section>
                <Section
                    title="Company and contact analysis"
                    note="Selected period · normalized company names group contact records. Repeated company names alone do not establish duplication."
                >
                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                        <Metric
                            label="Unique companies"
                            value={data.overview.companies}
                        />
                        <Metric
                            label="Unique contact emails"
                            value={data.overview.emails}
                        />
                        <Metric
                            label="Average contact records per company"
                            value={data.company_analysis.average_contacts}
                        />
                        <Metric
                            label="Companies with one contact record"
                            value={data.company_analysis.single_contact}
                        />
                        <Metric
                            label="Companies with multiple contact records"
                            value={data.company_analysis.multiple_contacts}
                        />
                    </div>
                    <DistributionChart
                        title="Top companies by contact records"
                        rows={data.companies.map((company) => ({
                            label: company.label,
                            value: company.contacts,
                            percent:
                                data.overview.records > 0
                                    ? Math.round(
                                          (1000 * company.contacts) /
                                              data.overview.records,
                                      ) / 10
                                    : null,
                        }))}
                        description={`Top 15 · percentage of all period-created records. ${data.company_analysis.unnamed_records.toLocaleString()} unnamed records excluded from company averages.`}
                    />
                </Section>
                <Section
                    title="Data quality"
                    note="Rows from uploads created in the selected period. Accepted includes Needs Review; possible duplicates can overlap review rows. Rates use processed rows and N/A means no denominator."
                >
                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        {(
                            [
                                'accepted',
                                'needs_review',
                                'duplicates',
                                'rejected',
                                'errors',
                                'location_issues',
                            ] as const
                        ).map((key) => (
                            <Metric
                                key={key}
                                label={formatLabel(key)}
                                value={quality[key]}
                                note={
                                    key === 'needs_review'
                                        ? 'Included in accepted rows'
                                        : percent(quality[`${key}_rate`])
                                }
                            />
                        ))}
                    </div>
                    <Card>
                        <CardHeader className="flex-row flex-wrap items-center justify-between gap-3">
                            <CardTitle>Data quality trend</CardTitle>
                            <label className="flex items-center gap-2 text-xs">
                                Display
                                <select
                                    className={selectClass}
                                    value={mode}
                                    onChange={(e) =>
                                        setMode(e.target.value as typeof mode)
                                    }
                                >
                                    <option value="count">Count</option>
                                    <option value="rate">Rate</option>
                                </select>
                            </label>
                        </CardHeader>
                        <CardContent>
                            <p className="mb-4 text-xs text-muted-foreground">
                                Grouped by upload creation date. Location issues
                                reflect recorded location flags; other review
                                flags can mask additional location problems.
                            </p>
                            <div
                                className="h-64 min-w-0"
                                role="img"
                                aria-label={`Data quality trend by ${mode}, one bar group per period; exact values in table below`}
                            >
                                <ResponsiveContainer width="100%" height="100%">
                                    <BarChart
                                        data={data.quality_trend}
                                        barGap={2}
                                        barCategoryGap="20%"
                                        accessibilityLayer
                                    >
                                        <CartesianGrid
                                            vertical={false}
                                            stroke="var(--color-border)"
                                        />
                                        <XAxis
                                            dataKey="date"
                                            tick={{ fontSize: 11 }}
                                            minTickGap={40}
                                        />
                                        <YAxis
                                            unit={mode === 'rate' ? '%' : ''}
                                            allowDecimals={mode === 'rate'}
                                            tick={{ fontSize: 11 }}
                                        />
                                        <Tooltip
                                            content={ChartTooltip}
                                            cursor={{
                                                fill: 'var(--color-muted)',
                                                opacity: 0.4,
                                            }}
                                        />
                                        {qualitySeries.map((key, index) => (
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
                                                isAnimationActive={false}
                                            />
                                        ))}
                                    </BarChart>
                                </ResponsiveContainer>
                            </div>
                            <ChartLegend
                                items={qualitySeries.map((key, index) => ({
                                    key,
                                    label: formatLabel(key),
                                    color: `var(--color-chart-${index + 1})`,
                                }))}
                            />
                            <details className="mt-4">
                                <summary className="cursor-pointer text-sm text-primary">
                                    View quality data
                                </summary>
                                <ReportTable
                                    headings={[
                                        'Period start',
                                        'Processed',
                                        ...qualitySeries.map(formatLabel),
                                    ]}
                                    rows={data.quality_trend.map((point) => [
                                        point.date,
                                        point.processed,
                                        ...qualitySeries.map((key) =>
                                            mode === 'rate'
                                                ? percent(point[`${key}_rate`])
                                                : point[key],
                                        ),
                                    ])}
                                />
                            </details>
                        </CardContent>
                    </Card>
                </Section>
                <Section
                    title="Upload quality analysis"
                    note="Selected-period uploads · submitted rows use batch totals; observed and processed counts use retained import rows. Unstarted uploads may not yet have row outcomes."
                    collapsible
                    defaultOpen={false}
                >
                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <Metric
                            label="Total uploads"
                            value={data.overview.uploads}
                        />
                        <Metric
                            label="Rows submitted · batch totals"
                            value={quality.submitted_rows}
                        />
                        <Metric
                            label="Observed import rows"
                            value={quality.observed_rows}
                        />
                        <Metric
                            label="Average batch size"
                            value={quality.average_batch_size}
                        />
                    </div>
                    <ReportTable
                        headings={[
                            'Processed',
                            'Pending',
                            'Accepted',
                            'Duplicates',
                            'Rejected',
                            'Errors',
                            'Acceptance rate',
                            'Duplicate rate',
                            'Rejection rate',
                            'Error rate',
                        ]}
                        rows={[
                            [
                                quality.processed,
                                quality.observed_rows - quality.processed,
                                quality.accepted,
                                quality.duplicates,
                                quality.rejected,
                                quality.errors,
                                percent(quality.accepted_rate),
                                percent(quality.duplicates_rate),
                                percent(quality.rejected_rate),
                                percent(quality.errors_rate),
                            ],
                        ]}
                    />
                </Section>
                <Section
                    title="Source quality analysis"
                    note="Selected period · Total records uses current lead sources. Import outcomes use saved source snapshots, including updates to existing leads. These are distinct populations; accepted rows are not necessarily new records. Missing snapshots are Unknown; manual records have no import-quality rate."
                    collapsible
                    defaultOpen={false}
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
                            source.label,
                            source.records,
                            source.observed_rows,
                            source.processed,
                            source.accepted,
                            percent(source.duplicates_rate),
                            percent(source.rejected_rate),
                            percent(source.errors_rate),
                        ])}
                    />
                </Section>
                <Section
                    title="Geographic analysis"
                    note="Selected period · click a country, then a state/province, then a city to narrow the location combinations. Historical City values are shown as stored and may contain province names."
                    collapsible
                    defaultOpen={false}
                >
                    <div className="flex flex-wrap items-center gap-2 text-sm">
                        <button
                            className="text-primary hover:underline"
                            onClick={() => drill()}
                        >
                            All locations
                        </button>
                        {geo.filters.geo_country && (
                            <>
                                <span>→</span>
                                <button
                                    className="text-primary hover:underline"
                                    onClick={() =>
                                        drill(geo.filters.geo_country)
                                    }
                                >
                                    {countryName(geo.filters.geo_country)}
                                </button>
                            </>
                        )}
                        {geo.filters.geo_province && (
                            <>
                                <span>→</span>
                                <button
                                    className="text-primary hover:underline"
                                    onClick={() =>
                                        drill(
                                            geo.filters.geo_country,
                                            geo.filters.geo_province,
                                        )
                                    }
                                >
                                    {geo.filters.geo_province}
                                </button>
                            </>
                        )}
                        {geo.filters.geo_city && (
                            <>
                                <span>→</span>
                                <span>{geo.filters.geo_city}</span>
                            </>
                        )}
                    </div>
                    <p className="text-xs text-muted-foreground">
                        Top 50 combinations · {geo.records.toLocaleString()}{' '}
                        matching records.{' '}
                        {data.geography.unverified_city_records.toLocaleString()}{' '}
                        period records have an unverified city label. Drill-down
                        filters affect this section only.
                    </p>
                    <ReportTable
                        headings={[
                            'Country',
                            'State / province',
                            'City as stored',
                            'Timezone',
                            'Records',
                        ]}
                        rows={geo.rows.map((row) => [
                            <button
                                className="text-primary hover:underline"
                                onClick={() => drill(row.country)}
                            >
                                {countryName(row.country)}
                            </button>,
                            <button
                                className="text-primary hover:underline"
                                onClick={() => drill(row.country, row.province)}
                            >
                                {row.province}
                            </button>,
                            <button
                                className="text-primary hover:underline"
                                onClick={() =>
                                    drill(row.country, row.province, row.city)
                                }
                            >
                                {row.city}
                            </button>,
                            row.timezone,
                            row.records,
                        ])}
                    />
                </Section>
                {data.can_compare_agents && (
                    <Section
                        title="Database contribution by agent"
                        note="Selected period · top 20 agents by currently owned records, including historical owners. Upload quality follows the uploader. Data quality rate = accepted rows with no recorded issue ÷ processed rows; it does not verify deliverability."
                        collapsible
                        defaultOpen={false}
                    >
                        <ReportTable
                            headings={[
                                'Agent',
                                'Records added',
                                'Unique companies',
                                'Uploads',
                                'Average batch size',
                                'Duplicate rate',
                                'Rejection rate',
                                'Error rate',
                                'Data quality rate',
                            ]}
                            rows={data.contribution.map((agent) => [
                                agent.name,
                                agent.records,
                                agent.companies,
                                agent.uploads,
                                agent.average_batch_size ?? 'N/A',
                                percent(agent.duplicates_rate),
                                percent(agent.rejected_rate),
                                percent(agent.errors_rate),
                                percent(agent.clean_rate),
                            ])}
                        />
                    </Section>
                )}
                {uploadTimingHeatmap.length > 0 && (
                    <Section
                        title="Upload timing"
                        note={`Selected period · uploads by weekday and hour in ${data.timezone}. Each cell shows the number of batches.`}
                        collapsible
                        defaultOpen={false}
                    >
                        <div className="overflow-x-auto rounded-xl border bg-card p-4">
                            <table className="w-full text-center text-xs">
                                <thead>
                                    <tr>
                                        <th scope="col">Day / hour</th>
                                        {Array.from(
                                            { length: 24 },
                                            (_, hour) => (
                                                <th
                                                    key={hour}
                                                    className="min-w-8 p-1"
                                                    scope="col"
                                                >
                                                    {hour}
                                                </th>
                                            ),
                                        )}
                                    </tr>
                                </thead>
                                <tbody>
                                    {uploadTimingHeatmap.map((row) => (
                                        <tr key={row.day}>
                                            <th scope="row" className="p-2">
                                                {row.day}
                                            </th>
                                            {row.hours.map((count, hour) => (
                                                <td
                                                    key={hour}
                                                    title={`${row.day} ${hour}:00 · ${count} uploads`}
                                                    className={`border-2 border-card p-1 tabular-nums ${count > 0 ? 'bg-primary/20 font-medium text-foreground' : 'bg-muted/40 text-muted-foreground'}`}
                                                >
                                                    {count || '·'}
                                                </td>
                                            ))}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Section>
                )}
                {auth.user.role === 'super_administrator' && (
                    <Section
                        title="Reply activity"
                        note={`Existing restricted reply analytics · ${selected}.`}
                        collapsible
                        defaultOpen={false}
                    >
                        <div className="grid gap-3 sm:grid-cols-3">
                            <Metric label="Replies" value={summary.replies} />
                            <Metric
                                label="Interested replies"
                                value={summary.interested_replies}
                            />
                            <Metric
                                label="Reply rate"
                                value={percent(summary.reply_rate)}
                            />
                        </div>
                        <DistributionChart
                            title="Reply classifications"
                            rows={replyClassifications.map((row) => ({
                                ...row,
                                percent:
                                    summary.replies > 0
                                        ? Math.round(
                                              (1000 * row.value) /
                                                  summary.replies,
                                          ) / 10
                                        : null,
                            }))}
                        />
                    </Section>
                )}
            </div>
        </>
    );
}
Analytics.layout = {
    breadcrumbs: [{ title: 'Lead Reports', href: reportIndex() }],
};
