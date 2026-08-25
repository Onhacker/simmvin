(function () {
  'use strict';

  function byId(id) { return document.getElementById(id); }

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

  function isMoneyField(target) {
    return !!target && (target.hasAttribute('data-money') || moneyDisplay(target) !== target);
  }

  function setMoney(target, value) {
    if (window.SimpMoney && typeof window.SimpMoney.set === 'function') {
      window.SimpMoney.set(moneyRaw(target), value);
      return;
    }
    if (target) target.value = value === null || typeof value === 'undefined' ? '' : value;
  }

  function refreshMoney(scope) {
    if (window.SimpMoney && typeof window.SimpMoney.refresh === 'function') window.SimpMoney.refresh(scope);
  }

  function updateCsrf(payload) {
    if (!payload || !payload.csrf) return;
    var name = payload.csrf.name || (window.SIMP && window.SIMP.csrfName);
    var hash = payload.csrf.hash || '';
    if (window.SIMP) {
      window.SIMP.csrfName = name || window.SIMP.csrfName;
      window.SIMP.csrfHash = hash;
    }
    if (!name || !hash) return;
    Array.prototype.slice.call(document.getElementsByName(name)).forEach(function (field) {
      field.value = hash;
      field.defaultValue = hash;
    });
  }

  function syncFormCsrf(form) {
    if (!form || !window.SIMP || !window.SIMP.csrfName) return;
    var field = form.elements[window.SIMP.csrfName];
    if (field) field.value = window.SIMP.csrfHash;
  }

  function requestForm(form) {
    var headers = {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'};
    if (window.SIMP && window.SIMP.csrfHash) headers['X-CSRF-TOKEN'] = window.SIMP.csrfHash;
    return fetch(form.action, {
      method: 'POST',
      headers: headers,
      body: new FormData(form),
      credentials: 'same-origin'
    }).then(function (response) {
      return response.text().then(function (text) {
        var payload;
        try { payload = JSON.parse(text); }
        catch (error) { throw new Error('Respons server tidak dapat dibaca. Silakan masuk ulang atau muat halaman.'); }
        updateCsrf(payload);
        if (!response.ok || payload.success === false) throw new Error(payload.message || 'Data gagal disimpan.');
        return payload;
      });
    });
  }

  function refreshSection(id) {
    return fetch(window.location.href, {
      headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html'},
      credentials: 'same-origin',
      cache: 'no-store'
    }).then(function (response) {
      if (!response.ok) throw new Error('Tampilan data gagal diperbarui.');
      return response.text();
    }).then(function (html) {
      var parsed = new DOMParser().parseFromString(html, 'text/html');
      var current = byId(id);
      var next = parsed.getElementById(id);
      if (!current || !next) throw new Error('Bagian data yang diperbarui tidak ditemukan.');
      var replacement = document.importNode(next, true);
      current.replaceWith(replacement);
      if (window.SimpMoney && typeof window.SimpMoney.init === 'function') window.SimpMoney.init(replacement);
    });
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
    return Promise.resolve();
  }

  function setBusy(button, busy, fallbackHtml) {
    if (!button) return;
    if (busy) {
      button.dataset.originalHtml = button.innerHTML;
      button.disabled = true;
      button.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i>Menyimpan...';
      return;
    }
    button.disabled = false;
    button.innerHTML = button.dataset.originalHtml || fallbackHtml;
  }

  function parsePayload(button, attribute) {
    try { return JSON.parse(button.getAttribute(attribute) || '{}'); }
    catch (error) { return {}; }
  }

  var accountForm = byId('account-modal-form');
  if (accountForm) {
    var accountTitle = byId('account-modal-title');
    var accountType = byId('account-modal-type');
    var accountSubmit = accountForm.querySelector('[data-account-modal-submit]');

    function setAccountValue(name, value) {
      var field = accountForm.elements[name];
      if (!field) return;
      if (field.type === 'checkbox') field.checked = Number(value) === 1;
      else if (isMoneyField(field)) setMoney(field, value);
      else field.value = value === null || typeof value === 'undefined' ? '' : value;
    }

    function toggleAccountBankFields() {
      var hidden = accountType.value === 'cash';
      Array.prototype.slice.call(document.querySelectorAll('.account-modal-bank-field')).forEach(function (field) {
        field.classList.toggle('d-none', hidden);
        field.setAttribute('aria-hidden', hidden ? 'true' : 'false');
      });
    }

    function prepareAccount(button) {
      var editing = button.dataset.mode === 'edit';
      var account = editing ? parsePayload(button, 'data-account') : {};
      accountForm.reset();
      syncFormCsrf(accountForm);
      accountForm.action = button.dataset.action || accountForm.action;
      accountTitle.textContent = editing ? 'Ubah Akun Dana' : 'Tambah Akun Dana';
      setAccountValue('name', editing ? account.name : '');
      setAccountValue('type', editing ? account.type : 'bank');
      setAccountValue('bank_name', editing ? account.bank_name : '');
      setAccountValue('account_number', editing ? account.account_number : '');
      setAccountValue('account_holder', editing ? account.account_holder : '');
      setAccountValue('opening_balance', editing ? account.opening_balance : '0');
      setAccountValue('sort_order', editing ? account.sort_order : '0');
      setAccountValue('include_in_total', editing ? account.include_in_total : 1);
      setAccountValue('is_active', editing ? account.is_active : 1);
      refreshMoney(accountForm);
      accountSubmit.innerHTML = '<i class="fa fa-save me-1"></i>' + (editing ? 'Simpan Perubahan' : 'Simpan Akun');
      toggleAccountBankFields();
      openMenu('account-modal-opener');
      window.setTimeout(function () { var input = byId('account-modal-name'); if (input) input.focus(); }, 80);
    }

    document.addEventListener('click', function (event) {
      var button = event.target.closest('[data-account-modal-open]');
      if (!button) return;
      event.preventDefault();
      prepareAccount(button);
    });
    accountType.addEventListener('change', toggleAccountBankFields);
    toggleAccountBankFields();

    accountForm.addEventListener('submit', function (event) {
      event.preventDefault();
      refreshMoney(accountForm);
      if (!accountForm.checkValidity()) { accountForm.reportValidity(); return; }
      setBusy(accountSubmit, true, '<i class="fa fa-save me-1"></i>Simpan Akun');
      requestForm(accountForm).then(function (payload) {
        closeMenu('account-modal');
        return refreshSection('account-content').then(function () {
          return notify(payload.message, {title: 'Berhasil', tone: 'success'});
        }, function () {
          return notify(payload.message + ' Tampilan belum diperbarui; jangan kirim ulang data.', {title: 'Data Sudah Tersimpan', tone: 'warning'});
        });
      }, function (error) {
        return notify(error.message, {title: 'Akun Gagal Disimpan', tone: 'danger'}).then(function () {
          openMenu('account-modal-opener');
        });
      }).then(function () {
        setBusy(accountSubmit, false, '<i class="fa fa-save me-1"></i>Simpan Akun');
      });
    });
  }

  var positionForm = byId('position-modal-form');
  if (positionForm) {
    var positionTitle = byId('position-modal-title');
    var positionSubmit = positionForm.querySelector('[data-position-modal-submit]');

    function setPositionValue(name, value) {
      var field = positionForm.elements[name];
      if (!field) return;
      if (field.type === 'checkbox') field.checked = Number(value) === 1;
      else field.value = value === null || typeof value === 'undefined' ? '' : value;
    }

    function preparePosition(button) {
      var editing = button.dataset.mode === 'edit';
      var position = editing ? parsePayload(button, 'data-position') : {};
      positionForm.reset();
      syncFormCsrf(positionForm);
      positionForm.action = button.dataset.action || positionForm.action;
      positionTitle.textContent = editing ? 'Ubah Jabatan' : 'Tambah Jabatan';
      setPositionValue('name', editing ? position.name : '');
      setPositionValue('category', editing ? position.category : 'pemerintah_desa');
      setPositionValue('code', editing ? position.code : '');
      setPositionValue('sort_order', editing ? position.sort_order : '0');
      setPositionValue('description', editing ? position.description : '');
      setPositionValue('is_active', editing ? position.is_active : 1);
      positionSubmit.innerHTML = '<i class="fa fa-save me-1"></i>' + (editing ? 'Simpan Perubahan' : 'Simpan Jabatan');
      openMenu('position-modal-opener');
      window.setTimeout(function () { var input = byId('position-modal-name'); if (input) input.focus(); }, 80);
    }

    document.addEventListener('click', function (event) {
      var button = event.target.closest('[data-position-modal-open]');
      if (!button) return;
      event.preventDefault();
      preparePosition(button);
    });

    positionForm.addEventListener('submit', function (event) {
      event.preventDefault();
      if (!positionForm.checkValidity()) { positionForm.reportValidity(); return; }
      setBusy(positionSubmit, true, '<i class="fa fa-save me-1"></i>Simpan Jabatan');
      requestForm(positionForm).then(function (payload) {
        closeMenu('position-modal');
        return refreshSection('position-content').then(function () {
          return notify(payload.message, {title: 'Berhasil', tone: 'success'});
        }, function () {
          return notify(payload.message + ' Tampilan belum diperbarui; jangan kirim ulang data.', {title: 'Data Sudah Tersimpan', tone: 'warning'});
        });
      }, function (error) {
        return notify(error.message, {title: 'Jabatan Gagal Disimpan', tone: 'danger'}).then(function () {
          openMenu('position-modal-opener');
        });
      }).then(function () {
        setBusy(positionSubmit, false, '<i class="fa fa-save me-1"></i>Simpan Jabatan');
      });
    });
  }

  document.addEventListener('submit', function (event) {
    var form = event.target.closest('form[data-position-status-ajax]');
    if (!form) return;
    event.preventDefault();
    if (form.dataset.ajaxSubmitting === '1') return;
    form.dataset.ajaxSubmitting = '1';
    var button = event.submitter || form.querySelector('[type="submit"]');
    if (button) button.disabled = true;
    requestForm(form).then(function (payload) {
      return refreshSection('position-content').then(function () {
        return notify(payload.message, {title: 'Status Diperbarui', tone: 'success'});
      }, function () {
        return notify(payload.message + ' Tampilan belum diperbarui; jangan kirim ulang data.', {title: 'Data Sudah Tersimpan', tone: 'warning'});
      });
    }, function (error) {
      return notify(error.message, {title: 'Status Gagal Diperbarui', tone: 'danger'});
    }).then(function () {
      form.dataset.ajaxSubmitting = '0';
      if (button) button.disabled = false;
    });
  });
})();
