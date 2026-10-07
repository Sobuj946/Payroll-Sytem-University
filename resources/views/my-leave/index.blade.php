@extends('layouts.app')

@section('title', 'My Leave')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h6 text-muted text-uppercase mb-0">My balance for {{ $year }}</h2>
        <a href="{{ route('my.leave.create') }}" class="btn btn-success"><i class="bi bi-plus-lg me-1"></i>Request leave</a>
    </div>

    <div class="row g-3 mb-4">
        @forelse ($balances as $item)
            @php $percent = $item['allocated'] > 0 ? min(100, round(($item['used'] / $item['allocated']) * 100)) : 0; @endphp
            <div class="col-sm-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted small">{{ $item['type']->name }}</div>
                        <div class="fs-3 fw-bold">{{ $item['available'] + 0 }} <span class="fs-6 fw-normal text-muted">days available</span></div>
                        <div class="progress my-2" style="height: 6px;"><div class="progress-bar" style="width: {{ $percent }}%"></div></div>
                        <div class="small text-muted">
                            {{ $item['used'] + 0 }} used of {{ $item['allocated'] + 0 }}
                            @if ($item['pending'] > 0) &middot; {{ $item['pending'] + 0 }} waiting for approval @endif
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12"><div class="card"><div class="card-body text-muted">No leave types with a yearly allowance are set up.</div></div></div>
        @endforelse
    </div>

    <div class="card">
        <div class="card-header">My requests</div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Leave type</th><th>Dates</th><th class="text-center">Days</th><th>Reason</th><th>Status</th><th class="text-end"></th></tr></thead>
                <tbody>
                    @forelse ($requests as $leave)
                        <tr>
                            <td>{{ $leave->leaveType->name }}</td>
                            <td class="text-nowrap">
                                {{ $leave->start_date->format('d M Y') }}
                                @unless ($leave->start_date->isSameDay($leave->end_date)) &ndash; {{ $leave->end_date->format('d M Y') }} @endunless
                            </td>
                            <td class="text-center">{{ $leave->days + 0 }}</td>
                            <td class="text-muted" title="{{ $leave->reason }}">{{ \Illuminate\Support\Str::limit($leave->reason, 45) }}</td>
                            <td>
                                <x-status-badge :status="$leave->status" />
                                @if ($leave->remarks && $leave->status === 'rejected')
                                    <div class="small text-muted mt-1">{{ $leave->remarks }}</div>
                                @endif
                            </td>
                            <td class="text-end">
                                @if ($leave->status === 'pending' || ($leave->status === 'approved' && $leave->start_date->gt(today())))
                                    <form method="POST" action="{{ route('my.leave.cancel', $leave) }}" class="d-inline"
                                          data-confirm="Cancel this leave request?">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">Cancel</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty-state"><i class="bi bi-calendar2-minus"></i>You have not requested any leave yet.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($requests->hasPages())
            <div class="card-footer">{{ $requests->links() }}</div>
        @endif
    </div>
@endsection
