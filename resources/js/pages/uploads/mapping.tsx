import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
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
                    <SelectItem value={DO_NOT_IMPORT}>Do not import</SelectItem>
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
                <Form {...process.form(batch.id)}>
                    {({ errors, processing }) => (
                        <div className="flex flex-col gap-4">
                            <Table>
                                <TableHeader>
                                    <TableRow className="hover:bg-transparent">
                                        <TableHead>CSV heading</TableHead>
                                        <TableHead>Lead field</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {batch.headers.map((header, index) =>
                                        header.trim() !== '' ? (
                                            <TableRow
                                                key={`${index}-${header}`}
                                            >
                                                <TableCell className="truncate">
                                                    {header}
                                                </TableCell>
                                                <TableCell>
                                                    <MappingRowSelect
                                                        name={`mapping[${index}]`}
                                                        defaultValue={
                                                            batch
                                                                .column_mapping[
                                                                header
                                                            ] ?? ''
                                                        }
                                                        fields={fields}
                                                    />
                                                </TableCell>
                                            </TableRow>
                                        ) : (
                                            <TableRow
                                                key={`${index}-blank`}
                                                className="text-muted-foreground"
                                            >
                                                <TableCell className="truncate">
                                                    Column {index + 1} (no
                                                    heading)
                                                </TableCell>
                                                <TableCell>
                                                    Not imported
                                                </TableCell>
                                            </TableRow>
                                        ),
                                    )}
                                </TableBody>
                            </Table>
                            <InputError message={errors.mapping} />
                            <Button
                                type="submit"
                                disabled={processing}
                                className="self-start"
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
