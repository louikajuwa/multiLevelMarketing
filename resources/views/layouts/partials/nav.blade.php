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

{{-- Role-specific navigation is appended by later phases (partners, customers,
     products, orders, commissions, tree, reports, settings, audit). --}}

@php([$cls, $href] = $navItem('profile.edit', 'Profile'))
<a href="{{ $href }}" class="{{ $cls }}">Profile</a>
