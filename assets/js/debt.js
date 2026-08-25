(function () {
  'use strict';

  function byId(id) { return document.getElementById(id); }

  function updateCsrf(payload) {
    if (!payload || !payload.csrf) return;
    var name = payload.csrf.name || (window.SIMP && window.SIMP.csrfName);
    var hash = payload.csrf.hash || '';
    if (window.SIMP) {
      window.SIMP.csrfName = name || window.SIMP.csrfName;
      window.SIMP.csrfHash = hash || window.SIMP.csrfHash;
    }
    if (!name || !hash) return;
    Array.prototype.slice.call(document.getElementsByName(name)).forEach(function (field) {
      field.value = hash;
      field.defaultValue = hash;
    });
  }

  function syncCsrf(form) {
    if (!form || !window.SIMP || !window.SIMP.csrfName) return;
    var field = form.elements[window.SIMP.csrfName];
    if (field) field.value = window.SIMP.csrfHash;
  }

  function parseResponse(response) {
    return response.text().then(function (text) {
      var payload;
      try { payload = JSON.parse(text); }
      catch (error) {
        if (response.status === 401 || /login|masuk/i.test(text)) throw new Error('Sesi Anda telah berakhir. Silakan masuk kembali.');
        if (response.status === 403) throw new Error('Anda tidak memiliki izin untuk tindakan ini.');
        throw new Error('Respons server tidak dapat dibaca. Silakan muat ulang halaman.');
      }
      updateCsrf(payload);
      if (!response.ok || payload.success === false) throw new Error(payload.message || 'Transaksi hutang gagal diproses.');
      return payload;
    });
  }

  function requestForm(form) {
    var headers = {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'};
    if (window.SIMP && window.SIMP.csrfHash) headers['X-CSRF-TOKEN'] = window.SIMP.csrfHash;
    syncCsrf(form);
    return fetch(form.action, {method: 'POST', headers: headers, body: new FormData(form), credentials: 'same-origin'}).then(parseResponse);
  }

  function openMenu(openerId) {
    var opener = byId(openerId);
    if (opener) opener.click();
  }

  function closeMenu(menuId) {
    var closer = document.querySelector('#' + menuId + ' .close-menu');
    if (closer) closer.click();
  }

  function notify(message, options) {
    if (typeof window.simpAlert === 'function') return window.simpAlert(message, options || {});
    var inline = byId('debt-inline-message');
    if (inline) { inline.textContent = message; inline.className = 'alert alert-small rounded-s bg-red-dark color-white ms-3 me-3'; }
    return Promise.resolve();
  }

  function setBusy(button, busy, normalHtml) {
    if (!button) return;
    if (busy) {
      button.dataset.debtOriginalHtml = button.innerHTML;
      button.disabled = true;
      button.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i>Menyimpan...';
    } else {
      button.disabled = false;
      button.innerHTML = button.dataset.debtOriginalHtml || normalHtml;
    }
  }

  function refreshContent() {
    return fetch(window.location.href, {
      headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html'},
      credentials: 'same-origin', cache: 'no-store'
    }).then(function (response) {
      if (!response.ok) throw new Error('Daftar hutang gagal diperbarui.');
      return response.text();
    }).then(function (html) {
      var parsed = new DOMParser().parseFromString(html, 'text/html');
      var current = byId('debt-content');
      var next = parsed.getElementById('debt-content');
      if (!current || !next) throw new Error('Bagian daftar hutang tidak ditemukan.');
      current.replaceWith(document.importNode(next, true));
      bindForms();
    });
  }

  function parseData(element, name) {
    try { return JSON.parse(element.getAttribute(name) || '{}'); }
    catch (error) { return {}; }
  }

  var createForm = null;
  var payForm = null;
  var accountOptions = [];

  function setCreateValue(name, value) {
    if (!createForm || !createForm.elements[name]) return;
    createForm.elements[name].value = value === null || typeof value === 'undefined' ? '' : value;
  }

  function setPrincipalLock(locked) {
    var amount = byId('debt-amount');
    var help = byId('debt-amount-help');
    if (amount) {
      amount.readOnly = !!locked;
      amount.setAttribute('aria-readonly', locked ? 'true' : 'false');
    }
    if (help) help.classList.toggle('d-none', !locked);
  }

  function prepareCreate() {
    if (!createForm) return;
    createForm.reset();
    syncCsrf(createForm);
    createForm.action = createForm.getAttribute('data-create-action') || createForm.action;
    createForm.dataset.debtMode = 'create';
    var title = byId('debt-create-title');
    if (title) title.textContent = 'Tambah Hutang';
    setPrincipalLock(false);
    setCreateValue('debt_date', localIsoDate());
    var submit = byId('debt-create-submit');
    if (submit) submit.innerHTML = '<i class="fa fa-save me-1"></i> Simpan Hutang';
  }

  function prepareEdit(button) {
    if (!createForm) return;
    var debt = parseData(button, 'data-debt');
    if (!debt.id) return;
    createForm.reset();
    syncCsrf(createForm);
    var prefix = createForm.getAttribute('data-update-prefix') || '';
    createForm.action = prefix.replace(/\/$/, '') + '/' + encodeURIComponent(debt.id) + '/ubah';
    createForm.dataset.debtMode = 'edit';
    var title = byId('debt-create-title');
    if (title) title.textContent = 'Ubah Hutang';
    setCreateValue('creditor', debt.creditor);
    setCreateValue('debt_date', debt.debt_date);
    setCreateValue('category_id', debt.category_id);
    setCreateValue('event_id', debt.event_id);
    setCreateValue('principal_amount', debt.principal_amount);
    setCreateValue('description', debt.description);
    setCreateValue('note', debt.note);
    setPrincipalLock(Number(debt.lock_principal) === 1);
    var submit = byId('debt-create-submit');
    if (submit) submit.innerHTML = '<i class="fa fa-save me-1"></i> Simpan Perubahan';
    openMenu('debt-create-opener');
    window.setTimeout(function () { var field = byId('debt-creditor'); if (field) field.focus(); }, 80);
  }

  function bindForms() {
    createForm = byId('debt-create-form');
    payForm = byId('debt-pay-form');
    accountOptions = [];
    if (payForm) {
      try { accountOptions = JSON.parse(payForm.getAttribute('data-accounts') || '[]'); }
      catch (error) { accountOptions = []; }
      var method = byId('debt-pay-method');
      if (method && !method.dataset.debtBound) {
        method.dataset.debtBound = '1';
        method.addEventListener('change', function () {
          clearIncompatibleAccount();
          updateProofRequirement();
        });
      }
      var account = byId('debt-pay-account');
      if (account && !account.dataset.debtBound) {
        account.dataset.debtBound = '1';
        account.addEventListener('change', function () {
          syncMethodFromAccount();
          updateProofRequirement();
        });
      }
    }
    if (createForm && !createForm.dataset.debtBound) {
      createForm.dataset.debtBound = '1';
      createForm.addEventListener('submit', function (event) {
        event.preventDefault();
        var editing = createForm.dataset.debtMode === 'edit';
        submitAjaxForm(
          createForm,
          byId('debt-create-submit'),
          editing ? 'Perubahan Hutang Gagal Disimpan' : 'Hutang Gagal Disimpan',
          'debt-create-opener',
          editing ? '<i class="fa fa-save me-1"></i> Simpan Perubahan' : '<i class="fa fa-save me-1"></i> Simpan Hutang',
          editing ? 'Hutang Diperbarui' : 'Hutang Tersimpan'
        );
      });
    }
    if (payForm && !payForm.dataset.debtBound) {
      payForm.dataset.debtBound = '1';
      payForm.addEventListener('submit', function (event) {
        event.preventDefault();
        submitAjaxForm(payForm, byId('debt-pay-submit'), 'Pembayaran Hutang Gagal', 'debt-pay-opener', '<i class="fa fa-save me-1"></i> Simpan Pembayaran', 'Pembayaran Tersimpan');
      });
    }
  }

  function populateAccounts() {
    if (!payForm) return;
    var select = byId('debt-pay-account');
    var previous = select.value;
    select.innerHTML = '<option value="">Pilih akun</option>';
    accountOptions.forEach(function (account) {
      var option = document.createElement('option');
      option.value = account.id;
      option.dataset.accountType = account.type;
      option.textContent = account.name + ' · ' + accountTypeLabel(account.type);
      select.appendChild(option);
    });
    if (Array.prototype.some.call(select.options, function (option) { return option.value === previous; })) select.value = previous;
  }

  function accountTypeLabel(type) {
    if (type === 'cash') return 'Tunai';
    if (type === 'bank') return 'Bank';
    if (type === 'personal') return 'Rekening Titipan';
    if (type === 'qris') return 'QRIS';
    return 'Akun';
  }

  function methodForAccountType(type) {
    if (type === 'cash') return 'cash';
    if (type === 'bank' || type === 'personal') return 'transfer';
    if (type === 'qris') return 'qris';
    return '';
  }

  function selectedAccountType() {
    var select = byId('debt-pay-account');
    if (!select || select.selectedIndex < 0) return '';
    return select.options[select.selectedIndex].dataset.accountType || '';
  }

  function syncMethodFromAccount() {
    var method = byId('debt-pay-method');
    var requiredMethod = methodForAccountType(selectedAccountType());
    if (method && requiredMethod) method.value = requiredMethod;
  }

  function clearIncompatibleAccount() {
    var select = byId('debt-pay-account');
    var method = byId('debt-pay-method');
    if (!select || !method || !select.value) return;
    if (methodForAccountType(selectedAccountType()) !== method.value) select.value = '';
  }

  function updateProofRequirement() {
    if (!payForm) return;
    var method = byId('debt-pay-method').value;
    var proof = byId('debt-pay-proof');
    var wrap = byId('debt-pay-proof-wrap');
    var required = method !== 'cash';
    proof.required = required;
    wrap.classList.toggle('border-red-dark', required);
    var marker = wrap.querySelector('em');
    if (marker) marker.textContent = required ? '*' : '';
  }

  function updateDebtFeeRule() {
    var fee = byId('debt-pay-fee');
    if (!fee) return;
    fee.value = '0';
    fee.readOnly = true;
  }

  function preparePayment(button) {
    if (!payForm) return;
    var debt = parseData(button, 'data-debt');
    if (!debt.id) return;
    payForm.reset();
    syncCsrf(payForm);
    var paymentDate = byId('debt-pay-date');
    if (paymentDate) paymentDate.value = localIsoDate();
    var prefix = payForm.getAttribute('data-action-prefix') || '';
    payForm.action = prefix.replace(/\/$/, '') + '/' + encodeURIComponent(debt.id) + '/bayar';
    byId('debt-pay-id').value = debt.id;
    byId('debt-pay-creditor').textContent = debt.creditor || debt.debt_no || '-';
    byId('debt-pay-remaining').innerHTML = 'Sisa hutang: <strong>' + formatRupiah(debt.remaining || 0) + '</strong>';
    var amount = byId('debt-pay-amount');
    amount.max = debt.remaining || '0';
    amount.value = debt.remaining || '';
    populateAccounts();
    updateProofRequirement();
    updateDebtFeeRule();
    openMenu('debt-pay-opener');
    window.setTimeout(function () { var field = byId('debt-pay-date'); if (field) field.focus(); }, 80);
  }

  function formatRupiah(value) {
    var number = Number(value || 0);
    if (!isFinite(number)) number = 0;
    return 'Rp ' + new Intl.NumberFormat('id-ID', {maximumFractionDigits: 0}).format(number);
  }

  function localIsoDate() {
    var now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    return now.toISOString().slice(0, 10);
  }

  document.addEventListener('click', function (event) {
    var createButton = event.target.closest('[data-debt-create-open]');
    if (createButton) {
      event.preventDefault();
      prepareCreate();
      openMenu('debt-create-opener');
      window.setTimeout(function () { var field = byId('debt-creditor'); if (field) field.focus(); }, 80);
      return;
    }
    var editButton = event.target.closest('[data-debt-edit-open]');
    if (editButton) {
      event.preventDefault();
      prepareEdit(editButton);
      return;
    }
    var payButton = event.target.closest('[data-debt-pay-open]');
    if (payButton) { event.preventDefault(); preparePayment(payButton); }
  });

  function submitAjaxForm(form, button, errorTitle, reopenId, normalButtonHtml, successTitle) {
    if (form.dataset.submitting === '1') return;
    if (!form.checkValidity()) { form.reportValidity(); return; }
    form.dataset.submitting = '1';
    setBusy(button, true, normalButtonHtml);
    requestForm(form).then(function (payload) {
      closeMenu(reopenId.replace('-opener', '-modal'));
      return refreshContent().then(function () {
        return notify(payload.message || 'Data berhasil disimpan.', {title: successTitle || 'Berhasil', tone: 'success'});
      }, function () {
        return notify((payload.message || 'Data berhasil disimpan.') + ' Tampilan belum diperbarui; jangan kirim ulang data.', {title: 'Data Sudah Tersimpan', tone: 'warning'});
      });
    }).catch(function (error) {
      return notify(error.message, {title: errorTitle, tone: 'danger'}).then(function () { openMenu(reopenId); });
    }).then(function () {
      delete form.dataset.submitting;
      setBusy(button, false, normalButtonHtml);
    });
  }

  document.addEventListener('submit', function (event) {
    var cancelForm = event.target.closest('[data-debt-cancel-form]');
    if (cancelForm) {
      event.preventDefault();
      var cancelButton = cancelForm.querySelector('button[type="submit"]');
      setBusy(cancelButton, true, '<i class="fa fa-ban me-1"></i> Batalkan');
      requestForm(cancelForm).then(function (payload) {
        return refreshContent().then(function () { return notify(payload.message || 'Hutang berhasil dibatalkan.', {title: 'Berhasil', tone: 'success'}); });
      }).catch(function (error) { return notify(error.message, {title: 'Pembatalan Gagal', tone: 'danger'}); })
        .then(function () { setBusy(cancelButton, false, '<i class="fa fa-ban me-1"></i> Batalkan'); });
      return;
    }
    var form = event.target.closest('[data-debt-payment-status-form]');
    if (!form) return;
    event.preventDefault();
    var button = form.querySelector('button[type="submit"]');
    var originalButtonHtml = button ? button.innerHTML : '';
    setBusy(button, true, originalButtonHtml);
    requestForm(form).then(function (payload) {
      return refreshContent().then(function () { return notify(payload.message || 'Pembayaran diverifikasi.', {title: 'Berhasil', tone: 'success'}); });
    }).catch(function (error) { return notify(error.message, {title: 'Verifikasi Gagal', tone: 'danger'}); })
      .then(function () { setBusy(button, false, originalButtonHtml); });
  });

  // Bind once on the initial page; refreshContent calls this defensively too.
  bindForms();
  if (createForm) prepareCreate();
})();
