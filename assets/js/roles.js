(function () {
  'use strict';

  var form = document.getElementById('role-modal-form');
  if (!form) return;
  var config = window.ROLE_MODAL_CONFIG || {};
  var submitButton = form.querySelector('[data-role-modal-submit]');
  var permissionBoxes = Array.prototype.slice.call(document.querySelectorAll('.role-modal-permission'));

  function parseRole(button) {
    try { return JSON.parse(button.getAttribute('data-role') || '{}'); } catch (error) { return {}; }
  }
  function openModal() { var opener = document.getElementById('role-modal-opener'); if (opener) opener.click(); }
  function closeModal() { var closer = document.querySelector('#role-modal .close-menu'); if (closer) closer.click(); }
  function syncCsrf(payload) {
    if (!payload || !payload.csrf) return;
    var name = payload.csrf.name || (window.SIMP && window.SIMP.csrfName), hash = payload.csrf.hash || '';
    if (window.SIMP) { window.SIMP.csrfName = name || window.SIMP.csrfName; window.SIMP.csrfHash = hash || window.SIMP.csrfHash; }
    if (name && hash) Array.prototype.slice.call(document.getElementsByName(name)).forEach(function (field) { field.value = hash; field.defaultValue = hash; });
  }
  function syncFormCsrf() { if (window.SIMP && window.SIMP.csrfName && form.elements[window.SIMP.csrfName]) form.elements[window.SIMP.csrfName].value = window.SIMP.csrfHash; }
  function notify(message, options) { if (typeof window.simpAlert === 'function') return window.simpAlert(message, options || {}); return Promise.resolve(); }
  function requestForm() {
    var headers = {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'};
    if (window.SIMP && window.SIMP.csrfHash) headers['X-CSRF-TOKEN'] = window.SIMP.csrfHash;
    return fetch(form.action, {method: 'POST', credentials: 'same-origin', headers: headers, body: new FormData(form)}).then(function (response) {
      return response.text().then(function (text) {
        var payload;
        try { payload = JSON.parse(text); } catch (error) { throw new Error('Respons server tidak dapat dibaca. Silakan masuk kembali.'); }
        syncCsrf(payload);
        if (!response.ok || payload.success === false) throw new Error(payload.message || 'Hak akses gagal disimpan.');
        return payload;
      });
    });
  }
  function refreshList() {
    return fetch(window.location.href, {credentials: 'same-origin', cache: 'no-store', headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html'}}).then(function (response) {
      if (!response.ok) throw new Error('Daftar peran gagal diperbarui.');
      return response.text();
    }).then(function (html) {
      var parsed = new DOMParser().parseFromString(html, 'text/html'), current = document.getElementById('role-content'), next = parsed.getElementById('role-content');
      if (!current || !next) throw new Error('Bagian daftar peran tidak ditemukan.');
      current.replaceWith(document.importNode(next, true));
    });
  }
  function setValue(name, value) { var field = form.elements[name]; if (field) field.value = value === null || typeof value === 'undefined' ? '' : value; }
  function setPermissions(ids, locked) {
    var selected = (ids || []).map(Number);
    permissionBoxes.forEach(function (box) { box.checked = locked || selected.indexOf(Number(box.value)) !== -1; box.disabled = !!locked; });
    var clear = document.getElementById('role-modal-clear');
    if (clear) clear.disabled = !!locked;
    document.querySelectorAll('.role-modal-toggle-module').forEach(function (button) { button.disabled = !!locked; });
  }
  function setBusy(busy) {
    if (!submitButton) return;
    if (busy) { submitButton.dataset.originalHtml = submitButton.innerHTML; submitButton.disabled = true; submitButton.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i>Menyimpan...'; }
    else { submitButton.disabled = false; submitButton.innerHTML = submitButton.dataset.originalHtml || '<i class="fa fa-save me-1"></i>Simpan Peran'; }
  }
  function prepare(button) {
    var role = parseRole(button), protectedIdentity = Number(role.is_system) === 1, superAdmin = role.slug === 'super-admin';
    form.reset();
    syncFormCsrf();
    form.action = button.dataset.action || form.action;
    setValue('name', role.name || ''); setValue('slug', role.slug || ''); setValue('description', role.description || '');
    document.getElementById('role-modal-title').textContent = 'Atur Hak Akses: ' + (role.name || 'Peran');
    document.getElementById('role-modal-name').readOnly = protectedIdentity;
    document.getElementById('role-modal-slug').readOnly = protectedIdentity;
    document.querySelectorAll('[data-role-identity-note]').forEach(function (note) { note.textContent = protectedIdentity ? '(dilindungi)' : '*'; });
    var note = document.getElementById('role-modal-system-note');
    if (note) note.textContent = superAdmin ? 'Super Admin selalu memiliki seluruh izin.' : 'Izin akan menjadi bawaan bagi semua pengguna dengan peran ini.';
    setPermissions(superAdmin ? (config.allPermissionIds || []) : role.permission_ids, superAdmin);
    openModal();
  }

  document.addEventListener('click', function (event) {
    var opener = event.target.closest('[data-role-modal-open]');
    if (opener) { event.preventDefault(); prepare(opener); return; }
    var clear = event.target.closest('#role-modal-clear');
    if (clear) { event.preventDefault(); if (!clear.disabled) permissionBoxes.forEach(function (box) { box.checked = false; }); return; }
    var toggle = event.target.closest('.role-modal-toggle-module');
    if (toggle && !toggle.disabled) {
      event.preventDefault();
      var boxes = Array.prototype.slice.call(toggle.closest('.card').querySelectorAll('.role-modal-permission'));
      var selectAll = boxes.some(function (box) { return !box.checked; });
      boxes.forEach(function (box) { box.checked = selectAll; });
    }
  });

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    if (form.dataset.submitting === '1') return;
    if (!form.checkValidity()) { form.reportValidity(); return; }
    form.dataset.submitting = '1'; setBusy(true);
    requestForm().then(function (payload) {
      closeModal();
      return refreshList().then(function () { return notify(payload.message, {title: 'Berhasil', tone: 'success'}); }, function () { return notify(payload.message + ' Tampilan belum diperbarui; jangan kirim ulang data.', {title: 'Data Sudah Tersimpan', tone: 'warning'}); });
    }, function (error) {
      return notify(error.message, {title: 'Peran Gagal Disimpan', tone: 'danger'}).then(openModal);
    }).then(function () { form.dataset.submitting = '0'; setBusy(false); });
  });
})();
