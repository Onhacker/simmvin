(function () {
  'use strict';

  var cfg = window.EVENT_FORM || {};
  var province = document.getElementById('province');
  var regencies = document.getElementById('regencies');
  var mode = document.getElementById('billing-mode');
  var list = document.getElementById('selected-regions');
  var add = document.getElementById('add-regencies');
  var form = document.getElementById('event-form');
  if (!province || !regencies || !mode || !list || !add || !form) return;

  var selected = (cfg.selectedRegions || []).slice();
  var villageFeeGroup = document.querySelector('.fee-village');
  var participantFeeGroup = document.querySelector('.fee-participant');
  var includedParticipantGroup = document.querySelector('.fee-included-participants');
  var villageFee = document.getElementById('village-fee');
  var participantFee = document.getElementById('participant-fee');
  var includedParticipantCount = document.getElementById('included-participant-count');
  var villageFeeLabel = document.getElementById('village-fee-label');
  var participantFeeLabel = document.getElementById('participant-fee-label');
  var billingPreview = document.getElementById('billing-formula-preview');

  function option(value, label) {
    var element = document.createElement('option');
    element.value = value;
    element.textContent = label;
    return element;
  }

  function currency(value) {
    return new Intl.NumberFormat('id-ID', {
      style: 'currency',
      currency: 'IDR',
      maximumFractionDigits: 0
    }).format(Number(value) || 0);
  }

  function positiveNumber(input) {
    var value = Number(input && input.value);
    return Number.isFinite(value) && value > 0 ? value : 0;
  }

  function updateBillingPreview() {
    if (!billingPreview) return;

    var baseFee = positiveNumber(villageFee);
    var perParticipantFee = positiveNumber(participantFee);
    var includedCount = Math.max(1, parseInt(includedParticipantCount && includedParticipantCount.value, 10) || 1);

    if (mode.value === 'per_village') {
      billingPreview.textContent = baseFee > 0
        ? 'Setiap desa memiliki tagihan tetap ' + currency(baseFee) + ', berapa pun jumlah pesertanya.'
        : 'Masukkan biaya tetap yang ditagihkan kepada setiap desa.';
      return;
    }
    if (mode.value === 'per_participant') {
      billingPreview.textContent = perParticipantFee > 0
        ? 'Total tagihan desa = jumlah peserta × ' + currency(perParticipantFee) + '.'
        : 'Masukkan biaya untuk setiap peserta.';
      return;
    }

    if (baseFee > 0 && perParticipantFee > 0) {
      billingPreview.textContent = 'Paket ' + currency(baseFee) + ' mencakup ' + includedCount +
        ' peserta. Peserta ke-' + (includedCount + 1) + ' dan seterusnya menambah ' +
        currency(perParticipantFee) + ' per orang. Contoh ' + (includedCount + 1) +
        ' peserta: ' + currency(baseFee + perParticipantFee) + '.';
    } else {
      billingPreview.textContent = 'Isi biaya paket desa, jumlah peserta dalam paket, dan biaya setiap peserta tambahan.';
    }
  }

  function updateFees() {
    var hybrid = mode.value === 'per_village_extra';
    var usesVillageFee = mode.value === 'per_village' || hybrid;
    var usesParticipantFee = mode.value === 'per_participant' || hybrid;

    villageFeeGroup.style.display = usesVillageFee ? '' : 'none';
    participantFeeGroup.style.display = usesParticipantFee ? '' : 'none';
    includedParticipantGroup.style.display = hybrid ? '' : 'none';

    villageFee.disabled = !usesVillageFee;
    villageFee.required = usesVillageFee;
    participantFee.disabled = !usesParticipantFee;
    participantFee.required = usesParticipantFee;
    includedParticipantCount.disabled = !hybrid;
    includedParticipantCount.required = hybrid;

    villageFeeLabel.textContent = hybrid ? 'Biaya Paket Desa' : 'Biaya Per Desa';
    participantFeeLabel.textContent = hybrid ? 'Biaya Peserta Tambahan' : 'Biaya Per Peserta';
    updateBillingPreview();
  }

  function render() {
    list.innerHTML = '';
    if (!selected.length) {
      list.innerHTML = '<div class="text-center py-3 opacity-50"><i class="fa fa-map-marked-alt font-24 d-block mb-2"></i>Belum ada wilayah dipilih.</div>';
      return;
    }

    selected.forEach(function (item, index) {
      var row = document.createElement('div');
      row.className = 'd-flex align-items-center py-2 border-bottom';

      var icon = document.createElement('i');
      icon.className = 'fa fa-map-marker-alt icon icon-s rounded-xl bg-highlight color-white me-3';

      var label = document.createElement('div');
      label.className = 'flex-grow-1';
      label.innerHTML = '<h6 class="font-14 font-600 mb-n1"></h6><span class="font-10 opacity-50"></span>';
      label.querySelector('h6').textContent = item.regency_name || item.regency_id;
      label.querySelector('span').textContent = item.province_name || item.province_id;

      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'icon icon-xxs rounded-xl bg-red-dark color-white shadow-s ms-2';
      button.setAttribute('aria-label', 'Hapus wilayah');
      button.innerHTML = '<i class="fas fa-times"></i>';
      button.addEventListener('click', function () {
        selected.splice(index, 1);
        render();
      });

      var hidden = document.createElement('input');
      hidden.type = 'hidden';
      hidden.name = 'region_pairs[]';
      hidden.value = JSON.stringify({
        province_id: String(item.province_id),
        regency_id: String(item.regency_id)
      });

      row.appendChild(icon);
      row.appendChild(label);
      row.appendChild(button);
      row.appendChild(hidden);
      list.appendChild(row);
    });
  }

  function loadRegencies() {
    regencies.innerHTML = '';
    if (!province.value) return;
    regencies.appendChild(option('', 'Memuat kabupaten/kota...'));
    simpFetch(window.SIMP.baseUrl + 'wilayah/kabupaten?province_id=' + encodeURIComponent(province.value))
      .then(function (response) {
        regencies.innerHTML = '';
        response.data.forEach(function (row) {
          regencies.appendChild(option(row.id, row.name));
        });
      })
      .catch(function (error) {
        regencies.innerHTML = '';
        window.simpAlert(error.message, {title: 'Wilayah Gagal Dimuat', tone: 'danger'});
      });
  }

  simpFetch(window.SIMP.baseUrl + 'wilayah/provinsi')
    .then(function (response) {
      province.innerHTML = '<option value="">Pilih provinsi</option>';
      response.data.forEach(function (row) {
        province.appendChild(option(row.id, row.name));
      });
    })
    .catch(function (error) {
      province.innerHTML = '<option value="">Gagal memuat</option>';
      window.simpAlert(error.message, {title: 'Provinsi Gagal Dimuat', tone: 'danger'});
    });

  province.addEventListener('change', loadRegencies);
  add.addEventListener('click', function () {
    var provinceName = province.options[province.selectedIndex] ? province.options[province.selectedIndex].text : '';
    Array.prototype.slice.call(regencies.selectedOptions).forEach(function (element) {
      if (element.value && !selected.some(function (item) { return String(item.regency_id) === String(element.value); })) {
        selected.push({
          province_id: province.value,
          province_name: provinceName,
          regency_id: element.value,
          regency_name: element.text
        });
      }
    });
    render();
  });

  form.addEventListener('submit', function (event) {
    if (!selected.length) {
      event.preventDefault();
      window.simpAlert('Pilih minimal satu kabupaten/kota cakupan event.', {title: 'Wilayah Belum Dipilih', tone: 'warning'});
    }
  });

  mode.addEventListener('change', updateFees);
  villageFee.addEventListener('input', updateBillingPreview);
  participantFee.addEventListener('input', updateBillingPreview);
  includedParticipantCount.addEventListener('input', updateBillingPreview);
  updateFees();
  render();
})();

/* The event form keeps its multi-region editor on a dedicated page, but saves
 * through the same JSON/AppKit flow as the compact CRUD screens. */
(function () {
  'use strict';
  var form = document.getElementById('event-form');
  if (!form || !form.hasAttribute('data-event-form-ajax')) return;
  function notify(message, options) {
    if (typeof window.simpAlert === 'function') return window.simpAlert(message, options || {});
    return Promise.resolve();
  }
  function updateCsrf(payload) {
    if (!payload || !payload.csrf) return;
    var name = payload.csrf.name || (window.SIMP && window.SIMP.csrfName), hash = payload.csrf.hash || '';
    if (window.SIMP) { window.SIMP.csrfName = name || window.SIMP.csrfName; window.SIMP.csrfHash = hash || window.SIMP.csrfHash; }
    if (name && hash) Array.prototype.slice.call(document.getElementsByName(name)).forEach(function (field) { field.value = hash; field.defaultValue = hash; });
  }
  function request() {
    var headers = {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'};
    if (window.SIMP && window.SIMP.csrfHash) headers['X-CSRF-TOKEN'] = window.SIMP.csrfHash;
    return fetch(form.action || window.location.href, {method: 'POST', credentials: 'same-origin', headers: headers, body: new FormData(form)}).then(function (response) {
      return response.text().then(function (text) {
        var payload = null;
        try { payload = text ? JSON.parse(text) : null; } catch (error) { payload = null; }
        if (!payload || typeof payload !== 'object' || Array.isArray(payload)) {
          var responseUrl = String(response.url || '');
          if (response.redirected || response.status === 401 || /\/login(?:[/?#]|$)/i.test(responseUrl)) {
            throw new Error('Sesi Anda telah berakhir. Silakan masuk kembali.');
          }
          if (response.status === 403 || response.status === 419) {
            throw new Error('Sesi keamanan form sudah tidak berlaku. Muat ulang halaman, lalu coba kembali.');
          }
          if (response.status >= 500) throw new Error('Event belum dapat disimpan karena terjadi gangguan server.');
          if (!response.ok) throw new Error('Event gagal disimpan. Periksa data lalu coba kembali.');
          throw new Error('Respons server tidak dapat dibaca. Muat ulang halaman, lalu coba kembali.');
        }
        updateCsrf(payload);
        if (!response.ok || payload.success === false) throw new Error(payload.message || 'Event gagal disimpan.');
        return payload;
      }, function () {
        throw new Error('Respons server tidak dapat dibaca. Silakan coba kembali.');
      });
    }, function () {
      throw new Error('Koneksi ke server terputus. Periksa jaringan lalu coba kembali.');
    });
  }
  form.addEventListener('submit', function (event) {
    if (event.defaultPrevented || form.dataset.submitting === '1') return;
    event.preventDefault();
    if (!form.checkValidity()) { form.reportValidity(); return; }
    if (!form.querySelector('input[name="region_pairs[]"]')) {
      notify('Pilih minimal satu kabupaten/kota cakupan event.', {title: 'Wilayah Belum Dipilih', tone: 'warning'});
      return;
    }
    form.dataset.submitting = '1';
    var button = event.submitter || form.querySelector('button[type="submit"]');
    var original = button ? button.innerHTML : '';
    if (button) { button.disabled = true; button.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i>Menyimpan...'; }
    request().then(function (payload) {
      return notify(payload.message, {title: 'Event Tersimpan', tone: 'success'}).then(function () {
        if (payload.event_id) window.location.href = window.SIMP.baseUrl + 'event/' + encodeURIComponent(payload.event_id);
      });
    }, function (error) {
      return notify(error.message, {title: 'Event Gagal Disimpan', tone: 'danger'});
    }).then(function () {
      form.dataset.submitting = '0';
      if (button && document.contains(button)) { button.disabled = false; button.innerHTML = original; }
    });
  });
})();

/* Event lifecycle actions use the same AppKit page and do not reload it. */
(function () {
  'use strict';
  function alertUser(message, options) {
    if (typeof window.simpAlert === 'function') return window.simpAlert(message, options || {});
    return Promise.resolve();
  }
  function postStatus(form) {
    var headers = {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'};
    if (window.SIMP && window.SIMP.csrfHash) headers['X-CSRF-TOKEN'] = window.SIMP.csrfHash;
    return fetch(form.action, {method:'POST', credentials:'same-origin', headers:headers, body:new FormData(form)}).then(function (response) {
      return response.text().then(function (text) {
        var payload = null;
        try { payload = text ? JSON.parse(text) : null; } catch (error) { payload = null; }
        if (!payload || typeof payload !== 'object' || Array.isArray(payload)) {
          var responseUrl = String(response.url || '');
          if (response.redirected || response.status === 401 || /\/login(?:[/?#]|$)/i.test(responseUrl)) {
            throw new Error('Sesi Anda telah berakhir. Silakan masuk kembali.');
          }
          if (response.status === 403 || response.status === 419) {
            throw new Error('Sesi keamanan form sudah tidak berlaku. Muat ulang halaman, lalu coba kembali.');
          }
          if (response.status >= 500) throw new Error('Status event belum dapat diperbarui karena terjadi gangguan server.');
          if (!response.ok) throw new Error('Status event gagal diperbarui.');
          throw new Error('Respons server tidak dapat dibaca. Muat ulang halaman, lalu coba kembali.');
        }
        if (payload.csrf && window.SIMP) {
          window.SIMP.csrfName = payload.csrf.name || window.SIMP.csrfName;
          window.SIMP.csrfHash = payload.csrf.hash;
          document.querySelectorAll('input[name="' + window.SIMP.csrfName + '"]').forEach(function (field) { field.value = payload.csrf.hash; field.defaultValue = payload.csrf.hash; });
        }
        if (!response.ok || payload.success === false) throw new Error(payload.message || 'Status event gagal diperbarui.');
        return payload;
      }, function () {
        throw new Error('Respons server tidak dapat dibaca. Silakan coba kembali.');
      });
    }, function () {
      throw new Error('Koneksi ke server terputus. Periksa jaringan lalu coba kembali.');
    });
  }
  function refreshEvent() {
    return fetch(window.location.href, {credentials:'same-origin', cache:'no-store', headers:{'X-Requested-With':'XMLHttpRequest','Accept':'text/html'}}).then(function (response) {
      var responseUrl = String(response.url || '');
      if (response.redirected || response.status === 401 || /\/login(?:[/?#]|$)/i.test(responseUrl)) throw new Error('Sesi Anda telah berakhir. Silakan masuk kembali.');
      if (!response.ok) throw new Error('Tampilan event gagal diperbarui.');
      return response.text();
    }, function () {
      throw new Error('Koneksi ke server terputus. Tampilan event belum dapat diperbarui.');
    }).then(function (html) {
      var parsed = new DOMParser().parseFromString(html, 'text/html');
      var current = document.getElementById('event-detail-content');
      var next = parsed.getElementById('event-detail-content');
      if (!current || !next) throw new Error('Tampilan event gagal diperbarui.');
      current.replaceWith(document.importNode(next, true));
    });
  }
  document.addEventListener('submit', function (event) {
    var form = event.target.closest('form[data-event-status-ajax]');
    if (!form || form.dataset.submitting === '1') return;
    event.preventDefault();
    form.dataset.submitting = '1';
    var button = event.submitter || form.querySelector('button[type="submit"]');
    if (button) { button.disabled = true; button.dataset.originalText = button.innerHTML; button.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i>Menyimpan...'; }
    postStatus(form).then(function (payload) {
      return refreshEvent().then(function () {
        return alertUser(payload.message, {title:'Event Diperbarui', tone:'success'});
      }, function () {
        return alertUser(payload.message + ' Tampilan belum diperbarui; jangan kirim ulang.', {title:'Data Sudah Tersimpan', tone:'warning'});
      });
    }, function (error) {
      return alertUser(error.message, {title:'Status Event Gagal', tone:'danger'});
    }).then(function () {
      form.dataset.submitting = '0';
      if (button && document.contains(button)) { button.disabled = false; button.innerHTML = button.dataset.originalText || 'Simpan'; }
    });
  });
})();
