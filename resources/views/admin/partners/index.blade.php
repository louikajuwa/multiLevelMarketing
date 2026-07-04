@extends('layouts.app')
@section('title', 'Partners')
@section('page-title', 'Partners')
@section('main-mode', 'fill')

@section('content')
<x-page-header title="Partners" subtitle="Register partners under a sponsor and manage their level, discount and status.">
    @can('partners.create')
        <button class="btn btn-primary" id="btn-new">+ Register partner</button>
    @endcan
</x-page-header>

<div class="card fill">
    <div class="card-pad flex flex-wrap items-center gap-3 border-b" style="border-color:var(--border)">
        <input id="dt-search" class="input" style="max-width:280px" placeholder="Search by name or email…">
        <select id="dt-level" class="select" style="max-width:180px">
            <option value="">All levels</option>
            @foreach ($levels as $lvl)
                <option value="{{ $lvl->id }}">{{ $lvl->name }}</option>
            @endforeach
        </select>
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
                    <th class="col-grow">Partner</th>
                    <th>Level</th>
                    <th data-dt-sort="depth">Depth</th>
                    <th data-dt-sort="direct_referral_count">Direct</th>
                    <th data-dt-sort="total_downline_count">Downline</th>
                    <th data-dt-sort="status">Status</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody data-dt-body></tbody>
        </table>
        <div data-dt-empty class="hidden">
            <x-empty-state title="No partners yet" message="Register your first partner to start building the network." />
        </div>
        <div class="card-pad" data-dt-pagination></div>
    </div>
</div>

<x-modal id="partner-modal" title="Partner">
    <form id="partner-form">
        <div class="modal-body">
            <div id="create-fields">
                <div class="field">
                    <label class="label" for="f-name">Full name</label>
                    <input class="input" id="f-name" name="name">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="field">
                        <label class="label" for="f-email">Email</label>
                        <input class="input" id="f-email" name="email" type="email">
                    </div>
                    <div class="field">
                        <label class="label" for="f-password">Password</label>
                        <input class="input" id="f-password" name="password" type="password">
                    </div>
                </div>
                <div class="field">
                    <label class="label" for="f-sponsor">Sponsor (upline)</label>
                    <select class="select" id="f-sponsor" name="sponsor_id">
                        <option value="">— None (root partner) —</option>
                        @foreach ($sponsors as $s)
                            <option value="{{ $s['id'] }}">{{ $s['name'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="field">
                    <label class="label" for="f-level">Partner level</label>
                    <select class="select" id="f-level" name="partner_level_id">
                        @foreach ($levels as $lvl)
                            <option value="{{ $lvl->id }}">{{ $lvl->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label class="label" for="f-override">Discount override %</label>
                    <input class="input" id="f-override" name="discount_override_percent" type="number" step="0.01" min="0" max="100" placeholder="level default">
                </div>
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
    const listUrl = "{{ route('admin.partners.index') }}";
    const base = "{{ url('admin/partners') }}";
    const esc = (s) => $('<div>').text(s == null ? '' : s).html();
    const badge = { active: 'success', inactive: 'neutral', suspended: 'danger' };

    const dt = new DataTable({
        url: listUrl, mount: '#dt', defaultSort: 'id', defaultDir: 'asc',
        searchInput: '#dt-search',
        filters: { '#dt-level': 'partner_level_id', '#dt-status': 'status' },
        row: (p) => `<tr>
            <td class="col-grow"><div class="font-medium">${esc(p.user ? p.user.name : '—')}</div>
                <div class="text-xs" style="color:var(--text-muted)">${esc(p.user ? p.user.email : '')}</div></td>
            <td>${esc(p.level ? p.level.name : '—')}</td>
            <td class="num">${p.depth}</td>
            <td class="num">${p.direct_referral_count}</td>
            <td class="num">${p.total_downline_count}</td>
            <td><span class="badge badge-${badge[p.status] || 'neutral'}">${esc(p.status)}</span></td>
            <td class="text-right whitespace-nowrap">
                @can('partners.update')<button class="btn btn-ghost btn-edit" data-id="${p.id}">Edit</button>@endcan
                @can('partners.delete')<button class="btn btn-ghost btn-del" data-id="${p.id}" style="color:var(--c-danger)">Delete</button>@endcan
            </td>
        </tr>`,
    });
    dt.reload();

    function openCreate() {
        $('#partner-form')[0].reset();
        $('#create-fields').removeClass('hidden');
        $('#status-field').addClass('hidden');
        $('#partner-form').data('url', "{{ route('admin.partners.store') }}").data('method', 'POST');
        Modal.open('partner-modal');
    }
    function openEdit(p) {
        $('#partner-form')[0].reset();
        $('#create-fields').addClass('hidden');
        $('#status-field').removeClass('hidden');
        $('#f-level').val(p.partner_level_id);
        $('#f-override').val(p.discount_override_percent);
        $('#f-status').val(p.status);
        $('#partner-form').data('url', base + '/' + p.id).data('method', 'PUT');
        Modal.open('partner-modal');
    }

    $('#btn-new').on('click', openCreate);
    $('#dt').on('click', '.btn-edit', function () { openEdit(dt.getItem($(this).data('id'))); });
    $('#dt').on('click', '.btn-del', function () {
        const id = $(this).data('id'); const btn = this;
        window.confirmDialog({ title: 'Delete partner?', message: 'This removes their account. This cannot be undone.', confirmText: 'Delete' })
            .then(function (ok) {
                if (!ok) return;
                window.http({ method: 'DELETE', url: base + '/' + id, button: btn }).done(() => dt.reload());
            });
    });

    $('#partner-form').on('submit', function (e) {
        e.preventDefault();
        const $form = $(this);
        window.http({
            method: $form.data('method'), url: $form.data('url'),
            data: $form.serialize(), form: $form, button: $form.find('[type=submit]'),
        }).done(function () { Modal.close('partner-modal'); dt.reload(); });
    });
});
</script>
@endpush
