import { Head, useForm } from '@inertiajs/react';
import { UploadCloud } from 'lucide-react';
import { useState } from 'react';
import type { DragEvent } from 'react';
import InputError from '@/components/input-error';
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
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { index, store } from '@/routes/uploads';

const ACCEPTED_EXTENSIONS = ['.csv', '.txt'];

const isAcceptedFile = (file: File) =>
    ACCEPTED_EXTENSIONS.some((extension) =>
        file.name.toLowerCase().endsWith(extension),
    );

export default function UploadCreate() {
    const [confirmationOpen, setConfirmationOpen] = useState(false);
    const [isDragging, setIsDragging] = useState(false);
    const upload = useForm<{
        file: File | null;
        duplicate_handling: 'flag' | 'update_missing';
    }>({ file: null, duplicate_handling: 'flag' });
    const selectedFile = upload.data.file;

    const selectFile = (file: File | undefined | null) => {
        if (!file) {
            return;
        }

        if (!isAcceptedFile(file)) {
            upload.setError('file', 'Select a CSV or TXT file.');

            return;
        }

        upload.clearErrors('file');
        upload.setData('file', file);
    };

    const confirmUpload = (duplicateHandling: 'flag' | 'update_missing') => {
        setConfirmationOpen(false);
        upload.transform((data) => ({
            ...data,
            duplicate_handling: duplicateHandling,
        }));
        upload.post(store.url(), { forceFormData: true });
    };

    return (
        <>
            <Head title="Upload Leads" />
            <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        setConfirmationOpen(true);
                    }}
                >
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-3">
                                <div className="rounded-lg bg-primary/10 p-2 text-primary">
                                    <UploadCloud className="size-5" />
                                </div>
                                <div>
                                    <CardTitle>Raw CSV file</CardTitle>
                                    <CardDescription>
                                        Goes to column mapping review. Columns
                                        are auto-detected, and any that
                                        don&apos;t match are left blank rather
                                        than rejecting the file.
                                    </CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="flex flex-col gap-5">
                                <label
                                    onDragOver={(
                                        event: DragEvent<HTMLLabelElement>,
                                    ) => {
                                        event.preventDefault();
                                        setIsDragging(true);
                                    }}
                                    onDragLeave={(
                                        event: DragEvent<HTMLLabelElement>,
                                    ) => {
                                        event.preventDefault();
                                        setIsDragging(false);
                                    }}
                                    onDrop={(
                                        event: DragEvent<HTMLLabelElement>,
                                    ) => {
                                        event.preventDefault();
                                        setIsDragging(false);
                                        selectFile(
                                            event.dataTransfer.files?.[0],
                                        );
                                    }}
                                    className={`flex cursor-pointer flex-col items-center gap-3 rounded-xl border-2 border-dashed p-12 text-center transition-colors focus-within:ring-2 focus-within:ring-ring hover:bg-muted/40 ${
                                        isDragging
                                            ? 'border-primary bg-primary/5'
                                            : ''
                                    }`}
                                >
                                    <UploadCloud className="size-10 text-primary" />
                                    <span className="font-medium">
                                        Drag and drop a raw CSV file
                                    </span>
                                    <span className="text-sm text-muted-foreground">
                                        or choose a CSV or TXT file below
                                    </span>
                                    <input
                                        type="file"
                                        accept=".csv,text/csv"
                                        required
                                        className="sr-only"
                                        onChange={(event) =>
                                            selectFile(event.target.files?.[0])
                                        }
                                    />
                                    <span className="inline-flex min-w-36 items-center justify-center rounded-md border bg-background px-4 py-2 text-sm font-medium shadow-xs hover:bg-muted">
                                        Choose file
                                    </span>
                                    <span
                                        className={
                                            selectedFile
                                                ? 'rounded-full bg-primary/10 px-3 py-1 text-sm font-medium text-primary'
                                                : 'text-sm text-muted-foreground'
                                        }
                                    >
                                        {selectedFile
                                            ? selectedFile.name
                                            : 'No file selected'}
                                    </span>
                                </label>
                                <InputError
                                    message={Object.values(upload.errors)[0]}
                                />
                                {upload.progress && (
                                    <div className="h-2 overflow-hidden rounded-full bg-muted">
                                        <div
                                            className="h-full bg-primary"
                                            style={{
                                                width: `${upload.progress.percentage}%`,
                                            }}
                                        />
                                    </div>
                                )}
                                <Button
                                    type="submit"
                                    disabled={
                                        upload.processing || !selectedFile
                                    }
                                >
                                    {upload.processing
                                        ? 'Uploading…'
                                        : 'Review column mapping'}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </form>
            </div>

            <Dialog open={confirmationOpen} onOpenChange={setConfirmationOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            How should matching leads be handled?
                        </DialogTitle>
                        <DialogDescription>
                            The CSV date is detected automatically. Choose
                            whether matching leads owned by you should receive
                            missing information from this upload.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="rounded-lg border bg-muted/40 p-4 text-sm text-muted-foreground">
                        Update missing information applies the CSV date and
                        repairs other blank fields. Other existing information
                        is not overwritten, and leads owned by another agent are
                        not changed.
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => confirmUpload('flag')}
                        >
                            Keep as duplicates
                        </Button>
                        <Button
                            type="button"
                            onClick={() => confirmUpload('update_missing')}
                        >
                            Update missing information
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

UploadCreate.layout = {
    breadcrumbs: [
        { title: 'Upload History', href: index() },
        { title: 'Upload Leads' },
    ],
};
