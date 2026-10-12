<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $definition['title'] }}</title>
    <style>
        @page { margin: 14mm 10mm; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 10px; color: #111; }
        h1 { font-size: 15px; margin: 0 0 2px 0; }
        .company { font-size: 11px; color: #555; margin-bottom: 8px; }
        .meta { color: #555; margin-bottom: 8px; }
        .pdf-table { width: 100%; border-collapse: collapse; }
        .pdf-table th { background: #eef1f6; border: 1px solid #c9cfda; padding: 4px 5px; text-align: left; font-size: 9px; text-transform: uppercase; }
        .pdf-table td { border: 1px solid #d5d9e2; padding: 3px 5px; }
        .pdf-table tfoot td { background: #f6f7fa; }
        .notes { margin-top: 8px; color: #555; }
        .footer { margin-top: 12px; color: #888; font-size: 9px; }
    </style>
</head>
<body>
    <div class="company">{{ $company }}</div>
    <h1>{{ $definition['title'] }}</h1>
    @if ($filterText !== '') <div class="meta">{{ $filterText }}</div> @endif

    @include('reports._table', ['mode' => 'pdf'])

    @if ($report['notes']) <div class="notes">{{ implode('  ·  ', $report['notes']) }}</div> @endif
    <div class="footer">Generated on {{ now()->format('d M Y, h:i A') }}</div>
</body>
</html>
