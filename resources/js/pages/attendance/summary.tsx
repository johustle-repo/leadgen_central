import { Head, router, useForm } from '@inertiajs/react';
import {
    ChevronDown,
    ChevronRight,
    Clock3,
    FileDown,
    LogIn,
    QrCode as QrCodeIcon,
    Users as UsersIcon,
} from 'lucide-react';
import { Fragment, useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { StatTile } from '@/components/stat-tile';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    exportExcel,
    exportPdf,
    index,
    summary as summaryRoute,
    updateEntry,
} from '@/routes/attendance';
import type { AttendanceEntryType, AttendanceMonthlyAgent } from '@/types';

function formatMinutes(minutes: number): string {
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;

    return `${hours}h ${String(mins).padStart(2, '0')}m`;
}

function formatTime(iso: string | null): string {
    return iso
        ? new Date(iso).toLocaleTimeString([], {
              hour: '2-digit',
              minute: '2-digit',
          })
        : '—';
}

function toDatetimeLocalValue(iso: string | null): string {
    if (!iso) {
        return '';
    }

    const date = new Date(iso);
    const pad = (value: number) => String(value).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function monthLabel(month: string): string {
    return new Date(`${month}-01T00:00:00`).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'long',
    });
}

type EditingCell = {
    userId: number;
    userName: string;
    date: string;
    entryType: AttendanceEntryType;
};

type Props = {
    summary: {
        total_records: number;
        time_ins_today: number;
        late_today: number;
        active_staff: number;
    };
    monthlyAttendance: AttendanceMonthlyAgent[];
    selectedMonth: string;
};

export default function AttendanceSummary({
    summary,
    monthlyAttendance,
    selectedMonth,
}: Props) {
    const editForm = useForm<{ recorded_at: string }>({ recorded_at: '' });
    const [expandedUserId, setExpandedUserId] = useState<number | null>(null);
    const [editingCell, setEditingCell] = useState<EditingCell | null>(null);

    function changeMonth(month: string) {
        router.get(
            summaryRoute.url(),
            { month },
            { preserveState: true, preserveScroll: true },
        );
    }

    function openEditor(
        userId: number,
        userName: string,
        date: string,
        entryType: AttendanceEntryType,
        currentValue: string | null,
    ) {
        editForm.clearErrors();
        editForm.setData('recorded_at', toDatetimeLocalValue(currentValue));
        setEditingCell({ userId, userName, date, entryType });
    }

    function submitEdit(event: React.FormEvent) {
        event.preventDefault();

        if (!editingCell) {
            return;
        }

        editForm.put(
            updateEntry.url({
                user: editingCell.userId,
                date: editingCell.date,
                entryType: editingCell.entryType,
            }),
            { preserveScroll: true, onSuccess: () => setEditingCell(null) },
        );
    }

    function clearEntry() {
        if (!editingCell) {
            return;
        }

        router.put(
            updateEntry.url({
                user: editingCell.userId,
                date: editingCell.date,
                entryType: editingCell.entryType,
            }),
            { recorded_at: '' },
            { preserveScroll: true, onSuccess: () => setEditingCell(null) },
        );
    }

    const teamSummary = monthlyAttendance.map((agent) => {
        const attendanceDays = agent.days.filter(
            (day) => day.time_in !== null,
        ).length;
        const logCount = agent.days.reduce(
            (sum, day) =>
                sum + (day.time_in ? 1 : 0) + (day.time_out ? 1 : 0),
            0,
        );

        return { ...agent, attendanceDays, logCount };
    });

    return (
        <>
            <Head title="Attendance Summary" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatTile
                        label="Total records"
                        value={summary.total_records}
                        icon={QrCodeIcon}
                        tone="text-info"
                    />
                    <StatTile
                        label="Time-ins today"
                        value={summary.time_ins_today}
                        icon={LogIn}
                        tone="text-success"
                    />
                    <StatTile
                        label="Late today"
                        value={summary.late_today}
                        icon={Clock3}
                        tone="text-warning"
                    />
                    <StatTile
                        label="Active staff"
                        value={summary.active_staff}
                        icon={UsersIcon}
                        tone="text-primary"
                    />
                </div>

                <Card>
                    <CardHeader className="flex-row flex-wrap items-center justify-between gap-3 space-y-0">
                        <div>
                            <CardTitle>Team attendance</CardTitle>
                            <CardDescription>
                                {monthLabel(selectedMonth)} &mdash; click a
                                staff row to see their daily log, and a Time
                                In/Out cell to correct it.
                            </CardDescription>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <Input
                                type="month"
                                value={selectedMonth}
                                onChange={(event) =>
                                    changeMonth(event.target.value)
                                }
                                className="w-auto"
                            />
                            <Button asChild variant="outline">
                                <a
                                    href={exportPdf.url({
                                        query: { month: selectedMonth },
                                    })}
                                >
                                    <FileDown />
                                    Export PDF
                                </a>
                            </Button>
                            <Button asChild variant="outline">
                                <a
                                    href={exportExcel.url({
                                        query: { month: selectedMonth },
                                    })}
                                >
                                    <FileDown />
                                    Export Excel
                                </a>
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {teamSummary.length ? (
                            <Table>
                                <TableHeader>
                                    <TableRow className="hover:bg-transparent">
                                        <TableHead />
                                        <TableHead>Staff</TableHead>
                                        <TableHead>Role</TableHead>
                                        <TableHead>Attendance Days</TableHead>
                                        <TableHead>Logs</TableHead>
                                        <TableHead>Total Hours</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {teamSummary.map((agent) => {
                                        const expanded =
                                            expandedUserId === agent.user_id;
                                        const totalMinutes = agent.days.reduce(
                                            (sum, day) =>
                                                sum + day.worked_minutes,
                                            0,
                                        );

                                        return (
                                            <Fragment key={agent.user_id}>
                                                <TableRow
                                                    className="cursor-pointer"
                                                    onClick={() =>
                                                        setExpandedUserId(
                                                            expanded
                                                                ? null
                                                                : agent.user_id,
                                                        )
                                                    }
                                                >
                                                    <TableCell>
                                                        {expanded ? (
                                                            <ChevronDown className="size-4 text-muted-foreground" />
                                                        ) : (
                                                            <ChevronRight className="size-4 text-muted-foreground" />
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        {agent.user_name}
                                                    </TableCell>
                                                    <TableCell className="text-muted-foreground">
                                                        {agent.role_label}
                                                    </TableCell>
                                                    <TableCell>
                                                        {agent.attendanceDays}
                                                    </TableCell>
                                                    <TableCell>
                                                        {agent.logCount}
                                                    </TableCell>
                                                    <TableCell>
                                                        {formatMinutes(
                                                            totalMinutes,
                                                        )}
                                                    </TableCell>
                                                </TableRow>
                                                {expanded && (
                                                    <TableRow
                                                        key={`${agent.user_id}-detail`}
                                                    >
                                                        <TableCell
                                                            colSpan={6}
                                                            className="bg-muted/20 p-0"
                                                        >
                                                            <Table>
                                                                <TableHeader>
                                                                    <TableRow className="hover:bg-transparent">
                                                                        <TableHead>
                                                                            Date
                                                                        </TableHead>
                                                                        <TableHead>
                                                                            Day
                                                                        </TableHead>
                                                                        <TableHead>
                                                                            Time
                                                                            In
                                                                        </TableHead>
                                                                        <TableHead>
                                                                            Time
                                                                            Out
                                                                        </TableHead>
                                                                        <TableHead>
                                                                            Total
                                                                            Hours
                                                                        </TableHead>
                                                                        <TableHead>
                                                                            Status
                                                                        </TableHead>
                                                                    </TableRow>
                                                                </TableHeader>
                                                                <TableBody>
                                                                    {agent.days.map(
                                                                        (
                                                                            day,
                                                                        ) => (
                                                                            <TableRow
                                                                                key={
                                                                                    day.date
                                                                                }
                                                                            >
                                                                                <TableCell className="whitespace-nowrap">
                                                                                    {
                                                                                        day.date
                                                                                    }
                                                                                </TableCell>
                                                                                <TableCell className="text-muted-foreground">
                                                                                    {new Date(
                                                                                        `${day.date}T00:00:00`,
                                                                                    ).toLocaleDateString(
                                                                                        undefined,
                                                                                        {
                                                                                            weekday:
                                                                                                'short',
                                                                                        },
                                                                                    )}
                                                                                </TableCell>
                                                                                <TableCell>
                                                                                    <button
                                                                                        type="button"
                                                                                        className="hover:underline"
                                                                                        onClick={(
                                                                                            event,
                                                                                        ) => {
                                                                                            event.stopPropagation();
                                                                                            openEditor(
                                                                                                agent.user_id,
                                                                                                agent.user_name,
                                                                                                day.date,
                                                                                                'time_in',
                                                                                                day.time_in,
                                                                                            );
                                                                                        }}
                                                                                    >
                                                                                        {formatTime(
                                                                                            day.time_in,
                                                                                        )}
                                                                                    </button>
                                                                                </TableCell>
                                                                                <TableCell>
                                                                                    <button
                                                                                        type="button"
                                                                                        className="hover:underline"
                                                                                        onClick={(
                                                                                            event,
                                                                                        ) => {
                                                                                            event.stopPropagation();
                                                                                            openEditor(
                                                                                                agent.user_id,
                                                                                                agent.user_name,
                                                                                                day.date,
                                                                                                'time_out',
                                                                                                day.time_out,
                                                                                            );
                                                                                        }}
                                                                                    >
                                                                                        {formatTime(
                                                                                            day.time_out,
                                                                                        )}
                                                                                    </button>
                                                                                </TableCell>
                                                                                <TableCell>
                                                                                    {
                                                                                        day.worked_minutes_label
                                                                                    }
                                                                                </TableCell>
                                                                                <TableCell>
                                                                                    <StatusBadge
                                                                                        value={
                                                                                            day.holiday_label ??
                                                                                            day.status
                                                                                        }
                                                                                    />
                                                                                </TableCell>
                                                                            </TableRow>
                                                                        ),
                                                                    )}
                                                                </TableBody>
                                                            </Table>
                                                        </TableCell>
                                                    </TableRow>
                                                )}
                                            </Fragment>
                                        );
                                    })}
                                </TableBody>
                            </Table>
                        ) : (
                            <EmptyState
                                icon={UsersIcon}
                                title="No active staff yet"
                            />
                        )}
                    </CardContent>
                </Card>
            </div>

            <Dialog
                open={editingCell !== null}
                onOpenChange={(open) => !open && setEditingCell(null)}
            >
                <DialogContent>
                    <DialogTitle>
                        {editingCell?.entryType === 'time_in'
                            ? 'Edit Time In'
                            : 'Edit Time Out'}
                    </DialogTitle>
                    <DialogDescription>
                        {editingCell?.userName} &mdash; {editingCell?.date}
                    </DialogDescription>
                    <form onSubmit={submitEdit} className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="recorded-at">Time</Label>
                            <Input
                                id="recorded-at"
                                type="datetime-local"
                                value={editForm.data.recorded_at}
                                onChange={(event) =>
                                    editForm.setData(
                                        'recorded_at',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={editForm.errors.recorded_at}
                            />
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={clearEntry}
                                disabled={editForm.processing}
                            >
                                Clear
                            </Button>
                            <Button
                                type="submit"
                                disabled={editForm.processing}
                            >
                                Save
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

AttendanceSummary.layout = {
    breadcrumbs: [
        { title: 'Attendance', href: index() },
        { title: 'Attendance Summary', href: summaryRoute() },
    ],
};
