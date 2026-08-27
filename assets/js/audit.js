(function () {
  'use strict';

  var requestSequence = 0;
  var requestController = null;

  function showError(message) {
    if (typeof window.simpAlert === 'function') {
      window.simpAlert(message || 'Aktivitas audit gagal diperbarui.', {
        title: 'Paginasi Gagal',
        tone: 'danger'
      });
    }
  }

  function refreshAuditPage(targetUrl) {
    var current = document.getElementById('audit-list-content');
    if (!current || !targetUrl) return Promise.resolve();

    var sequence = ++requestSequence;
    if (requestController && typeof requestController.abort === 'function') requestController.abort();
    requestController = typeof AbortController === 'function' ? new AbortController() : null;

    var options = {
      credentials: 'same-origin',
      cache: 'no-store',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      }
    };
    if (requestController) options.signal = requestController.signal;

    return fetch(targetUrl, options).then(function (response) {
      var responseUrl = String(response.url || '');
      if (response.redirected || response.status === 401 || /\/login(?:[/?#]|$)/i.test(responseUrl)) {
        throw new Error('Sesi Anda telah berakhir. Silakan masuk kembali.');
      }
      return response.text().then(function (body) {
        var payload;
        try { payload = JSON.parse(body); }
        catch (error) { throw new Error('Data audit gagal diperbarui.'); }
        if (!response.ok || payload.success !== true) {
          throw new Error(payload.message || 'Data audit gagal diperbarui.');
        }
        return payload;
      });
    }).then(function (payload) {
      if (sequence !== requestSequence) return;
      var parsed = new DOMParser().parseFromString(payload.html || '', 'text/html');
      var next = parsed.getElementById('audit-list-content');
      if (!next) throw new Error('Potongan aktivitas audit tidak lengkap.');
      current.replaceWith(document.importNode(next, true));
      if (window.history && window.history.replaceState) {
        window.history.replaceState({}, '', targetUrl);
      }
    }).catch(function (error) {
      if (error && error.name === 'AbortError') return;
      throw error;
    });
  }

  document.addEventListener('click', function (event) {
    var link = event.target.closest('[data-audit-page-link]');
    if (!link) return;
    event.preventDefault();
    if (link.getAttribute('aria-busy') === 'true') return;
    link.setAttribute('aria-busy', 'true');
    refreshAuditPage(link.href).catch(function (error) {
      showError(error && error.message ? error.message : 'Data audit gagal diperbarui.');
    }).finally(function () {
      link.removeAttribute('aria-busy');
    });
  });
}());
