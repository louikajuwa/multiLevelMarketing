@php
    $navItem = function (string $routeName, string $label) {
        $active = request()->routeIs($routeName) || request()->routeIs($routeName.'.*');
        $base = 'flex items-center gap-2 px-3 py-2 rounded-md transition';
        $cls = $active
            ? 'bg-[color:var(--c-accent-50)] text-[color:var(--c-accent-700)] font-semibold'
            : 'text-[color:var(--c-neutral-600)] hover:bg-[color:var(--c-neutral-100)]';
        return [$base.' '.$cls, \Illuminate\Support\Facades\Route::has($routeName) ? route($routeName) : '#'];
    };
@endphp

@php([$cls, $href] = $navItem('dashboard', 'Dashboard'))
<a href="{{ $href }}" class="{{ $cls }}">Dashboard</a>

@canany(['partner_levels.view', 'partners.view', 'products.view', 'orders.view', 'commissions.view', 'settings.view'])
    <div class="pt-3 mt-2 border-t" style="border-color:var(--border)">
        <p class="px-3 pb-1 text-[11px] font-semibold uppercase tracking-wide" style="color:var(--c-neutral-400)">Administration</p>
    </div>
    @can('partner_levels.view')
        @php([$cls, $href] = $navItem('admin.partner-levels', 'Partner Levels'))
        <a href="{{ $href }}" class="{{ $cls }}">Partner Levels</a>
    @endcan
    {{-- Partners, Customers, Products, Orders, Commissions, Tree, Reports, Settings, Audit
         links are appended by their respective phases. --}}
@endcanany

@php([$cls, $href] = $navItem('profile.edit', 'Profile'))
<a href="{{ $href }}" class="{{ $cls }}">Profile</a>
