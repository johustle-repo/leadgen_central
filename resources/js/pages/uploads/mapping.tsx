import { Form, Head } from '@inertiajs/react';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { process } from '@/routes/uploads';
type Batch = {
    id: number;
    batch_code: string;
    original_filename: string;
    headers: string[];
    column_mapping: Record<string, string | null>;
};
const label = (value: string) =>
    value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (character) => character.toUpperCase());
export default function UploadMapping({
    batch,
    fields,
}: {
    batch: Batch;
    fields: string[];
}) {
    return (
        <>
            <Head title="Map CSV Columns" />
            <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Map CSV columns"
                    description={`${batch.original_filename} · ${batch.batch_code}`}
                />
                <Form
                    {...process.form(batch.id)}
                    className="rounded-xl border bg-card p-6"
                >
                    {({ errors, processing }) => (
                        <div className="flex flex-col gap-4">
                            <div className="grid grid-cols-2 gap-4 border-b pb-3 text-sm font-medium">
                                <span>CSV heading</span>
                                <span>Lead field</span>
                            </div>
                            {batch.headers.map((header, index) => (
                                <div
                                    key={`${index}-${header}`}
                                    className="grid grid-cols-2 items-center gap-4"
                                >
                                    <span className="truncate text-sm">
                                        {header.trim() !== ''
                                            ? header
                                            : `Column ${index + 1} (no header — likely a row number; leave as "Do not import")`}
                                    </span>
                                    <select
                                        name={`mapping[${index}]`}
                                        defaultValue={
                                            batch.column_mapping[header] ?? ''
                                        }
                                        className="h-9 rounded-md border bg-background px-3 text-sm"
                                    >
                                        <option value="">Do not import</option>
                                        {fields.map((field) => (
                                            <option key={field} value={field}>
                                                {label(field)}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            ))}
                            {errors.mapping && (
                                <p className="text-sm text-destructive">
                                    {errors.mapping}
                                </p>
                            )}
                            <Button
                                type="submit"
                                disabled={processing}
                                className="mt-3"
                            >
                                {processing
                                    ? 'Starting…'
                                    : 'Confirm and process'}
                            </Button>
                        </div>
                    )}
                </Form>
            </div>
        </>
    );
}
