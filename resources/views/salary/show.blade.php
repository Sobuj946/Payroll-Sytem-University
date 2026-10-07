@extends('layouts.app')

@section('title', $employee->full_name . ' · Salary')

@section('content')
    @php
        $taka = fn ($amount) => '৳' . number_format((float) $amount, 2);
        $canManage = auth()->user()->hasPermission('salary.manage');
    @endphp

    @if ($errors->any())
        <div class="alert alert-danger py-2">
            @foreach ($errors->all() as $error) <div>{{ $error }}</div> @endforeach
        </div>
    @endif

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h2 class="h5 mb-0">{{ $employee->full_name }} <span class="text-muted fw-normal">({{ $employee->employee_code }})</span></h2>
            <div class="text-muted small">{{ $employee->designation?->name }} &middot; {{ $employee->department?->name }}</div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('salary.index') }}" class="btn btn-light">Back to list</a>
            @can('employees.view')
                <a href="{{ route('employees.show', $employee) }}" class="btn btn-outline-secondary">Profile</a>
            @endcan
        </div>
    </div>

    <div class="row g-3 mb-3">
        @foreach ([['Basic salary', $totals['basic']], ['Allowances', $totals['allowances']], ['Fixed deductions', $totals['deductions']], ['Gross (basic + allowances)', $totals['gross']]] as [$label, $value])
            <div class="col-6 col-xl-3">
                <div class="card"><div class="card-body py-3">
                    <div class="small text-muted">{{ $label }}</div>
                    <div class="fs-5 fw-bold">{{ $taka($value) }}</div>
                </div></div>
            </div>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card mb-3">
                <div class="card-header">Current salary structure <span class="text-muted fw-normal">&middot; as of {{ today()->format('d M Y') }}</span></div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr><th>Component</th><th>Type</th><th>Basis</th><th class="text-end">Amount</th><th>Since</th>@if ($canManage)<th class="text-end">Actions</th>@endif</tr>
                        </thead>
                        <tbody>
                            @forelse ($lines as $line)
                                @php $row = $line['row']; @endphp
                                <tr>
                                    <td>
                                        {{ $line['name'] }}
                                        @unless ($line['active']) <span class="badge text-bg-secondary ms-1" title="Ignored by payroll while inactive">Inactive</span> @endunless
                                    </td>
                                    <td>{{ ucfirst($line['type']) }}</td>
                                    <td class="text-muted">{{ $line['basis'] }}</td>
                                    <td class="text-end">{{ $line['type'] === 'deduction' ? '− ' : '' }}{{ $taka($line['amount']) }}</td>
                                    <td class="text-nowrap">{{ $row->effective_from->format('d M Y') }}@if ($row->effective_to) <span class="text-muted">to {{ $row->effective_to->format('d M Y') }}</span> @endif</td>
                                    @if ($canManage)
                                        <td class="text-end text-nowrap">
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#edit-{{ $row->id }}">Change amount</button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#end-{{ $row->id }}">Remove</button>
                                        </td>
                                    @endif
                                </tr>
                                @if ($canManage)
                                    <tr class="collapse" id="edit-{{ $row->id }}">
                                        <td colspan="6" class="bg-body-tertiary">
                                            <form method="POST" action="{{ route('salary.revise', [$employee, $row]) }}" class="row g-2 align-items-end"
                                                  data-confirm="Change {{ $line['name'] }} for {{ $employee->full_name }}?">
                                                @csrf
                                                <div class="col-md-3">
                                                    <label class="form-label small mb-1">New {{ $line['is_percent'] ? 'percentage' : 'amount (৳)' }}</label>
                                                    <input type="number" name="value" step="0.01" min="0" value="{{ $line['rate'] }}" class="form-control form-control-sm" required>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label small mb-1">Starts on</label>
                                                    <input type="date" name="effective_from" value="{{ today()->toDateString() }}" class="form-control form-control-sm" required>
                                                </div>
                                                <div class="col-md-auto"><button type="submit" class="btn btn-sm btn-primary">Save</button></div>
                                            </form>
                                        </td>
                                    </tr>
                                    <tr class="collapse" id="end-{{ $row->id }}">
                                        <td colspan="6" class="bg-body-tertiary">
                                            <form method="POST" action="{{ route('salary.end', [$employee, $row]) }}" class="row g-2 align-items-end"
                                                  data-confirm="Remove {{ $line['name'] }} from {{ $employee->full_name }}'s salary?">
                                                @csrf
                                                <div class="col-md-4">
                                                    <label class="form-label small mb-1">Last day it applies</label>
                                                    <input type="date" name="effective_to" value="{{ today()->toDateString() }}" class="form-control form-control-sm" required>
                                                </div>
                                                <div class="col-md-auto"><button type="submit" class="btn btn-sm btn-danger">Remove</button></div>
                                            </form>
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr><td colspan="6"><div class="empty-state"><i class="bi bi-list-ul"></i>No allowances or deductions are assigned yet.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($history->isNotEmpty())
                <div class="card">
                    <div class="card-header">Earlier and scheduled lines</div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Component</th><th>Value</th><th>From</th><th>To</th></tr></thead>
                            <tbody>
                                @foreach ($history as $row)
                                    <tr>
                                        <td>{{ $row->component->name }}</td>
                                        <td>{{ $row->value !== null ? ($row->value + 0) . ($row->component->calc_type === 'percent' ? '%' : '') : 'Default' }}</td>
                                        <td>{{ $row->effective_from->format('d M Y') }}</td>
                                        <td>{{ $row->effective_to?->format('d M Y') ?? 'Ongoing' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-xl-4">
            @if ($canManage)
                <div class="card mb-3">
                    <div class="card-header">Add allowance or deduction</div>
                    <div class="card-body">
                        @if ($assignable->isEmpty())
                            <p class="text-muted mb-0">Every available component is already assigned.</p>
                        @else
                            <form method="POST" action="{{ route('salary.assign', $employee) }}">
                                @csrf
                                <div class="mb-3">
                                    <label for="salary_component_id" class="form-label">Component</label>
                                    <select id="salary_component_id" name="salary_component_id" class="form-select" required>
                                        <option value="">Select...</option>
                                        @foreach ($assignable as $component)
                                            <option value="{{ $component->id }}">{{ $component->name }} ({{ $component->type }}, {{ $component->calc_type === 'percent' ? ($component->default_value + 0) . '%' : '৳' . number_format($component->default_value, 0) }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="value" class="form-label">Amount or percentage</label>
                                    <input type="number" id="value" name="value" step="0.01" min="0" class="form-control">
                                    <div class="form-text">Leave empty to use the default shown above.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="effective_from" class="form-label">Starts on</label>
                                    <input type="date" id="effective_from" name="effective_from" value="{{ today()->toDateString() }}" class="form-control" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Add to salary</button>
                            </form>
                        @endif
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">Revise basic salary</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('salary.basic', $employee) }}"
                              data-confirm="Change the basic salary of {{ $employee->full_name }}?">
                            @csrf
                            <div class="mb-3">
                                <label for="basic_salary" class="form-label">New basic salary (৳)</label>
                                <input type="number" id="basic_salary" name="basic_salary" step="0.01" min="0" value="{{ $employee->basic_salary }}" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label for="reason" class="form-label">Reason</label>
                                <input type="text" id="reason" name="reason" maxlength="255" class="form-control" placeholder="e.g. annual increment" required>
                            </div>
                            <button type="submit" class="btn btn-outline-primary w-100">Update basic salary</button>
                            <div class="form-text mt-2">Applies from now on. Payrolls already processed keep their own figures.</div>
                        </form>
                    </div>
                </div>
            @endif

            <div class="card">
                <div class="card-header">Basic salary changes</div>
                <ul class="list-group list-group-flush">
                    @forelse ($changes as $change)
                        <li class="list-group-item small">
                            {{ $change->description }}
                            <div class="text-muted">{{ $change->created_at->format('d M Y, h:i A') }} &middot; {{ $change->user?->name ?? 'System' }}</div>
                        </li>
                    @empty
                        <li class="list-group-item text-muted small">No changes recorded since the employee was added.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
@endsection
