{{-- 100% stacked bar with a legend listing each segment's count and share. --}}
@if ($segments === [])
    <p class="empty">No data for this period.</p>
@else
    <table class="stack">
        <tr>
            @foreach ($segments as $segment)
                <td style="width: {{ max($segment['percent'], 0.4) }}%; background-color: {{ $segment['color'] }};"></td>
            @endforeach
        </tr>
    </table>
    <table class="legend">
        @foreach ($segments as $segment)
            <tr>
                <td class="swatch-cell"><div class="swatch" style="background-color: {{ $segment['color'] }};"></div></td>
                <td>{{ $segment['label'] }}</td>
                <td class="num">{{ number_format($segment['value']) }}</td>
                <td class="num muted">{{ $segment['percent'] }}%</td>
            </tr>
        @endforeach
    </table>
@endif
