@props(['type' => null, 'status' => null])
@php
    // Single source of truth for status -> badge colour (mirrors window.badgeType in util.js).
    $map = [
        'pending' => 'warning', 'paid' => 'success', 'processing' => 'info', 'shipped' => 'info',
        'completed' => 'success', 'cancelled' => 'danger', 'voided' => 'neutral', 'void' => 'neutral',
        'active' => 'success', 'inactive' => 'neutral', 'suspended' => 'danger',
    ];
    $type = $type ?? ($status !== null ? ($map[strtolower((string) $status)] ?? 'neutral') : 'neutral');
@endphp
<span {{ $attributes->merge(['class' => 'badge badge-'.$type]) }}>{{ $slot->isEmpty() && $status !== null ? $status : $slot }}</span>
