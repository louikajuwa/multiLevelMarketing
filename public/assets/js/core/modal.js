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
})(jQuery);
