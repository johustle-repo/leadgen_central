import { Form, Head, router } from '@inertiajs/react';
import { FileText, UserRound } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
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
const DRAFT_STORAGE_KEY = 'leadgen:add-lead-draft';

function readDraft(): Record<string, string> {
    try {
        const raw = window.localStorage.getItem(DRAFT_STORAGE_KEY);

        return raw ? JSON.parse(raw) : {};
    } catch {
        return {};
    }
}

function writeDraft(values: Record<string, string>) {
    try {
        window.localStorage.setItem(DRAFT_STORAGE_KEY, JSON.stringify(values));
    } catch {
        // Ignore storage failures (private browsing, quota, etc).
    }
}

function clearDraft() {
    try {
        window.localStorage.removeItem(DRAFT_STORAGE_KEY);
    } catch {
        // Ignore storage failures.
    }
}

type CompanyContactCount = { company: string; agentId: string; count: number };

function CompanyField({
    defaultValue,
    agentId,
    result,
}: {
    defaultValue: string;
    agentId: string;
    result: CompanyContactCount;
}) {
    const [company, setCompany] = useState(defaultValue);
    const ownerId = agentId || result.agentId;
    const matches = result.company === company && result.agentId === ownerId;
    useEffect(() => {
        if (matches) {
            return;
        }

        let cancel: (() => void) | undefined;
        const timer = setTimeout(() => {
            router.reload({
                only: ['companyContactCount'],
                data: { company_name: company, agent_id: ownerId },
                onCancelToken: (token) => {
                    cancel = () => token.cancel();
                },
            });
        }, 250);

        return () => {
            clearTimeout(timer);
            cancel?.();
        };
    }, [company, ownerId, matches]);
    const count = matches ? result.count : undefined;

    return (
        <>
            <div className="flex items-center gap-2">
                <Label htmlFor="company_name">Company *</Label>
                <span className="text-xs text-muted-foreground" role="status">
                    (
                    {count === undefined
                        ? 'Loading contacts…'
                        : `${count} ${count === 1 ? 'contact' : 'contacts'}`}
                    )
                </span>
            </div>
            <Input
                id="company_name"
                name="company_name"
                required
                defaultValue={defaultValue}
                onChange={(event) => setCompany(event.target.value)}
                className="mt-2"
            />
        </>
    );
}

function DataSourceSelect({
    id,
    name,
    defaultValue,
    onValueChange,
}: {
    id: string;
    name: string;
    defaultValue: string;
    onValueChange?: (value: string) => void;
}) {
    const [value, setValue] = useState(defaultValue || NO_DATA_SOURCE);

    const handleValueChange = (next: string) => {
        setValue(next);
        onValueChange?.(next === NO_DATA_SOURCE ? '' : next);
    };

    return (
        <>
            <input
                type="hidden"
                name={name}
                value={value === NO_DATA_SOURCE ? '' : value}
            />
            <Select value={value} onValueChange={handleValueChange}>
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
    { name: 'contact_person', label: 'First Name', requiredOnCreate: true },
    {
        name: 'email',
        label: 'Email',
        type: 'email',
        requiredOnCreate: true,
    },
    { name: 'country_code', label: 'Country', maxLength: 2 },
    { name: 'city', label: 'City' },
    { name: 'import_trades', label: 'Import Trades' },
    { name: 'linkedin_url', label: 'LinkedIn', type: 'url' },
    { name: 'data_source', label: 'Sources of Data' },
    { name: 'source_url', label: 'Link', type: 'url' },
];
const dataSources = ['Tendata', 'Lusha', 'Tendata/Lusha', 'Email', 'Manual'];
const fieldLabel = (name: string) =>
    fields.find((field) => field.name === name)?.label ??
    name
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
const historyValue = (value: string | number | null) =>
    value === null || value === '' ? '(empty)' : String(value);
type ChangeHistoryEntry = {
    id: number;
    description: string;
    created_at: string;
    user: { id: number; name: string } | null;
    metadata: {
        changes?: Record<
            string,
            { old: string | number | null; new: string | number | null }
        >;
    } | null;
};
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
    companyContactCount,
    changeHistory = [],
}: {
    lead: Lead | null;
    defaults: Record<string, string | number | null>;
    formVersion: number;
    companyContactCount: CompanyContactCount;
    agents: Array<{ id: number; name: string }>;
    changeHistory?: ChangeHistoryEntry[];
}) {
    const form = lead ? update.form(lead.id) : store.form();
    const isCreating = !lead;
    const [draft] = useState<Record<string, string>>(() =>
        isCreating ? readDraft() : {},
    );
    const defaultFor = (name: string) =>
        String(lead?.[name] ?? draft[name] ?? defaults[name] ?? '');
    const [selectedAgent, setSelectedAgent] = useState(
        String(
            lead?.agent_id ??
                draft.agent_id ??
                defaults.agent_id ??
                agents[0]?.id ??
                '',
        ),
    );
    const autosaveTimer = useRef<ReturnType<typeof setTimeout> | undefined>(
        undefined,
    );

    const scheduleAutosave = (formEl: HTMLFormElement | null) => {
        if (!isCreating || !formEl) {
            return;
        }

        if (autosaveTimer.current) {
            clearTimeout(autosaveTimer.current);
        }

        autosaveTimer.current = setTimeout(() => {
            writeDraft(
                Object.fromEntries(
                    new FormData(formEl),
                ) as Record<string, string>,
            );
        }, 400);
    };

    const updateDraftField = (name: string, value: string) => {
        if (!isCreating) {
            return;
        }

        writeDraft({ ...readDraft(), [name]: value });
    };

    useEffect(() => {
        return () => {
            if (autosaveTimer.current) {
                clearTimeout(autosaveTimer.current);
            }
        };
    }, []);

    return (
        <>
            <Head title={lead ? `Edit ${lead.company_name}` : 'Add Lead'} />
            <div className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
                <Form
                    key={formVersion}
                    {...form}
                    resetOnSuccess={!lead}
                    onInput={(event) =>
                        scheduleAutosave(
                            (event.target as HTMLElement).closest('form'),
                        )
                    }
                    onSuccess={() => {
                        if (isCreating) {
                            clearDraft();
                        }
                    }}
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
                                            onValueChange={(value) => {
                                                setSelectedAgent(value);
                                                updateDraftField(
                                                    'agent_id',
                                                    value,
                                                );
                                            }}
                                            defaultValue={selectedAgent}
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
                                            const value = defaultFor(
                                                field.name,
                                            );
                                            const isRequired =
                                                field.required ||
                                                (field.requiredOnCreate &&
                                                    isCreating);

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
                                                    {field.name !==
                                                        'company_name' && (
                                                        <Label
                                                            htmlFor={field.name}
                                                        >
                                                            {field.label}
                                                            {isRequired
                                                                ? ' *'
                                                                : ''}
                                                        </Label>
                                                    )}
                                                    {field.name ===
                                                    'company_name' ? (
                                                        <CompanyField
                                                            result={
                                                                companyContactCount
                                                            }
                                                            defaultValue={value}
                                                            agentId={
                                                                selectedAgent
                                                            }
                                                        />
                                                    ) : field.name ===
                                                      'data_source' ? (
                                                        <DataSourceSelect
                                                            id={field.name}
                                                            name={field.name}
                                                            defaultValue={value}
                                                            onValueChange={(
                                                                next,
                                                            ) =>
                                                                updateDraftField(
                                                                    field.name,
                                                                    next,
                                                                )
                                                            }
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
                                                                isRequired
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
                {!isCreating && changeHistory.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Change history</CardTitle>
                            <CardDescription>
                                Field edits recorded for this lead, most
                                recent first.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="divide-y">
                            {changeHistory.map((entry) => {
                                const changes = Object.entries(
                                    entry.metadata?.changes ?? {},
                                );

                                return (
                                    <div key={entry.id} className="py-3">
                                        <p className="text-sm font-medium">
                                            {entry.description}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {entry.user?.name ??
                                                'Deleted user'}{' '}
                                            ·{' '}
                                            {new Date(
                                                entry.created_at,
                                            ).toLocaleString()}
                                        </p>
                                        {changes.length > 0 && (
                                            <ul className="mt-2 space-y-1 text-xs text-muted-foreground">
                                                {changes.map(
                                                    ([field, change]) => (
                                                        <li key={field}>
                                                            <span className="font-medium text-foreground">
                                                                {fieldLabel(
                                                                    field,
                                                                )}
                                                                :
                                                            </span>{' '}
                                                            {historyValue(
                                                                change.old,
                                                            )}{' '}
                                                            →{' '}
                                                            {historyValue(
                                                                change.new,
                                                            )}
                                                        </li>
                                                    ),
                                                )}
                                            </ul>
                                        )}
                                    </div>
                                );
                            })}
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}
LeadForm.layout = { breadcrumbs: [{ title: 'Leads', href: index() }] };
