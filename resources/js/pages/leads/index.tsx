import { Head, Link, router } from '@inertiajs/react';
import {
    Download,
    Mail,
    Pencil,
    Plus,
    Search,
    SlidersHorizontal,
    Trash2,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { EmptyState } from '@/components/empty-state';
import { FilterBar } from '@/components/filter-bar';
import { HeaderActionsPortal } from '@/components/header-actions';
import { Pagination } from '@/components/pagination';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
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
import {
    bulkDestroy,
    create,
    downloadCleaned,
    downloadRaw,
    edit,
    index,
} from '@/routes/leads';
type Lead = {
    id: number;
    lead_code: string;
    company_name: string;
    city: string | null;
    country: string | null;
    contact_person: string | null;
    email: string | null;
    status: string;
    source: string;
    validation_status: string;
    website_domain: string | null;
    upload_batch: { batch_code: string } | null;
    agent: { name: string } | null;
    can_update: boolean;
    email_replies_count: number;
    unread_email_replies_count: number;
};
type Agent = { id: number; name: string };
type Props = {
    leads: {
        data: Lead[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: Record<string, string>;
    canBulkDelete: boolean;
    agents: Agent[];
};
export default function LeadsIndex({
    leads,
    filters,
    canBulkDelete,
    agents,
}: Props) {
    const [selectedLeadIds, setSelectedLeadIds] = useState<number[]>([]);
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const [selectedDate, setSelectedDate] = useState(filters.date || '');
    const ALL_AGENTS = '__all__';
    const [agentFilter, setAgentFilter] = useState(
        filters.agent_id || ALL_AGENTS,
    );
    const visibleLeadIds = leads.data.map((lead) => lead.id);
    const selectedVisibleLeadIds = selectedLeadIds.filter((id) =>
        visibleLeadIds.includes(id),
    );
    const allVisibleLeadsSelected =
        visibleLeadIds.length > 0 &&
        visibleLeadIds.every((id) => selectedVisibleLeadIds.includes(id));

    const toggleAllVisibleLeads = (checked: boolean) => {
        setSelectedLeadIds(checked ? visibleLeadIds : []);
    };

    const toggleLead = (leadId: number, checked: boolean) => {
        setSelectedLeadIds((current) =>
            checked
                ? [
                      ...current.filter((id) => visibleLeadIds.includes(id)),
                      leadId,
                  ]
                : current.filter((id) => id !== leadId),
        );
    };

    const deleteSelectedLeads = () => {
        setDeleting(true);
        router.delete(bulkDestroy.url(), {
            data: { lead_ids: selectedVisibleLeadIds },
            preserveScroll: true,
            onSuccess: () => {
                setSelectedLeadIds([]);
                setDeleteDialogOpen(false);
            },
            onFinish: () => setDeleting(false),
        });
    };

    const search = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get(
            index.url(),
            Object.fromEntries(new FormData(event.currentTarget)),
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Leads" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <HeaderActionsPortal>
                    {canBulkDelete && selectedVisibleLeadIds.length > 0 && (
                        <Button
                            type="button"
                            size="sm"
                            variant="destructive"
                            onClick={() => setDeleteDialogOpen(true)}
                        >
                            <Trash2 />
                            Delete selected ({selectedVisibleLeadIds.length})
                        </Button>
                    )}
                    <Button type="submit" size="sm" form="leads-filters">
                        Apply filters
                    </Button>
                    <Button
                        asChild
                        size="sm"
                        variant="outline"
                        className="border-sky-500/30 bg-sky-500/10 text-sky-700 hover:bg-sky-500/15 hover:text-sky-800 dark:text-sky-300 dark:hover:text-sky-200"
                    >
                        <a
                            href={downloadRaw.url({
                                query: {
                                    date_from: selectedDate || undefined,
                                    date_to: selectedDate || undefined,
                                },
                            })}
                            download
                        >
                            <Download />
                            Download raw CSV
                        </a>
                    </Button>
                    <Button
                        asChild
                        size="sm"
                        variant="outline"
                        className="border-emerald-500/30 bg-emerald-500/10 text-emerald-700 hover:bg-emerald-500/15 hover:text-emerald-800 dark:text-emerald-300 dark:hover:text-emerald-200"
                    >
                        <a
                            href={downloadCleaned.url({
                                query: {
                                    date_from: selectedDate || undefined,
                                    date_to: selectedDate || undefined,
                                },
                            })}
                            download
                            onClick={() =>
                                toast.success(
                                    'Cleaned CSV download started with dataset cleaning.',
                                )
                            }
                        >
                            <Download />
                            Download cleaned CSV
                        </a>
                    </Button>
                    <Button asChild size="sm">
                        <Link href={create()}>
                            <Plus />
                            Add lead
                        </Link>
                    </Button>
                </HeaderActionsPortal>
                <FilterBar
                    as="form"
                    id="leads-filters"
                    onSubmit={search}
                    icon={SlidersHorizontal}
                    label="Filters"
                    gridClassName="sm:grid-cols-2 lg:grid-cols-[repeat(auto-fit,minmax(160px,1fr))]"
                    hint="Search matches company, contact, and email. Lead date filters by the date recorded on each lead, not when it was uploaded."
                >
                    <div className="flex flex-col gap-1.5 sm:col-span-2 lg:col-span-2">
                        <label
                            htmlFor="leads-search"
                            className="text-xs text-muted-foreground"
                        >
                            Search
                        </label>
                        <div className="relative">
                            <Search className="absolute top-2.5 left-3 size-4 text-muted-foreground" />
                            <Input
                                id="leads-search"
                                name="search"
                                defaultValue={filters.search}
                                placeholder="Search leads…"
                                className="pl-9"
                            />
                        </div>
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <label
                            htmlFor="leads-per-page"
                            className="text-xs text-muted-foreground"
                        >
                            Per page
                        </label>
                        <Select
                            name="per_page"
                            defaultValue={filters.per_page || '10'}
                        >
                            <SelectTrigger
                                id="leads-per-page"
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
                                htmlFor="leads-agent"
                                className="text-xs text-muted-foreground"
                            >
                                Agent
                            </label>
                            <input
                                type="hidden"
                                name="agent_id"
                                value={
                                    agentFilter === ALL_AGENTS
                                        ? ''
                                        : agentFilter
                                }
                            />
                            <Select
                                value={agentFilter}
                                onValueChange={setAgentFilter}
                            >
                                <SelectTrigger
                                    id="leads-agent"
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
                    {agents.length > 0 && (
                        <div className="flex flex-col gap-1.5">
                            <label
                                htmlFor="leads-sort"
                                className="text-xs text-muted-foreground"
                            >
                                Sort by
                            </label>
                            <Select
                                name="sort"
                                defaultValue={filters.sort || 'created_at'}
                            >
                                <SelectTrigger
                                    id="leads-sort"
                                    className="w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="created_at">
                                        Date added
                                    </SelectItem>
                                    <SelectItem value="agent">Agent</SelectItem>
                                    <SelectItem value="company_name">
                                        Company
                                    </SelectItem>
                                    <SelectItem value="status">
                                        Status
                                    </SelectItem>
                                    <SelectItem value="source">
                                        Source
                                    </SelectItem>
                                    <SelectItem value="country">
                                        Country
                                    </SelectItem>
                                    <SelectItem value="city">City</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    )}
                    {agents.length > 0 && (
                        <div className="flex flex-col gap-1.5">
                            <label
                                htmlFor="leads-direction"
                                className="text-xs text-muted-foreground"
                            >
                                Direction
                            </label>
                            <Select
                                name="direction"
                                defaultValue={filters.direction || 'desc'}
                            >
                                <SelectTrigger
                                    id="leads-direction"
                                    className="w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="desc">
                                        Descending
                                    </SelectItem>
                                    <SelectItem value="asc">
                                        Ascending
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    )}
                    <div className="flex flex-col gap-1.5">
                        <label
                            htmlFor="leads-date"
                            className="text-xs text-muted-foreground"
                        >
                            Lead date
                        </label>
                        <Input
                            id="leads-date"
                            name="date"
                            type="date"
                            value={selectedDate}
                            onChange={(event) =>
                                setSelectedDate(event.target.value)
                            }
                        />
                    </div>
                </FilterBar>
                {leads.data.length ? (
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                {canBulkDelete && (
                                    <TableHead className="w-12">
                                        <Checkbox
                                            checked={allVisibleLeadsSelected}
                                            onCheckedChange={(checked) =>
                                                toggleAllVisibleLeads(
                                                    checked === true,
                                                )
                                            }
                                            aria-label="Select all leads on this page"
                                        />
                                    </TableHead>
                                )}
                                <TableHead>Company</TableHead>
                                <TableHead>Location</TableHead>
                                <TableHead>Contact</TableHead>
                                <TableHead>Owner</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Source</TableHead>
                                <TableHead>Replies</TableHead>
                                <TableHead align="right">Action</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {leads.data.map((lead) => (
                                <TableRow key={lead.id}>
                                    {canBulkDelete && (
                                        <TableCell>
                                            <Checkbox
                                                checked={selectedVisibleLeadIds.includes(
                                                    lead.id,
                                                )}
                                                onCheckedChange={(checked) =>
                                                    toggleLead(
                                                        lead.id,
                                                        checked === true,
                                                    )
                                                }
                                                aria-label={`Select ${lead.company_name}`}
                                            />
                                        </TableCell>
                                    )}
                                    <TableCell>
                                        <Link
                                            href={edit(lead.id)}
                                            className="font-medium hover:underline"
                                        >
                                            {lead.company_name}
                                        </Link>
                                        <div className="text-xs text-muted-foreground">
                                            {lead.website_domain ||
                                                lead.lead_code}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        {[lead.city, lead.country]
                                            .filter(Boolean)
                                            .join(', ') || '—'}
                                    </TableCell>
                                    <TableCell>
                                        {lead.contact_person || '—'}
                                        <div className="text-xs text-muted-foreground">
                                            {lead.email}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        {lead.agent?.name || 'Unassigned'}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex flex-col items-start gap-1">
                                            <StatusBadge value={lead.status} />
                                            <span className="text-xs text-muted-foreground">
                                                {lead.validation_status}
                                            </span>
                                        </div>
                                    </TableCell>
                                    <TableCell className="capitalize">
                                        {lead.source}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex items-center gap-2">
                                            <Mail className="size-4 text-muted-foreground" />
                                            <span className="font-medium">
                                                {lead.email_replies_count}
                                            </span>
                                            {lead.unread_email_replies_count >
                                                0 && (
                                                <span className="rounded-full bg-info/15 px-2 py-0.5 text-xs font-semibold text-info">
                                                    {
                                                        lead.unread_email_replies_count
                                                    }{' '}
                                                    new
                                                </span>
                                            )}
                                        </div>
                                    </TableCell>
                                    <TableCell align="right">
                                        <div className="flex justify-end gap-2">
                                            {lead.can_update && (
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    variant="outline"
                                                >
                                                    <Link href={edit(lead.id)}>
                                                        <Pencil className="size-3.5" />
                                                        Edit
                                                    </Link>
                                                </Button>
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
                            icon={Users}
                            title="No leads match your filters"
                            description="Try a broader search or clear a filter."
                        />
                    </div>
                )}
                <Pagination links={leads.links} />
            </div>

            <Dialog open={deleteDialogOpen} onOpenChange={setDeleteDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete selected leads?</DialogTitle>
                        <DialogDescription>
                            {selectedVisibleLeadIds.length} selected lead(s)
                            will be removed from the active lead list. This
                            action is recorded in the audit log.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setDeleteDialogOpen(false)}
                            disabled={deleting}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={deleteSelectedLeads}
                            disabled={
                                deleting || selectedVisibleLeadIds.length === 0
                            }
                        >
                            <Trash2 />
                            {deleting ? 'Deleting…' : 'Delete leads'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
LeadsIndex.layout = { breadcrumbs: [{ title: 'Leads', href: index() }] };
