<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Analytics Report</title>
    <style>
        @page { margin: 36px 38px 54px; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #1e293b; margin: 0; }
        p { margin: 0; }
        .muted { color: #64748b; }
        .empty { color: #94a3b8; font-style: italic; padding: 6px 0; }
        .num { text-align: right; }
        .page-break { page-break-before: always; }
        .keep { page-break-inside: avoid; }

        .footer { position: fixed; bottom: -34px; left: 0; right: 0; height: 18px; border-top: 1px solid #e2e8f0; padding-top: 6px; font-size: 8px; color: #94a3b8; }

        .cover { background-color: #0b1628; color: #ffffff; border-radius: 10px; padding: 20px 22px; }
        .cover td { vertical-align: top; }
        .brand { font-size: 13px; font-weight: bold; }
        .brand .mint { color: #6ee7b7; }
        .cover h1 { font-size: 22px; margin: 14px 0 4px; letter-spacing: -0.3px; }
        .cover .lead { color: #cbd5e1; font-size: 10.5px; }
        .meta { font-size: 9px; color: #94a3b8; text-align: right; line-height: 1.6; }
        .meta strong { color: #e2e8f0; }
        .pill { background-color: #10352f; color: #6ee7b7; border-radius: 9px; padding: 3px 8px; font-size: 8.5px; }

        h2 { font-size: 13px; margin: 22px 0 4px; padding-left: 8px; border-left: 3px solid #10b981; page-break-after: avoid; }
        h3 { font-size: 10.5px; margin: 14px 0 6px; color: #0f172a; page-break-after: avoid; }
        .section-note { color: #64748b; font-size: 9px; margin-bottom: 8px; }

        .grid { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin: 0 -8px; }
        .grid > tbody > tr > td, .grid > tr > td { vertical-align: top; }

        .kpis { width: 100%; border-collapse: separate; border-spacing: 8px; margin: 6px -8px 0; }
        .kpi { border: 1px solid #e2e8f0; border-radius: 8px; padding: 9px 10px; width: 25%; vertical-align: top; }
        .kpi-label { font-size: 7.5px; text-transform: uppercase; letter-spacing: 0.6px; color: #64748b; }
        .kpi-value { font-size: 17px; font-weight: bold; margin: 4px 0 3px; color: #0f172a; }
        .kpi-note { font-size: 8px; color: #64748b; }
        .up { color: #059669; font-weight: bold; }
        .down { color: #e11d48; font-weight: bold; }

        .card { border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; }
        .card-title { font-size: 10.5px; font-weight: bold; color: #0f172a; }
        .card-sub { font-size: 8.5px; color: #64748b; margin: 2px 0 10px; }

        .findings { width: 100%; border-collapse: collapse; }
        .findings td { padding: 4px 0; vertical-align: top; font-size: 10px; line-height: 1.45; }
        .findings .dot { width: 14px; }
        .findings .dot div { width: 6px; height: 6px; border-radius: 3px; background-color: #10b981; margin-top: 4px; }

        table.data { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.data th, table.data td { text-align: left; padding: 5px 7px; border-bottom: 1px solid #e2e8f0; }
        table.data th { background-color: #f1f5f9; font-size: 7.5px; text-transform: uppercase; letter-spacing: 0.4px; color: #475569; }
        table.data td { font-size: 9px; }
        table.data th.num, table.data td.num { text-align: right; }
        table.data tbody tr:nth-child(even) td { background-color: #f8fafc; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }

        table.bars { width: 100%; border-collapse: collapse; }
        table.bars td { padding: 3px 0; vertical-align: middle; font-size: 9px; }
        .bar-label { width: 38%; padding-right: 8px !important; }
        .bar-track { width: 40%; }
        .bar-fill { height: 9px; border-radius: 3px; }
        .bar-value { width: 12%; text-align: right; font-weight: bold; }
        .bar-share { width: 10%; text-align: right; color: #64748b; }

        table.stack { width: 100%; border-collapse: collapse; table-layout: fixed; margin: 2px 0 8px; }
        table.stack td { height: 14px; padding: 0; }
        table.legend { width: 100%; border-collapse: collapse; }
        table.legend td { padding: 3px 0; font-size: 9px; }
        .swatch-cell { width: 14px; }
        .swatch { width: 8px; height: 8px; border-radius: 2px; }

        table.columns { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .column-cell { vertical-align: bottom; padding: 0 1px; border-bottom: 1px solid #cbd5e1; }
        .column-bar { background-color: #10b981; border-radius: 2px 2px 0 0; }
        .column-axis { font-size: 6.5px; color: #94a3b8; padding-top: 3px; white-space: nowrap; }

        .stat { font-size: 8.5px; color: #64748b; }
        .stat strong { color: #0f172a; font-size: 10px; }
    </style>
</head>
<body>
    @php
        $report = $data['databaseReport'];
        $sections = $data['databaseSections'];
        $period = $data['filters']['date_from'].' to '.$data['filters']['date_to'];
        $previousPeriod = $report['previous_period']['from'].' to '.$report['previous_period']['to'];
        $quality = $report['quality'];
        $rate = fn (?float $value): string => $value === null ? 'N/A' : $value.'%';
        $logo = 'data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64"><rect width="64" height="64" rx="16" fill="#34d399"/><g fill="none" stroke="#0b1628" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"><path d="M15 21 32 12l17 9v18L32 48 15 39V21Z"/><path d="m15 21 17 9.5L49 21M32 30.5V48"/><path d="M23 44v7l9 5 9-5v-7"/></g></svg>');
    @endphp

    <div class="footer">
        <table style="width: 100%;"><tr>
            <td>LeadGen Central &middot; Database intelligence report &middot; {{ $period }}</td>
        </tr></table>
    </div>

    {{-- Cover --}}
    <div class="cover">
        <table style="width: 100%;"><tr>
            <td>
                <table><tr>
                    <td style="vertical-align: middle; padding-right: 8px;"><img src="{{ $logo }}" width="26" height="26" alt=""></td>
                    <td style="vertical-align: middle;" class="brand">LeadGen <span class="mint">Central</span></td>
                </tr></table>
                <h1>Database Intelligence Report</h1>
                <p class="lead">Record growth, data quality, sources, companies and geography for the selected period.</p>
            </td>
            <td class="meta" style="width: 42%;">
                <span class="pill">{{ $scope }}</span><br><br>
                Period <strong>{{ $period }}</strong><br>
                Compared with <strong>{{ $previousPeriod }}</strong><br>
                Generated <strong>{{ now()->format('Y-m-d H:i') }}</strong> by <strong>{{ $generatedBy }}</strong>
            </td>
        </tr></table>
    </div>

    {{-- Headline metrics --}}
    <table class="kpis">
        @foreach (array_chunk($pdf['kpis'], 4) as $row)
            <tr>
                @foreach ($row as $kpi)
                    <td class="kpi">
                        <div class="kpi-label">{{ $kpi['label'] }}</div>
                        <div class="kpi-value">{{ $kpi['value'] }}</div>
                        <div class="kpi-note">
                            @if ($kpi['note'] !== null)
                                {{ $kpi['note'] }}
                            @elseif ($kpi['change'] === null)
                                None in previous period
                            @else
                                <span @class(['up' => $kpi['change'] >= 0, 'down' => $kpi['change'] < 0])>{{ $kpi['change'] >= 0 ? '+' : '' }}{{ $kpi['change'] }}%</span>
                                vs {{ $kpi['previous'] }}
                            @endif
                        </div>
                    </td>
                @endforeach
            </tr>
        @endforeach
    </table>

    <table class="grid" style="margin-top: 8px;"><tr>
        <td style="width: 44%;">
            <div class="card keep">
                <div class="card-title">Key findings</div>
                <div class="card-sub">Generated from this period's data.</div>
                <table class="findings">
                    @foreach ($pdf['highlights'] as $highlight)
                        <tr><td class="dot"><div></div></td><td>{{ $highlight }}</td></tr>
                    @endforeach
                </table>
            </div>
        </td>
        <td style="width: 56%;">
            <div class="card keep">
                <div class="card-title">Records added per {{ $pdf['growth']['bucket'] }}</div>
                <div class="card-sub">Peak {{ number_format($pdf['growth']['max']) }} &middot; average {{ number_format($pdf['growth']['average'], 1) }} per {{ $pdf['growth']['bucket'] }}</div>
                @include('reports.partials.columns', ['columns' => $pdf['growth']['columns']])
                <table style="width: 100%; margin-top: 10px;"><tr>
                    <td class="stat">Records added<br><strong>{{ number_format($report['growth']['totals']['records'] ?? $pdf['growth']['total']) }}</strong></td>
                    <td class="stat">First-seen companies<br><strong>{{ number_format($report['growth']['totals']['companies'] ?? 0) }}</strong></td>
                    <td class="stat">First-seen emails<br><strong>{{ number_format($report['growth']['totals']['emails'] ?? 0) }}</strong></td>
                </tr></table>
            </div>
        </td>
    </tr></table>

    <h2>Database totals</h2>
    <p class="section-note">All-time figures alongside the selected period, so period growth can be read against the whole database.</p>
    <table class="data">
        <thead><tr><th>Metric</th><th class="num">Selected period</th><th class="num">Previous period</th><th class="num">All time</th><th class="num">Period share of all time</th></tr></thead>
        <tbody>
            @foreach (['records' => 'Records', 'companies' => 'Unique companies', 'emails' => 'Unique emails', 'countries' => 'Countries', 'cities' => 'Cities', 'sources' => 'Raw data sources', 'uploads' => 'Upload batches'] as $key => $label)
                @php($allTime = (int) ($report['all_time'][$key] ?? 0))
                <tr>
                    <td>{{ $label }}</td>
                    <td class="num">{{ number_format((int) $report['overview'][$key]) }}</td>
                    <td class="num">{{ number_format((int) ($report['previous'][$key] ?? 0)) }}</td>
                    <td class="num">{{ number_format($allTime) }}</td>
                    <td class="num">{{ $allTime > 0 ? round(100 * $report['overview'][$key] / $allTime, 1).'%' : 'N/A' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Composition --}}
    <div class="page-break"></div>
    <h2 style="margin-top: 0;">Record composition</h2>
    <p class="section-note">How this period's records are classified and where they came from. Classifications are current statuses, not funnel stages.</p>
    <table class="grid"><tr>
        <td style="width: 50%;">
            <div class="card keep">
                <div class="card-title">Classification</div>
                <div class="card-sub">{{ number_format((int) $report['overview']['records']) }} records by current status</div>
                @include('reports.partials.stack', ['segments' => $pdf['classification']])
            </div>
        </td>
        <td style="width: 50%;">
            <div class="card keep">
                <div class="card-title">Records by source</div>
                <div class="card-sub">Current lead source; share of all period records</div>
                @include('reports.partials.bars', ['bars' => $pdf['sources'], 'color' => '#0ea5e9'])
            </div>
        </td>
    </tr></table>

    <h3>Source quality</h3>
    <p class="section-note">Import outcomes per source, using the source recorded on each import row. Rates exclude pending rows.</p>
    @include('reports.partials.table', ['rows' => $sections['Source quality - selected period']])

    {{-- Import quality --}}
    <h2>Import quality</h2>
    <p class="section-note">Outcomes of upload rows in batches created during the period.</p>
    <table class="grid"><tr>
        <td style="width: 50%;">
            <div class="card keep">
                <div class="card-title">Row outcomes</div>
                <div class="card-sub">{{ number_format((int) $quality['processed']) }} processed rows by processing status</div>
                @include('reports.partials.stack', ['segments' => $pdf['outcomes']])
            </div>
        </td>
        <td style="width: 50%;">
            <div class="card keep">
                <div class="card-title">Rates</div>
                <div class="card-sub">Share of processed rows; categories can overlap</div>
                <table class="bars">
                    @foreach ([['Acceptance', $quality['accepted_rate'], '#10b981'], ['Duplicates', $quality['duplicates_rate'], '#f59e0b'], ['Rejection', $quality['rejected_rate'], '#f43f5e'], ['Errors', $quality['errors_rate'], '#64748b'], ['Clean (no issues)', $quality['clean_rate'] ?? null, '#14b8a6']] as [$label, $value, $color])
                        <tr>
                            <td class="bar-label">{{ $label }}</td>
                            <td class="bar-track"><div class="bar-fill" style="width: {{ max((float) $value, 0.6) }}%; background-color: {{ $color }};"></div></td>
                            <td class="bar-value" colspan="2">{{ $rate($value) }}</td>
                        </tr>
                    @endforeach
                </table>
                <p class="stat" style="margin-top: 8px;">Average batch size <strong>{{ $quality['average_batch_size'] ?? 'N/A' }}</strong> rows across <strong>{{ number_format((int) $report['overview']['uploads']) }}</strong> batches</p>
            </div>
        </td>
    </tr></table>

    @if ($pdf['qualityTrend']['max'] > 0)
        <div class="card keep" style="margin-top: 8px;">
            <div class="card-title">Processed rows over time</div>
            <div class="card-sub">Stacked by outcome &middot; busiest {{ number_format($pdf['qualityTrend']['max']) }} rows</div>
            @include('reports.partials.columns', ['columns' => $pdf['qualityTrend']['columns']])
            <table class="legend" style="width: auto; margin-top: 6px;"><tr>
                @foreach ([['Accepted', '#10b981'], ['Skipped as duplicate', '#f59e0b'], ['Rejected', '#f43f5e'], ['Errors', '#64748b']] as [$label, $color])
                    <td class="swatch-cell"><div class="swatch" style="background-color: {{ $color }};"></div></td>
                    <td style="padding-right: 14px;">{{ $label }}</td>
                @endforeach
            </tr></table>
        </div>
    @endif


    {{-- Companies --}}
    <div class="page-break"></div>
    <h2 style="margin-top: 0;">Companies and contacts</h2>
    <p class="section-note">Companies are grouped by normalized name. Repeated company names are not automatically duplicates.</p>
    <table class="grid"><tr>
        <td style="width: 38%;">
            <div class="card keep">
                <div class="card-title">Contact coverage</div>
                <div class="card-sub">Average {{ $report['company_analysis']['average_contacts'] ?? 'N/A' }} contact records per company</div>
                @include('reports.partials.stack', ['segments' => $pdf['companySplit']])
                @if (($report['company_analysis']['unnamed_records'] ?? 0) > 0)
                    <p class="stat" style="margin-top: 8px;"><strong>{{ number_format($report['company_analysis']['unnamed_records']) }}</strong> records have no company name</p>
                @endif
            </div>
        </td>
        <td style="width: 62%;">
            <div class="card keep">
                <div class="card-title">Top {{ count($pdf['companies']) }} companies by contact records</div>
                <div class="card-sub">Share of all period records</div>
                @include('reports.partials.bars', ['bars' => $pdf['companies'], 'color' => '#6366f1'])
            </div>
        </td>
    </tr></table>

    {{-- Geography --}}
    <h2>Geography</h2>
    <p class="section-note">Countries resolve from stored codes, the country list or known aliases; unrecognized values keep their historical label. Location data may be incomplete.</p>
    <table class="grid"><tr>
        <td style="width: 50%;">
            <div class="card keep">
                <div class="card-title">Records by region</div>
                <div class="card-sub">Share of all period records</div>
                @include('reports.partials.bars', ['bars' => $pdf['regions'], 'color' => '#14b8a6'])
            </div>
        </td>
        <td style="width: 50%;">
            <div class="card keep">
                <div class="card-title">Top countries</div>
                <div class="card-sub">{{ number_format((int) $report['overview']['countries']) }} countries represented</div>
                @include('reports.partials.bars', ['bars' => $pdf['countries'], 'color' => '#0ea5e9'])
            </div>
        </td>
    </tr></table>
    <div class="card keep" style="margin-top: 8px;">
        <div class="card-title">Top {{ count($pdf['cities']) }} cities, states and provinces</div>
        <div class="card-sub">Uses the city field, which often holds a state or province</div>
        @include('reports.partials.bars', ['bars' => $pdf['cities'], 'color' => '#10b981'])
    </div>

    <h3>Leads by region</h3>
    @include('reports.partials.table', ['rows' => $sections['Leads by region - selected period']])
    <h3>Leads by country</h3>
    @include('reports.partials.table', ['rows' => $sections['Leads by country - selected period']])
    <h3>Leads by city or capital (top 50)</h3>
    @include('reports.partials.table', ['rows' => $sections['Leads by city or capital - selected period (top 50)']])

    {{-- Agents --}}
    @if ($report['can_compare_agents'])
        <div class="page-break"></div>
        <h2 style="margin-top: 0;">Agent contribution</h2>
        <p class="section-note">Records owned by each agent in the period, with the quality of the batches they uploaded.</p>
        <div class="card keep">
            <div class="card-title">Records by agent</div>
            <div class="card-sub">Top {{ count($pdf['agents']) }} agents; share of all period records</div>
            @include('reports.partials.bars', ['bars' => $pdf['agents'], 'color' => '#8b5cf6'])
        </div>
        <h3>Contribution detail</h3>
        @include('reports.partials.table', ['rows' => $sections['Database contribution by agent - selected period']])
        <h3>Leads by agent</h3>
        @include('reports.partials.table', ['rows' => $sections['Leads by agent - selected period']])

        @if (count($data['agentPerformance']))
            <h3>Agent performance</h3>
            <table class="data">
                <thead><tr>
                    <th>Agent</th><th class="num">Leads</th><th class="num">Qualified</th><th class="num">Qual. rate</th>
                    <th class="num">Uploads</th><th class="num">Avg batch</th><th class="num">Dup. rate</th><th class="num">Error rate</th>
                </tr></thead>
                <tbody>
                    @foreach ($data['agentPerformance'] as $agent)
                        <tr>
                            <td>{{ $agent['name'] }}</td>
                            <td class="num">{{ number_format($agent['leads']) }}</td>
                            <td class="num">{{ number_format($agent['qualified']) }}</td>
                            <td class="num">{{ $agent['qualification_rate'] }}%</td>
                            <td class="num">{{ number_format($agent['uploads']) }}</td>
                            <td class="num">{{ $agent['avg_batch_size'] }}</td>
                            <td class="num">{{ $agent['duplicate_rate'] }}%</td>
                            <td class="num">{{ $agent['error_rate'] }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endif

    {{-- Appendix --}}
    <div class="page-break"></div>
    <h2 style="margin-top: 0;">Appendix</h2>
    <h3>Database growth by {{ $report['growth']['granularity'] ?? 'day' }}</h3>
    @include('reports.partials.table', ['rows' => $sections['Database growth - selected period']])

    <h3>Upload quality detail</h3>
    @include('reports.partials.table', ['rows' => $sections['Upload quality - selected-period batches']])

    <h3>Top companies detail</h3>
    @include('reports.partials.table', ['rows' => $sections['Top companies by contact records']])

    <h3>Classification distribution</h3>
    @include('reports.partials.table', ['rows' => $sections['Database classification distribution']])

    <h3>Country labels as stored</h3>
    @include('reports.partials.table', ['rows' => $sections['Geographic analysis - selected period']])

    <h3>Lead activity summary</h3>
    <table class="grid"><tr>
        <td style="width: 50%;">
            <table class="data">
                <thead><tr><th>Metric</th><th class="num">Value</th></tr></thead>
                <tbody>
                    <tr><td>Leads created</td><td class="num">{{ number_format($data['summary']['total_leads']) }}</td></tr>
                    <tr><td>Qualified leads</td><td class="num">{{ number_format($data['summary']['qualified_leads']) }}</td></tr>
                    <tr><td>Qualification rate</td><td class="num">{{ $data['summary']['qualification_rate'] }}%</td></tr>
                    <tr><td>Duplicates flagged</td><td class="num">{{ number_format($data['summary']['duplicates']) }}</td></tr>
                </tbody>
            </table>
        </td>
        <td style="width: 50%;">
            <table class="data">
                <thead><tr><th>Raw lead source</th><th class="num">Records</th></tr></thead>
                <tbody>
                    @forelse ($data['sources'] as $item)
                        <tr><td>{{ $item['label'] }}</td><td class="num">{{ number_format($item['value']) }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="empty">No data for this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </td>
    </tr></table>

    <h3>Metric definitions</h3>
    @include('reports.partials.table', ['rows' => $sections['Metric definitions']])
</body>
</html>
