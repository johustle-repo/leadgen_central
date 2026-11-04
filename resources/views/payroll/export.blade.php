<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $headerRows[0][0] }}</title>
    <style>
        @page { margin: 26px 30px 44px; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 9.5px; color: #1e293b; }
        table { width: 100%; border-collapse: collapse; }

        .footer { position: fixed; left: 0; right: 0; bottom: -28px; font-size: 8px; color: #64748b; border-top: 1px solid #cbd5e1; padding-top: 5px; }
        .footer .page-number:after { content: "Page " counter(page); }

        .masthead { background-color: #0b3a5b; color: #ffffff; border-radius: 6px; margin-bottom: 12px; }
        .masthead td { padding: 10px 12px; vertical-align: middle; }
        .masthead .logo { width: 46px; padding-right: 0; }
        .masthead .logo img { width: 40px; height: 40px; background-color: #ffffff; border-radius: 6px; padding: 3px; }
        .masthead .eyebrow { font-size: 8px; letter-spacing: 1.2px; text-transform: uppercase; color: #7dd3fc; margin-bottom: 2px; }
        .masthead .heading { font-size: 16px; font-weight: bold; }
        .masthead .subheading { font-size: 8.5px; color: #cbe8f5; margin-top: 3px; }
        .masthead .meta { text-align: right; width: 32%; font-size: 8.5px; color: #cbe8f5; }
        .masthead .period { display: inline-block; background-color: #0891b2; color: #ffffff; font-weight: bold; font-size: 10px; padding: 3px 9px; border-radius: 10px; margin-bottom: 4px; }

        .stats { margin-bottom: 10px; }
        .stats td { width: 25%; padding: 0 4px; }
        .stats td:first-child { padding-left: 0; }
        .stats td:last-child { padding-right: 0; }
        .stat { border: 1px solid #cfe8f1; background-color: #f0f9fc; border-radius: 6px; padding: 8px 10px; }
        .stat .label { font-size: 7.5px; text-transform: uppercase; letter-spacing: 0.8px; color: #0e7490; }
        .stat .value { font-size: 16px; font-weight: bold; color: #0b3a5b; margin-top: 2px; }
        .stat.accent { background-color: #0891b2; border-color: #0891b2; }
        .stat.accent .label { color: #e0f7fb; }
        .stat.accent .value { color: #ffffff; }

        .section-title { font-size: 10.5px; font-weight: bold; color: #0b3a5b; margin: 0 0 6px; padding-left: 7px; border-left: 3px solid #0891b2; }
        .formula { font-size: 8.5px; color: #475569; margin: 0 0 10px; }

        .grid { margin-bottom: 12px; }
        .grid th { background-color: #0b3a5b; color: #ffffff; font-size: 7.5px; text-transform: uppercase; letter-spacing: 0.4px; text-align: left; padding: 6px 6px; }
        .grid td { padding: 4px 6px; border-bottom: 1px solid #e2e8f0; }
        .grid tr.striped td { background-color: #f8fafc; }
        .grid .number { text-align: right; }
        .grid .strong { font-weight: bold; color: #0b3a5b; }
        .grid tr.total td { font-weight: bold; color: #0b3a5b; border-top: 2px solid #0b3a5b; background-color: #f0f9fc; }
        .grid .muted { color: #94a3b8; }
        .code { font-family: Courier, monospace; font-size: 9px; color: #0e7490; }

        .bottom td { vertical-align: top; }
        .approval { margin-top: 14px; width: 250px; page-break-inside: avoid; }
        .approval .caption { font-size: 8px; text-transform: uppercase; letter-spacing: 0.8px; color: #64748b; }
        .approval .signature { text-align: center; margin: 0 0 -12px; }
        .approval .signature img { height: 92px; }
        .approval .name { font-weight: bold; font-size: 10.5px; color: #0f172a; border-top: 1px solid #0b3a5b; padding-top: 4px; text-align: center; }
        .approval .position { text-align: center; color: #64748b; }
        .empty { color: #64748b; font-style: italic; padding: 12px 0; }
    </style>
</head>
<body>
    @php
        $usd = fn (?float $amount): string => $amount === null ? '-' : 'USD '.number_format($amount, 2);
        $php = fn (float $amount): string => 'PHP '.number_format($amount, 2);
        $days = fn (float $value): string => rtrim(rtrim(number_format($value, 2), '0'), '.');
        $hours = fn (int $minutes): string => sprintf('%dh %02dm', intdiv($minutes, 60), $minutes % 60);
    @endphp

    <div class="footer">
        <table>
            <tr>
                <td>LeadGen Central | {{ $headerRows[0][0] }}</td>
                <td style="text-align: right;"><span class="page-number"></span></td>
            </tr>
        </table>
    </div>

    @include('attendance.partials.export-masthead', ['eyebrow' => 'Team Payroll', 'heading' => 'Payroll Summary'])

    <table class="stats">
        <tr>
            <td><div class="stat"><div class="label">Payroll Members</div><div class="value">{{ count($payroll['members']) }}</div></div></td>
            <td><div class="stat"><div class="label">Total Salary</div><div class="value">{{ $usd($payroll['totals']['salary']) }}</div></div></td>
            <td><div class="stat"><div class="label">Total Service</div><div class="value">{{ $usd($payroll['totals']['service']) }}</div></div></td>
            <td><div class="stat accent"><div class="label">Total to Pay</div><div class="value">{{ $usd($payroll['totals']['to_pay']) }}</div></div></td>
        </tr>
    </table>

    <div class="section-title">Salary Breakdown</div>
    <p class="formula">
        Pay = Monthly Salary &divide; {{ $payroll['days_in_month'] }} days in {{ $payroll['month_label'] }} &times; Paid Days.
        Per Day = Monthly Salary &divide; {{ $payroll['days_in_month'] }}. Per Hour = Per Day &divide; {{ $paidHoursPerDay }} hours.
        Full-time paid days count every day from the join date (Sundays and holidays included) minus absent workdays;
        part-time paid days = hours worked &divide; {{ $paidHoursPerDay }}.
        @if ($payroll['in_progress'])
            This month is still in progress, so remaining days are counted as paid.
        @endif
    </p>

    @if ($payroll['members'] === [])
        <p class="empty">No payroll members for this period.</p>
    @else
        <table class="grid">
            <tr>
                <th>Code</th><th>Name</th><th>Category</th><th>Date of Join</th>
                <th class="number">Monthly Salary</th><th class="number">Per Day</th><th class="number">Per Hour</th>
                <th class="number">Hours Worked</th><th class="number">Absent Days</th><th class="number">Paid Days</th><th class="number">{{ $payroll['month_label'] }} Pay</th>
            </tr>
            @foreach ($payroll['members'] as $member)
                <tr class="{{ $loop->even ? 'striped' : '' }}">
                    <td class="code">{{ $member['employee_code'] ?? '-' }}</td>
                    <td class="strong">{{ $member['name'] }}</td>
                    <td>{{ $member['employment_type_label'] }}</td>
                    <td>{{ $member['joined_on'] ? \Illuminate\Support\Carbon::parse($member['joined_on'])->format('M j, Y') : '-' }}</td>
                    <td class="number">{{ $usd($member['monthly_salary']) }}</td>
                    <td class="number">{{ $usd($member['day_rate']) }}</td>
                    <td class="number">{{ $usd($member['hour_rate']) }}</td>
                    <td class="number">{{ $hours($member['worked_minutes']) }}</td>
                    <td class="number">{{ $member['absent_days'] }}</td>
                    <td class="number">{{ $days($member['paid_days']) }}</td>
                    <td class="number strong">{{ $member['pay'] === null ? 'Salary not set' : $usd($member['pay']) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="10" class="number">Total Salary</td>
                <td class="number">{{ $usd($payroll['totals']['salary']) }}</td>
            </tr>
        </table>
    @endif

    <table class="bottom">
        <tr>
            <td style="width: 55%; padding-right: 16px;">
                <div class="section-title">Services</div>
                <table class="grid">
                    <tr>
                        <th>Service</th><th class="number">PHP</th><th class="number">USD</th>
                    </tr>
                    @foreach ($payroll['utilities']['items'] as $item)
                        <tr class="{{ $loop->even ? 'striped' : '' }}">
                            <td>{{ $item['label'] }}</td>
                            <td class="number">{{ $php($item['php']) }}</td>
                            <td class="number">{{ $usd($item['usd']) }}</td>
                        </tr>
                    @endforeach
                    <tr class="total">
                        <td>USD to PHP rate: {{ $payroll['utilities']['usd_to_php_rate'] === null ? 'not set' : number_format($payroll['utilities']['usd_to_php_rate'], 2) }}</td>
                        <td class="number">Total Service</td>
                        <td class="number">{{ $usd($payroll['totals']['service']) }}</td>
                    </tr>
                </table>
                <table class="grid">
                    <tr class="total">
                        <td>Total to Pay (Total Salary + Total Service)</td>
                        <td class="number">{{ $usd($payroll['totals']['to_pay']) }}</td>
                    </tr>
                </table>
            </td>
            <td>
                @include('attendance.partials.export-approval')
            </td>
        </tr>
    </table>
</body>
</html>
