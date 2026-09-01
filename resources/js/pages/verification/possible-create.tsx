import { Form, Head, Link } from '@inertiajs/react';
import { Building2, Save, UserRound } from 'lucide-react';
import { toast } from 'sonner';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/verification';
import possibleLeads from '@/routes/verification/possible-leads';

type Agent = { id: number; name: string };
type Defaults = { lead_date: string; data_source: string };

const titleCaseName = (value: string) =>
    value
        .trim()
        .toLocaleLowerCase()
        .replace(/(^|[\s'-])\p{L}/gu, (letter) => letter.toLocaleUpperCase());

export default function PossibleLeadCreate({
    agents,
    defaults,
}: {
    agents: Agent[];
    defaults: Defaults;
}) {
    return (
        <>
            <Head title="Add Possible Lead" />
            <div className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Add possible lead"
                    description="Create a sales opportunity directly in the Possible Leads workspace and assign it to an active agent."
                    actions={
                        <Button asChild variant="outline">
                            <Link
                                href={index({
                                    query: { status: 'possible_lead' },
                                })}
                            >
                                Back to possible leads
                            </Link>
                        </Button>
                    }
                />

                <Form
                    {...possibleLeads.store.form()}
                    onError={(errors) => {
                        if (errors.company_name?.includes('maximum of 10')) {
                            toast.error(errors.company_name);
                        }
                    }}
                    className="space-y-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <section className="rounded-xl border bg-card p-5 shadow-sm">
                                <div className="mb-4 flex items-center gap-3">
                                    <div className="rounded-lg bg-primary/10 p-2 text-primary">
                                        <UserRound className="size-5" />
                                    </div>
                                    <div>
                                        <h2 className="font-semibold">
                                            Ownership and date
                                        </h2>
                                        <p className="text-sm text-muted-foreground">
                                            Choose the agent responsible for
                                            follow-up.
                                        </p>
                                    </div>
                                </div>
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <Label htmlFor="agent_id">
                                            Lead owner *
                                        </Label>
                                        <select
                                            id="agent_id"
                                            name="agent_id"
                                            required
                                            defaultValue={agents[0]?.id}
                                            className="mt-2 h-9 w-full rounded-md border bg-background px-3 text-sm"
                                        >
                                            <option value="">
                                                Select an active agent
                                            </option>
                                            {agents.map((agent) => (
                                                <option
                                                    key={agent.id}
                                                    value={agent.id}
                                                >
                                                    {agent.name}
                                                </option>
                                            ))}
                                        </select>
                                        {errors.agent_id && (
                                            <p className="mt-1 text-sm text-destructive">
                                                {errors.agent_id}
                                            </p>
                                        )}
                                    </div>
                                    <div>
                                        <Label htmlFor="lead_date">
                                            Date *
                                        </Label>
                                        <Input
                                            id="lead_date"
                                            name="lead_date"
                                            type="date"
                                            required
                                            defaultValue={defaults.lead_date}
                                            className="mt-2"
                                        />
                                        {errors.lead_date && (
                                            <p className="mt-1 text-sm text-destructive">
                                                {errors.lead_date}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            </section>

                            <section className="rounded-xl border bg-card p-5 shadow-sm">
                                <div className="mb-4 flex items-center gap-3">
                                    <div className="rounded-lg bg-primary/10 p-2 text-primary">
                                        <Building2 className="size-5" />
                                    </div>
                                    <div>
                                        <h2 className="font-semibold">
                                            Contact and company details
                                        </h2>
                                        <p className="text-sm text-muted-foreground">
                                            Add enough detail for verification
                                            and sales follow-up.
                                        </p>
                                    </div>
                                </div>
                                <div className="grid gap-4 md:grid-cols-2">
                                    {[
                                        ['company_name', 'Company *', 'text'],
                                        ['website', 'Website', 'text'],
                                        [
                                            'contact_person',
                                            'Contact person',
                                            'text',
                                        ],
                                        ['position', 'Position', 'text'],
                                        ['email', 'Email', 'email'],
                                        ['phone', 'Phone', 'text'],
                                        ['industry', 'Industry', 'text'],
                                        [
                                            'business_type',
                                            'Business type',
                                            'text',
                                        ],
                                        ['address', 'Address', 'text'],
                                        ['city', 'City / State', 'text'],
                                        [
                                            'country_code',
                                            'Country code',
                                            'text',
                                        ],
                                        ['linkedin_url', 'LinkedIn', 'url'],
                                        ['source_url', 'Source link', 'url'],
                                    ].map(([name, label, type]) => (
                                        <div
                                            key={name}
                                            className={
                                                [
                                                    'address',
                                                    'source_url',
                                                ].includes(name)
                                                    ? 'md:col-span-2'
                                                    : ''
                                            }
                                        >
                                            <Label htmlFor={name}>
                                                {label}
                                            </Label>
                                            <Input
                                                id={name}
                                                name={name}
                                                type={type}
                                                required={
                                                    name === 'company_name'
                                                }
                                                maxLength={
                                                    name === 'country_code'
                                                        ? 2
                                                        : undefined
                                                }
                                                onBlur={
                                                    name === 'contact_person'
                                                        ? (event) => {
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
                                            {errors[name] && (
                                                <p className="mt-1 text-sm text-destructive">
                                                    {errors[name]}
                                                </p>
                                            )}
                                        </div>
                                    ))}
                                    <div>
                                        <Label htmlFor="data_source">
                                            Source of data
                                        </Label>
                                        <select
                                            id="data_source"
                                            name="data_source"
                                            defaultValue={defaults.data_source}
                                            className="mt-2 h-9 w-full rounded-md border bg-background px-3 text-sm"
                                        >
                                            {[
                                                'Manual',
                                                'Email',
                                                'Tendata',
                                                'Lusha',
                                                'Tendata/Lusha',
                                            ].map((source) => (
                                                <option
                                                    key={source}
                                                    value={source}
                                                >
                                                    {source}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div className="md:col-span-2">
                                        <Label htmlFor="notes">
                                            Opportunity notes
                                        </Label>
                                        <textarea
                                            id="notes"
                                            name="notes"
                                            rows={4}
                                            placeholder="Why is this contact a possible lead? Include requirements, timing, or next steps."
                                            className="mt-2 w-full rounded-md border bg-background p-3 text-sm"
                                        />
                                    </div>
                                </div>
                            </section>

                            {!agents.length && (
                                <p className="rounded-lg border border-destructive/30 bg-destructive/10 p-4 text-sm text-destructive">
                                    Add or activate an agent before creating a
                                    possible lead.
                                </p>
                            )}
                            <div className="flex justify-end gap-3">
                                <Button asChild type="button" variant="outline">
                                    <Link href={index()}>Cancel</Link>
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={processing || !agents.length}
                                >
                                    <Save />
                                    {processing
                                        ? 'Saving…'
                                        : 'Save possible lead'}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

PossibleLeadCreate.layout = {
    breadcrumbs: [
        { title: 'Verification', href: index() },
        { title: 'Add Possible Lead', href: possibleLeads.create() },
    ],
};
