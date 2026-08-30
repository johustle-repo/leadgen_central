import { Head, Link } from '@inertiajs/react';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { StatusBadge } from '@/components/status-badge';
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
    user: { name: string };
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
                <PageHeader
                    title={batch.batch_code}
                    description={`${batch.original_filename} · ${batch.user.name}`}
                    actions={
                        <div className="flex items-center gap-2">
                            <a
                                href={errors.url(batch.id)}
                                download
                                className="rounded-md border px-3 py-2 text-sm font-medium hover:bg-muted"
                            >
                                Download problem CSV
                            </a>
                            <StatusBadge value={batch.processing_status} />
                        </div>
                    }
                />
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5">
                    {[
                        ['Uploaded Rows', batch.total_rows],
                        ['New Leads', batch.new_leads],
                        ['Valid Leads', batch.valid_leads],
                        ['Accepted Leads', batch.accepted_rows],
                        ['Exact Duplicates', batch.exact_duplicate_rows],
                        ['Possible Duplicates', batch.possible_duplicate_rows],
                        ['Invalid Rows', batch.invalid_rows],
                        ['Location Issues', batch.location_error_rows],
                        ['Other Errors', batch.error_rows],
                    ].map(([label, value]) => (
                        <div
                            key={String(label)}
                            className="rounded-xl border bg-card p-5"
                        >
                            <p className="text-sm text-muted-foreground">
                                {label}
                            </p>
                            <p className="text-3xl font-semibold">{value}</p>
                        </div>
                    ))}
                </div>
                {batch.failure_message && (
                    <div className="rounded-lg border border-destructive/30 bg-destructive/10 p-4 text-sm text-destructive">
                        {batch.failure_message}
                    </div>
                )}
                <div className="flex flex-wrap gap-2">
                    {tabs.map((tab) => (
                        <Link
                            key={tab}
                            href={show(batch.id, {
                                query: { status: tab || undefined },
                            })}
                            className={`rounded-md px-3 py-2 text-sm capitalize ${filter === tab ? 'bg-primary text-primary-foreground' : 'border'}`}
                        >
                            {tab || 'all rows'}
                        </Link>
                    ))}
                </div>
                <div className="overflow-hidden rounded-xl border bg-card">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/60 text-left">
                                <tr>
                                    <th className="p-3">Row</th>
                                    <th className="p-3">Submitted data</th>
                                    <th className="p-3">Result</th>
                                    <th className="p-3">Message</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {rows.data.map((row) => {
                                    const isDuplicate =
                                        row.processing_status === 'duplicate' ||
                                        row.error_category ===
                                            'exact_duplicate' ||
                                        row.error_category ===
                                            'possible_duplicate';
                                    const hasError = Boolean(row.error_message);

                                    return (
                                        <tr
                                            key={row.id}
                                            className={
                                                isDuplicate
                                                    ? 'bg-red-500/10 text-red-700 dark:text-red-300'
                                                    : hasError
                                                      ? 'bg-amber-500/10'
                                                      : undefined
                                            }
                                        >
                                            <td className="p-3">
                                                {row.row_number}
                                            </td>
                                            <td className="p-3">
                                                <p className="max-w-xl truncate">
                                                    {Object.values(row.raw_data)
                                                        .filter(Boolean)
                                                        .join(' · ')}
                                                </p>
                                            </td>
                                            <td className="p-3">
                                                <StatusBadge
                                                    value={
                                                        row.processing_status
                                                    }
                                                />
                                            </td>
                                            <td
                                                className={`p-3 ${
                                                    isDuplicate
                                                        ? 'font-semibold text-red-700 dark:text-red-300'
                                                        : hasError
                                                          ? 'font-medium text-amber-700 dark:text-amber-300'
                                                          : 'text-muted-foreground'
                                                }`}
                                            >
                                                {row.error_message ||
                                                    row.lead?.lead_code ||
                                                    '—'}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                        {!rows.data.length && (
                            <p className="p-12 text-center text-muted-foreground">
                                No rows in this view.
                            </p>
                        )}
                    </div>
                </div>
                <Pagination links={rows.links} />
            </div>
        </>
    );
}
UploadShow.layout = {
    breadcrumbs: [{ title: 'Upload History', href: index() }],
};
