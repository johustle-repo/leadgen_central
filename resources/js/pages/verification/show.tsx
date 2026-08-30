import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, ArrowRight } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store as storeForwarding } from '@/routes/leads/forwardings';
import { store as storeNote } from '@/routes/leads/notes';
import { index, show, update } from '@/routes/verification';

type User = { id: number; name: string };
type Note = {
    id: number;
    note: string;
    note_type: string | null;
    created_at: string;
    user: User;
};
type History = {
    id: number;
    old_status: string | null;
    new_status: string;
    remarks: string | null;
    created_at: string;
    changer: User;
};
type Forwarding = {
    id: number;
    recipient_name: string | null;
    recipient_email: string | null;
    team: string | null;
    remarks: string | null;
    forwarded_at: string;
    forwarder: User;
};
type Lead = Record<string, string | number | null | object[]> & {
    id: number;
    lead_code: string;
    company_name: string;
    status: string;
    validation_status: string;
    agent: User;
    structured_notes: Note[];
    status_history: History[];
    forwardings: Forwarding[];
    upload_batch: { batch_code: string } | null;
};
const editableFields = [
    ['company_name', 'Company Name'],
    ['website', 'Website'],
    ['website_domain', 'Domain'],
    ['industry', 'Industry'],
    ['address', 'Address'],
    ['city', 'City'],
    ['country', 'Country'],
    ['country_code', 'Country Code'],
    ['timezone', 'Timezone'],
    ['contact_person', 'Contact Person'],
    ['position', 'Position'],
    ['email', 'Email'],
    ['phone', 'Phone'],
] as const;

export default function VerificationShow({
    lead,
    previousId,
    nextId,
    reviewers,
}: {
    lead: Lead;
    previousId: number | null;
    nextId: number | null;
    reviewers: User[];
}) {
    return (
        <>
            <Head title={`Verify ${lead.company_name}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={lead.company_name}
                    description={`${lead.lead_code} · ${lead.agent.name} · ${lead.upload_batch?.batch_code ?? 'Manual entry'}`}
                    actions={
                        <div className="flex gap-2">
                            <Button asChild variant="outline" size="sm">
                                <Link href={index()}>Queue</Link>
                            </Button>
                            {previousId && (
                                <Button asChild variant="outline" size="icon">
                                    <Link
                                        href={show(previousId)}
                                        aria-label="Previous lead"
                                    >
                                        <ArrowLeft />
                                    </Link>
                                </Button>
                            )}
                            {nextId && (
                                <Button asChild variant="outline" size="icon">
                                    <Link
                                        href={show(nextId)}
                                        aria-label="Next lead"
                                    >
                                        <ArrowRight />
                                    </Link>
                                </Button>
                            )}
                        </div>
                    }
                />
                <div className="grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)]">
                    <Form
                        {...update.form(lead.id)}
                        className="rounded-xl border bg-card p-5"
                    >
                        {({ errors, processing }) => (
                            <>
                                <div className="mb-5 flex flex-wrap items-center gap-2">
                                    <StatusBadge value={lead.status} />
                                    <StatusBadge
                                        value={lead.validation_status}
                                    />
                                </div>
                                <div className="grid gap-4 md:grid-cols-2">
                                    {editableFields.map(([name, label]) => (
                                        <div
                                            key={name}
                                            className={
                                                name === 'address'
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
                                                defaultValue={String(
                                                    lead[name] ?? '',
                                                )}
                                                readOnly={
                                                    name === 'website_domain'
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
                                </div>
                                <div className="mt-5 grid gap-4 md:grid-cols-2">
                                    <div>
                                        <Label htmlFor="status">
                                            Classification
                                        </Label>
                                        <select
                                            id="status"
                                            name="status"
                                            defaultValue={lead.status}
                                            className="mt-2 h-9 w-full rounded-md border bg-background px-3 text-sm"
                                        >
                                            <option value="needs_review">
                                                Needs Review
                                            </option>
                                            <option value="possible_lead">
                                                Possible Lead
                                            </option>
                                            <option value="qualified_lead">
                                                Qualified Lead
                                            </option>
                                            <option value="not_a_lead">
                                                Not a Lead
                                            </option>
                                            <option value="duplicate">
                                                Duplicate
                                            </option>
                                        </select>
                                    </div>
                                    <div>
                                        <Label htmlFor="remarks">
                                            Verification remarks
                                        </Label>
                                        <Input
                                            id="remarks"
                                            name="remarks"
                                            className="mt-2"
                                        />
                                    </div>
                                </div>
                                <div className="mt-5 flex justify-end gap-2">
                                    <Button
                                        type="submit"
                                        name="intent"
                                        value="save"
                                        variant="outline"
                                        disabled={processing}
                                    >
                                        Save
                                    </Button>
                                    <Button
                                        type="submit"
                                        name="intent"
                                        value="save_next"
                                        disabled={processing}
                                    >
                                        Save &amp; Next
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                    <aside className="flex flex-col gap-4">
                        <section className="rounded-xl border bg-card p-5">
                            <h2 className="font-semibold">Notes</h2>
                            <Form
                                {...storeNote.form(lead.id)}
                                className="mt-3 flex flex-col gap-3"
                            >
                                {({ processing }) => (
                                    <>
                                        <textarea
                                            name="note"
                                            rows={3}
                                            required
                                            className="w-full rounded-md border bg-background p-3 text-sm"
                                            placeholder="Add verification note…"
                                        />
                                        <select
                                            name="note_type"
                                            className="h-9 rounded-md border bg-background px-3 text-sm"
                                        >
                                            <option value="verification">
                                                Verification
                                            </option>
                                            <option value="correction">
                                                Correction
                                            </option>
                                            <option value="general">
                                                General
                                            </option>
                                        </select>
                                        <Button size="sm" disabled={processing}>
                                            Add note
                                        </Button>
                                    </>
                                )}
                            </Form>
                            <div className="mt-4 flex flex-col gap-3">
                                {lead.structured_notes.map((note) => (
                                    <div
                                        key={note.id}
                                        className="rounded-lg bg-muted/50 p-3 text-sm"
                                    >
                                        <p>{note.note}</p>
                                        <p className="mt-2 text-xs text-muted-foreground">
                                            {note.user.name} ·{' '}
                                            {new Date(
                                                note.created_at,
                                            ).toLocaleString()}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </section>
                        <section className="rounded-xl border bg-card p-5">
                            <h2 className="font-semibold">
                                Forward qualified lead
                            </h2>
                            <Form
                                {...storeForwarding.form(lead.id)}
                                className="mt-3 flex flex-col gap-3"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <Input
                                            name="recipient_name"
                                            placeholder="Recipient name"
                                        />
                                        <Input
                                            name="recipient_email"
                                            type="email"
                                            placeholder="Recipient email"
                                        />
                                        <Input name="team" placeholder="Team" />
                                        <textarea
                                            name="remarks"
                                            rows={2}
                                            className="rounded-md border bg-background p-3 text-sm"
                                            placeholder="Remarks"
                                        />
                                        {errors.lead && (
                                            <p className="text-sm text-destructive">
                                                {errors.lead}
                                            </p>
                                        )}
                                        <Button
                                            size="sm"
                                            disabled={
                                                processing ||
                                                lead.status !== 'qualified_lead'
                                            }
                                        >
                                            Forward
                                        </Button>
                                    </>
                                )}
                            </Form>
                            {reviewers.length > 0 && (
                                <p className="mt-2 text-xs text-muted-foreground">
                                    Authorized reviewers:{' '}
                                    {reviewers
                                        .map((user) => user.name)
                                        .join(', ')}
                                </p>
                            )}
                            <div className="mt-4 flex flex-col gap-3">
                                {lead.forwardings.map((item) => (
                                    <div
                                        key={item.id}
                                        className="rounded-lg bg-muted/50 p-3 text-sm"
                                    >
                                        <p>
                                            {item.recipient_name ||
                                                item.team ||
                                                item.recipient_email}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {item.forwarder.name} ·{' '}
                                            {new Date(
                                                item.forwarded_at,
                                            ).toLocaleString()}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </section>
                        <section className="rounded-xl border bg-card p-5">
                            <h2 className="font-semibold">Status history</h2>
                            <div className="mt-3 flex flex-col gap-3">
                                {lead.status_history.map((item) => (
                                    <div
                                        key={item.id}
                                        className="border-l-2 pl-3 text-sm"
                                    >
                                        <p>
                                            {item.old_status || 'Created'} →{' '}
                                            {item.new_status}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {item.changer.name} ·{' '}
                                            {new Date(
                                                item.created_at,
                                            ).toLocaleString()}
                                        </p>
                                        {item.remarks && (
                                            <p className="mt-1 text-muted-foreground">
                                                {item.remarks}
                                            </p>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </section>
                    </aside>
                </div>
            </div>
        </>
    );
}
VerificationShow.layout = {
    breadcrumbs: [{ title: 'Verification', href: index() }],
};
