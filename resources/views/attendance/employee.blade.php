@extends('layouts.app')

@section('title', $self ? 'My Attendance' : $employee->full_name . ' · Attendance')

@section('content')
    @php $action = $self ? route('my.attendance') : route('attendance.employee', $employee); @endphp

    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-end gap-3">
            <div>
                <div class="fw-semibold">{{ $employee->full_name }} <span class="text-muted fw-normal">({{ $employee->employee_code }})</span></div>
                <div class="small text-muted">{{ $employee->designation?->name }} &middot; {{ $employee->department?->name }}</div>
            </div>
            <form method="GET" action="{{ $action }}" class="d-flex gap-2 align-items-end">
                <div>
                    <label for="month" class="form-label small text-muted mb-1">Month</label>
                    <input type="month" id="month" name="month" value="{{ $month->format('Y-m') }}" class="form-control">
                </div>
                <button type="submit" class="btn btn-primary">Show</button>
                @unless ($self)
                    <a href="{{ route('employees.show', $employee) }}" class="btn btn-light">Profile</a>
                @endunless
            </form>
        </div>
    </div>

    <div class="row g-3 mb-3">
        @foreach ([['present', 'Present'], ['late', 'Late'], ['half_day', 'Half day'], ['absent', 'Absent'], ['leave', 'Leave']] as [$key, $label])
            <div class="col-6 col-md">
                <div class="card"><div class="card-body py-2 text-center">
                    <div class="fs-4 fw-bold">{{ $totals[$key] ?? 0 }}</div>
                    <div class="small text-muted">{{ $label }}</div>
                </div></div>
            </div>
        @endforeach
        <div class="col-6 col-md">
            <div class="card"><div class="card-body py-2 text-center">
                <div class="fs-4 fw-bold">{{ number_format($hours, 1) }}</div>
                <div class="small text-muted">Hours worked</div>
            </div></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">{{ $month->format('F Y') }}</div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Date</th><th>Check in</th><th>Check out</th><th>Hours</th><th>Status</th><th>Remarks</th></tr></thead>
                <tbody>
                    @foreach ($days as $day)
                        @php $key = $day->format('Y-m-d'); $row = $records[$key] ?? null; @endphp
                        <tr class="{{ isset($offDays[$key]) && ! $row ? 'table-secondary' : '' }}">
                            <td class="text-nowrap">{{ $day->format('D, d M') }}</td>
                            <td>{{ $row?->check_in ? substr($row->check_in, 0, 5) : '—' }}</td>
                            <td>{{ $row?->check_out ? substr($row->check_out, 0, 5) : '—' }}</td>
                            <td>{{ $row ? $row->working_hours + 0 : '—' }}</td>
                            <td>
                                @if ($row) <x-status-badge :status="$row->status" />
                                @elseif (isset($offDays[$key])) <span class="text-muted small">{{ $offDays[$key] }}</span>
                                @elseif ($day->isFuture()) <span class="text-muted">—</span>
                                @else <span class="text-muted small">Not recorded</span> @endif
                            </td>
                            <td class="text-muted">{{ $row?->remarks ?: '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
