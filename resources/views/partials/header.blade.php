@php
    $headerUser = auth()->user();
    $initial = strtoupper(mb_substr($headerUser->name, 0, 1));
@endphp

<header class="app-header">
    <button class="btn btn-outline-secondary btn-sm d-lg-none" type="button"
            data-bs-toggle="offcanvas" data-bs-target="#appSidebar" aria-controls="appSidebar" aria-label="Open menu">
        <i class="bi bi-list"></i>
    </button>

    <h1 class="page-title">@yield('title', 'Dashboard')</h1>

    <div class="ms-auto dropdown">
        <button class="btn btn-link text-decoration-none text-dark d-flex align-items-center gap-2 p-0"
                type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <span class="avatar-circle">{{ $initial }}</span>
            <span class="text-start d-none d-sm-block lh-sm">
                <span class="d-block fw-semibold">{{ $headerUser->name }}</span>
                <small class="text-muted">{{ $headerUser->role?->label }}</small>
            </span>
            <i class="bi bi-chevron-down small text-muted"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="{{ route('account.password') }}"><i class="bi bi-key me-2"></i>Change password</a></li>
            <li><hr class="dropdown-divider"></li>
            <li>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="dropdown-item"><i class="bi bi-box-arrow-right me-2"></i>Sign out</button>
                </form>
            </li>
        </ul>
    </div>
</header>
