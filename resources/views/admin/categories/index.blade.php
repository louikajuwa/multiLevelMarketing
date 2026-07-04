@extends('layouts.app')
@section('title', 'Categories')
@section('page-title', 'Categories')
@section('main-mode', 'fill')

@section('content')
<x-page-header title="Categories" subtitle="Product categories (optionally nested).">
    @can('categories.create')<button class="btn btn-primary" id="btn-new">+ New category</button>@endcan
</x-page-header>

<div class="card fill">
    <div class="card-pad flex items-center gap-3 border-b" style="border-color:var(--border)">
        <input id="dt-search" class="input" style="max-width:280px" placeholder="Search categories…">
        <select id="dt-status" class="select" style="max-width:150px">
            <option value="">All</option><option value="1">Active</option><option value="0">Inactive</option>
        </select>
    </div>
    <div id="dt" class="table-scroll">
        <table class="data-table">
            <thead><tr><th data-dt-sort="name" class="col-grow">Name</th><th>Parent</th><th data-dt-sort="is_active">Status</th><th class="text-right">Actions</th></tr></thead>
            <tbody data-dt-body></tbody>
        </table>
        <div data-dt-empty class="hidden"><x-empty-state title="No categories yet" message="Create your first category." /></div>
        <div class="card-pad" data-dt-pagination></div>
    </div>
</div>

<x-modal id="cat-modal" title="Category">
    <form id="cat-form">
        <div class="modal-body">
            <div class="field"><label class="label" for="c-name">Name</label><input class="input" id="c-name" name="name" required></div>
            <div class="field"><label class="label" for="c-parent">Parent category</label>
                <select class="select" id="c-parent" name="parent_id">
                    <option value="">— None —</option>
                    @foreach ($parents as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                </select>
            </div>
            <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" id="c-active" checked> Active</label>
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
    const listUrl = "{{ route('admin.categories.index') }}", base = "{{ url('admin/categories') }}";
    const esc = (s) => $('<div>').text(s == null ? '' : s).html();
    const dt = new DataTable({
        url: listUrl, mount: '#dt', defaultSort: 'name', defaultDir: 'asc',
        searchInput: '#dt-search', filters: { '#dt-status': 'is_active' },
        row: (c) => `<tr><td class="font-medium col-grow">${esc(c.name)}</td>
            <td>${esc(c.parent ? c.parent.name : '—')}</td>
            <td>${c.is_active ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-neutral">Inactive</span>'}</td>
            <td class="text-right whitespace-nowrap">
                @can('categories.update')<button class="btn btn-ghost btn-edit" data-id="${c.id}">Edit</button>@endcan
                @can('categories.delete')<button class="btn btn-ghost btn-del" data-id="${c.id}" style="color:var(--c-danger)">Delete</button>@endcan
            </td></tr>`,
    });
    dt.reload();
    $('#btn-new').on('click', function () { $('#cat-form')[0].reset(); $('#c-active').prop('checked', true); $('#cat-form').data('url', "{{ route('admin.categories.store') }}").data('method', 'POST'); Modal.open('cat-modal'); });
    $('#dt').on('click', '.btn-edit', function () { const c = dt.getItem($(this).data('id')); $('#cat-form')[0].reset(); $('#c-name').val(c.name); $('#c-parent').val(c.parent_id || ''); $('#c-active').prop('checked', !!c.is_active); $('#cat-form').data('url', base + '/' + c.id).data('method', 'PUT'); Modal.open('cat-modal'); });
    $('#dt').on('click', '.btn-del', function () { const id = $(this).data('id'), btn = this; window.confirmDialog({ title: 'Delete category?', confirmText: 'Delete' }).then(ok => { if (ok) window.http({ method: 'DELETE', url: base + '/' + id, button: btn }).done(() => dt.reload()); }); });
    $('#cat-form').on('submit', function (e) { e.preventDefault(); const $f = $(this); window.http({ method: $f.data('method'), url: $f.data('url'), data: $f.serialize() + '&is_active=' + ($('#c-active').is(':checked') ? 1 : 0), form: $f, button: $f.find('[type=submit]') }).done(() => { Modal.close('cat-modal'); dt.reload(); }); });
});
</script>
@endpush
