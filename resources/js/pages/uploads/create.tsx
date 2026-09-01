import { Head, useForm } from '@inertiajs/react';
import { UploadCloud } from 'lucide-react';
import { useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { index, store } from '@/routes/uploads';

export default function UploadCreate() {
    const [confirmationOpen, setConfirmationOpen] = useState(false);
    const upload = useForm<{
        files: File[];
        duplicate_handling: 'flag' | 'update_missing';
    }>({ files: [], duplicate_handling: 'flag' });
    const selectedFileCount = upload.data.files.length;

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
                <PageHeader
                    title="Upload leads"
                    description="Upload one raw CSV for mapping review, or select multiple compatible files to clean them together."
                />
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        setConfirmationOpen(true);
                    }}
                    className="rounded-xl border bg-card p-6"
                >
                    <div className="flex flex-col gap-5">
                        <label className="flex cursor-pointer flex-col items-center gap-3 rounded-xl border-2 border-dashed p-12 text-center focus-within:ring-2 focus-within:ring-ring hover:bg-muted/40">
                            <UploadCloud className="size-10 text-primary" />
                            <span className="font-medium">
                                Choose raw CSV files
                            </span>
                            <span className="text-sm text-muted-foreground">
                                Select up to 50 CSV or TXT files
                            </span>
                            <input
                                type="file"
                                accept=".csv,text/csv"
                                multiple
                                required
                                className="sr-only"
                                onChange={(event) =>
                                    upload.setData(
                                        'files',
                                        Array.from(event.target.files ?? []),
                                    )
                                }
                            />
                            <span className="inline-flex min-w-36 items-center justify-center rounded-md border bg-background px-4 py-2 text-sm font-medium shadow-xs hover:bg-muted">
                                Choose files
                            </span>
                            <span
                                className={
                                    selectedFileCount > 0
                                        ? 'rounded-full bg-primary/10 px-3 py-1 text-sm font-medium text-primary'
                                        : 'text-sm text-muted-foreground'
                                }
                            >
                                {selectedFileCount > 0 ? (
                                    <>
                                        {selectedFileCount}{' '}
                                        {selectedFileCount === 1
                                            ? 'file selected'
                                            : 'files selected'}
                                    </>
                                ) : (
                                    'No files selected'
                                )}
                            </span>
                        </label>
                        {Object.values(upload.errors)[0] && (
                            <p className="text-sm text-destructive">
                                {Object.values(upload.errors)[0]}
                            </p>
                        )}
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
                                upload.processing || selectedFileCount === 0
                            }
                        >
                            {upload.processing
                                ? 'Uploading…'
                                : selectedFileCount > 1
                                  ? `Upload and clean ${selectedFileCount} files`
                                  : 'Review column mapping'}
                        </Button>
                    </div>
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
