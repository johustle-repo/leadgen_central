import { Form, Head } from '@inertiajs/react';
import { toast } from 'sonner';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, store, update } from '@/routes/leads';
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
                    }}
                    className="flex flex-col gap-6"
                >
                    {({ errors, processing }) => (
                        <>
                            {agents.length > 0 && (
                                <section className="rounded-xl border bg-card p-5">
                                    <Label htmlFor="agent_id">Lead owner</Label>
                                    <select
                                        id="agent_id"
                                        name="agent_id"
                                        defaultValue={String(
                                            lead?.agent_id ??
                                                defaults.agent_id ??
                                                agents[0]?.id,
                                        )}
                                        className="mt-2 h-9 w-full rounded-md border bg-background px-3 text-sm"
                                    >
                                        {agents.map((agent) => (
                                            <option
                                                key={agent.id}
                                                value={agent.id}
                                            >
                                                {agent.name}
                                            </option>
                                        ))}
                                    </select>
                                </section>
                            )}
                            <section className="rounded-xl border bg-card p-5">
                                <h2 className="mb-4 font-semibold">
                                    Raw lead details
                                </h2>
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
                                                    field.name === 'source_url'
                                                        ? 'md:col-span-2'
                                                        : ''
                                                }
                                            >
                                                <Label htmlFor={field.name}>
                                                    {field.label}
                                                    {field.required ? ' *' : ''}
                                                </Label>
                                                {field.name ===
                                                'data_source' ? (
                                                    <select
                                                        id={field.name}
                                                        name={field.name}
                                                        defaultValue={value}
                                                        className="mt-2 h-9 w-full rounded-md border bg-background px-3 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                                    >
                                                        <option value="">
                                                            Select a source
                                                        </option>
                                                        {dataSources.map(
                                                            (source) => (
                                                                <option
                                                                    key={source}
                                                                    value={
                                                                        source
                                                                    }
                                                                >
                                                                    {source}
                                                                </option>
                                                            ),
                                                        )}
                                                    </select>
                                                ) : (
                                                    <Input
                                                        id={field.name}
                                                        name={field.name}
                                                        type={
                                                            field.type ?? 'text'
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
                                                        className="mt-2"
                                                    />
                                                )}
                                                {errors[field.name] && (
                                                    <p className="mt-1 text-sm text-destructive">
                                                        {errors[field.name]}
                                                    </p>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                            </section>
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
