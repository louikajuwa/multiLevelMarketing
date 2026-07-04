@props(['id', 'title' => ''])

<div id="{{ $id }}" class="modal-root hidden" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="modal-overlay"></div>
    <div class="modal-panel">
        <div class="modal-header">
            <h3 class="font-semibold">{{ $title }}</h3>
            <button type="button" class="btn btn-ghost" data-modal-close aria-label="Close">✕</button>
        </div>
        {{ $slot }}
    </div>
</div>
