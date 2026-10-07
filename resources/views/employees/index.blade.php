@extends('layouts.app')

@section('title', 'Employees')

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('employees.index') }}" class="row g-2 align-items-end">
                <div class="col-lg-3 col-md-6">
                    <label for="q" class="form-label small text-muted mb-1">Search</label>
                    <input type="search" id="q" name="q" value="{{ request('q') }}" class="form-control" placeholder="Name, ID, email, phone or NID">
                </div>
                <div class="col-lg-2 col-md-6">
                    <label for="department_id" class="form-label small text-muted mb-1">Department</label>
                    <select id="department_id" name="department_id" class="form-select">
                        <option value="">All</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected((string) request('department_id') === (string) $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label for="designation_id" class="form-label small text-muted mb-1">Designation</label>
                    <select id="designation_id" name="designation_id" class="form-select">
                        <option value="">All</option>
                        @foreach ($designations as $designation)
                            <option value="{{ $designation->id }}" @selected((string) request('designation_id') === (string) $designation->id)>{{ $designation->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-3">
                    <label for="employment_type" class="form-label small text-muted mb-1">Type</label>
                    <select id="employment_type" name="employment_type" class="form-select">
                        <option value="">All</option>
                        @foreach (\App\Models\Employee::EMPLOYMENT_TYPES as $key => $label)
                            <option value="{{ $key }}" @selected(request('employment_type') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-3">
                    <label for="status" class="form-label small text-muted mb-1">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">All</option>
                        @foreach (\App\Models\Employee::STATUSES as $key => $label)
                            <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-1 col-12">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i></button>
                </div>
            </form>
            <div class="d-flex justify-content-between align-items-center mt-3">
                <a href="{{ route('employees.index') }}" class="small text-decoration-none">Reset filters</a>
                @can('employees.manage')
                    <a href="{{ route('employees.create') }}" class="btn btn-success"><i class="bi bi-plus-lg me-1"></i>Add employee</a>
                @endcan
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Designation</th>
                        <th>Phone</th>
                        <th>Joined</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($employees as $employee)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    @if ($employee->photo_url)
                                        <img src="{{ $employee->photo_url }}" alt="" class="rounded-circle" width="36" height="36" style="object-fit: cover;">
                                    @else
                                        <span class="avatar-circle">{{ $employee->initials }}</span>
                                    @endif
                                    <div class="lh-sm">
                                        <a href="{{ route('employees.show', $employee) }}" class="fw-semibold text-decoration-none">{{ $employee->full_name }}</a>
                                        <div class="small text-muted">{{ $employee->employee_code }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $employee->department?->name }}</td>
                            <td>{{ $employee->designation?->name }}</td>
                            <td class="text-nowrap">{{ $employee->phone }}</td>
                            <td class="text-nowrap">{{ $employee->joining_date->format('d M Y') }}</td>
                            <td><x-status-badge :status="$employee->status" /></td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('employees.show', $employee) }}" class="btn btn-sm btn-outline-secondary">View</a>
                                @can('employees.manage')
                                    <a href="{{ route('employees.edit', $employee) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="bi bi-people"></i>
                                    No employees match your search.
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($employees->hasPages())
            <div class="card-footer bg-white">{{ $employees->links() }}</div>
        @endif
    </div>
@endsection
