import { Form, Head } from '@inertiajs/react';
import { UploadCloud } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { index, store } from '@/routes/uploads';
export default function UploadCreate() {
    return (
        <>
            <Head title="Upload Leads" />
            <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Upload leads"
                    description="Upload a CSV, review its column mapping, then process it."
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
                                    Choose a CSV file
                                </span>
                                <span className="text-sm text-muted-foreground">
                                    CSV or TXT, up to the configured limit
                                </span>
                                <input
                                    name="file"
                                    type="file"
                                    accept=".csv,text/csv"
                                    required
                                    className="max-w-full text-sm"
                                />
                            </label>
                            {errors.file && (
                                <p className="text-sm text-destructive">
                                    {errors.file}
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
