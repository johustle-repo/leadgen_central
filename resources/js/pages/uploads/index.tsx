import { Head, Link, router, usePage, usePoll } from '@inertiajs/react';
import { RotateCcw, Trash2, Upload } from 'lucide-react';
import { useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    bulkDestroy,
    create,
    destroy,
    index,
    reanalyze,
    retry,
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
    user: { name: string } | null;
};
type Agent = { id: number; name: string };

const formatUploadedDate = (value: string) =>
    new Intl.DateTimeFormat('en-US', {
        timeZone: 'Asia/Manila',
        year: 'numeric',
        month: 'short',
        day: '2-digit',
    }).format(new Date(value));

export default function UploadIndex({
    batches,
    sort,
    filters,
    deletableTotal,
    agents,
}: {
    batches: {
        data: Batch[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    sort: string;
    filters: { agent_id: string };
    deletableTotal: number;
    agents: Agent[];
}) {
    const { auth } = usePage<{ auth: Auth }>().props;
    usePoll(5000, { only: ['batches'] });
    const [selectedBatchIds, setSelectedBatchIds] = useState<number[]>([]);
    const [selectAllMatching, setSelectAllMatching] = useState(false);
    const isAdministrator = auth.user.role === 'administrator';
    const deletableBatchIds = batches.data
        .filter((batch) =>
            ['completed', 'failed'].includes(batch.processing_status),
        )
        .map((batch) => batch.id);
    const selectedVisibleBatchIds = selectedBatchIds.filter((id) =>
        deletableBatchIds.includes(id),
    );
    const allDeletableBatchesSelected =
        deletableBatchIds.length > 0 &&
        deletableBatchIds.every((id) => selectedVisibleBatchIds.includes(id));
    const canSelectAllMatching =
        allDeletableBatchesSelected &&
        !selectAllMatching &&
        deletableTotal > selectedVisibleBatchIds.length;
    const selectedCount = selectAllMatching
        ? deletableTotal
        : selectedVisibleBatchIds.length;

    const toggleAllBatches = (checked: boolean) => {
        setSelectedBatchIds(checked ? deletableBatchIds : []);
        setSelectAllMatching(false);
    };

    const toggleBatch = (batchId: number, checked: boolean) => {
        setSelectAllMatching(false);
        setSelectedBatchIds((current) =>
            checked
                ? [
                      ...current.filter((id) => deletableBatchIds.includes(id)),
                      batchId,
                  ]
                : current.filter((id) => id !== batchId),
        );
    };

    const deleteSelectedBatches = () => {
        if (
            !window.confirm(
                `Delete ${selectedCount} selected upload histories? Imported leads will be preserved, but the raw files and row history will be removed.`,
            )
        ) {
            return;
        }

        router.delete(bulkDestroy.url(), {
            data: selectAllMatching
                ? { select_all: true }
                : { upload_batch_ids: selectedVisibleBatchIds },
            preserveScroll: true,
            onSuccess: () => {
                setSelectedBatchIds([]);
                setSelectAllMatching(false);
            },
        });
    };

    return (
        <>
            <Head title="Upload History" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Upload history"
                    description="Track every CSV batch and its row-level results."
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            {isAdministrator && selectedCount > 0 && (
                                <Button
                                    type="button"
                                    variant="destructive"
                                    onClick={deleteSelectedBatches}
                                >
                                    <Trash2 />
                                    Delete selected ({selectedCount})
                                </Button>
                            )}
                            <Button asChild>
                                <Link href={create()}>
                                    <Upload />
                                    Upload CSV
                                </Link>
                            </Button>
                        </div>
                    }
                />
                <div className="flex flex-wrap items-center justify-end gap-3">
                    {agents.length > 0 && (
                        <label className="flex items-center gap-2 text-sm text-muted-foreground">
                            <span>Agent</span>
                            <select
                                value={filters.agent_id}
                                onChange={(event) =>
                                    router.get(
                                        index.url(),
                                        {
                                            sort,
                                            agent_id:
                                                event.target.value || undefined,
                                        },
                                        { preserveState: true, replace: true },
                                    )
                                }
                                className="h-9 rounded-md border bg-background px-3 text-sm text-foreground"
                                aria-label="Filter by agent"
                            >
                                <option value="">All agents</option>
                                {agents.map((agent) => (
                                    <option key={agent.id} value={agent.id}>
                                        {agent.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                    )}
                    <label className="flex items-center gap-2 text-sm text-muted-foreground">
                        <span>Sort by</span>
                        <select
                            value={sort}
                            onChange={(event) =>
                                router.get(
                                    index.url(),
                                    {
                                        sort: event.target.value,
                                        agent_id: filters.agent_id || undefined,
                                    },
                                    { preserveState: true, replace: true },
                                )
                            }
                            className="h-9 rounded-md border bg-background px-3 text-sm text-foreground"
                            aria-label="Sort upload history"
                        >
                            <option value="newest">Newest first</option>
                            <option value="oldest">Oldest first</option>
                            <option value="filename_asc">Filename A–Z</option>
                            <option value="filename_desc">Filename Z–A</option>
                            <option value="agent_asc">Agent A–Z</option>
                            <option value="agent_desc">Agent Z–A</option>
                            <option value="status">Status</option>
                        </select>
                    </label>
                </div>
                {isAdministrator && canSelectAllMatching && (
                    <div className="flex items-center justify-between rounded-lg border border-cyan-500/20 bg-cyan-500/5 px-4 py-2.5 text-sm">
                        <span>
                            All {selectedVisibleBatchIds.length} deletable
                            uploads on this page are selected.
                        </span>
                        <button
                            type="button"
                            className="font-medium text-cyan-600 hover:underline dark:text-cyan-400"
                            onClick={() => setSelectAllMatching(true)}
                        >
                            Select all {deletableTotal} matching uploads
                        </button>
                    </div>
                )}
                {isAdministrator && selectAllMatching && (
                    <div className="flex items-center justify-between rounded-lg border border-cyan-500/20 bg-cyan-500/5 px-4 py-2.5 text-sm">
                        <span>
                            All {deletableTotal} deletable uploads are selected.
                        </span>
                        <button
                            type="button"
                            className="font-medium text-cyan-600 hover:underline dark:text-cyan-400"
                            onClick={() => setSelectAllMatching(false)}
                        >
                            Select just this page instead
                        </button>
                    </div>
                )}
                <div className="overflow-hidden rounded-xl border bg-card">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/60 text-left">
                                <tr>
                                    {isAdministrator && (
                                        <th className="w-12 p-3">
                                            <Checkbox
                                                checked={
                                                    allDeletableBatchesSelected ||
                                                    selectAllMatching
                                                }
                                                onCheckedChange={(checked) =>
                                                    toggleAllBatches(
                                                        checked === true,
                                                    )
                                                }
                                                disabled={
                                                    deletableBatchIds.length ===
                                                    0
                                                }
                                                aria-label="Select all deletable uploads on this page"
                                            />
                                        </th>
                                    )}
                                    <th className="p-3">Batch</th>
                                    <th className="p-3">Owner</th>
                                    <th className="p-3">Date uploaded</th>
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
                                        {isAdministrator && (
                                            <td className="p-3">
                                                <Checkbox
                                                    checked={
                                                        selectAllMatching ||
                                                        selectedVisibleBatchIds.includes(
                                                            batch.id,
                                                        )
                                                    }
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        toggleBatch(
                                                            batch.id,
                                                            checked === true,
                                                        )
                                                    }
                                                    disabled={
                                                        !deletableBatchIds.includes(
                                                            batch.id,
                                                        )
                                                    }
                                                    aria-label={`Select ${batch.original_filename}`}
                                                />
                                            </td>
                                        )}
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
                                            {batch.user?.name ?? 'Former user'}
                                        </td>
                                        <td className="p-3 whitespace-nowrap">
                                            {formatUploadedDate(
                                                batch.created_at,
                                            )}
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
                                                    'pending' && (
                                                    <Link
                                                        href={retry(batch.id)}
                                                        method="post"
                                                        as="button"
                                                        preserveScroll
                                                        className="inline-flex items-center gap-1.5 rounded-md border px-3 py-2 text-xs font-medium whitespace-nowrap hover:bg-muted"
                                                    >
                                                        <RotateCcw className="size-3.5" />
                                                        Retry processing
                                                    </Link>
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
