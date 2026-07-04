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
      $input.addClass('is-invalid');
      let $err = $form.find('.error-text[data-error="' + field + '"]');
      if (!$err.length) {
        $err = $('<div class="error-text"></div>').attr('data-error', field);
        $input.after($err);
      }
      $err.text(msg).addClass('show');
    });
  }

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
      .fail(function (xhr) {
        const res = xhr.responseJSON || {};
        if (xhr.status === 422) {
          showErrors($form, res.errors);
          window.toast(res.message || 'Please check the form.', 'error');
        } else if (xhr.status === 419) {
          window.toast('Session expired. Reloading…', 'error');
          setTimeout(() => location.reload(), 1200);
        } else {
          window.toast((res && res.message) || 'Something went wrong.', 'error');
        }
      })
      .always(function () {
        if ($btn && $btn.length) {
          $btn.prop('disabled', false).html(btnHtml);
        }
      });
  };

  // Convenience: submit a <form> via AJAX. Reads method from data-method or the form.
  window.ajaxForm = function ($form, onSuccess) {
    $form = $($form);
    $form.on('submit', function (e) {
      e.preventDefault();
      const $submit = $form.find('[type="submit"]').first();
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
