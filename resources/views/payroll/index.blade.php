@extends('layouts.app')

@section('title', 'Payroll')

@section('content')
    @php $taka = fn ($amount) => '৳' . number_format((float) $amount, 2); @endphp

    @can('payroll.process')
        <div class="card mb-3">
            <div class="card-body">
                <form method="POST" action="{{ route('payroll.periods.store') }}" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-3">
                        <label for="month" class="form-label small text-muted mb-1">Start a new payroll month</label>
                        <input type="month" id="month" name="month" value="{{ old('month', now()->format('Y-m')) }}"
                               class="form-control @error('month') is-invalid @enderror" required>
                        @error('month') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-auto">
                        <button type="submit" class="btn btn-success"><i class="bi bi-plus-lg me-1"></i>Create period</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Period</th>
                        <th>Status</th>
                        <th class="text-center">Employees</th>
                        <th class="text-end">Gross</th>
                        <th class="text-end">Deductions</th>
                        <th class="text-end">Net salary</th>
                        <th class="text-end"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($periods as $period)
                        <tr>
                            <td class="fw-semibold"><a href="{{ route('payroll.show', $period) }}" class="text-decoration-none">{{ $period->label }}</a></td>
                            <td><x-status-badge :status="$period->status" /></td>
                            <td class="text-center">{{ $period->payrolls_count ?: '—' }}</td>
                            <td class="text-end">{{ $period->payrolls_count ? $taka($period->payrolls_sum_gross_salary) : '—' }}</td>
                            <td class="text-end">{{ $period->payrolls_count ? $taka($period->payrolls_sum_total_deductions) : '—' }}</td>
                            <td class="text-end fw-semibold">{{ $period->payrolls_count ? $taka($period->payrolls_sum_net_salary) : '—' }}</td>
                            <td class="text-end"><a href="{{ route('payroll.show', $period) }}" class="btn btn-sm btn-outline-secondary">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="empty-state"><i class="bi bi-calculator"></i>No payroll has been started yet.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($periods->hasPages())
            <div class="card-footer">{{ $periods->links() }}</div>
        @endif
    </div>
@endsection
