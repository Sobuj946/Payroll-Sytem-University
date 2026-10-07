@extends('layouts.app')

@section('title', 'Monthly Attendance')

@section('content')
    @include('attendance._nav')

    @php
        $codes = [
            'present' => ['P', 'success'], 'late' => ['L', 'warning'], 'absent' => ['A', 'danger'],
            'half_day' => ['H', 'info'], 'leave' => ['V', 'primary'],
        ];
    @endphp

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('attendance.monthly') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label for="month" class="form-label small text-muted mb-1">Month</label>
                    <input type="month" id="month" name="month" value="{{ $month->format('Y-m') }}" class="form-control">
                </div>
                <div class="col-md-4">
                    <label for="department_id" class="form-label small text-muted mb-1">Department</label>
                    <select id="department_id" name="department_id" class="form-select">
                        <option value="">All departments</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected((string) request('department_id') === (string) $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-auto">
                    <button type="submit" class="btn btn-primary">Show</button>
                    <a href="{{ route('attendance.monthly') }}" class="btn btn-light">This month</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">{{ $month->format('F Y') }}</div>
        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0 att-grid">
                <thead>
                    <tr>
                        <th class="sticky-col">Employee</th>
                        @foreach ($days as $day)
                            <th class="{{ isset($offDays[$day->format('Y-m-d')]) ? 'off-day' : '' }}" title="{{ $day->format('l, d M') }}">
                                {{ $day->format('j') }}<div class="fw-normal text-muted">{{ $day->format('D')[0] }}</div>
                            </th>
                        @endforeach
                        <th title="Present">P</th><th title="Late">L</th><th title="Half day">H</th><th title="Absent">A</th><th title="Leave">V</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($employees as $employee)
                        @php $rows = $records[$employee->id] ?? collect(); $count = $rows->groupBy('status')->map->count(); @endphp
                        <tr>
                            <td class="sticky-col">
                                <a href="{{ route('attendance.employee', ['employee' => $employee, 'month' => $month->format('Y-m')]) }}" class="text-decoration-none fw-semibold">{{ $employee->full_name }}</a>
                                <div class="small text-muted">{{ $employee->employee_code }} &middot; {{ $employee->department?->name }}</div>
                            </td>
                            @foreach ($days as $day)
                                @php $key = $day->format('Y-m-d'); $row = $rows[$key] ?? null; @endphp
                                <td class="{{ isset($offDays[$key]) ? 'off-day' : '' }}">
                                    @if ($row)
                                        <span class="badge text-bg-{{ $codes[$row->status][1] }}" title="{{ ucwords(str_replace('_', ' ', $row->status)) }}">{{ $codes[$row->status][0] }}</span>
                                    @elseif (isset($offDays[$key]))
                                        <span class="text-muted" title="{{ $offDays[$key] }}">&middot;</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="fw-semibold">{{ $count['present'] ?? 0 }}</td>
                            <td class="fw-semibold">{{ $count['late'] ?? 0 }}</td>
                            <td class="fw-semibold">{{ $count['half_day'] ?? 0 }}</td>
                            <td class="fw-semibold">{{ $count['absent'] ?? 0 }}</td>
                            <td class="fw-semibold">{{ $count['leave'] ?? 0 }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $days->count() + 6 }}"><div class="empty-state"><i class="bi bi-calendar3"></i>No employees found.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer small text-muted">
            P present &middot; L late &middot; H half day &middot; A absent &middot; V leave &middot; shaded columns are weekly offs or holidays.
        </div>
    </div>
@endsection
