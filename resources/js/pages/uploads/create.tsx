import { Form, Head } from '@inertiajs/react';
import { UploadCloud } from 'lucide-react';
import { useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { index, store } from '@/routes/uploads';
export default function UploadCreate() {
    const [selectedFileCount, setSelectedFileCount] = useState(0);

    return (
        <>
            <Head title="Upload Leads" />
            <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Upload leads"
                    description="Upload one raw CSV for mapping review, or select multiple compatible files to clean them together."
                />
                <Form
                    {...store.form()}
                    className="rounded-xl border bg-card p-6"
                >
                    {({ errors, processing, progress }) => (
                        <div className="flex flex-col gap-5">
                            <label className="flex cursor-pointer flex-col items-center gap-3 rounded-xl border-2 border-dashed p-12 text-center hover:bg-muted/40">
                                <UploadCloud className="size-10 text-primary" />
                                <span className="font-medium">
                                    Choose raw CSV files
                                </span>
                                <span className="text-sm text-muted-foreground">
                                    Select up to 50 CSV or TXT files
                                </span>
                                <input
                                    name="files[]"
                                    type="file"
                                    accept=".csv,text/csv"
                                    multiple
                                    required
                                    className="max-w-full text-sm"
                                    onChange={(event) =>
                                        setSelectedFileCount(
                                            event.target.files?.length ?? 0,
                                        )
                                    }
                                />
                                {selectedFileCount > 0 && (
                                    <span className="rounded-full bg-primary/10 px-3 py-1 text-sm font-medium text-primary">
                                        {selectedFileCount}{' '}
                                        {selectedFileCount === 1
                                            ? 'file selected'
                                            : 'files selected'}
                                    </span>
                                )}
                            </label>
                            {Object.values(errors)[0] && (
                                <p className="text-sm text-destructive">
                                    {Object.values(errors)[0]}
                                </p>
                            )}
                            {progress && (
                                <div className="h-2 overflow-hidden rounded-full bg-muted">
                                    <div
                                        className="h-full bg-primary"
                                        style={{
                                            width: `${progress.percentage}%`,
                                        }}
                                    />
                                </div>
                            )}
                            <Button type="submit" disabled={processing}>
                                {processing
                                    ? 'Uploading…'
                                    : selectedFileCount > 1
                                      ? `Upload and clean ${selectedFileCount} files`
                                      : 'Review column mapping'}
                            </Button>
                        </div>
                    )}
                </Form>
            </div>
        </>
    );
}
UploadCreate.layout = {
    breadcrumbs: [
        { title: 'Upload History', href: index() },
        { title: 'Upload Leads' },
    ],
};
