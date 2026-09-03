import { Form, Head } from '@inertiajs/react';
import {
    CheckCircle2,
    Database,
    FileSpreadsheet,
    HardDriveUpload,
    Info,
    Save,
    ShieldCheck,
} from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit, update } from '@/routes/system-settings';

const formatMegabytes = (kilobytes: number) => {
    const megabytes = kilobytes / 1024;

    return `${Number.isInteger(megabytes) ? megabytes : megabytes.toFixed(1)} MB`;
};

export default function SettingsEdit({
    settings,
}: {
    settings: { csv_max_kilobytes: number; csv_max_files: number };
}) {
    const [currentLimit, setCurrentLimit] = useState(
        settings.csv_max_kilobytes,
    );
    const [currentFileLimit, setCurrentFileLimit] = useState(
        settings.csv_max_files,
    );

    return (
        <>
            <Head title="System Settings" />
            <div className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="System settings"
                    description="Manage upload safeguards and operational limits across LeadGen Central."
                />

                <div className="grid gap-4 sm:grid-cols-3">
                    <Card className="gap-3 py-5">
                        <CardContent className="flex items-center gap-4">
                            <div className="rounded-xl bg-info/10 p-3 text-info">
                                <HardDriveUpload className="size-5" />
                            </div>
                            <div>
                                <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    Current limit
                                </p>
                                <p className="text-2xl font-semibold tabular-nums">
                                    {formatMegabytes(currentLimit)}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card className="gap-3 py-5">
                        <CardContent className="flex items-center gap-4">
                            <div className="rounded-xl bg-chart-1/10 p-3 text-chart-1">
                                <FileSpreadsheet className="size-5" />
                            </div>
                            <div>
                                <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    Files per upload
                                </p>
                                <p className="text-2xl font-semibold tabular-nums">
                                    {currentFileLimit}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card className="gap-3 py-5">
                        <CardContent className="flex items-center gap-4">
                            <div className="rounded-xl bg-success/10 p-3 text-success">
                                <ShieldCheck className="size-5" />
                            </div>
                            <div>
                                <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    Protection
                                </p>
                                <p className="text-2xl font-semibold">Active</p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                    <Form
                        {...update.form()}
                        setDefaultsOnSuccess
                        options={{ preserveScroll: true }}
                    >
                        {({
                            errors,
                            processing,
                            isDirty,
                            recentlySuccessful,
                        }) => (
                            <Card className="overflow-hidden">
                                <CardHeader className="border-b border-border/60">
                                    <div className="flex items-start gap-3">
                                        <div className="rounded-lg bg-primary/10 p-2 text-primary">
                                            <Database className="size-5" />
                                        </div>
                                        <div className="space-y-1">
                                            <CardTitle>Upload limits</CardTitle>
                                            <CardDescription>
                                                Control the largest lead file
                                                agents can submit for
                                                processing.
                                            </CardDescription>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent className="grid gap-6 pt-1">
                                    <div className="grid gap-2">
                                        <div className="flex flex-col justify-between gap-1 sm:flex-row sm:items-center">
                                            <Label htmlFor="csv_max_kilobytes">
                                                Maximum CSV file size
                                            </Label>
                                            <span className="text-xs text-muted-foreground">
                                                Allowed range: 128 KB–50 MB
                                            </span>
                                        </div>
                                        <div className="relative">
                                            <Input
                                                id="csv_max_kilobytes"
                                                name="csv_max_kilobytes"
                                                type="number"
                                                min="128"
                                                max="51200"
                                                step="128"
                                                defaultValue={
                                                    settings.csv_max_kilobytes
                                                }
                                                onChange={(event) =>
                                                    setCurrentLimit(
                                                        Number(
                                                            event.target.value,
                                                        ) || 0,
                                                    )
                                                }
                                                aria-invalid={
                                                    errors.csv_max_kilobytes
                                                        ? true
                                                        : undefined
                                                }
                                                className="h-11 pr-28 tabular-nums"
                                            />
                                            <span className="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm font-medium text-muted-foreground">
                                                {formatMegabytes(currentLimit)}
                                            </span>
                                        </div>
                                        {errors.csv_max_kilobytes ? (
                                            <InputError
                                                message={
                                                    errors.csv_max_kilobytes
                                                }
                                            />
                                        ) : (
                                            <p className="text-sm leading-6 text-muted-foreground">
                                                Larger limits support bigger
                                                imports but increase processing
                                                time and memory use.
                                            </p>
                                        )}
                                    </div>
                                    <div className="grid gap-2">
                                        <div className="flex flex-col justify-between gap-1 sm:flex-row sm:items-center">
                                            <Label htmlFor="csv_max_files">
                                                Maximum files per upload
                                            </Label>
                                            <span className="text-xs text-muted-foreground">
                                                Allowed range: 1–50 files
                                            </span>
                                        </div>
                                        <Input
                                            id="csv_max_files"
                                            name="csv_max_files"
                                            type="number"
                                            min="1"
                                            max="50"
                                            step="1"
                                            defaultValue={
                                                settings.csv_max_files
                                            }
                                            onChange={(event) =>
                                                setCurrentFileLimit(
                                                    Number(
                                                        event.target.value,
                                                    ) || 0,
                                                )
                                            }
                                            aria-invalid={
                                                errors.csv_max_files
                                                    ? true
                                                    : undefined
                                            }
                                            className="h-11 tabular-nums"
                                        />
                                        {errors.csv_max_files ? (
                                            <InputError
                                                message={errors.csv_max_files}
                                            />
                                        ) : (
                                            <p className="text-sm leading-6 text-muted-foreground">
                                                Agents cannot select or submit
                                                more files than this limit in
                                                one upload.
                                            </p>
                                        )}
                                    </div>
                                    <div className="rounded-lg border border-info/20 bg-info/5 p-4">
                                        <div className="flex gap-3">
                                            <Info className="mt-0.5 size-4 shrink-0 text-info" />
                                            <div className="space-y-1 text-sm">
                                                <p className="font-medium">
                                                    Recommended configuration
                                                </p>
                                                <p className="leading-6 text-muted-foreground">
                                                    5 MB works well for routine
                                                    lead batches. Increase the
                                                    limit only when agents
                                                    regularly import larger
                                                    validated datasets.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </CardContent>
                                <CardFooter className="justify-between gap-4 border-t border-border/60 bg-muted/20 py-4">
                                    <div className="min-h-5 text-sm">
                                        {recentlySuccessful ? (
                                            <span className="flex items-center gap-2 text-success">
                                                <CheckCircle2 className="size-4" />
                                                Settings saved
                                            </span>
                                        ) : isDirty ? (
                                            <span className="text-muted-foreground">
                                                You have unsaved changes
                                            </span>
                                        ) : null}
                                    </div>
                                    <Button
                                        type="submit"
                                        disabled={processing || !isDirty}
                                    >
                                        <Save />
                                        {processing
                                            ? 'Saving…'
                                            : 'Save changes'}
                                    </Button>
                                </CardFooter>
                            </Card>
                        )}
                    </Form>

                    <Card className="gap-4">
                        <CardHeader>
                            <CardTitle className="text-base">
                                Upload checklist
                            </CardTitle>
                            <CardDescription>
                                Help agents avoid preventable import errors.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ul className="grid gap-4 text-sm">
                                {[
                                    'Use a unique header for every column.',
                                    'Keep company names and emails in separate columns.',
                                    'Use two-letter country codes when available.',
                                    'Review the mapping before processing.',
                                ].map((item) => (
                                    <li key={item} className="flex gap-3">
                                        <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-success" />
                                        <span className="leading-5 text-muted-foreground">
                                            {item}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

SettingsEdit.layout = { breadcrumbs: [{ title: 'Settings', href: edit() }] };
