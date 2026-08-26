(function () {
  'use strict';

  var form = document.querySelector('[data-payment-data-filter]');
  if (!form) return;

  var district = form.querySelector('[data-payment-district]');
  var village = form.querySelector('[data-payment-village]');
  var submitButton = form.querySelector('[data-payment-filter-submit]');
  var resetButton = document.querySelector('[data-payment-filter-reset]');
  var requestNumber = 0;
  var requestController = null;
  if (!district || !village) return;

  var villages = [];
  try { villages = JSON.parse(form.getAttribute('data-villages') || '[]'); }
  catch (error) { villages = []; }

  function escapeHtml(value) {
    return String(value === null || typeof value === 'undefined' ? '' : value).replace(/[&<>'"]/g, function (character) {
      return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[character];
    });
  }

  function rebuildVillages(preserveSelected) {
    var districtId = district.value;
    var selected = preserveSelected ? (village.value || village.getAttribute('data-selected') || '') : '';
    if (!districtId) {
      village.innerHTML = '<option value="">Pilih kecamatan dahulu</option>';
      village.value = '';
      village.disabled = true;
      village.setAttribute('data-selected', '');
      return;
    }

    var matching = villages.filter(function (item) {
      return String(item.district_id) === String(districtId);
    });
    village.innerHTML = '<option value="">Semua desa</option>' + matching.map(function (item) {
      return '<option value="' + escapeHtml(item.id) + '">' + escapeHtml(item.name) + '</option>';
    }).join('');
    village.disabled = false;
    if (selected && matching.some(function (item) { return String(item.id) === String(selected); })) village.value = selected;
    else village.value = '';
    village.setAttribute('data-selected', village.value);
  }

  function syncSelections(filters) {
    filters = filters || {};
    district.value = String(filters.district_id || '');
    village.setAttribute('data-selected', String(filters.village_id || ''));
    rebuildVillages(true);
  }

  function filterUrl() {
    var url = new URL(form.action, window.location.href);
    if (district.value) url.searchParams.set('district_id', district.value);
    if (village.value) url.searchParams.set('village_id', village.value);
    return url;
  }

  function canonicalUrl(filterQuery) {
    var url = new URL(form.action, window.location.href);
    url.search = filterQuery ? '?' + filterQuery : '';
    return url;
  }

  function setBusy(busy) {
    if (submitButton) {
      if (busy) {
        submitButton.dataset.originalHtml = submitButton.innerHTML;
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i>Memuat...';
      } else {
        submitButton.disabled = false;
        submitButton.innerHTML = submitButton.dataset.originalHtml || '<i class="fa fa-filter me-1"></i>Terapkan';
      }
    }
    if (resetButton) {
      resetButton.setAttribute('aria-disabled', busy ? 'true' : 'false');
      resetButton.classList.toggle('no-click', busy);
    }
    var results = document.getElementById('payment-data-results');
    if (results) results.setAttribute('aria-busy', busy ? 'true' : 'false');
  }

  function notifyError(message) {
    if (typeof window.simpAlert === 'function') {
      window.simpAlert(message, {title:'Filter Gagal Dimuat', tone:'danger'});
    }
  }

  function replaceResults(html) {
    var template = document.createElement('template');
    template.innerHTML = String(html || '').trim();
    var next = template.content.querySelector('#payment-data-results');
    var current = document.getElementById('payment-data-results');
    if (!current || !next) throw new Error('Bagian hasil Data Bayar tidak ditemukan. Muat ulang halaman lalu coba kembali.');
    current.replaceWith(document.importNode(next, true));
  }

  function updatePrintTargets(payload) {
    var count = document.getElementById('payment-data-count');
    if (count) count.textContent = Number(payload.row_count || 0).toLocaleString('id-ID') + ' data';

    var showFilter = payload.filter_active === true && payload.has_rows === true;
    var allColumn = document.getElementById('payment-data-all-print-column');
    var filterColumn = document.getElementById('payment-data-filter-print-column');
    if (allColumn) allColumn.className = showFilter ? 'col-6 pe-1' : 'col-12';
    if (filterColumn) filterColumn.classList.toggle('d-none', !showFilter);

    var trigger = document.querySelector('[data-payment-filter-print-trigger]');
    if (trigger) trigger.href = payload.preview_url;
    var modal = document.getElementById('payment-data-filter-print-modal');
    if (!modal) return;
    var frame = modal.querySelector('[data-report-preview-frame]');
    var pdf = modal.querySelector('[data-report-file-download][data-report-file-label="PDF"]');
    if (frame) {
      frame.setAttribute('data-src', payload.preview_url);
      frame.dataset.loaded = 'false';
    }
    if (pdf) pdf.href = payload.pdf_url;
    var share = modal.querySelector('[data-report-share-pdf]');
    if (share) share.setAttribute('data-report-pdf-url', payload.pdf_url);
  }

  function requestJson(url, signal) {
    var options = {
      method: 'GET',
      credentials: 'same-origin',
      cache: 'no-store',
      signal: signal,
      headers: {'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json'}
    };
    if (typeof window.simpFetch === 'function') return window.simpFetch(url.toString(), options);
    return fetch(url.toString(), options).then(function (response) {
      return response.json().then(function (payload) {
        if (!response.ok || payload.success === false) throw new Error(payload.message || 'Filter gagal dimuat.');
        return payload;
      });
    });
  }

  function loadFilter(url, updateHistory) {
    requestNumber += 1;
    var currentRequest = requestNumber;
    if (requestController) requestController.abort();
    requestController = typeof AbortController !== 'undefined' ? new AbortController() : null;
    setBusy(true);

    return requestJson(url, requestController ? requestController.signal : undefined).then(function (payload) {
      if (currentRequest !== requestNumber) return;
      replaceResults(payload.html);
      syncSelections(payload.filters);
      updatePrintTargets(payload);
      if (updateHistory) window.history.pushState({}, '', canonicalUrl(payload.filter_query).toString());
    }).catch(function (error) {
      if (error && error.name === 'AbortError') return;
      notifyError(error && error.message ? error.message : 'Filter gagal dimuat.');
    }).then(function () {
      if (currentRequest === requestNumber) setBusy(false);
    });
  }

  district.addEventListener('change', function () { rebuildVillages(false); });
  village.addEventListener('change', function () { village.setAttribute('data-selected', village.value); });

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    loadFilter(filterUrl(), true);
  });

  if (resetButton) resetButton.addEventListener('click', function (event) {
    event.preventDefault();
    if (resetButton.getAttribute('aria-disabled') === 'true') return;
    district.value = '';
    rebuildVillages(false);
    loadFilter(new URL(resetButton.href, window.location.href), true);
  });

  window.addEventListener('popstate', function () {
    var current = new URL(window.location.href);
    syncSelections({
      district_id: current.searchParams.get('district_id') || '',
      village_id: current.searchParams.get('village_id') || ''
    });
    loadFilter(current, false);
  });

  rebuildVillages(true);
}());
