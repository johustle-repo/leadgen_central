import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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

const DO_NOT_IMPORT = '__skip__';

function MappingRowSelect({
    name,
    defaultValue,
    fields,
}: {
    name: string;
    defaultValue: string;
    fields: string[];
}) {
    const [value, setValue] = useState(defaultValue || DO_NOT_IMPORT);

    return (
        <>
            <input
                type="hidden"
                name={name}
                value={value === DO_NOT_IMPORT ? '' : value}
            />
            <Select value={value} onValueChange={setValue}>
                <SelectTrigger className="w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={DO_NOT_IMPORT}>
                        Do not import
                    </SelectItem>
                    {fields.map((field) => (
                        <SelectItem key={field} value={field}>
                            {label(field)}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </>
    );
}

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
                            {batch.headers.map((header, index) =>
                                header.trim() !== '' ? (
                                    <div
                                        key={`${index}-${header}`}
                                        className="grid grid-cols-2 items-center gap-4"
                                    >
                                        <span className="truncate text-sm">
                                            {header}
                                        </span>
                                        <MappingRowSelect
                                            name={`mapping[${index}]`}
                                            defaultValue={
                                                batch.column_mapping[header] ??
                                                ''
                                            }
                                            fields={fields}
                                        />
                                    </div>
                                ) : (
                                    <div
                                        key={`${index}-blank`}
                                        className="grid grid-cols-2 items-center gap-4 text-sm text-muted-foreground"
                                    >
                                        <span className="truncate">
                                            Column {index + 1} (no heading)
                                        </span>
                                        <span>Not imported</span>
                                    </div>
                                ),
                            )}
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
