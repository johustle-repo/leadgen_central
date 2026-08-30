import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Download,
    Mail,
    Paperclip,
    Pencil,
    Plus,
    Search,
    Send,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    create,
    downloadCleaned,
    downloadRaw,
    edit,
    index,
} from '@/routes/leads';
import { sendEmail } from '@/routes/leads';
type Lead = {
    id: number;
    lead_code: string;
    company_name: string;
    city: string | null;
    country: string | null;
    contact_person: string | null;
    email: string | null;
    status: string;
    source: string;
    validation_status: string;
    website_domain: string | null;
    upload_batch: { batch_code: string } | null;
    agent: { name: string };
    can_update: boolean;
    can_send_email: boolean;
    email_replies_count: number;
    unread_email_replies_count: number;
};
type Props = {
    leads: {
        data: Lead[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: Record<string, string>;
};
export default function LeadsIndex({ leads, filters }: Props) {
    const [composeLead, setComposeLead] = useState<Lead | null>(null);
    const emailForm = useForm({ subject: '', body: '' });

    const emailBody = (lead: Lead) => `Hi ${lead.contact_person || 'there'},

I can help you improve your cost efficiency in scaffolding materials by offering competitive pricing without compromising on quality and standards.

Duscaff is a global scaffolding manufacturer headquartered in Dubai, with production facilities across the Middle East, Europe, Central Asia, and China. We currently supply hundreds of clients worldwide and are ready to support your business as well.

We manufacture and export a full range of scaffolding products, including components for the following systems:

• Ringlock System
• Tube & Fitting System
• Frame System
• Kwikstage System
• Cuplock System
• Aluminium Towers and Ladders

All products are manufactured in compliance with American Standards, British Standards, and European Norms.

Our materials are backed by the trust and consistency of a global brand that has helped develop key projects in the Oil & Gas sector and civil construction across the Middle East and around the world.

Please find our brochure attached for your reference. If you don’t see a particular item you’re looking for, there’s a good chance we still manufacture it. Feel free to reach out with your specific requirements.

We look forward to the opportunity to support your upcoming projects.

Regards,`;

    const openComposer = (lead: Lead) => {
        setComposeLead(lead);
        emailForm.setData({
            subject: 'Competitive Scaffolding Materials from DUSCAFF',
            body: emailBody(lead),
        });
        emailForm.clearErrors();
    };

    const sendLeadEmail = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!composeLead) {
            return;
        }

        emailForm.post(sendEmail.url(composeLead.id), {
            preserveScroll: true,
            onSuccess: () => setComposeLead(null),
        });
    };

    const search = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get(
            index.url(),
            Object.fromEntries(new FormData(event.currentTarget)),
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Leads" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Leads"
                    description="Search, filter, and manage authorized lead records."
                    actions={
                        <Button asChild>
                            <Link href={create()}>
                                <Plus />
                                Add lead
                            </Link>
                        </Button>
                    }
                />
                <form
                    onSubmit={search}
                    className="grid gap-3 rounded-xl border bg-card p-4 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <div className="relative sm:col-span-2 lg:col-span-3">
                        <Search className="absolute top-2.5 left-3 size-4 text-muted-foreground" />
                        <Input
                            name="search"
                            defaultValue={filters.search}
                            placeholder="Search leads…"
                            className="pl-9"
                        />
                    </div>
                    <select
                        name="per_page"
                        defaultValue={filters.per_page || '10'}
                        className="h-9 rounded-md border bg-background px-3 text-sm"
                        aria-label="Leads per page"
                    >
                        <option value="10">10 per page</option>
                        <option value="25">25 per page</option>
                        <option value="50">50 per page</option>
                        <option value="100">100 per page</option>
                    </select>
                    <Input
                        name="date_from"
                        type="date"
                        defaultValue={filters.date_from}
                        aria-label="Date from"
                    />
                    <Input
                        name="date_to"
                        type="date"
                        defaultValue={filters.date_to}
                        aria-label="Date to"
                    />
                    <Button
                        type="submit"
                        variant="secondary"
                        className="lg:col-span-2"
                    >
                        Apply filters
                    </Button>
                    <Button asChild variant="outline" className="lg:col-span-2">
                        <a
                            href={downloadRaw.url({
                                query: {
                                    date_from: filters.date_from || undefined,
                                    date_to: filters.date_to || undefined,
                                },
                            })}
                            download
                        >
                            <Download />
                            Download raw CSV
                        </a>
                    </Button>
                    <Button asChild variant="outline" className="lg:col-span-2">
                        <a
                            href={downloadCleaned.url({
                                query: {
                                    date_from: filters.date_from || undefined,
                                    date_to: filters.date_to || undefined,
                                },
                            })}
                            download
                            onClick={() =>
                                toast.success(
                                    'Cleaned CSV download started with dataset cleaning.',
                                )
                            }
                        >
                            <Download />
                            Download cleaned CSV
                        </a>
                    </Button>
                </form>
                <div className="overflow-hidden rounded-xl border bg-card">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/60 text-left">
                                <tr>
                                    <th className="p-3">Company</th>
                                    <th className="p-3">Location</th>
                                    <th className="p-3">Contact</th>
                                    <th className="p-3">Owner</th>
                                    <th className="p-3">Status</th>
                                    <th className="p-3">Source</th>
                                    <th className="p-3">Replies</th>
                                    <th className="p-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {leads.data.map((lead) => (
                                    <tr key={lead.id}>
                                        <td className="p-3">
                                            <Link
                                                href={edit(lead.id)}
                                                className="font-medium hover:underline"
                                            >
                                                {lead.company_name}
                                            </Link>
                                            <div className="text-xs text-muted-foreground">
                                                {lead.website_domain ||
                                                    lead.lead_code}
                                            </div>
                                        </td>
                                        <td className="p-3">
                                            {[lead.city, lead.country]
                                                .filter(Boolean)
                                                .join(', ') || '—'}
                                        </td>
                                        <td className="p-3">
                                            {lead.contact_person || '—'}
                                            <div className="text-xs text-muted-foreground">
                                                {lead.email}
                                            </div>
                                        </td>
                                        <td className="p-3">
                                            {lead.agent.name}
                                        </td>
                                        <td className="p-3">
                                            <div className="flex flex-col items-start gap-1">
                                                <StatusBadge
                                                    value={lead.status}
                                                />
                                                <span className="text-xs text-muted-foreground">
                                                    {lead.validation_status}
                                                </span>
                                            </div>
                                        </td>
                                        <td className="p-3 capitalize">
                                            {lead.source}
                                        </td>
                                        <td className="p-3">
                                            <div className="flex items-center gap-2">
                                                <Mail className="size-4 text-muted-foreground" />
                                                <span className="font-medium">
                                                    {lead.email_replies_count}
                                                </span>
                                                {lead.unread_email_replies_count >
                                                    0 && (
                                                    <span className="rounded-full bg-cyan-500/15 px-2 py-0.5 text-xs font-semibold text-cyan-700 dark:text-cyan-300">
                                                        {
                                                            lead.unread_email_replies_count
                                                        }{' '}
                                                        new
                                                    </span>
                                                )}
                                            </div>
                                        </td>
                                        <td className="p-3 text-right">
                                            <div className="flex justify-end gap-2">
                                                {lead.can_send_email && (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        onClick={() =>
                                                            openComposer(lead)
                                                        }
                                                    >
                                                        <Send className="size-3.5" />
                                                        Send email
                                                    </Button>
                                                )}
                                                {lead.can_update && (
                                                    <Button
                                                        asChild
                                                        size="sm"
                                                        variant="outline"
                                                    >
                                                        <Link
                                                            href={edit(lead.id)}
                                                        >
                                                            <Pencil className="size-3.5" />
                                                            Edit
                                                        </Link>
                                                    </Button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {!leads.data.length && (
                            <p className="p-12 text-center text-muted-foreground">
                                No leads match your filters.
                            </p>
                        )}
                    </div>
                </div>
                <Pagination links={leads.links} />
            </div>

            <Dialog
                open={composeLead !== null}
                onOpenChange={(open) => !open && setComposeLead(null)}
            >
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Send DUSCAFF introduction</DialogTitle>
                        <DialogDescription>
                            Review the message before sending it through your
                            connected Gmail account.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={sendLeadEmail} className="grid gap-5">
                        <div className="grid gap-2">
                            <Label>Recipient</Label>
                            <div className="rounded-lg border bg-muted/40 px-3 py-2 text-sm">
                                {composeLead?.contact_person || 'Lead'} &lt;
                                {composeLead?.email}&gt;
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="email-subject">Subject</Label>
                            <Input
                                id="email-subject"
                                value={emailForm.data.subject}
                                onChange={(event) =>
                                    emailForm.setData(
                                        'subject',
                                        event.target.value,
                                    )
                                }
                                maxLength={150}
                                required
                            />
                            {emailForm.errors.subject && (
                                <p className="text-sm text-destructive">
                                    {emailForm.errors.subject}
                                </p>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="email-body">Message</Label>
                            <textarea
                                id="email-body"
                                value={emailForm.data.body}
                                onChange={(event) =>
                                    emailForm.setData(
                                        'body',
                                        event.target.value,
                                    )
                                }
                                rows={18}
                                maxLength={15000}
                                required
                                className="min-h-80 w-full resize-y rounded-lg border bg-background px-3 py-2 text-sm leading-relaxed outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            />
                            {emailForm.errors.body && (
                                <p className="text-sm text-destructive">
                                    {emailForm.errors.body}
                                </p>
                            )}
                        </div>

                        <div className="flex items-center gap-2 rounded-lg border border-cyan-500/20 bg-cyan-500/5 px-3 py-2 text-sm text-muted-foreground">
                            <Paperclip className="size-4 text-cyan-500" />
                            DUSCAFF Scaffolding Products brochure 2026.pdf
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setComposeLead(null)}
                                disabled={emailForm.processing}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={emailForm.processing}
                            >
                                <Send />
                                {emailForm.processing
                                    ? 'Sending…'
                                    : 'Send email'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
LeadsIndex.layout = { breadcrumbs: [{ title: 'Leads', href: index() }] };
