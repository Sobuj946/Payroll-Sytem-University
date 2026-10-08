@extends('layouts.app')

@section('title', 'Salary Payments')

@section('content')
    @php
        $taka = fn ($amount) => '৳' . number_format((float) $amount, 2);
        $methods = \App\Services\PaymentService::METHODS;
        $canManage = auth()->user()->hasPermission('payments.manage');
    @endphp

    @if ($errors->any())
        <div class="alert alert-danger py-2">@foreach ($errors->all() as $error) <div>{{ $error }}</div> @endforeach</div>
    @endif

    @if (! $period)
        <div class="card"><div class="card-body empty-state">
            <i class="bi bi-bank"></i>
            No payroll has been approved yet. Payments can be recorded once a payroll is approved.
            @can('payroll.view') <div class="mt-3"><a href="{{ route('payroll.index') }}" class="btn btn-primary">Go to payroll</a></div> @endcan
        </div></div>
    @else
        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" action="{{ route('payments.index') }}" class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label for="period" class="form-label small text-muted mb-1">Payroll month</label>
                        <select id="period" name="period" class="form-select" onchange="this.form.submit()">
                            @foreach ($periods as $item)
                                <option value="{{ $item->id }}" @selected($item->id === $period->id)>{{ $item->label }} ({{ ucfirst($item->status) }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="status" class="form-label small text-muted mb-1">Payment status</label>
                        <select id="status" name="status" class="form-select" onchange="this.form.submit()">
                            <option value="">All</option>
                            @foreach (['pending', 'paid', 'failed', 'cancelled'] as $status)
                                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-auto"><noscript><button type="submit" class="btn btn-primary">Show</button></noscript></div>
                    <div class="col-md text-md-end">
                        @can('payroll.view') <a href="{{ route('payroll.show', $period) }}" class="btn btn-light">Payroll for {{ $period->label }}</a> @endcan
                    </div>
                </form>
            </div>
        </div>

        {{-- Summary --}}
        @php $percent = $netTotal > 0 ? min(100, round($paidTotal / $netTotal * 100)) : 0; @endphp
        <div class="row g-3 mb-3">
            @foreach ([['Total net salary', $taka($netTotal)], ['Paid so far', $taka($paidTotal)], ['Still to pay', $taka(max(0, $netTotal - $paidTotal))], ['Pending / failed', ($counts['pending'] ?? 0) . ' / ' . ($counts['failed'] ?? 0)]] as [$label, $value])
                <div class="col-6 col-xl-3">
                    <div class="card"><div class="card-body py-3">
                        <div class="small text-muted">{{ $label }}</div>
                        <div class="fs-5 fw-bold">{{ $value }}</div>
                    </div></div>
                </div>
            @endforeach
        </div>
        <div class="progress mb-3" style="height: 8px;" title="{{ $percent }}% paid"><div class="progress-bar bg-success" style="width: {{ $percent }}%"></div></div>

        @if ($period->status === 'paid')
            <div class="alert alert-success py-2"><i class="bi bi-check-circle me-1"></i>All salaries for {{ $period->label }} have been paid.</div>
        @endif

        @if ($period->status === 'approved' && $canManage && $missing > 0)
            <div class="alert alert-info d-flex flex-wrap align-items-center justify-content-between gap-2 py-2">
                <span>{{ $missing }} of {{ $payrollCount }} employees have no payment line yet.</span>
                <form method="POST" action="{{ route('payments.generate', $period) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-sm">Prepare payment list</button>
                </form>
            </div>
        @endif

        {{-- Bulk: tick rows below, then submit this form --}}
        @if ($period->status === 'approved' && $canManage && $payments->isNotEmpty())
            <form method="POST" action="{{ route('payments.bulk') }}" id="bulkForm" class="card card-body mb-3 py-3"
                  data-confirm="Mark the ticked payments as paid?">
                @csrf
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label for="bulk_date" class="form-label small mb-1">Payment date</label>
                        <input type="date" id="bulk_date" name="payment_date" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-4">
                        <label for="bulk_ref" class="form-label small mb-1">Batch reference (bank / mobile payments)</label>
                        <input type="text" id="bulk_ref" name="reference" maxlength="80" class="form-control form-control-sm" placeholder="e.g. bank batch number">
                    </div>
                    <div class="col-md-auto"><button type="submit" class="btn btn-success btn-sm"><i class="bi bi-check2-all me-1"></i>Mark ticked as paid</button></div>
                    <div class="col-md-auto"><span class="small text-muted">Uses each line's saved method and account.</span></div>
                </div>
            </form>
        @endif

        <div class="card">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            @if ($period->status === 'approved' && $canManage)
                                <th style="width: 36px;"><input type="checkbox" class="form-check-input" id="tickAll" aria-label="Tick all unpaid"></th>
                            @endif
                            <th>Employee</th>
                            <th>Method</th>
                            <th>Account</th>
                            <th>Reference</th>
                            <th>Date</th>
                            <th class="text-end">Amount</th>
                            <th>Status</th>
                            @if ($canManage)<th class="text-end">Actions</th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($payments as $payment)
                            @php $open = in_array($payment->status, ['pending', 'failed']) && $period->status === 'approved'; @endphp
                            <tr>
                                @if ($period->status === 'approved' && $canManage)
                                    <td>@if ($open)<input type="checkbox" class="form-check-input tick-row" name="ids[]" value="{{ $payment->id }}" form="bulkForm">@endif</td>
                                @endif
                                <td>{{ $payment->employee->full_name }}<div class="small text-muted">{{ $payment->employee->employee_code }} &middot; {{ $payment->employee->department?->name }}</div></td>
                                <td>{{ $methods[$payment->method] ?? $payment->method }}</td>
                                <td class="small">{{ $payment->bank_name }}<div class="text-muted">{{ $payment->account_number }}</div></td>
                                <td class="small">{{ $payment->transaction_reference ?: '—' }}</td>
                                <td class="text-nowrap">{{ $payment->payment_date?->format('d M Y') ?? '—' }}</td>
                                <td class="text-end">{{ $taka($payment->amount) }}</td>
                                <td>
                                    <x-status-badge :status="$payment->status" />
                                    @if ($payment->remarks && in_array($payment->status, ['failed', 'cancelled']))
                                        <div class="small text-muted mt-1" title="{{ $payment->remarks }}">{{ \Illuminate\Support\Str::limit($payment->remarks, 30) }}</div>
                                    @endif
                                </td>
                                @if ($canManage)
                                    <td class="text-end text-nowrap">
                                        @if ($open)
                                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="collapse" data-bs-target="#pay-{{ $payment->id }}">{{ $payment->status === 'failed' ? 'Record again' : 'Record payment' }}</button>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                            @if ($open && $canManage)
                                <tr class="collapse" id="pay-{{ $payment->id }}">
                                    <td colspan="9" class="bg-body-tertiary">
                                        <form method="POST" action="{{ route('payments.pay', $payment) }}" class="row g-2 align-items-end"
                                              data-confirm="Record the payment of {{ $taka($payment->amount) }} to {{ $payment->employee->full_name }}?">
                                            @csrf
                                            <div class="col-md-3">
                                                <label class="form-label small mb-1">Method</label>
                                                <select name="method" class="form-select form-select-sm" required>
                                                    @foreach ($methods as $key => $label)
                                                        <option value="{{ $key }}" @selected($payment->method === $key)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label small mb-1">Bank or service name</label>
                                                <input type="text" name="bank_name" value="{{ $payment->bank_name }}" maxlength="100" class="form-control form-control-sm">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label small mb-1">Account / mobile number</label>
                                                <input type="text" name="account_number" value="{{ $payment->account_number }}" maxlength="40" class="form-control form-control-sm">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label small mb-1">Transaction reference</label>
                                                <input type="text" name="transaction_reference" maxlength="80" class="form-control form-control-sm">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label small mb-1">Payment date</label>
                                                <input type="date" name="payment_date" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" class="form-control form-control-sm" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small mb-1">Remarks</label>
                                                <input type="text" name="remarks" maxlength="255" class="form-control form-control-sm">
                                            </div>
                                            <div class="col-md-3"><button type="submit" class="btn btn-sm btn-success w-100">Save as paid</button></div>
                                        </form>

                                        <div class="d-flex flex-wrap gap-2 mt-3 pt-3 border-top">
                                            @if ($payment->status === 'pending')
                                                <form method="POST" action="{{ route('payments.fail', $payment) }}" class="d-flex gap-2" data-confirm="Mark this payment as failed?">
                                                    @csrf
                                                    <input type="text" name="remarks" maxlength="255" class="form-control form-control-sm" placeholder="What went wrong?" required>
                                                    <button type="submit" class="btn btn-sm btn-outline-warning text-nowrap">Mark failed</button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('payments.cancel', $payment) }}" class="d-flex gap-2" data-confirm="Cancel this payment line?">
                                                @csrf
                                                <input type="text" name="remarks" maxlength="255" class="form-control form-control-sm" placeholder="Why is it cancelled?" required>
                                                <button type="submit" class="btn btn-sm btn-outline-danger text-nowrap">Cancel payment</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr><td colspan="9"><div class="empty-state"><i class="bi bi-bank"></i>No payments here yet.@if ($period->status === 'approved') Use "Prepare payment list" above. @endif</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($payments->hasPages())
                <div class="card-footer">{{ $payments->links() }}</div>
            @endif
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        var tickAll = document.getElementById('tickAll');
        if (tickAll) {
            tickAll.addEventListener('change', function () {
                document.querySelectorAll('.tick-row').forEach(function (box) { box.checked = tickAll.checked; });
            });
        }
    </script>
@endpush
