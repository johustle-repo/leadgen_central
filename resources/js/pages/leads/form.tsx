import { Form, Head } from '@inertiajs/react';
import { FileText, UserRound } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index, store, update } from '@/routes/leads';

const NO_DATA_SOURCE = '__none__';

function DataSourceSelect({
    id,
    name,
    defaultValue,
}: {
    id: string;
    name: string;
    defaultValue: string;
}) {
    const [value, setValue] = useState(defaultValue || NO_DATA_SOURCE);

    return (
        <>
            <input
                type="hidden"
                name={name}
                value={value === NO_DATA_SOURCE ? '' : value}
            />
            <Select value={value} onValueChange={setValue}>
                <SelectTrigger id={id} className="mt-2 w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={NO_DATA_SOURCE}>
                        Select a source
                    </SelectItem>
                    {dataSources.map((source) => (
                        <SelectItem key={source} value={source}>
                            {source}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </>
    );
}
type Lead = Record<string, string | number | null> & {
    id: number;
    company_name: string;
};
const fields = [
    { name: 'lead_date', label: 'Date', type: 'date' },
    { name: 'company_name', label: 'Company', required: true },
    { name: 'website', label: 'Website' },
    { name: 'contact_person', label: 'First Name' },
    { name: 'email', label: 'Email', type: 'email' },
    { name: 'country_code', label: 'Country', maxLength: 2 },
    { name: 'city', label: 'City' },
    { name: 'import_trades', label: 'Import Trades' },
    { name: 'linkedin_url', label: 'LinkedIn', type: 'url' },
    { name: 'data_source', label: 'Sources of Data' },
    { name: 'source_url', label: 'Link', type: 'url' },
];
const dataSources = ['Tendata', 'Lusha', 'Tendata/Lusha', 'Email', 'Manual'];
const titleCaseName = (value: string) =>
    value
        .trim()
        .toLocaleLowerCase()
        .replace(/(^|[\s'-])\p{L}/gu, (letter) => letter.toLocaleUpperCase());

export default function LeadForm({
    lead,
    defaults,
    formVersion,
    agents,
}: {
    lead: Lead | null;
    defaults: Record<string, string | number | null>;
    formVersion: number;
    agents: Array<{ id: number; name: string }>;
}) {
    const form = lead ? update.form(lead.id) : store.form();

    return (
        <>
            <Head title={lead ? `Edit ${lead.company_name}` : 'Add Lead'} />
            <div className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={lead ? 'Edit lead' : 'Add lead'}
                    description="Enter the same details used in the raw lead file."
                />
                <Form
                    key={formVersion}
                    {...form}
                    resetOnSuccess={!lead}
                    onError={(errors) => {
                        const companyError = errors.company_name;

                        if (
                            typeof companyError === 'string' &&
                            companyError.includes('maximum of 10')
                        ) {
                            toast.error(companyError);
                        }

                        const emailError = errors.email;

                        if (
                            typeof emailError === 'string' &&
                            emailError.includes('already saved')
                        ) {
                            toast.error(emailError);
                        }
                    }}
                    className="flex flex-col gap-6"
                >
                    {({ errors, processing }) => (
                        <>
                            {agents.length > 0 && (
                                <Card>
                                    <CardHeader>
                                        <div className="flex items-center gap-3">
                                            <div className="rounded-lg bg-primary/10 p-2 text-primary">
                                                <UserRound className="size-5" />
                                            </div>
                                            <div>
                                                <CardTitle>
                                                    Lead owner
                                                </CardTitle>
                                                <CardDescription>
                                                    Choose which agent this lead
                                                    belongs to.
                                                </CardDescription>
                                            </div>
                                        </div>
                                    </CardHeader>
                                    <CardContent>
                                        <Label htmlFor="agent_id">Agent</Label>
                                        <Select
                                            name="agent_id"
                                            defaultValue={String(
                                                lead?.agent_id ??
                                                    defaults.agent_id ??
                                                    agents[0]?.id,
                                            )}
                                        >
                                            <SelectTrigger
                                                id="agent_id"
                                                className="mt-2 w-full"
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {agents.map((agent) => (
                                                    <SelectItem
                                                        key={agent.id}
                                                        value={String(agent.id)}
                                                    >
                                                        {agent.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </CardContent>
                                </Card>
                            )}
                            <Card>
                                <CardHeader>
                                    <div className="flex items-center gap-3">
                                        <div className="rounded-lg bg-primary/10 p-2 text-primary">
                                            <FileText className="size-5" />
                                        </div>
                                        <div>
                                            <CardTitle>
                                                Raw lead details
                                            </CardTitle>
                                            <CardDescription>
                                                Match the fields from the raw
                                                lead source.
                                            </CardDescription>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <div className="grid gap-4 md:grid-cols-2">
                                        {fields.map((field) => {
                                            const value = String(
                                                lead?.[field.name] ??
                                                    defaults[field.name] ??
                                                    '',
                                            );

                                            return (
                                                <div
                                                    key={field.name}
                                                    className={
                                                        field.name ===
                                                        'source_url'
                                                            ? 'md:col-span-2'
                                                            : ''
                                                    }
                                                >
                                                    <Label htmlFor={field.name}>
                                                        {field.label}
                                                        {field.required
                                                            ? ' *'
                                                            : ''}
                                                    </Label>
                                                    {field.name ===
                                                    'data_source' ? (
                                                        <DataSourceSelect
                                                            id={field.name}
                                                            name={field.name}
                                                            defaultValue={value}
                                                        />
                                                    ) : (
                                                        <Input
                                                            id={field.name}
                                                            name={field.name}
                                                            type={
                                                                field.type ??
                                                                'text'
                                                            }
                                                            defaultValue={
                                                                field.type ===
                                                                'date'
                                                                    ? value.slice(
                                                                          0,
                                                                          10,
                                                                      )
                                                                    : value
                                                            }
                                                            required={
                                                                field.required
                                                            }
                                                            maxLength={
                                                                field.maxLength
                                                            }
                                                            onBlur={
                                                                field.name ===
                                                                'contact_person'
                                                                    ? (
                                                                          event,
                                                                      ) => {
                                                                          event.currentTarget.value =
                                                                              titleCaseName(
                                                                                  event
                                                                                      .currentTarget
                                                                                      .value,
                                                                              );
                                                                      }
                                                                    : undefined
                                                            }
                                                            className="mt-2"
                                                        />
                                                    )}
                                                    <InputError
                                                        className="mt-1"
                                                        message={
                                                            errors[field.name]
                                                        }
                                                    />
                                                </div>
                                            );
                                        })}
                                    </div>
                                </CardContent>
                            </Card>
                            <div className="flex justify-end gap-3">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => history.back()}
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Saving…' : 'Save lead'}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
LeadForm.layout = { breadcrumbs: [{ title: 'Leads', href: index() }] };
