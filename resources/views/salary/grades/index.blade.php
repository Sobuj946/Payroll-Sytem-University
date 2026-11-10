@extends('layouts.app')

@section('title', 'Pay Grades')

@section('content')
    @include('salary._nav')

    <div class="alert alert-warning py-2">
        <i class="bi bi-exclamation-triangle me-1"></i>
        The grades and amounts that come with the system are <strong>samples</strong>. Check every grade against the official pay scale and your university's service rules, and correct them here.
    </div>

    @can('salary.manage')
        <div class="d-flex justify-content-end mb-3">
            <a href="{{ route('salary.grades.create') }}" class="btn btn-success"><i class="bi bi-plus-lg me-1"></i>Add grade</a>
        </div>
    @endcan

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr><th>Grade</th><th>Name</th><th class="text-end">Starting basic</th><th class="text-end">Highest basic</th><th class="text-end">Yearly increment</th><th class="text-center">Employees</th><th>Status</th>@can('salary.manage')<th class="text-end">Actions</th>@endcan</tr>
                </thead>
                <tbody>
                    @forelse ($grades as $grade)
                        <tr>
                            <td class="fw-semibold">{{ $grade->grade_no }}</td>
                            <td>{{ $grade->name }}</td>
                            <td class="text-end">৳{{ number_format($grade->min_basic, 2) }}</td>
                            <td class="text-end">{{ $grade->max_basic === null ? 'Fixed scale' : '৳' . number_format($grade->max_basic, 2) }}</td>
                            <td class="text-end">{{ $grade->increment_percent + 0 }}%</td>
                            <td class="text-center">{{ $grade->employees_count }}</td>
                            <td><x-status-badge :status="$grade->status" /></td>
                            @can('salary.manage')
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('salary.grades.edit', $grade) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                    <form method="POST" action="{{ route('salary.grades.status', $grade) }}" class="d-inline"
                                          data-confirm="{{ $grade->status === 'active' ? 'Deactivate' : 'Activate' }} Grade {{ $grade->grade_no }}?">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-sm btn-outline-{{ $grade->status === 'active' ? 'warning' : 'success' }}">
                                            {{ $grade->status === 'active' ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>
                                </td>
                            @endcan
                        </tr>
                    @empty
                        <tr><td colspan="8"><div class="empty-state"><i class="bi bi-layers"></i>No pay grades yet.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer small text-muted">
            When an employee is placed in a grade, the system refuses a basic salary outside that grade's range.
            The yearly increment is a percentage of the current basic salary, rounded to the nearest taka and never above the highest basic of the grade.
        </div>
    </div>
@endsection
