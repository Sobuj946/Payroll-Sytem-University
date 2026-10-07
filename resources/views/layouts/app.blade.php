<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="{{ asset('assets/js/theme-init.js') }}"></script>
    <title>@yield('title', 'Dashboard') · {{ $companyName }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('assets/css/app.css') }}" rel="stylesheet">
    @stack('styles')
</head>
<body>
<div class="app-shell">
    @include('partials.sidebar')

    <div class="app-main">
        @include('partials.header')

        <main class="app-content">
            @yield('content')
        </main>

        <footer class="app-footer">
            &copy; {{ date('Y') }} {{ $companyName }} &middot; Payroll Management System
        </footer>
    </div>
</div>

@include('partials.toasts')
@include('partials.confirm-modal')

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('assets/js/app.js') }}"></script>
@stack('scripts')
</body>
</html>
