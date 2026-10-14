<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="{{ asset('assets/js/theme-init.js') }}"></script>
    <title>@yield('title', 'Sign in') · {{ $companyName }}</title>
    @include('partials.assets-css')
    <link href="{{ asset('assets/css/app.css') }}" rel="stylesheet">
</head>
<body>
<div class="guest-wrap">
    <div class="card guest-card shadow">
        <div class="card-body p-4 p-sm-5">
            <div class="text-center mb-4">
                <span class="brand-mark mb-3"><i class="bi bi-wallet2"></i></span>
                <h1 class="h5 mb-0">{{ $companyName }}</h1>
                <div class="text-muted small">Payroll Management System</div>
            </div>
            @yield('content')
        </div>
    </div>
</div>
@include('partials.assets-js')
</body>
</html>
