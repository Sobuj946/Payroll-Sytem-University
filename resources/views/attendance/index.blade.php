@extends('layouts.app')

@section('title', 'Attendance')

@section('content')
    @include('attendance._nav')

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('attendance.index') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label for="date" class="form-label small text-muted mb-1">Date</label>
                    <input type="date" id="date" name="date" value="{{ $date->toDateString() }}" max="{{ today()->toDateString() }}" class="form-control">
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
                    <button type="submit" class="btn btn-primary">Show</button>
                    <a href="{{ route('attendance.index') }}" class="btn btn-light">Today</a>
                </div>
            </form>
        </div>
    </div>

    @if ($offReason)
        <div class="alert alert-info py-2"><i class="bi bi-info-circle me-1"></i>{{ $date->format('l, d M Y') }} is marked as <strong>{{ $offReason }}</strong>. Attendance is not normally recorded on this day.</div>
    @endif
    @if ($date->isFuture())
        <div class="alert alert-warning py-2">Attendance cannot be recorded for a future date.</div>
    @endif

    <form method="POST" action="{{ route('attendance.store') }}">
        @csrf
        <input type="hidden" name="date" value="{{ $date->toDateString() }}">
        <input type="hidden" name="department_id" value="{{ request('department_id') }}">

        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span>{{ $date->format('l, d F Y') }} <span class="text-muted fw-normal">&middot; {{ $employees->count() }} employees</span></span>
                @if ($canEdit && $employees->isNotEmpty())
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="markAllPresent">Mark everyone present</button>
                @endif
            </div>

            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Department</th>
                            <th style="width: 130px;">Check in</th>
                            <th style="width: 130px;">Check out</th>
                            <th style="width: 90px;">Hours</th>
                            <th style="width: 150px;">Status</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($employees as $i => $employee)
                            @php
                                $record = $records[$employee->id] ?? null;
                                $status = old("rows.$i.status", $record?->status ?? (in_array($employee->id, $onLeave) ? 'leave' : 'present'));
                                $checkIn = old("rows.$i.check_in", $record?->check_in ? substr($record->check_in, 0, 5) : '');
                                $checkOut = old("rows.$i.check_out", $record?->check_out ? substr($record->check_out, 0, 5) : '');
                            @endphp
                            <tr>
                                <td>
                                    <input type="hidden" name="rows[{{ $i }}][employee_id]" value="{{ $employee->id }}">
                                    <div class="fw-semibold">{{ $employee->full_name }}</div>
                                    <div class="small text-muted">{{ $employee->employee_code }}</div>
                                </td>
                                <td>{{ $employee->department?->name }}</td>
                                @if ($canEdit)
                                    <td><input type="time" name="rows[{{ $i }}][check_in]" value="{{ $checkIn }}" class="form-control form-control-sm"></td>
                                    <td>
                                        <input type="time" name="rows[{{ $i }}][check_out]" value="{{ $checkOut }}"
                                               class="form-control form-control-sm @error("rows.$i.check_out") is-invalid @enderror">
                                        @error("rows.$i.check_out") <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </td>
                                    <td class="text-muted">{{ $record ? $record->working_hours + 0 : '—' }}</td>
                                    <td>
                                        <select name="rows[{{ $i }}][status]" class="form-select form-select-sm att-status">
                                            @foreach (\App\Models\Attendance::STATUSES as $option)
                                                <option value="{{ $option }}" @selected($status === $option)>{{ ucwords(str_replace('_', ' ', $option)) }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><input type="text" name="rows[{{ $i }}][remarks]" value="{{ old("rows.$i.remarks", $record?->remarks) }}" maxlength="255" class="form-control form-control-sm"></td>
                                @else
                                    <td>{{ $checkIn ?: '—' }}</td>
                                    <td>{{ $checkOut ?: '—' }}</td>
                                    <td class="text-muted">{{ $record ? $record->working_hours + 0 : '—' }}</td>
                                    <td>
                                        @if ($record) <x-status-badge :status="$record->status" />
                                        @else <span class="text-muted small">Not recorded</span> @endif
                                    </td>
                                    <td class="text-muted">{{ $record?->remarks ?: '—' }}</td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="7"><div class="empty-state"><i class="bi bi-people"></i>No active employees found for this date.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($canEdit && $employees->isNotEmpty())
                <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span class="small text-muted">
                        Rows start as Present. Someone who checks in after office start plus the grace period is saved as Late.
                        Absent and Leave rows ignore times.
                    </span>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i>Save attendance</button>
                </div>
            @endif
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        var markAll = document.getElementById('markAllPresent');
        if (markAll) {
            markAll.addEventListener('click', function () {
                document.querySelectorAll('.att-status').forEach(function (select) { select.value = 'present'; });
            });
        }
    </script>
@endpush
