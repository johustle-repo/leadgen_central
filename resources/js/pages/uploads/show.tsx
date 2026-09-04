import { Head } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    ClipboardCheck,
    Copy,
    Database,
    FileSearch,
    FileWarning,
    MapPinOff,
    ShieldAlert,
    UserPlus,
} from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { FilterTabs } from '@/components/filter-tabs';
import { HeaderActionsPortal } from '@/components/header-actions';
import { Pagination } from '@/components/pagination';
import { StatTile } from '@/components/stat-tile';
import { StatusBadge } from '@/components/status-badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { errors, index, show } from '@/routes/uploads';
type Batch = {
    id: number;
    batch_code: string;
    original_filename: string;
    total_rows: number;
    accepted_rows: number;
    new_leads: number;
    valid_leads: number;
    exact_duplicate_rows: number;
    possible_duplicate_rows: number;
    invalid_rows: number;
    location_error_rows: number;
    rejected_rows: number;
    error_rows: number;
    processing_status: string;
    failure_message: string | null;
    user: { name: string } | null;
};
type Row = {
    id: number;
    row_number: number;
    raw_data: Record<string, string>;
    processing_status: string;
    error_category: string | null;
    error_message: string | null;
    lead: { lead_code: string; company_name: string } | null;
};
export default function UploadShow({
    batch,
    rows,
    filter,
}: {
    batch: Batch;
    rows: {
        data: Row[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filter: string;
}) {
    const tabs = [
        '',
        'accepted',
        'needs_review',
        'duplicate',
        'rejected',
        'error',
    ];

    return (
        <>
            <Head title={batch.batch_code} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <HeaderActionsPortal>
                    <a
                        href={errors.url(batch.id)}
                        download
                        className="rounded-md border px-3 py-1.5 text-sm font-medium hover:bg-muted"
                    >
                        Download problem CSV
                    </a>
                    <StatusBadge value={batch.processing_status} />
                </HeaderActionsPortal>
                <section className="flex flex-col gap-3">
                    <h2 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                        Import results
                    </h2>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {[
                            {
                                label: 'Uploaded Rows',
                                value: batch.total_rows,
                                icon: Database,
                                tone: 'text-info',
                            },
                            {
                                label: 'New Leads',
                                value: batch.new_leads,
                                icon: UserPlus,
                                tone: 'text-chart-1',
                            },
                            {
                                label: 'Valid Leads',
                                value: batch.valid_leads,
                                icon: CheckCircle2,
                                tone: 'text-success',
                            },
                            {
                                label: 'Accepted Leads',
                                value: batch.accepted_rows,
                                icon: ClipboardCheck,
                                tone: 'text-chart-2',
                            },
                        ].map((metric) => (
                            <StatTile key={metric.label} {...metric} />
                        ))}
                    </div>
                </section>
                <section className="flex flex-col gap-3">
                    <h2 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                        Duplicates &amp; issues
                    </h2>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                        {[
                            {
                                label: 'Exact Duplicates',
                                value: batch.exact_duplicate_rows,
                                icon: Copy,
                                tone: 'text-warning',
                            },
                            {
                                label: 'Possible Duplicates',
                                value: batch.possible_duplicate_rows,
                                icon: ShieldAlert,
                                tone: 'text-chart-4',
                            },
                            {
                                label: 'Invalid Rows',
                                value: batch.invalid_rows,
                                icon: FileWarning,
                                tone: 'text-destructive',
                            },
                            {
                                label: 'Location Issues',
                                value: batch.location_error_rows,
                                icon: MapPinOff,
                                tone: 'text-chart-5',
                            },
                            {
                                label: 'Other Errors',
                                value: batch.error_rows,
                                icon: AlertTriangle,
                                tone: 'text-destructive',
                            },
                        ].map((metric) => (
                            <StatTile key={metric.label} {...metric} />
                        ))}
                    </div>
                </section>
                {batch.failure_message && (
                    <div className="rounded-lg border border-destructive/30 bg-destructive/10 p-4 text-sm text-destructive">
                        {batch.failure_message}
                    </div>
                )}
                <FilterTabs
                    tabs={tabs.map((tab) => ({
                        label: tab
                            ? tab
                                  .replaceAll('_', ' ')
                                  .replace(/\b\w/g, (letter) =>
                                      letter.toUpperCase(),
                                  )
                            : 'All rows',
                        href: show(batch.id, {
                            query: { status: tab || undefined },
                        }),
                        active: filter === tab,
                    }))}
                />
                {rows.data.length ? (
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead>Row</TableHead>
                                <TableHead>Submitted data</TableHead>
                                <TableHead>Result</TableHead>
                                <TableHead>Message</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {rows.data.map((row) => {
                                const isDuplicate =
                                    row.processing_status === 'duplicate' ||
                                    row.error_category === 'exact_duplicate' ||
                                    row.error_category === 'possible_duplicate';
                                const hasError = Boolean(row.error_message);

                                return (
                                    <TableRow
                                        key={row.id}
                                        className={
                                            isDuplicate
                                                ? 'bg-destructive/10 text-destructive'
                                                : hasError
                                                  ? 'bg-warning/10'
                                                  : undefined
                                        }
                                    >
                                        <TableCell>{row.row_number}</TableCell>
                                        <TableCell>
                                            <p className="max-w-xl truncate">
                                                {Object.values(row.raw_data)
                                                    .filter(Boolean)
                                                    .join(' · ')}
                                            </p>
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge
                                                value={row.processing_status}
                                            />
                                        </TableCell>
                                        <TableCell
                                            className={
                                                isDuplicate
                                                    ? 'font-semibold text-destructive'
                                                    : hasError
                                                      ? 'font-medium text-warning'
                                                      : 'text-muted-foreground'
                                            }
                                        >
                                            {row.error_message ||
                                                row.lead?.lead_code ||
                                                '—'}
                                        </TableCell>
                                    </TableRow>
                                );
                            })}
                        </TableBody>
                    </Table>
                ) : (
                    <div className="rounded-xl border bg-card">
                        <EmptyState
                            icon={FileSearch}
                            title="No rows in this view"
                            description="Try a different status filter above."
                        />
                    </div>
                )}
                <Pagination links={rows.links} />
            </div>
        </>
    );
}
UploadShow.layout = {
    breadcrumbs: [{ title: 'Upload History', href: index() }],
};
