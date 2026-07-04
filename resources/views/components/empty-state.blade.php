@props(['title' => 'Nothing here yet', 'message' => ''])

<div class="empty-state">
    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M8 4v16"/>
    </svg>
    <p class="font-semibold" style="color: var(--c-neutral-700)">{{ $title }}</p>
    @if ($message)<p class="text-sm mt-1">{{ $message }}</p>@endif
    @if (trim($slot)) <div class="mt-4">{{ $slot }}</div> @endif
</div>
