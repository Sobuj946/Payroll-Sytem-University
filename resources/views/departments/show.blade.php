@extends('layouts.app')

@section('title', $department->name)

@section('content')
    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <div class="mb-2"><x-status-badge :status="$department->status" /></div>
                <p class="mb-0 text-muted">{{ $department->description ?: 'No description added.' }}</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('departments.index') }}" class="btn btn-light">Back to list</a>
                @can('departments.manage')
                    <a href="{{ route('departments.edit', $department) }}" class="btn btn-primary">Edit</a>
                @endcan
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Employees ({{ $employees->count() }})</div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr><th>Employee</th><th>ID</th><th>Designation</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @forelse ($employees as $employee)
                        <tr>
                            <td>
                                @can('employees.view')
                                    <a href="{{ route('employees.show', $employee) }}" class="text-decoration-none">{{ $employee->full_name }}</a>
                                @else
                                    {{ $employee->full_name }}
                                @endcan
                            </td>
                            <td>{{ $employee->employee_code }}</td>
                            <td>{{ $employee->designation?->name }}</td>
                            <td><x-status-badge :status="$employee->status" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><div class="empty-state"><i class="bi bi-people"></i>No employees in this department yet.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
