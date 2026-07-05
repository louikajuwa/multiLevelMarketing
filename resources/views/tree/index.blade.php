@extends('layouts.app')
@section('title', 'Tree')
@section('page-title', 'Network Tree')
@section('main-mode', 'fill')

@section('content')
<x-page-header title="Network tree" subtitle="Expand a partner to lazy-load their downline and customers." />

<div class="card fill">
    <div id="tree" class="table-scroll" style="padding:.5rem 0">
        <div class="p-6 text-center" style="color:var(--text-muted)">Loading…</div>
    </div>
</div>

<style>
    .tnode { display:flex; align-items:center; gap:.55rem; padding:.5rem .9rem; border-radius:8px; font-size:.85rem; }
    .tnode.clickable { cursor:pointer; }
    .tnode.clickable:hover { background:var(--c-accent-50); }
    .tnode .chev { width:14px; display:inline-block; color:var(--text-muted); transition:transform .15s; }
    .tnode.open .chev { transform:rotate(90deg); }
    .tmeta { color:var(--text-muted); font-size:.75rem; }
    .tcustomer { color:var(--c-neutral-600); }
</style>
@endsection

@push('scripts')
<script>
$(function () {
    const nodesUrl = "{{ route('tree.nodes') }}";
    const esc = (s) => $('<div>').text(s == null ? '' : s).html();

    function rowHtml(n, level) {
        const pad = 14 + level * 22;
        if (n.type === 'customer') {
            return `<div class="tnode tcustomer" style="padding-left:${pad}px">
                <span class="chev"></span>◦ ${esc(n.name)}
                <span class="badge badge-neutral">customer · ${Number(n.discount||0)}%</span></div>`;
        }
        return `<div class="tnode ${n.has_children ? 'clickable' : ''}" data-id="${n.id}" data-level="${level}" data-loaded="0" style="padding-left:${pad}px">
                <span class="chev">${n.has_children ? '▸' : '·'}</span>
                <span class="font-medium">${esc(n.name)}</span>
                <span class="badge badge-info">${esc(n.level || '')}</span>
                <span class="tmeta">direct ${n.direct} · downline ${n.downline} · customers ${n.customers}</span>
            </div><div class="tkids" data-kids="${n.id}"></div>`;
    }

    function load($container, parentId, level) {
        return $.ajax({ url: nodesUrl, data: parentId ? { parent: parentId } : {}, dataType: 'json', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .done(function (res) {
                $container.html((res.data || []).map(n => rowHtml(n, level)).join('') ||
                    '<div class="p-4 text-center" style="color:var(--text-muted)">No nodes.</div>');
            });
    }

    load($('#tree'), null, 0);

    $('#tree').on('click', '.tnode.clickable', function () {
        const $n = $(this), id = $n.data('id'), level = $n.data('level');
        const $kids = $n.next('.tkids');
        if ($n.data('loaded') === 0) {
            $n.data('loaded', 1).addClass('open');
            $kids.html('<div style="padding:.4rem 0 .4rem '+((level+1)*22+14)+'px;color:var(--text-muted);font-size:.8rem">Loading…</div>');
            load($kids, id, level + 1);
        } else {
            $n.toggleClass('open');
            $kids.slideToggle(120);
        }
    });
});
</script>
@endpush
