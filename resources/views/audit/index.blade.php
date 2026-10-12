@extends('layouts.app')

@section('title', 'Audit Logs')

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger py-2">@foreach ($errors->all() as $error) <div>{{ $error }}</div> @endforeach</div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('audit.index') }}" class="row g-2 align-items-end">
                <div class="col-lg-2 col-md-4">
                    <label for="from" class="form-label small text-muted mb-1">From</label>
                    <input type="date" id="from" name="from" value="{{ request('from') }}" class="form-control">
                </div>
                <div class="col-lg-2 col-md-4">
                    <label for="to" class="form-label small text-muted mb-1">To</label>
                    <input type="date" id="to" name="to" value="{{ request('to') }}" class="form-control">
                </div>
                <div class="col-lg-2 col-md-4">
                    <label for="user_id" class="form-label small text-muted mb-1">User</label>
                    <select id="user_id" name="user_id" class="form-select">
                        <option value="">Anyone</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected((string) request('user_id') === (string) $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-4">
                    <label for="module" class="form-label small text-muted mb-1">Area</label>
                    <select id="module" name="module" class="form-select">
                        <option value="">All</option>
                        @foreach ($modules as $module)
                            <option value="{{ $module }}" @selected(request('module') === $module)>{{ ucfirst($module) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-4">
                    <label for="action" class="form-label small text-muted mb-1">Action</label>
                    <select id="action" name="action" class="form-select">
                        <option value="">All</option>
                        @foreach ($actions as $action)
                            <option value="{{ $action }}" @selected(request('action') === $action)>{{ ucwords(str_replace('_', ' ', $action)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-4">
                    <label for="q" class="form-label small text-muted mb-1">Contains</label>
                    <input type="search" id="q" name="q" value="{{ request('q') }}" class="form-control" placeholder="Words in the description">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Filter</button>
                    <a href="{{ route('audit.index') }}" class="btn btn-light">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead><tr><th>When</th><th>User</th><th>Area</th><th>Action</th><th>What happened</th><th>IP address</th></tr></thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="text-nowrap small">{{ $log->created_at->format('d M Y, h:i A') }}</td>
                            <td>{{ $log->user?->name ?? 'System / not signed in' }}</td>
                            <td>{{ ucfirst($log->module) }}</td>
                            <td><span class="badge text-bg-light border">{{ ucwords(str_replace('_', ' ', $log->action)) }}</span></td>
                            <td>{{ $log->description }}</td>
                            <td class="small text-muted">{{ $log->ip_address }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty-state"><i class="bi bi-journal-text"></i>No entries match.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($logs->hasPages())
            <div class="card-footer">{{ $logs->links() }}</div>
        @endif
        <div class="card-footer small text-muted">The audit log can only be read here. Entries cannot be edited or deleted from the application.</div>
    </div>
@endsection
