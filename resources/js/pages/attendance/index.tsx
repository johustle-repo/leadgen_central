import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    CalendarCheck2,
    CalendarOff,
    Download,
    FileJson,
    QrCode as QrCodeIcon,
    SlidersHorizontal,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { AttendanceEntryDialog } from '@/components/attendance-entry-dialog';
import { EmptyState } from '@/components/empty-state';
import { FilterBar } from '@/components/filter-bar';
import InputError from '@/components/input-error';
import { Pagination } from '@/components/pagination';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
import { useAttendanceEntryEditor } from '@/hooks/use-attendance-entry-editor';
import {
    formatAttendanceDateTime,
    formatAttendanceTime,
} from '@/lib/attendance-time';
import { downloadDataUrl, drawIdentityCard } from '@/lib/qr';
import { cn } from '@/lib/utils';
import {
    importMethod as importAttendance,
    index,
    scanner,
    updateEntry,
} from '@/routes/attendance';
import dateStatus from '@/routes/attendance/date-status';
import type {
    AttendanceCalendarDay,
    AttendanceDailyMonitorRow,
    AttendanceEntryType,
    AttendanceManualEntryAgent,
    AttendanceRecord,
    AttendanceUser,
} from '@/types';

const ALL_ENTRY_TYPES = '__all__';

type Props = {
    users: AttendanceUser[];
    records: {
        data: AttendanceRecord[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: { search?: string; entry_type?: string; date?: string };
    calendarWeek: AttendanceCalendarDay[];
    dailyMonitor: AttendanceDailyMonitorRow[];
    monitorStats: {
        summary_rows: number;
        unique_users: number;
        team_size: number;
    };
    monitorDate: string;
    monitorSearch: string;
    agentsForManualEntry: AttendanceManualEntryAgent[];
};

export default function AttendanceIndex({
    users,
    records,
    filters,
    calendarWeek,
    dailyMonitor,
    monitorStats,
    monitorDate,
    monitorSearch,
    agentsForManualEntry,
}: Props) {
    const { flash } = usePage().props;
    const editor = useAttendanceEntryEditor();
    const importForm = useForm<{ files: File[] }>({ files: [] });
    const manualForm = useForm<{
        user_id: string;
        entry_type: AttendanceEntryType;
        date: string;
        time: string;
    }>({
        user_id: '',
        entry_type: 'time_in',
        date: monitorDate,
        time: '',
    });
    const [entryTypeFilter, setEntryTypeFilter] = useState(
        filters.entry_type || ALL_ENTRY_TYPES,
    );
    const [importErrorsDismissed, setImportErrorsDismissed] = useState(false);
    const [activeTab, setActiveTab] = useState<'monitor' | 'record'>(
        'monitor',
    );

    function applyFilters(overrides: Record<string, string>) {
        router.get(
            index.url(),
            {
                search: filters.search ?? '',
                entry_type: filters.entry_type ?? '',
                date: filters.date ?? '',
                monitor_search: monitorSearch,
                monitor_date: monitorDate,
                ...overrides,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    function filterRecords(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        applyFilters(
            Object.fromEntries(
                new FormData(event.currentTarget),
            ) as Record<string, string>,
        );
    }

    function filterMonitor(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        applyFilters(
            Object.fromEntries(
                new FormData(event.currentTarget),
            ) as Record<string, string>,
        );
    }

    function selectCalendarDate(date: string) {
        applyFilters({ monitor_date: date });
    }

    function markDateStatus(type: 'rest_day' | 'regular') {
        router.post(
            dateStatus.store.url(),
            { date: monitorDate, type },
            { preserveScroll: true },
        );
    }

    function clearDateStatus() {
        router.delete(dateStatus.destroy.url({ date: monitorDate }), {
            preserveScroll: true,
        });
    }

    async function handleDownloadCard(user: AttendanceUser) {
        const dataUrl = await drawIdentityCard(
            {
                id: user.id,
                name: user.name,
                roleLabel: user.role.replaceAll('_', ' '),
                team: user.team,
            },
            user.qr_value,
        );
        downloadDataUrl(
            dataUrl,
            `${user.name.replace(/\s+/g, '-').toLowerCase()}-attendance-id.png`,
        );
    }

    function submitImport(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setImportErrorsDismissed(false);
        importForm.post(importAttendance.url(), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => importForm.reset('files'),
        });
    }

    function submitManualEntry(event: React.FormEvent) {
        event.preventDefault();

        if (!manualForm.data.user_id || !manualForm.data.time) {
            return;
        }

        manualForm.transform((data) => ({
            recorded_at: `${data.date}T${data.time}:00`,
        }));
        manualForm.put(
            updateEntry.url({
                user: Number(manualForm.data.user_id),
                date: manualForm.data.date,
                entryType: manualForm.data.entry_type,
            }),
            {
                preserveScroll: true,
                onSuccess: () => manualForm.reset('time'),
            },
        );
    }

    const manualEntryError = Object.values(manualForm.errors)[0];

    const importErrors =
        !importErrorsDismissed && flash.importErrors
            ? flash.importErrors
            : [];

    const selectedCalendarDay = calendarWeek.find(
        (day) => day.date === monitorDate,
    );

    return (
        <>
            <Head title="Attendance" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">Attendance</h1>
                        <p className="text-sm text-muted-foreground">
                            One row per date per user, with a calendar view
                            for marking rest days and holidays.
                        </p>
                    </div>
                    <Button asChild>
                        <a href={scanner().url}>
                            <QrCodeIcon />
                            QR Scanner
                        </a>
                    </Button>
                </div>

                {importErrors.length > 0 && (
                    <div className="flex flex-col gap-2 rounded-xl border border-warning/30 bg-warning/10 p-4 text-sm">
                        <div className="flex items-center justify-between gap-3">
                            <div className="flex items-center gap-2 font-medium text-warning">
                                <AlertTriangle className="size-4 shrink-0" />
                                {importErrors.length} row
                                {importErrors.length === 1 ? '' : 's'} skipped
                                during import
                            </div>
                            <button
                                type="button"
                                onClick={() => setImportErrorsDismissed(true)}
                                className="text-muted-foreground hover:text-foreground"
                                aria-label="Dismiss"
                            >
                                <X className="size-4" />
                            </button>
                        </div>
                        <ul className="ml-6 list-disc space-y-0.5 text-muted-foreground">
                            {importErrors.map((error, errorIndex) => (
                                <li key={errorIndex}>{error}</li>
                            ))}
                        </ul>
                    </div>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Attendance table</CardTitle>
                        <CardDescription>
                            One row per date per user with separate Time In
                            and Time Out values, plus a late check based on
                            office hours.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-6">
                        <FilterBar
                            as="form"
                            onSubmit={filterMonitor}
                            icon={SlidersHorizontal}
                            label="Filters"
                            gridClassName="sm:grid-cols-3"
                        >
                            <div className="flex flex-col gap-1.5 sm:col-span-2">
                                <label
                                    htmlFor="monitor-search"
                                    className="text-xs text-muted-foreground"
                                >
                                    Search
                                </label>
                                <Input
                                    id="monitor-search"
                                    name="monitor_search"
                                    defaultValue={monitorSearch}
                                    placeholder="Search ID, name, or email…"
                                />
                            </div>
                            <div className="flex flex-col gap-1.5">
                                <label
                                    htmlFor="monitor-date"
                                    className="text-xs text-muted-foreground"
                                >
                                    Date
                                </label>
                                <Input
                                    id="monitor-date"
                                    type="date"
                                    name="monitor_date"
                                    defaultValue={monitorDate}
                                />
                            </div>
                            <div className="flex flex-col justify-end sm:col-span-3">
                                <Button
                                    type="submit"
                                    variant="secondary"
                                    className="sm:w-fit"
                                >
                                    Apply filters
                                </Button>
                            </div>
                        </FilterBar>

                        <div className="rounded-xl border p-4">
                            <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p className="font-medium">
                                        Calendar week
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Week starts on Sunday. Select any
                                        date, then mark it as a holiday or
                                        rest day.
                                    </p>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            markDateStatus('rest_day')
                                        }
                                    >
                                        <CalendarOff />
                                        Mark rest day
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            markDateStatus('regular')
                                        }
                                    >
                                        <CalendarCheck2 />
                                        Mark holiday
                                    </Button>
                                    {selectedCalendarDay?.is_real && (
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="ghost"
                                            onClick={clearDateStatus}
                                        >
                                            Clear
                                        </Button>
                                    )}
                                </div>
                            </div>
                            <div className="grid grid-cols-4 gap-2 sm:grid-cols-7">
                                {calendarWeek.map((day) => (
                                    <button
                                        key={day.date}
                                        type="button"
                                        onClick={() =>
                                            selectCalendarDate(day.date)
                                        }
                                        className={cn(
                                            'rounded-lg border p-2 text-center transition-colors hover:bg-accent',
                                            day.date === monitorDate &&
                                                'border-cyan-500 ring-1 ring-cyan-500',
                                        )}
                                    >
                                        <p className="text-[10px] tracking-wide text-muted-foreground uppercase">
                                            {new Date(
                                                `${day.date}T00:00:00`,
                                            ).toLocaleDateString(undefined, {
                                                weekday: 'short',
                                            })}
                                        </p>
                                        <p className="text-lg font-semibold">
                                            {day.date.slice(-2)}
                                        </p>
                                        <StatusBadge value={day.label} />
                                    </button>
                                ))}
                            </div>
                        </div>

                        <div className="grid gap-3 sm:grid-cols-3">
                            <div className="rounded-lg border p-3">
                                <p className="text-xs text-muted-foreground">
                                    Summary rows
                                </p>
                                <p className="text-2xl font-semibold">
                                    {monitorStats.summary_rows}
                                </p>
                            </div>
                            <div className="rounded-lg border p-3">
                                <p className="text-xs text-muted-foreground">
                                    Unique users
                                </p>
                                <p className="text-2xl font-semibold">
                                    {monitorStats.unique_users}
                                </p>
                            </div>
                            <div className="rounded-lg border p-3">
                                <p className="text-xs text-muted-foreground">
                                    Team size
                                </p>
                                <p className="text-2xl font-semibold">
                                    {monitorStats.team_size}
                                </p>
                            </div>
                        </div>

                        <div className="flex gap-2">
                            <Button
                                type="button"
                                variant={
                                    activeTab === 'monitor'
                                        ? 'default'
                                        : 'outline'
                                }
                                onClick={() => setActiveTab('monitor')}
                            >
                                Attendance Monitor
                            </Button>
                            <Button
                                type="button"
                                variant={
                                    activeTab === 'record'
                                        ? 'default'
                                        : 'outline'
                                }
                                onClick={() => setActiveTab('record')}
                            >
                                Record Attendance
                            </Button>
                        </div>

                        {activeTab === 'monitor' ? (
                            <div>
                                <p className="mb-3 text-sm text-muted-foreground">
                                    Super admin accounts can adjust existing
                                    Time In and Time Out records, add a
                                    missing Time Out, and switch to the
                                    Record Attendance tab for new manual
                                    entries.
                                </p>
                                {dailyMonitor.length ? (
                                    <Table>
                                        <TableHeader>
                                            <TableRow className="hover:bg-transparent">
                                                <TableHead>Staff</TableHead>
                                                <TableHead>
                                                    Time In
                                                </TableHead>
                                                <TableHead>
                                                    Time Out
                                                </TableHead>
                                                <TableHead>
                                                    Total Hours
                                                </TableHead>
                                                <TableHead>Status</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {dailyMonitor.map((row) => (
                                                <TableRow key={row.user_id}>
                                                    <TableCell>
                                                        <p>{row.user_name}</p>
                                                        {row.employee_code && (
                                                            <p className="text-xs text-muted-foreground">
                                                                {
                                                                    row.employee_code
                                                                }
                                                            </p>
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        <button
                                                            type="button"
                                                            className="hover:underline"
                                                            onClick={() =>
                                                                editor.openEditor(
                                                                    row.user_id,
                                                                    row.user_name,
                                                                    monitorDate,
                                                                    'time_in',
                                                                    row.time_in,
                                                                )
                                                            }
                                                        >
                                                            {formatAttendanceTime(
                                                                row.time_in,
                                                            )}
                                                        </button>
                                                    </TableCell>
                                                    <TableCell>
                                                        <button
                                                            type="button"
                                                            className="hover:underline"
                                                            onClick={() =>
                                                                editor.openEditor(
                                                                    row.user_id,
                                                                    row.user_name,
                                                                    monitorDate,
                                                                    'time_out',
                                                                    row.time_out,
                                                                )
                                                            }
                                                        >
                                                            {formatAttendanceTime(
                                                                row.time_out,
                                                            )}
                                                        </button>
                                                    </TableCell>
                                                    <TableCell>
                                                        {
                                                            row.worked_minutes_label
                                                        }
                                                    </TableCell>
                                                    <TableCell>
                                                        <StatusBadge
                                                            value={
                                                                row.holiday_label ??
                                                                row.status
                                                            }
                                                        />
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                ) : (
                                    <EmptyState
                                        icon={QrCodeIcon}
                                        title="No attendance records found for the selected filters."
                                    />
                                )}
                            </div>
                        ) : (
                            <div className="grid gap-6 lg:grid-cols-2">
                                <div>
                                    <p className="font-medium">
                                        Record attendance
                                    </p>
                                    <p className="mb-4 text-sm text-muted-foreground">
                                        Manually add a Time In or Time Out
                                        record for an active agent. Time Out
                                        entries still require an existing
                                        Time In on the same date.
                                    </p>
                                    <form
                                        onSubmit={submitManualEntry}
                                        className="grid gap-4"
                                    >
                                        <div>
                                            <Label htmlFor="manual-agent">
                                                Agent
                                            </Label>
                                            <Select
                                                value={
                                                    manualForm.data.user_id
                                                }
                                                onValueChange={(value) =>
                                                    manualForm.setData(
                                                        'user_id',
                                                        value,
                                                    )
                                                }
                                            >
                                                <SelectTrigger
                                                    id="manual-agent"
                                                    className="mt-2 w-full"
                                                >
                                                    <SelectValue placeholder="Select an agent" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {agentsForManualEntry.map(
                                                        (agent) => (
                                                            <SelectItem
                                                                key={agent.id}
                                                                value={String(
                                                                    agent.id,
                                                                )}
                                                            >
                                                                {agent.name}
                                                                {agent.employee_code
                                                                    ? ` (${agent.employee_code})`
                                                                    : ''}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <div>
                                                <Label htmlFor="manual-entry-type">
                                                    Entry type
                                                </Label>
                                                <Select
                                                    value={
                                                        manualForm.data
                                                            .entry_type
                                                    }
                                                    onValueChange={(value) =>
                                                        manualForm.setData(
                                                            'entry_type',
                                                            value as AttendanceEntryType,
                                                        )
                                                    }
                                                >
                                                    <SelectTrigger
                                                        id="manual-entry-type"
                                                        className="mt-2 w-full"
                                                    >
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="time_in">
                                                            Time In
                                                        </SelectItem>
                                                        <SelectItem value="time_out">
                                                            Time Out
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <div>
                                                <Label htmlFor="manual-date">
                                                    Date
                                                </Label>
                                                <Input
                                                    id="manual-date"
                                                    type="date"
                                                    className="mt-2"
                                                    value={
                                                        manualForm.data.date
                                                    }
                                                    onChange={(event) =>
                                                        manualForm.setData(
                                                            'date',
                                                            event.target
                                                                .value,
                                                        )
                                                    }
                                                />
                                            </div>
                                        </div>
                                        <div>
                                            <Label htmlFor="manual-time">
                                                Time
                                            </Label>
                                            <Input
                                                id="manual-time"
                                                type="time"
                                                className="mt-2"
                                                value={manualForm.data.time}
                                                onChange={(event) =>
                                                    manualForm.setData(
                                                        'time',
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                        </div>
                                        <InputError
                                            message={manualEntryError}
                                        />
                                        <div className="rounded-lg border bg-muted/30 p-3 text-xs text-muted-foreground">
                                            <p className="mb-1 font-medium text-foreground">
                                                Quick reminders
                                            </p>
                                            Only active agents are listed
                                            here. A Time In must happen
                                            before a Time Out on the same
                                            date, and each date only allows
                                            one Time In and one Time Out per
                                            agent.
                                        </div>
                                        <div className="flex gap-2">
                                            <Button
                                                type="submit"
                                                disabled={
                                                    manualForm.processing ||
                                                    !manualForm.data
                                                        .user_id ||
                                                    !manualForm.data.time
                                                }
                                            >
                                                Record attendance
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() =>
                                                    manualForm.reset()
                                                }
                                            >
                                                Reset form
                                            </Button>
                                        </div>
                                    </form>
                                </div>

                                <div>
                                    <p className="font-medium">
                                        Import attendance history
                                    </p>
                                    <p className="mb-4 text-sm text-muted-foreground">
                                        Upload one or more JSON exports from
                                        a previous system. Rows are matched
                                        to staff by email or name;
                                        re-importing the same file is safe.
                                    </p>
                                    <form
                                        onSubmit={submitImport}
                                        className="grid gap-3"
                                    >
                                        <Input
                                            type="file"
                                            accept="application/json,.json"
                                            multiple
                                            onChange={(event) =>
                                                importForm.setData(
                                                    'files',
                                                    Array.from(
                                                        event.target.files ??
                                                            [],
                                                    ),
                                                )
                                            }
                                        />
                                        {importForm.data.files.length >
                                            0 && (
                                            <p className="text-xs text-muted-foreground">
                                                {
                                                    importForm.data.files
                                                        .length
                                                }{' '}
                                                file
                                                {importForm.data.files
                                                    .length === 1
                                                    ? ''
                                                    : 's'}{' '}
                                                selected:{' '}
                                                {importForm.data.files
                                                    .map((file) => file.name)
                                                    .join(', ')}
                                            </p>
                                        )}
                                        <InputError
                                            message={
                                                importForm.errors.files
                                            }
                                        />
                                        <Button
                                            type="submit"
                                            variant="outline"
                                            disabled={
                                                importForm.processing ||
                                                importForm.data.files
                                                    .length === 0
                                            }
                                        >
                                            <FileJson />
                                            {importForm.processing
                                                ? 'Importing…'
                                                : 'Import records'}
                                        </Button>
                                    </form>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Agent profiles</CardTitle>
                        <CardDescription>
                            Download a printable identity card for each user.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {users.length ? (
                            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                {users.map((user) => (
                                    <div
                                        key={user.id}
                                        className={cn(
                                            'flex items-center justify-between gap-3 rounded-lg border p-3',
                                            user.status !== 'active' &&
                                                'opacity-60',
                                        )}
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate font-medium">
                                                {user.name}
                                            </p>
                                            <p className="truncate text-xs text-muted-foreground">
                                                {user.role.replaceAll(
                                                    '_',
                                                    ' ',
                                                )}
                                            </p>
                                        </div>
                                        <Button
                                            type="button"
                                            size="icon"
                                            variant="ghost"
                                            onClick={() =>
                                                handleDownloadCard(user)
                                            }
                                        >
                                            <Download />
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <EmptyState
                                icon={QrCodeIcon}
                                title="No staff yet"
                            />
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Recent scans</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        <FilterBar
                            as="form"
                            onSubmit={filterRecords}
                            icon={SlidersHorizontal}
                            label="Filters"
                            gridClassName="sm:grid-cols-3"
                        >
                            <div className="flex flex-col gap-1.5">
                                <label
                                    htmlFor="attendance-search"
                                    className="text-xs text-muted-foreground"
                                >
                                    Staff
                                </label>
                                <Input
                                    id="attendance-search"
                                    name="search"
                                    defaultValue={filters.search}
                                    placeholder="Search staff…"
                                />
                            </div>
                            <div className="flex flex-col gap-1.5">
                                <label
                                    htmlFor="attendance-entry-type"
                                    className="text-xs text-muted-foreground"
                                >
                                    Entry
                                </label>
                                <input
                                    type="hidden"
                                    name="entry_type"
                                    value={
                                        entryTypeFilter === ALL_ENTRY_TYPES
                                            ? ''
                                            : entryTypeFilter
                                    }
                                />
                                <Select
                                    value={entryTypeFilter}
                                    onValueChange={setEntryTypeFilter}
                                >
                                    <SelectTrigger id="attendance-entry-type">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={ALL_ENTRY_TYPES}>
                                            All entries
                                        </SelectItem>
                                        <SelectItem value="time_in">
                                            Time In
                                        </SelectItem>
                                        <SelectItem value="time_out">
                                            Time Out
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="flex flex-col gap-1.5">
                                <label
                                    htmlFor="attendance-date"
                                    className="text-xs text-muted-foreground"
                                >
                                    Date
                                </label>
                                <Input
                                    id="attendance-date"
                                    type="date"
                                    name="date"
                                    defaultValue={filters.date}
                                />
                            </div>
                            <div className="flex flex-col justify-end sm:col-span-3">
                                <Button
                                    type="submit"
                                    variant="secondary"
                                    className="sm:w-fit"
                                >
                                    Apply filters
                                </Button>
                            </div>
                        </FilterBar>

                        {records.data.length ? (
                            <Table>
                                <TableHeader>
                                    <TableRow className="hover:bg-transparent">
                                        <TableHead>Staff</TableHead>
                                        <TableHead>Entry</TableHead>
                                        <TableHead>Recorded at</TableHead>
                                        <TableHead>Status</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {records.data.map((record) => (
                                        <TableRow key={record.id}>
                                            <TableCell>
                                                {record.user_name ??
                                                    'Deleted user'}
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge
                                                    value={record.entry_type}
                                                />
                                            </TableCell>
                                            <TableCell className="whitespace-nowrap text-muted-foreground">
                                                {formatAttendanceDateTime(
                                                    record.recorded_at,
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {record.status && (
                                                    <StatusBadge
                                                        value={record.status}
                                                    />
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        ) : (
                            <EmptyState
                                icon={QrCodeIcon}
                                title="No attendance recorded yet"
                            />
                        )}
                        <Pagination links={records.links} />
                    </CardContent>
                </Card>
            </div>

            <AttendanceEntryDialog
                editingCell={editor.editingCell}
                editForm={editor.editForm}
                onSubmit={editor.submitEdit}
                onClear={editor.clearEntry}
                onClose={editor.closeEditor}
            />
        </>
    );
}

AttendanceIndex.layout = {
    breadcrumbs: [{ title: 'Attendance', href: index() }],
};
