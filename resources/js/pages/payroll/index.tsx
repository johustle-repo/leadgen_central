import { Form, Head, router, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    Calculator,
    FileDown,
    FileUp,
    Pencil,
    Users as UsersIcon,
    Wallet,
} from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { StatTile } from '@/components/stat-tile';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    exportPdf,
    importMethod as importSalaries,
    index,
} from '@/routes/payroll';
import { update as updateAdjustment } from '@/routes/payroll/adjustments';
import { update as updateMember } from '@/routes/payroll/members';

type PayrollMember = {
    user_id: number;
    name: string;
    employee_code: string | null;
    position: string;
    employment_type: string;
    employment_type_label: string;
    default_employment_type: string;
    joined_on: string | null;
    joined_this_month: boolean;
    monthly_salary: number | null;
    day_rate: number | null;
    hour_rate: number | null;
    employed_days: number;
    absent_days: number;
    worked_days: number;
    worked_minutes: number;
    paid_days: number;
    paid_days_adjusted: boolean;
    adjustment: { employment_type: string | null; paid_days: number | null };
    pay: number | null;
};

type Payroll = {
    month: string;
    month_label: string;
    days_in_month: number;
    in_progress: boolean;
    members: PayrollMember[];
    totals: { salary: number; missing_salary_count: number };
};

type EmploymentTypeOption = { value: string; label: string };

type Props = {
    payroll: Payroll;
    employmentTypes: EmploymentTypeOption[];
};

const PAID_HOURS_PER_DAY = 8;
const DEFAULT_CATEGORY = 'default';

function usd(amount: number | null): string {
    if (amount === null) {
        return '—';
    }

    return `USD ${amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function formatDays(days: number): string {
    return days.toLocaleString(undefined, { maximumFractionDigits: 3 });
}

function formatMinutes(minutes: number): string {
    return `${Math.floor(minutes / 60)}h ${String(minutes % 60).padStart(2, '0')}m`;
}

function formatDate(date: string | null): string {
    if (!date) {
        return '—';
    }

    return new Date(`${date}T00:00:00`).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function MonthAdjustmentForm({
    member,
    payroll,
    employmentTypes,
    onSaved,
}: {
    member: PayrollMember;
    payroll: Payroll;
    employmentTypes: EmploymentTypeOption[];
    onSaved: () => void;
}) {
    const [category, setCategory] = useState(
        member.adjustment.employment_type ?? DEFAULT_CATEGORY,
    );
    const defaultLabel =
        employmentTypes.find(
            (type) => type.value === member.default_employment_type,
        )?.label ?? member.default_employment_type;

    return (
        <Form
            {...updateAdjustment.form({
                month: payroll.month,
                user: member.user_id,
            })}
            options={{ preserveScroll: true }}
            onSuccess={onSaved}
            className="grid gap-4"
        >
            {({ errors, processing }) => (
                <>
                    <div>
                        <p className="font-medium">
                            {payroll.month_label} only
                        </p>
                        <p className="text-sm text-muted-foreground">
                            Use when this month was paid differently, e.g. a
                            part-time month with a set number of paid days.
                        </p>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="month_employment_type">
                                Category this month
                            </Label>
                            <Select
                                value={category}
                                onValueChange={setCategory}
                            >
                                <SelectTrigger id="month_employment_type">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={DEFAULT_CATEGORY}>
                                        Same as usual ({defaultLabel})
                                    </SelectItem>
                                    {employmentTypes.map((type) => (
                                        <SelectItem
                                            key={type.value}
                                            value={type.value}
                                        >
                                            {type.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <input
                                type="hidden"
                                name="employment_type"
                                value={
                                    category === DEFAULT_CATEGORY
                                        ? ''
                                        : category
                                }
                            />
                            <InputError message={errors.employment_type} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="paid_days">Paid days</Label>
                            <Input
                                id="paid_days"
                                name="paid_days"
                                type="number"
                                min="0"
                                max={payroll.days_in_month}
                                step="0.001"
                                placeholder={`Auto (${formatDays(member.paid_days)})`}
                                defaultValue={member.adjustment.paid_days ?? ''}
                            />
                            <InputError message={errors.paid_days} />
                        </div>
                    </div>
                    <p className="text-xs text-muted-foreground">
                        Leave paid days empty to calculate them from attendance.
                    </p>
                    <div className="flex justify-end">
                        <Button type="submit" disabled={processing}>
                            Save {payroll.month_label}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}

function MemberDetailsForm({
    member,
    employmentTypes,
    onSaved,
}: {
    member: PayrollMember;
    employmentTypes: EmploymentTypeOption[];
    onSaved: () => void;
}) {
    return (
        <Form
            {...updateMember.form(member.user_id)}
            options={{ preserveScroll: true }}
            onSuccess={onSaved}
            className="grid gap-4"
        >
            {({ errors, processing }) => (
                <>
                    <div>
                        <p className="font-medium">All months</p>
                        <p className="text-sm text-muted-foreground">
                            Used for every month unless that month is set above.
                        </p>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-3">
                        <div className="grid gap-2">
                            <Label htmlFor="monthly_salary">
                                Monthly salary (USD)
                            </Label>
                            <Input
                                id="monthly_salary"
                                name="monthly_salary"
                                type="number"
                                min="0"
                                step="0.01"
                                required
                                defaultValue={member.monthly_salary ?? ''}
                            />
                            <InputError message={errors.monthly_salary} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="employment_type">Category</Label>
                            <Select
                                name="employment_type"
                                defaultValue={member.default_employment_type}
                            >
                                <SelectTrigger id="employment_type">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {employmentTypes.map((type) => (
                                        <SelectItem
                                            key={type.value}
                                            value={type.value}
                                        >
                                            {type.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.employment_type} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="joined_on">Date of join</Label>
                            <Input
                                id="joined_on"
                                name="joined_on"
                                type="date"
                                required
                                defaultValue={member.joined_on ?? ''}
                            />
                            <InputError message={errors.joined_on} />
                        </div>
                    </div>
                    <div className="flex justify-end gap-2">
                        <DialogClose asChild>
                            <Button type="button" variant="secondary">
                                Close
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={processing}>
                            Save details
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}

function EditMemberDialog({
    member,
    payroll,
    employmentTypes,
    onClose,
}: {
    member: PayrollMember;
    payroll: Payroll;
    employmentTypes: EmploymentTypeOption[];
    onClose: () => void;
}) {
    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-2xl">
                <DialogTitle>Payroll — {member.name}</DialogTitle>
                <DialogDescription>
                    Part-time is paid for hours worked ÷ {PAID_HOURS_PER_DAY}.
                </DialogDescription>
                <MonthAdjustmentForm
                    member={member}
                    payroll={payroll}
                    employmentTypes={employmentTypes}
                    onSaved={onClose}
                />
                <Separator />
                <MemberDetailsForm
                    member={member}
                    employmentTypes={employmentTypes}
                    onSaved={onClose}
                />
            </DialogContent>
        </Dialog>
    );
}

const SALARY_IMPORT_EXAMPLE = `[
  {
    "employee_code": "DUS-006", "name": "Jane Doe", "monthly_salary": 400,
    "employment_type": "full_time", "joined_on": "2026-03-20",
    "months": [{ "month": "2026-04", "employment_type": "part_time", "paid_days": 15.45 }]
  }
]`;

function ImportSalariesCard() {
    const { flash } = usePage().props;
    const importErrors = flash.importErrors ?? [];

    return (
        <Card>
            <CardHeader>
                <CardTitle>Import salaries</CardTitle>
                <CardDescription>
                    Upload a JSON file to set monthly salary, category and date
                    of join for many members at once, plus optional per-month
                    category and paid days. Members are matched by employee
                    code, then email, then name.
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-4">
                <pre className="overflow-x-auto rounded-lg bg-muted/40 p-3 text-xs text-muted-foreground">
                    {SALARY_IMPORT_EXAMPLE}
                </pre>
                <Form
                    {...importSalaries.form()}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    className="flex flex-wrap items-start gap-2"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-1">
                                <Input
                                    name="file"
                                    type="file"
                                    accept=".json,application/json"
                                    required
                                    className="w-auto"
                                />
                                <InputError message={errors.file} />
                            </div>
                            <Button type="submit" disabled={processing}>
                                <FileUp />
                                Import
                            </Button>
                        </>
                    )}
                </Form>
                {importErrors.length > 0 && (
                    <ul className="list-disc space-y-1 pl-5 text-sm text-warning">
                        {importErrors.map((error) => (
                            <li key={error}>{error}</li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}

export default function PayrollIndex({ payroll, employmentTypes }: Props) {
    const [editingUserId, setEditingUserId] = useState<number | null>(null);
    const editing =
        payroll.members.find((member) => member.user_id === editingUserId) ??
        null;
    const totalPaidDays = payroll.members.reduce(
        (sum, member) => sum + member.paid_days,
        0,
    );

    function changeMonth(month: string) {
        if (!month) {
            return;
        }

        router.get(
            index.url(),
            { month },
            { preserveState: true, preserveScroll: true },
        );
    }

    return (
        <>
            <Head title="Payroll" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <StatTile
                        label="Payroll members"
                        value={payroll.members.length}
                        icon={UsersIcon}
                        tone="text-info"
                    />
                    <StatTile
                        label="Total paid days"
                        value={formatDays(totalPaidDays)}
                        icon={CalendarDays}
                        tone="text-warning"
                    />
                    <StatTile
                        label="Total salary"
                        value={usd(payroll.totals.salary)}
                        icon={Wallet}
                        tone="text-success"
                    />
                </div>

                <Card>
                    <CardHeader className="flex-row flex-wrap items-center justify-between gap-3 space-y-0">
                        <div>
                            <CardTitle>
                                Salary breakdown — {payroll.month_label}
                            </CardTitle>
                            <CardDescription>
                                Per hour, per day and per month for each payroll
                                member, calculated from attendance.
                            </CardDescription>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <Input
                                type="month"
                                value={payroll.month}
                                onChange={(event) =>
                                    changeMonth(event.target.value)
                                }
                                className="w-auto"
                            />
                            <Button asChild variant="outline">
                                <a
                                    href={exportPdf.url({
                                        query: { month: payroll.month },
                                    })}
                                >
                                    <FileDown />
                                    Export PDF
                                </a>
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        <div className="flex gap-3 rounded-lg border bg-muted/30 p-3 text-sm">
                            <Calculator className="mt-0.5 size-4 shrink-0 text-primary" />
                            <div className="space-y-1 text-muted-foreground">
                                <p>
                                    <span className="font-medium text-foreground">
                                        Pay = Monthly salary ÷{' '}
                                        {payroll.days_in_month} days × Paid
                                        days.
                                    </span>{' '}
                                    Per day = Monthly salary ÷{' '}
                                    {payroll.days_in_month}. Per hour = Per day
                                    ÷ {PAID_HOURS_PER_DAY} hours.
                                </p>
                                <p>
                                    Full-time paid days count every day of the
                                    month, Sundays and holidays included, minus
                                    absent workdays. In the month a member
                                    joins, only the days worked are paid.
                                    Part-time paid days = hours worked ÷{' '}
                                    {PAID_HOURS_PER_DAY}. Paid days marked *
                                    were set for this month.
                                    {payroll.in_progress &&
                                        ' This month is still in progress, so the remaining days are counted as paid.'}
                                </p>
                                {payroll.totals.missing_salary_count > 0 && (
                                    <p className="text-warning">
                                        {payroll.totals.missing_salary_count}{' '}
                                        member(s) have no monthly salary set and
                                        are left out of the total.
                                    </p>
                                )}
                            </div>
                        </div>

                        <Table>
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead>Name</TableHead>
                                    <TableHead>Category</TableHead>
                                    <TableHead>Date of join</TableHead>
                                    <TableHead className="text-right">
                                        Monthly
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Per day
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Per hour
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Hours worked
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Absent
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Paid days
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Pay
                                    </TableHead>
                                    <TableHead />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {payroll.members.map((member) => (
                                    <TableRow key={member.user_id}>
                                        <TableCell>
                                            <div className="font-medium">
                                                {member.name}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {[
                                                    member.employee_code,
                                                    member.position,
                                                ]
                                                    .filter(Boolean)
                                                    .join(' · ')}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {member.employment_type_label}
                                        </TableCell>
                                        <TableCell>
                                            {formatDate(member.joined_on)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {usd(member.monthly_salary)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {usd(member.day_rate)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {usd(member.hour_rate)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatMinutes(
                                                member.worked_minutes,
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {member.absent_days}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatDays(member.paid_days)}
                                            {member.paid_days_adjusted && '*'}
                                        </TableCell>
                                        <TableCell className="text-right font-semibold tabular-nums">
                                            {member.pay === null ? (
                                                <span className="text-warning">
                                                    Salary not set
                                                </span>
                                            ) : (
                                                usd(member.pay)
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`Edit payroll for ${member.name}`}
                                                onClick={() =>
                                                    setEditingUserId(
                                                        member.user_id,
                                                    )
                                                }
                                            >
                                                <Pencil />
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                <TableRow className="font-semibold hover:bg-transparent">
                                    <TableCell colSpan={9}>
                                        Total salary
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {usd(payroll.totals.salary)}
                                    </TableCell>
                                    <TableCell />
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <ImportSalariesCard />
            </div>

            {editing && (
                <EditMemberDialog
                    key={`${payroll.month}-${editing.user_id}`}
                    member={editing}
                    payroll={payroll}
                    employmentTypes={employmentTypes}
                    onClose={() => setEditingUserId(null)}
                />
            )}
        </>
    );
}
