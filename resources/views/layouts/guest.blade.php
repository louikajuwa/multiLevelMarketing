@php($asset = fn ($p) => asset($p).'?v='.(@filemtime(public_path($p)) ?: config('app.version', '1')))
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Sign in') · {{ config('app.name') }}</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet">
    <link rel="stylesheet" href="{{ $asset('assets/css/app.css') }}">
</head>
<body class="h-full">
    <div class="min-h-full flex flex-col items-center justify-center px-4 py-12">
        <div class="w-full max-w-md">
            <div class="text-center mb-6">
                <div class="inline-flex items-center gap-2 text-[color:var(--c-neutral-900)] font-bold text-xl">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-white"
                          style="background: var(--c-accent-600)">M</span>
                    {{ config('app.name') }}
                </div>
            </div>
            <div class="card card-pad">
                @yield('content')
            </div>
            <p class="text-center text-xs mt-6" style="color: var(--text-muted)">
                &copy; {{ date('Y') }} {{ config('app.name') }}
            </p>
        </div>
    </div>

    <div id="toast-root"></div>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="{{ $asset('assets/js/core/util.js') }}"></script>
    <script src="{{ $asset('assets/js/core/toast.js') }}"></script>
    <script src="{{ $asset('assets/js/core/http.js') }}"></script>
    @stack('scripts')
</body>
</html>
