<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Analytics Report</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #1f2937; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .subtitle { color: #6b7280; margin: 0 0 20px; }
        h2 { font-size: 13px; margin: 20px 0 8px; padding-bottom: 4px; border-bottom: 1px solid #d1d5db; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        th, td { text-align: left; padding: 5px 8px; border-bottom: 1px solid #e5e7eb; }
        th { background-color: #f3f4f6; font-size: 10px; text-transform: uppercase; letter-spacing: 0.03em; color: #6b7280; }
        td { font-size: 11px; }
        .empty { color: #9ca3af; font-style: italic; }
    </style>
</head>
<body>
    <h1>LeadGen Central &mdash; Analytics Report</h1>
    <p class="subtitle">{{ $data['filters']['date_from'] }} to {{ $data['filters']['date_to'] }} &middot; Generated {{ now()->format('Y-m-d H:i') }}</p>

    <h2>Summary</h2>
    <table>
        <tr><th>Metric</th><th>Value</th></tr>
        <tr><td>Leads created</td><td>{{ $data['summary']['total_leads'] }}</td></tr>
        <tr><td>Qualified leads</td><td>{{ $data['summary']['qualified_leads'] }}</td></tr>
        <tr><td>Qualification rate</td><td>{{ $data['summary']['qualification_rate'] }}%</td></tr>
        <tr><td>Email replies</td><td>{{ $data['summary']['replies'] }}</td></tr>
        <tr><td>Reply rate</td><td>{{ $data['summary']['reply_rate'] }}%</td></tr>
        <tr><td>Interested replies</td><td>{{ $data['summary']['interested_replies'] }}</td></tr>
        <tr><td>Duplicates flagged</td><td>{{ $data['summary']['duplicates'] }}</td></tr>
    </table>

    @foreach ([['Lead status', $data['leadStatuses']], ['Reply classification', $data['replyClassifications']], ['Lead sources', $data['sources']], ['Top countries', $data['countries']]] as [$title, $items])
        <h2>{{ $title }}</h2>
        @if (count($items))
            <table>
                <tr><th>Label</th><th>Count</th></tr>
                @foreach ($items as $item)
                    <tr><td>{{ $item['label'] }}</td><td>{{ $item['value'] }}</td></tr>
                @endforeach
            </table>
        @else
            <p class="empty">No data for this period.</p>
        @endif
    @endforeach

    @if (count($data['agentPerformance']))
        <h2>Agent performance</h2>
        <table>
            <tr>
                <th>Agent</th><th>Leads</th><th>Qualified</th><th>Qual. rate</th><th>Replies</th>
                <th>Interested</th><th>Uploads</th><th>Avg batch</th><th>Dup. rate</th><th>Error rate</th>
            </tr>
            @foreach ($data['agentPerformance'] as $agent)
                <tr>
                    <td>{{ $agent['name'] }}</td>
                    <td>{{ $agent['leads'] }}</td>
                    <td>{{ $agent['qualified'] }}</td>
                    <td>{{ $agent['qualification_rate'] }}%</td>
                    <td>{{ $agent['replies'] }}</td>
                    <td>{{ $agent['interested'] }}</td>
                    <td>{{ $agent['uploads'] }}</td>
                    <td>{{ $agent['avg_batch_size'] }}</td>
                    <td>{{ $agent['duplicate_rate'] }}%</td>
                    <td>{{ $agent['error_rate'] }}%</td>
                </tr>
            @endforeach
        </table>
    @endif
</body>
</html>
