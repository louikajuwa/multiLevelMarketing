@extends('layouts.app')
@section('title', 'Tree')
@section('page-title', 'Network Tree')
@section('main-mode', 'fill')

@section('content')
<x-page-header title="Network tree" subtitle="Expand a partner to lazy-load their downline and customers." />

<div class="card fill">
    <div class="card-pad border-b" style="border-color:var(--border)">
        <label for="tree-search" class="sr-only">Search partner or customer</label>
        <input id="tree-search" class="input" style="max-width:320px" placeholder="Search partner or customer…" autocomplete="off">
    </div>
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

    const fetchNodes = (params) => $.ajax({ url: nodesUrl, data: params, dataType: 'json', headers: { 'X-Requested-With': 'XMLHttpRequest' } });

    function rowHtml(n, level, flat) {
        const pad = 14 + level * 22;
        if (n.type === 'customer') {
            return `<div class="tnode tcustomer" style="padding-left:${Number(pad)}px">
                <span class="chev"></span>◦ ${esc(n.name)}
                <span class="badge badge-neutral">customer · ${esc(Number(n.discount||0))}%</span></div>`;
        }
        const clickable = (!flat && n.has_children) ? 'clickable' : '';
        const chev = (!flat && n.has_children) ? '▸' : '·';
        return `<div class="tnode ${clickable}" ${clickable ? 'role="button" tabindex="0" aria-expanded="false"' : ''} data-id="${esc(n.id)}" data-level="${esc(level)}" data-loaded="0" style="padding-left:${pad}px">
                <span class="chev" aria-hidden="true">${chev}</span>
                <span class="font-medium">${esc(n.name)}</span>
                <span class="badge badge-info">${esc(n.level || '')}</span>
                <span class="tmeta">direct ${esc(Number(n.direct)||0)} · downline ${esc(Number(n.downline)||0)} · customers ${esc(Number(n.customers)||0)}</span>
            </div>${flat ? '' : `<div class="tkids" data-kids="${esc(n.id)}"></div>`}`;
    }

    function moreBtn(kind, ctx, page, level) {
        return `<div class="tmore" data-kind="${esc(kind)}" data-ctx="${esc(ctx == null ? '' : ctx)}" data-page="${esc(page)}" data-level="${esc(level)}" style="padding:.5rem .9rem">
            <button class="btn btn-secondary btn-sm">Load more…</button></div>`;
    }

    // Append a page of nodes into $c, plus a "Load more" control when more remain.
    function renderInto($c, res, level, flat, kind, ctx) {
        $c.append((res.data || []).map(n => rowHtml(n, level, flat)).join(''));
        if (res.meta && res.meta.has_more) $c.append(moreBtn(kind, ctx, (res.meta.current_page || 1) + 1, level));
    }

    function loadRoots() {
        $('#tree').html('<div class="p-6 text-center" style="color:var(--text-muted)">Loading…</div>');
        fetchNodes({ page: 1 }).fail(function (xhr) {
            $('#tree').html('<div class="p-6 text-center" style="color:var(--c-danger)">Failed to load. <button type="button" class="btn btn-secondary btn-sm" id="tree-retry">Retry</button></div>');
            window.httpFail(xhr, null);
        }).done(function (res) {
            $('#tree').empty();
            if (!(res.data || []).length) { $('#tree').html('<div class="p-4 text-center" style="color:var(--text-muted)">No nodes.</div>'); return; }
            renderInto($('#tree'), res, 0, false, 'roots', null);
        });
    }

    function runSearch(term) {
        $('#tree').html('<div class="p-6 text-center" style="color:var(--text-muted)">Searching…</div>');
        fetchNodes({ q: term, page: 1 }).done(function (res) {
            $('#tree').empty();
            if (!(res.data || []).length) { $('#tree').html('<div class="p-4 text-center" style="color:var(--text-muted)">No matches.</div>'); return; }
            renderInto($('#tree'), res, 0, true, 'search', null);
        });
    }

    $('#tree').on('click', '#tree-retry', loadRoots);
    loadRoots();

    let st;
    $('#tree-search').on('input', function () {
        clearTimeout(st);
        const v = this.value.trim();
        st = setTimeout(() => (v === '' ? loadRoots() : runSearch(v)), 300);
    });

    // Lazy-expand a partner's children (paged).
    $('#tree').on('click', '.tnode.clickable', function () {
        const $n = $(this), id = $n.data('id'), level = $n.data('level');
        const $kids = $n.next('.tkids');
        if ($n.data('loaded') === 0) {
            $n.data('loaded', 1).addClass('open');
            $kids.empty().html('<div style="padding:.4rem 0 .4rem '+((level+1)*22+14)+'px;color:var(--text-muted);font-size:.8rem">Loading…</div>');
            fetchNodes({ parent: id, page: 1 }).done(function (res) {
                $kids.empty();
                renderInto($kids, res, level + 1, false, 'children', id);
            }).fail(function (xhr) {
                $n.data('loaded', 0).removeClass('open');
                $kids.html('<div style="padding:.4rem 1rem;color:var(--c-danger);font-size:.8rem">Failed to load. Click the row to retry.</div>');
                window.httpFail(xhr, null);
            });
        } else {
            $n.toggleClass('open');
            $kids.slideToggle(120);
        }
    });

    $('#tree').on('keydown', '.tnode.clickable', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); $(this).trigger('click'); } });
    $('#tree').on('click', '.tnode.clickable', function () { const $n = $(this); setTimeout(function () { $n.attr('aria-expanded', $n.hasClass('open') ? 'true' : 'false'); }, 0); });

    // Load-more (roots / children / search).
    $('#tree').on('click', '.tmore button', function () {
        const $more = $(this).closest('.tmore');
        const kind = $more.data('kind'), ctx = $more.data('ctx'), page = $more.data('page'), level = $more.data('level');
        const $c = $more.parent();
        const flat = kind === 'search';
        const params = kind === 'search' ? { q: $('#tree-search').val().trim(), page }
            : kind === 'children' ? { parent: ctx, page } : { page };
        $more.remove();
        fetchNodes(params).done(res => renderInto($c, res, level, flat, kind, ctx));
    });
});
</script>
@endpush
