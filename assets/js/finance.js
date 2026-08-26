(function () {
  'use strict';

  function all(selector) { return Array.prototype.slice.call(document.querySelectorAll(selector)); }

  function financeFilterUrl() {
    var form = document.querySelector('[data-finance-filter-form]');
    if (!form) return null;
    var url = new URL(form.action || window.location.href, window.location.href);
    var from = form.querySelector('[name="date_from"]');
    var to = form.querySelector('[name="date_to"]');
    url.search = '';
    if (from && from.value) url.searchParams.set('date_from', from.value);
    if (to && to.value) url.searchParams.set('date_to', to.value);
    return url;
  }

  function financeDatesValid() {
    var form = document.querySelector('[data-finance-filter-form]');
    if (!form) return true;
    var from = form.querySelector('[name="date_from"]');
    var to = form.querySelector('[name="date_to"]');
    if (from && to && from.value && to.value && from.value > to.value) {
      financeAlert('Dari tanggal tidak boleh setelah sampai tanggal.', 'Periode Tidak Valid', 'warning');
      from.focus();
      return false;
    }
    return true;
  }

  function setFinancePrintUrls(previewUrl, pdfUrl) {
    var trigger = document.querySelector('[data-finance-print-trigger]');
    var modal = document.getElementById('finance-print-modal');
    var frame = modal ? modal.querySelector('[data-report-preview-frame]') : null;
    var pdf = modal ? modal.querySelector('[data-report-file-label="PDF"]') : null;
    var share = modal ? modal.querySelector('[data-report-share-pdf]') : null;
    if (trigger) trigger.href = previewUrl;
    if (frame) {
      frame.setAttribute('data-src', previewUrl);
      frame.removeAttribute('src');
      frame.dataset.loaded = 'false';
    }
    if (pdf) pdf.href = pdfUrl;
    if (share) share.setAttribute('data-report-pdf-url', pdfUrl);
  }

  function financeAlert(message, title, tone) {
    if (typeof window.simpAlert === 'function') window.simpAlert(message, {title:title, tone:tone});
  }

  // Switch income report grouping without a full page reload. The returned
  // partial contains the same outer #income-report-content wrapper and the
  // current print link; the shared print modal remains mounted outside it.
  function requestIncomeView(view, trigger) {
    var current = document.getElementById('income-report-content');
    if (!current || !view) return;
    var url = new URL((trigger && trigger.href) || window.location.href, window.location.href);
    url.search = '';
    url.searchParams.set('view', view);
    if (trigger) trigger.setAttribute('aria-busy', 'true');
    fetch(url.toString(), {
      credentials: 'same-origin', cache: 'no-store',
      headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'}
    }).then(function (response) {
      return response.text().then(function (body) {
        var responseUrl = String(response.url || '');
        if (response.status === 401 || /\/login(?:[/?#]|$)/i.test(responseUrl)) {
          throw new Error('Sesi Anda telah berakhir. Silakan masuk kembali.');
        }
        var payload;
        try { payload = JSON.parse(body); } catch (error) { throw new Error('Laporan pemasukan gagal diperbarui.'); }
        if (!response.ok || payload.success !== true) throw new Error(payload.message || 'Laporan pemasukan gagal diperbarui.');
        return payload;
      });
    }).then(function (payload) {
      var parsed = new DOMParser().parseFromString(payload.html || '', 'text/html');
      var next = parsed.getElementById('income-report-content');
      if (!next) throw new Error('Potongan laporan pemasukan tidak lengkap.');
      current.replaceWith(document.importNode(next, true));
      if (window.history && window.history.replaceState) window.history.replaceState({}, '', url.toString());
      var modal = document.getElementById('income-print-modal');
      if (modal) {
        var frame = modal.querySelector('[data-report-preview-frame]');
        var pdf = modal.querySelector('[data-report-file-label="PDF"]');
        var excel = modal.querySelector('[data-report-file-label="Excel"]');
        var share = modal.querySelector('[data-report-share-pdf]');
        if (frame) { frame.setAttribute('data-src', payload.preview_url); frame.removeAttribute('src'); frame.dataset.loaded = 'false'; }
        if (pdf) pdf.href = payload.pdf_url;
        if (excel && payload.excel_url) excel.href = payload.excel_url;
        if (share) share.setAttribute('data-report-pdf-url', payload.pdf_url);
      }
    }).catch(function (error) {
      financeAlert(error.message || 'Laporan pemasukan gagal diperbarui.', 'Gagal Memuat', 'danger');
    }).finally(function () {
      if (trigger) trigger.removeAttribute('aria-busy');
    });
  }

  document.addEventListener('click', function (event) {
    var trigger = event.target.closest('[data-income-view]');
    if (!trigger) return;
    event.preventDefault();
    requestIncomeView(trigger.getAttribute('data-income-view'), trigger);
  });

  document.addEventListener('click', function (event) {
    var trigger = event.target.closest('[data-income-print-trigger]');
    if (!trigger) return;
    var active = document.querySelector('[data-income-view][aria-pressed="true"]');
    if (active) {
      var href = new URL(trigger.href, window.location.href);
      href.searchParams.set('view', active.getAttribute('data-income-view'));
      trigger.href = href.toString();
    }
  }, true);

  function requestFinanceFilter(url) {
    var form = document.querySelector('[data-finance-filter-form]');
    var button = form ? form.querySelector('[data-finance-apply]') : null;
    var label = button ? button.querySelector('span') : null;
    if (button) button.disabled = true;
    if (label) label.textContent = 'Memuat...';
    return fetch(url.toString(), {
      credentials:'same-origin', cache:'no-store',
      headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}
    }).then(function (response) {
      var responseUrl = String(response.url || '');
      if (response.redirected || response.status === 401 || /\/login(?:[/?#]|$)/i.test(responseUrl)) throw new Error('Sesi Anda telah berakhir. Silakan masuk kembali.');
      return response.text().then(function (body) {
        var payload;
        try { payload = JSON.parse(body); }
        catch (error) { throw new Error('Laporan keuangan gagal diperbarui.'); }
        if (!response.ok || payload.success !== true) throw new Error(payload.message || 'Laporan keuangan gagal diperbarui.');
        return payload;
      });
    }).then(function (payload) {
      var current = document.getElementById('finance-report-content');
      if (!current || !payload.html) throw new Error('Potongan laporan keuangan tidak lengkap.');
      var parsed = new DOMParser().parseFromString(payload.html, 'text/html');
      var next = parsed.getElementById('finance-report-content');
      if (!next) throw new Error('Potongan laporan keuangan tidak lengkap.');
      current.replaceWith(document.importNode(next, true));
      setFinancePrintUrls(payload.preview_url, payload.pdf_url);
      if (window.history && window.history.replaceState) window.history.replaceState({}, '', url.toString());
      return payload;
    }).catch(function (error) {
      financeAlert(error.message || 'Laporan keuangan gagal diperbarui.', 'Filter Gagal', 'danger');
      throw error;
    }).finally(function () {
      if (button) button.disabled = false;
      if (label) label.textContent = 'Terapkan';
    });
  }

  document.addEventListener('submit', function (event) {
    var form = event.target.closest('[data-finance-filter-form]');
    if (!form) return;
    event.preventDefault();
    if (!financeDatesValid()) return;
    var url = financeFilterUrl();
    if (url) requestFinanceFilter(url).catch(function () {});
  });

  // Cetak always follows the dates currently visible in the form.  The user
  // does not need to press Terapkan first; the modal receives fresh preview
  // and PDF URLs immediately before the shared print handler opens it.
  document.addEventListener('click', function (event) {
    var trigger = event.target.closest('[data-finance-print-trigger]');
    if (!trigger) return;
    if (!financeDatesValid()) {
      event.preventDefault();
      event.stopImmediatePropagation();
      return;
    }
    var url = financeFilterUrl();
    if (!url) return;
    var previewUrl = new URL(trigger.href, window.location.href);
    var pdfAnchor = document.querySelector('#finance-print-modal [data-report-file-label="PDF"]');
    var pdfUrl = new URL(pdfAnchor ? pdfAnchor.href : trigger.href.replace(/\/cetak(?=\?|$)/, '/pdf'), window.location.href);
    previewUrl.search = url.search;
    pdfUrl.search = url.search;
    setFinancePrintUrls(previewUrl.toString(), pdfUrl.toString());
  }, true);

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

  function moneyIsZero(target) {
    var raw = moneyRaw(target);
    var value = raw && raw.value !== null && typeof raw.value !== 'undefined'
      ? String(raw.value).trim()
      : '';
    return value === '' || /^0(?:\.0*)?$/.test(value);
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
    else if (input) {
      if (moneyIsZero(input)) setMoney(input, '2500');
      setMoneyControl(input, {readOnly: false});
    }
  }

  var type = document.querySelector('.js-account-type');
  if (type) { type.addEventListener('change', toggleAccountFields); toggleAccountFields(); }

  var method = document.querySelector('.js-payment-method');
  if (method) { method.addEventListener('change', toggleAdminFee); toggleAdminFee(); }

  // Payment proof is optional for every regular expense method. Keep the
  // standalone/legacy form aligned with the AJAX modal and server rules.
  var legacyExpenseProof = document.querySelector('.js-expense-proof');
  var legacyExpenseProofHelp = document.getElementById('expense-proof-help');
  function toggleLegacyExpenseProof() {
    if (!legacyExpenseProof) return;
    legacyExpenseProof.required = false;
    legacyExpenseProof.removeAttribute('aria-required');
    if (legacyExpenseProofHelp) legacyExpenseProofHelp.textContent = 'JPG, PNG, atau PDF; maksimal 5 MB.';
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

  var expenseRefreshSequence = 0;
  var expenseRefreshController = null;

  function refreshExpenseCards(targetUrl, options) {
    options = options || {};
    var requestUrl = targetUrl || window.location.href;
    var requestSequence = ++expenseRefreshSequence;
    if (expenseRefreshController && typeof expenseRefreshController.abort === 'function') expenseRefreshController.abort();
    expenseRefreshController = typeof AbortController === 'function' ? new AbortController() : null;
    var fetchOptions = {
      credentials:'same-origin', cache:'no-store',
      headers:{'X-Requested-With':'XMLHttpRequest','Accept':'text/html'}
    };
    if (expenseRefreshController) fetchOptions.signal = expenseRefreshController.signal;
    return fetch(requestUrl, fetchOptions).then(function (response) {
      var responseUrl = String(response.url || '');
      if (response.redirected || response.status === 401 || /\/login(?:[/?#]|$)/i.test(responseUrl)) throw new Error('Sesi Anda telah berakhir. Silakan masuk kembali.');
      if (!response.ok) throw new Error('Daftar pengeluaran gagal diperbarui.');
      return response.text();
    }).then(function (html) {
      if (requestSequence !== expenseRefreshSequence) return;
      var parsed = new DOMParser().parseFromString(html, 'text/html');
      var ids = ['expense-summary','expense-list','expense-pagination'];
      if (options.replaceFilters) ids.push('expense-filters');
      var replacements = ids.map(function (id) {
        return {current:document.getElementById(id), next:parsed.getElementById(id)};
      });
      if (replacements.some(function (item) { return !item.current || !item.next; })) {
        throw new Error('Potongan daftar pengeluaran tidak lengkap.');
      }
      replacements.forEach(function (item) {
        item.current.replaceWith(document.importNode(item.next, true));
      });
      if (options.updateHistory !== false && window.history && window.history.replaceState) {
        window.history.replaceState({}, '', requestUrl);
      }
    }).catch(function (error) {
      if (error && error.name === 'AbortError') return;
      throw error;
    });
  }

  function expenseFilterUrl(page) {
    var form = document.querySelector('[data-expense-filter-form]');
    if (!form) return window.location.href;
    var url = new URL(form.action || window.location.href, window.location.href);
    var search = form.querySelector('[name="q"]');
    var category = form.querySelector('[name="category_id"]');
    var query = (search ? search.value : '').trim();
    var categoryId = category ? category.value : '';
    url.search = '';
    if (query) url.searchParams.set('q', query);
    if (categoryId) url.searchParams.set('category_id', categoryId);
    var targetPage = parseInt(page, 10) || 1;
    if (targetPage > 1) url.searchParams.set('page', String(targetPage));
    return url.toString();
  }

  function requestExpenseFilter(url, replaceFilters) {
    refreshExpenseCards(url, {replaceFilters:!!replaceFilters, updateHistory:true}).catch(function (error) {
      if (error && error.name === 'AbortError') return;
      expenseAlert(error.message || 'Daftar pengeluaran gagal diperbarui.', 'Filter Gagal', 'danger');
    });
  }

  var expenseSearchTimer = null;
  document.addEventListener('input', function (event) {
    var search = event.target.closest('[data-expense-filter-form] [name="q"]');
    if (!search) return;
    if (expenseSearchTimer) window.clearTimeout(expenseSearchTimer);
    expenseSearchTimer = window.setTimeout(function () {
      requestExpenseFilter(expenseFilterUrl(1), false);
    }, 350);
  });

  document.addEventListener('change', function (event) {
    var category = event.target.closest('[data-expense-filter-form] [name="category_id"]');
    if (category) {
      if (expenseSearchTimer) window.clearTimeout(expenseSearchTimer);
      expenseSearchTimer = null;
      requestExpenseFilter(expenseFilterUrl(1), false);
    }
  });

  document.addEventListener('submit', function (event) {
    var filterForm = event.target.closest('[data-expense-filter-form]');
    if (!filterForm) return;
    event.preventDefault();
    if (expenseSearchTimer) window.clearTimeout(expenseSearchTimer);
    expenseSearchTimer = null;
    requestExpenseFilter(expenseFilterUrl(1), false);
  });

  document.addEventListener('click', function (event) {
    var pageLink = event.target.closest('[data-expense-page-link]');
    if (pageLink) {
      event.preventDefault();
      requestExpenseFilter(pageLink.href, false);
      return;
    }
    var reset = event.target.closest('[data-expense-filter-reset]');
    if (reset) {
      event.preventDefault();
      requestExpenseFilter(reset.href, true);
    }
  });

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

  function deleteExpense(button) {
    var url = button.getAttribute('data-expense-delete-url') || '';
    var label = String(button.getAttribute('data-expense-delete-label') || 'pengeluaran ini').trim();
    var status = button.getAttribute('data-expense-delete-status') || 'pending';
    if (!url) {
      expenseAlert('Alamat penghapusan pengeluaran tidak tersedia.', 'Hapus Gagal', 'danger');
      return;
    }
    if (label.length > 140) label = label.slice(0, 137) + '...';
    var message = status === 'verified'
      ? 'Hapus pengeluaran "' + label + '"? Jurnal akan dibatalkan dan saldo akun dikembalikan. Tindakan ini tidak dapat dibatalkan.'
      : 'Hapus pengeluaran "' + label + '" beserta bukti pembayarannya? Tindakan ini tidak dapat dibatalkan.';
    if (typeof window.simpConfirm !== 'function') {
      expenseAlert('Konfirmasi MVIN belum siap. Muat ulang halaman lalu coba kembali.', 'Hapus Gagal', 'danger');
      return;
    }

    window.simpConfirm(message, {
      title: 'Hapus Pengeluaran?',
      confirmLabel: 'Ya, Hapus',
      tone: 'danger'
    }).then(function (confirmed) {
      if (!confirmed) return;
      var originalText = button.innerHTML;
      button.disabled = true;
      button.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i>Menghapus...';
      var body = new FormData();
      if (window.SIMP && window.SIMP.csrfName) body.append(window.SIMP.csrfName, window.SIMP.csrfHash || '');
      var headers = {'X-Requested-With':'XMLHttpRequest','Accept':'application/json'};
      if (window.SIMP && window.SIMP.csrfHash) headers['X-CSRF-TOKEN'] = window.SIMP.csrfHash;
      fetch(url, {method:'POST', headers:headers, body:body})
        .then(function (response) { return expenseJson(response, 'Pengeluaran gagal dihapus.'); })
        .then(function (payload) {
          return refreshExpenseCards(window.location.href, {replaceFilters:true, updateHistory:false}).then(
            function () { notifyExpensePersisted(payload, false, 'Pengeluaran'); },
            function () { notifyExpensePersisted(payload, true, 'Pengeluaran'); }
          );
        })
        .catch(function (error) { expenseAlert(error.message || 'Pengeluaran gagal dihapus.', 'Hapus Pengeluaran Gagal', 'danger'); })
        .then(function () {
          button.disabled = false;
          button.innerHTML = originalText;
        });
    });
  }

  // Delegated so the action remains available after the list is refreshed by
  // search, pagination, status changes, or another AJAX mutation.
  document.addEventListener('click', function (event) {
    var deleteButton = event.target.closest('[data-expense-delete-open]');
    if (!deleteButton) return;
    event.preventDefault();
    deleteExpense(deleteButton);
  });

  var expenseForm = document.getElementById('expense-add-form');
  if (expenseForm) {
    var expenseAccounts = [];
    try { expenseAccounts = JSON.parse(expenseForm.dataset.accounts || '[]'); } catch (error) { expenseAccounts = []; }
    var expenseMethod = document.getElementById('expense-modal-method');
    var expenseAccount = document.getElementById('expense-modal-account');
    var expenseProof = document.getElementById('expense-modal-proof');
    var expenseProofHelp = document.getElementById('expense-modal-proof-help');
    var expenseFee = document.getElementById('expense-modal-fee');
    var expenseFeeWrap = document.getElementById('expense-modal-fee-wrap');
    var expenseStatus = document.getElementById('expense-modal-status');
    var expenseStatusWrap = document.getElementById('expense-modal-status-wrap');
    var expenseTitle = document.getElementById('expense-add-title');
    var expenseExpectedUpdatedAt = document.getElementById('expense-modal-expected-updated-at');
    var expenseSubmit = expenseForm.querySelector('[data-expense-add-submit]');
    var defaultExpenseAdminFee = '2500';

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

    function expenseData(element) {
      try { return JSON.parse(element.getAttribute('data-expense') || '{}'); }
      catch (error) { return {}; }
    }

    function setExpenseValue(name, value) {
      var field = expenseForm.elements[name];
      if (!field) return;
      if (name === 'amount' || name === 'admin_fee') setMoney(field, value);
      else field.value = value === null || typeof value === 'undefined' ? '' : value;
    }

    function localExpenseDate() {
      var now = new Date();
      now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
      return now.toISOString().slice(0, 10);
    }

    function openExpenseModal() {
      var opener = document.querySelector('[data-menu="expense-add-modal"]');
      if (opener) opener.click();
    }

    function updateExpenseProofState() {
      if (!expenseProof) return;
      var editingWithProof = expenseForm.dataset.expenseMode === 'edit' && expenseForm.dataset.hasProof === '1';
      expenseProof.required = false;
      expenseProof.removeAttribute('aria-required');
      if (expenseProofHelp) {
        expenseProofHelp.classList.remove('d-none');
        expenseProofHelp.textContent = editingWithProof
          ? 'Bukti lama tetap digunakan jika tidak memilih file baru.'
          : 'JPG, PNG, atau PDF; maksimal 5 MB.';
      }
    }

    function updateExpenseFeeRule() {
      if (!expenseMethod || !expenseFee) return;
      var isTransfer = expenseMethod.value === 'transfer';
      if (expenseFeeWrap) {
        expenseFeeWrap.classList.toggle('d-none', !isTransfer);
        expenseFeeWrap.hidden = !isTransfer;
        expenseFeeWrap.setAttribute('aria-hidden', isTransfer ? 'false' : 'true');
      }
      setMoneyControl(expenseFee, {readOnly: !isTransfer});
      if (isTransfer) {
        // New transfers start with the usual bank fee, but the field remains
        // editable. Existing transactions keep their stored fee in Edit mode.
        if (expenseForm.dataset.expenseMode === 'create' && moneyIsZero(expenseFee)) {
          setMoney(expenseFee, defaultExpenseAdminFee);
        }
      } else {
        setMoney(expenseFee, '0');
      }
    }

    function prepareExpenseCreate() {
      expenseForm.reset();
      expenseForm.action = expenseForm.dataset.createAction || expenseForm.action;
      expenseForm.dataset.expenseMode = 'create';
      expenseForm.dataset.hasProof = '0';
      if (expenseExpectedUpdatedAt) expenseExpectedUpdatedAt.value = '';
      if (expenseTitle) expenseTitle.textContent = 'Tambah Pengeluaran';
      if (expenseStatusWrap) expenseStatusWrap.classList.remove('d-none');
      if (expenseStatus) expenseStatus.disabled = false;
      setExpenseValue('expense_date', localExpenseDate());
      filterExpenseAccounts();
      updateExpenseProofState();
      updateExpenseFeeRule();
      refreshMoney(expenseForm);
    }

    function prepareExpenseEdit(button) {
      var expense = expenseData(button);
      if (!expense.id) return;
      expenseForm.reset();
      expenseForm.dataset.expenseMode = 'edit';
      expenseForm.dataset.hasProof = Number(expense.has_proof) === 1 ? '1' : '0';
      if (expenseExpectedUpdatedAt) expenseExpectedUpdatedAt.value = expense.updated_at || '';
      expenseForm.action = (expenseForm.dataset.updatePrefix || '').replace(/\/$/, '') + '/' + encodeURIComponent(expense.id) + '/ubah';
      if (expenseTitle) expenseTitle.textContent = 'Edit Pengeluaran';
      setExpenseValue('event_id', expense.event_id);
      setExpenseValue('category_id', expense.category_id);
      setExpenseValue('expense_date', expense.expense_date);
      setExpenseValue('description', expense.description);
      setExpenseValue('amount', expense.amount);
      setExpenseValue('method', expense.method);
      setExpenseValue('admin_fee', expense.admin_fee);
      setExpenseValue('note', expense.note);
      if (expenseStatus) {
        expenseStatus.value = expense.status || 'pending';
        expenseStatus.disabled = true;
      }
      if (expenseStatusWrap) expenseStatusWrap.classList.add('d-none');
      filterExpenseAccounts();
      setExpenseValue('account_id', expense.account_id);
      updateExpenseProofState();
      updateExpenseFeeRule();
      refreshMoney(expenseForm);
      if (expenseSubmit) expenseSubmit.innerHTML = '<i class="fa fa-save me-1"></i> Simpan Perubahan';
      openExpenseModal();
    }

    document.addEventListener('click', function (event) {
      var addButton = event.target.closest('[data-expense-add-open]');
      if (addButton) {
        event.preventDefault();
        prepareExpenseCreate();
        if (expenseSubmit) expenseSubmit.innerHTML = '<i class="fa fa-save me-1"></i> Simpan Pengeluaran';
        openExpenseModal();
        return;
      }
      var editButton = event.target.closest('[data-expense-edit-open]');
      if (editButton) {
        event.preventDefault();
        prepareExpenseEdit(editButton);
      }
    });

    expenseMethod.addEventListener('change', function () { filterExpenseAccounts(); updateExpenseProofState(); updateExpenseFeeRule(); });
    filterExpenseAccounts();
    updateExpenseProofState();
    updateExpenseFeeRule();

    expenseForm.addEventListener('submit', function (event) {
      event.preventDefault();
      refreshMoney(expenseForm);
      if (!expenseForm.checkValidity()) { expenseForm.reportValidity(); return; }
      if (window.SIMP && window.SIMP.csrfName && expenseForm.elements[window.SIMP.csrfName]) expenseForm.elements[window.SIMP.csrfName].value = window.SIMP.csrfHash;
      var editing = expenseForm.dataset.expenseMode === 'edit';
      if (expenseSubmit) { expenseSubmit.disabled = true; expenseSubmit.dataset.originalText = expenseSubmit.innerHTML; expenseSubmit.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i>Menyimpan...'; }
      var headers = {'X-Requested-With':'XMLHttpRequest','Accept':'application/json'};
      if (window.SIMP && window.SIMP.csrfHash) headers['X-CSRF-TOKEN'] = window.SIMP.csrfHash;
      fetch(expenseForm.action, {method:'POST',headers:headers,body:new FormData(expenseForm)})
        .then(function (response) { return expenseJson(response, editing ? 'Pengeluaran gagal diperbarui.' : 'Pengeluaran gagal disimpan.'); })
        .then(function (payload) {
          var closer = document.querySelector('#expense-add-modal .close-menu');
          if (closer) closer.click();
          return refreshExpenseCards(window.location.href, {replaceFilters:true, updateHistory:false}).then(
            function () { notifyExpensePersisted(payload, false, 'Pengeluaran'); },
            function () { notifyExpensePersisted(payload, true, 'Pengeluaran'); }
          );
        })
        .catch(function (error) { expenseAlert(error.message, editing ? 'Edit Pengeluaran Gagal' : 'Pengeluaran Gagal', 'danger'); })
        .then(function () {
          if (expenseSubmit) { expenseSubmit.disabled = false; expenseSubmit.innerHTML = expenseSubmit.dataset.originalText || (editing ? '<i class="fa fa-save me-1"></i> Simpan Perubahan' : '<i class="fa fa-save me-1"></i> Simpan Pengeluaran'); }
        });
    });

    prepareExpenseCreate();
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
        return refreshExpenseCards(window.location.href, {replaceFilters:true, updateHistory:false}).then(
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

  function reportPreviewAlert(message, title, tone) {
    if (typeof window.simpAlert === 'function') {
      window.simpAlert(message, {title:title || 'Cetak Laporan', tone:tone || 'warning'});
    }
  }

  /*
   * A PDF cannot be attached to WhatsApp through a wa.me URL.  On browsers
   * that implement Web Share Level 2 we can, however, hand the actual PDF
   * file to the native share sheet and let the user choose WhatsApp.  The
   * file is prefetched as soon as a print modal is opened so the share call
   * still happens inside the button's user gesture (Safari is strict about
   * this).  Unsupported browsers get a deterministic download fallback and
   * an MVIN dialog explaining the next step.
   */
  var reportPdfCache = Object.create(null);

  function reportPdfLink(modal) {
    return modal ? modal.querySelector('[data-report-file-download][data-report-file-label="PDF"]') : null;
  }

  function reportPdfUrl(modal, shareButton) {
    var fromButton = shareButton && shareButton.getAttribute('data-report-pdf-url');
    var link = reportPdfLink(modal);
    var fromLink = link && link.getAttribute('href');
    /* Data Bayar changes the PDF link after an AJAX filter.  Always prefer
     * that live link over the initial button attribute. */
    var source = (fromLink && fromLink !== '#') ? fromLink : fromButton;
    if (!source || source === '#') return '';
    try { return new URL(source, window.location.href).toString(); }
    catch (error) { return source; }
  }

  function reportPdfFileName(title) {
    var safe = String(title || 'Laporan MVIN')
      .replace(/[^a-z0-9]+/gi, '-')
      .replace(/^-+|-+$/g, '')
      .toLowerCase() || 'laporan-mvin';
    return safe + '.pdf';
  }

  function fetchReportPdf(url, title) {
    var cached = reportPdfCache[url];
    if (cached && !cached.error) return cached;
    if (cached && cached.error) delete reportPdfCache[url];

    var entry = {loading:true, file:null, error:null, promise:null};
    entry.promise = fetch(url, {
      credentials:'same-origin',
      cache:'no-store',
      headers:{'Accept':'application/pdf','X-Requested-With':'XMLHttpRequest'}
    }).then(function (response) {
      var responseUrl = String(response.url || '');
      var contentType = String(response.headers.get('content-type') || '').toLowerCase();
      if (!response.ok || response.redirected || /\/login(?:[/?#]|$)/i.test(responseUrl)) {
        throw new Error('Sesi Anda telah berakhir atau PDF tidak dapat dibuat.');
      }
      if (contentType.indexOf('text/html') !== -1 || contentType.indexOf('application/json') !== -1) {
        throw new Error('Server mengembalikan halaman selain PDF. Muat ulang halaman lalu coba kembali.');
      }
      return response.blob();
    }).then(function (blob) {
      var pdfBlob = blob;
      if (typeof Blob === 'function' && (!blob.type || blob.type.toLowerCase().indexOf('pdf') === -1)) {
        pdfBlob = new Blob([blob], {type:'application/pdf'});
      }
      var filename = reportPdfFileName(title || 'Laporan MVIN');
      var file = typeof File === 'function'
        ? new File([pdfBlob], filename, {type:'application/pdf'})
        : pdfBlob;
      try { file.name = filename; } catch (error) {}
      entry.file = file;
      entry.loading = false;
      return file;
    }).catch(function (error) {
      entry.loading = false;
      entry.error = error;
      throw error;
    });
    reportPdfCache[url] = entry;
    return entry;
  }

  function primeReportPdf(modal) {
    var url = reportPdfUrl(modal);
    if (!url) return null;
    var titleNode = modal && modal.querySelector('[data-report-share-title]');
    var title = titleNode ? titleNode.getAttribute('data-report-share-title') : '';
    var entry = fetchReportPdf(url, title);
    /* Prefetch errors are reported when the user explicitly presses share. */
    entry.promise.catch(function () {});
    return entry;
  }

  function downloadReportPdf(file, filename) {
    var objectUrl = URL.createObjectURL(file);
    var anchor = document.createElement('a');
    anchor.href = objectUrl;
    anchor.download = filename;
    anchor.style.display = 'none';
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
    window.setTimeout(function () { URL.revokeObjectURL(objectUrl); }, 1500);
  }

  function setReportShareBusy(button, busy) {
    if (!button) return;
    if (busy) {
      button.dataset.originalHtml = button.innerHTML;
      button.dataset.busy = 'true';
      button.setAttribute('aria-busy', 'true');
      button.disabled = true;
      var iconOnly = button.closest('.is-icon-only');
      button.innerHTML = iconOnly
        ? '<i class="fa fa-spinner fa-spin" aria-hidden="true"></i><span class="visually-hidden">Menyiapkan PDF...</span>'
        : '<i class="fa fa-spinner fa-spin me-1" aria-hidden="true"></i>Menyiapkan PDF...';
    } else {
      button.dataset.busy = 'false';
      button.removeAttribute('aria-busy');
      button.disabled = false;
      if (button.dataset.originalHtml) button.innerHTML = button.dataset.originalHtml;
    }
  }

  function shareReportPdf(button, file, title) {
    var filename = file && file.name ? file.name : reportPdfFileName(title);
    var canUseNativeShare = false;
    try {
      canUseNativeShare = !!(navigator.share) && (!navigator.canShare || navigator.canShare({files:[file]}));
    } catch (error) { canUseNativeShare = false; }

    function fallbackShare(error) {
      /* Closing the native chooser is a normal user action. */
      if (error && error.name === 'AbortError') return;
      downloadReportPdf(file, filename);
      reportPreviewAlert('Perangkat ini menolak berbagi langsung. PDF sudah diunduh; lampirkan file tersebut di WhatsApp.', 'PDF Diunduh', 'info');
    }

    if (canUseNativeShare) {
      try {
        Promise.resolve(navigator.share({
          files:[file],
          title:title || 'Laporan MVIN',
          text:'PDF ' + (title || 'Laporan MVIN')
        })).catch(fallbackShare);
      } catch (error) {
        fallbackShare(error);
      }
      return;
    }

    downloadReportPdf(file, filename);
    reportPreviewAlert('Perangkat ini belum mendukung berbagi file langsung. PDF sudah diunduh; lampirkan file tersebut di WhatsApp.', 'PDF Diunduh', 'info');
  }

  function updateReportZoomControls(frame, percent, ready) {
    var modal = frame ? frame.closest('.simp-print-modal') : null;
    if (!modal) return;
    var normalized = Math.max(50, Math.min(1000, parseInt(percent, 10) || 100));
    var label = modal.querySelector('[data-report-zoom-value]');
    if (label) label.textContent = normalized + '%';
    modal.querySelectorAll('[data-report-zoom]').forEach(function (button) {
      var delta = parseInt(button.getAttribute('data-report-zoom'), 10) || 0;
      button.disabled = !ready || (delta < 0 && normalized <= 50) || (delta > 0 && normalized >= 1000);
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

    /* Warm the current PDF while the preview is opening.  This makes the
     * subsequent Web Share call eligible for Safari's transient gesture. */
    primeReportPdf(modal);

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
    var shareButton = event.target.closest('[data-report-share-pdf]');
    if (!shareButton) return;
    event.preventDefault();
    if (shareButton.dataset.busy === 'true') return;

    var modal = shareButton.closest('.simp-print-modal');
    var url = reportPdfUrl(modal, shareButton);
    if (!url) {
      reportPreviewAlert('Tautan PDF belum tersedia. Muat ulang pratinjau lalu coba kembali.', 'PDF Belum Tersedia', 'warning');
      return;
    }
    var title = shareButton.getAttribute('data-report-share-title') || 'Laporan MVIN';
    var entry = reportPdfCache[url];
    if (!entry || entry.error) entry = fetchReportPdf(url, title);
    if (!entry.file) {
      setReportShareBusy(shareButton, true);
      entry.promise.then(function () {
        setReportShareBusy(shareButton, false);
        reportPreviewAlert('PDF sudah siap. Tekan tombol WhatsApp sekali lagi untuk memilih penerima.', 'PDF Siap', 'info');
      }).catch(function (error) {
        setReportShareBusy(shareButton, false);
        reportPreviewAlert(error.message || 'PDF gagal disiapkan.', 'Berbagi PDF Gagal', 'danger');
      });
      return;
    }
    shareReportPdf(shareButton, entry.file, title);
    return;
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
