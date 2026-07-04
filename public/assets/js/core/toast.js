// Minimal toast notifications. window.toast(message, type) — type: success|error|info
(function () {
  function ensureRoot() {
    let root = document.getElementById('toast-root');
    if (!root) {
      root = document.createElement('div');
      root.id = 'toast-root';
      document.body.appendChild(root);
    }
    return root;
  }

  window.toast = function (message, type) {
    type = type || 'info';
    const root = ensureRoot();
    const el = document.createElement('div');
    el.className = 'toast toast-' + type;
    el.textContent = message;
    root.appendChild(el);
    requestAnimationFrame(() => el.classList.add('show'));
    setTimeout(() => {
      el.classList.remove('show');
      setTimeout(() => el.remove(), 250);
    }, 3800);
  };
})();
