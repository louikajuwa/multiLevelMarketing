@extends('layouts.app')
@section('title', 'Profile')
@section('page-title', 'Profile')

@section('content')
<div class="content-narrow grid gap-5">
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">Profile information</div>
                <div class="card-sub">Update your account's name and contact details.</div>
            </div>
        </div>
        <form id="profile-form" data-busy="Saving…" method="POST" action="{{ route('profile.update') }}" data-method="PUT">
            @csrf
            <div class="card-pad">
                <div class="field">
                    <label class="label" for="name">Name</label>
                    <input class="input" id="name" name="name" value="{{ $user->name }}" required>
                </div>
                <div class="form-grid">
                    <div class="field">
                        <label class="label" for="email">Email</label>
                        <input class="input" type="email" id="email" name="email" value="{{ $user->email }}" required>
                    </div>
                    <div class="field">
                        <label class="label" for="phone">Phone</label>
                        <input class="input" id="phone" name="phone" value="{{ $user->phone }}" placeholder="Optional">
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="border-radius:0 0 var(--radius) var(--radius)">
                <button type="submit" class="btn btn-primary">Save changes</button>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">Change password</div>
                <div class="card-sub">Use a strong password you don't reuse elsewhere.</div>
            </div>
        </div>
        <form id="password-form" data-busy="Updating…" method="POST" action="{{ route('profile.password') }}" data-method="PUT">
            @csrf
            <div class="card-pad">
                <div class="field">
                    <label class="label" for="current_password">Current password</label>
                    <input class="input" type="password" id="current_password" name="current_password" required>
                </div>
                <div class="form-grid">
                    <div class="field">
                        <label class="label" for="new_password">New password</label>
                        <input class="input" type="password" id="new_password" name="password" required>
                    </div>
                    <div class="field">
                        <label class="label" for="password_confirmation">Confirm new password</label>
                        <input class="input" type="password" id="password_confirmation" name="password_confirmation" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="border-radius:0 0 var(--radius) var(--radius)">
                <button type="submit" class="btn btn-primary">Update password</button>
            </div>
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
