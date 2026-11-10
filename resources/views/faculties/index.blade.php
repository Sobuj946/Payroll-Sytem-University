@extends('layouts.app')

@section('title', 'Faculties')

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('faculties.index') }}" class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label for="q" class="form-label small text-muted mb-1">Search</label>
                    <input type="search" id="q" name="q" value="{{ request('q') }}" class="form-control" placeholder="Faculty name">
                </div>
                <div class="col-md-auto">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>Search</button>
                    <a href="{{ route('faculties.index') }}" class="btn btn-light">Reset</a>
                </div>
                @can('departments.manage')
                    <div class="col-md text-md-end">
                        <a href="{{ route('faculties.create') }}" class="btn btn-success"><i class="bi bi-plus-lg me-1"></i>Add faculty</a>
                    </div>
                @endcan
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Faculty</th><th>Short name</th><th class="text-center">Departments</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    @forelse ($faculties as $faculty)
                        <tr>
                            <td class="fw-semibold">{{ $faculty->name }}</td>
                            <td>{{ $faculty->short_name ?: '—' }}</td>
                            <td class="text-center">{{ $faculty->active_departments_count }} / {{ $faculty->departments_count }}</td>
                            <td><x-status-badge :status="$faculty->status" /></td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('departments.index', ['faculty_id' => $faculty->id]) }}" class="btn btn-sm btn-outline-secondary">Departments</a>
                                @can('departments.manage')
                                    <a href="{{ route('faculties.edit', $faculty) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                    <form method="POST" action="{{ route('faculties.status', $faculty) }}" class="d-inline"
                                          data-confirm="{{ $faculty->status === 'active' ? 'Deactivate' : 'Activate' }} {{ $faculty->name }}?">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-sm btn-outline-{{ $faculty->status === 'active' ? 'warning' : 'success' }}">
                                            {{ $faculty->status === 'active' ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty-state"><i class="bi bi-buildings"></i>No faculties yet.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($faculties->hasPages())
            <div class="card-footer">{{ $faculties->links() }}</div>
        @endif
        <div class="card-footer small text-muted">The first number counts active departments, the second all departments.</div>
    </div>
@endsection
