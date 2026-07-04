@extends('layouts.app')
@section('title', 'Customers')
@section('page-title', 'Customers')

@section('content')
<x-page-header title="Customers" subtitle="Customers belong to a partner. Discount is capped at the partner's effective discount.">
    @can('customers.create')
        <button class="btn btn-primary" id="btn-new">+ Add customer</button>
    @endcan
</x-page-header>

<div class="card">
    <div class="card-pad flex flex-wrap items-center gap-3 border-b" style="border-color:var(--border)">
        <input id="dt-search" class="input" style="max-width:280px" placeholder="Search by name or email…">
        <select id="dt-status" class="select" style="max-width:160px">
            <option value="">All statuses</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
            <option value="suspended">Suspended</option>
        </select>
    </div>

    <div id="dt" class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Partner</th>
                    <th data-dt-sort="discount_percent">Discount</th>
                    <th data-dt-sort="status">Status</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody data-dt-body></tbody>
        </table>
        <div data-dt-empty class="hidden">
            <x-empty-state title="No customers yet" message="Add your first customer under a partner." />
        </div>
        <div class="card-pad" data-dt-pagination></div>
    </div>
</div>

<x-modal id="customer-modal" title="Customer">
    <form id="customer-form">
        <div class="modal-body">
            <div id="create-fields">
                <div class="field">
                    <label class="label" for="f-name">Full name</label>
                    <input class="input" id="f-name" name="name">
                </div>
                <div class="form-grid">
                    <div class="field">
                        <label class="label" for="f-email">Email</label>
                        <input class="input" id="f-email" name="email" type="email">
                    </div>
                    <div class="field">
                        <label class="label" for="f-password">Password</label>
                        <input class="input" id="f-password" name="password" type="password">
                    </div>
                </div>
                @if ($isStaff)
                    <div class="field">
                        <label class="label" for="f-partner">Partner</label>
                        <select class="select" id="f-partner" name="partner_id">
                            <option value="">Select a partner…</option>
                            @foreach ($partners as $p)
                                <option value="{{ $p['id'] }}">{{ $p['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>

            <div class="form-grid">
                <div class="field">
                    <label class="label" for="f-discount">Discount %</label>
                    <input class="input" id="f-discount" name="discount_percent" type="number" step="0.01" min="0" max="100" value="0">
                    <p class="help-text">Capped at the partner's effective discount.</p>
                </div>
                <div class="field">
                    <label class="label" for="f-phone">Phone</label>
                    <input class="input" id="f-phone" name="phone" placeholder="Optional">
                </div>
            </div>
            <div class="field">
                <label class="label" for="f-address">Address</label>
                <input class="input" id="f-address" name="address" placeholder="Optional">
            </div>
            <div class="field hidden" id="status-field">
                <label class="label" for="f-status">Status</label>
                <select class="select" id="f-status" name="status">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="suspended">Suspended</option>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
            <button type="submit" class="btn btn-primary">Save</button>
        </div>
    </form>
</x-modal>
@endsection

@push('scripts')
<script>
$(function () {
    const listUrl = "{{ route('customers.index') }}";
    const base = "{{ url('customers') }}";
    const esc = (s) => $('<div>').text(s == null ? '' : s).html();
    const pct = (v) => (Number(v || 0).toFixed(2).replace(/\.00$/, '')) + '%';
    const badge = { active: 'success', inactive: 'neutral', suspended: 'danger' };

    const dt = new DataTable({
        url: listUrl, mount: '#dt', defaultSort: 'id', defaultDir: 'asc',
        searchInput: '#dt-search', filters: { '#dt-status': 'status' },
        row: (c) => `<tr>
            <td><div class="font-medium">${esc(c.user ? c.user.name : '—')}</div>
                <div class="text-xs" style="color:var(--text-muted)">${esc(c.user ? c.user.email : '')}</div></td>
            <td>${esc(c.partner && c.partner.user ? c.partner.user.name : '—')}</td>
            <td class="num">${pct(c.discount_percent)}</td>
            <td><span class="badge badge-${badge[c.status] || 'neutral'}">${esc(c.status)}</span></td>
            <td class="text-right whitespace-nowrap">
                @can('customers.update')<button class="btn btn-ghost btn-edit" data-id="${c.id}">Edit</button>@endcan
                @can('customers.delete')<button class="btn btn-ghost btn-del" data-id="${c.id}" style="color:var(--c-danger)">Delete</button>@endcan
            </td>
        </tr>`,
    });
    dt.reload();

    function openCreate() {
        $('#customer-form')[0].reset();
        $('#create-fields').removeClass('hidden');
        $('#status-field').addClass('hidden');
        $('#f-discount').val(0);
        $('#customer-form').data('url', "{{ route('customers.store') }}").data('method', 'POST');
        Modal.open('customer-modal');
    }
    function openEdit(c) {
        $('#customer-form')[0].reset();
        $('#create-fields').addClass('hidden');
        $('#status-field').removeClass('hidden');
        $('#f-discount').val(c.discount_percent);
        $('#f-phone').val(c.phone); $('#f-address').val(c.address); $('#f-status').val(c.status);
        $('#customer-form').data('url', base + '/' + c.id).data('method', 'PUT');
        Modal.open('customer-modal');
    }

    $('#btn-new').on('click', openCreate);
    $('#dt').on('click', '.btn-edit', function () { openEdit(dt.getItem($(this).data('id'))); });
    $('#dt').on('click', '.btn-del', function () {
        const id = $(this).data('id'); const btn = this;
        window.confirmDialog({ title: 'Delete customer?', message: 'This removes their account. This cannot be undone.', confirmText: 'Delete' })
            .then(function (ok) {
                if (!ok) return;
                window.http({ method: 'DELETE', url: base + '/' + id, button: btn }).done(() => dt.reload());
            });
    });

    $('#customer-form').on('submit', function (e) {
        e.preventDefault();
        const $form = $(this);
        window.http({
            method: $form.data('method'), url: $form.data('url'),
            data: $form.serialize(), form: $form, button: $form.find('[type=submit]'),
        }).done(function () { Modal.close('customer-modal'); dt.reload(); });
    });
});
</script>
@endpush
