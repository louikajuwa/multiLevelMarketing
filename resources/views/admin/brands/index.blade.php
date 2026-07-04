@extends('layouts.app')
@section('title', 'Brands')
@section('page-title', 'Brands')

@section('content')
<x-page-header title="Brands" subtitle="Product brands.">
    @can('brands.create')<button class="btn btn-primary" id="btn-new">+ New brand</button>@endcan
</x-page-header>

<div class="card">
    <div class="card-pad flex items-center gap-3 border-b" style="border-color:var(--border)">
        <input id="dt-search" class="input" style="max-width:280px" placeholder="Search brands…">
        <select id="dt-status" class="select" style="max-width:150px">
            <option value="">All</option><option value="1">Active</option><option value="0">Inactive</option>
        </select>
    </div>
    <div id="dt" class="table-scroll">
        <table class="data-table">
            <thead><tr><th data-dt-sort="name">Name</th><th data-dt-sort="is_active">Status</th><th class="text-right">Actions</th></tr></thead>
            <tbody data-dt-body></tbody>
        </table>
        <div data-dt-empty class="hidden"><x-empty-state title="No brands yet" message="Create your first brand." /></div>
        <div class="card-pad" data-dt-pagination></div>
    </div>
</div>

<x-modal id="brand-modal" title="Brand">
    <form id="brand-form">
        <div class="modal-body">
            <div class="field"><label class="label" for="b-name">Name</label><input class="input" id="b-name" name="name" required></div>
            <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" id="b-active" checked> Active</label>
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
    const listUrl = "{{ route('admin.brands.index') }}", base = "{{ url('admin/brands') }}";
    const esc = (s) => $('<div>').text(s == null ? '' : s).html();
    const dt = new DataTable({
        url: listUrl, mount: '#dt', defaultSort: 'name', defaultDir: 'asc',
        searchInput: '#dt-search', filters: { '#dt-status': 'is_active' },
        row: (b) => `<tr><td class="font-medium">${esc(b.name)}</td>
            <td>${b.is_active ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-neutral">Inactive</span>'}</td>
            <td class="text-right whitespace-nowrap">
                @can('brands.update')<button class="btn btn-ghost btn-edit" data-id="${b.id}">Edit</button>@endcan
                @can('brands.delete')<button class="btn btn-ghost btn-del" data-id="${b.id}" style="color:var(--c-danger)">Delete</button>@endcan
            </td></tr>`,
    });
    dt.reload();
    $('#btn-new').on('click', function () { $('#brand-form')[0].reset(); $('#b-active').prop('checked', true); $('#brand-form').data('url', "{{ route('admin.brands.store') }}").data('method', 'POST'); Modal.open('brand-modal'); });
    $('#dt').on('click', '.btn-edit', function () { const b = dt.getItem($(this).data('id')); $('#brand-form')[0].reset(); $('#b-name').val(b.name); $('#b-active').prop('checked', !!b.is_active); $('#brand-form').data('url', base + '/' + b.id).data('method', 'PUT'); Modal.open('brand-modal'); });
    $('#dt').on('click', '.btn-del', function () { const id = $(this).data('id'), btn = this; window.confirmDialog({ title: 'Delete brand?', confirmText: 'Delete' }).then(ok => { if (ok) window.http({ method: 'DELETE', url: base + '/' + id, button: btn }).done(() => dt.reload()); }); });
    $('#brand-form').on('submit', function (e) { e.preventDefault(); const $f = $(this); window.http({ method: $f.data('method'), url: $f.data('url'), data: $f.serialize() + '&is_active=' + ($('#b-active').is(':checked') ? 1 : 0), form: $f, button: $f.find('[type=submit]') }).done(() => { Modal.close('brand-modal'); dt.reload(); }); });
});
</script>
@endpush
