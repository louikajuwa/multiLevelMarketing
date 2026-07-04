@extends('layouts.app')
@section('title', 'Products')
@section('page-title', 'Products')
@section('main-mode', 'fill')

@section('content')
<x-page-header title="Products" subtitle="Catalog products with pricing, stock and images.">
    @can('products.create')<button class="btn btn-primary" id="btn-new">+ New product</button>@endcan
</x-page-header>

<div class="card fill">
    <div class="card-pad flex flex-wrap items-center gap-3 border-b" style="border-color:var(--border)">
        <input id="dt-search" class="input" style="max-width:260px" placeholder="Search name or SKU…">
        <select id="dt-cat" class="select" style="max-width:170px"><option value="">All categories</option>
            @foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
        </select>
        <select id="dt-brand" class="select" style="max-width:170px"><option value="">All brands</option>
            @foreach ($brands as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
        </select>
        <select id="dt-status" class="select" style="max-width:130px"><option value="">All</option><option value="1">Active</option><option value="0">Inactive</option></select>
    </div>
    <div id="dt" class="table-scroll">
        <table class="data-table">
            <thead><tr><th>Product</th><th>Category</th><th>Brand</th><th data-dt-sort="retail_price">Retail</th><th data-dt-sort="stock">Stock</th><th data-dt-sort="is_active">Status</th><th class="text-right">Actions</th></tr></thead>
            <tbody data-dt-body></tbody>
        </table>
        <div data-dt-empty class="hidden"><x-empty-state title="No products yet" message="Add your first product to the catalog." /></div>
        <div class="card-pad" data-dt-pagination></div>
    </div>
</div>

<x-modal id="product-modal" title="Product">
    <form id="product-form" enctype="multipart/form-data">
        <div class="modal-body">
            <div class="form-grid">
                <div class="field"><label class="label" for="p-name">Name</label><input class="input" id="p-name" name="name" required></div>
                <div class="field"><label class="label" for="p-sku">SKU</label><input class="input" id="p-sku" name="sku" required></div>
                <div class="field"><label class="label" for="p-cat">Category</label><select class="select" id="p-cat" name="category_id"><option value="">—</option>@foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
                <div class="field"><label class="label" for="p-brand">Brand</label><select class="select" id="p-brand" name="brand_id"><option value="">—</option>@foreach ($brands as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></div>
                <div class="field"><label class="label" for="p-cost">Cost price</label><input class="input" id="p-cost" name="cost_price" type="number" step="0.01" min="0" required></div>
                <div class="field"><label class="label" for="p-retail">Retail price</label><input class="input" id="p-retail" name="retail_price" type="number" step="0.01" min="0" required></div>
                <div class="field"><label class="label" for="p-pd">Partner discount % (optional cap)</label><input class="input" id="p-pd" name="partner_discount" type="number" step="0.01" min="0" max="100" placeholder="none"></div>
                <div class="field"><label class="label" for="p-stock">Stock</label><input class="input" id="p-stock" name="stock" type="number" min="0" required></div>
            </div>
            <div class="field"><label class="label" for="p-desc">Description</label><textarea class="textarea" id="p-desc" name="description"></textarea></div>
            <div class="field"><label class="label" for="p-images">Images</label><input type="file" id="p-images" name="images[]" accept="image/*" multiple><p class="help-text">First image becomes the primary. Adding images on edit appends them.</p></div>
            <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" id="p-active" checked> Active</label>
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
    const listUrl = "{{ route('admin.products.index') }}", base = "{{ url('admin/products') }}";
    const esc = (s) => $('<div>').text(s == null ? '' : s).html();
    const money = (v) => '$' + Number(v || 0).toFixed(2);
    const thumb = (p) => p.primary_image
        ? `<img src="/storage/${p.primary_image.path}" alt="" style="width:34px;height:34px;border-radius:6px;object-fit:cover;border:1px solid var(--border)">`
        : `<div style="width:34px;height:34px;border-radius:6px;background:var(--c-neutral-100)"></div>`;

    const dt = new DataTable({
        url: listUrl, mount: '#dt', defaultSort: 'name', defaultDir: 'asc',
        searchInput: '#dt-search', filters: { '#dt-cat': 'category_id', '#dt-brand': 'brand_id', '#dt-status': 'is_active' },
        row: (p) => `<tr>
            <td><div class="flex items-center gap-3">${thumb(p)}<div><div class="font-medium">${esc(p.name)}</div><div class="text-xs" style="color:var(--text-muted)">${esc(p.sku)}</div></div></div></td>
            <td>${esc(p.category ? p.category.name : '—')}</td>
            <td>${esc(p.brand ? p.brand.name : '—')}</td>
            <td class="num">${money(p.retail_price)}</td>
            <td class="num">${p.stock}</td>
            <td>${p.is_active ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-neutral">Inactive</span>'}</td>
            <td class="text-right whitespace-nowrap">
                @can('products.update')<button class="btn btn-ghost btn-edit" data-id="${p.id}">Edit</button>@endcan
                @can('products.delete')<button class="btn btn-ghost btn-del" data-id="${p.id}" style="color:var(--c-danger)">Delete</button>@endcan
            </td></tr>`,
    });
    dt.reload();

    function fill(p) {
        const f = $('#product-form')[0]; f.reset();
        if (p) {
            $('#p-name').val(p.name); $('#p-sku').val(p.sku); $('#p-cat').val(p.category_id || ''); $('#p-brand').val(p.brand_id || '');
            $('#p-cost').val(p.cost_price); $('#p-retail').val(p.retail_price); $('#p-pd').val(p.partner_discount); $('#p-stock').val(p.stock);
            $('#p-desc').val(p.description); $('#p-active').prop('checked', !!p.is_active);
        } else { $('#p-active').prop('checked', true); }
    }
    $('#btn-new').on('click', function () { fill(null); $('#product-form').data('url', "{{ route('admin.products.store') }}").data('method', 'POST'); Modal.open('product-modal'); });
    $('#dt').on('click', '.btn-edit', function () { const p = dt.getItem($(this).data('id')); fill(p); $('#product-form').data('url', base + '/' + p.id).data('method', 'PUT'); Modal.open('product-modal'); });
    $('#dt').on('click', '.btn-del', function () { const id = $(this).data('id'), btn = this; window.confirmDialog({ title: 'Delete product?', confirmText: 'Delete' }).then(ok => { if (ok) window.http({ method: 'DELETE', url: base + '/' + id, button: btn }).done(() => dt.reload()); }); });
    $('#product-form').on('submit', function (e) {
        e.preventDefault(); const $f = $(this);
        window.httpForm({ form: $f, url: $f.data('url'), method: $f.data('method'), button: $f.find('[type=submit]') })
            .done(() => { Modal.close('product-modal'); dt.reload(); });
    });
});
</script>
@endpush
