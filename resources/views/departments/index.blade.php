@extends('layouts.app')

@section('title', 'Departments')

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('departments.index') }}" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label for="q" class="form-label small text-muted mb-1">Search</label>
                    <input type="search" id="q" name="q" value="{{ request('q') }}" class="form-control" placeholder="Department name">
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label small text-muted mb-1">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">All</option>
                        <option value="active" @selected(request('status') === 'active')>Active</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                    </select>
                </div>
                <div class="col-md-auto">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>Search</button>
                    <a href="{{ route('departments.index') }}" class="btn btn-light">Reset</a>
                </div>
                @can('departments.manage')
                    <div class="col-md text-md-end">
                        <a href="{{ route('departments.create') }}" class="btn btn-success"><i class="bi bi-plus-lg me-1"></i>Add department</a>
                    </div>
                @endcan
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Description</th>
                        <th class="text-center">Employees</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($departments as $department)
                        <tr>
                            <td class="fw-semibold">
                                <a href="{{ route('departments.show', $department) }}" class="text-decoration-none">{{ $department->name }}</a>
                            </td>
                            <td class="text-muted">{{ \Illuminate\Support\Str::limit($department->description, 70) ?: '—' }}</td>
                            <td class="text-center">{{ $department->employees_count }}</td>
                            <td><x-status-badge :status="$department->status" /></td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('departments.show', $department) }}" class="btn btn-sm btn-outline-secondary">View</a>
                                @can('departments.manage')
                                    <a href="{{ route('departments.edit', $department) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                    <form method="POST" action="{{ route('departments.status', $department) }}" class="d-inline"
                                          data-confirm="{{ $department->status === 'active' ? 'Deactivate' : 'Activate' }} {{ $department->name }}?">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-sm btn-outline-{{ $department->status === 'active' ? 'warning' : 'success' }}">
                                            {{ $department->status === 'active' ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <i class="bi bi-diagram-3"></i>
                                    No departments found.
                                    @if (request()->hasAny(['q', 'status'])) Try a different search. @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($departments->hasPages())
            <div class="card-footer">{{ $departments->links() }}</div>
        @endif
    </div>
@endsection
