@php
    $attendanceTabs = [
        ['attendance.index', 'Daily sheet', 'bi-list-check'],
        ['attendance.monthly', 'Monthly view', 'bi-calendar3'],
        ['attendance.report', 'Late & absent reports', 'bi-exclamation-diamond'],
    ];
@endphp
<ul class="nav nav-pills mb-3">
    @foreach ($attendanceTabs as [$route, $label, $icon])
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs($route) ? 'active' : '' }}" href="{{ route($route) }}">
                <i class="bi {{ $icon }} me-1"></i>{{ $label }}
            </a>
        </li>
    @endforeach
</ul>
