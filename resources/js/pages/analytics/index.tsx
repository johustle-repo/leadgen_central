import { Head, router } from '@inertiajs/react';
import {
    BarChart3,
    Download,
    MailCheck,
    ShieldAlert,
    SlidersHorizontal,
    Target,
    TrendingDown,
    TrendingUp,
    UsersRound,
} from 'lucide-react';
import {
    CartesianGrid,
    Cell,
    Funnel,
    FunnelChart,
    LabelList,
    Line,
    LineChart,
    ResponsiveContainer,
    Tooltip as RechartsTooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { EmptyState } from '@/components/empty-state';
import { FilterBar } from '@/components/filter-bar';
import { PageHeader } from '@/components/page-header';
import { StatTile } from '@/components/stat-tile';
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
import {
    Tooltip as UiTooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    exportMethod as analyticsExport,
    index as analyticsIndex,
} from '@/routes/analytics';

type Distribution = { label: string; value: number };
type DailyActivity = {
    date: string;
    label: string;
    leads: number;
    replies: number;
};
type Summary = {
    total_leads: number;
    qualified_leads: number;
    qualification_rate: number;
    replies: number;
    replied_leads: number;
    reply_rate: number;
    interested_replies: number;
    duplicates: number;
    lead_change: number;
    reply_change: number;
};
type AgentPerformance = {
    id: number;
    name: string;
    leads: number;
    qualified: number;
    replies: number;
    interested: number;
    qualification_rate: number;
    uploads: number;
    avg_batch_size: number;
    duplicate_rate: number;
    error_rate: number;
};
type FunnelStage = {
    stage: string;
    count: number;
    percent_of_total: number;
    conversion_from_previous: number;
};
type QualityTrendPoint = {
    date: string;
    label: string;
    duplicate_rate: number;
    error_rate: number;
    location_error_rate: number;
};
type HeatmapRow = { day: string; hours: number[] };
type Props = {
    period: string;
    filters: { date_from: string; date_to: string };
    summary: Summary;
    dailyActivity: DailyActivity[];
    leadStatuses: Distribution[];
    sources: Distribution[];
    countries: Distribution[];
    replyClassifications: Distribution[];
    agentPerformance: AgentPerformance[];
    funnel: FunnelStage[];
    funnelExcluded: Distribution[];
    dataQualityTrend: QualityTrendPoint[];
    uploadTimingHeatmap: HeatmapRow[];
    industries: Distribution[];
};

const prettyLabel = (value: string) =>
    value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());

// Ordinal ramp (single hue, light -> dark) for the funnel's ordered stages.
// --funnel-1..5 in app.css already swap per theme, same as --color-chart-*.
const FUNNEL_COLORS = [
    'var(--color-funnel-1)',
    'var(--color-funnel-2)',
    'var(--color-funnel-3)',
    'var(--color-funnel-4)',
    'var(--color-funnel-5)',
];

function Change({ value }: { value: number }) {
    const positive = value >= 0;
    const Icon = positive ? TrendingUp : TrendingDown;

    return (
        <span
            className={`inline-flex items-center gap-1 text-xs font-semibold ${positive ? 'text-emerald-500' : 'text-red-500'}`}
        >
            <Icon className="size-3.5" />
            {Math.abs(value)}% vs previous period
        </span>
    );
}

function ChartTooltip({
    active,
    payload,
    label,
    formatter,
}: {
    active?: boolean;
    payload?: { name: string; value: number; color: string }[];
    label?: string;
    formatter?: (name: string, value: number) => string;
}) {
    if (!active || !payload?.length) {
        return null;
    }

    return (
        <div className="rounded-md border bg-card p-2.5 text-xs shadow-md">
            <p className="mb-1.5 font-medium">{label}</p>
            <div className="flex flex-col gap-1">
                {payload.map((entry) => (
                    <div key={entry.name} className="flex items-center gap-2">
                        <i
                            className="size-2 shrink-0 rounded-full"
                            style={{ backgroundColor: entry.color }}
                        />
                        <span className="text-muted-foreground">
                            {entry.name}
                        </span>
                        <span className="ml-auto font-medium tabular-nums">
                            {formatter
                                ? formatter(entry.name, entry.value)
                                : entry.value}
                        </span>
                    </div>
                ))}
            </div>
        </div>
    );
}

function Breakdown({
    title,
    items,
    color,
}: {
    title: string;
    items: Distribution[];
    color: string;
}) {
    const maximum = Math.max(...items.map((item) => item.value), 1);
    const total = items.reduce((sum, item) => sum + item.value, 0);

    return (
        <Card>
            <CardHeader className="flex-row items-center justify-between">
                <CardTitle>{title}</CardTitle>
                <span className="text-xs text-muted-foreground">
                    {total.toLocaleString()} total
                </span>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                {items.length ? (
                    items.map((item) => (
                        <div key={item.label} className="flex flex-col gap-1.5">
                            <div className="flex items-center justify-between gap-3 text-sm">
                                <span className="truncate font-medium">
                                    {prettyLabel(item.label)}
                                </span>
                                <span className="text-muted-foreground tabular-nums">
                                    {item.value.toLocaleString()}
                                </span>
                            </div>
                            <div className="h-2 overflow-hidden rounded-full bg-muted">
                                <div
                                    className="h-full rounded-full"
                                    style={{
                                        width: `${Math.max((item.value / maximum) * 100, 3)}%`,
                                        backgroundColor: color,
                                    }}
                                />
                            </div>
                        </div>
                    ))
                ) : (
                    <EmptyState
                        icon={BarChart3}
                        title="No data for this period"
                    />
                )}
            </CardContent>
        </Card>
    );
}

function LeadFunnel({
    stages,
    excluded,
}: {
    stages: FunnelStage[];
    excluded: Distribution[];
}) {
    const data = stages.map((stage, index) => ({
        name: stage.stage,
        value: stage.count,
        label: `${stage.stage} (${stage.count.toLocaleString()})`,
        percent: stage.percent_of_total,
        conversion: stage.conversion_from_previous,
        fill: FUNNEL_COLORS[index] ?? FUNNEL_COLORS.at(-1),
    }));
    const excludedTotal = excluded.reduce((sum, item) => sum + item.value, 0);

    return (
        <Card>
            <CardHeader>
                <CardTitle>Lead lifecycle funnel</CardTitle>
                <p className="text-sm text-muted-foreground">
                    Leads created in the period, by furthest stage reached.
                    Snapshot of current status, not a step-by-step timeline.
                </p>
            </CardHeader>
            <CardContent>
                {data.length ? (
                    <>
                        <div className="h-72">
                            <ResponsiveContainer width="100%" height="100%">
                                <FunnelChart>
                                    <RechartsTooltip
                                        content={({ active, payload }) => {
                                            if (!active || !payload?.length) {
                                                return null;
                                            }

                                            const item = payload[0]
                                                .payload as (typeof data)[number];

                                            return (
                                                <div className="rounded-md border bg-card p-2.5 text-xs shadow-md">
                                                    <p className="font-medium">
                                                        {item.name}
                                                    </p>
                                                    <p className="text-muted-foreground">
                                                        {item.value.toLocaleString()}{' '}
                                                        leads ({item.percent}%
                                                        of total)
                                                    </p>
                                                </div>
                                            );
                                        }}
                                    />
                                    <Funnel
                                        dataKey="value"
                                        data={data}
                                        isAnimationActive={false}
                                    >
                                        {data.map((entry) => (
                                            <Cell
                                                key={entry.name}
                                                fill={entry.fill}
                                            />
                                        ))}
                                        <LabelList
                                            dataKey="label"
                                            position="right"
                                            fill="var(--color-foreground)"
                                            stroke="none"
                                            fontSize={12}
                                        />
                                    </Funnel>
                                </FunnelChart>
                            </ResponsiveContainer>
                        </div>
                        <div className="mt-2 grid grid-cols-2 gap-3 border-t pt-4 sm:grid-cols-4">
                            {stages.slice(1).map((stage) => (
                                <div key={stage.stage} className="text-xs">
                                    <p className="text-muted-foreground">
                                        {stage.stage}
                                    </p>
                                    <p className="font-semibold tabular-nums">
                                        {stage.conversion_from_previous}%{' '}
                                        <span className="font-normal text-muted-foreground">
                                            conversion
                                        </span>
                                    </p>
                                </div>
                            ))}
                        </div>
                        {excludedTotal > 0 && (
                            <p className="mt-4 text-xs text-muted-foreground">
                                {excludedTotal.toLocaleString()} leads left the
                                funnel this period (
                                {excluded
                                    .map(
                                        (item) =>
                                            `${prettyLabel(item.label)}: ${item.value}`,
                                    )
                                    .join(', ')}
                                ).
                            </p>
                        )}
                    </>
                ) : (
                    <EmptyState
                        icon={BarChart3}
                        title="No data for this period"
                    />
                )}
            </CardContent>
        </Card>
    );
}

function UploadTimingHeatmap({ data }: { data: HeatmapRow[] }) {
    const maximum = Math.max(...data.flatMap((row) => row.hours), 1);
    const bucket = (value: number) => {
        if (value === 0) {
            return 'var(--color-heat-0)';
        }

        const ratio = value / maximum;

        if (ratio > 0.75) {
            return 'var(--color-heat-4)';
        }

        if (ratio > 0.5) {
            return 'var(--color-heat-3)';
        }

        if (ratio > 0.25) {
            return 'var(--color-heat-2)';
        }

        return 'var(--color-heat-1)';
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>Upload timing</CardTitle>
                <p className="text-sm text-muted-foreground">
                    When agents submit upload batches, by day and hour (server
                    time).
                </p>
            </CardHeader>
            <CardContent className="overflow-x-auto">
                <div className="min-w-2xl">
                    <div
                        className="grid gap-1"
                        style={{
                            gridTemplateColumns:
                                '3rem repeat(24, minmax(0, 1fr))',
                        }}
                    >
                        <span />
                        {Array.from({ length: 24 }, (_, hour) => (
                            <span
                                key={hour}
                                className="text-center text-[10px] text-muted-foreground"
                            >
                                {hour % 3 === 0 ? hour : ''}
                            </span>
                        ))}
                        {data.map((row) => (
                            <div key={row.day} className="contents">
                                <span className="text-xs text-muted-foreground">
                                    {row.day}
                                </span>
                                {row.hours.map((value, hour) => (
                                    <UiTooltip key={hour}>
                                        <TooltipTrigger asChild>
                                            <div
                                                className="aspect-square rounded-sm"
                                                style={{
                                                    backgroundColor:
                                                        bucket(value),
                                                }}
                                            />
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            {row.day} {hour}:00 &mdash; {value}{' '}
                                            upload
                                            {value === 1 ? '' : 's'}
                                        </TooltipContent>
                                    </UiTooltip>
                                ))}
                            </div>
                        ))}
                    </div>
                    <div className="mt-4 flex items-center gap-1.5 text-xs text-muted-foreground">
                        Fewer
                        {[
                            'var(--color-heat-0)',
                            'var(--color-heat-1)',
                            'var(--color-heat-2)',
                            'var(--color-heat-3)',
                            'var(--color-heat-4)',
                        ].map((color) => (
                            <span
                                key={color}
                                className="size-3 rounded-sm border"
                                style={{ backgroundColor: color }}
                            />
                        ))}
                        More
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

export default function Analytics({
    period,
    filters,
    summary,
    dailyActivity,
    leadStatuses,
    sources,
    countries,
    replyClassifications,
    agentPerformance,
    funnel,
    funnelExcluded,
    dataQualityTrend,
    uploadTimingHeatmap,
    industries,
}: Props) {
    const applyFilters = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get(
            analyticsIndex.url(),
            Object.fromEntries(new FormData(event.currentTarget)),
            { preserveState: true, replace: true },
        );
    };
    const metrics = [
        {
            label: 'Leads created',
            value: summary.total_leads,
            detail: <Change value={summary.lead_change} />,
            icon: UsersRound,
            tone: 'text-chart-1',
        },
        {
            label: 'Qualified leads',
            value: summary.qualified_leads,
            detail: `${summary.qualification_rate}% qualification rate`,
            icon: Target,
            tone: 'text-chart-3',
        },
        {
            label: 'Email replies',
            value: summary.replies,
            detail: <Change value={summary.reply_change} />,
            icon: MailCheck,
            tone: 'text-chart-2',
        },
        {
            label: 'Reply rate',
            value: `${summary.reply_rate}%`,
            detail: `${summary.replied_leads} unique leads replied`,
            icon: BarChart3,
            tone: 'text-chart-1',
        },
        {
            label: 'Interested replies',
            value: summary.interested_replies,
            detail: 'Interested or possible lead',
            icon: TrendingUp,
            tone: 'text-chart-3',
        },
        {
            label: 'Duplicates flagged',
            value: summary.duplicates,
            detail: 'Detected during uploads',
            icon: ShieldAlert,
            tone: 'text-chart-4',
        },
    ];
    const isAdmin = agentPerformance.length > 0 || funnel.length > 0;
    const periodHint =
        period === 'custom'
            ? 'Showing the custom date range below.'
            : `Showing the last ${period.replace('_days', '')} days.`;

    return (
        <>
            <Head title="Reports" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Reports"
                    description="Measure lead quality, reply outcomes, and team performance over time."
                    actions={
                        <Button
                            asChild
                            variant="outline"
                            className="border-sky-500/30 bg-sky-500/10 text-sky-700 hover:bg-sky-500/15 hover:text-sky-800 dark:text-sky-300 dark:hover:text-sky-200"
                        >
                            <a
                                href={analyticsExport.url({
                                    query: {
                                        period,
                                        date_from: filters.date_from,
                                        date_to: filters.date_to,
                                    },
                                })}
                                download
                            >
                                <Download />
                                Export reports
                            </a>
                        </Button>
                    }
                />

                <FilterBar
                    as="form"
                    onSubmit={applyFilters}
                    icon={SlidersHorizontal}
                    label="Reporting period"
                    gridClassName="md:grid-cols-[minmax(180px,0.8fr)_1fr_1fr_auto]"
                    hint={periodHint}
                >
                    <div className="flex flex-col gap-1.5">
                        <label
                            htmlFor="analytics-period"
                            className="text-xs text-muted-foreground"
                        >
                            Period
                        </label>
                        <Select name="period" defaultValue={period}>
                            <SelectTrigger
                                id="analytics-period"
                                className="w-full"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="7_days">
                                    Last 7 days
                                </SelectItem>
                                <SelectItem value="30_days">
                                    Last 30 days
                                </SelectItem>
                                <SelectItem value="90_days">
                                    Last 90 days
                                </SelectItem>
                                <SelectItem value="custom">
                                    Custom range
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <label
                            htmlFor="analytics-date-from"
                            className="text-xs text-muted-foreground"
                        >
                            From
                        </label>
                        <Input
                            id="analytics-date-from"
                            type="date"
                            name="date_from"
                            defaultValue={filters.date_from}
                        />
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <label
                            htmlFor="analytics-date-to"
                            className="text-xs text-muted-foreground"
                        >
                            To
                        </label>
                        <Input
                            id="analytics-date-to"
                            type="date"
                            name="date_to"
                            defaultValue={filters.date_to}
                        />
                    </div>
                    <div className="flex flex-col justify-end">
                        <Button type="submit" variant="secondary">
                            Update analytics
                        </Button>
                    </div>
                </FilterBar>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {metrics.map((metric) => (
                        <StatTile
                            key={metric.label}
                            label={metric.label}
                            value={metric.value}
                            detail={metric.detail}
                            icon={metric.icon}
                            tone={metric.tone}
                        />
                    ))}
                </div>

                <Card>
                    <CardHeader className="flex-row items-center justify-between">
                        <div className="flex flex-col gap-1">
                            <CardTitle>Lead and reply activity</CardTitle>
                            <p className="text-sm text-muted-foreground">
                                Daily volume for the selected period
                            </p>
                        </div>
                        <div className="flex gap-4 text-xs text-muted-foreground">
                            <span className="flex items-center gap-1.5">
                                <i
                                    className="size-2 rounded-full"
                                    style={{
                                        backgroundColor: 'var(--color-chart-1)',
                                    }}
                                />
                                Leads
                            </span>
                            <span className="flex items-center gap-1.5">
                                <i
                                    className="size-2 rounded-full"
                                    style={{
                                        backgroundColor: 'var(--color-chart-2)',
                                    }}
                                />
                                Replies
                            </span>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="h-64 min-w-0">
                            <ResponsiveContainer width="100%" height="100%">
                                <LineChart data={dailyActivity}>
                                    <CartesianGrid
                                        vertical={false}
                                        stroke="var(--color-border)"
                                        strokeOpacity={0.6}
                                    />
                                    <XAxis
                                        dataKey="label"
                                        tick={{ fontSize: 11 }}
                                        tickLine={false}
                                        axisLine={false}
                                        interval="preserveStartEnd"
                                        stroke="var(--color-muted-foreground)"
                                    />
                                    <YAxis
                                        tick={{ fontSize: 11 }}
                                        tickLine={false}
                                        axisLine={false}
                                        width={32}
                                        allowDecimals={false}
                                        stroke="var(--color-muted-foreground)"
                                    />
                                    <RechartsTooltip
                                        content={<ChartTooltip />}
                                    />
                                    <Line
                                        type="monotone"
                                        dataKey="leads"
                                        name="Leads"
                                        stroke="var(--color-chart-1)"
                                        strokeWidth={2}
                                        dot={false}
                                        activeDot={{ r: 4 }}
                                    />
                                    <Line
                                        type="monotone"
                                        dataKey="replies"
                                        name="Replies"
                                        stroke="var(--color-chart-2)"
                                        strokeWidth={2}
                                        dot={false}
                                        activeDot={{ r: 4 }}
                                    />
                                </LineChart>
                            </ResponsiveContainer>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid gap-6 xl:grid-cols-2">
                    <Breakdown
                        title="Lead status"
                        items={leadStatuses}
                        color="var(--color-chart-3)"
                    />
                    <Breakdown
                        title="Reply classification"
                        items={replyClassifications}
                        color="var(--color-chart-5)"
                    />
                    <Breakdown
                        title="Lead sources"
                        items={sources}
                        color="var(--color-chart-1)"
                    />
                    <Breakdown
                        title="Top countries"
                        items={countries}
                        color="var(--color-chart-2)"
                    />
                </div>

                {isAdmin && (
                    <>
                        <div className="grid gap-6 xl:grid-cols-2">
                            <LeadFunnel
                                stages={funnel}
                                excluded={funnelExcluded}
                            />
                            <Breakdown
                                title="Industries"
                                items={industries}
                                color="var(--color-chart-4)"
                            />
                        </div>

                        <Card>
                            <CardHeader className="flex-row items-center justify-between">
                                <div className="flex flex-col gap-1">
                                    <CardTitle>Data quality trend</CardTitle>
                                    <p className="text-sm text-muted-foreground">
                                        Share of uploaded rows flagged as
                                        duplicate, rejected, or location errors
                                    </p>
                                </div>
                                <div className="flex flex-wrap gap-4 text-xs text-muted-foreground">
                                    <span className="flex items-center gap-1.5">
                                        <i
                                            className="size-2 rounded-full"
                                            style={{
                                                backgroundColor:
                                                    'var(--color-chart-1)',
                                            }}
                                        />
                                        Duplicate rate
                                    </span>
                                    <span className="flex items-center gap-1.5">
                                        <i
                                            className="size-2 rounded-full"
                                            style={{
                                                backgroundColor:
                                                    'var(--color-chart-2)',
                                            }}
                                        />
                                        Error rate
                                    </span>
                                    <span className="flex items-center gap-1.5">
                                        <i
                                            className="size-2 rounded-full"
                                            style={{
                                                backgroundColor:
                                                    'var(--color-chart-3)',
                                            }}
                                        />
                                        Location error rate
                                    </span>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div className="h-64 min-w-0">
                                    <ResponsiveContainer
                                        width="100%"
                                        height="100%"
                                    >
                                        <LineChart data={dataQualityTrend}>
                                            <CartesianGrid
                                                vertical={false}
                                                stroke="var(--color-border)"
                                                strokeOpacity={0.6}
                                            />
                                            <XAxis
                                                dataKey="label"
                                                tick={{ fontSize: 11 }}
                                                tickLine={false}
                                                axisLine={false}
                                                interval="preserveStartEnd"
                                                stroke="var(--color-muted-foreground)"
                                            />
                                            <YAxis
                                                tick={{ fontSize: 11 }}
                                                tickLine={false}
                                                axisLine={false}
                                                width={40}
                                                unit="%"
                                                stroke="var(--color-muted-foreground)"
                                            />
                                            <RechartsTooltip
                                                content={
                                                    <ChartTooltip
                                                        formatter={(_, value) =>
                                                            `${value}%`
                                                        }
                                                    />
                                                }
                                            />
                                            <Line
                                                type="monotone"
                                                dataKey="duplicate_rate"
                                                name="Duplicate rate"
                                                stroke="var(--color-chart-1)"
                                                strokeWidth={2}
                                                dot={false}
                                                activeDot={{ r: 4 }}
                                            />
                                            <Line
                                                type="monotone"
                                                dataKey="error_rate"
                                                name="Error rate"
                                                stroke="var(--color-chart-2)"
                                                strokeWidth={2}
                                                dot={false}
                                                activeDot={{ r: 4 }}
                                            />
                                            <Line
                                                type="monotone"
                                                dataKey="location_error_rate"
                                                name="Location error rate"
                                                stroke="var(--color-chart-3)"
                                                strokeWidth={2}
                                                dot={false}
                                                activeDot={{ r: 4 }}
                                            />
                                        </LineChart>
                                    </ResponsiveContainer>
                                </div>
                            </CardContent>
                        </Card>

                        <UploadTimingHeatmap data={uploadTimingHeatmap} />
                    </>
                )}

                {agentPerformance.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Agent performance</CardTitle>
                        </CardHeader>
                        <CardContent className="overflow-x-auto p-0">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/60 text-left text-xs tracking-wide text-muted-foreground uppercase">
                                    <tr>
                                        {[
                                            'Agent',
                                            'Leads',
                                            'Qualified',
                                            'Qualification rate',
                                            'Replies',
                                            'Interested',
                                            'Uploads',
                                            'Avg batch size',
                                            'Duplicate rate',
                                            'Error rate',
                                        ].map((heading) => (
                                            <th
                                                key={heading}
                                                className="px-5 py-3"
                                            >
                                                {heading}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {agentPerformance.map((agent) => (
                                        <tr
                                            key={agent.id}
                                            className="hover:bg-muted/30"
                                        >
                                            <td className="px-5 py-3 font-medium">
                                                {agent.name}
                                            </td>
                                            <td className="px-5 py-3 tabular-nums">
                                                {agent.leads}
                                            </td>
                                            <td className="px-5 py-3 tabular-nums">
                                                {agent.qualified}
                                            </td>
                                            <td className="px-5 py-3 tabular-nums">
                                                {agent.qualification_rate}%
                                            </td>
                                            <td className="px-5 py-3 tabular-nums">
                                                {agent.replies}
                                            </td>
                                            <td className="px-5 py-3 tabular-nums">
                                                {agent.interested}
                                            </td>
                                            <td className="px-5 py-3 tabular-nums">
                                                {agent.uploads}
                                            </td>
                                            <td className="px-5 py-3 tabular-nums">
                                                {agent.avg_batch_size}
                                            </td>
                                            <td className="px-5 py-3 tabular-nums">
                                                {agent.duplicate_rate}%
                                            </td>
                                            <td className="px-5 py-3 tabular-nums">
                                                {agent.error_rate}%
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

Analytics.layout = {
    breadcrumbs: [{ title: 'Reports', href: analyticsIndex() }],
};
