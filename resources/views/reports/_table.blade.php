@php
    // $mode is 'html' for the screen and 'pdf' for the PDF file.
    $isPdf = $mode === 'pdf';
    $format = function (array $column, mixed $value) use ($isPdf) {
        if ($value === null || $value === '') {
            return '';
        }

        return match ($column['type']) {
            'money' => ($isPdf ? 'BDT ' : '৳') . number_format((float) $value, 2),
            'number' => rtrim(rtrim(number_format((float) $value, 1, '.', ','), '0'), '.'),
            'date' => \Carbon\Carbon::parse($value)->format('d M Y'),
            'status' => ucwords(str_replace('_', ' ', (string) $value)),
            default => (string) $value,
        };
    };
    $right = fn (array $column) => in_array($column['type'], ['money', 'number'], true);
@endphp

<table class="{{ $isPdf ? 'pdf-table' : 'table table-sm table-hover align-middle mb-0' }}">
    <thead>
        <tr>
            @foreach ($report['columns'] as $column)
                <th class="{{ $right($column) ? 'text-end' : '' }}" @if ($isPdf && $right($column)) style="text-align: right;" @endif>{{ $column['label'] }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse ($report['rows'] as $row)
            <tr>
                @foreach ($report['columns'] as $column)
                    @php $value = $row[$column['key']] ?? null; @endphp
                    <td class="{{ $right($column) ? 'text-end' : '' }}" @if ($isPdf && $right($column)) style="text-align: right;" @endif>
                        @if (! $isPdf && $column['type'] === 'status' && $value)
                            <x-status-badge :status="$value" />
                        @else
                            {{ $format($column, $value) }}
                        @endif
                    </td>
                @endforeach
            </tr>
        @empty
            <tr><td colspan="{{ count($report['columns']) }}" style="text-align: center; padding: 18px; color: #777;">There is nothing to show for these filters.</td></tr>
        @endforelse
    </tbody>
    @if ($report['totals'] && count($report['rows']))
        <tfoot>
            <tr class="fw-bold" @if ($isPdf) style="font-weight: bold;" @endif>
                @foreach ($report['columns'] as $i => $column)
                    <td class="{{ $right($column) ? 'text-end' : '' }}" @if ($isPdf && $right($column)) style="text-align: right;" @endif>
                        @if ($i === 0) Total
                        @elseif (isset($report['totals'][$column['key']])) {{ $format($column, $report['totals'][$column['key']]) }}
                        @endif
                    </td>
                @endforeach
            </tr>
        </tfoot>
    @endif
</table>
