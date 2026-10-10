import { Head, router, usePoll } from '@inertiajs/react';
import {
    CalendarClock,
    DatabaseBackup,
    Download,
    HardDrive,
    History,
    Info,
    LoaderCircle,
} from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { download, index, store } from '@/routes/backups';

type Backup = { name: string; size: number; created_at: string };

const formatBytes = (bytes: number) => {
    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

const formatDate = (value: string) =>
    new Date(value).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });

function PendingPoller() {
    usePoll(15000, { only: ['backups', 'pending'] });

    return null;
}

export default function BackupIndex({
    backups,
    pending,
    keepDays,
}: {
    backups: Backup[];
    pending: boolean;
    keepDays: number;
}) {
    const [requesting, setRequesting] = useState(false);
    const latest = backups[0];
    const totalSize = backups.reduce((sum, backup) => sum + backup.size, 0);
    const isRunning = pending || requesting;

    return (
        <>
            <Head title="Backups" />
            {pending && <PendingPoller />}
            <div className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="grid gap-4 sm:grid-cols-3">
                    <Card className="gap-3 py-5">
                        <CardContent className="flex items-center gap-4">
                            <div className="rounded-xl bg-success/10 p-3 text-success">
                                <History className="size-5" />
                            </div>
                            <div>
                                <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    Latest backup
                                </p>
                                <p className="text-lg font-semibold">
                                    {latest
                                        ? formatDate(latest.created_at)
                                        : 'None yet'}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card className="gap-3 py-5">
                        <CardContent className="flex items-center gap-4">
                            <div className="rounded-xl bg-info/10 p-3 text-info">
                                <HardDrive className="size-5" />
                            </div>
                            <div>
                                <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    Stored backups
                                </p>
                                <p className="text-lg font-semibold tabular-nums">
                                    {backups.length} · {formatBytes(totalSize)}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card className="gap-3 py-5">
                        <CardContent className="flex items-center gap-4">
                            <div className="rounded-xl bg-chart-1/10 p-3 text-chart-1">
                                <CalendarClock className="size-5" />
                            </div>
                            <div>
                                <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    Automatic schedule
                                </p>
                                <p className="text-lg font-semibold">
                                    Daily, 2:00 AM PHT
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card className="overflow-hidden">
                    <CardHeader className="flex flex-col gap-4 border-b border-border/60 sm:flex-row sm:items-start sm:justify-between">
                        <div className="flex items-start gap-3">
                            <div className="rounded-lg bg-primary/10 p-2 text-primary">
                                <DatabaseBackup className="size-5" />
                            </div>
                            <div className="space-y-1">
                                <CardTitle>Database backups</CardTitle>
                                <CardDescription>
                                    Every backup holds all lead data. Backups
                                    older than {keepDays} days are removed
                                    automatically.
                                </CardDescription>
                            </div>
                        </div>
                        <Button
                            type="button"
                            disabled={isRunning}
                            onClick={() => {
                                setRequesting(true);
                                router.post(
                                    store.url(),
                                    {},
                                    {
                                        preserveScroll: true,
                                        onFinish: () => setRequesting(false),
                                    },
                                );
                            }}
                        >
                            {isRunning ? (
                                <LoaderCircle className="animate-spin" />
                            ) : (
                                <DatabaseBackup />
                            )}
                            {isRunning ? 'Backup in progress…' : 'Back up now'}
                        </Button>
                    </CardHeader>
                    <CardContent className="px-0">
                        {backups.length ? (
                            <Table>
                                <TableHeader>
                                    <TableRow className="hover:bg-transparent">
                                        <TableHead className="pl-6">
                                            Created
                                        </TableHead>
                                        <TableHead>File</TableHead>
                                        <TableHead>Size</TableHead>
                                        <TableHead className="pr-6 text-right">
                                            Download
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {backups.map((backup) => (
                                        <TableRow key={backup.name}>
                                            <TableCell className="pl-6 whitespace-nowrap">
                                                {formatDate(backup.created_at)}
                                            </TableCell>
                                            <TableCell className="font-mono text-xs text-muted-foreground">
                                                {backup.name}
                                            </TableCell>
                                            <TableCell className="whitespace-nowrap tabular-nums">
                                                {formatBytes(backup.size)}
                                            </TableCell>
                                            <TableCell className="pr-6 text-right">
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    variant="outline"
                                                >
                                                    {/* No download attribute: the route may first redirect to password confirmation. */}
                                                    <a
                                                        href={download.url(
                                                            backup.name,
                                                        )}
                                                    >
                                                        <Download />
                                                        Download
                                                    </a>
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        ) : (
                            <EmptyState
                                icon={DatabaseBackup}
                                title="No backups yet"
                                description="Click Back up now, or wait for the nightly backup."
                            />
                        )}
                    </CardContent>
                </Card>

                <div className="rounded-lg border border-info/20 bg-info/5 p-4">
                    <div className="flex gap-3">
                        <Info className="mt-0.5 size-4 shrink-0 text-info" />
                        <div className="space-y-1 text-sm">
                            <p className="font-medium">
                                Keep a copy off the server
                            </p>
                            <p className="leading-6 text-muted-foreground">
                                These backups are stored on the same server as
                                the database. Download one at least weekly and
                                keep it somewhere private. Restoring is done by
                                importing the file in phpMyAdmin; see the
                                deployment guide.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

BackupIndex.layout = { breadcrumbs: [{ title: 'Backups', href: index() }] };
