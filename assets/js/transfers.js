(function () {
  'use strict';

  var addForm = document.getElementById('transfer-add-form');
  var addModalId = 'transfer-add-modal';

  function updateCsrf(payload) {
    if (!payload || !payload.csrf) return;
    window.SIMP = window.SIMP || {};
    window.SIMP.csrfName = payload.csrf.name || window.SIMP.csrfName;
    window.SIMP.csrfHash = payload.csrf.hash || window.SIMP.csrfHash;
    if (!window.SIMP.csrfName) return;
    document.querySelectorAll('input[name="' + window.SIMP.csrfName + '"]').forEach(function (field) {
      field.value = window.SIMP.csrfHash;
      field.defaultValue = window.SIMP.csrfHash;
    });
  }

  function requestForm(form) {
    var headers = {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'};
    if (window.SIMP && window.SIMP.csrfHash) headers['X-CSRF-TOKEN'] = window.SIMP.csrfHash;
    return fetch(form.action, {method: 'POST', credentials: 'same-origin', headers: headers, body: new FormData(form)}).then(function (response) {
      return response.text().then(function (text) {
        var payload = null;
        try { payload = text ? JSON.parse(text) : null; } catch (error) { payload = null; }
        if (!payload || typeof payload !== 'object' || Array.isArray(payload)) {
          var responseUrl = String(response.url || '');
          if (response.redirected || response.status === 401 || /\/login(?:[/?#]|$)/i.test(responseUrl)) {
            throw new Error('Sesi Anda telah berakhir. Silakan masuk kembali.');
          }
          if (response.status === 403 || response.status === 419) {
            throw new Error('Sesi keamanan form sudah tidak berlaku. Muat ulang halaman lalu coba kembali.');
          }
          if (response.status >= 500) throw new Error('Transfer belum dapat diproses karena terjadi gangguan server.');
          if (!response.ok) throw new Error('Transfer tidak dapat diproses. Periksa data lalu coba kembali.');
          throw new Error('Respons server tidak dapat dibaca. Muat ulang halaman lalu coba kembali.');
        }
        updateCsrf(payload);
        if (!response.ok || payload.success === false) throw new Error(payload.message || 'Permintaan gagal diproses.');
        return payload;
      }, function () {
        throw new Error('Respons server tidak dapat dibaca. Silakan coba kembali.');
      });
    }, function () {
      throw new Error('Koneksi ke server terputus. Periksa jaringan lalu coba kembali.');
    });
  }

  function refreshTransferContent() {
    return fetch(window.location.href, {credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html'}}).then(function (response) {
      var responseUrl = String(response.url || '');
      if (response.redirected || response.status === 401 || /\/login(?:[/?#]|$)/i.test(responseUrl)) throw new Error('Sesi Anda telah berakhir. Silakan masuk kembali.');
      if (!response.ok) throw new Error('Daftar transfer gagal diperbarui.');
      return response.text();
    }, function () {
      throw new Error('Koneksi ke server terputus. Daftar transfer belum dapat diperbarui.');
    }).then(function (html) {
      var parsed = new DOMParser().parseFromString(html, 'text/html');
      var current = document.getElementById('transfer-index-content');
      var next = parsed.getElementById('transfer-index-content');
      if (!current || !next) throw new Error('Daftar transfer gagal diperbarui.');
      current.replaceWith(document.importNode(next, true));
    });
  }

  function alertUser(message, options) {
    if (typeof window.simpAlert === 'function') return window.simpAlert(message, options || {});
    return Promise.resolve();
  }

  function finishMutation(payload) {
    return refreshTransferContent().then(function () {
      return alertUser(payload.message, {title: 'Berhasil', tone: 'success'});
    }).catch(function () {
      return alertUser(payload.message + ' Tampilan belum dapat diperbarui; muat ulang halaman untuk melihat data terbaru dan jangan kirim ulang transaksi.', {
        title: 'Data Sudah Tersimpan', tone: 'warning'
      });
    });
  }

  function openAddModal(reset) {
    if (!addForm) return;
    if (reset) {
      addForm.reset();
      if (window.SIMP && window.SIMP.csrfName) {
        var csrf = addForm.querySelector('input[name="' + window.SIMP.csrfName + '"]');
        if (csrf) csrf.value = window.SIMP.csrfHash;
      }
    }
    var opener = document.querySelector('[data-menu="' + addModalId + '"]');
    if (opener) opener.click();
  }

  document.addEventListener('click', function (event) {
    if (!event.target.closest('[data-transfer-add-open]')) return;
    event.preventDefault();
    openAddModal(true);
  });

  if (addForm) {
    addForm.addEventListener('submit', function (event) {
      event.preventDefault();
      if (!addForm.checkValidity()) { addForm.reportValidity(); return; }
      var from = addForm.querySelector('[name="from_account_id"]');
      var to = addForm.querySelector('[name="to_account_id"]');
      if (from && to && from.value === to.value) {
        alertUser('Akun sumber dan tujuan harus berbeda.', {title: 'Akun Tidak Valid', tone: 'warning'}).then(function () { openAddModal(false); });
        return;
      }
      var submit = addForm.querySelector('[data-transfer-submit]');
      if (submit) { submit.disabled = true; submit.dataset.originalText = submit.innerHTML; submit.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i>Menyimpan...'; }
      requestForm(addForm).then(function (payload) {
        var closer = document.querySelector('#' + addModalId + ' .close-menu');
        if (closer) closer.click();
        return finishMutation(payload);
      }, function (error) {
        return alertUser(error.message, {title: 'Transfer Gagal', tone: 'danger'}).then(function () { openAddModal(false); });
      }).then(function () {
        if (submit) { submit.disabled = false; submit.innerHTML = submit.dataset.originalText || '<i class="fas fa-exchange-alt me-1"></i>Simpan Transfer'; }
      });
    });
  }

  document.addEventListener('submit', function (event) {
    var form = event.target.closest('form[data-transfer-status-form]');
    if (!form) return;
    event.preventDefault();
    var submit = event.submitter || form.querySelector('button[type="submit"]');
    if (submit) { submit.disabled = true; submit.dataset.originalText = submit.innerHTML; submit.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i>Menyimpan...'; }
    requestForm(form).then(function (payload) {
      return finishMutation(payload);
    }, function (error) {
      return alertUser(error.message, {title: 'Status Gagal', tone: 'danger'});
    }).then(function () {
      if (submit && document.contains(submit)) { submit.disabled = false; submit.innerHTML = submit.dataset.originalText || 'Simpan Status'; }
    });
  });
})();
