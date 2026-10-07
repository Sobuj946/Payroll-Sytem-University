@php
    $leaveTabs = [
        ['leave.index', 'Requests', 'bi-inbox', 'leave.view'],
        ['leave.balances', 'Balances', 'bi-pie-chart', 'leave.view'],
        ['leave-types.index', 'Leave types', 'bi-sliders', 'leave.types'],
    ];
@endphp
<ul class="nav nav-pills mb-3">
    @foreach ($leaveTabs as [$route, $label, $icon, $permission])
        @can($permission)
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs($route === 'leave.index' ? 'leave.index' : $route) ? 'active' : '' }}" href="{{ route($route) }}">
                    <i class="bi {{ $icon }} me-1"></i>{{ $label }}
                </a>
            </li>
        @endcan
    @endforeach
</ul>
