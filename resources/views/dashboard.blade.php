@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="mb-4">
        <h2 class="h5 mb-1">Welcome back, {{ $user->name }}</h2>
        <div class="text-muted">{{ now()->format('l, d F Y') }}</div>
    </div>

    @if (count($cards))
        <div class="row g-3 mb-4">
            @foreach ($cards as $card)
                <div class="col-sm-6 col-xl-3">
                    <div class="card stat-card h-100">
                        <div class="card-body d-flex align-items-center gap-3">
                            <span class="stat-icon bg-{{ $card['tone'] }}-subtle text-{{ $card['tone'] }}">
                                <i class="bi {{ $card['icon'] }}"></i>
                            </span>
                            <div>
                                <div class="stat-value">{{ number_format($card['value']) }}</div>
                                <div class="stat-label">{{ $card['label'] }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if ($user->employee)
        <div class="card">
            <div class="card-header">My details</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-3">Employee ID</dt>
                    <dd class="col-sm-9">{{ $user->employee->employee_code }}</dd>
                    <dt class="col-sm-3">Department</dt>
                    <dd class="col-sm-9">{{ $user->employee->department?->name }}</dd>
                    <dt class="col-sm-3">Designation</dt>
                    <dd class="col-sm-9">{{ $user->employee->designation?->name }}</dd>
                    <dt class="col-sm-3">Joined</dt>
                    <dd class="col-sm-9 mb-0">{{ $user->employee->joining_date->format('d M Y') }}</dd>
                </dl>
            </div>
        </div>
    @elseif (! count($cards))
        <div class="card">
            <div class="card-body empty-state">
                <i class="bi bi-inbox"></i>
                There is nothing to show here yet.
            </div>
        </div>
    @endif
@endsection
