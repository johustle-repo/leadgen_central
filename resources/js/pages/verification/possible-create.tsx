import { Form, Head, Link } from '@inertiajs/react';
import {
    Building2,
    Paperclip,
    Save,
    Send,
    StickyNote,
    UserRound,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { HeaderActionsPortal } from '@/components/header-actions';
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
import { COUNTRY_CAPITALS } from '@/lib/country-capitals';
import { index } from '@/routes/verification';
import possibleLeads from '@/routes/verification/possible-leads';

const SELECT_AGENT = '__select__';
const SELECT_COUNTRY = '__select__';
const COUNTRY_OPTIONS = Object.entries(COUNTRY_CAPITALS)
    .map(([code, { name }]) => ({ code, name }))
    .sort((a, b) => a.name.localeCompare(b.name));

type Agent = { id: number; name: string };
type Defaults = { lead_date: string; data_source: string };

const titleCaseName = (value: string) =>
    value
        .trim()
        .toLocaleLowerCase()
        .replace(/(^|[\s'-])\p{L}/gu, (letter) => letter.toLocaleUpperCase());

function SimpleField({
    name,
    label,
    type,
    errors,
}: {
    name: string;
    label: string;
    type: string;
    errors: Record<string, string | undefined>;
}) {
    return (
        <div>
            <Label htmlFor={name}>{label}</Label>
            <Input
                id={name}
                name={name}
                type={type}
                required={name === 'company_name'}
                onBlur={
                    name === 'contact_person'
                        ? (event) => {
                              event.currentTarget.value = titleCaseName(
                                  event.currentTarget.value,
                              );
                          }
                        : undefined
                }
                className="mt-2"
            />
            {errors[name] && (
                <p className="mt-1 text-sm text-destructive">{errors[name]}</p>
            )}
        </div>
    );
}

export default function PossibleLeadCreate({
    agents,
    defaults,
}: {
    agents: Agent[];
    defaults: Defaults;
}) {
    const [agentId, setAgentId] = useState(
        agents[0] ? String(agents[0].id) : SELECT_AGENT,
    );
    const [countryCode, setCountryCode] = useState(SELECT_COUNTRY);

    return (
        <>
            <Head title="Add Possible Lead" />
            <div className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
                <HeaderActionsPortal>
                    <Button asChild size="sm" variant="outline">
                        <Link
                            href={index({
                                query: { status: 'possible_lead' },
                            })}
                        >
                            Back to possible leads
                        </Link>
                    </Button>
                </HeaderActionsPortal>

                <div className="grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)]">
                    <Form
                        {...possibleLeads.store.form()}
                        onError={(errors) => {
                            if (
                                errors.company_name?.includes('maximum of 10')
                            ) {
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
                                            <input
                                                type="hidden"
                                                name="agent_id"
                                                value={
                                                    agentId === SELECT_AGENT
                                                        ? ''
                                                        : agentId
                                                }
                                            />
                                            <Select
                                                value={agentId}
                                                onValueChange={setAgentId}
                                            >
                                                <SelectTrigger
                                                    id="agent_id"
                                                    className="mt-2 w-full"
                                                >
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem
                                                        value={SELECT_AGENT}
                                                    >
                                                        Select an active agent
                                                    </SelectItem>
                                                    {agents.map((agent) => (
                                                        <SelectItem
                                                            key={agent.id}
                                                            value={String(
                                                                agent.id,
                                                            )}
                                                        >
                                                            {agent.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
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
                                                defaultValue={
                                                    defaults.lead_date
                                                }
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
                                                Add enough detail for
                                                verification and sales
                                                follow-up.
                                            </p>
                                        </div>
                                    </div>
                                    <div className="grid gap-4 md:grid-cols-2">
                                        <SimpleField
                                            name="company_name"
                                            label="Company *"
                                            type="text"
                                            errors={errors}
                                        />
                                        <SimpleField
                                            name="website"
                                            label="Website"
                                            type="text"
                                            errors={errors}
                                        />
                                        <SimpleField
                                            name="contact_person"
                                            label="Contact person"
                                            type="text"
                                            errors={errors}
                                        />
                                        <SimpleField
                                            name="position"
                                            label="Position"
                                            type="text"
                                            errors={errors}
                                        />
                                        <SimpleField
                                            name="email"
                                            label="Email"
                                            type="email"
                                            errors={errors}
                                        />
                                        <SimpleField
                                            name="secondary_email"
                                            label="Secondary Email"
                                            type="email"
                                            errors={errors}
                                        />
                                        <SimpleField
                                            name="phone"
                                            label="Phone"
                                            type="text"
                                            errors={errors}
                                        />
                                        <div>
                                            <Label htmlFor="country">
                                                Country
                                            </Label>
                                            <input
                                                type="hidden"
                                                name="country"
                                                value={
                                                    countryCode ===
                                                    SELECT_COUNTRY
                                                        ? ''
                                                        : COUNTRY_CAPITALS[
                                                              countryCode
                                                          ].name
                                                }
                                            />
                                            <input
                                                type="hidden"
                                                name="country_code"
                                                value={
                                                    countryCode ===
                                                    SELECT_COUNTRY
                                                        ? ''
                                                        : countryCode
                                                }
                                            />
                                            <Select
                                                value={countryCode}
                                                onValueChange={setCountryCode}
                                            >
                                                <SelectTrigger
                                                    id="country"
                                                    className="mt-2 w-full"
                                                >
                                                    <SelectValue placeholder="Select a country" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem
                                                        value={SELECT_COUNTRY}
                                                    >
                                                        Select a country
                                                    </SelectItem>
                                                    {COUNTRY_OPTIONS.map(
                                                        ({ code, name }) => (
                                                            <SelectItem
                                                                key={code}
                                                                value={code}
                                                            >
                                                                {name}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                Country code is set
                                                automatically from this
                                                selection.
                                            </p>
                                            {errors.country && (
                                                <p className="mt-1 text-sm text-destructive">
                                                    {errors.country}
                                                </p>
                                            )}
                                        </div>
                                        <SimpleField
                                            name="city"
                                            label="City / State"
                                            type="text"
                                            errors={errors}
                                        />
                                        <SimpleField
                                            name="linkedin_url"
                                            label="LinkedIn"
                                            type="url"
                                            errors={errors}
                                        />
                                        <div>
                                            <Label htmlFor="data_source">
                                                Source of data
                                            </Label>
                                            <Select
                                                name="data_source"
                                                defaultValue={
                                                    defaults.data_source ||
                                                    'Manual'
                                                }
                                            >
                                                <SelectTrigger
                                                    id="data_source"
                                                    className="mt-2 w-full"
                                                >
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {[
                                                        'Manual',
                                                        'Email',
                                                        'Tendata',
                                                        'Lusha',
                                                        'Tendata/Lusha',
                                                    ].map((source) => (
                                                        <SelectItem
                                                            key={source}
                                                            value={source}
                                                        >
                                                            {source}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <SimpleField
                                            name="source_url"
                                            label="Source link"
                                            type="url"
                                            errors={errors}
                                        />
                                        <SimpleField
                                            name="product_requested"
                                            label="Product requested"
                                            type="text"
                                            errors={errors}
                                        />
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
                                        Add or activate an agent before creating
                                        a possible lead.
                                    </p>
                                )}
                                <div className="flex justify-end gap-3">
                                    <Button
                                        asChild
                                        type="button"
                                        variant="outline"
                                    >
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
                    <aside className="flex flex-col gap-4">
                        <Card>
                            <CardHeader className="flex-row items-start justify-between">
                                <div className="flex items-center gap-3">
                                    <div className="rounded-lg bg-primary/10 p-2 text-primary">
                                        <Paperclip className="size-5" />
                                    </div>
                                    <div>
                                        <CardTitle>Contact documents</CardTitle>
                                        <CardDescription>
                                            Private PDF, Excel, CSV, or Word
                                            files, up to 20 MB each.
                                        </CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <p className="rounded-lg bg-muted/50 p-3 text-xs text-muted-foreground">
                                    Save this lead first - you&apos;ll land on
                                    its detail page where you can attach
                                    supporting documents.
                                </p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <div className="flex items-center gap-3">
                                    <div className="rounded-lg bg-primary/10 p-2 text-primary">
                                        <StickyNote className="size-5" />
                                    </div>
                                    <div>
                                        <CardTitle>Notes</CardTitle>
                                        <CardDescription>
                                            Internal notes for this lead.
                                        </CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <p className="rounded-lg bg-muted/50 p-3 text-xs text-muted-foreground">
                                    Save this lead first - you&apos;ll be able
                                    to add notes from its detail page.
                                </p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <div className="flex items-center gap-3">
                                    <div className="rounded-lg bg-primary/10 p-2 text-primary">
                                        <Send className="size-5" />
                                    </div>
                                    <div>
                                        <CardTitle>
                                            Forward qualified lead
                                        </CardTitle>
                                        <CardDescription>
                                            Send this lead to another team or
                                            contact.
                                        </CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <p className="rounded-lg bg-muted/50 p-3 text-xs text-muted-foreground">
                                    Save this lead and mark it Qualified from
                                    its detail page to forward it.
                                </p>
                            </CardContent>
                        </Card>
                    </aside>
                </div>
            </div>
        </>
    );
}

PossibleLeadCreate.layout = {
    breadcrumbs: [
        { title: 'Lead Review', href: index() },
        { title: 'Add Possible Lead', href: possibleLeads.create() },
    ],
};
