import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    Camera,
    Clock3,
    Download,
    FileDown,
    FileJson,
    LogIn,
    LogOut,
    QrCode as QrCodeIcon,
    SlidersHorizontal,
    Users as UsersIcon,
    X,
} from 'lucide-react';
import QrScanner from 'qr-scanner';
import { useEffect, useRef, useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import { FilterBar } from '@/components/filter-bar';
import InputError from '@/components/input-error';
import { Pagination } from '@/components/pagination';
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
import { downloadDataUrl, drawIdentityCard } from '@/lib/qr';
import { cn } from '@/lib/utils';
import {
    exportPdf,
    importMethod as importAttendance,
    index,
    scan,
} from '@/routes/attendance';
import type {
    AttendanceEntryType,
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
    summary: {
        total_records: number;
        time_ins_today: number;
        late_today: number;
        active_staff: number;
    };
    filters: { search?: string; entry_type?: string; date?: string };
};

export default function AttendanceIndex({
    users,
    records,
    summary,
    filters,
}: Props) {
    const { flash } = usePage().props;
    const form = useForm<{ code: string; entry_type: AttendanceEntryType }>({
        code: '',
        entry_type: 'time_in',
    });
    const importForm = useForm<{ files: File[] }>({ files: [] });
    const [entryTypeFilter, setEntryTypeFilter] = useState(
        filters.entry_type || ALL_ENTRY_TYPES,
    );
    const [importErrorsDismissed, setImportErrorsDismissed] = useState(false);
    const videoRef = useRef<HTMLVideoElement>(null);
    const scannerRef = useRef<QrScanner | null>(null);
    const [scanning, setScanning] = useState(false);

    useEffect(
        () => () => {
            scannerRef.current?.stop();
            scannerRef.current?.destroy();
        },
        [],
    );

    function startCamera() {
        if (!videoRef.current) {
            return;
        }

        const scanner = new QrScanner(
            videoRef.current,
            (result) => {
                form.setData('code', result.data);
                stopCamera();
            },
            { highlightScanRegion: true, highlightCodeOutline: true },
        );

        scannerRef.current = scanner;
        scanner
            .start()
            .then(() => setScanning(true))
            .catch(() => setScanning(false));
    }

    function stopCamera() {
        scannerRef.current?.stop();
        setScanning(false);
    }

    function submit() {
        form.post(scan.url(), {
            preserveScroll: true,
            onSuccess: () => form.reset('code'),
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

    function filterRecords(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get(
            index.url(),
            Object.fromEntries(new FormData(event.currentTarget)),
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    const importErrors =
        !importErrorsDismissed && flash.importErrors
            ? flash.importErrors
            : [];

    return (
        <>
            <Head title="Attendance" />
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

                <div className="grid gap-6 lg:grid-cols-[minmax(0,22rem)_1fr]">
                    <div className="grid gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Scan attendance</CardTitle>
                                <CardDescription>
                                    Scan a staff member&apos;s QR code, or
                                    enter their code manually.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <div className="overflow-hidden rounded-lg border bg-muted/30">
                                    <video
                                        ref={videoRef}
                                        className="aspect-square w-full object-cover"
                                        muted
                                        playsInline
                                    />
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={scanning ? stopCamera : startCamera}
                                >
                                    <Camera />
                                    {scanning ? 'Stop camera' : 'Start camera'}
                                </Button>

                                <div className="grid gap-2">
                                    <Label htmlFor="code">QR code value</Label>
                                    <Input
                                        id="code"
                                        name="code"
                                        value={form.data.code}
                                        onChange={(event) =>
                                            form.setData(
                                                'code',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="attendance:..."
                                        aria-invalid={
                                            form.errors.code
                                                ? true
                                                : undefined
                                        }
                                    />
                                    <InputError message={form.errors.code} />
                                </div>

                                <div className="grid grid-cols-2 gap-2">
                                    <Button
                                        type="button"
                                        variant={
                                            form.data.entry_type === 'time_in'
                                                ? 'default'
                                                : 'outline'
                                        }
                                        onClick={() =>
                                            form.setData(
                                                'entry_type',
                                                'time_in',
                                            )
                                        }
                                    >
                                        <LogIn />
                                        Time In
                                    </Button>
                                    <Button
                                        type="button"
                                        variant={
                                            form.data.entry_type ===
                                            'time_out'
                                                ? 'default'
                                                : 'outline'
                                        }
                                        onClick={() =>
                                            form.setData(
                                                'entry_type',
                                                'time_out',
                                            )
                                        }
                                    >
                                        <LogOut />
                                        Time Out
                                    </Button>
                                </div>

                                <Button
                                    type="button"
                                    disabled={
                                        form.processing ||
                                        !form.data.code.trim()
                                    }
                                    onClick={submit}
                                >
                                    Record scan
                                </Button>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Import attendance history</CardTitle>
                                <CardDescription>
                                    Upload one or more JSON exports from a
                                    previous system. Rows are matched to
                                    staff by email or name; re-importing the
                                    same file is safe.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
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
                                                    event.target.files ?? [],
                                                ),
                                            )
                                        }
                                    />
                                    {importForm.data.files.length > 0 && (
                                        <p className="text-xs text-muted-foreground">
                                            {importForm.data.files.length}{' '}
                                            file
                                            {importForm.data.files.length ===
                                            1
                                                ? ''
                                                : 's'}{' '}
                                            selected:{' '}
                                            {importForm.data.files
                                                .map((file) => file.name)
                                                .join(', ')}
                                        </p>
                                    )}
                                    <InputError
                                        message={importForm.errors.files}
                                    />
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        disabled={
                                            importForm.processing ||
                                            importForm.data.files.length === 0
                                        }
                                    >
                                        <FileJson />
                                        {importForm.processing
                                            ? 'Importing…'
                                            : 'Import records'}
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="grid gap-6">
                        <Card>
                            <CardHeader className="flex-row items-center justify-between space-y-0">
                                <div>
                                    <CardTitle>Staff QR identities</CardTitle>
                                    <CardDescription>
                                        Download a printable identity card for
                                        each user.
                                    </CardDescription>
                                </div>
                                <Button asChild variant="outline">
                                    <a href={exportPdf.url()}>
                                        <FileDown />
                                        Export PDF
                                    </a>
                                </Button>
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
                                                        handleDownloadCard(
                                                            user,
                                                        )
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
                                <CardTitle>Recent records</CardTitle>
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
                                                entryTypeFilter ===
                                                ALL_ENTRY_TYPES
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
                                                <SelectItem
                                                    value={ALL_ENTRY_TYPES}
                                                >
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
                                                <TableHead>
                                                    Recorded at
                                                </TableHead>
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
                                                            value={
                                                                record.entry_type
                                                            }
                                                        />
                                                    </TableCell>
                                                    <TableCell className="whitespace-nowrap text-muted-foreground">
                                                        {new Date(
                                                            record.recorded_at,
                                                        ).toLocaleString()}
                                                    </TableCell>
                                                    <TableCell>
                                                        {record.status && (
                                                            <StatusBadge
                                                                value={
                                                                    record.status
                                                                }
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
                </div>
            </div>
        </>
    );
}

AttendanceIndex.layout = {
    breadcrumbs: [{ title: 'Attendance', href: index() }],
};
