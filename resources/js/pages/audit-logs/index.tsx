import { Head } from '@inertiajs/react';
import { History } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { Pagination } from '@/components/pagination';
import { StatusBadge } from '@/components/status-badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
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
                {logs.data.length ? (
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead>Date</TableHead>
                                <TableHead>Administrator</TableHead>
                                <TableHead>Action</TableHead>
                                <TableHead>Details</TableHead>
                                <TableHead>IP address</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {logs.data.map((log) => (
                                <TableRow key={log.id}>
                                    <TableCell className="whitespace-nowrap text-muted-foreground">
                                        {new Date(
                                            log.created_at,
                                        ).toLocaleString()}
                                    </TableCell>
                                    <TableCell>
                                        <p className="font-medium">
                                            {log.user?.name ?? 'Deleted user'}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {log.user?.email ?? '—'}
                                        </p>
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge
                                            value={log.action.replaceAll(
                                                '.',
                                                ' ',
                                            )}
                                            colorClass={actionColorClass(
                                                log.action,
                                            )}
                                        />
                                    </TableCell>
                                    <TableCell>
                                        <p>{log.description}</p>
                                        {log.metadata?.batch_code && (
                                            <p className="text-xs text-muted-foreground">
                                                Batch: {log.metadata.batch_code}
                                            </p>
                                        )}
                                    </TableCell>
                                    <TableCell className="font-mono text-xs text-muted-foreground">
                                        {log.ip_address ?? '—'}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                ) : (
                    <div className="rounded-xl border bg-card">
                        <EmptyState
                            icon={History}
                            title="No audit activity recorded yet"
                            description="Sensitive administrative actions will appear here as they happen."
                        />
                    </div>
                )}
                <Pagination links={logs.links} />
            </div>
        </>
    );
}

AuditLogIndex.layout = {
    breadcrumbs: [{ title: 'Audit Logs', href: index() }],
};
