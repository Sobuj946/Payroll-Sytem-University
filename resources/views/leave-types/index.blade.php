@extends('layouts.app')

@section('title', 'Leave Types')

@section('content')
    @include('leave._nav')

    <div class="d-flex justify-content-end mb-3">
        <a href="{{ route('leave-types.create') }}" class="btn btn-success"><i class="bi bi-plus-lg me-1"></i>Add leave type</a>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr><th>Name</th><th class="text-center">Days per year</th><th>Pay</th><th class="text-center">Requests</th><th>Status</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    @forelse ($types as $type)
                        <tr>
                            <td class="fw-semibold">{{ $type->name }}</td>
                            <td class="text-center">{{ $type->days_per_year > 0 ? $type->days_per_year : 'No limit' }}</td>
                            <td>{!! $type->is_paid ? '<span class="badge text-bg-success">Paid</span>' : '<span class="badge text-bg-secondary">Unpaid</span>' !!}</td>
                            <td class="text-center">{{ $type->requests_count }}</td>
                            <td><x-status-badge :status="$type->status" /></td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('leave-types.edit', $type) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                <form method="POST" action="{{ route('leave-types.status', $type) }}" class="d-inline"
                                      data-confirm="{{ $type->status === 'active' ? 'Deactivate' : 'Activate' }} {{ $type->name }}?">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-sm btn-outline-{{ $type->status === 'active' ? 'warning' : 'success' }}">
                                        {{ $type->status === 'active' ? 'Deactivate' : 'Activate' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty-state"><i class="bi bi-sliders"></i>No leave types yet.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer small text-muted">
            Unpaid types and types with "No limit" are not checked against a balance. Changing the yearly allowance affects balances created from now on, not the ones already issued.
        </div>
    </div>
@endsection
