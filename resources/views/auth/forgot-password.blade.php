@extends('layouts.guest')

@section('title', 'Forgot password')

@section('content')
    <p class="text-muted small">Enter your email address and we will send you a link to choose a new password.</p>

    @if (session('status'))
        <div class="alert alert-success py-2">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div class="mb-4">
            <label for="email" class="form-label">Email address</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}"
                   class="form-control @error('email') is-invalid @enderror" required autofocus>
            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <button type="submit" class="btn btn-primary w-100">Send reset link</button>
        <div class="text-center mt-3"><a href="{{ route('login') }}" class="small text-decoration-none">Back to sign in</a></div>
    </form>
@endsection
