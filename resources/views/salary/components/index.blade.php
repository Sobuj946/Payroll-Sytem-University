@extends('layouts.app')

@section('title', 'Salary Components')

@section('content')
    @include('salary._nav')

    @php
        $sourceLabels = ['structure' => 'In salary structure', 'adjustment' => 'Entered every month', 'system' => 'Calculated by payroll'];
    @endphp

    @can('salary.manage')
        <div class="d-flex justify-content-end mb-3">
            <a href="{{ route('salary.components.create') }}" class="btn btn-success"><i class="bi bi-plus-lg me-1"></i>Add component</a>
        </div>
    @endcan

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr><th>Component</th><th>Code</th><th>Type</th><th>How it is used</th><th class="text-end">Default</th><th>Taxable</th><th>Status</th>@can('salary.manage')<th class="text-end">Actions</th>@endcan</tr>
                </thead>
                <tbody>
                    @foreach ($components as $component)
                        <tr>
                            <td class="fw-semibold">
                                {{ $component->name }}
                                @if ($component->source === 'system') <i class="bi bi-lock-fill text-muted ms-1" title="Calculated by the payroll engine"></i> @endif
                            </td>
                            <td><code>{{ $component->code }}</code></td>
                            <td><span class="badge text-bg-{{ $component->type === 'earning' ? 'success' : 'danger' }}">{{ ucfirst($component->type) }}</span></td>
                            <td class="text-muted">{{ $sourceLabels[$component->source] ?? $component->source }}</td>
                            <td class="text-end">
                                @if ($component->source === 'system') —
                                @elseif ($component->calc_type === 'percent') {{ $component->default_value + 0 }}% of basic
                                @else ৳{{ number_format($component->default_value, 2) }} @endif
                            </td>
                            <td>{{ $component->is_taxable ? 'Yes' : 'No' }}</td>
                            <td><x-status-badge :status="$component->status" /></td>
                            @can('salary.manage')
                                <td class="text-end text-nowrap">
                                    @if ($component->source !== 'system')
                                        <a href="{{ route('salary.components.edit', $component) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                        <form method="POST" action="{{ route('salary.components.status', $component) }}" class="d-inline"
                                              data-confirm="{{ $component->status === 'active' ? 'Deactivate' : 'Activate' }} {{ $component->name }}?">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-sm btn-outline-{{ $component->status === 'active' ? 'warning' : 'success' }}">
                                                {{ $component->status === 'active' ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            @endcan
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer small text-muted">
            Percentages are always taken from the basic salary. Inactive components are skipped when payroll is processed.
            Rates and amounts here are defaults: each employee can have their own value on their salary page.
        </div>
    </div>
@endsection
