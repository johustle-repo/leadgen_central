import { useForm } from '@inertiajs/react';
import { Camera, ImageUp, LogIn, LogOut, RotateCcw, Send, Video, VideoOff } from 'lucide-react';
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
import { formatAttendanceDateTime } from '@/lib/attendance-time';
import type { AttendanceCheckIn, AttendanceEntryType } from '@/types';

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
    postUrl: string;
    recentCheckIns: AttendanceCheckIn[];
    recentCheckInsTitle?: string;
    recentCheckInsDescription?: string;
    headerStat?: { label: string; value: number | string };
    /** 'stacked' suits a narrow container (e.g. the Settings page); 'wide' uses a two-column layout for full-width station pages. */
    layout?: 'wide' | 'stacked';
};

export function AttendanceQrScanner({
    postUrl,
    recentCheckIns,
    recentCheckInsTitle = 'Recent check-ins',
    recentCheckInsDescription = 'Latest successful attendance records.',
    headerStat,
    layout = 'wide',
}: Props) {
    const form = useForm<{ code: string; entry_type: AttendanceEntryType }>({
        code: '',
        entry_type: 'time_in',
    });
    const videoRef = useRef<HTMLVideoElement>(null);
    const scannerRef = useRef<QrScanner | null>(null);
    const submittingRef = useRef(false);
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
            (result) => recordScan(result.data),
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
            setCameraError(null);
            recordScan(result.data);
        } catch {
            setCameraError('Could not read a QR code from that image.');
        }
    }

    /**
     * Auto-records the instant a code is detected (camera or image
     * upload) or a manual value is submitted - no separate "confirm"
     * step. `submittingRef` guards synchronously against the camera's
     * continuous scan loop firing several times for the same still-
     * visible badge before React state (`form.processing`) catches up.
     */
    function recordScan(code: string) {
        const trimmed = code.trim();

        if (!trimmed || submittingRef.current) {
            return;
        }

        submittingRef.current = true;
        form.setData('code', trimmed);
        form.transform((data) => ({ ...data, code: trimmed }));
        form.post(postUrl, {
            preserveScroll: true,
            onSuccess: () => form.reset('code'),
            onFinish: () => {
                submittingRef.current = false;
                // Briefly pause the camera after any attempt (success or
                // rejected duplicate) so the same badge lingering in view
                // doesn't immediately trigger another submission.
                scannerRef.current?.pause();
                window.setTimeout(() => {
                    scannerRef.current?.start().catch(() => {});
                }, 2000);
            },
        });
    }

    function submitManualCode(event: React.FormEvent) {
        event.preventDefault();
        recordScan(form.data.code);
    }

    return (
        <div
            className={
                layout === 'wide'
                    ? 'grid gap-6 lg:grid-cols-[1fr_minmax(0,22rem)]'
                    : 'grid gap-6'
            }
        >
            <Card>
                <CardHeader className="flex-row flex-wrap items-center justify-between gap-3 space-y-0">
                    <div>
                        <CardTitle>Open the camera and scan</CardTitle>
                        <CardDescription>
                            Live camera / image upload / manual entry.
                        </CardDescription>
                    </div>
                    {headerStat && (
                        <div className="rounded-lg border bg-muted/30 px-3 py-1.5 text-right">
                            <p className="text-xs text-muted-foreground">
                                {headerStat.label}
                            </p>
                            <p className="text-lg font-semibold">
                                {headerStat.value}
                            </p>
                        </div>
                    )}
                </CardHeader>
                <CardContent className="grid gap-6 md:grid-cols-2">
                    <div className="grid gap-4">
                        <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                            Camera
                        </p>

                        <div className="mx-auto w-full max-w-72 overflow-hidden rounded-lg border bg-muted/30">
                            <video
                                ref={videoRef}
                                className="aspect-square w-full object-cover"
                                muted
                                playsInline
                            />
                        </div>

                        <Select
                            value={selectedCameraId}
                            onValueChange={changeCamera}
                        >
                            <SelectTrigger className="w-full">
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

                        <div className="grid grid-cols-3 gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => void refreshCameras()}
                            >
                                <RotateCcw />
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={restartCamera}
                            >
                                <Video />
                                Restart
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={stopCamera}
                                disabled={!scanning}
                            >
                                <VideoOff />
                                Stop
                            </Button>
                        </div>

                        {cameraError && (
                            <p className="text-sm text-destructive">
                                {cameraError}
                            </p>
                        )}
                    </div>

                    <div className="grid content-start gap-4 border-t pt-6 md:border-t-0 md:border-l md:pt-0 md:pl-6">
                        <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                            Entry type &amp; manual fallback
                        </p>

                        <div className="grid gap-2">
                            <Label>Entry type</Label>
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
                        </div>

                        <form
                            onSubmit={submitManualCode}
                            className="grid gap-2"
                        >
                            <Label htmlFor="manual-code">
                                Or type/paste a QR value
                            </Label>
                            <div className="flex gap-2">
                                <Input
                                    id="manual-code"
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
                                <Button
                                    type="submit"
                                    size="icon"
                                    disabled={
                                        form.processing ||
                                        !form.data.code.trim()
                                    }
                                    aria-label="Submit code"
                                >
                                    <Send />
                                </Button>
                            </div>
                            <InputError message={form.errors.code} />
                        </form>

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

                        <div className="rounded-lg border bg-muted/30 p-3 text-xs text-muted-foreground">
                            Attendance is recorded the instant a QR code is
                            detected by the camera or an uploaded image -
                            there&apos;s no separate confirm step. Pick Time
                            In or Time Out first.
                        </div>
                    </div>
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
                                Choose Time In or Time Out, then use the live
                                camera scanner.
                            </li>
                            <li>
                                If camera access fails, upload a QR image or
                                paste the raw QR value.
                            </li>
                            <li>
                                Every successful scan records the timestamp
                                immediately.
                            </li>
                        </ol>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{recentCheckInsTitle}</CardTitle>
                        <CardDescription>
                            {recentCheckInsDescription}
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
                                                value={checkIn.entry_type}
                                            />
                                        </div>
                                    </div>
                                    <p className="shrink-0 text-right text-xs text-muted-foreground">
                                        {formatAttendanceDateTime(
                                            checkIn.recorded_at,
                                        )}
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
    );
}
