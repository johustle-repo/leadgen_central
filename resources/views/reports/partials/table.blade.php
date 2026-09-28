{{-- Detail table; numeric columns (all but the first) are right-aligned. --}}
<table class="data">
    <thead>
        <tr>
            @foreach ($rows[0] as $index => $heading)
                <th @class(['num' => $index > 0])>{{ $heading }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse (array_slice($rows, 1) as $row)
            <tr>
                @foreach ($row as $index => $cell)
                    <td @class(['num' => $index > 0])>{{ is_int($cell) ? number_format($cell) : $cell }}</td>
                @endforeach
            </tr>
        @empty
            <tr><td colspan="{{ count($rows[0]) }}" class="empty">No data for this period.</td></tr>
        @endforelse
    </tbody>
</table>
