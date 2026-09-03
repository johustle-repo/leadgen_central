import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowRight,
    Building2,
    CalendarRange,
    Database,
    FileWarning,
    MailCheck,
    ShieldAlert,
    Sparkles,
    Target,
    TrendingUp,
} from 'lucide-react';
import { useState } from 'react';
import { PageHeader } from '@/components/page-header';
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
import { index as uploadsIndex, show as uploadShow } from '@/routes/uploads';

type Props = {
    stats: Record<string, number>;
    period: string;
    filters: Record<string, string>;
    productivity: Array<{
        id: number;
        name: string;
        uploaded: number | null;
        accepted: number | null;
        duplicates: number | null;
        errors: number | null;
        possible: number;
        qualified: number;
        forwarded: number;
    }>;
    recentBatches: Array<{
        id: number;
        batch_code: string;
        original_filename: string;
        processing_status: string;
        user: { name: string } | null;
    }>;
    recentLeads: Array<{
        id: number;
        lead_code: string;
        company_name: string;
        status: string;
        agent: { name: string } | null;
    }>;
};

const PERIOD_HINTS: Record<string, string> = {
    today: 'Activity recorded today.',
    week: 'From the start of this week through today.',
    month: 'From the start of this month through today.',
    custom: 'Pick an exact start and end date below.',
};

export default function Dashboard({
    stats,
    recentBatches,
    recentLeads,
    period,
    filters,
    productivity,
}: Props) {
    const [selectedPeriod, setSelectedPeriod] = useState(period);
    const isCustomPeriod = selectedPeriod === 'custom';

    const applyPeriod = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get(
            dashboard.url(),
            Object.fromEntries(new FormData(event.currentTarget)),
            { preserveState: true, replace: true },
        );
    };

    const totalLeads = stats.total_leads ?? 0;
    const uniqueCompanies = stats.unique_leads ?? 0;
    const qualifiedLeads = stats.qualified_leads ?? 0;

    const pipelineMetrics = [
        {
            label: 'Total leads',
            value: totalLeads,
            icon: Database,
            tone: 'text-info',
        },
        {
            label: 'Unique companies',
            value: uniqueCompanies,
            detail: totalLeads
                ? `${Math.round((uniqueCompanies / totalLeads) * 100)}% of total leads`
                : undefined,
            icon: Building2,
            tone: 'text-chart-1',
        },
        {
            label: 'Qualified leads',
            value: qualifiedLeads,
            icon: Target,
            tone: 'text-success',
        },
        {
            label: 'Qualification rate',
            value: `${stats.qualification_rate ?? 0}%`,
            detail: `${qualifiedLeads.toLocaleString()} of ${totalLeads.toLocaleString()} leads`,
            icon: TrendingUp,
            tone: 'text-chart-2',
        },
    ];

    const healthMetrics = [
        {
            label: 'Duplicates flagged',
            value: stats.duplicates_flagged ?? 0,
            detail: 'Exact + possible matches caught on import',
            icon: ShieldAlert,
            tone: 'text-warning',
        },
        {
            label: 'Data issues',
            value: stats.data_issues ?? 0,
            detail: 'Rejected, location, or processing errors',
            icon: FileWarning,
            tone: 'text-destructive',
        },
        {
            label: 'Unread replies',
            value: stats.unread_replies ?? 0,
            icon: MailCheck,
            tone: 'text-chart-4',
        },
        {
            label: 'Possible leads from replies',
            value: stats.possible_reply_leads ?? 0,
            icon: Sparkles,
            tone: 'text-chart-5',
        },
    ];

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Dashboard"
                    description="A live view of lead generation activity."
                />

                <form
                    onSubmit={applyPeriod}
                    className="rounded-xl border bg-card p-4"
                >
                    <div className="mb-3 flex items-center gap-1.5 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                        <CalendarRange className="size-3.5" />
                        Reporting period
                    </div>
                    <div className="grid gap-3 sm:grid-cols-4">
                        <div className="flex flex-col gap-1.5">
                            <label
                                htmlFor="dashboard-period"
                                className="text-xs text-muted-foreground"
                            >
                                Period
                            </label>
                            <Select
                                name="period"
                                defaultValue={period}
                                onValueChange={setSelectedPeriod}
                            >
                                <SelectTrigger
                                    id="dashboard-period"
                                    className="w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="today">
                                        Today
                                    </SelectItem>
                                    <SelectItem value="week">
                                        This Week
                                    </SelectItem>
                                    <SelectItem value="month">
                                        This Month
                                    </SelectItem>
                                    <SelectItem value="custom">
                                        Custom Date
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <label
                                htmlFor="dashboard-date-from"
                                className="text-xs text-muted-foreground"
                            >
                                From
                            </label>
                            <Input
                                id="dashboard-date-from"
                                type="date"
                                name="date_from"
                                defaultValue={filters.date_from}
                                disabled={!isCustomPeriod}
                                aria-label="Date from"
                            />
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <label
                                htmlFor="dashboard-date-to"
                                className="text-xs text-muted-foreground"
                            >
                                To
                            </label>
                            <Input
                                id="dashboard-date-to"
                                type="date"
                                name="date_to"
                                defaultValue={filters.date_to}
                                disabled={!isCustomPeriod}
                                aria-label="Date to"
                            />
                        </div>
                        <div className="flex flex-col justify-end">
                            <Button type="submit" variant="secondary">
                                Apply period
                            </Button>
                        </div>
                    </div>
                    <p className="mt-3 text-xs text-muted-foreground">
                        {PERIOD_HINTS[selectedPeriod] ??
                            PERIOD_HINTS.custom}
                    </p>
                </form>

                <section className="flex flex-col gap-3">
                    <h2 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                        Pipeline
                    </h2>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {pipelineMetrics.map((metric) => (
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
                </section>

                <section className="flex flex-col gap-3">
                    <h2 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                        Data health &amp; inbox
                    </h2>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {healthMetrics.map((metric) => (
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
                </section>

                {productivity.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Agent productivity</CardTitle>
                        </CardHeader>
                        <CardContent className="overflow-x-auto p-0">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/60 text-left">
                                    <tr>
                                        <th className="p-3">Agent</th>
                                        {[
                                            'Uploaded',
                                            'Accepted',
                                            'Duplicate',
                                            'Error',
                                            'Possible',
                                            'Qualified',
                                            'Forwarded',
                                        ].map((label) => (
                                            <th
                                                key={label}
                                                className="p-3 text-right"
                                            >
                                                {label}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {productivity.map((agent) => (
                                        <tr
                                            key={agent.id}
                                            className="hover:bg-muted/40"
                                        >
                                            <td className="p-3 font-medium">
                                                {agent.name}
                                            </td>
                                            <td className="p-3 text-right tabular-nums">
                                                {agent.uploaded ?? 0}
                                            </td>
                                            <td className="p-3 text-right tabular-nums">
                                                {agent.accepted ?? 0}
                                            </td>
                                            <td className="p-3 text-right tabular-nums">
                                                {agent.duplicates ?? 0}
                                            </td>
                                            <td className="p-3 text-right tabular-nums">
                                                {agent.errors ?? 0}
                                            </td>
                                            <td className="p-3 text-right tabular-nums">
                                                {agent.possible}
                                            </td>
                                            <td className="p-3 text-right tabular-nums">
                                                {agent.qualified}
                                            </td>
                                            <td className="p-3 text-right tabular-nums">
                                                {agent.forwarded}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-6 xl:grid-cols-2">
                    <Card>
                        <CardHeader className="flex-row items-center justify-between">
                            <CardTitle>Recent leads</CardTitle>
                            <Link
                                href={leadsIndex()}
                                className="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                            >
                                View all
                                <ArrowRight className="size-3.5" />
                            </Link>
                        </CardHeader>
                        <CardContent className="divide-y">
                            {recentLeads.length ? (
                                recentLeads.map((lead) => (
                                    <Link
                                        key={lead.id}
                                        href={leadEdit(lead.id)}
                                        className="-mx-2 flex items-center justify-between gap-3 rounded-lg px-2 py-3 transition-colors hover:bg-muted/40"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate font-medium">
                                                {lead.company_name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {lead.lead_code} ·{' '}
                                                {lead.agent?.name ??
                                                    'Deleted user'}
                                            </p>
                                        </div>
                                        <StatusBadge value={lead.status} />
                                    </Link>
                                ))
                            ) : (
                                <p className="py-8 text-center text-sm text-muted-foreground">
                                    No leads yet.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex-row items-center justify-between">
                            <CardTitle>Recent uploads</CardTitle>
                            <Link
                                href={uploadsIndex()}
                                className="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                            >
                                View all
                                <ArrowRight className="size-3.5" />
                            </Link>
                        </CardHeader>
                        <CardContent className="divide-y">
                            {recentBatches.length ? (
                                recentBatches.map((batch) => (
                                    <Link
                                        key={batch.id}
                                        href={uploadShow(batch.id)}
                                        className="-mx-2 flex items-center justify-between gap-3 rounded-lg px-2 py-3 transition-colors hover:bg-muted/40"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate font-medium">
                                                {batch.original_filename}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {batch.batch_code} ·{' '}
                                                {batch.user?.name ??
                                                    'Deleted user'}
                                            </p>
                                        </div>
                                        <StatusBadge
                                            value={batch.processing_status}
                                        />
                                    </Link>
                                ))
                            ) : (
                                <p className="py-8 text-center text-sm text-muted-foreground">
                                    No uploads yet.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
Dashboard.layout = { breadcrumbs: [{ title: 'Dashboard', href: dashboard() }] };
