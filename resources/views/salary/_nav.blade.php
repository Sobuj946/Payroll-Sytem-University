@php
    $salaryTabs = [
        ['salary.index', 'Employees', 'bi-people', 'salary.view', 'salary.index'],
        ['salary.components.index', 'Components', 'bi-list-ul', 'salary.view', 'salary.components.*'],
        ['salary.adjustments.index', 'Overtime, bonus & other items', 'bi-plus-slash-minus', 'payroll.view', 'salary.adjustments.*'],
    ];
@endphp
<ul class="nav nav-pills mb-3">
    @foreach ($salaryTabs as [$route, $label, $icon, $permission, $pattern])
        @can($permission)
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs($pattern) ? 'active' : '' }}" href="{{ route($route) }}">
                    <i class="bi {{ $icon }} me-1"></i>{{ $label }}
                </a>
            </li>
        @endcan
    @endforeach
</ul>
