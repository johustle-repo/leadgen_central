import { Head, Link, usePage } from '@inertiajs/react';
import { RotateCcw, Trash2, Upload } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    cleaned,
    create,
    destroy,
    index,
    reanalyze,
    show,
} from '@/routes/uploads';
import type { Auth } from '@/types';
type Batch = {
    id: number;
    batch_code: string;
    original_filename: string;
    total_rows: number;
    accepted_rows: number;
    rejected_rows: number;
    error_rows: number;
    processing_status: string;
    created_at: string;
    user: { name: string };
};
export default function UploadIndex({
    batches,
}: {
    batches: {
        data: Batch[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
}) {
    const { auth } = usePage<{ auth: Auth }>().props;

    return (
        <>
            <Head title="Upload History" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Upload history"
                    description="Track every CSV batch and its row-level results."
                    actions={
                        <Button asChild>
                            <Link href={create()}>
                                <Upload />
                                Upload CSV
                            </Link>
                        </Button>
                    }
                />
                <div className="overflow-hidden rounded-xl border bg-card">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/60 text-left">
                                <tr>
                                    <th className="p-3">Batch</th>
                                    <th className="p-3">Owner</th>
                                    <th className="p-3">Rows</th>
                                    <th className="p-3">Accepted</th>
                                    <th className="p-3">Rejected</th>
                                    <th className="p-3">Errors</th>
                                    <th className="p-3">Status</th>
                                    <th className="p-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {batches.data.map((batch) => (
                                    <tr key={batch.id}>
                                        <td className="p-3">
                                            <Link
                                                href={show(batch.id)}
                                                className="font-medium hover:underline"
                                            >
                                                {batch.original_filename}
                                            </Link>
                                            <div className="text-xs text-muted-foreground">
                                                {batch.batch_code}
                                            </div>
                                        </td>
                                        <td className="p-3">
                                            {batch.user.name}
                                        </td>
                                        <td className="p-3">
                                            {batch.total_rows}
                                        </td>
                                        <td className="p-3 text-emerald-700">
                                            {batch.accepted_rows}
                                        </td>
                                        <td className="p-3 text-red-700">
                                            {batch.rejected_rows}
                                        </td>
                                        <td className="p-3 text-red-700">
                                            {batch.error_rows}
                                        </td>
                                        <td className="p-3">
                                            <StatusBadge
                                                value={batch.processing_status}
                                            />
                                        </td>
                                        <td className="p-3">
                                            <div className="flex items-center gap-2">
                                                {batch.processing_status ===
                                                    'completed' && (
                                                    <a
                                                        href={cleaned.url(
                                                            batch.id,
                                                        )}
                                                        download
                                                        className="inline-flex rounded-md border px-3 py-2 text-xs font-medium whitespace-nowrap hover:bg-muted"
                                                    >
                                                        Download cleaned CSV
                                                    </a>
                                                )}
                                                {batch.processing_status ===
                                                    'completed' && (
                                                    <Link
                                                        href={reanalyze(
                                                            batch.id,
                                                        )}
                                                        method="post"
                                                        as="button"
                                                        preserveScroll
                                                        onBefore={() =>
                                                            window.confirm(
                                                                'Re-analyze this upload’s duplicate rows using the latest rules?',
                                                            )
                                                        }
                                                        className="inline-flex items-center gap-1.5 rounded-md border px-3 py-2 text-xs font-medium whitespace-nowrap hover:bg-muted"
                                                    >
                                                        <RotateCcw className="size-3.5" />
                                                        Re-analyze
                                                    </Link>
                                                )}
                                                {auth.user.role ===
                                                    'administrator' &&
                                                    [
                                                        'completed',
                                                        'failed',
                                                    ].includes(
                                                        batch.processing_status,
                                                    ) && (
                                                        <Link
                                                            href={destroy(
                                                                batch.id,
                                                            )}
                                                            method="delete"
                                                            as="button"
                                                            preserveScroll
                                                            onBefore={() =>
                                                                window.confirm(
                                                                    `Delete ${batch.original_filename} from upload history? Imported leads will be preserved, but this cannot be undone.`,
                                                                )
                                                            }
                                                            className="inline-flex items-center gap-1.5 rounded-md border border-red-500/30 px-3 py-2 text-xs font-medium whitespace-nowrap text-red-700 hover:bg-red-500/10 dark:text-red-300"
                                                        >
                                                            <Trash2 className="size-3.5" />
                                                            Delete
                                                        </Link>
                                                    )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {!batches.data.length && (
                            <p className="p-12 text-center text-muted-foreground">
                                No upload batches yet.
                            </p>
                        )}
                    </div>
                </div>
                <Pagination links={batches.links} />
            </div>
        </>
    );
}
UploadIndex.layout = {
    breadcrumbs: [{ title: 'Upload History', href: index() }],
};
