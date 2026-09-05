import { Head, useForm } from '@inertiajs/react';
import { Camera, ImageUp, LogIn, LogOut, RotateCcw, Video, VideoOff } from 'lucide-react';
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
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dashboard } from '@/routes';
import { index, scan, scanner } from '@/routes/attendance';
import type {
    AttendanceCheckIn,
    AttendanceEntryType,
} from '@/types';

function describeCameraError(error: unknown): string {
    const name = error instanceof Error ? error.name : '';

    if (name === 'NotAllowedError') {
        return 'Camera access was blocked. Allow camera permission for this site in your browser settings, then try again.';
    }

    if (name === 'NotFoundError' || name === 'OverconstrainedError') {
        return 'No camera was found on this device.';
    }

    if (name === 'NotReadableError') {
        return 'The camera is already in use by another app or browser tab.';
    }

    return error instanceof Error && error.message
        ? `Could not start the camera: ${error.message}`
        : 'Could not start the camera.';
}

type Props = {
    registeredUsers: number;
    recentCheckIns: AttendanceCheckIn[];
};

export default function AttendanceScanner({
    registeredUsers,
    recentCheckIns,
}: Props) {
    const form = useForm<{ code: string; entry_type: AttendanceEntryType }>({
        code: '',
        entry_type: 'time_in',
    });
    const videoRef = useRef<HTMLVideoElement>(null);
    const scannerRef = useRef<QrScanner | null>(null);
    const [scanning, setScanning] = useState(false);
    const [cameraError, setCameraError] = useState<string | null>(null);
    const [cameras, setCameras] = useState<QrScanner.Camera[]>([]);
    const [selectedCameraId, setSelectedCameraId] = useState<string>('');

    async function refreshCameras() {
        try {
            const list = await QrScanner.listCameras(true);
            setCameras(list);

            if (!selectedCameraId && list.length > 0) {
                setSelectedCameraId(list[0].id);
            }
        } catch {
            // Camera enumeration failing just leaves the manual/image-upload
            // options available - not a fatal error for this page.
        }
    }

    function startCamera() {
        if (!videoRef.current) {
            return;
        }

        if (!window.isSecureContext) {
            setCameraError(
                'The camera requires a secure connection (HTTPS). This page was loaded over plain HTTP.',
            );

            return;
        }

        if (!navigator.mediaDevices?.getUserMedia) {
            setCameraError('This browser does not support camera access.');

            return;
        }

        setCameraError(null);

        const scanner = new QrScanner(
            videoRef.current,
            (result) => {
                form.setData('code', result.data);
            },
            {
                highlightScanRegion: true,
                highlightCodeOutline: true,
                preferredCamera: selectedCameraId || 'environment',
            },
        );

        scannerRef.current = scanner;
        scanner
            .start()
            .then(() => {
                setScanning(true);
                void refreshCameras();
            })
            .catch((error: unknown) => {
                setScanning(false);
                setCameraError(describeCameraError(error));
            });
    }

    function stopCamera() {
        scannerRef.current?.stop();
        setScanning(false);
    }

    function restartCamera() {
        scannerRef.current?.stop();
        scannerRef.current?.destroy();
        scannerRef.current = null;
        startCamera();
    }

    useEffect(() => {
        // Auto-start the scanner on mount, mirroring a physical check-in
        // station rather than requiring an extra click to begin.
        // eslint-disable-next-line react-hooks/set-state-in-effect
        startCamera();

        return () => {
            scannerRef.current?.stop();
            scannerRef.current?.destroy();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    function changeCamera(deviceId: string) {
        setSelectedCameraId(deviceId);

        if (scannerRef.current) {
            scannerRef.current.setCamera(deviceId).catch((error: unknown) => {
                setCameraError(describeCameraError(error));
            });
        }
    }

    async function handleImageUpload(
        event: React.ChangeEvent<HTMLInputElement>,
    ) {
        const file = event.target.files?.[0];
        event.target.value = '';

        if (!file) {
            return;
        }

        try {
            const result = await QrScanner.scanImage(file, {
                returnDetailedScanResult: true,
            });
            form.setData('code', result.data);
            setCameraError(null);
        } catch {
            setCameraError('Could not read a QR code from that image.');
        }
    }

    function submit() {
        form.post(scan.url(), {
            preserveScroll: true,
            onSuccess: () => form.reset('code'),
        });
    }

    return (
        <>
            <Head title="QR Scanner" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p className="text-xs font-semibold tracking-widest text-cyan-600 uppercase dark:text-cyan-400">
                            Attendance Scanner
                        </p>
                        <h1 className="text-2xl font-semibold">
                            Reliable QR check-in station
                        </h1>
                        <p className="mt-1 max-w-xl text-sm text-muted-foreground">
                            Use the live webcam, a QR image, or manual code
                            entry to record attendance quickly and reliably.
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <a href={dashboard().url}>Return to home</a>
                    </Button>
                </div>

                <div className="grid gap-6 lg:grid-cols-[1fr_minmax(0,22rem)]">
                    <Card>
                        <CardHeader className="flex-row flex-wrap items-center justify-between gap-3 space-y-0">
                            <div>
                                <CardTitle>Open the camera and scan</CardTitle>
                                <CardDescription>
                                    Live camera / image upload / manual entry.
                                </CardDescription>
                            </div>
                            <div className="rounded-lg border bg-muted/30 px-3 py-1.5 text-right">
                                <p className="text-xs text-muted-foreground">
                                    Registered users
                                </p>
                                <p className="text-lg font-semibold">
                                    {registeredUsers}
                                </p>
                            </div>
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

                            <div className="flex flex-wrap items-center gap-2">
                                <Select
                                    value={selectedCameraId}
                                    onValueChange={changeCamera}
                                >
                                    <SelectTrigger className="min-w-56 flex-1">
                                        <SelectValue placeholder="Select a camera" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {cameras.map((camera) => (
                                            <SelectItem
                                                key={camera.id}
                                                value={camera.id}
                                            >
                                                {camera.label ||
                                                    `Camera ${camera.id.slice(0, 6)}`}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => void refreshCameras()}
                                >
                                    <RotateCcw />
                                    Refresh webcams
                                </Button>
                            </div>

                            <div className="grid grid-cols-2 gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={restartCamera}
                                >
                                    <Video />
                                    Restart scanner
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={stopCamera}
                                    disabled={!scanning}
                                >
                                    <VideoOff />
                                    Stop scanner
                                </Button>
                            </div>

                            {cameraError && (
                                <p className="text-sm text-destructive">
                                    {cameraError}
                                </p>
                            )}

                            <div className="grid gap-2">
                                <Label htmlFor="qr-image-upload">
                                    Or upload a QR image
                                </Label>
                                <div className="flex items-center gap-2">
                                    <ImageUp className="size-4 shrink-0 text-muted-foreground" />
                                    <Input
                                        id="qr-image-upload"
                                        type="file"
                                        accept="image/*"
                                        onChange={handleImageUpload}
                                    />
                                </div>
                            </div>

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
                                <Camera />
                                Record scan
                            </Button>
                        </CardContent>
                    </Card>

                    <div className="grid gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Better scanning flow</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <ol className="list-decimal space-y-2 pl-4 text-sm text-muted-foreground">
                                    <li>
                                        Choose Time In or Time Out, then use
                                        the live camera scanner.
                                    </li>
                                    <li>
                                        If camera access fails, upload a QR
                                        image or paste the raw QR value.
                                    </li>
                                    <li>
                                        Every successful scan records the
                                        timestamp immediately.
                                    </li>
                                </ol>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Recent check-ins</CardTitle>
                                <CardDescription>
                                    Latest successful attendance records.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-3">
                                {recentCheckIns.length ? (
                                    recentCheckIns.map((checkIn) => (
                                        <div
                                            key={checkIn.id}
                                            className="flex items-start justify-between gap-3 rounded-lg border p-3"
                                        >
                                            <div className="min-w-0">
                                                <p className="truncate font-medium">
                                                    {checkIn.user_name ??
                                                        'Deleted user'}
                                                </p>
                                                {checkIn.employee_code && (
                                                    <p className="text-xs text-muted-foreground">
                                                        {checkIn.employee_code}
                                                    </p>
                                                )}
                                                <div className="mt-1">
                                                    <StatusBadge
                                                        value={
                                                            checkIn.entry_type
                                                        }
                                                    />
                                                </div>
                                            </div>
                                            <p className="shrink-0 text-right text-xs text-muted-foreground">
                                                {new Date(
                                                    checkIn.recorded_at,
                                                ).toLocaleString()}
                                            </p>
                                        </div>
                                    ))
                                ) : (
                                    <EmptyState
                                        icon={Camera}
                                        title="No check-ins yet"
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

AttendanceScanner.layout = {
    breadcrumbs: [
        { title: 'Attendance', href: index() },
        { title: 'QR Scanner', href: scanner() },
    ],
};
