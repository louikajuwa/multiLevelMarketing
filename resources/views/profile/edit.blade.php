@extends('layouts.app')
@section('title', 'Profile')
@section('page-title', 'Profile')

@section('content')
<div class="grid gap-6 max-w-2xl">
    <div class="card card-pad">
        <h2 class="text-base font-semibold mb-4">Profile information</h2>
        <form id="profile-form" method="POST" action="{{ route('profile.update') }}" data-method="PUT">
            @csrf
            <div class="field">
                <label class="label" for="name">Name</label>
                <input class="input" id="name" name="name" value="{{ $user->name }}" required>
            </div>
            <div class="field">
                <label class="label" for="email">Email</label>
                <input class="input" type="email" id="email" name="email" value="{{ $user->email }}" required>
            </div>
            <div class="field">
                <label class="label" for="phone">Phone</label>
                <input class="input" id="phone" name="phone" value="{{ $user->phone }}">
            </div>
            <button type="submit" class="btn btn-primary">Save changes</button>
        </form>
    </div>

    <div class="card card-pad">
        <h2 class="text-base font-semibold mb-4">Change password</h2>
        <form id="password-form" method="POST" action="{{ route('profile.password') }}" data-method="PUT">
            @csrf
            <div class="field">
                <label class="label" for="current_password">Current password</label>
                <input class="input" type="password" id="current_password" name="current_password" required>
            </div>
            <div class="field">
                <label class="label" for="new_password">New password</label>
                <input class="input" type="password" id="new_password" name="password" required>
            </div>
            <div class="field">
                <label class="label" for="password_confirmation">Confirm new password</label>
                <input class="input" type="password" id="password_confirmation" name="password_confirmation" required>
            </div>
            <button type="submit" class="btn btn-primary">Update password</button>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(function () {
        window.ajaxForm('#profile-form', function () {});
        window.ajaxForm('#password-form', function () { $('#password-form')[0].reset(); });
    });
</script>
@endpush
