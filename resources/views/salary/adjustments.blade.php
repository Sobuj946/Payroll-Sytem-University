@extends('layouts.app')

@section('title', 'Overtime, Bonus & Other Items')

@section('content')
    @include('salary._nav')

    @php $taka = fn ($amount) => '৳' . number_format((float) $amount, 2); @endphp

    @if ($errors->any())
        <div class="alert alert-danger py-2">
            @foreach ($errors->all() as $error) <div>{{ $error }}</div> @endforeach
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('salary.adjustments.index') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label for="month" class="form-label small text-muted mb-1">Payroll month</label>
                    <input type="month" id="month" name="month" value="{{ $month->format('Y-m') }}" class="form-control">
                </div>
                <div class="col-md-auto">
                    <button type="submit" class="btn btn-primary">Show</button>
                    <a href="{{ route('salary.adjustments.index') }}" class="btn btn-light">This month</a>
                </div>
                @if ($period)
                    <div class="col-md text-md-end">
                        <span class="text-muted me-1">{{ $period->label }}:</span><x-status-badge :status="$period->status" />
                    </div>
                @endif
            </form>
        </div>
    </div>

    @if (! $period)
        <div class="card">
            <div class="card-body empty-state">
                <i class="bi bi-calendar-plus"></i>
                There is no payroll period for {{ $month->format('F Y') }} yet.
                @can('payroll.process')
                    <form method="POST" action="{{ route('payroll.periods.store') }}" class="mt-3">
                        @csrf
                        <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
                        <button type="submit" class="btn btn-primary">Create the {{ $month->format('F Y') }} period</button>
                    </form>
                @endcan
            </div>
        </div>
    @else
        <div class="row g-3">
            <div class="{{ $canEdit ? 'col-xl-8' : 'col-12' }}">
                @if ($period->status === 'processed' && $canEdit)
                    <div class="alert alert-warning py-2"><i class="bi bi-exclamation-triangle me-1"></i>Payroll for this month is already processed. After changing items, process it again from the <a href="{{ route('payroll.show', $period) }}">payroll page</a>.</div>
                @endif
                <div class="card">
                    <div class="card-header">Items for {{ $period->label }}</div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead><tr><th>Employee</th><th>Item</th><th class="text-end">Amount</th><th>Note</th>@if ($canEdit)<th></th>@endif</tr></thead>
                            <tbody>
                                @forelse ($adjustments as $adjustment)
                                    <tr>
                                        <td>{{ $adjustment->employee->full_name }}<div class="small text-muted">{{ $adjustment->employee->employee_code }}</div></td>
                                        <td>
                                            <span class="badge text-bg-{{ $adjustment->component->type === 'earning' ? 'success' : 'danger' }}">{{ $adjustment->component->type === 'earning' ? '+' : '−' }}</span>
                                            {{ $adjustment->component->name }}
                                        </td>
                                        <td class="text-end">{{ $taka($adjustment->amount) }}</td>
                                        <td class="text-muted">{{ $adjustment->note ?: '—' }}</td>
                                        @if ($canEdit)
                                            <td class="text-end">
                                                <form method="POST" action="{{ route('salary.adjustments.destroy', $adjustment) }}" class="d-inline"
                                                      data-confirm="Remove {{ $adjustment->component->name }} for {{ $adjustment->employee->full_name }}?">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                                </form>
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr><td colspan="5"><div class="empty-state"><i class="bi bi-inbox"></i>Nothing has been entered for this month yet.</div></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if (! $canEdit)
                        <div class="card-footer small text-muted">
                            @if (! in_array($period->status, ['draft', 'processed'])) Payroll for this month is {{ $period->status }}, so items are locked.
                            @else You can view these items but not change them. @endif
                        </div>
                    @endif
                </div>
            </div>

            @if ($canEdit)
                <div class="col-xl-4">
                    <div class="card">
                        <div class="card-header">Add an item</div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('salary.adjustments.store') }}">
                                @csrf
                                <input type="hidden" name="payroll_period_id" value="{{ $period->id }}">

                                <div class="mb-3">
                                    <label for="employee_id" class="form-label">Employee</label>
                                    <select id="employee_id" name="employee_id" class="form-select" required>
                                        <option value="">Select...</option>
                                        @foreach ($employees as $employee)
                                            <option value="{{ $employee->id }}" @selected((string) old('employee_id') === (string) $employee->id)>{{ $employee->employee_code }} · {{ $employee->full_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="salary_component_id" class="form-label">Item</label>
                                    <select id="salary_component_id" name="salary_component_id" class="form-select" required>
                                        <option value="">Select...</option>
                                        @foreach ($components as $component)
                                            <option value="{{ $component->id }}" @selected((string) old('salary_component_id') === (string) $component->id)>{{ $component->name }} ({{ $component->type === 'earning' ? 'added' : 'deducted' }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="amount" class="form-label">Amount (৳)</label>
                                    <input type="number" id="amount" name="amount" step="0.01" min="0.01" value="{{ old('amount') }}" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label for="note" class="form-label">Note</label>
                                    <input type="text" id="note" name="note" maxlength="255" value="{{ old('note') }}" class="form-control" placeholder="e.g. 12 overtime hours">
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Add item</button>
                            </form>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endif
@endsection
