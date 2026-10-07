@extends('layouts.app')

@section('title', 'Salary Structure')

@section('content')
    @include('salary._nav')

    @php $taka = fn ($amount) => '৳' . number_format((float) $amount, 2); @endphp

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('salary.index') }}" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label for="q" class="form-label small text-muted mb-1">Search</label>
                    <input type="search" id="q" name="q" value="{{ request('q') }}" class="form-control" placeholder="Name or employee ID">
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
                    <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>Search</button>
                    <a href="{{ route('salary.index') }}" class="btn btn-light">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th class="text-end">Basic</th>
                        <th class="text-end">Allowances</th>
                        <th class="text-end">Fixed deductions</th>
                        <th class="text-end">Gross</th>
                        <th class="text-end">Before tax &amp; attendance</th>
                        <th class="text-end"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($employees as $employee)
                        @php $t = $totals[$employee->id]; @endphp
                        <tr>
                            <td>
                                <a href="{{ route('salary.employee', $employee) }}" class="fw-semibold text-decoration-none">{{ $employee->full_name }}</a>
                                <div class="small text-muted">{{ $employee->employee_code }} &middot; {{ $employee->department?->name }}</div>
                            </td>
                            <td class="text-end">{{ $taka($t['basic']) }}</td>
                            <td class="text-end">{{ $taka($t['allowances']) }}</td>
                            <td class="text-end">{{ $taka($t['deductions']) }}</td>
                            <td class="text-end">{{ $taka($t['gross']) }}</td>
                            <td class="text-end fw-semibold">{{ $taka($t['net']) }}</td>
                            <td class="text-end"><a href="{{ route('salary.employee', $employee) }}" class="btn btn-sm btn-outline-secondary">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="empty-state"><i class="bi bi-cash-stack"></i>No employees found.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($employees->hasPages())
            <div class="card-footer">{{ $employees->links() }}</div>
        @endif
        <div class="card-footer small text-muted">
            These are the fixed monthly figures. Overtime, bonus, absence and unpaid leave deductions and income tax are added when payroll is processed.
        </div>
    </div>
@endsection
