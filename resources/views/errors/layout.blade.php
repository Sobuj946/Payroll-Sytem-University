<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="{{ asset('assets/js/theme-init.js') }}"></script>
    <title>@yield('code') · @yield('heading')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('assets/css/app.css') }}" rel="stylesheet">
</head>
<body>
<div class="d-flex align-items-center justify-content-center min-vh-100 p-3">
    <div class="text-center" style="max-width: 460px;">
        <div class="display-1 fw-bold text-primary">@yield('code')</div>
        <h1 class="h4 mb-2">@yield('heading')</h1>
        <p class="text-muted mb-4">@yield('message')</p>
        <a href="{{ url('/dashboard') }}" class="btn btn-primary"><i class="bi bi-house me-1"></i>Go to dashboard</a>
        <a href="javascript:history.back()" class="btn btn-light ms-1">Go back</a>
    </div>
</div>
</body>
</html>
