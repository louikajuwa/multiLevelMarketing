@extends('layouts.guest')
@section('title', 'Set new password')

@section('content')
    <h1 class="text-lg font-semibold mb-1">Set a new password</h1>
    <p class="text-sm mb-5" style="color: var(--text-muted)">Choose a strong password for your account.</p>

    <form id="reset-form" method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div class="field">
            <label class="label" for="email">Email</label>
            <input class="input" type="email" id="email" name="email" value="{{ $email }}" required>
        </div>
        <div class="field">
            <label class="label" for="password">New password</label>
            <input class="input" type="password" id="password" name="password" autocomplete="new-password" required>
        </div>
        <div class="field">
            <label class="label" for="password_confirmation">Confirm password</label>
            <input class="input" type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Reset password</button>
    </form>
@endsection

@push('scripts')
<script>
    $(function () { window.ajaxForm('#reset-form'); });
</script>
@endpush
