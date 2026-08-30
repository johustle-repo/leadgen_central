import { Head, router, useForm } from '@inertiajs/react';
import { Clock3, Mail, MessageSquareReply, Send, Square } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cancel, enroll, update } from '@/routes/email-sequences';

type Step = {
    day: number;
    subject: string;
    body: string;
    attach_brochure: boolean;
};
type Lead = {
    id: number;
    contact_person: string | null;
    email: string;
    company_name: string;
};
type Enrollment = {
    id: number;
    status: string;
    current_step: number;
    next_send_at: string | null;
    stop_reason: string | null;
    last_error: string | null;
    lead: Lead | null;
    messages: Array<{ id: number; step_number: number; sent_at: string }>;
};
type Props = {
    sequence: { name: string; is_active: boolean; steps: Step[] };
    enrollments: {
        data: Enrollment[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    availableLeads: Lead[];
    gmailConnected: boolean;
    summary: { active: number; replied: number; completed: number };
};

const displayDate = (value: string | null) =>
    value ? value.replace('T', ' ').slice(0, 16) : '—';

export default function EmailSequencesIndex({
    sequence,
    enrollments,
    availableLeads,
    gmailConnected,
    summary,
}: Props) {
    const editor = useForm(sequence);
    const enrollment = useForm({ lead_id: '' });

    const setStep = (index: number, values: Partial<Step>) => {
        const steps = editor.data.steps.map((step, stepIndex) =>
            stepIndex === index ? { ...step, ...values } : step,
        );
        editor.setData('steps', steps);
    };

    const save = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        editor.put(update.url(), { preserveScroll: true });
    };

    const addLead = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        enrollment.post(enroll.url(), {
            preserveScroll: true,
            onSuccess: () => enrollment.reset(),
        });
    };

    return (
        <>
            <Head title="Email Sequences" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Email Sequences"
                    description="Automate Day 1, Day 3, and Day 7 outreach and stop follow-ups as soon as a lead replies."
                />

                <div className="grid gap-4 sm:grid-cols-3">
                    {[
                        {
                            label: 'Active',
                            value: summary.active,
                            icon: Clock3,
                        },
                        {
                            label: 'Replied',
                            value: summary.replied,
                            icon: MessageSquareReply,
                        },
                        {
                            label: 'Completed',
                            value: summary.completed,
                            icon: Mail,
                        },
                    ].map(({ label, value, icon: Icon }) => (
                        <Card key={label} className="gap-3 py-5">
                            <CardContent className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-muted-foreground">
                                        {label}
                                    </p>
                                    <p className="text-3xl font-semibold">
                                        {value}
                                    </p>
                                </div>
                                <Icon className="size-6 text-cyan-400" />
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {!gmailConnected && (
                    <div className="rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 text-sm text-amber-200">
                        Connect Gmail from Email Replies before enrolling a
                        lead.
                    </div>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Enroll a lead</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={addLead}
                            className="flex flex-col gap-3 sm:flex-row"
                        >
                            <select
                                value={enrollment.data.lead_id}
                                onChange={(event) =>
                                    enrollment.setData(
                                        'lead_id',
                                        event.target.value,
                                    )
                                }
                                className="h-10 flex-1 rounded-md border bg-background px-3 text-sm"
                                aria-label="Lead to enroll"
                            >
                                <option value="">
                                    Select one of your leads…
                                </option>
                                {availableLeads.map((lead) => (
                                    <option key={lead.id} value={lead.id}>
                                        {lead.contact_person || lead.email} —{' '}
                                        {lead.company_name}
                                    </option>
                                ))}
                            </select>
                            <Button
                                disabled={
                                    !gmailConnected ||
                                    !enrollment.data.lead_id ||
                                    enrollment.processing
                                }
                            >
                                <Send /> Enroll and start
                            </Button>
                        </form>
                        {enrollment.errors.lead_id && (
                            <p className="mt-2 text-sm text-destructive">
                                {enrollment.errors.lead_id}
                            </p>
                        )}
                    </CardContent>
                </Card>

                <form onSubmit={save} className="flex flex-col gap-4">
                    <Card>
                        <CardHeader>
                            <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                                <div className="w-full max-w-md space-y-2">
                                    <Label htmlFor="sequence-name">
                                        Sequence name
                                    </Label>
                                    <Input
                                        id="sequence-name"
                                        value={editor.data.name}
                                        onChange={(event) =>
                                            editor.setData(
                                                'name',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={editor.data.is_active}
                                        onChange={(event) =>
                                            editor.setData(
                                                'is_active',
                                                event.target.checked,
                                            )
                                        }
                                    />
                                    Sequence enabled
                                </label>
                            </div>
                        </CardHeader>
                    </Card>

                    <div className="relative grid gap-4 lg:grid-cols-3">
                        {editor.data.steps.map((step, index) => (
                            <Card
                                key={step.day}
                                className="relative overflow-hidden"
                            >
                                <div className="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-cyan-400 to-blue-500" />
                                <CardHeader>
                                    <div className="flex items-center justify-between">
                                        <CardTitle>
                                            Step {index + 1} · Day {step.day}
                                        </CardTitle>
                                        {index < 2 && (
                                            <span className="text-xs text-muted-foreground">
                                                Monitor until Day{' '}
                                                {
                                                    editor.data.steps[index + 1]
                                                        .day
                                                }
                                            </span>
                                        )}
                                    </div>
                                </CardHeader>
                                <CardContent className="flex flex-col gap-4">
                                    <div className="space-y-2">
                                        <Label htmlFor={`subject-${index}`}>
                                            Subject
                                        </Label>
                                        <Input
                                            id={`subject-${index}`}
                                            value={step.subject}
                                            onChange={(event) =>
                                                setStep(index, {
                                                    subject: event.target.value,
                                                })
                                            }
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor={`body-${index}`}>
                                            Message
                                        </Label>
                                        <textarea
                                            id={`body-${index}`}
                                            value={step.body}
                                            onChange={(event) =>
                                                setStep(index, {
                                                    body: event.target.value,
                                                })
                                            }
                                            className="min-h-72 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                        />
                                    </div>
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={step.attach_brochure}
                                            onChange={(event) =>
                                                setStep(index, {
                                                    attach_brochure:
                                                        event.target.checked,
                                                })
                                            }
                                        />
                                        Attach DUSCAFF brochure
                                    </label>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                    <p className="text-xs text-muted-foreground">
                        Available variables: {'{{firstName}}'} and{' '}
                        {'{{companyName}}'}. A reply stops all remaining steps.
                    </p>
                    <Button
                        type="submit"
                        className="self-end"
                        disabled={editor.processing}
                    >
                        Save sequence
                    </Button>
                </form>

                <Card>
                    <CardHeader>
                        <CardTitle>Sequence activity</CardTitle>
                    </CardHeader>
                    <CardContent className="overflow-x-auto px-0">
                        <table className="w-full text-sm">
                            <thead className="border-y bg-muted/50 text-left">
                                <tr>
                                    <th className="p-3 pl-6">Lead</th>
                                    <th className="p-3">Progress</th>
                                    <th className="p-3">Status</th>
                                    <th className="p-3">Next send</th>
                                    <th className="p-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {enrollments.data.map((item) => (
                                    <tr key={item.id}>
                                        <td className="p-3 pl-6">
                                            <p className="font-medium">
                                                {item.lead?.contact_person ||
                                                    item.lead?.email ||
                                                    'Deleted lead'}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {item.lead
                                                    ? `${item.lead.company_name} · ${item.lead.email}`
                                                    : 'Enrollment retained for history'}
                                            </p>
                                        </td>
                                        <td className="p-3">
                                            {item.current_step} of 3 sent
                                        </td>
                                        <td className="p-3">
                                            <StatusBadge value={item.status} />
                                            <p className="mt-1 max-w-xs text-xs text-muted-foreground">
                                                {item.stop_reason ||
                                                    item.last_error}
                                            </p>
                                        </td>
                                        <td className="p-3">
                                            {displayDate(item.next_send_at)}
                                        </td>
                                        <td className="p-3 text-right">
                                            {item.status === 'active' && (
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        router.delete(
                                                            cancel.url(item.id),
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    <Square /> Stop
                                                </Button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                                {enrollments.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            className="p-8 text-center text-muted-foreground"
                                        >
                                            No leads are enrolled yet.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
                <Pagination links={enrollments.links} />
            </div>
        </>
    );
}
