import { Head } from '@inertiajs/react';
import { AttendanceQrScanner } from '@/components/attendance-qr-scanner';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { index, scan, scanner } from '@/routes/attendance';
import type { AttendanceCheckIn } from '@/types';

type Props = {
    registeredUsers: number;
    recentCheckIns: AttendanceCheckIn[];
};

export default function AttendanceScanner({
    registeredUsers,
    recentCheckIns,
}: Props) {
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

                <AttendanceQrScanner
                    postUrl={scan.url()}
                    recentCheckIns={recentCheckIns}
                    headerStat={{
                        label: 'Registered users',
                        value: registeredUsers,
                    }}
                />
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
