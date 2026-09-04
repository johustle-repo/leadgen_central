import { Head, useForm } from '@inertiajs/react';
import {
    Camera,
    Download,
    FileDown,
    LogIn,
    LogOut,
    QrCode as QrCodeIcon,
} from 'lucide-react';
import QrScanner from 'qr-scanner';
import { useEffect, useRef, useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
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
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { downloadDataUrl, drawIdentityCard } from '@/lib/qr';
import { cn } from '@/lib/utils';
import { exportPdf, index, scan } from '@/routes/attendance';
import type {
    AttendanceEntryType,
    AttendanceRecord,
    AttendanceUser,
} from '@/types';

export default function AttendanceIndex({
    users,
    records,
}: {
    users: AttendanceUser[];
    records: AttendanceRecord[];
}) {
    const form = useForm<{ code: string; entry_type: AttendanceEntryType }>({
        code: '',
        entry_type: 'time_in',
    });
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

    return (
        <>
            <Head title="Attendance" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="grid gap-6 lg:grid-cols-[minmax(0,22rem)_1fr]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Scan attendance</CardTitle>
                            <CardDescription>
                                Scan a staff member&apos;s QR code, or enter
                                their code manually.
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
                                        form.setData('code', event.target.value)
                                    }
                                    placeholder="attendance:..."
                                    aria-invalid={
                                        form.errors.code ? true : undefined
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
                                        form.setData('entry_type', 'time_in')
                                    }
                                >
                                    <LogIn />
                                    Time In
                                </Button>
                                <Button
                                    type="button"
                                    variant={
                                        form.data.entry_type === 'time_out'
                                            ? 'default'
                                            : 'outline'
                                    }
                                    onClick={() =>
                                        form.setData('entry_type', 'time_out')
                                    }
                                >
                                    <LogOut />
                                    Time Out
                                </Button>
                            </div>

                            <Button
                                type="button"
                                disabled={
                                    form.processing || !form.data.code.trim()
                                }
                                onClick={submit}
                            >
                                Record scan
                            </Button>
                        </CardContent>
                    </Card>

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
                                <CardTitle>Recent records</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {records.length ? (
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
                                            {records.map((record) => (
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
