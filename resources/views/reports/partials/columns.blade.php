{{-- Vertical column chart; each column is either a single bar or a stack of coloured parts. --}}
@php($plotHeight = 120)
@if ($columns === [])
    <p class="empty">No data for this period.</p>
@else
    <table class="columns">
        <tr>
            @foreach ($columns as $column)
                <td class="column-cell" style="height: {{ $plotHeight }}px;">
                    @if (isset($column['stack']))
                        @foreach ($column['stack'] as $part)
                            <div class="column-part" style="height: {{ max(1, round($part['height'] * $plotHeight / 100)) }}px; background-color: {{ $part['color'] }};"></div>
                        @endforeach
                    @elseif ($column['value'] > 0)
                        <div class="column-bar" style="height: {{ max(1, round($column['height'] * $plotHeight / 100)) }}px;"></div>
                    @endif
                </td>
            @endforeach
        </tr>
        <tr>
            @foreach ($columns as $column)
                <td class="column-axis">{{ $column['show_label'] ? $column['label'] : '' }}</td>
            @endforeach
        </tr>
    </table>
@endif
