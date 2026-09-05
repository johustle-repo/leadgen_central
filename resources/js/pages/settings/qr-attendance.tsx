import { Head } from '@inertiajs/react';
import { AttendanceQrScanner } from '@/components/attendance-qr-scanner';
import { edit, scan } from '@/routes/qr-attendance';
import type { AttendanceCheckIn } from '@/types';

type Props = {
    recentCheckIns: AttendanceCheckIn[];
};

export default function QrAttendance({ recentCheckIns }: Props) {
    return (
        <>
            <Head title="QR Attendance" />

            <h1 className="sr-only">QR Attendance</h1>

            <AttendanceQrScanner
                postUrl={scan.url()}
                recentCheckIns={recentCheckIns}
                recentCheckInsTitle="Your recent check-ins"
                recentCheckInsDescription="Your latest attendance records."
                layout="stacked"
            />
        </>
    );
}

QrAttendance.layout = {
    breadcrumbs: [{ title: 'QR Attendance', href: edit() }],
};
