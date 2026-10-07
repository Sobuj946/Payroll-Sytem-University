@extends('layouts.app')

@section('title', $employee->full_name)

@section('content')
    @php
        $canAttendance = auth()->user()->hasPermission('attendance.view');
        $canLeave = auth()->user()->hasPermission('leave.view');
        $canSalary = auth()->user()->hasPermission('salary.view');
        $canPayroll = auth()->user()->hasPermission('payroll.view');
        $taka = fn ($amount) => '৳' . number_format((float) $amount, 2);
    @endphp

    <div class="row g-3">
        {{-- Profile summary --}}
        <div class="col-lg-4 col-xl-3">
            <div class="card">
                <div class="card-body text-center">
                    @if ($employee->photo_url)
                        <img src="{{ $employee->photo_url }}" alt="" class="rounded-circle mb-3" width="96" height="96" style="object-fit: cover;">
                    @else
                        <span class="avatar-circle mb-3" style="width: 96px; height: 96px; font-size: 2rem;">{{ $employee->initials }}</span>
                    @endif
                    <h2 class="h5 mb-1">{{ $employee->full_name }}</h2>
                    <div class="text-muted mb-2">{{ $employee->designation?->name }}</div>
                    <x-status-badge :status="$employee->status" />
                </div>
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Employee ID</span><span>{{ $employee->employee_code }}</span></li>
                    <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Department</span><span>{{ $employee->department?->name }}</span></li>
                    <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Joined</span><span>{{ $employee->joining_date->format('d M Y') }}</span></li>
                    <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Type</span><span>{{ \App\Models\Employee::EMPLOYMENT_TYPES[$employee->employment_type] ?? $employee->employment_type }}</span></li>
                </ul>
                <div class="card-body d-flex gap-2">
                    <a href="{{ route('employees.index') }}" class="btn btn-light flex-fill">Back</a>
                    @can('employees.manage')
                        <a href="{{ route('employees.edit', $employee) }}" class="btn btn-primary flex-fill">Edit</a>
                    @endcan
                </div>
            </div>
        </div>

        {{-- Tabs --}}
        <div class="col-lg-8 col-xl-9">
            <div class="card">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs" role="tablist">
                        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-overview" type="button">Overview</button></li>
                        @if ($canAttendance) <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-attendance" type="button">Attendance</button></li> @endif
                        @if ($canLeave) <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-leave" type="button">Leave</button></li> @endif
                        @if ($canSalary) <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-salary" type="button">Salary</button></li> @endif
                        @if ($canPayroll) <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-payroll" type="button">Payroll</button></li> @endif
                    </ul>
                </div>

                <div class="tab-content">
                    {{-- Overview --}}
                    <div class="tab-pane fade show active p-3" id="tab-overview">
                        <div class="row">
                            <div class="col-md-6">
                                <h3 class="h6 text-muted text-uppercase small">Personal</h3>
                                <dl class="row">
                                    <dt class="col-5">Gender</dt><dd class="col-7">{{ \App\Models\Employee::GENDERS[$employee->gender] ?? $employee->gender }}</dd>
                                    <dt class="col-5">Date of birth</dt><dd class="col-7">{{ $employee->date_of_birth->format('d M Y') }}</dd>
                                    <dt class="col-5">NID</dt><dd class="col-7">{{ $employee->nid }}</dd>
                                    <dt class="col-5">Email</dt><dd class="col-7 text-break">{{ $employee->email }}</dd>
                                    <dt class="col-5">Mobile</dt><dd class="col-7">{{ $employee->phone }}</dd>
                                    <dt class="col-5">Address</dt><dd class="col-7">{{ $employee->address ?: '—' }}</dd>
                                </dl>
                            </div>
                            <div class="col-md-6">
                                <h3 class="h6 text-muted text-uppercase small">Emergency contact</h3>
                                <dl class="row">
                                    <dt class="col-5">Name</dt><dd class="col-7">{{ $employee->emergency_contact_name ?: '—' }}</dd>
                                    <dt class="col-5">Mobile</dt><dd class="col-7">{{ $employee->emergency_contact_phone ?: '—' }}</dd>
                                </dl>
                                <h3 class="h6 text-muted text-uppercase small mt-3">Bank</h3>
                                <dl class="row mb-0">
                                    <dt class="col-5">Bank</dt><dd class="col-7">{{ $employee->bank_name ?: '—' }}</dd>
                                    <dt class="col-5">Branch</dt><dd class="col-7">{{ $employee->bank_branch ?: '—' }}</dd>
                                    <dt class="col-5">Account</dt><dd class="col-7">{{ $employee->bank_account_number ?: '—' }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>

                    {{-- Attendance --}}
                    @if ($canAttendance)
                        <div class="tab-pane fade" id="tab-attendance">
                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <thead><tr><th>Date</th><th>Check in</th><th>Check out</th><th>Hours</th><th>Status</th></tr></thead>
                                    <tbody>
                                        @forelse ($attendance as $row)
                                            <tr>
                                                <td>{{ $row->date->format('D, d M Y') }}</td>
                                                <td>{{ $row->check_in ? substr($row->check_in, 0, 5) : '—' }}</td>
                                                <td>{{ $row->check_out ? substr($row->check_out, 0, 5) : '—' }}</td>
                                                <td>{{ $row->working_hours }}</td>
                                                <td><x-status-badge :status="$row->status" /></td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="5"><div class="empty-state"><i class="bi bi-calendar-check"></i>No attendance recorded yet.</div></td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <div class="small text-muted p-3 pt-2">Showing the latest 15 records. <a href="{{ route('attendance.employee', $employee) }}">View full history</a></div>
                        </div>
                    @endif

                    {{-- Leave --}}
                    @if ($canLeave)
                        <div class="tab-pane fade" id="tab-leave">
                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <thead><tr><th>Type</th><th>From</th><th>To</th><th>Days</th><th>Status</th></tr></thead>
                                    <tbody>
                                        @forelse ($leaves as $leave)
                                            <tr>
                                                <td>{{ $leave->leaveType->name }}</td>
                                                <td>{{ $leave->start_date->format('d M Y') }}</td>
                                                <td>{{ $leave->end_date->format('d M Y') }}</td>
                                                <td>{{ $leave->days + 0 }}</td>
                                                <td><x-status-badge :status="$leave->status" /></td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="5"><div class="empty-state"><i class="bi bi-calendar2-minus"></i>No leave requests yet.</div></td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    {{-- Salary --}}
                    @if ($canSalary)
                        <div class="tab-pane fade p-3" id="tab-salary">
                            <div class="mb-3">
                                <span class="text-muted">Basic salary</span>
                                <span class="fs-5 fw-semibold ms-2">{{ $taka($employee->basic_salary) }}</span>
                            </div>

                            <h3 class="h6 text-muted text-uppercase small">Current salary structure</h3>
                            <div class="table-responsive mb-4">
                                <table class="table table-sm align-middle">
                                    <thead><tr><th>Component</th><th>Type</th><th>Basis</th><th class="text-end">Amount</th></tr></thead>
                                    <tbody>
                                        @forelse ($structure as $line)
                                            <tr>
                                                <td>{{ $line['name'] }}</td>
                                                <td>{{ ucfirst($line['type']) }}</td>
                                                <td class="text-muted">{{ $line['basis'] }}</td>
                                                <td class="text-end">{{ $taka($line['amount']) }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="4" class="text-muted">No allowances or deductions assigned.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            <div class="mb-3"><a href="{{ route('salary.employee', $employee) }}" class="btn btn-sm btn-outline-primary">Open salary page</a></div>

                            <h3 class="h6 text-muted text-uppercase small">Basic salary changes</h3>
                            <ul class="list-unstyled mb-0">
                                @forelse ($salaryChanges as $change)
                                    <li class="mb-2">
                                        {{ $change->description }}
                                        <div class="small text-muted">{{ $change->created_at->format('d M Y, h:i A') }} &middot; {{ $change->user?->name ?? 'System' }}</div>
                                    </li>
                                @empty
                                    <li class="text-muted">No changes have been recorded since the employee was added.</li>
                                @endforelse
                            </ul>
                        </div>
                    @endif

                    {{-- Payroll --}}
                    @if ($canPayroll)
                        <div class="tab-pane fade" id="tab-payroll">
                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <thead><tr><th>Period</th><th class="text-end">Gross</th><th class="text-end">Deductions</th><th class="text-end">Net salary</th><th>Period status</th></tr></thead>
                                    <tbody>
                                        @forelse ($payrolls as $payroll)
                                            <tr>
                                                <td>{{ $payroll->period->label }}</td>
                                                <td class="text-end">{{ $taka($payroll->gross_salary) }}</td>
                                                <td class="text-end">{{ $taka($payroll->total_deductions) }}</td>
                                                <td class="text-end fw-semibold">{{ $taka($payroll->net_salary) }}</td>
                                                <td><x-status-badge :status="$payroll->period->status" /></td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="5"><div class="empty-state"><i class="bi bi-calculator"></i>No payroll has been processed for this employee yet.</div></td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
