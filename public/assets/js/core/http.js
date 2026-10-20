// AJAX wrapper around jQuery: attaches CSRF, shows loading state on the
// triggering button, surfaces toasts on error, and maps 422 -> inline field
// errors. Returns a jQuery promise resolving to the JSON envelope's data.
(function ($) {
  const csrf = () => $('meta[name="csrf-token"]').attr('content');

  // Global CSRF + AJAX headers for all requests
  $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' } });

  function clearErrors($form) {
    if (!$form) return;
    $form.find('.error-text').removeClass('show').text('');
    $form.find('.is-invalid').removeClass('is-invalid');
  }

  function showErrors($form, errors) {
    if (!$form || !errors) return;
    Object.keys(errors).forEach(function (field) {
      const msg = Array.isArray(errors[field]) ? errors[field][0] : errors[field];
      const $input = $form.find('[name="' + field + '"]');
      $input.addClass('is-invalid').attr('aria-invalid', 'true');
      let $err = $form.find('.error-text[data-error="' + field + '"]');
      if (!$err.length) {
        $err = $('<div class="error-text" role="alert"></div>').attr('data-error', field);
        $input.after($err);
      }
      $err.text(msg).addClass('show');
    });
  }

  // Shared failure handling. Never surfaces raw HTML bodies; messages are set via textContent.
  function handleFail(xhr, $form) {
    const res = xhr.responseJSON || {};
    const msg = typeof res.message === 'string' ? res.message : '';
    switch (xhr.status) {
      case 0:
        if (xhr.statusText === 'abort') return;
        window.toast('Network error. Check your connection and try again.', 'error');
        break;
      case 401:
        window.toast('Session expired. Redirecting to sign in…', 'error');
        setTimeout(function () { window.location = '/login'; }, 1000);
        break;
      case 419:
        window.toast('Session expired. Reloading…', 'error');
        setTimeout(function () { location.reload(); }, 1200);
        break;
      case 422:
        showErrors($form, res.errors);
        window.toast(msg || 'Please check the form.', 'error');
        break;
      case 429:
        window.toast('Too many attempts, wait a moment and try again.', 'error');
        break;
      default:
        if (xhr.status >= 500) window.toast('Something went wrong on our side. Please try again.', 'error');
        else window.toast(msg || 'Something went wrong.', 'error');
    }
  }
  window.httpFail = handleFail;

  // opts: { method, url, data, form (jQuery), button (jQuery), quiet }
  window.http = function (opts) {
    const $form = opts.form ? $(opts.form) : null;
    const $btn = opts.button ? $(opts.button) : null;
    let btnHtml;
    clearErrors($form);
    if ($btn && $btn.length) {
      btnHtml = $btn.html();
      $btn.prop('disabled', true).html('<span class="spinner"></span>');
    }

    return $.ajax({
      method: (opts.method || 'GET').toUpperCase(),
      url: opts.url,
      data: opts.data,
      dataType: 'json',
    })
      .done(function (res) {
        if (res && res.message && !opts.quiet && (opts.method || 'GET').toUpperCase() !== 'GET') {
          window.toast(res.message, 'success');
        }
      })
      .fail(function (xhr) { handleFail(xhr, $form); })
      .always(function () {
        if ($btn && $btn.length) {
          $btn.prop('disabled', false).html(btnHtml);
        }
      });
  };

  // Multipart submit (file uploads) via FormData. opts: {form, url, method, button}
  window.httpForm = function (opts) {
    var $form = $(opts.form);
    var $btn = opts.button ? $(opts.button) : $form.find('[type=submit]').first();
    var btnHtml;
    clearErrors($form);
    if ($btn.length) { btnHtml = $btn.html(); $btn.prop('disabled', true).html('<span class="spinner"></span>'); }
    var fd = new FormData($form[0]);
    var method = (opts.method || 'POST').toUpperCase();
    if (method !== 'POST') fd.append('_method', method);
    return $.ajax({ method: 'POST', url: opts.url, data: fd, processData: false, contentType: false, dataType: 'json' })
      .done(function (res) { if (res && res.message) window.toast(res.message, 'success'); })
      .fail(function (xhr) { handleFail(xhr, $form); })
      .always(function () { if ($btn.length) $btn.prop('disabled', false).html(btnHtml); });
  };

  // Convenience: submit a <form> via AJAX. Reads method from data-method or the form.
  window.ajaxForm = function ($form, onSuccess) {
    $form = $($form);
    $form.on('submit', function (e) {
      e.preventDefault();
      const $submit = $form.find('[type="submit"]').first();
      if ($submit.prop('disabled')) return; // already in flight
      http({
        method: $form.attr('data-method') || $form.attr('method') || 'POST',
        url: $form.attr('action'),
        data: $form.serialize(),
        form: $form,
        button: $submit,
      }).done(function (res) {
        if (typeof onSuccess === 'function') onSuccess(res);
        else if (res && res.data && res.data.redirect) window.location = res.data.redirect;
      });
    });
  };
})(jQuery);
