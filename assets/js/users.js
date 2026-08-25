(function () {
  'use strict';

  var form = document.getElementById('user-modal-form');
  if (!form) return;
  var config = window.USER_MODAL_CONFIG || {};
  var modalId = 'user-modal';
  var openerId = 'user-modal-opener';
  var roleSelect = document.getElementById('user-modal-role');
  var permissionBoxes = Array.prototype.slice.call(document.querySelectorAll('.user-modal-permission'));
  var submitButton = form.querySelector('[data-user-modal-submit]');
  var editing = false;

  function jsonAttribute(element, name) {
    try { return JSON.parse(element.getAttribute(name) || '{}'); } catch (error) { return {}; }
  }
  function openModal() { var opener = document.getElementById(openerId); if (opener) opener.click(); }
  function closeModal() { var closer = document.querySelector('#' + modalId + ' .close-menu'); if (closer) closer.click(); }
  function syncCsrf(payload) {
    if (!payload || !payload.csrf) return;
    var name = payload.csrf.name || (window.SIMP && window.SIMP.csrfName);
    var hash = payload.csrf.hash || '';
    if (window.SIMP) { window.SIMP.csrfName = name || window.SIMP.csrfName; window.SIMP.csrfHash = hash || window.SIMP.csrfHash; }
    if (name && hash) Array.prototype.slice.call(document.getElementsByName(name)).forEach(function (field) { field.value = hash; field.defaultValue = hash; });
  }
  function syncFormCsrf() {
    if (!window.SIMP || !window.SIMP.csrfName) return;
    var field = form.elements[window.SIMP.csrfName];
    if (field) field.value = window.SIMP.csrfHash;
  }
  function notify(message, options) {
    if (typeof window.simpAlert === 'function') return window.simpAlert(message, options || {});
    return Promise.resolve();
  }
  function requestForm() {
    var headers = {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'};
    if (window.SIMP && window.SIMP.csrfHash) headers['X-CSRF-TOKEN'] = window.SIMP.csrfHash;
    return fetch(form.action, {method: 'POST', credentials: 'same-origin', headers: headers, body: new FormData(form)})
      .then(function (response) { return response.text().then(function (text) {
        var payload;
        try { payload = JSON.parse(text); } catch (error) { throw new Error('Respons server tidak dapat dibaca. Silakan masuk kembali.'); }
        syncCsrf(payload);
        if (!response.ok || payload.success === false) throw new Error(payload.message || 'Data pengguna gagal disimpan.');
        return payload;
      }); });
  }
  function refreshList() {
    return fetch(window.location.href, {credentials: 'same-origin', cache: 'no-store', headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html'}})
      .then(function (response) { if (!response.ok) throw new Error('Daftar pengguna gagal diperbarui.'); return response.text(); })
      .then(function (html) {
        var parsed = new DOMParser().parseFromString(html, 'text/html');
        var current = document.getElementById('user-content');
        var next = parsed.getElementById('user-content');
        if (!current || !next) throw new Error('Bagian daftar pengguna tidak ditemukan.');
        current.replaceWith(document.importNode(next, true));
      });
  }
  function setValue(name, value) {
    var field = form.elements[name];
    if (!field) return;
    if (field.type === 'checkbox') field.checked = Number(value) === 1;
    else field.value = value === null || typeof value === 'undefined' ? '' : value;
  }
  function defaultsForRole() {
    if (!roleSelect) return [];
    var map = config.roleDefaults || {};
    return (map[String(roleSelect.value)] || map[roleSelect.value] || []).map(Number);
  }
  function isSuperAdmin() {
    if (!roleSelect) return false;
    var option = roleSelect.options[roleSelect.selectedIndex];
    return !!(option && option.dataset.roleSlug === 'super-admin');
  }
  function applyPermissions(ids) {
    var selected = (ids || []).map(Number);
    permissionBoxes.forEach(function (box) { box.checked = selected.indexOf(Number(box.value)) !== -1; });
    var locked = isSuperAdmin();
    if (locked) permissionBoxes.forEach(function (box) { box.checked = true; });
    permissionBoxes.forEach(function (box) { box.disabled = locked; });
    var defaultsButton = document.getElementById('user-modal-role-defaults');
    if (defaultsButton) defaultsButton.disabled = locked;
  }
  function setBusy(busy) {
    if (!submitButton) return;
    if (busy) { submitButton.dataset.originalHtml = submitButton.innerHTML; submitButton.disabled = true; submitButton.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i>Menyimpan...'; }
    else { submitButton.disabled = false; submitButton.innerHTML = submitButton.dataset.originalHtml || '<i class="fa fa-save me-1"></i>Simpan Pengguna'; }
  }
  function prepare(button) {
    editing = button.dataset.mode === 'edit';
    var user = editing ? jsonAttribute(button, 'data-user') : {};
    form.reset();
    syncFormCsrf();
    form.action = button.dataset.action || form.action;
    document.getElementById('user-modal-title').textContent = editing ? 'Ubah Pengguna' : 'Tambah Pengguna';
    setValue('name', editing ? user.name : '');
    setValue('username', editing ? user.username : '');
    setValue('email', editing ? user.email : '');
    setValue('phone', editing ? user.phone : '');
    setValue('role_id', editing ? user.role_id : (roleSelect.options[0] ? roleSelect.options[0].value : ''));
    setValue('is_active', editing ? user.is_active : 1);
    setValue('password', '');
    setValue('password_confirmation', '');
    var passwordRequired = !editing;
    form.elements.password.required = passwordRequired;
    form.elements.password_confirmation.required = passwordRequired;
    document.querySelectorAll('[data-user-password-required]').forEach(function (label) { label.textContent = passwordRequired ? '*' : ''; });
    applyPermissions(editing ? user.permission_ids : defaultsForRole());
    openModal();
    window.setTimeout(function () { var input = document.getElementById('user-modal-name'); if (input) input.focus(); }, 80);
  }

  document.addEventListener('click', function (event) {
    var opener = event.target.closest('[data-user-modal-open]');
    if (opener) { event.preventDefault(); prepare(opener); return; }
    var defaults = event.target.closest('#user-modal-role-defaults');
    if (defaults) { event.preventDefault(); applyPermissions(defaultsForRole()); return; }
    var toggle = event.target.closest('.user-modal-toggle-module');
    if (toggle) {
      event.preventDefault();
      var boxes = Array.prototype.slice.call(toggle.closest('.card').querySelectorAll('.user-modal-permission'));
      var selectAll = boxes.some(function (box) { return !box.checked; });
      if (!isSuperAdmin()) boxes.forEach(function (box) { box.checked = selectAll; });
    }
  });
  if (roleSelect) roleSelect.addEventListener('change', function () { applyPermissions(defaultsForRole()); });

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    if (form.dataset.submitting === '1') return;
    if (!form.checkValidity()) { form.reportValidity(); return; }
    form.dataset.submitting = '1';
    setBusy(true);
    requestForm().then(function (payload) {
      closeModal();
      return refreshList().then(function () {
        return notify(payload.message, {title: 'Berhasil', tone: 'success'});
      }, function () {
        return notify(payload.message + ' Tampilan belum diperbarui; jangan kirim ulang data.', {title: 'Data Sudah Tersimpan', tone: 'warning'});
      });
    }, function (error) {
      return notify(error.message, {title: 'Pengguna Gagal Disimpan', tone: 'danger'}).then(function () { openModal(); });
    }).then(function () { form.dataset.submitting = '0'; setBusy(false); });
  });
})();
