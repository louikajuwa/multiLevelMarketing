<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ config('app.name') }}</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
</head>
<body class="h-full">
<div class="min-h-full flex" x-data>
    {{-- Sidebar --}}
    <aside id="sidebar"
           class="fixed lg:static inset-y-0 left-0 z-40 w-64 -translate-x-full lg:translate-x-0 transition-transform
                  bg-white border-r flex flex-col" style="border-color: var(--border)">
        <div class="h-16 flex items-center gap-2 px-5 border-b" style="border-color: var(--border)">
            <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-white font-bold"
                  style="background: var(--c-accent-600)">M</span>
            <span class="font-bold">{{ config('app.name') }}</span>
        </div>
        <nav class="flex-1 overflow-y-auto p-3 space-y-1 text-sm">
            @include('layouts.partials.nav')
        </nav>
        <div class="p-3 border-t text-xs" style="border-color: var(--border); color: var(--text-muted)">
            v1.0 · {{ config('app.env') }}
        </div>
    </aside>

    {{-- Main --}}
    <div class="flex-1 flex flex-col min-w-0">
        <header class="h-16 bg-white border-b flex items-center justify-between px-4 lg:px-6" style="border-color: var(--border)">
            <div class="flex items-center gap-3">
                <button id="sidebar-toggle" class="btn btn-ghost lg:hidden" aria-label="Toggle navigation">☰</button>
                <h1 class="font-semibold">@yield('page-title', 'Dashboard')</h1>
            </div>
            <div class="flex items-center gap-3">
                <div class="relative" id="user-menu">
                    <button class="btn btn-secondary" id="user-menu-btn">
                        {{ auth()->user()->name }} ▾
                    </button>
                    <div id="user-menu-dropdown"
                         class="hidden absolute right-0 mt-2 w-44 card shadow-lg py-1 text-sm z-50">
                        <a href="{{ route('profile.edit') }}" class="block px-4 py-2 hover:bg-[color:var(--c-neutral-50)]">Profile</a>
                        <button id="logout-btn" class="w-full text-left px-4 py-2 hover:bg-[color:var(--c-neutral-50)]" style="color: var(--c-danger)">Sign out</button>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1 p-4 lg:p-6 overflow-x-hidden">
            @yield('content')
        </main>
    </div>
</div>

<form id="logout-form" method="POST" action="{{ route('logout') }}" class="hidden">@csrf</form>
<div id="toast-root"></div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="{{ asset('assets/js/core/toast.js') }}"></script>
<script src="{{ asset('assets/js/core/http.js') }}"></script>
<script src="{{ asset('assets/js/core/modal.js') }}"></script>
<script src="{{ asset('assets/js/core/table.js') }}"></script>
<script>
    $(function () {
        $('#sidebar-toggle').on('click', function () {
            $('#sidebar').toggleClass('-translate-x-full');
        });
        $('#user-menu-btn').on('click', function (e) {
            e.stopPropagation();
            $('#user-menu-dropdown').toggleClass('hidden');
        });
        $(document).on('click', function () { $('#user-menu-dropdown').addClass('hidden'); });
        $('#logout-btn').on('click', function () {
            window.http({ method: 'POST', url: '{{ route('logout') }}', quiet: true })
                .done(function (res) { window.location = (res.data && res.data.redirect) || '{{ route('login') }}'; });
        });
    });
</script>
@stack('scripts')
</body>
</html>
