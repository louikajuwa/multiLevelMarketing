// Minimal modal controller. Markup: a container #<id> with .modal-overlay + .modal-panel.
// window.Modal.open(id), window.Modal.close(id). Esc + backdrop close, focus trap, focus return.
(function ($) {
  const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type=hidden]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
  const opener = {}; // modal id -> element that had focus before opening

  function syncBody() {
    $('body').toggleClass('overflow-hidden', $('.modal-root:not(.hidden)').length > 0);
  }

  const Modal = {
    open(id) {
      const $m = $('#' + id);
      opener[id] = document.activeElement;
      $m.removeClass('hidden').attr('aria-hidden', 'false');
      const $panel = $m.find('.modal-panel').first();
      if (!$panel.attr('tabindex')) $panel.attr('tabindex', '-1');
      const $first = $m.find('[autofocus], input, select, textarea, button').filter(':visible').not('[data-modal-close]').first();
      ($first.length ? $first : $panel).trigger('focus');
      syncBody();
    },
    close(id) {
      const $m = id ? $('#' + id) : $('.modal-root:not(.hidden)').last();
      $m.each(function () {
        const mid = this.id;
        $(this).addClass('hidden').attr('aria-hidden', 'true');
        const el = opener[mid];
        delete opener[mid];
        if (el && document.contains(el) && el.focus) el.focus();
      });
      syncBody();
    },
  };

  // Trap Tab inside the topmost open modal.
  function trap(e) {
    if (e.key !== 'Tab') return;
    const $m = $('.modal-root:not(.hidden)').last();
    if (!$m.length) return;
    const $f = $m.find(FOCUSABLE).filter(':visible');
    if (!$f.length) { e.preventDefault(); return; }
    const first = $f[0], last = $f[$f.length - 1];
    if (!$.contains($m[0], document.activeElement)) { e.preventDefault(); first.focus(); }
    else if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  }

  $(function () {
    $(document).on('click', '[data-modal-open]', function () { Modal.open($(this).data('modal-open')); });
    $(document).on('click', '[data-modal-close]', function () { Modal.close($(this).closest('.modal-root').attr('id')); });
    $(document).on('click', '.modal-overlay', function () { const $r = $(this).closest('.modal-root'); if ($r.attr('id') !== 'confirm-dialog') Modal.close($r.attr('id')); });
    $(document).on('keydown', function (e) {
      if ($('#confirm-dialog').length) return; // confirmDialog owns Esc/Tab while open
      if (e.key === 'Escape') Modal.close();
      else trap(e);
    });
  });

  window.Modal = Modal;

  // Promise-based styled confirm dialog — replaces window.confirm().
  // window.confirmDialog({title, message, confirmText, cancelText, danger}) -> Promise<bool>
  // title/message are escaped; pass plain text.
  window.confirmDialog = function (opts) {
    opts = opts || {};
    var e = window.esc;
    var danger = opts.danger !== false; // default to danger styling
    return new Promise(function (resolve) {
      $('#confirm-dialog').remove();
      var prev = document.activeElement;
      var html =
        '<div id="confirm-dialog" class="modal-root" role="alertdialog" aria-modal="true" aria-labelledby="cd-title" aria-describedby="cd-msg">' +
          '<div class="modal-overlay"></div>' +
          '<div class="modal-panel" style="max-width:400px">' +
            '<div class="modal-body" style="display:flex;gap:.85rem;align-items:flex-start">' +
              '<div class="confirm-ico ' + (danger ? 'is-danger' : 'is-accent') + '" aria-hidden="true">!</div>' +
              '<div><div id="cd-title" style="font-weight:600">' + e(opts.title || 'Are you sure?') + '</div>' +
              '<div id="cd-msg" style="color:var(--text-muted);font-size:.8125rem;margin-top:.2rem">' + e(opts.message || '') + '</div></div>' +
            '</div>' +
            '<div class="modal-footer">' +
              '<button type="button" class="btn btn-secondary" data-cd="cancel">' + e(opts.cancelText || 'Cancel') + '</button>' +
              '<button type="button" class="btn ' + (danger ? 'btn-danger' : 'btn-primary') + '" data-cd="ok">' + e(opts.confirmText || 'Confirm') + '</button>' +
            '</div>' +
          '</div>' +
        '</div>';
      var $d = $(html).appendTo('body');
      $('body').addClass('overflow-hidden');
      function close(v) {
        $(document).off('keydown.cd'); $d.remove(); syncBody();
        if (prev && document.contains(prev) && prev.focus) prev.focus();
        resolve(v);
      }
      $d.on('click', '[data-cd=ok]', function () { close(true); });
      $d.on('click', '[data-cd=cancel], .modal-overlay', function () { close(false); });
      $(document).on('keydown.cd', function (ev) {
        if (ev.key === 'Escape') { ev.stopPropagation(); close(false); }
        else if (ev.key === 'Tab') {
          var $b = $d.find('button'), f = $b[0], l = $b[$b.length - 1];
          if (ev.shiftKey && document.activeElement === f) { ev.preventDefault(); l.focus(); }
          else if (!ev.shiftKey && document.activeElement === l) { ev.preventDefault(); f.focus(); }
        }
      });
      // Destructive dialogs focus the safe choice so a stray Enter doesn't confirm.
      $d.find(danger ? '[data-cd=cancel]' : '[data-cd=ok]').trigger('focus');
    });
  };
})(jQuery);
