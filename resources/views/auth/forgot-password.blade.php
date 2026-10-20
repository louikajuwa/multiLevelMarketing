@extends('layouts.guest')
@section('title', 'Forgot password')

@section('content')
    <h1 class="text-lg font-semibold mb-1">Reset your password</h1>
    <p class="text-sm mb-5" style="color: var(--text-muted)">Enter your email and we'll send a reset link.</p>

    <form id="forgot-form" data-busy="Sending…" method="POST" action="{{ route('password.email') }}">
        @csrf
        <div class="field">
            <label class="label" for="email">Email</label>
            <input class="input" type="email" id="email" name="email" required autofocus>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Email reset link</button>
    </form>
    <p class="text-center text-sm mt-4">
        <a style="color: var(--c-accent-600)" href="{{ route('login') }}">Back to sign in</a>
    </p>
@endsection

@push('scripts')
<script>
    $(function () { window.ajaxForm('#forgot-form', function () {
        $('#forgot-form')[0].reset();
    }); });
</script>
@endpush
