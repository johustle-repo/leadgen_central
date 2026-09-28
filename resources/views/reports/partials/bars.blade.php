{{-- Ranked horizontal bar chart: label, bar scaled to the largest value, count, share of total. --}}
@if ($bars === [])
    <p class="empty">No data for this period.</p>
@else
    <table class="bars">
        @foreach ($bars as $bar)
            <tr>
                <td class="bar-label">{{ $bar['label'] }}</td>
                <td class="bar-track">
                    <div class="bar-fill" style="width: {{ max($bar['width'], 0.6) }}%; background-color: {{ $color ?? '#10b981' }};"></div>
                </td>
                <td class="bar-value">{{ number_format($bar['value']) }}</td>
                <td class="bar-share">{{ $bar['percent'] }}%</td>
            </tr>
        @endforeach
    </table>
@endif
