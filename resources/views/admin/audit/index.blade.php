@extends('layouts.app')
@section('title', 'Audit Log')
@section('page-title', 'Audit Log')
@section('main-mode', 'fill')

@section('content')
<x-page-header title="Audit &amp; activity" subtitle="Data changes and login/logout activity with IP and browser." />

<div class="card fill">
    <div class="card-pad flex flex-wrap items-center gap-3 border-b filter-bar" style="border-color:var(--border)">
        <label for="dt-log" class="sr-only">Log type</label>
        <select id="dt-log" class="select" style="max-width:180px">
            <option value="audit">Data changes</option>
            <option value="activity">Login activity</option>
        </select>
        <label for="dt-search" class="sr-only">Search log</label>
        <input id="dt-search" class="input" style="max-width:260px" placeholder="Search…">
    </div>
    <div id="dt" class="table-scroll">
        <table class="data-table">
            <thead><tr><th>When</th><th>User</th><th class="col-grow">Event</th><th>Target</th><th>IP</th><th>Browser</th></tr></thead>
            <tbody data-dt-body></tbody>
        </table>
        <div data-dt-empty class="hidden"><x-empty-state title="No entries" message="Nothing logged yet." /></div>
        <div class="card-pad" data-dt-pagination></div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function () {
    const listUrl = "{{ route('admin.audit.index') }}";
    const short = (t) => t ? String(t).split('\\\\').pop() : '';
    const actBadge = { created:'success', updated:'info', deleted:'danger', login:'success', logout:'neutral' };

    const dt = new DataTable({
        url: listUrl, mount: '#dt', defaultSort: 'id', defaultDir: 'desc',
        searchInput: '#dt-search', filters: { '#dt-log': 'log' },
        row: (r) => {
            const isActivity = r.event !== undefined;
            const ev = isActivity ? r.event : r.action;
            const target = isActivity ? '—' : short(r.auditable_type) + ' #' + r.auditable_id;
            return `<tr>
                <td class="num" style="color:var(--text-muted)">${esc((r.created_at || '').replace('T',' ').substring(0,16))}</td>
                <td>${esc(r.user ? r.user.name : '—')}</td>
                <td class="col-grow"><span class="badge badge-${esc(actBadge[ev] || 'neutral')}">${esc(ev)}</span></td>
                <td>${esc(target)}</td>
                <td class="num" style="color:var(--text-muted)">${esc(r.ip_address)}</td>
                <td class="text-xs" style="color:var(--text-muted);max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(r.user_agent)}</td>
            </tr>`;
        },
    });
    dt.reload();
});
</script>
@endpush
