@extends('layouts.guest')
@section('title', 'Sign in')

@section('content')
    <h1 class="text-lg font-semibold mb-1">Sign in to your account</h1>
    <p class="text-sm mb-5" style="color: var(--text-muted)">Welcome back. Enter your details to continue.</p>

    <form id="login-form" data-busy="Signing in…" method="POST" action="{{ route('login.attempt') }}">
        @csrf
        <div class="field">
            <label class="label" for="email">Email</label>
            <input class="input" type="email" id="email" name="email" autocomplete="email" required autofocus>
        </div>
        <div class="field">
            <label class="label" for="password">Password</label>
            <input class="input" type="password" id="password" name="password" autocomplete="current-password" required>
        </div>
        <div class="flex items-center justify-between mb-5">
            <label class="inline-flex items-center gap-2 text-sm" style="color: var(--c-neutral-700)">
                <input type="checkbox" name="remember" value="1"> Remember me
            </label>
            <a class="text-sm font-medium" style="color: var(--c-accent-600)" href="{{ route('password.request') }}">Forgot password?</a>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Sign in</button>
    </form>
@endsection

@push('scripts')
<script>
    $(function () { window.ajaxForm('#login-form'); });
</script>
@endpush
