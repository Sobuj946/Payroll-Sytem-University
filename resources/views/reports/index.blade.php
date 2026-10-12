@extends('layouts.app')

@section('title', 'Reports')

@section('content')
    @php
        $icons = ['Employees' => 'bi-people', 'Attendance' => 'bi-calendar-check', 'Leave' => 'bi-calendar2-minus', 'Payroll' => 'bi-cash-stack'];
    @endphp

    <p class="text-muted">Open a report, choose the filters you need, then print it or download it as PDF or CSV (CSV opens in Excel).</p>

    <div class="row g-3">
        @foreach ($groups as $group => $reports)
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><i class="bi {{ $icons[$group] ?? 'bi-file-text' }} me-2"></i>{{ $group }} reports</div>
                    <div class="list-group list-group-flush">
                        @foreach ($reports as $key => $report)
                            <a href="{{ route('reports.show', $key) }}" class="list-group-item list-group-item-action">
                                <div class="fw-semibold">{{ $report['title'] }}</div>
                                <div class="small text-muted">{{ $report['about'] }}</div>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
