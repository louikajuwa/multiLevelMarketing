@extends('layouts.app')
@section('title', 'Commissions')
@section('page-title', 'Commissions')
@section('main-mode', 'fill')

@section('content')
<x-page-header title="Commissions" subtitle="Earned on paid orders (direct + sponsor bonus). Reversals are manual adjustments.">
    @can('commissions.adjust')<button class="btn btn-primary" id="btn-adjust">Adjust</button>@endcan
</x-page-header>

<div class="card card-pad stat" style="margin-bottom:1rem;max-width:280px">
    <span class="stat-label">Total earnings{{ $isStaff ? '' : ' (you)' }}</span>
    <span class="stat-value num" id="earnings">$0.00</span>
</div>

<div class="card fill">
    <div class="card-pad flex flex-wrap items-center gap-3 border-b" style="border-color:var(--border)">
        <input id="dt-search" class="input" style="max-width:240px" placeholder="Search partner…">
        <select id="dt-type" class="select" style="max-width:160px">
            <option value="">All types</option><option value="direct">Direct</option><option value="sponsor_bonus">Sponsor bonus</option><option value="adjustment">Adjustment</option>
        </select>
    </div>
    <div id="dt" class="table-scroll">
        <table class="data-table">
            <thead><tr><th class="col-grow">Partner</th><th>Type</th><th>Order</th><th data-dt-sort="amount">Amount</th><th>%</th><th>Status</th><th>Date</th></tr></thead>
            <tbody data-dt-body></tbody>
        </table>
        <div data-dt-empty class="hidden"><x-empty-state title="No commissions yet" message="Mark an order as paid to generate commissions." /></div>
        <div class="card-pad" data-dt-pagination></div>
    </div>
</div>

@can('commissions.adjust')
<x-modal id="adjust-modal" title="Commission adjustment">
    <form id="adjust-form">
        <div class="modal-body">
            <div class="field"><label class="label" for="a-partner">Partner</label>
                <select class="select" id="a-partner" name="partner_id" required>
                    <option value="">Select…</option>
                    @foreach ($partners as $p)<option value="{{ $p['id'] }}">{{ $p['name'] }}</option>@endforeach
                </select>
            </div>
            <div class="field"><label class="label" for="a-amount">Amount (negative to reverse)</label>
                <input class="input" id="a-amount" name="amount" type="number" step="0.01" required></div>
            <div class="field"><label class="label" for="a-note">Reason</label><input class="input" id="a-note" name="note" required></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
            <button type="submit" class="btn btn-primary">Record</button>
        </div>
    </form>
</x-modal>
@endcan
@endsection

@push('scripts')
<script>
$(function () {
    const listUrl = "{{ route('commissions.index') }}";
    const esc = (s) => $('<div>').text(s == null ? '' : s).html();
    const money = (v) => (v < 0 ? '-$' : '$') + Math.abs(Number(v || 0)).toFixed(2);
    const typeBadge = { direct:'success', sponsor_bonus:'info', adjustment:'warning' };

    const dt = new DataTable({
        url: listUrl, mount: '#dt', defaultSort: 'id', defaultDir: 'desc',
        searchInput: '#dt-search', filters: { '#dt-type': 'type' },
        row: (c) => `<tr>
            <td class="col-grow font-medium">${esc(c.partner && c.partner.user ? c.partner.user.name : '—')}</td>
            <td><span class="badge badge-${typeBadge[c.type]||'neutral'}">${esc(c.type)}</span></td>
            <td style="color:var(--text-muted)">${esc(c.order ? c.order.order_number : '—')}</td>
            <td class="num" style="${c.amount<0?'color:var(--c-danger)':''}">${money(c.amount)}</td>
            <td class="num">${c.percent > 0 ? Number(c.percent).toFixed(2)+'%' : '—'}</td>
            <td><span class="badge badge-${c.status==='paid'?'success':c.status==='void'?'danger':'neutral'}">${esc(c.status)}</span></td>
            <td class="num" style="color:var(--text-muted)">${c.created_at ? c.created_at.substring(0,10) : '—'}</td>
        </tr>`,
    });
    function refresh() { dt.reload().done(r => { if (r.meta) $('#earnings').text(money(r.meta.earnings)); }); }
    refresh();

    @can('commissions.adjust')
    $('#btn-adjust').on('click', function () { $('#adjust-form')[0].reset(); Modal.open('adjust-modal'); });
    $('#adjust-form').on('submit', function (e) {
        e.preventDefault(); const $f = $(this);
        window.http({ method: 'POST', url: "{{ route('commissions.adjust') }}", data: $f.serialize(), form: $f, button: $f.find('[type=submit]') })
            .done(() => { Modal.close('adjust-modal'); refresh(); });
    });
    @endcan
});
</script>
@endpush
