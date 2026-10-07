@extends('layouts.app')

@section('title', 'Designations')

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('designations.index') }}" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label for="q" class="form-label small text-muted mb-1">Search</label>
                    <input type="search" id="q" name="q" value="{{ request('q') }}" class="form-control" placeholder="Designation name">
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
                    <a href="{{ route('designations.index') }}" class="btn btn-light">Reset</a>
                </div>
                @can('designations.manage')
                    <div class="col-md text-md-end">
                        <a href="{{ route('designations.create') }}" class="btn btn-success"><i class="bi bi-plus-lg me-1"></i>Add designation</a>
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
                    @forelse ($designations as $designation)
                        <tr>
                            <td class="fw-semibold">
                                <a href="{{ route('designations.show', $designation) }}" class="text-decoration-none">{{ $designation->name }}</a>
                            </td>
                            <td class="text-muted">{{ \Illuminate\Support\Str::limit($designation->description, 70) ?: '—' }}</td>
                            <td class="text-center">{{ $designation->employees_count }}</td>
                            <td><x-status-badge :status="$designation->status" /></td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('designations.show', $designation) }}" class="btn btn-sm btn-outline-secondary">View</a>
                                @can('designations.manage')
                                    <a href="{{ route('designations.edit', $designation) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                    <form method="POST" action="{{ route('designations.status', $designation) }}" class="d-inline"
                                          data-confirm="{{ $designation->status === 'active' ? 'Deactivate' : 'Activate' }} {{ $designation->name }}?">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-sm btn-outline-{{ $designation->status === 'active' ? 'warning' : 'success' }}">
                                            {{ $designation->status === 'active' ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <i class="bi bi-award"></i>
                                    No designations found.
                                    @if (request()->hasAny(['q', 'status'])) Try a different search. @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($designations->hasPages())
            <div class="card-footer bg-white">{{ $designations->links() }}</div>
        @endif
    </div>
@endsection
