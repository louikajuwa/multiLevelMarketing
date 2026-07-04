@extends('layouts.app')
@section('title', 'Partner Levels')
@section('page-title', 'Partner Levels')

@section('content')
<x-page-header title="Partner Levels" subtitle="Configure discount, commission and sponsor-bonus percentages per level.">
    @can('partner_levels.create')
        <button class="btn btn-primary" id="btn-new">+ New level</button>
    @endcan
</x-page-header>

<div class="card">
    <div class="card-pad flex items-center gap-3 border-b" style="border-color:var(--border)">
        <input id="dt-search" class="input" style="max-width:280px" placeholder="Search levels…">
        <select id="dt-status" class="select" style="max-width:160px">
            <option value="">All statuses</option>
            <option value="1">Active</option>
            <option value="0">Inactive</option>
        </select>
    </div>

    <div id="dt" class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <th data-dt-sort="level">Level</th>
                    <th data-dt-sort="name">Name</th>
                    <th data-dt-sort="discount_percent">Discount</th>
                    <th data-dt-sort="commission_percent">Commission</th>
                    <th data-dt-sort="sponsor_bonus_percent">Sponsor bonus</th>
                    <th data-dt-sort="is_active">Status</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody data-dt-body></tbody>
        </table>
        <div data-dt-empty class="hidden">
            <x-empty-state title="No partner levels yet" message="Create your first level to define partner discounts and commissions." />
        </div>
        <div class="card-pad" data-dt-pagination></div>
    </div>
</div>

<x-modal id="level-modal" title="Partner level">
    <form id="level-form">
        <div class="modal-body">
            <input type="hidden" name="_method" id="level-method" value="POST">
            <div class="grid grid-cols-2 gap-3">
                <div class="field col-span-2">
                    <label class="label" for="f-name">Name</label>
                    <input class="input" id="f-name" name="name" required>
                </div>
                <div class="field">
                    <label class="label" for="f-level">Level (1–10)</label>
                    <input class="input" id="f-level" name="level" type="number" min="1" max="10" required>
                </div>
                <div class="field">
                    <label class="label" for="f-discount">Discount %</label>
                    <input class="input" id="f-discount" name="discount_percent" type="number" step="0.01" min="0" max="100" required>
                </div>
                <div class="field">
                    <label class="label" for="f-commission">Commission %</label>
                    <input class="input" id="f-commission" name="commission_percent" type="number" step="0.01" min="0" max="100">
                </div>
                <div class="field">
                    <label class="label" for="f-bonus">Sponsor bonus %</label>
                    <input class="input" id="f-bonus" name="sponsor_bonus_percent" type="number" step="0.01" min="0" max="100">
                </div>
                <div class="field col-span-2">
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" name="is_active" value="1" id="f-active" checked> Active
                    </label>
                </div>
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
    const storeUrl = "{{ route('admin.partner-levels.index') }}";
    const base = "{{ url('admin/partner-levels') }}";
    const esc = (s) => $('<div>').text(s == null ? '' : s).html();
    const pct = (v) => v == null ? '—' : (Number(v).toFixed(2).replace(/\.00$/, '') + '%');

    const dt = new DataTable({
        url: storeUrl,
        mount: '#dt',
        defaultSort: 'level', defaultDir: 'asc',
        searchInput: '#dt-search',
        filters: { '#dt-status': 'is_active' },
        row: (l) => `<tr>
            <td class="num">${l.level}</td>
            <td class="font-medium">${esc(l.name)}</td>
            <td class="num">${pct(l.discount_percent)}</td>
            <td class="num">${pct(l.commission_percent)}</td>
            <td class="num">${pct(l.sponsor_bonus_percent)}</td>
            <td>${l.is_active ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-neutral">Inactive</span>'}</td>
            <td class="text-right whitespace-nowrap">
                @can('partner_levels.update')<button class="btn btn-ghost btn-edit" data-id="${l.id}">Edit</button>@endcan
                @can('partner_levels.delete')<button class="btn btn-ghost btn-del" data-id="${l.id}" style="color:var(--c-danger)">Delete</button>@endcan
            </td>
        </tr>`,
    });
    dt.reload();

    function openCreate() {
        $('#level-form')[0].reset();
        $('#level-method').val('POST');
        $('#level-form').data('url', storeUrl).data('method', 'POST');
        $('#f-active').prop('checked', true);
        Modal.open('level-modal');
    }
    function openEdit(l) {
        const f = $('#level-form')[0];
        f.reset();
        $('#f-name').val(l.name); $('#f-level').val(l.level);
        $('#f-discount').val(l.discount_percent); $('#f-commission').val(l.commission_percent);
        $('#f-bonus').val(l.sponsor_bonus_percent); $('#f-active').prop('checked', !!l.is_active);
        $('#level-form').data('url', base + '/' + l.id).data('method', 'PUT');
        Modal.open('level-modal');
    }

    $('#btn-new').on('click', openCreate);
    $('#dt').on('click', '.btn-edit', function () { openEdit(dt.getItem($(this).data('id'))); });
    $('#dt').on('click', '.btn-del', function () {
        const id = $(this).data('id');
        if (!confirm('Delete this partner level?')) return;
        window.http({ method: 'DELETE', url: base + '/' + id, button: this })
            .done(() => dt.reload());
    });

    $('#level-form').on('submit', function (e) {
        e.preventDefault();
        const $form = $(this);
        const method = $form.data('method') || 'POST';
        window.http({
            method: method,
            url: $form.data('url'),
            data: $form.serialize() + '&is_active=' + ($('#f-active').is(':checked') ? 1 : 0),
            form: $form,
            button: $form.find('[type=submit]'),
        }).done(function () { Modal.close('level-modal'); dt.reload(); });
    });
});
</script>
@endpush
