import { Head, Link, router } from '@inertiajs/react';
import {
    CheckCircle2,
    Database,
    FileWarning,
    MailCheck,
    ShieldAlert,
    Sparkles,
    Target,
    TrendingUp,
} from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';
import { edit as leadEdit } from '@/routes/leads';
import { show as uploadShow } from '@/routes/uploads';

type Props = {
    stats: Record<string, number>;
    period: string;
    filters: Record<string, string>;
    productivity: Array<{
        id: number;
        name: string;
        uploaded: number | null;
        unique_leads: number | null;
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
export default function Dashboard({
    stats,
    recentBatches,
    recentLeads,
    period,
    filters,
    productivity,
}: Props) {
    const applyPeriod = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get(
            dashboard.url(),
            Object.fromEntries(new FormData(event.currentTarget)),
            { preserveState: true, replace: true },
        );
    };
    const criticalMetrics = [
        {
            label: 'Total leads',
            value: stats.total_leads ?? 0,
            icon: Database,
            tone: 'text-cyan-600 dark:text-cyan-300',
        },
        {
            label: 'Unique leads',
            value: stats.unique_leads ?? 0,
            icon: CheckCircle2,
            tone: 'text-emerald-600 dark:text-emerald-300',
        },
        {
            label: 'Qualified leads',
            value: stats.qualified_leads ?? 0,
            icon: Target,
            tone: 'text-indigo-600 dark:text-indigo-300',
        },
        {
            label: 'Qualification rate',
            value: `${stats.qualification_rate ?? 0}%`,
            icon: TrendingUp,
            tone: 'text-sky-600 dark:text-sky-300',
        },
        {
            label: 'Duplicates flagged',
            value: stats.duplicates_flagged ?? 0,
            icon: ShieldAlert,
            tone: 'text-amber-600 dark:text-amber-300',
        },
        {
            label: 'Data issues',
            value: stats.data_issues ?? 0,
            icon: FileWarning,
            tone: 'text-red-600 dark:text-red-300',
        },
        {
            label: 'Unread replies',
            value: stats.unread_replies ?? 0,
            icon: MailCheck,
            tone: 'text-cyan-600 dark:text-cyan-300',
        },
        {
            label: 'Possible leads from replies',
            value: stats.possible_reply_leads ?? 0,
            icon: Sparkles,
            tone: 'text-emerald-600 dark:text-emerald-300',
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
                    className="grid gap-3 rounded-xl border bg-card p-4 sm:grid-cols-4"
                >
                    <select
                        name="period"
                        defaultValue={period}
                        className="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="today">Today</option>
                        <option value="week">This Week</option>
                        <option value="month">This Month</option>
                        <option value="custom">Custom Date</option>
                    </select>
                    <Input
                        type="date"
                        name="date_from"
                        defaultValue={filters.date_from}
                        aria-label="Date from"
                    />
                    <Input
                        type="date"
                        name="date_to"
                        defaultValue={filters.date_to}
                        aria-label="Date to"
                    />
                    <Button type="submit" variant="secondary">
                        Apply period
                    </Button>
                </form>
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {criticalMetrics.map((metric) => {
                        const Icon = metric.icon;

                        return (
                            <Card key={metric.label}>
                                <CardContent className="flex items-center justify-between gap-4 p-5">
                                    <div>
                                        <p className="text-sm text-muted-foreground">
                                            {metric.label}
                                        </p>
                                        <p className="mt-1 text-3xl font-bold tracking-tight">
                                            {typeof metric.value === 'number'
                                                ? metric.value.toLocaleString()
                                                : metric.value}
                                        </p>
                                    </div>
                                    <div
                                        className={`flex size-11 items-center justify-center rounded-2xl bg-current/8 ${metric.tone}`}
                                    >
                                        <Icon className="size-5" />
                                    </div>
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>
                {productivity.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Agent productivity</CardTitle>
                        </CardHeader>
                        <CardContent className="overflow-x-auto p-0">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/60 text-left">
                                    <tr>
                                        {[
                                            'Agent',
                                            'Uploaded',
                                            'Unique',
                                            'Duplicate',
                                            'Error',
                                            'Possible',
                                            'Qualified',
                                            'Forwarded',
                                        ].map((label) => (
                                            <th key={label} className="p-3">
                                                {label}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {productivity.map((agent) => (
                                        <tr key={agent.id}>
                                            <td className="p-3 font-medium">
                                                {agent.name}
                                            </td>
                                            <td className="p-3">
                                                {agent.uploaded ?? 0}
                                            </td>
                                            <td className="p-3">
                                                {agent.unique_leads ?? 0}
                                            </td>
                                            <td className="p-3">
                                                {agent.duplicates ?? 0}
                                            </td>
                                            <td className="p-3">
                                                {agent.errors ?? 0}
                                            </td>
                                            <td className="p-3">
                                                {agent.possible}
                                            </td>
                                            <td className="p-3">
                                                {agent.qualified}
                                            </td>
                                            <td className="p-3">
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
                        <CardHeader>
                            <CardTitle>Recent leads</CardTitle>
                        </CardHeader>
                        <CardContent className="divide-y">
                            {recentLeads.length ? (
                                recentLeads.map((lead) => (
                                    <Link
                                        key={lead.id}
                                        href={leadEdit(lead.id)}
                                        className="flex items-center justify-between gap-3 py-3"
                                    >
                                        <div>
                                            <p className="font-medium">
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
                        <CardHeader>
                            <CardTitle>Recent uploads</CardTitle>
                        </CardHeader>
                        <CardContent className="divide-y">
                            {recentBatches.length ? (
                                recentBatches.map((batch) => (
                                    <Link
                                        key={batch.id}
                                        href={uploadShow(batch.id)}
                                        className="flex items-center justify-between gap-3 py-3"
                                    >
                                        <div>
                                            <p className="font-medium">
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
