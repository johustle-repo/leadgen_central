import { Head, router } from '@inertiajs/react';
import {
    BarChart3,
    MailCheck,
    ShieldAlert,
    Target,
    TrendingDown,
    TrendingUp,
    UsersRound,
} from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { index as analyticsIndex } from '@/routes/analytics';

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
};
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
};

const prettyLabel = (value: string) =>
    value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());

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
                                    className={`h-full rounded-full ${color}`}
                                    style={{
                                        width: `${Math.max((item.value / maximum) * 100, 3)}%`,
                                    }}
                                />
                            </div>
                        </div>
                    ))
                ) : (
                    <p className="py-8 text-center text-sm text-muted-foreground">
                        No data for this period.
                    </p>
                )}
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
}: Props) {
    const maxActivity = Math.max(
        ...dailyActivity.flatMap((day) => [day.leads, day.replies]),
        1,
    );
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
            color: 'text-cyan-500',
        },
        {
            label: 'Qualified leads',
            value: summary.qualified_leads,
            detail: `${summary.qualification_rate}% qualification rate`,
            icon: Target,
            color: 'text-emerald-500',
        },
        {
            label: 'Email replies',
            value: summary.replies,
            detail: <Change value={summary.reply_change} />,
            icon: MailCheck,
            color: 'text-indigo-500',
        },
        {
            label: 'Reply rate',
            value: `${summary.reply_rate}%`,
            detail: `${summary.replied_leads} unique leads replied`,
            icon: BarChart3,
            color: 'text-sky-500',
        },
        {
            label: 'Interested replies',
            value: summary.interested_replies,
            detail: 'Interested or possible lead',
            icon: TrendingUp,
            color: 'text-violet-500',
        },
        {
            label: 'Duplicates flagged',
            value: summary.duplicates,
            detail: 'Detected during uploads',
            icon: ShieldAlert,
            color: 'text-amber-500',
        },
    ];

    return (
        <>
            <Head title="Analytics" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Analytics"
                    description="Measure lead quality, reply outcomes, and team performance over time."
                />

                <form
                    onSubmit={applyFilters}
                    className="grid gap-3 rounded-xl border bg-card p-4 md:grid-cols-[minmax(180px,0.8fr)_1fr_1fr_auto]"
                >
                    <select
                        name="period"
                        defaultValue={period}
                        className="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="7_days">Last 7 days</option>
                        <option value="30_days">Last 30 days</option>
                        <option value="90_days">Last 90 days</option>
                        <option value="custom">Custom range</option>
                    </select>
                    <Input
                        type="date"
                        name="date_from"
                        defaultValue={filters.date_from}
                        aria-label="Analytics start date"
                    />
                    <Input
                        type="date"
                        name="date_to"
                        defaultValue={filters.date_to}
                        aria-label="Analytics end date"
                    />
                    <Button type="submit" variant="secondary">
                        Update analytics
                    </Button>
                </form>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {metrics.map((metric) => {
                        const Icon = metric.icon;

                        return (
                            <Card
                                key={metric.label}
                                className="overflow-hidden"
                            >
                                <CardContent className="flex items-start justify-between gap-4 p-5">
                                    <div className="flex min-w-0 flex-col gap-1">
                                        <p className="text-sm text-muted-foreground">
                                            {metric.label}
                                        </p>
                                        <p className="text-3xl font-bold tracking-tight tabular-nums">
                                            {typeof metric.value === 'number'
                                                ? metric.value.toLocaleString()
                                                : metric.value}
                                        </p>
                                        <div className="text-xs text-muted-foreground">
                                            {metric.detail}
                                        </div>
                                    </div>
                                    <div
                                        className={`flex size-11 shrink-0 items-center justify-center rounded-2xl bg-current/10 ${metric.color}`}
                                    >
                                        <Icon className="size-5" />
                                    </div>
                                </CardContent>
                            </Card>
                        );
                    })}
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
                                <i className="size-2 rounded-full bg-cyan-500" />
                                Leads
                            </span>
                            <span className="flex items-center gap-1.5">
                                <i className="size-2 rounded-full bg-violet-500" />
                                Replies
                            </span>
                        </div>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <div
                            className="grid h-64 min-w-3xl items-end gap-1"
                            style={{
                                gridTemplateColumns: `repeat(${dailyActivity.length}, minmax(8px, 1fr))`,
                            }}
                        >
                            {dailyActivity.map((day, index) => (
                                <div
                                    key={day.date}
                                    className="group relative flex h-full items-end justify-center gap-px"
                                    title={`${day.label}: ${day.leads} leads, ${day.replies} replies`}
                                >
                                    <div
                                        className="w-1/2 min-w-1 rounded-t bg-cyan-500/85 transition-opacity group-hover:opacity-70"
                                        style={{
                                            height: `${Math.max((day.leads / maxActivity) * 88, day.leads ? 4 : 0)}%`,
                                        }}
                                    />
                                    <div
                                        className="w-1/2 min-w-1 rounded-t bg-violet-500/85 transition-opacity group-hover:opacity-70"
                                        style={{
                                            height: `${Math.max((day.replies / maxActivity) * 88, day.replies ? 4 : 0)}%`,
                                        }}
                                    />
                                    {(dailyActivity.length <= 31 ||
                                        index % 7 === 0) && (
                                        <span className="absolute bottom-0 translate-y-5 text-[10px] whitespace-nowrap text-muted-foreground">
                                            {day.label}
                                        </span>
                                    )}
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>

                <div className="grid gap-6 xl:grid-cols-2">
                    <Breakdown
                        title="Lead status"
                        items={leadStatuses}
                        color="bg-emerald-500"
                    />
                    <Breakdown
                        title="Reply classification"
                        items={replyClassifications}
                        color="bg-violet-500"
                    />
                    <Breakdown
                        title="Lead sources"
                        items={sources}
                        color="bg-cyan-500"
                    />
                    <Breakdown
                        title="Top countries"
                        items={countries}
                        color="bg-indigo-500"
                    />
                </div>

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
    breadcrumbs: [{ title: 'Analytics', href: analyticsIndex() }],
};
