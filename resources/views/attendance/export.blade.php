<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $headerRows[0][0] }}</title>
    <style>
        @page { margin: 22px 28px; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #111827; }
        .page + .page { page-break-before: always; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #000; padding: 4px 6px; text-align: left; vertical-align: middle; }
        th { font-weight: bold; background-color: #f3f4f6; }
        .title { border: 1px solid #000; padding: 7px; text-align: center; font-weight: bold; font-size: 12px; margin-bottom: 10px; }
        .details { width: 55%; margin-bottom: 10px; }
        .details td.label { width: 40%; }
        .details td.value { font-weight: bold; }
        .details td.plain { font-weight: normal; }
        .section { margin-bottom: 10px; }
        .profile td { padding: 1px 6px; }
        .profile td.label { width: 16%; }
        .profile td.value { width: 34%; font-weight: bold; }
        .profile td.plain { font-weight: normal; }
        .days th, .days td { padding: 1px 5px; font-size: 9px; line-height: 1.15; }
        .number { text-align: right; }
        .rest-day td { background-color: #ffe699; }
        .holiday td { background-color: #a9d18e; }
        .approval { margin-top: 12px; width: 240px; text-align: center; page-break-inside: avoid; }
        .approval .caption { text-align: left; margin-bottom: 2px; }
        .approval img { height: 60px; }
        .approval .name { font-weight: bold; border-top: 1px solid #000; padding-top: 3px; }
        .empty { color: #6b7280; font-style: italic; }
    </style>
</head>
<body>
    @php
        $formatMinutes = fn (int $minutes): string => \App\Models\Attendance::formatMinutes($minutes);
    @endphp

    <div class="page">
        <div class="title">{{ $headerRows[0][0] }}</div>

        <table class="details">
            <tr><td class="label">{{ $headerRows[1][0] }}</td><td class="value">{{ $headerRows[1][1] }}</td></tr>
            <tr><td class="label">{{ $headerRows[2][0] }}</td><td class="value plain">{{ $headerRows[2][1] }}</td></tr>
        </table>

        <table class="details section">
            <tr><td class="label">Total Members</td><td class="value plain">{{ count($members) }}</td></tr>
            <tr><td class="label">Total Attendance Days</td><td class="value plain">{{ $teamTotals['attendance_days'] }}</td></tr>
            <tr><td class="label">Total Attendance Logs</td><td class="value plain">{{ $teamTotals['log_count'] }}</td></tr>
            <tr><td class="label">Total Hours</td><td class="value">{{ $formatMinutes($teamTotals['worked_minutes']) }}</td></tr>
        </table>

        @if ($members === [])
            <p class="empty">No payroll members for this period.</p>
        @else
            <table>
                <tr>
                    <th>Employee Code</th><th>Name</th><th>Position</th><th class="number">Attendance Days</th><th class="number">Attendance Logs</th><th class="number">Total Hours</th>
                </tr>
                @foreach ($members as $member)
                    <tr>
                        <td>{{ $member['user']->employee_code }}</td>
                        <td>{{ $member['user']->name }}</td>
                        <td>{{ $member['position'] }}</td>
                        <td class="number">{{ $member['totals']['attendance_days'] }}</td>
                        <td class="number">{{ $member['totals']['log_count'] }}</td>
                        <td class="number">{{ $formatMinutes($member['totals']['worked_minutes']) }}</td>
                    </tr>
                @endforeach
            </table>
        @endif

        @include('attendance.partials.export-approval', ['showPosition' => true])
    </div>

    @foreach ($members as $member)
        <div class="page">
            <div class="title">{{ $headerRows[0][0] }}</div>

            <table class="profile section">
                <tr>
                    <td class="label">{{ $headerRows[1][0] }}</td><td class="value">{{ $headerRows[1][1] }}</td>
                    <td class="label">Role</td><td class="value plain">{{ $member['role'] }}</td>
                </tr>
                <tr>
                    <td class="label">{{ $headerRows[2][0] }}</td><td class="value plain">{{ $headerRows[2][1] }}</td>
                    <td class="label">Employee Code</td><td class="value plain">{{ $member['user']->employee_code }}</td>
                </tr>
                <tr>
                    <td class="label">Name</td><td class="value">{{ $member['user']->name }}</td>
                    <td class="label">Status</td><td class="value plain">{{ ucfirst($member['user']->status->value) }}</td>
                </tr>
                <tr>
                    <td class="label">Sub Name</td><td class="value plain">{{ $member['user']->alias_name }}</td>
                    <td class="label">Attendance Days</td><td class="value plain">{{ $member['totals']['attendance_days'] }}</td>
                </tr>
                <tr>
                    <td class="label">Email</td><td class="value plain">{{ $member['user']->alias_email }}</td>
                    <td class="label">Attendance Logs</td><td class="value plain">{{ $member['totals']['log_count'] }}</td>
                </tr>
                <tr>
                    <td class="label"></td><td class="value plain"></td>
                    <td class="label">Member Total Hours</td><td class="value">{{ $formatMinutes($member['totals']['worked_minutes']) }}</td>
                </tr>
            </table>

            <table class="days">
                <tr>
                    <th>Date</th><th>Day</th><th>Time In</th><th>Time Out</th><th>Daily Total Hours</th><th>Logs</th><th>Remarks</th>
                </tr>
                @foreach ($member['days'] as $day)
                    <tr class="{{ str_replace('_', '-', $day['type']) }}">
                        <td>{{ $day['date'] }}</td>
                        <td>{{ $day['day'] }}</td>
                        <td>{{ $day['time_in'] }}</td>
                        <td>{{ $day['time_out'] }}</td>
                        <td>{{ $day['total_hours'] }}</td>
                        <td>{{ $day['logs'] }}</td>
                        <td>{{ $day['remarks'] }}</td>
                    </tr>
                @endforeach
            </table>

            @include('attendance.partials.export-approval', ['showPosition' => false])
        </div>
    @endforeach
</body>
</html>
