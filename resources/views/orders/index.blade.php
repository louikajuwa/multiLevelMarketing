@extends('layouts.app')
@section('title', 'Orders')
@section('page-title', 'Orders')
@section('main-mode', 'fill')

@section('content')
<x-page-header title="Orders" subtitle="Place orders for customers; discount is resolved automatically from the partner + customer.">
    @can('orders.create')<button class="btn btn-primary" id="btn-new">+ New order</button>@endcan
</x-page-header>

<div class="card fill">
    <div class="card-pad flex flex-wrap items-center gap-3 border-b filter-bar" style="border-color:var(--border)">
        <label for="dt-search" class="sr-only">Search orders</label>
        <input id="dt-search" class="input" style="max-width:260px" placeholder="Search order # or customer…">
        <label for="dt-status" class="sr-only">Filter by status</label>
        <select id="dt-status" class="select" style="max-width:160px">
            <option value="">All statuses</option>
            @foreach ($statuses as $s)<option value="{{ $s }}">{{ ucfirst($s) }}</option>@endforeach
        </select>
    </div>
    <div id="dt" class="table-scroll">
        <table class="data-table">
            <thead><tr>
                <th class="col-grow">Order</th><th>Customer</th><th>Partner</th>
                <th data-dt-sort="grand_total">Total</th><th data-dt-sort="status">Status</th><th>Placed</th><th class="text-right">Actions</th>
            </tr></thead>
            <tbody data-dt-body></tbody>
        </table>
        <div data-dt-empty class="hidden"><x-empty-state title="No orders yet" message="Place your first order." /></div>
        <div class="card-pad" data-dt-pagination></div>
    </div>
</div>

<x-modal id="order-modal" title="New order">
    <form id="order-form">
        <div class="modal-body">
            @if ($canPickCustomer)
                <div class="field"><label class="label" for="o-customer-search">Customer</label>
                    <input class="input" id="o-customer-search" placeholder="Search customer…" autocomplete="off">
                    <input type="hidden" id="o-customer" name="customer_id" required>
                </div>
            @else
                <input type="hidden" name="customer_id" value="{{ $selfCustomerId }}">
            @endif

            <span class="label" id="o-items-label">Items</span>
            <div id="o-lines" role="group" aria-labelledby="o-items-label"></div>
            <button type="button" class="btn btn-secondary btn-sm" id="add-line" style="margin-top:.4rem">+ Add item</button>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
            <button type="submit" class="btn btn-primary">Place order</button>
        </div>
    </form>
</x-modal>

<x-modal id="order-view" title="Order detail">
    <div class="modal-body" id="order-view-body"></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-modal-close>Close</button></div>
</x-modal>
@endsection

@push('scripts')
<script>
$(function () {
    const listUrl = "{{ route('orders.index') }}", base = "{{ url('orders') }}";
    const statuses = @json($statuses);
    const canStatus = @json(auth()->user()->can('orders.update_status'));
    const money = (v) => '$' + Number(v || 0).toFixed(2);
    // Mirrors the backend order state machine; terminal states have no next status.
    const NEXT = { pending: ['paid','cancelled'], paid: ['processing','cancelled'], processing: ['shipped','cancelled'], shipped: ['completed'], completed: [], cancelled: [] };
    const badge = (s) => window.badgeType(s);
    let li = 0;

    @if ($canPickCustomer)
    const customerSelect = new SearchableSelect({
        input: '#o-customer-search', hidden: '#o-customer',
        url: "{{ route('orders.customer-options') }}", placeholder: 'Search customer…',
        map: c => ({ id: c.id, label: c.user.name }),
    });
    @endif

    function statusCell(o) {
        const next = NEXT[o.status] || [];
        if (!canStatus || !next.length) return `<span class="badge badge-${badge(o.status)}">${esc(o.status)}</span>`;
        return `<select class="select st-sel" data-id="${esc(o.id)}" data-current="${esc(o.status)}" aria-label="Change status of order ${esc(o.order_number)}" style="height:30px;font-size:12px;width:130px">` +
            [o.status].concat(next).filter(s => statuses.includes(s)).map(s => `<option value="${esc(s)}" ${s===o.status?'selected':''}>${esc(s)}</option>`).join('') + `</select>`;
    }

    const dt = new DataTable({
        url: listUrl, mount: '#dt', defaultSort: 'id', defaultDir: 'desc',
        searchInput: '#dt-search', filters: { '#dt-status': 'status' },
        row: (o) => `<tr>
            <td class="col-grow font-medium">${esc(o.order_number)}</td>
            <td>${esc(o.customer && o.customer.user ? o.customer.user.name : '—')}</td>
            <td>${esc(o.partner && o.partner.user ? o.partner.user.name : '—')}</td>
            <td class="num">${money(o.grand_total)}</td>
            <td>${statusCell(o)}</td>
            <td class="num" style="color:var(--text-muted)">${esc(o.placed_at ? o.placed_at.substring(0,10) : '—')}</td>
            <td class="text-right whitespace-nowrap"><button class="btn btn-ghost btn-view" data-id="${esc(o.id)}">View</button></td>
        </tr>`,
    });
    dt.reload();

    function addLine() {
        li++;
        const idx = li;
        const $line = $(`<div class="flex items-start gap-2 mb-2 o-line">
            <div style="flex:1">
                <input type="text" class="input o-prod-search" aria-label="Product" placeholder="Search product…" autocomplete="off">
                <input type="hidden" name="items[${idx}][product_id]" class="o-prod-id" required>
            </div>
            <input name="items[${idx}][quantity]" aria-label="Quantity" type="number" min="1" value="1" class="input" style="width:90px" required>
            <button type="button" class="btn btn-ghost line-del" aria-label="Remove item">✕</button>
        </div>`);
        $('#o-lines').append($line);
        new SearchableSelect({
            input: $line.find('.o-prod-search'), hidden: $line.find('.o-prod-id'),
            url: "{{ route('orders.product-options') }}", placeholder: 'Search product…',
            map: p => ({ id: p.id, label: p.name + ' — ' + money(p.retail_price) + ' (stock ' + p.stock + ')' }),
        });
    }
    $('#add-line').on('click', addLine);
    $('#o-lines').on('click', '.line-del', function () { $(this).closest('.o-line').remove(); });

    $('#btn-new').on('click', function () {
        $('#order-form')[0].reset(); @if ($canPickCustomer) customerSelect.reset(); @endif
        $('#o-lines').empty(); addLine();
        Modal.open('order-modal');
    });
    $('#order-form').on('submit', function (e) {
        e.preventDefault(); const $f = $(this);
        window.http({ method: 'POST', url: "{{ route('orders.store') }}", data: $f.serialize(), form: $f, button: $f.find('[type=submit]') })
            .done(() => { Modal.close('order-modal'); dt.reload(); });
    });

    // Status change (admin)
    $('#dt').on('change', '.st-sel', function () {
        const sel = this, id = $(this).data('id'), status = this.value, prev = $(this).data('current');
        if (status === prev) return;
        const send = () => window.http({ method: 'PUT', url: base + '/' + id + '/status', data: { status } })
            .done(() => dt.reload())
            .fail(() => { sel.value = prev; dt.reload(); });
        if (status === 'cancelled') {
            window.confirmDialog({ title: 'Cancel this order?', message: 'Cancelling is final and cannot be undone.', confirmText: 'Cancel order', cancelText: 'Keep order' })
                .then(ok => { if (ok) send(); else sel.value = prev; });
        } else if (status === 'completed') {
            window.confirmDialog({ title: 'Mark order completed?', message: 'Completed orders cannot be changed afterwards.', confirmText: 'Mark completed', danger: false })
                .then(ok => { if (ok) send(); else sel.value = prev; });
        } else send();
    });

    // View detail
    $('#dt').on('click', '.btn-view', function () {
        const id = $(this).data('id');
        window.http({ method: 'GET', url: base + '/' + id, quiet: true }).done(function (res) {
            const o = res.data;
            const rows = (o.items || []).map(it => `<tr>
                <td class="col-grow">${esc(it.product_name)}</td><td class="num">${esc(it.quantity)}</td>
                <td class="num">${money(it.unit_retail_price)}</td><td class="num">${money(it.unit_final_price)}</td>
                <td class="num">${money(it.line_total)}</td></tr>`).join('');
            $('#order-view-body').html(`
                <div class="mb-3 text-sm"><b>${esc(o.order_number)}</b> · ${esc(o.customer?.user?.name||'')}
                  · <span class="badge badge-${badge(o.status)}">${esc(o.status)}</span></div>
                <div class="table-scroll" style="border:1px solid var(--border);border-radius:8px">
                <table class="data-table"><thead><tr><th class="col-grow">Item</th><th>Qty</th><th>Retail</th><th>Final</th><th>Line</th></tr></thead>
                <tbody>${rows}</tbody></table></div>
                <div class="text-right mt-3"><span style="color:var(--text-muted)">Discount ${money(o.discount_total)} · </span>
                  <b>Total ${money(o.grand_total)}</b></div>`);
            Modal.open('order-view');
        });
    });
});
</script>
@endpush
