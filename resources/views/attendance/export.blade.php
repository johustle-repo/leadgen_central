<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Attendance Report</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #1f2937; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .subtitle { color: #6b7280; margin: 0 0 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        th, td { text-align: left; padding: 5px 8px; border-bottom: 1px solid #e5e7eb; }
        th { background-color: #f3f4f6; font-size: 10px; text-transform: uppercase; letter-spacing: 0.03em; color: #6b7280; }
        td { font-size: 11px; }
        .empty { color: #9ca3af; font-style: italic; }
    </style>
</head>
<body>
    <h1>LeadGen Central &mdash; Attendance Report</h1>
    <p class="subtitle">Generated {{ now()->format('Y-m-d H:i') }}</p>

    @if ($records->count())
        <table>
            <tr>
                <th>Name</th><th>Role</th><th>Entry</th><th>Recorded at</th><th>Status</th><th>Total Hours</th>
            </tr>
            @foreach ($records as $record)
                <tr>
                    <td>{{ $record['user_name'] }}</td>
                    <td>{{ $record['role_label'] }}</td>
                    <td>{{ $record['entry_label'] }}</td>
                    <td>{{ $record['recorded_at'] }}</td>
                    <td>{{ $record['status_label'] }}</td>
                    <td>{{ $record['total_hours_label'] }}</td>
                </tr>
            @endforeach
        </table>
    @else
        <p class="empty">No attendance records yet.</p>
    @endif
</body>
</html>
