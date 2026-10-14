@extends('layouts.app')

@section('title', $definition['title'])

@push('styles')
    <style>
        @media print {
            .app-sidebar, .app-header, .app-footer, .no-print, .toast-container { display: none !important; }
            .app-shell, .app-main { display: block !important; }
            .app-content { padding: 0 !important; }
            body { background: #fff !important; color: #000 !important; }
            .card { border: 0 !important; }
            .table { font-size: 11px; }
            @page { size: A4 landscape; margin: 10mm; }
        }
    </style>
@endpush

@section('content')
    @php $query = request()->query(); @endphp

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 no-print">
        <a href="{{ route('reports.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>All reports</a>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary" data-print><i class="bi bi-printer me-1"></i>Print</button>
            <a href="{{ route('reports.pdf', ['key' => $key] + $query) }}" class="btn btn-outline-primary"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>
            <a href="{{ route('reports.csv', ['key' => $key] + $query) }}" class="btn btn-outline-success"><i class="bi bi-filetype-csv me-1"></i>CSV / Excel</a>
        </div>
    </div>

    @if (count($fields))
        <div class="card mb-3 no-print">
            <div class="card-body">
                <form method="GET" action="{{ route('reports.show', $key) }}" class="row g-2 align-items-end">
                    @foreach ($fields as $name => $field)
                        <div class="col-lg-3 col-md-4 col-sm-6">
                            <label for="f_{{ $name }}" class="form-label small text-muted mb-1">{{ $field['label'] }}</label>
                            @if ($field['input'] === 'select')
                                <select id="f_{{ $name }}" name="{{ $name }}" class="form-select">
                                    @if (array_key_exists('blank', $field) && $field['blank'] !== null) <option value="">{{ $field['blank'] }}</option> @endif
                                    @foreach ($field['choices'] as $value => $label)
                                        <option value="{{ $value }}" @selected((string) ($filters[$name] ?? '') === (string) $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            @else
                                <input type="{{ $field['input'] }}" id="f_{{ $name }}" name="{{ $name }}" value="{{ $filters[$name] ?? '' }}"
                                       class="form-control" @if ($field['input'] === 'number') min="2000" max="2100" @endif>
                            @endif
                        </div>
                    @endforeach
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Show report</button>
                        <a href="{{ route('reports.show', $key) }}" class="btn btn-light">Reset</a>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <div>{{ $definition['title'] }}</div>
            @php
                $describe = [];
                foreach ($fields as $name => $field) {
                    $value = $filters[$name] ?? null;
                    if ($value === null || $value === '') continue;
                    $describe[] = $field['label'] . ': ' . match ($field['input']) {
                        'month' => \Carbon\Carbon::createFromFormat('!Y-m', $value)->format('F Y'),
                        'date' => \Carbon\Carbon::parse($value)->format('d M Y'),
                        'select' => $field['choices'][$value] ?? $value,
                        default => $value,
                    };
                }
            @endphp
            @if ($describe)<div class="small text-muted fw-normal">{{ implode('  ·  ', $describe) }}</div>@endif
        </div>
        <div class="table-responsive">
            @include('reports._table', ['mode' => 'html'])
        </div>
        @if ($report['notes'])
            <div class="card-footer small text-muted">{{ implode('  ·  ', $report['notes']) }}</div>
        @endif
    </div>
@endsection
