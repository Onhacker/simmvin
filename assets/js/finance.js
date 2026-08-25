(function () {
  'use strict';

  function all(selector) { return Array.prototype.slice.call(document.querySelectorAll(selector)); }

  function toggleAccountFields() {
    var type = document.querySelector('.js-account-type');
    if (!type) return;
    all('.js-bank-field').forEach(function (field) {
      var hidden = type.value === 'cash';
      field.style.display = hidden ? 'none' : '';
      field.setAttribute('aria-hidden', hidden ? 'true' : 'false');
    });
  }

  function toggleAdminFee() {
    var method = document.querySelector('.js-payment-method');
    var fee = document.querySelector('.js-admin-fee');
    if (!method || !fee) return;
    var isTransfer = method.value === 'transfer';
    fee.style.opacity = isTransfer ? '1' : '.55';
    var input = fee.querySelector('input');
    if (!isTransfer && input) { input.value = '0'; input.readOnly = true; }
    else if (input) input.readOnly = false;
  }

  var type = document.querySelector('.js-account-type');
  if (type) { type.addEventListener('change', toggleAccountFields); toggleAccountFields(); }

  var method = document.querySelector('.js-payment-method');
  if (method) { method.addEventListener('change', toggleAdminFee); toggleAdminFee(); }

  // The standalone/legacy expense form still follows the same proof policy
  // as the AJAX modal: cash may omit a receipt, while Transfer and QRIS must
  // provide one.  The server enforces this too; this only keeps browser
  // validation and the helper text in sync.
  var legacyExpenseProof = document.querySelector('.js-expense-proof');
  var legacyExpenseProofHelp = document.getElementById('expense-proof-help');
  function toggleLegacyExpenseProof() {
    if (!method || !legacyExpenseProof) return;
    var required = method.value !== 'cash';
    legacyExpenseProof.required = required;
    if (legacyExpenseProofHelp) {
      legacyExpenseProofHelp.textContent = required
        ? 'Bukti wajib untuk metode Transfer/QRIS. JPG, PNG, atau PDF; maksimal 5 MB.'
        : 'Bukti opsional untuk metode Tunai. JPG, PNG, atau PDF; maksimal 5 MB.';
    }
  }
  if (method) { method.addEventListener('change', toggleLegacyExpenseProof); toggleLegacyExpenseProof(); }

  var transfer = document.querySelector('.js-transfer-form');
  if (transfer) transfer.addEventListener('submit', function (event) {
    var from = transfer.querySelector('.js-from-account');
    var to = transfer.querySelector('.js-to-account');
    if (from && to && from.value && from.value === to.value) {
      event.preventDefault();
      window.simpAlert('Akun sumber dan akun tujuan harus berbeda.', {title: 'Akun Tidak Valid', tone: 'warning'});
      to.focus();
    }
  });

  function updateExpenseCsrf(payload) {
    if (!payload || !payload.csrf) return;
    if (window.SIMP) window.SIMP.csrfHash = payload.csrf.hash;
    var name = payload.csrf.name || (window.SIMP && window.SIMP.csrfName);
    if (name) document.querySelectorAll('input[name="' + name + '"]').forEach(function (field) { field.value = payload.csrf.hash; });
  }

  function expenseJson(response, fallbackMessage) {
    return response.text().then(function (body) {
      var payload;
      try { payload = JSON.parse(body); }
      catch (error) { throw new Error(fallbackMessage); }
      updateExpenseCsrf(payload);
      if (!response.ok || payload.success !== true) throw new Error(payload.message || fallbackMessage);
      return payload;
    });
  }

  function refreshExpenseCards() {
    return fetch(window.location.href, {
      headers:{'X-Requested-With':'XMLHttpRequest','Accept':'text/html'}
    }).then(function (response) {
      if (!response.ok || response.redirected) throw new Error('Daftar pengeluaran gagal diperbarui.');
      return response.text();
    }).then(function (html) {
      var parsed = new DOMParser().parseFromString(html, 'text/html');
      var ids = ['expense-summary','expense-list'];
      var replacements = ids.map(function (id) {
        return {current:document.getElementById(id), next:parsed.getElementById(id)};
      });
      if (replacements.some(function (item) { return !item.current || !item.next; })) {
        throw new Error('Potongan daftar pengeluaran tidak lengkap.');
      }
      replacements.forEach(function (item) {
        item.current.replaceWith(document.importNode(item.next, true));
      });
    });
  }

  function expenseAlert(message, title, tone) {
    if (typeof window.simpAlert === 'function') window.simpAlert(message, {title:title,tone:tone});
  }

  function notifyExpensePersisted(payload, refreshFailed, subject) {
    if (refreshFailed) {
      expenseAlert(
        payload.message + ' Namun daftar belum dapat diperbarui. Jangan simpan ulang; muat ulang halaman untuk melihat data terbaru.',
        subject + ' Tersimpan',
        'warning'
      );
      return;
    }
    expenseAlert(payload.message, 'Berhasil', 'success');
  }

  var expenseForm = document.getElementById('expense-add-form');
  if (expenseForm) {
    var expenseAccounts = [];
    try { expenseAccounts = JSON.parse(expenseForm.dataset.accounts || '[]'); } catch (error) { expenseAccounts = []; }
    var expenseMethod = document.getElementById('expense-modal-method');
    var expenseAccount = document.getElementById('expense-modal-account');
    var expenseProof = document.getElementById('expense-modal-proof');
    var expenseProofLabel = document.getElementById('expense-modal-proof-label');
    var expenseFee = document.getElementById('expense-modal-fee');
    var expenseSubmit = expenseForm.querySelector('[data-expense-add-submit]');

    function expenseEscape(value) {
      return String(value === null || typeof value === 'undefined' ? '' : value).replace(/[&<>'"]/g, function (character) {
        return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[character];
      });
    }

    function filterExpenseAccounts() {
      var allowed = {cash:['cash'], transfer:['bank','personal'], qris:['qris']}[expenseMethod.value] || [];
      var selected = expenseAccount.value;
      expenseAccount.innerHTML = '<option value="">Pilih akun</option>' + expenseAccounts.filter(function (account) {
        return allowed.indexOf(account.type) !== -1;
      }).map(function (account) {
        return '<option value="' + expenseEscape(account.id) + '">' + expenseEscape(account.name) + '</option>';
      }).join('');
      if (expenseAccount.querySelector('option[value="' + selected + '"]')) expenseAccount.value = selected;
    }

    function updateExpenseProofRequirement() {
      if (!expenseMethod || !expenseProof) return;
      var required = expenseMethod.value !== 'cash';
      expenseProof.required = required;
      if (expenseProofLabel) expenseProofLabel.textContent = required ? '(wajib untuk transfer/QRIS)' : '(opsional untuk tunai)';
    }

    function updateExpenseFeeRule() {
      if (!expenseMethod || !expenseFee) return;
      var isTransfer = expenseMethod.value === 'transfer';
      expenseFee.readOnly = !isTransfer;
      if (!isTransfer) expenseFee.value = '0';
    }

    document.addEventListener('click', function (event) {
      if (!event.target.closest('[data-expense-add-open]')) return;
      event.preventDefault();
      expenseForm.reset();
      var date = document.getElementById('expense-modal-date');
      if (date && !date.value) date.value = new Date().toISOString().slice(0,10);
      filterExpenseAccounts();
      updateExpenseProofRequirement();
      updateExpenseFeeRule();
      var opener = document.querySelector('[data-menu="expense-add-modal"]');
      if (opener) opener.click();
    });

    expenseMethod.addEventListener('change', function () { filterExpenseAccounts(); updateExpenseProofRequirement(); updateExpenseFeeRule(); });
    filterExpenseAccounts();
    updateExpenseProofRequirement();
    updateExpenseFeeRule();

    expenseForm.addEventListener('submit', function (event) {
      event.preventDefault();
      if (!expenseForm.checkValidity()) { expenseForm.reportValidity(); return; }
      if (expenseSubmit) { expenseSubmit.disabled = true; expenseSubmit.dataset.originalText = expenseSubmit.innerHTML; expenseSubmit.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i>Menyimpan...'; }
      var headers = {'X-Requested-With':'XMLHttpRequest','Accept':'application/json'};
      if (window.SIMP && window.SIMP.csrfHash) headers['X-CSRF-TOKEN'] = window.SIMP.csrfHash;
      fetch(expenseForm.action, {method:'POST',headers:headers,body:new FormData(expenseForm)})
        .then(function (response) { return expenseJson(response, 'Pengeluaran gagal disimpan.'); })
        .then(function (payload) {
          var closer = document.querySelector('#expense-add-modal .close-menu');
          if (closer) closer.click();
          return refreshExpenseCards().then(
            function () { notifyExpensePersisted(payload, false, 'Pengeluaran'); },
            function () { notifyExpensePersisted(payload, true, 'Pengeluaran'); }
          );
        })
        .catch(function (error) { expenseAlert(error.message, 'Pengeluaran Gagal', 'danger'); })
        .then(function () {
          if (expenseSubmit) { expenseSubmit.disabled = false; expenseSubmit.innerHTML = expenseSubmit.dataset.originalText || '<i class="fa fa-save me-1"></i> Simpan Pengeluaran'; }
        });
    });
  }

  // Delegated handling remains active after the expense-list fragment is replaced.
  document.addEventListener('submit', function (event) {
    var statusForm = event.target.closest('form[data-expense-status-form]');
    if (!statusForm) return;
    event.preventDefault();
    if (!statusForm.checkValidity()) { statusForm.reportValidity(); return; }

    var statusSubmit = event.submitter || statusForm.querySelector('[data-expense-status-submit]');
    var originalText = statusSubmit ? statusSubmit.innerHTML : '';
    if (statusSubmit) {
      statusSubmit.disabled = true;
      statusSubmit.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i>Menyimpan...';
    }
    var headers = {'X-Requested-With':'XMLHttpRequest','Accept':'application/json'};
    if (window.SIMP && window.SIMP.csrfHash) headers['X-CSRF-TOKEN'] = window.SIMP.csrfHash;

    fetch(statusForm.action, {method:'POST',headers:headers,body:new FormData(statusForm)})
      .then(function (response) { return expenseJson(response, 'Status pengeluaran gagal diperbarui.'); })
      .then(function (payload) {
        return refreshExpenseCards().then(
          function () { notifyExpensePersisted(payload, false, 'Status'); },
          function () { notifyExpensePersisted(payload, true, 'Status'); }
        );
      })
      .catch(function (error) { expenseAlert(error.message, 'Status Gagal', 'danger'); })
      .then(function () {
        if (statusSubmit) {
          statusSubmit.disabled = false;
          statusSubmit.innerHTML = originalText;
        }
      });
  });

  function reportPreviewAlert(message, title) {
    if (typeof window.simpAlert === 'function') {
      window.simpAlert(message, {title:title || 'Pratinjau Laporan', tone:'warning'});
    }
  }

  function updateReportZoomControls(frame, percent, ready) {
    var modal = frame ? frame.closest('.simp-print-modal') : null;
    if (!modal) return;
    var normalized = Math.max(50, Math.min(200, parseInt(percent, 10) || 100));
    var label = modal.querySelector('[data-report-zoom-value]');
    if (label) label.textContent = normalized + '%';
    modal.querySelectorAll('[data-report-zoom]').forEach(function (button) {
      var delta = parseInt(button.getAttribute('data-report-zoom'), 10) || 0;
      button.disabled = !ready || (delta < 0 && normalized <= 50) || (delta > 0 && normalized >= 200);
    });
  }

  all('[data-report-preview-frame]').forEach(function (frame) {
    frame.addEventListener('load', function () {
      var shell = frame.closest('.simp-print-frame-shell');
      var validDocument = false;
      try {
        validDocument = !!(frame.contentDocument && frame.contentDocument.querySelector('[data-print-sheet]'));
      } catch (error) {
        validDocument = false;
      }
      frame.dataset.loaded = validDocument ? 'true' : 'false';
      var previewApi = validDocument && frame.contentWindow ? frame.contentWindow.SIMPPrintPreview : null;
      var zoomPercent = previewApi && typeof previewApi.reset === 'function' ? previewApi.reset() : 100;
      updateReportZoomControls(frame, zoomPercent, !!previewApi);
      if (!shell) return;
      shell.classList.toggle('is-loaded', validDocument);
      shell.classList.toggle('is-error', !validDocument);
      if (!validDocument) {
        var loading = shell.querySelector('[data-report-preview-loading]');
        if (loading) loading.innerHTML = '<i class="fa fa-exclamation-circle color-red-dark"></i><span>Pratinjau gagal dimuat. Muat ulang halaman lalu coba kembali.</span>';
      }
    });
  });

  document.addEventListener('click', function (event) {
    var trigger = event.target.closest('[data-report-preview-open]');
    if (!trigger) return;
    event.preventDefault();

    var modalId = trigger.getAttribute('data-report-preview-open');
    var modal = modalId ? document.getElementById(modalId) : null;
    var frame = modal ? modal.querySelector('[data-report-preview-frame]') : null;
    var opener = modalId ? document.getElementById(modalId + '-opener') : null;
    if (!modal || !frame || !opener) {
      window.open(trigger.href, '_blank', 'noopener');
      return;
    }

    var source = frame.getAttribute('data-src');
    if (source) {
      var separator = source.indexOf('?') === -1 ? '?' : '&';
      frame.dataset.loaded = 'false';
      updateReportZoomControls(frame, 100, false);
      var shell = frame.closest('.simp-print-frame-shell');
      if (shell) {
        shell.classList.remove('is-loaded', 'is-error');
        var loading = shell.querySelector('[data-report-preview-loading]');
        if (loading) loading.innerHTML = '<i class="fa fa-spinner fa-spin color-highlight"></i><span>Menyiapkan pratinjau...</span>';
      }
      frame.src = source + separator + '_preview=' + Date.now();
    }
    opener.click();
  });

  document.addEventListener('click', function (event) {
    var zoomButton = event.target.closest('[data-report-zoom]');
    if (!zoomButton) return;
    event.preventDefault();

    var frame = document.getElementById(zoomButton.getAttribute('data-report-zoom-frame'));
    var previewApi = frame && frame.dataset.loaded === 'true' && frame.contentWindow
      ? frame.contentWindow.SIMPPrintPreview
      : null;
    if (!previewApi || typeof previewApi.getZoom !== 'function' || typeof previewApi.setZoom !== 'function') {
      reportPreviewAlert('Pratinjau masih disiapkan. Tunggu sebentar lalu coba kembali.');
      return;
    }

    var delta = parseInt(zoomButton.getAttribute('data-report-zoom'), 10) || 0;
    var percent = previewApi.setZoom(previewApi.getZoom() + delta);
    updateReportZoomControls(frame, percent, true);
  });

  document.addEventListener('click', function (event) {
    var fileLink = event.target.closest('[data-report-file-download]');
    if (!fileLink) return;
    if (fileLink.dataset.busy === 'true') {
      event.preventDefault();
      return;
    }
    fileLink.dataset.busy = 'true';
    fileLink.setAttribute('aria-busy', 'true');
    var fileOriginalText = fileLink.innerHTML;
    var fileLabel = fileLink.getAttribute('data-report-file-label') || 'File';
    fileLink.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> Menyiapkan ' + fileLabel + '...';
    window.setTimeout(function () {
      fileLink.dataset.busy = 'false';
      fileLink.removeAttribute('aria-busy');
      fileLink.innerHTML = fileOriginalText;
    }, 5000);
  });

  document.addEventListener('click', function (event) {
    var printButton = event.target.closest('[data-report-print]');
    if (!printButton) return;
    event.preventDefault();

    var frame = document.getElementById(printButton.getAttribute('data-report-print'));
    if (!frame || frame.dataset.loaded !== 'true' || !frame.contentWindow) {
      reportPreviewAlert('Pratinjau masih disiapkan. Tunggu sebentar lalu pilih Cetak kembali.');
      return;
    }

    try {
      frame.contentWindow.focus();
      frame.contentWindow.print();
    } catch (error) {
      window.open(frame.src, '_blank', 'noopener');
      reportPreviewAlert('Pratinjau dibuka di tab baru. Gunakan menu Cetak dari tab tersebut.');
    }
  });
})();
