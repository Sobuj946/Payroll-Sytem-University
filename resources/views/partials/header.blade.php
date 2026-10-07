@php
    $headerUser = auth()->user();
    $initial = strtoupper(mb_substr($headerUser->name, 0, 1));
    $themes = ['blue' => '#2f6fed', 'teal' => '#0d9488', 'green' => '#16a34a', 'purple' => '#7c3aed', 'maroon' => '#be123c', 'orange' => '#ea580c'];
@endphp

<header class="app-header">
    <button class="btn btn-outline-secondary btn-sm d-lg-none" type="button"
            data-bs-toggle="offcanvas" data-bs-target="#appSidebar" aria-controls="appSidebar" aria-label="Open menu">
        <i class="bi bi-list"></i>
    </button>

    <h1 class="page-title">@yield('title', 'Dashboard')</h1>

    <div class="ms-auto d-flex align-items-center gap-3">
        <div class="dropdown">
            <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="dropdown"
                    data-bs-auto-close="outside" aria-expanded="false" aria-label="Appearance" title="Appearance">
                <i class="bi bi-palette"></i>
            </button>
            <div class="dropdown-menu dropdown-menu-end p-3" style="min-width: 230px;">
                <div class="small text-muted mb-2">Colour theme</div>
                <div class="d-flex gap-2 flex-wrap mb-3">
                    @foreach ($themes as $key => $hex)
                        <button type="button" class="theme-swatch" data-theme-choice="{{ $key }}"
                                title="{{ ucfirst($key) }}" aria-label="{{ ucfirst($key) }} theme" style="background: {{ $hex }};"></button>
                    @endforeach
                </div>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" role="switch" id="darkModeSwitch">
                    <label class="form-check-label" for="darkModeSwitch">Dark mode</label>
                </div>
            </div>
        </div>

        <div class="dropdown">
            <button class="btn btn-link text-body text-decoration-none d-flex align-items-center gap-2 p-0"
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
    </div>
</header>
