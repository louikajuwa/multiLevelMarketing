// Minimal modal controller. Markup: a container #<id> with .modal-overlay + .modal-panel.
// window.Modal.open(id), window.Modal.close(id). Esc + backdrop close. Focus trap-lite.
(function ($) {
  const Modal = {
    open(id) {
      const $m = $('#' + id);
      $m.removeClass('hidden').attr('aria-hidden', 'false');
      $m.find('[autofocus], input, select, textarea, button').filter(':visible').first().trigger('focus');
      $('body').addClass('overflow-hidden');
    },
    close(id) {
      const $m = id ? $('#' + id) : $('.modal-root:not(.hidden)');
      $m.addClass('hidden').attr('aria-hidden', 'true');
      $('body').removeClass('overflow-hidden');
    },
  };

  $(function () {
    $(document).on('click', '[data-modal-open]', function () { Modal.open($(this).data('modal-open')); });
    $(document).on('click', '[data-modal-close]', function () { Modal.close($(this).closest('.modal-root').attr('id')); });
    $(document).on('click', '.modal-overlay', function () { Modal.close($(this).closest('.modal-root').attr('id')); });
    $(document).on('keydown', function (e) { if (e.key === 'Escape') Modal.close(); });
  });

  window.Modal = Modal;

  // Promise-based styled confirm dialog — replaces window.confirm().
  // window.confirmDialog({title, message, confirmText, cancelText, danger}) -> Promise<bool>
  window.confirmDialog = function (opts) {
    opts = opts || {};
    var danger = opts.danger !== false; // default to danger styling
    return new Promise(function (resolve) {
      $('#confirm-dialog').remove();
      var html =
        '<div id="confirm-dialog" class="modal-root" role="dialog" aria-modal="true">' +
          '<div class="modal-overlay"></div>' +
          '<div class="modal-panel" style="max-width:400px">' +
            '<div class="modal-body" style="display:flex;gap:.85rem;align-items:flex-start">' +
              '<div class="confirm-ico ' + (danger ? 'is-danger' : 'is-accent') + '">!</div>' +
              '<div><div style="font-weight:600">' + (opts.title || 'Are you sure?') + '</div>' +
              '<div style="color:var(--text-muted);font-size:.8125rem;margin-top:.2rem">' + (opts.message || '') + '</div></div>' +
            '</div>' +
            '<div class="modal-footer">' +
              '<button type="button" class="btn btn-secondary" data-cd="cancel">' + (opts.cancelText || 'Cancel') + '</button>' +
              '<button type="button" class="btn ' + (danger ? 'btn-danger' : 'btn-primary') + '" data-cd="ok">' + (opts.confirmText || 'Confirm') + '</button>' +
            '</div>' +
          '</div>' +
        '</div>';
      var $d = $(html).appendTo('body');
      $('body').addClass('overflow-hidden');
      function close(v) { $(document).off('keydown.cd'); $d.remove(); $('body').removeClass('overflow-hidden'); resolve(v); }
      $d.on('click', '[data-cd=ok]', function () { close(true); });
      $d.on('click', '[data-cd=cancel], .modal-overlay', function () { close(false); });
      $(document).on('keydown.cd', function (e) { if (e.key === 'Escape') close(false); });
      $d.find('[data-cd=ok]').trigger('focus');
    });
  };
})(jQuery);
