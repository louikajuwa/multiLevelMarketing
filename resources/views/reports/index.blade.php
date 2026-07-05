@extends('layouts.app')
@section('title', 'Reports')
@section('page-title', 'Reports')

@section('content')
<x-page-header title="Reports" subtitle="Export data to Excel, CSV or PDF. Date range applies to sales, orders and commissions." />

<div class="card card-pad mb-5" style="max-width:520px">
    <div class="form-grid">
        <div class="field"><label class="label" for="r-from">From</label><input class="input" id="r-from" type="date"></div>
        <div class="field"><label class="label" for="r-to">To</label><input class="input" id="r-to" type="date"></div>
    </div>
    <p class="help-text">Leave blank for all-time.</p>
</div>

@php
    $meta = [
        'sales' => ['Sales', 'Paid orders and their totals'],
        'commissions' => ['Commissions', 'Direct, sponsor bonus and adjustments'],
        'orders' => ['Orders', 'All orders and statuses'],
        'partners' => ['Partners', 'Network partners and their downline'],
        'customers' => ['Customers', 'Customers and their discounts'],
        'inventory' => ['Inventory', 'Product stock and pricing'],
    ];
@endphp

<div class="grid gap-4" style="grid-template-columns:repeat(auto-fill,minmax(300px,1fr))">
    @foreach ($types as $type)
        <div class="card card-pad">
            <div class="card-title">{{ $meta[$type][0] ?? ucfirst($type) }}</div>
            <p class="text-sm mb-3" style="color:var(--text-muted)">{{ $meta[$type][1] ?? '' }}</p>
            <div class="flex gap-2">
                <button class="btn btn-secondary btn-sm export-btn" data-type="{{ $type }}" data-format="xlsx">Excel</button>
                <button class="btn btn-secondary btn-sm export-btn" data-type="{{ $type }}" data-format="csv">CSV</button>
                <button class="btn btn-secondary btn-sm export-btn" data-type="{{ $type }}" data-format="pdf">PDF</button>
            </div>
        </div>
    @endforeach
</div>
@endsection

@push('scripts')
<script>
$(function () {
    const exportUrl = "{{ route('reports.export') }}";
    $('.export-btn').on('click', function () {
        const params = new URLSearchParams({ type: $(this).data('type'), format: $(this).data('format') });
        const from = $('#r-from').val(), to = $('#r-to').val();
        if (from) params.set('from', from);
        if (to) params.set('to', to);
        window.location = exportUrl + '?' + params.toString();
    });
});
</script>
@endpush
