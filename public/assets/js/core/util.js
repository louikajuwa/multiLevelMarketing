// Shared helpers: HTML escaping, URL sanitising, double-submit guard, a11y niceties.
(function () {
  var MAP = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
  // Escape for HTML text AND quoted attribute contexts.
  window.esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return MAP[c]; }); };

  // Allow only relative paths ("/x", not "//x") or same-origin http(s) URLs; else '#'.
  window.safeUrl = function (u) {
    if (typeof u !== 'string') return '#';
    u = u.trim();
    if (u.charAt(0) === '/' && u.charAt(1) !== '/' && u.charAt(1) !== String.fromCharCode(92)) return u;
    try {
      var p = new URL(u, location.origin);
      if ((p.protocol === 'http:' || p.protocol === 'https:') && p.origin === location.origin) return p.href;
    } catch (e) { /* fall through */ }
    return '#';
  };

  // Double-submit guard for plain <form data-busy="Signing in…"> posts. AJAX forms
  // (which preventDefault) are skipped: window.http already handles their buttons.
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (!f || !f.hasAttribute || !f.hasAttribute('data-busy') || e.defaultPrevented) return;
    if (f.dataset.submitting === '1') { e.preventDefault(); return; }
    if (f.checkValidity && !f.checkValidity()) return;
    f.dataset.submitting = '1';
    var b = f.querySelector('[type=submit]');
    if (b) {
      var label = f.getAttribute('data-busy') || 'Please wait…';
      setTimeout(function () { b.disabled = true; b.innerHTML = '<span class="spinner"></span> ' + window.esc(label); }, 0);
    }
  });
  // Restore after bfcache navigation back.
  window.addEventListener('pageshow', function (e) {
    if (!e.persisted) return;
    document.querySelectorAll('form[data-busy]').forEach(function (f) { f.dataset.submitting = ''; var b = f.querySelector('[type=submit]'); if (b) b.disabled = false; });
  });

  // Give unlabeled search inputs / selects an accessible name (placeholder or first option).
  function labelControls() {
    document.querySelectorAll('input:not([type=hidden]):not([aria-label]):not([aria-labelledby]), select:not([aria-label]):not([aria-labelledby])').forEach(function (el) {
      if ((el.labels && el.labels.length) || el.closest('label')) return;
      var t = el.getAttribute('placeholder') || (el.tagName === 'SELECT' && el.options[0] ? el.options[0].text : '');
      if (t) el.setAttribute('aria-label', t.replace(/…$/, ''));
    });
  }
  document.addEventListener('DOMContentLoaded', labelControls);
})();

// Status -> badge colour. Keep in sync with resources/views/components/badge.blade.php.
(function () {
  var MAP = {
    pending: 'warning', paid: 'success', processing: 'info', shipped: 'info', completed: 'success', cancelled: 'danger',
    voided: 'neutral', void: 'neutral', active: 'success', inactive: 'neutral', suspended: 'danger'
  };
  window.badgeType = function (s) { return MAP[String(s || '').toLowerCase()] || 'neutral'; };
  window.badgeHtml = function (s, label) { return '<span class="badge badge-' + window.badgeType(s) + '">' + window.esc(label == null ? s : label) + '</span>'; };
})();
