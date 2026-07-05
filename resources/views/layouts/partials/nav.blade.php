@php
    $active = fn (string $r) => request()->routeIs($r) || request()->routeIs($r.'.*');
    $href = fn (string $r) => \Illuminate\Support\Facades\Route::has($r) ? route($r) : '#';
@endphp

<a href="{{ $href('dashboard') }}" class="nav-link {{ $active('dashboard') ? 'active' : '' }}">Dashboard</a>

@canany(['partners.view', 'partner_levels.view', 'customers.view', 'products.view', 'orders.view', 'commissions.view', 'settings.view'])
    <div class="nav-section">Manage</div>
    @can('partners.view')
        <a href="{{ $href('admin.partners.index') }}" class="nav-link {{ $active('admin.partners') ? 'active' : '' }}">Partners</a>
    @endcan
    @can('customers.view')
        <a href="{{ $href('customers.index') }}" class="nav-link {{ $active('customers') ? 'active' : '' }}">Customers</a>
    @endcan
    @can('orders.view')
        <a href="{{ $href('orders.index') }}" class="nav-link {{ $active('orders') ? 'active' : '' }}">Orders</a>
    @endcan
    @can('commissions.view')
        <a href="{{ $href('commissions.index') }}" class="nav-link {{ $active('commissions') ? 'active' : '' }}">Commissions</a>
    @endcan
    @can('products.view')
        <a href="{{ $href('admin.products.index') }}" class="nav-link {{ $active('admin.products') ? 'active' : '' }}">Products</a>
    @endcan
    @can('categories.view')
        <a href="{{ $href('admin.categories.index') }}" class="nav-link {{ $active('admin.categories') ? 'active' : '' }}">Categories</a>
    @endcan
    @can('brands.view')
        <a href="{{ $href('admin.brands.index') }}" class="nav-link {{ $active('admin.brands') ? 'active' : '' }}">Brands</a>
    @endcan
    @can('partner_levels.view')
        <a href="{{ $href('admin.partner-levels.index') }}" class="nav-link {{ $active('admin.partner-levels') ? 'active' : '' }}">Partner Levels</a>
    @endcan
    {{-- Customers, Products, Orders, Commissions, Tree, Reports, Settings, Audit appended by later phases. --}}
@endcanany

<div class="nav-section">Account</div>
<a href="{{ $href('profile.edit') }}" class="nav-link {{ $active('profile.edit') ? 'active' : '' }}">Profile</a>
