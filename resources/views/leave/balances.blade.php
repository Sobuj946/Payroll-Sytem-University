@extends('layouts.app')

@section('title', 'Leave Balances')

@section('content')
    @include('leave._nav')

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('leave.balances') }}" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label for="year" class="form-label small text-muted mb-1">Year</label>
                    <input type="number" id="year" name="year" value="{{ $year }}" min="2000" max="2100" class="form-control">
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
                    <a href="{{ route('leave.balances') }}" class="btn btn-light">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Days remaining in {{ $year }} <span class="text-muted fw-normal">(used / yearly allowance below each figure)</span></div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Employee</th>
                        @foreach ($types as $type)
                            <th class="text-center">{{ $type->name }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($employees as $employee)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $employee->full_name }}</div>
                                <div class="small text-muted">{{ $employee->employee_code }} &middot; {{ $employee->department?->name }}</div>
                            </td>
                            @foreach ($types as $type)
                                @php
                                    // No row yet means nothing has been used: the full allowance is still there.
                                    $row = $rows[$employee->id . '-' . $type->id] ?? null;
                                    $allocated = $row ? (float) $row->allocated : (float) $type->days_per_year;
                                    $used = $row ? (float) $row->used : 0.0;
                                    $left = $allocated - $used;
                                @endphp
                                <td class="text-center">
                                    <span class="fw-semibold {{ $left <= 0 ? 'text-danger' : '' }}">{{ $left + 0 }}</span>
                                    <div class="small text-muted">{{ $used + 0 }} / {{ $allocated + 0 }}</div>
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ $types->count() + 1 }}"><div class="empty-state"><i class="bi bi-people"></i>No employees found.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
