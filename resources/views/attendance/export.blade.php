<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $headerRows[0][0] }}</title>
    <style>
        @page { margin: 26px 30px 44px; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 9.5px; color: #1e293b; }
        .page + .page { page-break-before: always; }
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

        .profile { border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 12px; }
        .profile td { padding: 5px 10px; border-bottom: 1px solid #eef2f6; }
        .profile tr:last-child td { border-bottom: none; }
        .profile .label { width: 17%; color: #64748b; }
        .profile .value { width: 33%; font-weight: bold; color: #0f172a; }

        .grid th { background-color: #0b3a5b; color: #ffffff; font-size: 8px; text-transform: uppercase; letter-spacing: 0.5px; text-align: left; padding: 6px 7px; }
        .grid td { padding: 3.5px 7px; border-bottom: 1px solid #e2e8f0; }
        .grid tr.striped td { background-color: #f8fafc; }
        .grid .number { text-align: right; }
        .grid .strong { font-weight: bold; color: #0b3a5b; }
        .grid tr.rest-day td { background-color: #fff4d1; color: #7c5a00; }
        .grid tr.holiday td { background-color: #e1f3d8; color: #2f6b1f; }
        .grid .muted { color: #94a3b8; }
        .code { font-family: Courier, monospace; font-size: 9px; color: #0e7490; }

        .legend { margin-top: 6px; font-size: 8px; color: #64748b; }
        .legend .swatch { display: inline-block; width: 9px; height: 9px; border-radius: 2px; margin: 0 3px -1px 10px; }
        .legend .swatch.rest-day { background-color: #fff4d1; border: 1px solid #e6c65c; }
        .legend .swatch.holiday { background-color: #e1f3d8; border: 1px solid #8cc57a; }

        .approval { margin-top: 14px; width: 230px; page-break-inside: avoid; }
        .approval .caption { font-size: 8px; text-transform: uppercase; letter-spacing: 0.8px; color: #64748b; }
        .approval img { height: 64px; margin: 2px 0 -8px 30px; }
        .approval .name { font-weight: bold; font-size: 10.5px; color: #0f172a; border-top: 1px solid #0b3a5b; padding-top: 4px; text-align: center; }
        .approval .position { text-align: center; color: #64748b; }
        .empty { color: #64748b; font-style: italic; padding: 12px 0; }
    </style>
</head>
<body>
    @php
        $formatMinutes = fn (int $minutes): string => \App\Models\Attendance::formatMinutes($minutes);
    @endphp

    <div class="footer">
        <table>
            <tr>
                <td>LeadGen Central | {{ $headerRows[0][0] }}</td>
                <td style="text-align: right;"><span class="page-number"></span></td>
            </tr>
        </table>
    </div>

    <div class="page">
        @include('attendance.partials.export-masthead', ['eyebrow' => 'Team Attendance Summary', 'heading' => 'Attendance Summary'])

        <table class="stats">
            <tr>
                <td><div class="stat"><div class="label">Total Members</div><div class="value">{{ count($members) }}</div></div></td>
                <td><div class="stat"><div class="label">Total Attendance Days</div><div class="value">{{ $teamTotals['attendance_days'] }}</div></div></td>
                <td><div class="stat"><div class="label">Total Attendance Logs</div><div class="value">{{ $teamTotals['log_count'] }}</div></div></td>
                <td><div class="stat accent"><div class="label">Total Hours</div><div class="value">{{ $formatMinutes($teamTotals['worked_minutes']) }}</div></div></td>
            </tr>
        </table>

        <div class="section-title">Payroll Members</div>
        @if ($members === [])
            <p class="empty">No payroll members for this period.</p>
        @else
            <table class="grid">
                <tr>
                    <th>Employee Code</th><th>Name</th><th>Position</th><th class="number">Attendance Days</th><th class="number">Attendance Logs</th><th class="number">Total Hours</th>
                </tr>
                @foreach ($members as $member)
                    <tr class="{{ $loop->even ? 'striped' : '' }}">
                        <td class="code">{{ $member['user']->employee_code ?? '-' }}</td>
                        <td class="strong">{{ $member['user']->name }}</td>
                        <td>{{ $member['position'] }}</td>
                        <td class="number">{{ $member['totals']['attendance_days'] }}</td>
                        <td class="number">{{ $member['totals']['log_count'] }}</td>
                        <td class="number strong">{{ $formatMinutes($member['totals']['worked_minutes']) }}</td>
                    </tr>
                @endforeach
            </table>
        @endif

        @include('attendance.partials.export-approval')
    </div>

    @foreach ($members as $member)
        <div class="page">
            @include('attendance.partials.export-masthead', ['eyebrow' => 'Member Attendance', 'heading' => $member['user']->name])

            <table class="profile">
                <tr>
                    <td class="label">Name</td><td class="value">{{ $member['user']->name }}</td>
                    <td class="label">Role</td><td class="value">{{ $member['role'] }}</td>
                </tr>
                <tr>
                    <td class="label">Sub Name</td><td class="value">{{ $member['user']->alias_name ?? '-' }}</td>
                    <td class="label">Employee Code</td><td class="value code">{{ $member['user']->employee_code ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="label">Email</td><td class="value">{{ $member['user']->alias_email ?? '-' }}</td>
                    <td class="label">Status</td><td class="value">{{ ucfirst($member['user']->status->value) }}</td>
                </tr>
            </table>

            <table class="stats">
                <tr>
                    <td><div class="stat"><div class="label">Attendance Days</div><div class="value">{{ $member['totals']['attendance_days'] }}</div></div></td>
                    <td><div class="stat"><div class="label">Attendance Logs</div><div class="value">{{ $member['totals']['log_count'] }}</div></div></td>
                    <td colspan="2"><div class="stat accent"><div class="label">Member Total Hours</div><div class="value">{{ $formatMinutes($member['totals']['worked_minutes']) }}</div></div></td>
                </tr>
            </table>

            <div class="section-title">Daily Attendance</div>
            <table class="grid">
                <tr>
                    <th>Date</th><th>Day</th><th>Time In</th><th>Time Out</th><th class="number">Hours</th><th>Logs</th><th>Remarks</th>
                </tr>
                @foreach ($member['days'] as $day)
                    <tr class="{{ $day['type'] === 'workday' ? ($loop->even ? 'striped' : '') : str_replace('_', '-', $day['type']) }}">
                        <td>{{ $day['date'] }}</td>
                        <td>{{ $day['day'] }}</td>
                        <td>{{ $day['time_in'] }}</td>
                        <td>{{ $day['time_out'] }}</td>
                        <td class="number">{{ $day['total_hours'] }}</td>
                        <td>{{ $day['logs'] }}</td>
                        <td>{{ $day['remarks'] }}</td>
                    </tr>
                @endforeach
            </table>
            <div class="legend">
                Legend:<span class="swatch rest-day"></span>Rest day<span class="swatch holiday"></span>Holiday
            </div>

            @include('attendance.partials.export-approval')
        </div>
    @endforeach
</body>
</html>
