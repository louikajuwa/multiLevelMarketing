@props(['title', 'subtitle' => ''])

<div class="page-header">
    <div>
        <h1 class="text-lg font-semibold">{{ $title }}</h1>
        @if ($subtitle)
            <p class="text-sm" style="color: var(--text-muted)">{{ $subtitle }}</p>
        @endif
    </div>
    <div class="page-header-actions">{{ $slot }}</div>
</div>
