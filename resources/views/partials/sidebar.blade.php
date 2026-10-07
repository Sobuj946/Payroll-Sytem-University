@php
    $authUser = auth()->user();

    // [label, icon, route name, any-of permissions (null = everyone), active route pattern]
    // A link only appears once its route exists, so the menu grows as each phase is built.
    $menu = [
        'Overview' => [
            ['Dashboard', 'bi-speedometer2', 'dashboard', null, 'dashboard'],
        ],
        'People' => [
            ['Employees', 'bi-people', 'employees.index', ['employees.view'], 'employees.*'],
            ['Departments', 'bi-diagram-3', 'departments.index', ['departments.view'], 'departments.*'],
            ['Designations', 'bi-award', 'designations.index', ['designations.view'], 'designations.*'],
        ],
        'Time & Leave' => [
            ['Attendance', 'bi-calendar-check', 'attendance.index', ['attendance.view'], 'attendance.*'],
            ['Leave Requests', 'bi-calendar2-minus', 'leave.index', ['leave.view'], 'leave.*'],
        ],
        'Payroll' => [
            ['Salary Structure', 'bi-cash-stack', 'salary.index', ['salary.view'], 'salary.*'],
            ['Payroll', 'bi-calculator', 'payroll.index', ['payroll.view'], 'payroll.*'],
            ['Payments', 'bi-bank', 'payments.index', ['payments.view'], 'payments.*'],
            ['Salary Slips', 'bi-receipt', 'payslips.index', ['payslips.view'], 'payslips.*'],
        ],
        'Reports' => [
            ['Reports', 'bi-bar-chart-line', 'reports.index', ['reports.employee', 'reports.attendance', 'reports.leave', 'reports.payroll'], 'reports.*'],
        ],
        'Administration' => [
            ['Users', 'bi-person-gear', 'users.index', ['users.manage'], 'users.*'],
            ['Audit Logs', 'bi-journal-text', 'audit.index', ['audit.view'], 'audit.*'],
            ['Settings', 'bi-gear', 'settings.index', ['settings.manage'], 'settings.*'],
        ],
        'My Account' => [
            ['My Profile', 'bi-person-badge', 'my.profile', ['self.access'], 'my.profile'],
            ['My Attendance', 'bi-calendar-check', 'my.attendance', ['self.access'], 'my.attendance'],
            ['My Leave', 'bi-calendar2-minus', 'my.leave', ['self.access'], 'my.leave'],
            ['My Salary Slips', 'bi-receipt', 'my.payslips', ['self.access'], 'my.payslips'],
        ],
    ];

    $allowed = function (?array $permissions) use ($authUser) {
        if ($permissions === null) {
            return true;
        }
        foreach ($permissions as $permission) {
            if ($authUser->hasPermission($permission)) {
                return true;
            }
        }
        return false;
    };
@endphp

<aside class="app-sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="appSidebar" aria-label="Main menu">
    <div class="sidebar-brand d-flex justify-content-between align-items-start">
        <div>
            <div class="brand-name">{{ $companyName }}</div>
            <div class="brand-sub">Payroll System</div>
        </div>
        <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#appSidebar" aria-label="Close"></button>
    </div>

    <nav class="pb-4">
        @foreach ($menu as $group => $items)
            @php
                $visible = collect($items)->filter(function ($item) use ($allowed, $group, $authUser) {
                    if (! \Illuminate\Support\Facades\Route::has($item[2]) || ! $allowed($item[3])) {
                        return false;
                    }
                    // "My ..." pages only make sense for users linked to an employee record.
                    return $group !== 'My Account' || $authUser->employee_id;
                });
            @endphp

            @if ($visible->isNotEmpty())
                <div class="sidebar-heading">{{ $group }}</div>
                @foreach ($visible as $item)
                    <a href="{{ route($item[2]) }}" class="sidebar-link {{ request()->routeIs($item[4]) ? 'active' : '' }}">
                        <i class="bi {{ $item[1] }}"></i>
                        <span>{{ $item[0] }}</span>
                    </a>
                @endforeach
            @endif
        @endforeach
    </nav>
</aside>
