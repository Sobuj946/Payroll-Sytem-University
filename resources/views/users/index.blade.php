@extends('layouts.app')

@section('title', 'Users')

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('users.index') }}" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label for="q" class="form-label small text-muted mb-1">Search</label>
                    <input type="search" id="q" name="q" value="{{ request('q') }}" class="form-control" placeholder="Name or email">
                </div>
                <div class="col-md-3">
                    <label for="role_id" class="form-label small text-muted mb-1">Role</label>
                    <select id="role_id" name="role_id" class="form-select">
                        <option value="">All roles</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" @selected((string) request('role_id') === (string) $role->id)>{{ $role->label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label small text-muted mb-1">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">All</option>
                        <option value="active" @selected(request('status') === 'active')>Active</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                    </select>
                </div>
                <div class="col-md-auto">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>Search</button>
                    <a href="{{ route('users.index') }}" class="btn btn-light">Reset</a>
                </div>
                <div class="col-md text-md-end">
                    <a href="{{ route('users.create') }}" class="btn btn-success"><i class="bi bi-plus-lg me-1"></i>Add user</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr><th>Name</th><th>Role</th><th>Linked employee</th><th>Last sign-in</th><th>Status</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td>
                                <span class="fw-semibold">{{ $user->name }}</span>
                                @if ($user->id === auth()->id()) <span class="badge text-bg-light border ms-1">You</span> @endif
                                <div class="small text-muted">{{ $user->email }}</div>
                            </td>
                            <td>{{ $user->role->label }}</td>
                            <td>{{ $user->employee ? $user->employee->employee_code . ' · ' . $user->employee->full_name : '—' }}</td>
                            <td class="small text-muted">{{ $user->last_login_at ? $user->last_login_at->diffForHumans() : 'Never' }}</td>
                            <td><x-status-badge :status="$user->status" /></td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('users.edit', $user) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                @if ($user->id !== auth()->id())
                                    <form method="POST" action="{{ route('users.status', $user) }}" class="d-inline"
                                          data-confirm="{{ $user->status === 'active' ? 'Deactivate' : 'Activate' }} the account of {{ $user->name }}?">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-sm btn-outline-{{ $user->status === 'active' ? 'warning' : 'success' }}">
                                            {{ $user->status === 'active' ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty-state"><i class="bi bi-person-gear"></i>No users found.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($users->hasPages())
            <div class="card-footer">{{ $users->links() }}</div>
        @endif
    </div>
@endsection
