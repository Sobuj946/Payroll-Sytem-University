@extends('layouts.app')

@section('title', 'My Salary Slips')

@section('content')
    @php $taka = fn ($amount) => '৳' . number_format((float) $amount, 2); @endphp

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Month</th><th class="text-end">Gross</th><th class="text-end">Deductions</th><th class="text-end">Net salary</th><th>Payment</th><th class="text-end"></th></tr></thead>
                <tbody>
                    @forelse ($payrolls as $payroll)
                        @php $payment = $payroll->payments->where('status', '!=', 'cancelled')->sortByDesc('id')->first(); @endphp
                        <tr>
                            <td class="fw-semibold">{{ $payroll->period->label }}</td>
                            <td class="text-end">{{ $taka($payroll->gross_salary) }}</td>
                            <td class="text-end">{{ $taka($payroll->total_deductions) }}</td>
                            <td class="text-end fw-semibold">{{ $taka($payroll->net_salary) }}</td>
                            <td>@if ($payment) <x-status-badge :status="$payment->status" /> @else <span class="text-muted small">Not paid yet</span> @endif</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('my.payslips.show', $payroll) }}" class="btn btn-sm btn-outline-secondary">View</a>
                                <a href="{{ route('my.payslips.pdf', $payroll) }}" class="btn btn-sm btn-outline-primary">PDF</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty-state"><i class="bi bi-receipt"></i>Your salary slips will appear here once your salary is approved each month.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
