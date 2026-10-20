// Reusable searchable/paginated single-select. Server-side search — never loads
// the whole table. Writes the chosen id into a hidden <input> so plain form
// serialize() still submits it; also fires `change` on the hidden input so it
// can drive a DataTable filter.
//
// Usage:
//   const ss = new SearchableSelect({
//     input:  '#f-partner-search',        // visible text input (selector or element)
//     hidden: '#f-partner',               // hidden input carrying the value (selector or element)
//     url:    '/customers/partner-options',
//     map:    p => ({ id: p.id, label: p.user.name + ' (L' + p.depth + ')' }),
//     placeholder: 'Search partner…',
//     allowClear: true,                   // default true
//   });
//   ss.setValue(id, 'Label');  ss.clear();  ss.reset();
(function ($) {
  class SearchableSelect {
    constructor(cfg) {
      this.cfg = cfg;
      this.perPage = cfg.perPage || 10;
      this.$input = $(cfg.input);
      this.$hidden = $(cfg.hidden);
      this.allowClear = cfg.allowClear !== false;
      this.page = 1;
      this.lastPage = 1;
      this.term = '';
      this._build();
      this._bind();
    }

    _build() {
      if (this.cfg.placeholder) this.$input.attr('placeholder', this.cfg.placeholder);
      this.$input.attr('autocomplete', 'off');
      this.$input.wrap('<div class="ss-wrap" style="position:relative"></div>');
      this.$wrap = this.$input.parent();
      this.$panel = $('<div class="ss-panel"></div>').css({
        position: 'absolute', left: 0, right: 0, top: '100%', zIndex: 70, marginTop: '2px',
        background: '#fff', border: '1px solid var(--border,#e2e8f0)', borderRadius: '8px',
        boxShadow: '0 8px 24px rgba(0,0,0,.12)', maxHeight: '260px', overflow: 'auto', display: 'none',
      }).appendTo(this.$wrap);
      if (this.allowClear) {
        this.$clear = $('<button type="button" class="ss-clear" aria-label="Clear">&times;</button>').css({
          position: 'absolute', right: '8px', top: '50%', transform: 'translateY(-50%)',
          border: 0, background: 'transparent', cursor: 'pointer', fontSize: '16px',
          color: 'var(--text-muted,#94a3b8)', display: 'none',
        }).appendTo(this.$wrap);
      }
    }

    _bind() {
      const self = this;
      let t;
      this.$input.on('input focus', function () {
        clearTimeout(t);
        const v = this.value;
        t = setTimeout(function () { self.term = v.trim(); self.page = 1; self._load(false); }, 250);
      });
      // Typing invalidates a previous selection until a new option is picked.
      this.$input.on('input', function () {
        if (self.$hidden.val()) { self.$hidden.val('').trigger('change'); }
        self._toggleClear();
      });
      this.$panel.on('click', '.ss-opt', function () {
        self.setValue($(this).data('id'), $(this).data('label'));
        self._close();
      });
      this.$panel.on('click', '.ss-more', function (e) {
        e.stopPropagation();
        self.page += 1;
        self._load(true);
      });
      if (this.$clear) {
        this.$clear.on('click', function () { self.clear(); self.$input.focus(); });
      }
      $(document).on('mousedown', function (e) {
        if (!self.$wrap[0].contains(e.target)) self._close();
      });
      this.$input.on('keydown', function (e) {
        if (e.key === 'Escape') self._close();
        else if (e.key === 'Enter') { e.preventDefault(); self.$panel.find('.ss-opt').first().trigger('click'); }
      });
    }

    _load(append) {
      const self = this;
      if (!append) this.$panel.html('<div class="ss-loading" style="padding:.6rem .8rem;color:var(--text-muted,#94a3b8);font-size:.85rem">Searching…</div>').show();
      $.ajax({
        url: this.cfg.url,
        data: { search: this.term, per_page: this.perPage, page: this.page },
        dataType: 'json', headers: { 'X-Requested-With': 'XMLHttpRequest' },
      }).done(function (res) {
        const items = (res.data || []).map(self.cfg.map);
        self.lastPage = (res.meta && res.meta.last_page) || 1;
        const rows = items.map(function (o) {
          return '<div class="ss-opt" data-id="' + o.id + '" data-label="' + self._esc(o.label) + '" ' +
            'style="padding:.5rem .8rem;cursor:pointer;font-size:.87rem" ' +
            'onmouseover="this.style.background=\'var(--c-accent-50,#eff6ff)\'" onmouseout="this.style.background=\'\'">' +
            self._esc(o.label) + '</div>';
        }).join('');
        if (append) self.$panel.find('.ss-more').remove(); else self.$panel.empty();
        self.$panel.append(rows || (append ? '' : '<div style="padding:.6rem .8rem;color:var(--text-muted,#94a3b8);font-size:.85rem">No matches.</div>'));
        if (self.page < self.lastPage) {
          self.$panel.append('<div class="ss-more" style="padding:.5rem .8rem;text-align:center;cursor:pointer;font-size:.8rem;color:var(--c-accent-600,#2563eb);border-top:1px solid var(--border,#e2e8f0)">Load more…</div>');
        }
        self.$panel.show();
      }).fail(function () {
        self.$panel.html('<div style="padding:.6rem .8rem;color:var(--c-danger,#dc2626);font-size:.85rem">Failed to load.</div>').show();
      });
    }

    setValue(id, label) {
      this.$hidden.val(id).trigger('change');
      this.$input.val(label);
      this._toggleClear();
    }

    clear() {
      this.$hidden.val('').trigger('change');
      this.$input.val('');
      this._close();
      this._toggleClear();
    }

    reset() { this.clear(); }

    _toggleClear() {
      if (this.$clear) this.$clear.toggle(!!(this.$input.val() || this.$hidden.val()));
    }

    _close() { this.$panel.hide(); }

    _esc(s) { return $('<div>').text(s == null ? '' : s).html(); }
  }

  window.SearchableSelect = SearchableSelect;
})(jQuery);
