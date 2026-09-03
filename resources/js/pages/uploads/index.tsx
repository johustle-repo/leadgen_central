import { Head, Link, router, usePage, usePoll } from '@inertiajs/react';
import { RotateCcw, Trash2, Upload } from 'lucide-react';
import { useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
const ALL_AGENTS = '__all__';

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
    filters: { agent_id: string; per_page: string };
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

    const updateQuery = (
        changes: Partial<{
            sort: string;
            agent_id: string;
            per_page: string;
        }>,
    ) => {
        router.get(
            index.url(),
            {
                sort,
                agent_id: filters.agent_id || undefined,
                per_page: filters.per_page,
                ...changes,
            },
            { preserveState: true, replace: true },
        );
    };

    const deleteSelectedBatches = () => {
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
                                <Dialog>
                                    <DialogTrigger asChild>
                                        <Button
                                            type="button"
                                            variant="destructive"
                                        >
                                            <Trash2 />
                                            Delete selected ({selectedCount})
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent>
                                        <DialogTitle>
                                            Delete {selectedCount} upload
                                            {selectedCount === 1 ? '' : 's'}?
                                        </DialogTitle>
                                        <DialogDescription>
                                            Imported leads will be preserved,
                                            but the raw files and row history
                                            will be removed. This can't be
                                            undone.
                                        </DialogDescription>
                                        <DialogFooter>
                                            <DialogClose asChild>
                                                <Button variant="secondary">
                                                    Cancel
                                                </Button>
                                            </DialogClose>
                                            <Button
                                                type="button"
                                                variant="destructive"
                                                onClick={deleteSelectedBatches}
                                            >
                                                Delete
                                            </Button>
                                        </DialogFooter>
                                    </DialogContent>
                                </Dialog>
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
                    <label className="flex items-center gap-2 text-sm text-muted-foreground">
                        <span>Show</span>
                        <Select
                            value={filters.per_page}
                            onValueChange={(value) =>
                                updateQuery({ per_page: value })
                            }
                        >
                            <SelectTrigger aria-label="Uploads per page">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="10">10 per page</SelectItem>
                                <SelectItem value="25">25 per page</SelectItem>
                                <SelectItem value="50">50 per page</SelectItem>
                                <SelectItem value="100">
                                    100 per page
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </label>
                    {agents.length > 0 && (
                        <label className="flex items-center gap-2 text-sm text-muted-foreground">
                            <span>Agent</span>
                            <Select
                                value={filters.agent_id || ALL_AGENTS}
                                onValueChange={(value) =>
                                    updateQuery({
                                        agent_id:
                                            value === ALL_AGENTS ? '' : value,
                                    })
                                }
                            >
                                <SelectTrigger aria-label="Filter by agent">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL_AGENTS}>
                                        All agents
                                    </SelectItem>
                                    {agents.map((agent) => (
                                        <SelectItem
                                            key={agent.id}
                                            value={String(agent.id)}
                                        >
                                            {agent.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </label>
                    )}
                    <label className="flex items-center gap-2 text-sm text-muted-foreground">
                        <span>Sort by</span>
                        <Select
                            value={sort}
                            onValueChange={(value) =>
                                updateQuery({ sort: value })
                            }
                        >
                            <SelectTrigger aria-label="Sort upload history">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="newest">
                                    Newest first
                                </SelectItem>
                                <SelectItem value="oldest">
                                    Oldest first
                                </SelectItem>
                                <SelectItem value="filename_asc">
                                    Filename A–Z
                                </SelectItem>
                                <SelectItem value="filename_desc">
                                    Filename Z–A
                                </SelectItem>
                                <SelectItem value="agent_asc">
                                    Agent A–Z
                                </SelectItem>
                                <SelectItem value="agent_desc">
                                    Agent Z–A
                                </SelectItem>
                                <SelectItem value="status">Status</SelectItem>
                            </SelectContent>
                        </Select>
                    </label>
                </div>
                {isAdministrator && canSelectAllMatching && (
                    <div className="flex items-center justify-between rounded-lg border border-info/20 bg-info/5 px-4 py-2.5 text-sm">
                        <span>
                            All {selectedVisibleBatchIds.length} deletable
                            uploads on this page are selected.
                        </span>
                        <button
                            type="button"
                            className="font-medium text-info hover:underline"
                            onClick={() => setSelectAllMatching(true)}
                        >
                            Select all {deletableTotal} matching uploads
                        </button>
                    </div>
                )}
                {isAdministrator && selectAllMatching && (
                    <div className="flex items-center justify-between rounded-lg border border-info/20 bg-info/5 px-4 py-2.5 text-sm">
                        <span>
                            All {deletableTotal} deletable uploads are selected.
                        </span>
                        <button
                            type="button"
                            className="font-medium text-info hover:underline"
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
                                        <td className="p-3 text-success">
                                            {batch.accepted_rows}
                                        </td>
                                        <td className="p-3 text-destructive">
                                            {batch.rejected_rows}
                                        </td>
                                        <td className="p-3 text-destructive">
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
                                                    <Button
                                                        asChild
                                                        size="sm"
                                                        variant="outline"
                                                    >
                                                        <Link
                                                            href={retry(
                                                                batch.id,
                                                            )}
                                                            method="post"
                                                            preserveScroll
                                                        >
                                                            <RotateCcw />
                                                            Retry processing
                                                        </Link>
                                                    </Button>
                                                )}
                                                {batch.processing_status ===
                                                    'completed' && (
                                                    <Dialog>
                                                        <DialogTrigger asChild>
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                variant="outline"
                                                            >
                                                                <RotateCcw />
                                                                Re-analyze
                                                            </Button>
                                                        </DialogTrigger>
                                                        <DialogContent>
                                                            <DialogTitle>
                                                                Re-analyze this
                                                                upload?
                                                            </DialogTitle>
                                                            <DialogDescription>
                                                                Duplicate rows
                                                                will be
                                                                re-checked
                                                                using the
                                                                latest rules.
                                                            </DialogDescription>
                                                            <DialogFooter>
                                                                <DialogClose
                                                                    asChild
                                                                >
                                                                    <Button variant="secondary">
                                                                        Cancel
                                                                    </Button>
                                                                </DialogClose>
                                                                <Button
                                                                    type="button"
                                                                    onClick={() =>
                                                                        router.post(
                                                                            reanalyze(
                                                                                batch.id,
                                                                            ),
                                                                            {},
                                                                            {
                                                                                preserveScroll: true,
                                                                            },
                                                                        )
                                                                    }
                                                                >
                                                                    Re-analyze
                                                                </Button>
                                                            </DialogFooter>
                                                        </DialogContent>
                                                    </Dialog>
                                                )}
                                                {auth.user.role ===
                                                    'administrator' &&
                                                    [
                                                        'completed',
                                                        'failed',
                                                    ].includes(
                                                        batch.processing_status,
                                                    ) && (
                                                        <Dialog>
                                                            <DialogTrigger
                                                                asChild
                                                            >
                                                                <Button
                                                                    type="button"
                                                                    size="sm"
                                                                    variant="destructive"
                                                                >
                                                                    <Trash2 />
                                                                    Delete
                                                                </Button>
                                                            </DialogTrigger>
                                                            <DialogContent>
                                                                <DialogTitle>
                                                                    Delete{' '}
                                                                    {
                                                                        batch.original_filename
                                                                    }
                                                                    ?
                                                                </DialogTitle>
                                                                <DialogDescription>
                                                                    Imported
                                                                    leads will
                                                                    be
                                                                    preserved,
                                                                    but this
                                                                    removes the
                                                                    upload from
                                                                    history and
                                                                    can't be
                                                                    undone.
                                                                </DialogDescription>
                                                                <DialogFooter>
                                                                    <DialogClose
                                                                        asChild
                                                                    >
                                                                        <Button variant="secondary">
                                                                            Cancel
                                                                        </Button>
                                                                    </DialogClose>
                                                                    <Button
                                                                        type="button"
                                                                        variant="destructive"
                                                                        onClick={() =>
                                                                            router.delete(
                                                                                destroy(
                                                                                    batch.id,
                                                                                ),
                                                                                {
                                                                                    preserveScroll: true,
                                                                                },
                                                                            )
                                                                        }
                                                                    >
                                                                        Delete
                                                                    </Button>
                                                                </DialogFooter>
                                                            </DialogContent>
                                                        </Dialog>
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
