import { Head, router } from '@inertiajs/react';
import {
    ChevronDown,
    ChevronRight,
    Clock3,
    FileDown,
    LogIn,
    QrCode as QrCodeIcon,
    Search,
    Users as UsersIcon,
} from 'lucide-react';
import { Fragment, useState } from 'react';
import { AttendanceEntryDialog } from '@/components/attendance-entry-dialog';
import { EmptyState } from '@/components/empty-state';
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
import { Input } from '@/components/ui/input';
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
    exportExcel,
    exportPdf,
    index,
    summary as summaryRoute,
} from '@/routes/attendance';
import type { AttendanceMonthlyAgent } from '@/types';

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

function monthLabel(month: string): string {
    return new Date(`${month}-01T00:00:00`).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'long',
    });
}

function isRestDay(holidayLabel: string | null): boolean {
    return (holidayLabel ?? '').toLowerCase().includes('rest');
}

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
    const editor = useAttendanceEntryEditor();
    const [expandedUserId, setExpandedUserId] = useState<number | null>(null);
    const [search, setSearch] = useState('');

    function changeMonth(month: string) {
        router.get(
            summaryRoute.url(),
            { month },
            { preserveState: true, preserveScroll: true },
        );
    }

    const teamSummary = monthlyAttendance
        .map((agent) => {
            const attendanceDays = agent.days.filter(
                (day) => day.time_in !== null,
            ).length;
            const logCount = agent.days.reduce(
                (sum, day) =>
                    sum + (day.time_in ? 1 : 0) + (day.time_out ? 1 : 0),
                0,
            );
            const totalMinutes = agent.days.reduce(
                (sum, day) => sum + day.worked_minutes,
                0,
            );

            return { ...agent, attendanceDays, logCount, totalMinutes };
        })
        .filter((agent) => {
            if (!search.trim()) {
                return true;
            }

            const haystack = [
                agent.user_name,
                agent.alias_name,
                agent.alias_email,
                agent.employee_code,
                agent.role_label,
            ]
                .filter(Boolean)
                .join(' ')
                .toLowerCase();

            return haystack.includes(search.trim().toLowerCase());
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
                    <CardContent className="flex flex-col gap-4">
                        <div className="relative max-w-md">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Search by name, alias, email, employee code, or role…"
                                className="pl-9"
                            />
                        </div>

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
                                                            agent.totalMinutes,
                                                        )}
                                                    </TableCell>
                                                </TableRow>
                                                {expanded && (
                                                    <TableRow
                                                        key={`${agent.user_id}-detail`}
                                                    >
                                                        <TableCell
                                                            colSpan={6}
                                                            className="bg-muted/20 p-4"
                                                        >
                                                            <div className="mb-4 flex flex-col gap-3">
                                                                <div className="flex flex-wrap items-center gap-2">
                                                                    <p className="text-base font-semibold">
                                                                        {
                                                                            agent.user_name
                                                                        }
                                                                    </p>
                                                                    <StatusBadge
                                                                        value={
                                                                            agent.role_label
                                                                        }
                                                                    />
                                                                    <StatusBadge
                                                                        value={
                                                                            agent.status
                                                                        }
                                                                    />
                                                                    <StatusBadge
                                                                        value={`${agent.days.length} days`}
                                                                    />
                                                                </div>
                                                                {agent.alias_name && (
                                                                    <p className="text-sm text-cyan-700 dark:text-cyan-300">
                                                                        {
                                                                            agent.alias_name
                                                                        }
                                                                        {agent.alias_email && (
                                                                            <span className="ml-2 text-muted-foreground">
                                                                                {
                                                                                    agent.alias_email
                                                                                }
                                                                            </span>
                                                                        )}
                                                                    </p>
                                                                )}
                                                                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                                                                    {[
                                                                        [
                                                                            'Employee Code',
                                                                            agent.employee_code ??
                                                                                '—',
                                                                        ],
                                                                        [
                                                                            'Position',
                                                                            agent.role_label,
                                                                        ],
                                                                        [
                                                                            'Status',
                                                                            agent.status,
                                                                        ],
                                                                        [
                                                                            'Logs in period',
                                                                            String(
                                                                                agent.logCount,
                                                                            ),
                                                                        ],
                                                                        [
                                                                            'Total Hours',
                                                                            formatMinutes(
                                                                                agent.totalMinutes,
                                                                            ),
                                                                        ],
                                                                        [
                                                                            'Added',
                                                                            agent.added_at ??
                                                                                '—',
                                                                        ],
                                                                    ].map(
                                                                        ([
                                                                            label,
                                                                            value,
                                                                        ]) => (
                                                                            <div
                                                                                key={
                                                                                    label
                                                                                }
                                                                                className="rounded-lg border bg-background p-2.5"
                                                                            >
                                                                                <p className="text-[11px] tracking-wide text-muted-foreground uppercase">
                                                                                    {
                                                                                        label
                                                                                    }
                                                                                </p>
                                                                                <p className="truncate text-sm font-medium">
                                                                                    {
                                                                                        value
                                                                                    }
                                                                                </p>
                                                                            </div>
                                                                        ),
                                                                    )}
                                                                </div>
                                                            </div>

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
                                                                            Logs
                                                                        </TableHead>
                                                                    </TableRow>
                                                                </TableHeader>
                                                                <TableBody>
                                                                    {agent.days.map(
                                                                        (
                                                                            day,
                                                                        ) => {
                                                                            const restDay =
                                                                                day.status ===
                                                                                    'holiday' &&
                                                                                isRestDay(
                                                                                    day.holiday_label,
                                                                                );
                                                                            const holiday =
                                                                                day.status ===
                                                                                    'holiday' &&
                                                                                !restDay;
                                                                            const placeholder =
                                                                                restDay
                                                                                    ? 'Rest Day'
                                                                                    : holiday
                                                                                        ? 'Holiday'
                                                                                        : null;

                                                                            return (
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
                                                                                                editor.openEditor(
                                                                                                    agent.user_id,
                                                                                                    agent.user_name,
                                                                                                    day.date,
                                                                                                    'time_in',
                                                                                                    day.time_in,
                                                                                                );
                                                                                            }}
                                                                                        >
                                                                                            {placeholder ??
                                                                                                formatTime(
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
                                                                                                editor.openEditor(
                                                                                                    agent.user_id,
                                                                                                    agent.user_name,
                                                                                                    day.date,
                                                                                                    'time_out',
                                                                                                    day.time_out,
                                                                                                );
                                                                                            }}
                                                                                        >
                                                                                            {placeholder ??
                                                                                                formatTime(
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
                                                                                        <div className="flex flex-wrap gap-1">
                                                                                            {placeholder ? (
                                                                                                <StatusBadge
                                                                                                    value={`${placeholder} - ${day.holiday_label}`}
                                                                                                />
                                                                                            ) : (
                                                                                                <>
                                                                                                    {day.time_in && (
                                                                                                        <StatusBadge
                                                                                                            value={`Time In - ${formatTime(day.time_in)}`}
                                                                                                        />
                                                                                                    )}
                                                                                                    {day.time_out && (
                                                                                                        <StatusBadge
                                                                                                            value={`Time Out - ${formatTime(day.time_out)}`}
                                                                                                        />
                                                                                                    )}
                                                                                                </>
                                                                                            )}
                                                                                        </div>
                                                                                    </TableCell>
                                                                                </TableRow>
                                                                            );
                                                                        },
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
                                title="No matching staff"
                            />
                        )}
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

AttendanceSummary.layout = {
    breadcrumbs: [
        { title: 'Attendance', href: index() },
        { title: 'Attendance Summary', href: summaryRoute() },
    ],
};
