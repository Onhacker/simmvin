(function () {
  'use strict';
  var form = document.getElementById('login-form');
  if (!form) return;
  var alertBox = document.getElementById('login-alert');
  var alertMessage = document.getElementById('login-alert-message');
  var submit = document.getElementById('login-submit');
  var csrf = form.querySelector('input[name="' + (window.SIMP && window.SIMP.csrfName ? window.SIMP.csrfName : 'rab_csrf_token') + '"]');
  function showMessage(message) {
    // Login is also a template page, so use the same AppKit dialog as the
    // authenticated application. The inline alert remains a safe fallback
    // if the shared dialog cannot be initialised (for example, a blocked JS).
    var dialog = document.getElementById('menu-simp-dialog');
    if (dialog && typeof window.simpAlert === 'function') {
      window.simpAlert(message || 'Login gagal.', {
        title: 'Login gagal',
        tone: 'danger',
        okLabel: 'Mengerti'
      });
      if (alertBox) alertBox.classList.add('d-none');
      return;
    }
    if (alertMessage) alertMessage.textContent = String(message || 'Login gagal.');
    if (alertBox) { alertBox.classList.remove('d-none'); alertBox.classList.add('show'); }
  }
  function syncCsrf(payload) {
    if (!payload || !payload.csrf || !window.SIMP) return;
    if (payload.csrf.name) window.SIMP.csrfName = payload.csrf.name;
    if (payload.csrf.hash) {
      window.SIMP.csrfHash = payload.csrf.hash;
      if (csrf) csrf.value = payload.csrf.hash;
    }
  }
  function fallbackMessage(response) {
    if (response && response.status === 401) return 'Sesi login tidak valid. Silakan muat ulang halaman lalu coba lagi.';
    if (response && response.status >= 500) return 'Terjadi kesalahan pada server. Silakan coba lagi.';
    return 'Login tidak dapat diproses. Silakan coba lagi.';
  }
  form.addEventListener('submit', function (event) {
    event.preventDefault();
    if (submit) { submit.disabled = true; submit.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Memeriksa...'; }
    var body = new URLSearchParams(new FormData(form));
    fetch(form.action || window.location.href, {
      method: 'POST',
      headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'},
      credentials: 'same-origin',
      body: body
    }).then(function (response) {
      // Read the body once and parse it defensively. A proxy, expired session,
      // or framework error can return an HTML page even when JSON was asked for.
      return response.text().then(function (raw) {
        var payload = null;
        try {
          payload = raw ? JSON.parse(raw) : null;
        } catch (ignore) {
          payload = null;
        }
        if (!payload || typeof payload !== 'object' || Array.isArray(payload)) {
          throw new Error(fallbackMessage(response));
        }
        syncCsrf(payload);
        if (!response.ok || !payload.success) {
          throw new Error(payload.message || fallbackMessage(response));
        }
        window.location.assign(payload.redirect || '/');
      });
    }).catch(function (error) {
      var message = error && error.message ? error.message : 'Login tidak dapat diproses. Silakan coba lagi.';
      // Never expose parser/HTML errors to the user; keep the AppKit alert clear.
      if (/unexpected token|json|syntaxerror/i.test(message)) message = 'Login tidak dapat diproses. Silakan coba lagi.';
      showMessage(message);
      if (submit) { submit.disabled = false; submit.innerHTML = 'Masuk <i class="fas fa-arrow-right ms-2"></i>'; }
    });
  });
})();
