@extends('layouts.app')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
<div class="mb-5">
    <h1 class="text-lg font-semibold">Welcome back, {{ $user->name }}</h1>
    <p class="text-sm" style="color:var(--text-muted)">{{ ucfirst($role) }} overview</p>
</div>

{{-- KPI strip --}}
<div class="grid gap-4 mb-5" style="grid-template-columns:repeat(auto-fit,minmax(min(150px,100%),1fr))">
    @foreach ($kpis as $kpi)
        <div class="card stat">
            <span class="stat-label">{{ $kpi['label'] }}</span>
            <span class="stat-value num">{{ $kpi['value'] }}</span>
        </div>
    @endforeach
</div>

<div class="grid gap-5 {{ $chart ? 'dash-split' : '' }}">
    @if ($chart)
        <div class="card">
            <div class="card-header"><div class="card-title">{{ $role === 'partner' ? 'My sales' : 'Revenue' }} — last 6 months</div></div>
            <div class="card-pad"><canvas id="revChart" height="120" role="img" aria-label="Revenue bar chart for the last 6 months"></canvas></div>
        </div>
    @endif

    <div class="card">
        <div class="card-header"><div class="card-title">Recent orders</div></div>
        <div class="table-scroll">
            <table class="data-table">
                <thead><tr><th class="col-grow">Order</th><th>Customer</th><th>Total</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($recentOrders as $o)
                        <tr>
                            <td class="col-grow font-medium">{{ $o['number'] }}</td>
                            <td>{{ $o['customer'] }}</td>
                            <td class="num">{{ $o['total'] }}</td>
                            <td><x-badge :status="$o['status']">{{ $o['status'] }}</x-badge></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="p-6 text-center" style="color:var(--text-muted)">No orders yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if (! empty($secondary) || $topPartners->isNotEmpty())
<div class="grid gap-5 mt-5" style="grid-template-columns:repeat(auto-fit,minmax(280px,1fr))">
    @if (! empty($secondary))
        <div class="card card-pad">
            <div class="card-title mb-3">At a glance</div>
            <div class="grid gap-2 text-sm">
                @foreach ($secondary as $label => $val)
                    <div class="flex justify-between border-b pb-2" style="border-color:var(--c-neutral-100)">
                        <span style="color:var(--text-muted)">{{ $label }}</span><span class="font-semibold num">{{ $val }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
    @if ($topPartners->isNotEmpty())
        <div class="card card-pad">
            <div class="card-title mb-3">Top partners by earnings</div>
            <div class="grid gap-2 text-sm">
                @foreach ($topPartners as $p)
                    <div class="flex justify-between border-b pb-2" style="border-color:var(--c-neutral-100)">
                        <span>{{ $p['name'] }}</span><span class="font-semibold num">{{ $p['value'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endif
@endsection

@push('scripts')
@if ($chart)
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>
<script>
$(function () {
    const ctx = document.getElementById('revChart');
    if (!ctx || !window.Chart) return;
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: @json($chart['labels']),
            datasets: [{ label: 'Revenue', data: @json($chart['values']),
                backgroundColor: 'rgba(79,70,229,.85)', borderRadius: 6, maxBarThickness: 42 }],
        },
        options: {
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { callback: v => '$' + v } }, x: { grid: { display: false } } },
            responsive: true, maintainAspectRatio: false,
        },
    });
});
</script>
@endif
@endpush
