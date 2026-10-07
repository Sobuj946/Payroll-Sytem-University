@extends('layouts.app')

@section('title', $type === 'late' ? 'Late Report' : 'Absent Report')

@section('content')
    @include('attendance._nav')

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('attendance.report') }}" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label for="type" class="form-label small text-muted mb-1">Report</label>
                    <select id="type" name="type" class="form-select">
                        <option value="late" @selected($type === 'late')>Late</option>
                        <option value="absent" @selected($type === 'absent')>Absent</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="from" class="form-label small text-muted mb-1">From</label>
                    <input type="date" id="from" name="from" value="{{ $from->toDateString() }}" class="form-control">
                </div>
                <div class="col-md-2">
                    <label for="to" class="form-label small text-muted mb-1">To</label>
                    <input type="date" id="to" name="to" value="{{ $to->toDateString() }}" class="form-control">
                </div>
                <div class="col-md-3">
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
                    <a href="{{ route('attendance.report') }}" class="btn btn-light">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">Most {{ $type }} days</div>
                <ul class="list-group list-group-flush">
                    @forelse ($summary as $line)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>{{ $line->employee?->full_name }}<span class="small text-muted d-block">{{ $line->employee?->employee_code }}</span></span>
                            <span class="badge text-bg-{{ $type === 'late' ? 'warning' : 'danger' }}">{{ $line->total }}</span>
                        </li>
                    @empty
                        <li class="list-group-item text-muted">Nobody was {{ $type }} in this period.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">{{ ucfirst($type) }} records <span class="text-muted fw-normal">&middot; {{ $rows->total() }} in total</span></div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr><th>Date</th><th>Employee</th><th>Department</th><th>Check in</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $row)
                                <tr>
                                    <td class="text-nowrap">{{ $row->date->format('D, d M Y') }}</td>
                                    <td>{{ $row->employee?->full_name }}<span class="small text-muted d-block">{{ $row->employee?->employee_code }}</span></td>
                                    <td>{{ $row->employee?->department?->name }}</td>
                                    <td>{{ $row->check_in ? substr($row->check_in, 0, 5) : '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4"><div class="empty-state"><i class="bi bi-emoji-smile"></i>No {{ $type }} records between {{ $from->format('d M') }} and {{ $to->format('d M Y') }}.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($rows->hasPages())
                    <div class="card-footer">{{ $rows->links() }}</div>
                @endif
            </div>
        </div>
    </div>
@endsection
