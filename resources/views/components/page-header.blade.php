@props(['title', 'subtitle' => ''])

<div class="flex items-center justify-between gap-4 mb-5">
    <div>
        <h1 class="text-lg font-semibold">{{ $title }}</h1>
        @if ($subtitle)
            <p class="text-sm" style="color: var(--text-muted)">{{ $subtitle }}</p>
        @endif
    </div>
    <div class="flex items-center gap-2">{{ $slot }}</div>
</div>
