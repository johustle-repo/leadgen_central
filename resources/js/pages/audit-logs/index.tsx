import { Head } from '@inertiajs/react';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { StatusBadge } from '@/components/status-badge';
import { index } from '@/routes/audit-logs';

const SENSITIVE_ACTION_PATTERN = /delete|disconnect|stop|cancel|reject/i;
const ROUTINE_ACTION_PATTERN =
    /create|upload|connect|enroll|sent|verified|forward/i;

function actionColorClass(action: string): string {
    if (SENSITIVE_ACTION_PATTERN.test(action)) {
        return 'bg-destructive/15 text-destructive dark:bg-destructive/20';
    }

    if (ROUTINE_ACTION_PATTERN.test(action)) {
        return 'bg-success/15 text-success dark:bg-success/20';
    }

    return 'bg-muted text-muted-foreground';
}

type AuditLog = {
    id: number;
    action: string;
    auditable_type: string | null;
    auditable_id: number | null;
    description: string;
    metadata: Record<string, string | number | null> | null;
    ip_address: string | null;
    created_at: string;
    user: { name: string; email: string } | null;
};

export default function AuditLogIndex({
    logs,
}: {
    logs: {
        data: AuditLog[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
}) {
    return (
        <>
            <Head title="Audit Logs" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Audit logs"
                    description="Review sensitive administrative actions across LeadGen Central."
                />
                <div className="overflow-hidden rounded-xl border bg-card">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/60 text-left">
                                <tr>
                                    <th className="p-3">Date</th>
                                    <th className="p-3">Administrator</th>
                                    <th className="p-3">Action</th>
                                    <th className="p-3">Details</th>
                                    <th className="p-3">IP address</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {logs.data.map((log) => (
                                    <tr key={log.id}>
                                        <td className="p-3 whitespace-nowrap text-muted-foreground">
                                            {new Date(
                                                log.created_at,
                                            ).toLocaleString()}
                                        </td>
                                        <td className="p-3">
                                            <p className="font-medium">
                                                {log.user?.name ??
                                                    'Deleted user'}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {log.user?.email ?? '—'}
                                            </p>
                                        </td>
                                        <td className="p-3">
                                            <StatusBadge
                                                value={log.action.replaceAll(
                                                    '.',
                                                    ' ',
                                                )}
                                                colorClass={actionColorClass(
                                                    log.action,
                                                )}
                                            />
                                        </td>
                                        <td className="p-3">
                                            <p>{log.description}</p>
                                            {log.metadata?.batch_code && (
                                                <p className="text-xs text-muted-foreground">
                                                    Batch:{' '}
                                                    {log.metadata.batch_code}
                                                </p>
                                            )}
                                        </td>
                                        <td className="p-3 font-mono text-xs text-muted-foreground">
                                            {log.ip_address ?? '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {!logs.data.length && (
                            <p className="p-12 text-center text-muted-foreground">
                                No audit activity recorded yet.
                            </p>
                        )}
                    </div>
                </div>
                <Pagination links={logs.links} />
            </div>
        </>
    );
}

AuditLogIndex.layout = {
    breadcrumbs: [{ title: 'Audit Logs', href: index() }],
};
