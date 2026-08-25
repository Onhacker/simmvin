(function () {
  'use strict';

  function all(selector) { return Array.prototype.slice.call(document.querySelectorAll(selector)); }

  function moneyRaw(target) {
    return window.SimpMoney && typeof window.SimpMoney.raw === 'function'
      ? (window.SimpMoney.raw(target) || target)
      : target;
  }

  function moneyDisplay(target) {
    return window.SimpMoney && typeof window.SimpMoney.display === 'function'
      ? (window.SimpMoney.display(target) || target)
      : target;
  }

  function setMoney(target, value) {
    if (window.SimpMoney && typeof window.SimpMoney.set === 'function') {
      window.SimpMoney.set(moneyRaw(target), value);
      return;
    }
    if (target) target.value = value === null || typeof value === 'undefined' ? '' : value;
  }

  function setMoneyControl(target, properties) {
    var raw = moneyRaw(target);
    var display = moneyDisplay(target);
    Object.keys(properties || {}).forEach(function (property) {
      if (raw) raw[property] = properties[property];
      if (display && display !== raw) display[property] = properties[property];
    });
  }

  function refreshMoney(scope) {
    if (window.SimpMoney && typeof window.SimpMoney.refresh === 'function') window.SimpMoney.refresh(scope);
  }

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
    if (!isTransfer && input) { setMoney(input, '0'); setMoneyControl(input, {readOnly: true}); }
    else if (input) setMoneyControl(input, {readOnly: false});
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
        ? 'Bukti diperlukan untuk metode Transfer/QRIS. JPG, PNG, atau PDF; maksimal 5 MB.'
        : 'Bukti dapat dilampirkan untuk metode Tunai. JPG, PNG, atau PDF; maksimal 5 MB.';
    }
  }
  if (method) { method.addEventListener('change', toggleLegacyExpenseProof); toggleLegacyExpenseProof(); }

  var transfer = document.querySelector('.js-transfer-form');
  if (transfer) transfer.addEventListener('submit', function (event) {
    refreshMoney(transfer);
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
      if (expenseProofLabel) expenseProofLabel.textContent = required ? '*' : '';
    }

    function updateExpenseFeeRule() {
      if (!expenseMethod || !expenseFee) return;
      var isTransfer = expenseMethod.value === 'transfer';
      setMoneyControl(expenseFee, {readOnly: !isTransfer});
      if (!isTransfer) setMoney(expenseFee, '0');
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
      refreshMoney(expenseForm);
      var opener = document.querySelector('[data-menu="expense-add-modal"]');
      if (opener) opener.click();
    });

    expenseMethod.addEventListener('change', function () { filterExpenseAccounts(); updateExpenseProofRequirement(); updateExpenseFeeRule(); });
    filterExpenseAccounts();
    updateExpenseProofRequirement();
    updateExpenseFeeRule();

    expenseForm.addEventListener('submit', function (event) {
      event.preventDefault();
      refreshMoney(expenseForm);
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
    var normalized = Math.max(50, Math.min(400, parseInt(percent, 10) || 100));
    var label = modal.querySelector('[data-report-zoom-value]');
    if (label) label.textContent = normalized + '%';
    modal.querySelectorAll('[data-report-zoom]').forEach(function (button) {
      var delta = parseInt(button.getAttribute('data-report-zoom'), 10) || 0;
      button.disabled = !ready || (delta < 0 && normalized <= 50) || (delta > 0 && normalized >= 400);
    });
  }

  function reportTouchDistance(touches) {
    if (!touches || touches.length < 2) return 0;
    var dx = touches[0].clientX - touches[1].clientX;
    var dy = touches[0].clientY - touches[1].clientY;
    return Math.sqrt((dx * dx) + (dy * dy));
  }

  /*
   * Reports also load a small standalone pinch handler. This parent-side
   * fallback covers cached/older report documents and keeps the percentage
   * label in sync when a preview is embedded in an application modal.
   */
  function installReportPinchZoom(frame, previewApi) {
    if (!frame || !previewApi || typeof previewApi.getZoom !== 'function' || typeof previewApi.setZoom !== 'function') return;
    var reportDocument;
    try { reportDocument = frame.contentDocument; } catch (error) { reportDocument = null; }
    if (!reportDocument || !reportDocument.documentElement) return;

    var surface = reportDocument.documentElement;
    if (surface.getAttribute('data-simp-pinch-zoom') === 'true') return;
    surface.setAttribute('data-simp-pinch-zoom', 'true');
    surface.style.touchAction = 'pan-x pan-y';
    if (reportDocument.body) reportDocument.body.style.touchAction = 'pan-x pan-y';

    var pinch = null;
    var webkitGestureZoom = null;
    function start(event) {
      if (webkitGestureZoom !== null) return;
      if (!event.touches || event.touches.length !== 2) return;
      var distance = reportTouchDistance(event.touches);
      if (distance <= 0) return;
      pinch = {distance: distance, zoom: previewApi.getZoom()};
      if (event.cancelable) event.preventDefault();
    }
    function move(event) {
      if (webkitGestureZoom !== null) return;
      if (!pinch || !event.touches || event.touches.length < 2) return;
      var distance = reportTouchDistance(event.touches);
      if (distance <= 0) return;
      var percent = previewApi.setZoom(pinch.zoom * distance / pinch.distance);
      updateReportZoomControls(frame, percent, true);
      if (event.cancelable) event.preventDefault();
    }
    function end(event) {
      if (!event.touches || event.touches.length < 2) pinch = null;
    }
    function startWebkitGesture(event) {
      if (typeof event.scale !== 'number') return;
      pinch = null;
      webkitGestureZoom = previewApi.getZoom();
      if (event.cancelable) event.preventDefault();
    }
    function changeWebkitGesture(event) {
      if (webkitGestureZoom === null || typeof event.scale !== 'number') return;
      var percent = previewApi.setZoom(webkitGestureZoom * event.scale);
      updateReportZoomControls(frame, percent, true);
      if (event.cancelable) event.preventDefault();
    }
    function endWebkitGesture() { webkitGestureZoom = null; }
    reportDocument.addEventListener('touchstart', start, {capture:true, passive:false});
    reportDocument.addEventListener('touchmove', move, {capture:true, passive:false});
    reportDocument.addEventListener('touchend', end, {capture:true, passive:true});
    reportDocument.addEventListener('touchcancel', end, {capture:true, passive:true});
    reportDocument.addEventListener('gesturestart', startWebkitGesture, {capture:true, passive:false});
    reportDocument.addEventListener('gesturechange', changeWebkitGesture, {capture:true, passive:false});
    reportDocument.addEventListener('gestureend', endWebkitGesture, {capture:true, passive:true});
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
      if (validDocument && previewApi) installReportPinchZoom(frame, previewApi);
      if (!shell) return;
      shell.classList.toggle('is-loaded', validDocument);
      shell.classList.toggle('is-error', !validDocument);
      if (!validDocument) {
        var loading = shell.querySelector('[data-report-preview-loading]');
        if (loading) loading.innerHTML = '<i class="fa fa-exclamation-circle color-red-dark"></i><span>Pratinjau gagal dimuat. Muat ulang halaman lalu coba kembali.</span>';
      }
    });
  });

  // The standalone report handler posts zoom changes so the modal toolbar
  // remains accurate while the user pinches inside the iframe.
  window.addEventListener('message', function (event) {
    var payload = event && event.data;
    if (!payload || payload.type !== 'simp-print-zoom' || !event.source) return;
    all('[data-report-preview-frame]').some(function (frame) {
      if (!frame.contentWindow || event.source !== frame.contentWindow) return false;
      updateReportZoomControls(frame, payload.percent, true);
      return true;
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
