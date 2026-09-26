import { Head, Link, router, usePage, usePoll } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    Copy,
    FileStack,
    Rows3,
    RotateCcw,
    Search,
    SlidersHorizontal,
    Trash2,
    Upload,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { KpiCard } from '@/components/bi-visuals';
import { percent } from '@/components/database-charts';
import { EmptyState } from '@/components/empty-state';
import { FilterBar } from '@/components/filter-bar';
import { HeaderActionsPortal } from '@/components/header-actions';
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
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { isAdministratorRole } from '@/lib/utils';
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
    duplicate_rows: number;
    processing_status: string;
    failure_message: string | null;
    created_at: string;
    user: { name: string } | null;
};
type Agent = { id: number; name: string };
type Summary = {
    uploads: number;
    rows: number;
    accepted: number;
    duplicates: number;
    rejected: number;
    errors: number;
};
type StatusTab = 'all' | 'in_progress' | 'completed' | 'failed';
const ALL_AGENTS = '__all__';
const STATUS_TABS: Array<[StatusTab, string]> = [
    ['all', 'All'],
    ['in_progress', 'In progress'],
    ['completed', 'Completed'],
    ['failed', 'Failed'],
];
const share = (value: number, total: number) =>
    total > 0 ? Math.round((1000 * value) / total) / 10 : null;

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
    summary,
    statusCounts,
}: {
    batches: {
        data: Batch[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    sort: string;
    filters: {
        agent_id: string;
        per_page: string;
        status: StatusTab;
        search: string;
    };
    deletableTotal: number;
    agents: Agent[];
    summary: Summary;
    statusCounts: Record<StatusTab, number>;
}) {
    const [searchTerm, setSearchTerm] = useState(filters.search);
    const { auth } = usePage<{ auth: Auth }>().props;
    usePoll(5000, { only: ['batches'] });
    const [selectedBatchIds, setSelectedBatchIds] = useState<number[]>([]);
    const [selectAllMatching, setSelectAllMatching] = useState(false);
    const isAdministrator = isAdministratorRole(auth.user.role);
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
            status: StatusTab;
            search: string;
        }>,
    ) => {
        router.get(
            index.url(),
            {
                sort,
                agent_id: filters.agent_id || undefined,
                per_page: filters.per_page,
                status: filters.status === 'all' ? undefined : filters.status,
                search: filters.search || undefined,
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
                <HeaderActionsPortal>
                    {isAdministrator && selectedCount > 0 && (
                        <Dialog>
                            <DialogTrigger asChild>
                                <Button
                                    type="button"
                                    size="sm"
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
                                    Imported leads will be preserved, but the
                                    raw files and row history will be removed.
                                    This can't be undone.
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
                    <Button asChild size="sm">
                        <Link href={create()}>
                            <Upload />
                            Upload CSV
                        </Link>
                    </Button>
                </HeaderActionsPortal>
                <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3 2xl:grid-cols-6">
                    <KpiCard
                        label="Uploads"
                        value={summary.uploads}
                        icon={FileStack}
                    />
                    <KpiCard
                        label="Rows uploaded"
                        value={summary.rows}
                        icon={Rows3}
                    />
                    <KpiCard
                        label="Accepted"
                        value={summary.accepted}
                        footnote={`${percent(share(summary.accepted, summary.rows))} of rows`}
                        icon={CheckCircle2}
                    />
                    <KpiCard
                        label="Duplicates"
                        value={summary.duplicates}
                        footnote={`${percent(share(summary.duplicates, summary.rows))} of rows`}
                        icon={Copy}
                    />
                    <KpiCard
                        label="Rejected"
                        value={summary.rejected}
                        footnote={`${percent(share(summary.rejected, summary.rows))} of rows`}
                        icon={XCircle}
                    />
                    <KpiCard
                        label="Errors"
                        value={summary.errors}
                        footnote={`${percent(share(summary.errors, summary.rows))} of rows`}
                        icon={AlertTriangle}
                    />
                </div>
                <nav
                    aria-label="Upload status"
                    className="flex gap-1 overflow-x-auto border-b"
                >
                    {STATUS_TABS.map(([value, label]) => (
                        <button
                            key={value}
                            type="button"
                            aria-current={
                                filters.status === value ? 'page' : undefined
                            }
                            onClick={() =>
                                updateQuery({
                                    status: value === 'all' ? undefined : value,
                                })
                            }
                            className={`-mb-px flex items-center gap-2 border-b-2 px-3 py-2.5 text-sm whitespace-nowrap ${filters.status === value ? 'border-primary font-semibold text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground'}`}
                        >
                            {label}
                            <span className="rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground tabular-nums">
                                {(statusCounts[value] ?? 0).toLocaleString()}
                            </span>
                        </button>
                    ))}
                </nav>
                <FilterBar
                    as="div"
                    icon={SlidersHorizontal}
                    label="Filters"
                    gridClassName="sm:grid-cols-2 lg:grid-cols-5"
                >
                    <div className="flex flex-col gap-1.5 sm:col-span-2">
                        <label
                            htmlFor="uploads-search"
                            className="text-xs text-muted-foreground"
                        >
                            Search
                        </label>
                        <div className="relative">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                id="uploads-search"
                                value={searchTerm}
                                onChange={(event) =>
                                    setSearchTerm(event.target.value)
                                }
                                onKeyDown={(event) => {
                                    if (event.key === 'Enter') {
                                        event.preventDefault();
                                        updateQuery({ search: searchTerm });
                                    }
                                }}
                                placeholder="Filename or batch code — press Enter"
                                className="pl-9"
                            />
                        </div>
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <label
                            htmlFor="uploads-per-page"
                            className="text-xs text-muted-foreground"
                        >
                            Show
                        </label>
                        <Select
                            value={filters.per_page}
                            onValueChange={(value) =>
                                updateQuery({ per_page: value })
                            }
                        >
                            <SelectTrigger
                                id="uploads-per-page"
                                className="w-full"
                            >
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
                    </div>
                    {agents.length > 0 && (
                        <div className="flex flex-col gap-1.5">
                            <label
                                htmlFor="uploads-agent"
                                className="text-xs text-muted-foreground"
                            >
                                Agent
                            </label>
                            <Select
                                value={filters.agent_id || ALL_AGENTS}
                                onValueChange={(value) =>
                                    updateQuery({
                                        agent_id:
                                            value === ALL_AGENTS ? '' : value,
                                    })
                                }
                            >
                                <SelectTrigger
                                    id="uploads-agent"
                                    className="w-full"
                                >
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
                        </div>
                    )}
                    <div className="flex flex-col gap-1.5">
                        <label
                            htmlFor="uploads-sort"
                            className="text-xs text-muted-foreground"
                        >
                            Sort by
                        </label>
                        <Select
                            value={sort}
                            onValueChange={(value) =>
                                updateQuery({ sort: value })
                            }
                        >
                            <SelectTrigger id="uploads-sort" className="w-full">
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
                    </div>
                </FilterBar>
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
                {batches.data.length ? (
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                {isAdministrator && (
                                    <TableHead className="w-12">
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
                                                deletableBatchIds.length === 0
                                            }
                                            aria-label="Select all deletable uploads on this page"
                                        />
                                    </TableHead>
                                )}
                                <TableHead>Batch</TableHead>
                                <TableHead>Owner</TableHead>
                                <TableHead>Date uploaded</TableHead>
                                <TableHead align="right">Rows</TableHead>
                                <TableHead>Acceptance</TableHead>
                                <TableHead align="right">Accepted</TableHead>
                                <TableHead align="right">Duplicates</TableHead>
                                <TableHead align="right">Rejected</TableHead>
                                <TableHead align="right">Errors</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {batches.data.map((batch) => (
                                <TableRow key={batch.id}>
                                    {isAdministrator && (
                                        <TableCell>
                                            <Checkbox
                                                checked={
                                                    selectAllMatching ||
                                                    selectedVisibleBatchIds.includes(
                                                        batch.id,
                                                    )
                                                }
                                                onCheckedChange={(checked) =>
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
                                        </TableCell>
                                    )}
                                    <TableCell>
                                        <Link
                                            href={show(batch.id)}
                                            className="font-medium hover:underline"
                                        >
                                            {batch.original_filename}
                                        </Link>
                                        <div className="text-xs whitespace-nowrap text-muted-foreground">
                                            {batch.batch_code}
                                        </div>
                                    </TableCell>
                                    <TableCell className="whitespace-nowrap">
                                        {batch.user?.name ?? 'Former user'}
                                    </TableCell>
                                    <TableCell className="whitespace-nowrap">
                                        {formatUploadedDate(batch.created_at)}
                                    </TableCell>
                                    <TableCell align="right">
                                        {batch.total_rows.toLocaleString()}
                                    </TableCell>
                                    <TableCell>
                                        {batch.total_rows > 0 &&
                                        batch.processing_status ===
                                            'completed' ? (
                                            <div className="flex min-w-28 items-center gap-2">
                                                <div
                                                    className="h-1.5 flex-1 overflow-hidden rounded-full bg-muted"
                                                    aria-hidden="true"
                                                >
                                                    <div
                                                        className="h-full rounded-full bg-success"
                                                        style={{
                                                            width: `${Math.min(100, share(batch.accepted_rows, batch.total_rows) ?? 0)}%`,
                                                        }}
                                                    />
                                                </div>
                                                <span className="w-11 text-right text-xs tabular-nums">
                                                    {percent(
                                                        share(
                                                            batch.accepted_rows,
                                                            batch.total_rows,
                                                        ),
                                                    )}
                                                </span>
                                            </div>
                                        ) : (
                                            <span className="text-xs text-muted-foreground">
                                                —
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell
                                        align="right"
                                        className="text-success"
                                    >
                                        {batch.accepted_rows.toLocaleString()}
                                    </TableCell>
                                    <TableCell
                                        align="right"
                                        className={
                                            batch.duplicate_rows > 0
                                                ? 'text-warning'
                                                : 'text-muted-foreground'
                                        }
                                    >
                                        {batch.duplicate_rows.toLocaleString()}
                                    </TableCell>
                                    <TableCell
                                        align="right"
                                        className={
                                            batch.rejected_rows > 0
                                                ? 'text-destructive'
                                                : 'text-muted-foreground'
                                        }
                                    >
                                        {batch.rejected_rows.toLocaleString()}
                                    </TableCell>
                                    <TableCell
                                        align="right"
                                        className={
                                            batch.error_rows > 0
                                                ? 'text-destructive'
                                                : 'text-muted-foreground'
                                        }
                                    >
                                        {batch.error_rows.toLocaleString()}
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge
                                            value={batch.processing_status}
                                        />
                                        {batch.processing_status === 'failed' &&
                                            batch.failure_message && (
                                                <p
                                                    className="mt-1 max-w-48 truncate text-xs text-destructive"
                                                    title={
                                                        batch.failure_message
                                                    }
                                                >
                                                    {batch.failure_message}
                                                </p>
                                            )}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex items-center gap-2">
                                            {batch.processing_status ===
                                                'pending' && (
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    variant="outline"
                                                    className="border-amber-500/30 bg-amber-500/10 text-amber-700 hover:bg-amber-500/15 hover:text-amber-800 dark:text-amber-300 dark:hover:text-amber-200"
                                                >
                                                    <Link
                                                        href={retry(batch.id)}
                                                        method="post"
                                                        preserveScroll
                                                    >
                                                        <RotateCcw />
                                                        Retry processing
                                                    </Link>
                                                </Button>
                                            )}
                                            {['completed', 'failed'].includes(
                                                batch.processing_status,
                                            ) && (
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    variant="outline"
                                                    className="border-sky-500/30 bg-sky-500/10 text-sky-700 hover:bg-sky-500/15 hover:text-sky-800 dark:text-sky-300 dark:hover:text-sky-200"
                                                >
                                                    <Link
                                                        href={reanalyze(
                                                            batch.id,
                                                        )}
                                                        method="post"
                                                        preserveScroll
                                                    >
                                                        <RotateCcw />
                                                        Re-analyze
                                                    </Link>
                                                </Button>
                                            )}
                                            {isAdministrator &&
                                                [
                                                    'completed',
                                                    'failed',
                                                ].includes(
                                                    batch.processing_status,
                                                ) && (
                                                    <Dialog>
                                                        <DialogTrigger asChild>
                                                            <Button
                                                                type="button"
                                                                size="icon"
                                                                variant="ghost"
                                                                className="size-8 text-muted-foreground hover:text-destructive"
                                                                aria-label={`Delete ${batch.original_filename}`}
                                                            >
                                                                <Trash2 />
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
                                                                Imported leads
                                                                will be
                                                                preserved, but
                                                                this removes the
                                                                upload from
                                                                history and
                                                                can't be undone.
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
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                ) : (
                    <div className="rounded-xl border bg-card">
                        <EmptyState
                            icon={Upload}
                            title={
                                filters.search || filters.status !== 'all'
                                    ? 'No uploads match these filters'
                                    : 'No upload batches yet'
                            }
                            description={
                                filters.search || filters.status !== 'all'
                                    ? 'Try another status tab or a different search.'
                                    : 'Upload a CSV to see its processing results here.'
                            }
                        />
                    </div>
                )}
                <Pagination links={batches.links} />
            </div>
        </>
    );
}
UploadIndex.layout = {
    breadcrumbs: [{ title: 'Upload History', href: index() }],
};
