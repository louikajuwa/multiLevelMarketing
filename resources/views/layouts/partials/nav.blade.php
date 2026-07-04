@php
    $active = fn (string $r) => request()->routeIs($r) || request()->routeIs($r.'.*');
    $href = fn (string $r) => \Illuminate\Support\Facades\Route::has($r) ? route($r) : '#';
@endphp

<a href="{{ $href('dashboard') }}" class="nav-link {{ $active('dashboard') ? 'active' : '' }}">Dashboard</a>

@canany(['partners.view', 'partner_levels.view', 'products.view', 'orders.view', 'commissions.view', 'settings.view'])
    <div class="nav-section">Administration</div>
    @can('partners.view')
        <a href="{{ $href('admin.partners') }}" class="nav-link {{ $active('admin.partners') ? 'active' : '' }}">Partners</a>
    @endcan
    @can('partner_levels.view')
        <a href="{{ $href('admin.partner-levels') }}" class="nav-link {{ $active('admin.partner-levels') ? 'active' : '' }}">Partner Levels</a>
    @endcan
    {{-- Customers, Products, Orders, Commissions, Tree, Reports, Settings, Audit appended by later phases. --}}
@endcanany

<div class="nav-section">Account</div>
<a href="{{ $href('profile.edit') }}" class="nav-link {{ $active('profile.edit') ? 'active' : '' }}">Profile</a>
