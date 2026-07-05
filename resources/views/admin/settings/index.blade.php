@extends('layouts.app')
@section('title', 'Settings')
@section('page-title', 'Settings')

@section('content')
@php $s = fn ($k, $d = '') => $settings[$k] ?? $d; @endphp
<div class="content-narrow grid gap-5">
    <form id="settings-form" method="POST" action="{{ route('admin.settings.update') }}" data-method="PUT">
        @csrf
        <div class="card">
            <div class="card-header"><div><div class="card-title">Company</div><div class="card-sub">Branding and currency.</div></div></div>
            <div class="card-pad">
                <div class="field"><label class="label" for="company_name">Company name</label><input class="input" id="company_name" name="company_name" value="{{ $s('company_name') }}"></div>
                <div class="form-grid">
                    <div class="field"><label class="label" for="currency">Currency</label><input class="input" id="currency" name="currency" value="{{ $s('currency', 'USD') }}"></div>
                    <div class="field"><label class="label" for="currency_symbol">Symbol</label><input class="input" id="currency_symbol" name="currency_symbol" value="{{ $s('currency_symbol', '$') }}"></div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><div><div class="card-title">Financial defaults</div><div class="card-sub">Used when a partner level leaves a value blank.</div></div></div>
            <div class="card-pad">
                <div class="form-grid">
                    <div class="field"><label class="label" for="tax_percent">Tax %</label><input class="input" id="tax_percent" name="tax_percent" type="number" step="0.01" value="{{ $s('tax_percent', '0') }}"></div>
                    <div class="field"><label class="label" for="default_partner_discount">Default partner discount %</label><input class="input" id="default_partner_discount" name="default_partner_discount" type="number" step="0.01" value="{{ $s('default_partner_discount', '5') }}"></div>
                    <div class="field"><label class="label" for="default_commission_percent">Default commission %</label><input class="input" id="default_commission_percent" name="default_commission_percent" type="number" step="0.01" value="{{ $s('default_commission_percent', '10') }}"></div>
                    <div class="field"><label class="label" for="default_sponsor_bonus_percent">Default sponsor bonus %</label><input class="input" id="default_sponsor_bonus_percent" name="default_sponsor_bonus_percent" type="number" step="0.01" value="{{ $s('default_sponsor_bonus_percent', '5') }}"></div>
                    <div class="field"><label class="label" for="max_partner_depth">Max partner depth</label><input class="input" id="max_partner_depth" name="max_partner_depth" type="number" value="{{ $s('max_partner_depth', '4') }}"></div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><div><div class="card-title">Email</div><div class="card-sub">SMTP — works with SendGrid or any provider. Leave password blank to keep the current one.</div></div></div>
            <div class="card-pad">
                <div class="form-grid">
                    <div class="field"><label class="label" for="mail_host">Host</label><input class="input" id="mail_host" name="mail_host" value="{{ $s('mail_host') }}" placeholder="smtp.sendgrid.net"></div>
                    <div class="field"><label class="label" for="mail_port">Port</label><input class="input" id="mail_port" name="mail_port" type="number" value="{{ $s('mail_port') }}" placeholder="587"></div>
                    <div class="field"><label class="label" for="mail_username">Username</label><input class="input" id="mail_username" name="mail_username" value="{{ $s('mail_username') }}" placeholder="apikey"></div>
                    <div class="field"><label class="label" for="mail_password">Password / API key</label><input class="input" id="mail_password" name="mail_password" type="password" placeholder="••••••••"></div>
                    <div class="field"><label class="label" for="mail_encryption">Encryption</label><input class="input" id="mail_encryption" name="mail_encryption" value="{{ $s('mail_encryption') }}" placeholder="tls"></div>
                    <div class="field"><label class="label" for="mail_from_address">From address</label><input class="input" id="mail_from_address" name="mail_from_address" value="{{ $s('mail_from_address') }}"></div>
                    <div class="field"><label class="label" for="mail_from_name">From name</label><input class="input" id="mail_from_name" name="mail_from_name" value="{{ $s('mail_from_name') }}"></div>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" id="test-email">Send test email</button>
            </div>
            <div class="modal-footer" style="border-radius:0 0 var(--radius) var(--radius)">
                <button type="submit" class="btn btn-primary">Save settings</button>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
$(function () {
    window.ajaxForm('#settings-form', function () {});
    $('#test-email').on('click', function () {
        window.http({ method: 'POST', url: "{{ route('admin.settings.test-email') }}", button: this });
    });
});
</script>
@endpush
