import { Form, Head, router } from '@inertiajs/react';
import {
    Banknote,
    Calculator,
    FileDown,
    Pencil,
    Receipt,
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
    DialogFooter,
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { exportPdf, index } from '@/routes/payroll';
import { update as updateMember } from '@/routes/payroll/members';
import { update as updateMonth } from '@/routes/payroll/months';

type PayrollMember = {
    user_id: number;
    name: string;
    employee_code: string | null;
    position: string;
    employment_type: string;
    employment_type_label: string;
    joined_on: string | null;
    monthly_salary: number | null;
    day_rate: number | null;
    hour_rate: number | null;
    employed_days: number;
    absent_days: number;
    worked_days: number;
    worked_minutes: number;
    paid_days: number;
    pay: number | null;
};

type Payroll = {
    month: string;
    month_label: string;
    days_in_month: number;
    in_progress: boolean;
    members: PayrollMember[];
    utilities: {
        usd_to_php_rate: number | null;
        items: {
            key: string;
            label: string;
            php: number;
            usd: number | null;
        }[];
        total_usd: number;
    };
    totals: {
        salary: number;
        service: number;
        to_pay: number;
        missing_salary_count: number;
    };
};

type Props = {
    payroll: Payroll;
    employmentTypes: { value: string; label: string }[];
};

const PAID_HOURS_PER_DAY = 8;

function usd(amount: number | null): string {
    if (amount === null) {
        return '—';
    }

    return `USD ${amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function php(amount: number): string {
    return `PHP ${amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
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

function EditMemberDialog({
    member,
    employmentTypes,
    onClose,
}: {
    member: PayrollMember;
    employmentTypes: Props['employmentTypes'];
    onClose: () => void;
}) {
    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogTitle>Payroll details — {member.name}</DialogTitle>
                <DialogDescription>
                    Changes apply to every month&apos;s calculation for this
                    member.
                </DialogDescription>
                <Form
                    {...updateMember.form(member.user_id)}
                    options={{ preserveScroll: true }}
                    onSuccess={onClose}
                    className="grid gap-4"
                >
                    {({ errors, processing }) => (
                        <>
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
                                <Label htmlFor="employment_type">
                                    Category
                                </Label>
                                <Select
                                    name="employment_type"
                                    defaultValue={member.employment_type}
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
                                <p className="text-xs text-muted-foreground">
                                    Part-time is paid for hours worked ÷{' '}
                                    {PAID_HOURS_PER_DAY}.
                                </p>
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
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    Save
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function UtilitiesCard({ payroll }: { payroll: Payroll }) {
    const { utilities } = payroll;
    const phpValue = (key: string) =>
        utilities.items.find((item) => item.key === key)?.php ?? 0;

    return (
        <Card>
            <CardHeader>
                <CardTitle>Services</CardTitle>
                <CardDescription>
                    Utility bills for {payroll.month_label} in PHP, converted at
                    the month&apos;s USD to PHP rate.
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-6">
                <Form
                    key={payroll.month}
                    {...updateMonth.form(payroll.month)}
                    options={{ preserveScroll: true }}
                    className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="usd_to_php_rate">
                                    USD to PHP rate
                                </Label>
                                <Input
                                    id="usd_to_php_rate"
                                    name="usd_to_php_rate"
                                    type="number"
                                    min="0"
                                    step="0.0001"
                                    placeholder="e.g. 61.43"
                                    defaultValue={
                                        utilities.usd_to_php_rate ?? ''
                                    }
                                />
                                <InputError message={errors.usd_to_php_rate} />
                            </div>
                            {utilities.items.map((item) => (
                                <div key={item.key} className="grid gap-2">
                                    <Label htmlFor={`${item.key}_php`}>
                                        {item.label} (PHP)
                                    </Label>
                                    <Input
                                        id={`${item.key}_php`}
                                        name={`${item.key}_php`}
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        required
                                        defaultValue={phpValue(item.key)}
                                    />
                                    <InputError
                                        message={
                                            errors[
                                                `${item.key}_php` as keyof typeof errors
                                            ]
                                        }
                                    />
                                </div>
                            ))}
                            <div className="sm:col-span-2 lg:col-span-4">
                                <Button type="submit" disabled={processing}>
                                    Save services
                                </Button>
                            </div>
                        </>
                    )}
                </Form>

                <Table>
                    <TableHeader>
                        <TableRow className="hover:bg-transparent">
                            <TableHead>Service</TableHead>
                            <TableHead className="text-right">PHP</TableHead>
                            <TableHead className="text-right">USD</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {utilities.items.map((item) => (
                            <TableRow key={item.key}>
                                <TableCell>{item.label}</TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {php(item.php)}
                                </TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {usd(item.usd)}
                                </TableCell>
                            </TableRow>
                        ))}
                        <TableRow className="font-semibold hover:bg-transparent">
                            <TableCell colSpan={2}>Total service</TableCell>
                            <TableCell className="text-right tabular-nums">
                                {usd(payroll.totals.service)}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                {utilities.usd_to_php_rate === null && (
                    <p className="text-sm text-muted-foreground">
                        Set the USD to PHP rate to convert the bills to USD.
                    </p>
                )}
            </CardContent>
        </Card>
    );
}

export default function PayrollIndex({ payroll, employmentTypes }: Props) {
    const [editing, setEditing] = useState<PayrollMember | null>(null);

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
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatTile
                        label="Payroll members"
                        value={payroll.members.length}
                        icon={UsersIcon}
                        tone="text-info"
                    />
                    <StatTile
                        label="Total salary"
                        value={usd(payroll.totals.salary)}
                        icon={Wallet}
                        tone="text-primary"
                    />
                    <StatTile
                        label="Total service"
                        value={usd(payroll.totals.service)}
                        icon={Receipt}
                        tone="text-warning"
                    />
                    <StatTile
                        label="Total to pay"
                        value={usd(payroll.totals.to_pay)}
                        icon={Banknote}
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
                                    Full-time paid days count every day from the
                                    join date, Sundays and holidays included,
                                    minus absent workdays. Part-time paid days =
                                    hours worked ÷ {PAID_HOURS_PER_DAY}.
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
                                            {member.paid_days}
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
                                                aria-label={`Edit payroll details for ${member.name}`}
                                                onClick={() =>
                                                    setEditing(member)
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

                <UtilitiesCard payroll={payroll} />
            </div>

            {editing && (
                <EditMemberDialog
                    member={editing}
                    employmentTypes={employmentTypes}
                    onClose={() => setEditing(null)}
                />
            )}
        </>
    );
}
