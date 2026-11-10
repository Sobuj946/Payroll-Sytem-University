@extends('layouts.app')

@section('title', 'Annual Increment')

@section('content')
    @include('salary._nav')

    @php $taka = fn ($amount) => '৳' . number_format((float) $amount, 2); @endphp

    @if ($errors->any())
        <div class="alert alert-danger py-2">@foreach ($errors->all() as $error) <div>{{ $error }}</div> @endforeach</div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('salary.increments.index') }}" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label for="department_id" class="form-label small text-muted mb-1">Department</label>
                    <select id="department_id" name="department_id" class="form-select">
                        <option value="">All departments</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected((string) request('department_id') === (string) $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="category" class="form-label small text-muted mb-1">Category</label>
                    <select id="category" name="category" class="form-select">
                        <option value="">All</option>
                        @foreach (\App\Models\Designation::CATEGORIES as $key => $label)
                            <option value="{{ $key }}" @selected(request('category') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-auto">
                    <button type="submit" class="btn btn-primary">Show</button>
                    <a href="{{ route('salary.increments.index') }}" class="btn btn-light">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <form method="POST" action="{{ route('salary.increments.apply') }}" data-confirm="Apply the increment to every ticked employee? Their basic salary changes now.">
        @csrf
        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span>Employees with a pay grade</span>
                <span class="small text-muted fw-normal">Total increase of the ticked rows: <strong>{{ $taka($totalGain) }}</strong> a month</span>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr><th style="width: 36px;"></th><th>Employee</th><th>Grade</th><th class="text-end">Basic now</th><th class="text-end">Increment</th><th class="text-end">New basic</th><th>Note</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            @php $e = $row['employee']; $p = $row['proposal']; @endphp
                            <tr>
                                <td>@if ($p['eligible'])<input type="checkbox" class="form-check-input" name="ids[]" value="{{ $e->id }}" checked>@endif</td>
                                <td>{{ $e->full_name }}<div class="small text-muted">{{ $e->employee_code }} &middot; {{ $e->designation?->name }}</div></td>
                                <td>{{ $e->payGrade->grade_no }} <span class="small text-muted">({{ $e->payGrade->increment_percent + 0 }}%)</span></td>
                                <td class="text-end">{{ $taka($e->basic_salary) }}</td>
                                <td class="text-end">{{ $p['increment'] > 0 ? $taka($p['increment']) : '—' }}</td>
                                <td class="text-end fw-semibold">{{ $p['increment'] > 0 ? $taka($p['proposed']) : '—' }}</td>
                                <td class="small text-muted">{{ $p['note'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><div class="empty-state"><i class="bi bi-graph-up-arrow"></i>No active employee has a pay grade yet.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($rows->isNotEmpty())
                <div class="card-footer">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-6">
                            <label for="reason" class="form-label small mb-1">Reason (recorded in the salary history) <span class="text-danger">*</span></label>
                            <input type="text" id="reason" name="reason" maxlength="200" value="{{ old('reason', 'Annual increment ' . now()->format('F Y')) }}" class="form-control" required>
                        </div>
                        <div class="col-md-auto"><button type="submit" class="btn btn-primary"><i class="bi bi-graph-up-arrow me-1"></i>Apply increment to ticked</button></div>
                    </div>
                </div>
            @endif
        </div>
    </form>
@endsection
