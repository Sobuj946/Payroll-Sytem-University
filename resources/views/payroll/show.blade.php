@extends('layouts.app')

@section('title', 'Payroll · ' . $period->label)

@section('content')
    @php
        $taka = fn ($amount) => '৳' . number_format((float) $amount, 2);
        $user = auth()->user();
        $steps = \App\Models\PayrollPeriod::STATUSES;
        $stepIndex = array_search($period->status, $steps, true);
        $canReopen = in_array($period->status, ['processed', 'reviewed', 'approved'], true) && $user->hasPermission('payroll.approve');
    @endphp

    @if ($errors->any())
        <div class="alert alert-danger py-2">@foreach ($errors->all() as $error) <div>{{ $error }}</div> @endforeach</div>
    @endif

    {{-- Heading and workflow steps --}}
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h2 class="h5 mb-1">{{ $period->label }}</h2>
            <div class="text-muted small">
                {{ $period->start_date->format('d M') }} &ndash; {{ $period->end_date->format('d M Y') }}
                &middot; created by {{ $period->creator?->name }}
                @if ($period->approved_at) &middot; approved by {{ $period->approver?->name }} on {{ $period->approved_at->format('d M Y') }} @endif
            </div>
        </div>
        <a href="{{ route('payroll.index') }}" class="btn btn-light">All periods</a>
    </div>

    <div class="card mb-3">
        <div class="card-body py-3">
            <div class="d-flex flex-wrap gap-2">
                @foreach ($steps as $i => $step)
                    @if ($step === 'processing') @continue @endif
                    @php $done = $i < $stepIndex; $current = $i === $stepIndex; @endphp
                    <span class="badge rounded-pill {{ $current ? 'text-bg-primary' : ($done ? 'text-bg-success' : 'text-bg-light border') }} px-3 py-2">
                        @if ($done)<i class="bi bi-check2 me-1"></i>@endif{{ ucfirst($step) }}
                    </span>
                @endforeach
            </div>
            @if ($period->notes && $period->status === 'draft')
                <div class="small text-muted mt-2"><i class="bi bi-arrow-counterclockwise me-1"></i>Reopened. Reason: {{ $period->notes }}</div>
            @endif
        </div>
    </div>

    {{-- Actions for the current step --}}
    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap align-items-center gap-2">
            @if (in_array($period->status, ['draft', 'processed']) && $user->hasPermission('payroll.process'))
                <form method="POST" action="{{ route('payroll.process', $period) }}"
                      data-confirm="{{ $period->status === 'processed' ? 'Process payroll again? The earlier result for ' . $period->label . ' will be replaced.' : 'Process payroll for ' . $period->label . '?' }}">
                    @csrf
                    <button type="submit" class="btn btn-primary"><i class="bi bi-calculator me-1"></i>{{ $period->status === 'processed' ? 'Process again' : 'Process payroll' }}</button>
                </form>
                @can('payroll.view')
                    <a href="{{ route('salary.adjustments.index', ['month' => $period->start_date->format('Y-m')]) }}" class="btn btn-outline-secondary">
                        <i class="bi bi-plus-slash-minus me-1"></i>Overtime, bonus &amp; other items ({{ $adjustmentCount }})
                    </a>
                @endcan
            @endif

            @if ($period->status === 'processed' && $user->hasPermission('payroll.review'))
                <form method="POST" action="{{ route('payroll.review', $period) }}" data-confirm="Mark payroll for {{ $period->label }} as reviewed? Items can no longer be changed.">
                    @csrf
                    <button type="submit" class="btn btn-success"><i class="bi bi-clipboard-check me-1"></i>Mark as reviewed</button>
                </form>
            @endif

            @if ($period->status === 'reviewed' && $user->hasPermission('payroll.approve'))
                <form method="POST" action="{{ route('payroll.approve', $period) }}" data-confirm="Approve payroll for {{ $period->label }}? The figures will be locked.">
                    @csrf
                    <button type="submit" class="btn btn-success"><i class="bi bi-check-circle me-1"></i>Approve payroll</button>
                </form>
            @endif

            @if ($period->status === 'approved')
                <span class="text-muted"><i class="bi bi-lock me-1"></i>Approved and locked.</span>
            @endif
            @if (in_array($period->status, ['approved', 'paid', 'closed']) && $user->hasPermission('payments.view'))
                <a href="{{ route('payments.index', ['period' => $period->id]) }}" class="btn btn-primary"><i class="bi bi-bank me-1"></i>{{ $period->status === 'approved' ? 'Go to payments' : 'View payments' }}</a>
            @endif

            @if ($period->status === 'paid' && $user->hasPermission('payroll.close'))
                <form method="POST" action="{{ route('payroll.close', $period) }}" data-confirm="Close payroll for {{ $period->label }}?">
                    @csrf
                    <button type="submit" class="btn btn-dark"><i class="bi bi-archive me-1"></i>Close period</button>
                </form>
            @endif

            @if ($period->status === 'closed')
                <span class="text-muted"><i class="bi bi-archive me-1"></i>This period is closed.</span>
            @endif

            @if ($canReopen)
                <button type="button" class="btn btn-outline-danger ms-md-auto" data-bs-toggle="collapse" data-bs-target="#reopenBox">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reopen for correction
                </button>
            @endif
        </div>

        @if ($canReopen)
            <div class="collapse" id="reopenBox">
                <div class="card-body border-top bg-body-tertiary">
                    <form method="POST" action="{{ route('payroll.reopen', $period) }}" class="row g-2 align-items-end"
                          data-confirm="Reopen payroll for {{ $period->label }}? It goes back to Draft and the change is recorded in the audit log.">
                        @csrf
                        <div class="col-md-8">
                            <label for="reason" class="form-label small mb-1">Reason for the correction <span class="text-danger">*</span></label>
                            <input type="text" id="reason" name="reason" maxlength="255" class="form-control" placeholder="e.g. wrong overtime entered for two employees" required>
                        </div>
                        <div class="col-md-auto"><button type="submit" class="btn btn-danger">Reopen</button></div>
                    </form>
                </div>
            </div>
        @endif
    </div>

    {{-- Attendance pre-check --}}
    @if ($gaps->isNotEmpty())
        <div class="alert alert-warning">
            <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle me-1"></i>{{ $gaps->count() }} employee(s) have working days with no attendance record</div>
            <div class="small mb-2">Payroll treats an unrecorded day as a normal working day, so nobody is charged for it. Record attendance first if these days should count as absent.</div>
            <div class="small">
                @foreach ($gaps->take(8) as $gap)
                    <span class="badge text-bg-warning me-1">{{ $gap['employee']->full_name }}: {{ $gap['missing'] }} day(s)</span>
                @endforeach
                @if ($gaps->count() > 8) <span class="text-muted">and {{ $gaps->count() - 8 }} more</span> @endif
            </div>
            @can('attendance.view')
                <a href="{{ route('attendance.monthly', ['month' => $period->start_date->format('Y-m')]) }}" class="small d-inline-block mt-2">Open the monthly attendance view</a>
            @endcan
        </div>
    @endif

    {{-- Totals --}}
    @if ($summary['count'] > 0)
        <div class="row g-3 mb-3">
            @foreach ([['Employees', number_format($summary['count']), 'bi-people'], ['Total gross', $taka($summary['gross']), 'bi-cash-stack'], ['Total deductions', $taka($summary['deductions']), 'bi-dash-circle'], ['Total net salary', $taka($summary['net']), 'bi-wallet2']] as [$label, $value, $icon])
                <div class="col-6 col-xl-3">
                    <div class="card"><div class="card-body py-3">
                        <div class="small text-muted"><i class="bi {{ $icon }} me-1"></i>{{ $label }}</div>
                        <div class="fs-5 fw-bold">{{ $value }}</div>
                    </div></div>
                </div>
            @endforeach
        </div>

        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span>Employee payroll</span>
                <form method="GET" action="{{ route('payroll.show', $period) }}" class="d-flex gap-2">
                    <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Name or ID">
                    <button type="submit" class="btn btn-sm btn-primary">Search</button>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th class="text-end">Gross</th>
                            <th class="text-end">Tax</th>
                            <th class="text-end">Total deductions</th>
                            <th class="text-end">Net salary</th>
                            <th class="text-center">Absent</th>
                            <th class="text-end"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($payrolls as $payroll)
                            <tr>
                                <td>
                                    <a href="{{ route('payroll.record', $payroll) }}" class="fw-semibold text-decoration-none">{{ $payroll->employee->full_name }}</a>
                                    <div class="small text-muted">{{ $payroll->employee->employee_code }} &middot; {{ $payroll->employee->department?->name }}</div>
                                </td>
                                <td class="text-end">{{ $taka($payroll->gross_salary) }}</td>
                                <td class="text-end">{{ $taka($payroll->tax) }}</td>
                                <td class="text-end">{{ $taka($payroll->total_deductions) }}</td>
                                <td class="text-end fw-semibold">{{ $taka($payroll->net_salary) }}</td>
                                <td class="text-center">{{ $payroll->absent_days + $payroll->unpaid_leave_days > 0 ? ($payroll->absent_days + $payroll->unpaid_leave_days) + 0 : '—' }}</td>
                                <td class="text-end"><a href="{{ route('payroll.record', $payroll) }}" class="btn btn-sm btn-outline-secondary">Details</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><div class="empty-state"><i class="bi bi-search"></i>No employee matches your search.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($payrolls->hasPages())
                <div class="card-footer">{{ $payrolls->links() }}</div>
            @endif
        </div>
    @else
        <div class="card">
            <div class="card-body empty-state">
                <i class="bi bi-calculator"></i>
                Payroll has not been processed for {{ $period->label }} yet.
            </div>
        </div>
    @endif
@endsection
