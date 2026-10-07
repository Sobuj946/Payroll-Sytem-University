@extends('layouts.app')

@section('title', 'Leave Requests')

@section('content')
    @include('leave._nav')

    @if ($errors->has('remarks'))
        <div class="alert alert-danger py-2">{{ $errors->first('remarks') }}</div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('leave.index') }}" class="row g-2 align-items-end">
                <div class="col-lg-3 col-md-6">
                    <label for="q" class="form-label small text-muted mb-1">Employee</label>
                    <input type="search" id="q" name="q" value="{{ request('q') }}" class="form-control" placeholder="Name or ID">
                </div>
                <div class="col-lg-2 col-md-6">
                    <label for="status" class="form-label small text-muted mb-1">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">All</option>
                        @foreach (['pending', 'approved', 'rejected', 'cancelled'] as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label for="leave_type_id" class="form-label small text-muted mb-1">Leave type</label>
                    <select id="leave_type_id" name="leave_type_id" class="form-select">
                        <option value="">All</option>
                        @foreach ($types as $type)
                            <option value="{{ $type->id }}" @selected((string) request('leave_type_id') === (string) $type->id)>{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-3 col-md-6">
                    <label for="department_id" class="form-label small text-muted mb-1">Department</label>
                    <select id="department_id" name="department_id" class="form-select">
                        <option value="">All</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected((string) request('department_id') === (string) $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-auto">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>Search</button>
                    <a href="{{ route('leave.index') }}" class="btn btn-light">Reset</a>
                </div>
            </form>
        </div>
    </div>

    @if ($pendingCount > 0 && ! request('status'))
        <div class="alert alert-warning py-2"><i class="bi bi-hourglass-split me-1"></i>{{ $pendingCount }} request(s) are waiting for a decision. They are listed first.</div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Leave type</th>
                        <th>Dates</th>
                        <th class="text-center">Days</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requests as $leave)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $leave->employee->full_name }}</div>
                                <div class="small text-muted">{{ $leave->employee->employee_code }} &middot; {{ $leave->employee->department?->name }}</div>
                            </td>
                            <td>{{ $leave->leaveType->name }}</td>
                            <td class="text-nowrap">
                                {{ $leave->start_date->format('d M Y') }}
                                @unless ($leave->start_date->isSameDay($leave->end_date)) &ndash; {{ $leave->end_date->format('d M Y') }} @endunless
                            </td>
                            <td class="text-center">{{ $leave->days + 0 }}</td>
                            <td class="text-muted" title="{{ $leave->reason }}">{{ \Illuminate\Support\Str::limit($leave->reason, 50) }}</td>
                            <td>
                                <x-status-badge :status="$leave->status" />
                                @if ($leave->remarks && $leave->status === 'rejected')
                                    <div class="small text-muted mt-1" title="{{ $leave->remarks }}">{{ \Illuminate\Support\Str::limit($leave->remarks, 40) }}</div>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                @if ($leave->status === 'pending' && auth()->user()->hasPermission('leave.manage'))
                                    @if ($leave->employee_id === auth()->user()->employee_id)
                                        <span class="small text-muted">Your own request</span>
                                    @else
                                        <form method="POST" action="{{ route('leave.approve', $leave) }}" class="d-inline"
                                              data-confirm="Approve {{ $leave->days + 0 }} day(s) of {{ $leave->leaveType->name }} for {{ $leave->employee->full_name }}?">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success">Approve</button>
                                        </form>
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal"
                                                data-reject-url="{{ route('leave.reject', $leave) }}" data-reject-who="{{ $leave->employee->full_name }}">Reject</button>
                                    @endif
                                @elseif ($leave->approver)
                                    <span class="small text-muted">{{ $leave->approver->name }}<br>{{ $leave->approved_at?->format('d M Y') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="empty-state"><i class="bi bi-calendar2-check"></i>No leave requests found.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($requests->hasPages())
            <div class="card-footer">{{ $requests->links() }}</div>
        @endif
    </div>

    {{-- One reject dialog shared by every row --}}
    <div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="" id="rejectForm" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h2 class="modal-title fs-6" id="rejectModalLabel">Reject leave request</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">You are rejecting the request from <strong id="rejectWho"></strong>.</p>
                    <label for="remarks" class="form-label">Reason <span class="text-danger">*</span></label>
                    <textarea id="remarks" name="remarks" rows="3" maxlength="255" class="form-control" required></textarea>
                    <div class="form-text">The employee will see this reason.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject request</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('[data-reject-url]').forEach(function (button) {
            button.addEventListener('click', function () {
                document.getElementById('rejectForm').action = button.dataset.rejectUrl;
                document.getElementById('rejectWho').textContent = button.dataset.rejectWho;
                document.getElementById('remarks').value = '';
            });
        });
    </script>
@endpush
