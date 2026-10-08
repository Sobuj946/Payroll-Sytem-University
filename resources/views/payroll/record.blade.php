@extends('layouts.app')

@section('title', $payroll->employee->full_name . ' · ' . $payroll->period->label)

@section('content')
    @php
        $taka = fn ($amount) => '৳' . number_format((float) $amount, 2);
        $employee = $payroll->employee;
    @endphp

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h2 class="h5 mb-1">{{ $employee->full_name }} <span class="text-muted fw-normal">({{ $employee->employee_code }})</span></h2>
            <div class="text-muted small">
                {{ $employee->designation?->name }} &middot; {{ $employee->department?->name }}
                &middot; {{ $payroll->period->label }} <x-status-badge :status="$payroll->period->status" />
            </div>
        </div>
        <div class="d-flex gap-2">
            @if (in_array($payroll->period->status, ['approved', 'paid', 'closed']) && auth()->user()->hasPermission('payslips.view'))
                <a href="{{ route('payslips.show', $payroll) }}" class="btn btn-primary"><i class="bi bi-receipt me-1"></i>Salary slip</a>
            @endif
            <a href="{{ route('payroll.show', $payroll->period) }}" class="btn btn-light">Back to {{ $payroll->period->label }}</a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        @foreach ([['Gross salary', $payroll->gross_salary], ['Total deductions', $payroll->total_deductions], ['Net salary', $payroll->net_salary]] as [$label, $value])
            <div class="col-md-4">
                <div class="card {{ $label === 'Net salary' ? 'border-primary' : '' }}"><div class="card-body py-3">
                    <div class="small text-muted">{{ $label }}</div>
                    <div class="fs-4 fw-bold">{{ $taka($value) }}</div>
                </div></div>
            </div>
        @endforeach
    </div>

    @php $payment = $payroll->payments->where('status', '!=', 'cancelled')->sortByDesc('id')->first(); @endphp
    @if ($payment)
        <div class="alert alert-light border d-flex flex-wrap align-items-center gap-2 py-2">
            <i class="bi bi-bank"></i> Payment: <x-status-badge :status="$payment->status" />
            @if ($payment->status === 'paid')
                <span class="text-muted">{{ $payment->payment_date?->format('d M Y') }} &middot; {{ \App\Services\PaymentService::METHODS[$payment->method] ?? $payment->method }}@if ($payment->transaction_reference) &middot; Ref. {{ $payment->transaction_reference }}@endif</span>
            @endif
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">Earnings</div>
                <table class="table mb-0">
                    <tbody>
                        @foreach ($earnings as $item)
                            <tr><td>{{ $item->name }}</td><td class="text-end">{{ $taka($item->amount) }}</td></tr>
                        @endforeach
                        <tr class="fw-bold"><td>Gross salary</td><td class="text-end">{{ $taka($payroll->gross_salary) }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">Deductions</div>
                <table class="table mb-0">
                    <tbody>
                        @forelse ($deductions as $item)
                            <tr><td>{{ $item->name }}</td><td class="text-end">{{ $taka($item->amount) }}</td></tr>
                        @empty
                            <tr><td colspan="2" class="text-muted">No deductions this month.</td></tr>
                        @endforelse
                        <tr class="fw-bold"><td>Total deductions</td><td class="text-end">{{ $taka($payroll->total_deductions) }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header">Attendance used for this calculation</div>
        <div class="card-body">
            <div class="row text-center g-3">
                @foreach ([['Working days', $payroll->working_days], ['Present days', $payroll->present_days + 0], ['Absent days', $payroll->absent_days + 0], ['Unpaid leave days', $payroll->unpaid_leave_days + 0], ['Late arrivals', $payroll->late_count]] as [$label, $value])
                    <div class="col-6 col-md">
                        <div class="fs-4 fw-bold">{{ $value }}</div>
                        <div class="small text-muted">{{ $label }}</div>
                    </div>
                @endforeach
            </div>
            <div class="small text-muted mt-3">
                A half-day counts as half an absent day. Absence and unpaid leave are charged at the daily rate: (basic + fixed allowances) divided by the working days of the month.
                Income tax is worked out from the tax slabs saved in the system.
            </div>
        </div>
    </div>
@endsection
