@extends('layouts.guest')

@section('title', 'Sign in')

@section('content')
    @if (session('status'))
        <div class="alert alert-success py-2">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger py-2">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="mb-3">
            <label for="email" class="form-label">Email address</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}"
                   class="form-control @error('email') is-invalid @enderror" autocomplete="username" required autofocus>
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input type="password" id="password" name="password"
                   class="form-control @error('password') is-invalid @enderror" autocomplete="current-password" required>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="remember" name="remember">
                <label class="form-check-label" for="remember">Remember me</label>
            </div>
            <a href="{{ route('password.request') }}" class="small text-decoration-none">Forgot password?</a>
        </div>

        <button type="submit" class="btn btn-primary w-100">Sign in</button>
    </form>

    @if (app()->environment('local'))
        <div class="border-top mt-4 pt-3 small text-muted">
            <div class="fw-semibold mb-1">Demo accounts (password: Password@123)</div>
            admin@padmatech.test<br>
            nusrat.jahan@padmatech.test<br>
            farhana.akter@padmatech.test<br>
            tanvir.ahmed@padmatech.test
        </div>
    @endif
@endsection
