@extends('layouts.app')

@section('title', 'Salary Slips')

@section('content')
    @php $taka = fn ($amount) => '৳' . number_format((float) $amount, 2); @endphp

    @if (! $period)
        <div class="card"><div class="card-body empty-state">
            <i class="bi bi-receipt"></i>
            Salary slips appear here once a payroll is approved.
            @can('payroll.view') <div class="mt-3"><a href="{{ route('payroll.index') }}" class="btn btn-primary">Go to payroll</a></div> @endcan
        </div></div>
    @else
        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" action="{{ route('payslips.index') }}" class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label for="period" class="form-label small text-muted mb-1">Payroll month</label>
                        <select id="period" name="period" class="form-select" onchange="this.form.submit()">
                            @foreach ($periods as $item)
                                <option value="{{ $item->id }}" @selected($item->id === $period->id)>{{ $item->label }} ({{ ucfirst($item->status) }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="q" class="form-label small text-muted mb-1">Employee</label>
                        <input type="search" id="q" name="q" value="{{ request('q') }}" class="form-control" placeholder="Name or ID">
                    </div>
                    <div class="col-md-auto">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>Search</button>
                    </div>
                    @can('payslips.generate')
                        <div class="col-md text-md-end">
                            <a href="{{ route('payslips.period.pdf', $period) }}" class="btn btn-outline-primary"><i class="bi bi-files me-1"></i>Download all slips (PDF)</a>
                        </div>
                    @endcan
                </form>
            </div>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr><th>Employee</th><th class="text-end">Gross</th><th class="text-end">Net salary</th><th>Payment</th><th class="text-end">Slip</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($payrolls as $payroll)
                            @php $payment = $payroll->payments->where('status', '!=', 'cancelled')->sortByDesc('id')->first(); @endphp
                            <tr>
                                <td>{{ $payroll->employee->full_name }}<div class="small text-muted">{{ $payroll->employee->employee_code }} &middot; {{ $payroll->employee->department?->name }}</div></td>
                                <td class="text-end">{{ $taka($payroll->gross_salary) }}</td>
                                <td class="text-end fw-semibold">{{ $taka($payroll->net_salary) }}</td>
                                <td>@if ($payment) <x-status-badge :status="$payment->status" /> @else <span class="text-muted small">Not paid yet</span> @endif</td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('payslips.show', $payroll) }}" class="btn btn-sm btn-outline-secondary">View</a>
                                    @can('payslips.generate')
                                        <a href="{{ route('payslips.pdf', $payroll) }}" class="btn btn-sm btn-outline-primary">PDF</a>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><div class="empty-state"><i class="bi bi-search"></i>No employee matches your search.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($payrolls->hasPages())
                <div class="card-footer">{{ $payrolls->links() }}</div>
            @endif
        </div>
    @endif
@endsection
