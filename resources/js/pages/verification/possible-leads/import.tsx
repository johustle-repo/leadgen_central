import { Head, Link, useForm } from '@inertiajs/react';
import { Upload } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index } from '@/routes/verification';
import possibleLeads from '@/routes/verification/possible-leads';

type Agent = { id: number; name: string };

export default function PossibleLeadsImport({ agents }: { agents: Agent[] }) {
    const form = useForm<{ file: File | null; agent_id: string }>({
        file: null,
        agent_id: agents[0] ? String(agents[0].id) : '',
    });

    return (
        <>
            <Head title="Import Possible Leads" />
            <div className="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-6 p-4 md:p-6">
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(possibleLeads.import.store.url(), {
                            forceFormData: true,
                        });
                    }}
                >
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-3">
                                <div className="rounded-lg bg-primary/10 p-2 text-primary">
                                    <Upload className="size-5" />
                                </div>
                                <div>
                                    <CardTitle>
                                        Import a list of possible leads
                                    </CardTitle>
                                    <CardDescription>
                                        Each row is matched against existing
                                        leads by email, then by company name.
                                        A match is updated with the file's
                                        data and marked Possible Lead. A row
                                        with no match is created fresh as a
                                        Possible Lead, owned by the agent you
                                        pick below. A company matching more
                                        than one existing lead is skipped and
                                        reported so you can resolve it
                                        manually.
                                    </CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-5">
                            <div>
                                <Label htmlFor="agent_id">
                                    Owner for newly-created leads
                                </Label>
                                <Select
                                    value={form.data.agent_id}
                                    onValueChange={(value) =>
                                        form.setData('agent_id', value)
                                    }
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
                                <InputError
                                    className="mt-1"
                                    message={form.errors.agent_id}
                                />
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Only applies to rows with no existing
                                    match. A matched lead keeps its current
                                    owner.
                                </p>
                            </div>
                            <div>
                                <Label htmlFor="file">CSV file</Label>
                                <input
                                    id="file"
                                    type="file"
                                    accept=".csv,text/csv"
                                    required
                                    className="mt-2 block w-full text-sm file:mr-3 file:rounded-md file:border file:bg-background file:px-3 file:py-1.5 file:text-sm file:font-medium"
                                    onChange={(event) =>
                                        form.setData(
                                            'file',
                                            event.target.files?.[0] ?? null,
                                        )
                                    }
                                />
                                <InputError
                                    className="mt-1"
                                    message={form.errors.file}
                                />
                            </div>
                            {form.progress && (
                                <div className="h-1.5 overflow-hidden rounded-full bg-muted">
                                    <div
                                        className="h-full bg-primary transition-all"
                                        style={{
                                            width: `${form.progress.percentage}%`,
                                        }}
                                    />
                                </div>
                            )}
                            <div className="flex justify-end gap-3">
                                <Button asChild type="button" variant="outline">
                                    <Link
                                        href={index({
                                            query: { status: 'possible_lead' },
                                        })}
                                    >
                                        Cancel
                                    </Link>
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={
                                        form.processing ||
                                        !form.data.file ||
                                        !form.data.agent_id
                                    }
                                >
                                    {form.processing
                                        ? 'Importing…'
                                        : 'Import and match'}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </form>
            </div>
        </>
    );
}

PossibleLeadsImport.layout = {
    breadcrumbs: [
        { title: 'Lead Review', href: index() },
        { title: 'Import Possible Leads' },
    ],
};
