(function () {
  'use strict';

  function esc(value) {
    return String(value === null || typeof value === 'undefined' ? '' : value).replace(/[&<>'"]/g, function (character) {
      return {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        "'": '&#39;',
        '"': '&quot;'
      }[character];
    });
  }

  function parseJson(value, fallback) {
    try {
      var parsed = JSON.parse(value || '');
      return parsed === null ? fallback : parsed;
    } catch (error) {
      return fallback;
    }
  }

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

  function moneyValue(target) {
    var raw = moneyRaw(target);
    return raw && typeof raw.value !== 'undefined' ? String(raw.value || '') : '';
  }

  function canonicalMoney(value) {
    var match = String(value === null || typeof value === 'undefined' ? '' : value).trim().match(/^(\d+)(?:\.(\d{1,2}))?$/);
    if (!match) return '0.00';
    var major = match[1].replace(/^0+(?=\d)/, '') || '0';
    return major + '.' + String(match[2] || '').padEnd(2, '0');
  }

  function moneyCents(value) {
    var parts = canonicalMoney(value).split('.');
    return (BigInt(parts[0]) * 100n) + BigInt(parts[1]);
  }

  function moneyFromCents(value) {
    var cents = value < 0n ? 0n : value;
    return String(cents / 100n) + '.' + String(cents % 100n).padStart(2, '0');
  }

  function multiplyMoney(value, multiplier) {
    var count = Math.max(0, parseInt(multiplier, 10) || 0);
    return moneyFromCents(moneyCents(value) * BigInt(count));
  }

  function addMoney(left, right) {
    return moneyFromCents(moneyCents(left) + moneyCents(right));
  }

  function compareMoney(left, right) {
    var leftCents = moneyCents(left);
    var rightCents = moneyCents(right);
    return leftCents === rightCents ? 0 : (leftCents > rightCents ? 1 : -1);
  }

  function setMoney(target, value) {
    if (window.SimpMoney && typeof window.SimpMoney.set === 'function') {
      window.SimpMoney.set(moneyRaw(target), value);
      return;
    }
    var raw = moneyRaw(target);
    if (raw) raw.value = value === null || typeof value === 'undefined' ? '' : value;
  }

  function setMoneyValidity(target, message) {
    if (window.SimpMoney && typeof window.SimpMoney.setValidity === 'function') {
      window.SimpMoney.setValidity(moneyRaw(target), message || '');
      return;
    }
    var field = moneyDisplay(target);
    if (field && typeof field.setCustomValidity === 'function') field.setCustomValidity(message || '');
  }

  function setMoneyControl(target, properties) {
    var raw = moneyRaw(target);
    var display = moneyDisplay(target);
    Object.keys(properties || {}).forEach(function (property) {
      var value = properties[property];
      if (raw) raw[property] = value;
      if (display && display !== raw) display[property] = value;
    });
  }

  function refreshMoney(scope) {
    if (window.SimpMoney && typeof window.SimpMoney.refresh === 'function') window.SimpMoney.refresh(scope);
  }

  function requestJson(url) {
    return fetch(url, {
      credentials: 'same-origin',
      headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'}
    }).then(function (response) {
      return response.text().then(function (body) {
        var payload = null;
        try { payload = JSON.parse(body); } catch (ignored) { payload = null; }
        if (payload) {
          refreshCsrfFromPayload(payload);
          if (!response.ok || payload.success === false) {
            throw new Error(payload.message || 'Data gagal dimuat.');
          }
          return payload;
        }

        var responseUrl = String(response.url || '');
        if (response.redirected || /\/login(?:[/?#]|$)/i.test(responseUrl) || response.status === 401) {
          throw new Error('Sesi Anda telah berakhir. Silakan masuk kembali, lalu ulangi pemuatan data.');
        }
        if (response.status === 403 || response.status === 419) {
          throw new Error('Sesi keamanan form sudah tidak berlaku. Muat ulang halaman, lalu coba kembali.');
        }
        if (response.status >= 500) throw new Error('Data belum dapat dimuat karena terjadi gangguan server. Silakan coba kembali.');
        if (!response.ok) throw new Error('Data gagal dimuat. Silakan coba kembali.');
        throw new Error('Respons server tidak dapat dibaca. Muat ulang halaman, lalu coba kembali.');
      }, function () {
        throw new Error('Respons server tidak dapat dibaca. Muat ulang halaman, lalu coba kembali.');
      });
    }, function () {
      throw new Error('Koneksi ke server terputus. Periksa jaringan lalu coba kembali.');
    });
  }

  function formatCurrency(value) {
    if (window.SimpMoney && typeof window.SimpMoney.format === 'function') {
      return window.SimpMoney.format(canonicalMoney(value));
    }
    return new Intl.NumberFormat('id-ID', {
      style: 'currency',
      currency: 'IDR',
      maximumFractionDigits: 0
    }).format(Number(value) || 0);
  }

  function todayValue() {
    var today = new Date();
    var month = String(today.getMonth() + 1).padStart(2, '0');
    var day = String(today.getDate()).padStart(2, '0');
    return today.getFullYear() + '-' + month + '-' + day;
  }

  function truthy(value) {
    return value === true || value === 1 || value === '1' || value === 'true' || value === 'on';
  }

  function own(object, key) {
    return object && Object.prototype.hasOwnProperty.call(object, key) ? object[key] : undefined;
  }

  /*
   * Jabatan memakai combobox milik aplikasi, bukan <datalist> native. Pada
   * Android/iOS datalist ditampilkan sebagai deretan saran di atas keyboard,
   * sehingga operator tidak melihatnya sebagai dropdown. Nilai yang dikirim
   * tetap position_id; teks pencarian hanya memilih master jabatan aktif.
   */
  function normalizePositionName(value) {
    return String(value === null || typeof value === 'undefined' ? '' : value)
      .trim()
      .replace(/\s+/g, ' ')
      .toLocaleLowerCase();
  }

  function normalizeParticipantName(value) {
    var normalized = String(value === null || typeof value === 'undefined' ? '' : value);
    if (typeof normalized.normalize === 'function') normalized = normalized.normalize('NFKC');
    return normalized.trim().replace(/\s+/g, ' ');
  }

  function participantNameKey(value) {
    return normalizeParticipantName(value).toLocaleLowerCase();
  }

  function activeParticipantNames(excludedParticipantId) {
    var excluded = String(excludedParticipantId || '');
    return Array.prototype.map.call(document.querySelectorAll('#registration-detail-content [data-active-participant]'), function (participant) {
      if (excluded && String(participant.dataset.participantId || '') === excluded) return null;
      return participant.dataset.participantName || '';
    }).filter(function (name) { return participantNameKey(name) !== ''; });
  }

  /*
   * Frontend validation keeps repeated names from being sent accidentally.
   * The model performs the same check under the registration lock, so direct
   * or concurrent requests cannot bypass this convenience validation.
   */
  function validateUniqueParticipantNames(scope, existingNames) {
    var fields = scope ? scope.querySelectorAll('input[name$="[full_name]"], input[name="full_name"]') : [];
    var seen = {};
    var firstDuplicate = null;

    Array.prototype.forEach.call(fields, function (field) {
      if (field.dataset.duplicateNameError === '1') {
        field.setCustomValidity('');
        delete field.dataset.duplicateNameError;
      }
    });

    (existingNames || []).forEach(function (name) {
      var key = participantNameKey(name);
      if (key) seen[key] = {field: null};
    });

    Array.prototype.forEach.call(fields, function (field) {
      var displayName = normalizeParticipantName(field.value);
      var key = participantNameKey(displayName);
      if (!key) return;
      if (seen[key]) {
        var message = 'Nama peserta "' + displayName + '" sudah digunakan pada desa ini.';
        if (seen[key].field) {
          seen[key].field.setCustomValidity(message);
          seen[key].field.dataset.duplicateNameError = '1';
        }
        field.setCustomValidity(message);
        field.dataset.duplicateNameError = '1';
        if (!firstDuplicate) firstDuplicate = {field: field, message: message};
        return;
      }
      seen[key] = {field: field};
    });

    return firstDuplicate;
  }

  document.addEventListener('input', function (event) {
    var field = event.target;
    if (!field || !field.matches('input[name$="[full_name]"], input[name="full_name"]')) return;
    var villageCard = field.closest('[data-village]');
    if (villageCard) {
      validateUniqueParticipantNames(villageCard, []);
      return;
    }
    var addRows = field.closest('#registration-add-participants');
    if (addRows) {
      validateUniqueParticipantNames(addRows, []);
      return;
    }
    var detailRows = field.closest('#registration-participant-rows');
    if (detailRows) {
      validateUniqueParticipantNames(detailRows, activeParticipantNames(''));
      return;
    }
    var mutationForm = field.closest('#registration-participant-mutation-form');
    if (mutationForm) {
      var currentId = mutationForm.querySelector('input[name="participant_id"]');
      validateUniqueParticipantNames(mutationForm, activeParticipantNames(currentId ? currentId.value : ''));
    }
  });

  function positionPickerMarkup(positionList, selectedId, inputId, fieldName) {
    var pickerId = inputId + '-picker';
    var valueId = inputId + '-value';
    var listId = inputId + '-options';
    var selectedValue = String(selectedId || '');
    var selectedName = '';
    var options = [];

    (positionList || []).forEach(function (position) {
      if (!position || typeof position !== 'object') return;
      var id = String(position.id || '');
      var name = String(position.name || '').trim();
      if (!id || !name) return;
      var category = position.category_label || position.category || 'Lainnya';
      if (selectedValue === id) selectedName = name;
      var optionId = listId + '-option-' + options.length;
      options.push([
        '<button type="button" id="', esc(optionId), '" class="position-picker-option" role="option" tabindex="-1" aria-selected="false"',
          ' data-position-option data-position-id="', esc(id), '" data-position-name="', esc(name), '"',
          ' data-position-filter="', esc(normalizePositionName(name + ' ' + category)), '">',
          '<strong class="position-picker-name">', esc(name), '</strong><small>', esc(category), '</small>',
        '</button>'
      ].join(''));
    });

    var hasSelected = selectedName !== '' && selectedValue !== '';
    var validClass = hasSelected ? 'valid color-green-dark' : 'valid disabled color-green-dark';
    var invalidClass = hasSelected ? 'invalid disabled color-red-dark' : 'invalid disabled color-red-dark';
    var placeholder = options.length ? 'Ketik untuk mencari jabatan' : 'Belum ada jabatan aktif';

    return [
      '<div id="', esc(pickerId), '" class="input-style has-borders no-icon input-style-always-active mb-4 position-picker" data-position-picker data-position-value-id="', esc(valueId), '">',
        '<input class="form-control" type="search" id="', esc(inputId), '" value="', esc(selectedName), '" autocomplete="off" spellcheck="false" required data-position-search role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="', esc(listId), '" placeholder="', esc(placeholder), '">',
        '<label class="color-highlight" for="', esc(inputId), '">Jabatan</label>',
        '<i class="fa fa-times disabled ', invalidClass, '"></i><i class="fa fa-check ', validClass, '"></i><em>*</em>',
        '<button type="button" class="position-picker-toggle" data-position-toggle tabindex="-1" aria-label="Buka daftar jabatan"><i class="fa fa-chevron-down"></i></button>',
        '<div id="', esc(listId), '" class="position-picker-menu" role="listbox" data-position-options hidden>',
          options.join(''),
          '<p class="position-picker-empty" data-position-empty hidden>Tidak ada jabatan yang cocok.</p>',
        '</div>',
      '</div>',
      '<input type="hidden" id="', esc(valueId), '" name="', esc(fieldName), '" value="', esc(hasSelected ? selectedValue : ''), '" data-position-value>',
    ].join('');
  }

  function positionPickerOptions(picker) {
    if (!picker) return [];
    var menu = picker.querySelector('[data-position-options]');
    var idBase = menu && menu.id ? menu.id : 'position-options';
    var options = Array.prototype.slice.call(picker.querySelectorAll('[data-position-option]'));
    options.forEach(function (option, index) {
      if (!option.id) option.id = idBase + '-option-' + index;
      if (!option.hasAttribute('aria-selected')) option.setAttribute('aria-selected', 'false');
    });
    return options;
  }

  function filterPositionPicker(picker) {
    if (!picker) return [];
    var search = picker.querySelector('[data-position-search]');
    var query = normalizePositionName(search ? search.value : '');
    var visible = [];
    positionPickerOptions(picker).forEach(function (option) {
      var filterText = option.dataset.positionFilter || normalizePositionName((option.dataset.positionName || '') + ' ' + option.textContent);
      var matches = !query || String(filterText).indexOf(query) !== -1;
      option.hidden = !matches;
      if (!matches) option.classList.remove('is-active');
      if (matches) visible.push(option);
    });
    var empty = picker.querySelector('[data-position-empty]');
    if (empty) empty.hidden = visible.length > 0;
    return visible;
  }

  function openPositionPicker(picker) {
    if (!picker) return;
    var search = picker.querySelector('[data-position-search]');
    var menu = picker.querySelector('[data-position-options]');
    if (!search || !menu || search.disabled) return;
    var wasClosed = menu.hidden;
    filterPositionPicker(picker);
    menu.hidden = false;
    picker.classList.add('is-open');
    search.setAttribute('aria-expanded', 'true');
    if (wasClosed) {
      var reveal = function () {
        if (!menu.hidden) menu.scrollIntoView({block: 'nearest'});
      };
      window.requestAnimationFrame(reveal);
      window.setTimeout(reveal, 250);
    }
  }

  function closePositionPicker(picker) {
    if (!picker) return;
    var search = picker.querySelector('[data-position-search]');
    var menu = picker.querySelector('[data-position-options]');
    if (menu) menu.hidden = true;
    picker.classList.remove('is-open');
    positionPickerOptions(picker).forEach(function (option) { option.classList.remove('is-active'); });
    if (search) {
      search.setAttribute('aria-expanded', 'false');
      search.removeAttribute('aria-activedescendant');
    }
  }

  function choosePositionOption(picker, option) {
    if (!picker || !option) return false;
    var search = picker.querySelector('[data-position-search]');
    var valueId = picker.getAttribute('data-position-value-id');
    var hidden = valueId ? document.getElementById(valueId) : null;
    if (search) search.value = String(option.dataset.positionName || '');
    if (hidden) hidden.value = String(option.dataset.positionId || '');
    syncPositionPicker(picker);
    closePositionPicker(picker);
    return true;
  }

  function syncPositionPicker(picker) {
    if (!picker) return false;
    var search = picker.querySelector('[data-position-search]');
    var valueId = picker.getAttribute('data-position-value-id');
    var hidden = valueId ? document.getElementById(valueId) : null;
    if (!search) return false;

    var query = normalizePositionName(search.value);
    var match = null;
    if (query) {
      positionPickerOptions(picker).some(function (option) {
        if (normalizePositionName(option.dataset.positionName || '') !== query) return false;
        match = option;
        return true;
      });
    }

    if (hidden) hidden.value = match ? String(match.dataset.positionId || '') : '';
    if (!query) {
      search.setCustomValidity('');
    } else if (!match) {
      search.setCustomValidity('Pilih jabatan dari daftar yang tersedia.');
    } else {
      search.setCustomValidity('');
    }

    var validIcon = picker.querySelector('.valid');
    var invalidIcon = picker.querySelector('.invalid');
    if (validIcon) validIcon.classList.toggle('disabled', !match);
    if (invalidIcon) invalidIcon.classList.toggle('disabled', !query || !!match);
    positionPickerOptions(picker).forEach(function (option) {
      option.setAttribute('aria-selected', match === option ? 'true' : 'false');
    });
    return !!match;
  }

  function syncPositionPickers(scope) {
    var root = scope && scope.querySelectorAll ? scope : document;
    var valid = true;
    Array.prototype.forEach.call(root.querySelectorAll('[data-position-picker]'), function (picker) {
      if (!syncPositionPicker(picker)) valid = false;
    });
    return valid;
  }

  document.addEventListener('input', function (event) {
    var search = event.target.closest('[data-position-search]');
    if (search) {
      var picker = search.closest('[data-position-picker]');
      syncPositionPicker(picker);
      positionPickerOptions(picker).forEach(function (option) { option.classList.remove('is-active'); });
      search.removeAttribute('aria-activedescendant');
      openPositionPicker(picker);
    }
  });
  document.addEventListener('change', function (event) {
    var search = event.target.closest('[data-position-search]');
    if (search) syncPositionPicker(search.closest('[data-position-picker]'));
  });
  document.addEventListener('focusin', function (event) {
    var search = event.target.closest('[data-position-search]');
    if (search) openPositionPicker(search.closest('[data-position-picker]'));
  });
  document.addEventListener('focusout', function (event) {
    var picker = event.target.closest('[data-position-picker]');
    if (!picker) return;
    window.setTimeout(function () {
      if (!picker.contains(document.activeElement)) {
        syncPositionPicker(picker);
        closePositionPicker(picker);
      }
    }, 0);
  });
  document.addEventListener('click', function (event) {
    var option = event.target.closest('[data-position-option]');
    if (option) {
      event.preventDefault();
      var optionPicker = option.closest('[data-position-picker]');
      choosePositionOption(optionPicker, option);
      return;
    }
    var toggle = event.target.closest('[data-position-toggle]');
    if (toggle) {
      event.preventDefault();
      var togglePicker = toggle.closest('[data-position-picker]');
      var toggleMenu = togglePicker && togglePicker.querySelector('[data-position-options]');
      var shouldOpen = !toggleMenu || toggleMenu.hidden;
      var toggleSearch = togglePicker && togglePicker.querySelector('[data-position-search]');
      if (toggleSearch) toggleSearch.focus();
      if (shouldOpen) openPositionPicker(togglePicker);
      else closePositionPicker(togglePicker);
      return;
    }
    Array.prototype.forEach.call(document.querySelectorAll('[data-position-picker].is-open'), function (picker) {
      if (!picker.contains(event.target)) closePositionPicker(picker);
    });
  });
  document.addEventListener('keydown', function (event) {
    var search = event.target.closest('[data-position-search]');
    if (!search) return;
    var picker = search.closest('[data-position-picker]');
    if (event.key === 'Escape') {
      closePositionPicker(picker);
      return;
    }
    if (event.key === 'Tab') {
      syncPositionPicker(picker);
      closePositionPicker(picker);
      return;
    }
    if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp' && event.key !== 'Enter') return;
    var menu = picker.querySelector('[data-position-options]');
    if (!menu || menu.hidden) openPositionPicker(picker);
    var visible = filterPositionPicker(picker);
    if (!visible.length) return;
    var activeIndex = visible.findIndex(function (option) { return option.classList.contains('is-active'); });
    if (event.key === 'Enter') {
      if (activeIndex >= 0) {
        event.preventDefault();
        choosePositionOption(picker, visible[activeIndex]);
      }
      return;
    }
    event.preventDefault();
    activeIndex += event.key === 'ArrowDown' ? 1 : -1;
    if (activeIndex < 0) activeIndex = visible.length - 1;
    if (activeIndex >= visible.length) activeIndex = 0;
    visible.forEach(function (option, index) { option.classList.toggle('is-active', index === activeIndex); });
    search.setAttribute('aria-activedescendant', visible[activeIndex].id);
    visible[activeIndex].scrollIntoView({block: 'nearest'});
  });

  var form = document.getElementById('registration-create-form');
  if (form) {
    var events = parseJson(form.dataset.events, []);
    var positions = parseJson(form.dataset.positions, []);
    var accounts = parseJson(form.dataset.accounts, []);
    var canRecordPayment = form.dataset.canRecordPayment === '1';
    var oldVillages = parseJson(form.dataset.oldVillages, []);
    var oldParticipantGroups = parseJson(form.dataset.oldParticipantGroups, {});
    var oldNotes = parseJson(form.dataset.oldNotes, {});
    var oldPaymentGroups = parseJson(form.dataset.oldPaymentGroups, {});

    var eventSelect = document.getElementById('registration-event');
    var districtSelect = document.getElementById('registration-districts');
    var villageSelect = document.getElementById('registration-villages');
    var billingInfo = document.getElementById('registration-billing-info');
    var prepareButton = document.getElementById('prepare-villages');
    var cards = document.getElementById('village-registration-cards');
    var submitButton = document.getElementById('registration-submit');
    var participantCounter = 0;
    var participantDomCounter = 0;
    var paymentTokenCounter = 0;
    var usedPaymentTokens = {};
    var previousEventValue = eventSelect.value;
    var districtRequestToken = 0;
    var villageRequestToken = 0;
    var restoreRows = normalizeVillageRows(oldVillages);
    var restoreSelectionsPending = restoreRows.length > 0;
    var maxVillages = 20;
    var maxParticipants = 20;

    function alertUser(message, options) {
      if (typeof window.simpAlert === 'function') {
        window.simpAlert(message, options || {});
      }
    }

    function currentEvent() {
      for (var index = 0; index < events.length; index++) {
        if (String(events[index].id) === String(eventSelect.value)) return events[index];
      }
      return null;
    }

    function billingMode(eventData) {
      return eventData && eventData.billing_mode ? String(eventData.billing_mode) : '';
    }

    function includedParticipantCount(eventData) {
      return Math.max(0, parseInt(eventData && eventData.included_participant_count, 10) || 0);
    }

    function villageFee(eventData) {
      return canonicalMoney(eventData && eventData.village_fee);
    }

    function participantFee(eventData) {
      return canonicalMoney(eventData && eventData.participant_fee);
    }

    function expectedForVillage(eventData, participantCount) {
      var count = Math.max(0, parseInt(participantCount, 10) || 0);
      var mode = billingMode(eventData);
      if (mode === 'per_participant') return multiplyMoney(participantFee(eventData), count);
      if (mode === 'per_village_extra') {
        return addMoney(
          villageFee(eventData),
          multiplyMoney(participantFee(eventData), Math.max(0, count - includedParticipantCount(eventData)))
        );
      }
      return villageFee(eventData);
    }

    function billingDescription(eventData) {
      var mode = billingMode(eventData);
      if (mode === 'per_participant') return 'Per peserta: ' + formatCurrency(participantFee(eventData));
      if (mode === 'per_village_extra') {
        return 'Paket desa ' + formatCurrency(villageFee(eventData)) + ' mencakup ' +
          includedParticipantCount(eventData) + ' peserta, tambahan ' + formatCurrency(participantFee(eventData)) + ' per peserta.';
      }
      return 'Per desa: ' + formatCurrency(villageFee(eventData));
    }

    function normalizeVillageRows(input) {
      var source = [];
      if (Array.isArray(input)) {
        source = input;
      } else if (input && typeof input === 'object') {
        Object.keys(input).forEach(function (key) {
          var value = input[key];
          if (value && typeof value === 'object') {
            var copy = {};
            Object.keys(value).forEach(function (property) { copy[property] = value[property]; });
            copy._fallback_id = key;
            source.push(copy);
          } else {
            source.push({village_id: key, village_name: value});
          }
        });
      }

      var result = [];
      var seen = {};
      source.forEach(function (row) {
        if (row === null || typeof row === 'undefined') return;
        if (typeof row !== 'object') row = {village_id: row};
        var id = row.village_id || row.id || row.value || row._fallback_id;
        if (!id || seen[String(id)]) return;
        seen[String(id)] = true;
        result.push({
          id: String(id),
          name: String(row.village_name || row.name || row.label || id),
          districtId: String(row.district_id || ''),
          districtName: String(row.district_name || row.district || ''),
          regencyId: String(row.regency_id || ''),
          regencyName: String(row.regency_name || row.regency || '')
        });
      });
      return result;
    }

    function villageFromOption(option) {
      var labelParts = String(option.textContent || '').split(' · ');
      return {
        id: String(option.value),
        name: String(option.dataset.villageName || labelParts[labelParts.length - 1] || option.value),
        districtId: String(option.dataset.districtId || ''),
        districtName: String(option.dataset.district || (labelParts.length > 1 ? labelParts[0] : '')),
        regencyId: String(option.dataset.regencyId || ''),
        regencyName: String(option.dataset.regency || '')
      };
    }

    function accountOptions(selectedId) {
      return '<option value="">Pilih akun dana</option>' + accounts.map(function (account) {
        var selected = String(selectedId || '') === String(account.id) ? ' selected' : '';
        return '<option value="' + esc(account.id) + '" data-type="' + esc(account.type) + '"' + selected + '>' + esc(account.name) + '</option>';
      }).join('');
    }

    function paymentState(villageId, targetKey) {
      var villageGroup = own(oldPaymentGroups, String(villageId));
      return own(villageGroup, String(targetKey)) || {};
    }

    function reservePaymentToken(requestedToken) {
      var token = String(requestedToken || '');
      if (!/^[A-Za-z0-9]+$/.test(token) || usedPaymentTokens[token]) token = '';
      if (token) {
        usedPaymentTokens[token] = true;
        var match = token.match(/^pay(\d+)$/i);
        if (match) paymentTokenCounter = Math.max(paymentTokenCounter, parseInt(match[1], 10));
        return token;
      }
      do {
        token = 'pay' + (++paymentTokenCounter);
      } while (usedPaymentTokens[token]);
      usedPaymentTokens[token] = true;
      return token;
    }

    function normalizeParticipantKey(requestedKey, villageCard) {
      var key = String(requestedKey || '');
      if (!/^[A-Za-z0-9_-]+$/.test(key) || (villageCard && villageCard.querySelector('[data-participant-key="' + key + '"]'))) key = '';
      if (key) {
        var match = key.match(/^p(\d+)$/i);
        if (match) participantCounter = Math.max(participantCounter, parseInt(match[1], 10));
        return key;
      }
      do {
        key = 'p' + (++participantCounter);
      } while (villageCard && villageCard.querySelector('[data-participant-key="' + key + '"]'));
      return key;
    }

    function paymentBlock(villageId, targetKey, state, targetAmount, targetLabel) {
      state = state || {};
      var token = reservePaymentToken(state.token);
      var prefix = 'payments[' + esc(villageId) + '][' + esc(targetKey) + ']';
      var enabledId = 'payment-enabled-' + token;
      var dateId = 'payment-date-' + token;
      var methodId = 'payment-method-' + token;
      var accountId = 'payment-account-' + token;
      var amountId = 'payment-amount-' + token;
      var proofId = 'payment-proof-' + token;
      var noteId = 'payment-note-' + token;
      var enabled = truthy(state.enabled);
      var method = state.method || 'cash';
      var amount = state.amount === null || typeof state.amount === 'undefined' ? '' : state.amount;
      var paymentDate = state.payment_date || todayValue();

      return [
        '<div class="rounded-s bg-gray-light p-3 mt-3" data-payment-block data-payment-token="', esc(token), '" data-target-amount="', esc(targetAmount), '">',
          '<div class="d-flex align-items-start">',
            '<span class="icon icon-s rounded-xl bg-green-light color-green-dark me-3 flex-shrink-0"><i class="fa fa-money-bill-wave"></i></span>',
            '<div class="min-width-zero pe-2">',
              '<h5 class="font-13 mb-n1">Pembayaran Awal</h5>',
              '<p class="font-10 opacity-60 mb-0">', esc(targetLabel), ' · maksimal <span data-payment-maximum-text>', formatCurrency(targetAmount), '</span></p>',
            '</div>',
          '</div>',
          '<div class="form-check icon-check mt-3 mb-1">',
            '<input class="form-check-input" type="checkbox" name="', prefix, '[enabled]" value="1" id="', enabledId, '" data-payment-toggle', enabled ? ' checked' : '', '>',
            '<label class="form-check-label font-12 font-600" for="', enabledId, '">Catat pembayaran sekarang</label>',
            '<i class="icon-check-1 far fa-square color-gray-dark font-16"></i>',
            '<i class="icon-check-2 far fa-check-square color-highlight font-16"></i>',
          '</div>',
          '<div class="mt-3', enabled ? '' : ' d-none', '" data-payment-fields>',
            '<input type="hidden" name="', prefix, '[token]" value="', esc(token), '" data-payment-input>',
            '<div class="row mb-0">',
              '<div class="col-12 col-md-6">',
                '<div class="input-style has-borders no-icon input-style-always-active mb-4">',
                  '<input type="date" class="form-control" id="', dateId, '" name="', prefix, '[payment_date]" value="', esc(paymentDate), '" data-payment-input data-payment-required>',
                  '<label for="', dateId, '" class="color-highlight">Tanggal pembayaran</label>',
                  '<i class="fa fa-check disabled valid color-green-dark"></i><i class="fa fa-times disabled invalid color-red-dark"></i><em>*</em>',
                '</div>',
              '</div>',
              '<div class="col-12 col-md-6">',
                '<div class="input-style has-borders no-icon input-style-always-active mb-4">',
                  '<label for="', methodId, '" class="color-highlight">Metode pembayaran</label>',
                  '<select class="form-select" id="', methodId, '" name="', prefix, '[method]" data-payment-input data-payment-required data-payment-method>',
                    '<option value="cash"', method === 'cash' ? ' selected' : '', '>Tunai</option>',
                    '<option value="transfer"', method === 'transfer' ? ' selected' : '', '>Transfer</option>',
                    '<option value="qris"', method === 'qris' ? ' selected' : '', '>QRIS</option>',
                  '</select>',
                  '<span><i class="fa fa-chevron-down"></i></span><i class="fa fa-check disabled valid color-green-dark"></i><i class="fa fa-times disabled invalid color-red-dark"></i><em></em>',
                '</div>',
              '</div>',
              '<div class="col-12 col-md-6">',
                '<div class="input-style has-borders no-icon input-style-always-active mb-4">',
                  '<label for="', accountId, '" class="color-highlight">Akun penerima</label>',
                  '<select class="form-select" id="', accountId, '" name="', prefix, '[account_id]" data-payment-input data-payment-required data-payment-account>',
                    accountOptions(state.account_id),
                  '</select>',
                  '<span><i class="fa fa-chevron-down"></i></span><i class="fa fa-check disabled valid color-green-dark"></i><i class="fa fa-times disabled invalid color-red-dark"></i><em></em>',
                '</div>',
              '</div>',
              '<div class="col-12 col-md-6">',
                '<div class="input-style has-borders no-icon input-style-always-active mb-1">',
                  '<input type="number" data-money min="1" step="1" class="form-control" id="', amountId, '" name="', prefix, '[amount]" value="', esc(amount), '" placeholder="Rp 0" data-payment-input data-payment-required data-payment-amount>',
                  '<label for="', amountId, '" class="color-highlight">Nominal pembayaran</label>',
                  '<i class="fa fa-check disabled valid color-green-dark"></i><i class="fa fa-times disabled invalid color-red-dark"></i><em>*</em>',
                '</div>',
                '<div class="text-end mb-3"><button type="button" class="btn btn-xxs border-green-dark color-green-dark rounded-s font-600" data-fill-payment><i class="fa fa-check-circle me-1"></i>Isi Lunas</button></div>',
              '</div>',
              '<div class="col-12">',
                '<div class="input-style has-borders no-icon input-style-always-active mb-1">',
                  '<input type="file" class="form-control" id="', proofId, '" name="payment_proof_', esc(token), '" accept="image/jpeg,image/png,application/pdf" style="padding-top:13px;" data-payment-input data-payment-proof>',
                  '<label for="', proofId, '" class="color-highlight">Bukti pembayaran</label>',
                  '<i class="fa fa-check disabled valid color-green-dark"></i><i class="fa fa-times disabled invalid color-red-dark"></i><em data-proof-required-label></em>',
                '</div>',
                '<p class="font-10 opacity-60 mb-4 ps-2">JPG, PNG, atau PDF maksimal 5 MB. Bukti diperlukan untuk Transfer/QRIS.</p>',
              '</div>',
              '<div class="col-12">',
                '<div class="input-style has-borders no-icon input-style-always-active mb-1">',
                  '<textarea class="form-control" id="', noteId, '" name="', prefix, '[note]" rows="2" maxlength="2000" placeholder="Catatan pembayaran" data-payment-input>', esc(state.note || ''), '</textarea>',
                  '<label for="', noteId, '" class="color-highlight">Catatan pembayaran</label><em class="mt-n3"></em>',
                '</div>',
              '</div>',
            '</div>',
          '</div>',
        '</div>'
      ].join('');
    }

    function participant(villageId, requestedKey, participantState, villageCard) {
      participantState = participantState || {};
      var key = normalizeParticipantKey(requestedKey, villageCard);
      var domKey = 'participant-' + (++participantDomCounter);
      var nameId = 'participant-name-' + domKey;
      var positionId = 'participant-position-' + domKey;
      var phoneId = 'participant-phone-' + domKey;
      var selectedEvent = currentEvent();
      var participantPayment = '';
      if (canRecordPayment && billingMode(selectedEvent) === 'per_participant') {
        participantPayment = paymentBlock(
          villageId,
          key,
          paymentState(villageId, key),
          participantFee(selectedEvent),
          'Pembayaran peserta ini'
        );
      }

      return [
        '<div class="card bg-theme border rounded-s shadow-0 mb-3" data-participant-row data-participant-key="', esc(key), '"><div class="content my-3">',
          '<div class="d-flex align-items-start">',
            '<span class="icon icon-s rounded-xl bg-blue-light color-blue-dark me-2 flex-shrink-0"><i class="fa fa-user"></i></span>',
            '<div class="min-width-zero pe-2"><h5 class="font-14 mb-n1">Data Peserta</h5><p class="font-10 opacity-60 mb-0" data-participant-charge></p></div>',
            '<button type="button" class="btn btn-xxs border-red-dark color-red-dark rounded-s font-600 ms-auto flex-shrink-0" data-remove-participant><i class="fa fa-times me-1"></i>Hapus</button>',
          '</div>',
          '<div class="divider mt-3 mb-3"></div>',
          '<div class="row mb-0">',
            '<div class="col-12 col-md-4">',
              '<div class="input-style has-borders no-icon input-style-always-active mb-4">',
                '<input class="form-control" id="', nameId, '" required maxlength="160" name="participants[', esc(villageId), '][', esc(key), '][full_name]" value="', esc(participantState.full_name || ''), '" placeholder="Nama lengkap">',
                '<label class="color-highlight" for="', nameId, '">Nama lengkap</label><i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em>*</em>',
              '</div>',
            '</div>',
            '<div class="col-12 col-md-4">',
              positionPickerMarkup(positions, participantState.position_id, positionId, 'participants[' + villageId + '][' + key + '][position_id]'),
            '</div>',
            '<div class="col-12 col-md-4">',
              '<div class="input-style has-borders no-icon input-style-always-active mb-4">',
                '<input class="form-control" type="tel" id="', phoneId, '" maxlength="30" name="participants[', esc(villageId), '][', esc(key), '][phone]" value="', esc(participantState.phone || ''), '" placeholder="Nomor HP">',
                '<label class="color-highlight" for="', phoneId, '">Nomor HP</label><i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em></em>',
              '</div>',
            '</div>',
          '</div>',
          participantPayment,
        '</div></div>'
      ].join('');
    }

    function participantEntries(villageId) {
      var group = own(oldParticipantGroups, String(villageId));
      if (!group || typeof group !== 'object') return [];
      return Object.keys(group).map(function (key) {
        return {key: key, value: group[key] || {}};
      });
    }

    function billingBreakdown() {
      return [
        '<div class="rounded-s bg-blue-light px-3 py-2 mb-3" data-billing-breakdown>',
          '<div class="d-flex align-items-center py-2 border-bottom" data-billing-base-row><span class="font-11 color-blue-dark" data-billing-base-label>Tagihan</span><strong class="font-12 color-blue-dark ms-auto text-end" data-billing-base-amount></strong></div>',
          '<div class="d-flex align-items-center py-2 border-bottom d-none" data-billing-included-row><span class="font-11 color-blue-dark">Termasuk paket desa</span><strong class="font-12 color-blue-dark ms-auto" data-billing-included></strong></div>',
          '<div class="d-flex align-items-center py-2 border-bottom d-none" data-billing-extra-row><span class="font-11 color-blue-dark">Peserta tambahan</span><strong class="font-12 color-blue-dark ms-auto text-end" data-billing-extra></strong></div>',
          '<div class="d-flex align-items-center py-2"><span class="font-12 color-blue-dark font-600">Total tagihan desa</span><strong class="font-14 color-blue-dark ms-auto text-end" data-billing-total></strong></div>',
        '</div>'
      ].join('');
    }

    function buildVillageCard(village, restoreState) {
      var villageId = String(village.id);
      var noteId = 'village-note-' + (++participantDomCounter);
      var note = restoreState ? (own(oldNotes, villageId) || '') : '';
      var entries = restoreState ? participantEntries(villageId) : [];
      var selectedEvent = currentEvent();
      var villagePayment = '';
      var participantMarkup = '';
      if (!entries.length) entries.push({key: '', value: {}});
      entries.forEach(function (entry) {
        participantMarkup += participant(villageId, entry.key, entry.value, null);
      });
      if (canRecordPayment && billingMode(selectedEvent) !== 'per_participant') {
        villagePayment = paymentBlock(
          villageId,
          'village',
          restoreState ? paymentState(villageId, 'village') : {},
          expectedForVillage(selectedEvent, entries.length),
          'Pembayaran tingkat desa'
        );
      }

      var wrapper = document.createElement('div');
      wrapper.innerHTML = [
        '<div class="card card-style mx-0 mb-3 shadow-s" data-village="', esc(villageId), '">',
          '<input type="hidden" name="village_ids[]" value="', esc(villageId), '">',
          '<div class="content mb-2">',
            '<div class="d-flex align-items-start flex-wrap">',
              '<span class="icon icon-m rounded-xl gradient-blue color-white shadow-s me-3 flex-shrink-0"><i class="fa fa-building"></i></span>',
              '<div class="min-width-zero me-2">',
                '<p class="font-11 color-highlight mb-n1">', esc(village.districtName || village.regencyName || 'Wilayah event'), '</p>',
                '<h4 class="mb-n1">', esc(village.name), '</h4>',
                '<p class="font-10 opacity-60 mb-0" data-village-billing-summary></p>',
              '</div>',
              '<button type="button" class="btn btn-xxs gradient-highlight rounded-s font-600 ms-auto mt-1 flex-shrink-0" data-add-participant><i class="fa fa-user-plus me-1"></i>Peserta</button>',
            '</div>',
            '<div class="divider mt-3"></div>',
            '<div data-participants>', participantMarkup, '</div>',
            billingBreakdown(),
            '<div class="input-style has-borders no-icon input-style-always-active mb-3">',
              '<textarea class="form-control" id="', noteId, '" name="notes[', esc(villageId), ']" rows="2" maxlength="2000" placeholder="Catatan desa">', esc(note), '</textarea>',
              '<label class="color-highlight" for="', noteId, '">Catatan desa</label><i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em class="mt-n3"></em>',
            '</div>',
            villagePayment,
          '</div>',
        '</div>'
      ].join('');
      return wrapper.firstElementChild;
    }

    function resetPreparedCards() {
      participantCounter = 0;
      participantDomCounter = 0;
      paymentTokenCounter = 0;
      usedPaymentTokens = {};
      cards.innerHTML = [
        '<div class="d-flex align-items-center py-3">',
          '<span class="icon icon-m rounded-xl bg-blue-light color-blue-dark me-3"><i class="fa fa-info"></i></span>',
          '<div><h5 class="font-14 mb-0">Form peserta belum disiapkan</h5><p class="font-11 opacity-60 mb-0">Pilih desa pada langkah pertama, lalu tekan “Siapkan Form Peserta”.</p></div>',
        '</div>'
      ].join('');
    }

    function compatibleAccountType(method, type) {
      if (method === 'cash') return type === 'cash';
      if (method === 'transfer') return type === 'bank' || type === 'personal';
      if (method === 'qris') return type === 'qris';
      return false;
    }

    function filterPaymentAccounts(block) {
      var method = block.querySelector('[data-payment-method]');
      var account = block.querySelector('[data-payment-account]');
      if (!method || !account) return;
      var selectedCompatible = !account.value;
      Array.prototype.forEach.call(account.options, function (option) {
        if (!option.value) {
          option.hidden = false;
          option.disabled = false;
          return;
        }
        var compatible = compatibleAccountType(method.value, option.dataset.type || '');
        option.hidden = !compatible;
        option.disabled = !compatible;
        if (option.selected && compatible) selectedCompatible = true;
      });
      if (!selectedCompatible) account.value = '';
    }

    function updateProofRequirement(block) {
      var toggle = block.querySelector('[data-payment-toggle]');
      var method = block.querySelector('[data-payment-method]');
      var proof = block.querySelector('[data-payment-proof]');
      var label = block.querySelector('[data-proof-required-label]');
      if (!toggle || !method || !proof) return;
      var required = toggle.checked && method.value !== 'cash';
      proof.required = required;
      if (label) label.textContent = required ? '*' : '';
    }

    function updatePaymentAmount(block, targetAmount, fillWhenFull) {
      var amount = block.querySelector('[data-payment-amount]');
      var maximumText = block.querySelector('[data-payment-maximum-text]');
      var toggle = block.querySelector('[data-payment-toggle]');
      if (!amount) return;
      var oldMaximum = canonicalMoney(block.dataset.targetAmount);
      var currentAmount = moneyValue(amount);
      var wasFull = currentAmount !== '' && compareMoney(currentAmount, oldMaximum) === 0;
      var maximum = canonicalMoney(targetAmount);
      block.dataset.targetAmount = maximum;
      setMoneyControl(amount, {max: maximum});
      if (maximumText) maximumText.textContent = formatCurrency(maximum);
      if (toggle && toggle.checked && (currentAmount === '' || (fillWhenFull && wasFull))) {
        setMoney(amount, moneyCents(maximum) > 0n ? maximum : '');
        currentAmount = moneyValue(amount);
      }
      if (currentAmount !== '' && compareMoney(currentAmount, maximum) > 0) {
        setMoneyValidity(amount, 'Nominal melebihi total tagihan ' + formatCurrency(maximum) + '.');
      } else {
        setMoneyValidity(amount, '');
      }
    }

    function syncPaymentBlock(block, fillAmount) {
      var toggle = block.querySelector('[data-payment-toggle]');
      var fields = block.querySelector('[data-payment-fields]');
      if (!toggle || !fields) return;
      var enabled = toggle.checked;
      if (enabled && moneyCents(block.dataset.targetAmount) <= 0n) {
        toggle.checked = false;
        enabled = false;
        alertUser('Tagihan target ini Rp 0 sehingga pembayaran tidak perlu dicatat.', {title: 'Tidak Ada Tagihan', tone: 'warning'});
      }
      fields.classList.toggle('d-none', !enabled);
      Array.prototype.forEach.call(fields.querySelectorAll('[data-payment-input]'), function (input) {
        if (input.hasAttribute('data-money')) {
          setMoneyControl(input, {
            disabled: !enabled,
            required: enabled && input.hasAttribute('data-payment-required')
          });
        } else {
          input.disabled = !enabled;
          input.required = enabled && input.hasAttribute('data-payment-required');
        }
      });
      filterPaymentAccounts(block);
      updateProofRequirement(block);
      updatePaymentAmount(block, block.dataset.targetAmount, fillAmount);
    }

    function initializePaymentBlocks(scope) {
      if (window.SimpMoney && typeof window.SimpMoney.init === 'function') window.SimpMoney.init(scope);
      Array.prototype.forEach.call(scope.querySelectorAll('[data-payment-block]'), function (block) {
        syncPaymentBlock(block, false);
      });
    }

    function updateVillageBilling(card) {
      var selectedEvent = currentEvent();
      if (!selectedEvent || !card) return;
      var rows = Array.prototype.slice.call(card.querySelectorAll('[data-participant-row]'));
      var count = rows.length;
      var mode = billingMode(selectedEvent);
      var included = includedParticipantCount(selectedEvent);
      var extras = Math.max(0, count - included);
      var total = expectedForVillage(selectedEvent, count);
      var summary = card.querySelector('[data-village-billing-summary]');
      var baseLabel = card.querySelector('[data-billing-base-label]');
      var baseAmount = card.querySelector('[data-billing-base-amount]');
      var includedRow = card.querySelector('[data-billing-included-row]');
      var includedValue = card.querySelector('[data-billing-included]');
      var extraRow = card.querySelector('[data-billing-extra-row]');
      var extraValue = card.querySelector('[data-billing-extra]');
      var totalValue = card.querySelector('[data-billing-total]');

      rows.forEach(function (row, index) {
        var charge = row.querySelector('[data-participant-charge]');
        if (!charge) return;
        if (mode === 'per_participant') {
          charge.textContent = 'Tagihan ' + formatCurrency(participantFee(selectedEvent));
        } else if (mode === 'per_village_extra') {
          charge.textContent = index < included ? 'Termasuk paket desa' : 'Peserta tambahan · ' + formatCurrency(participantFee(selectedEvent));
        } else {
          charge.textContent = 'Tagihan dicatat per desa';
        }
      });

      if (mode === 'per_participant') {
        if (summary) summary.textContent = count + ' peserta · ' + formatCurrency(total);
        if (baseLabel) baseLabel.textContent = count + ' peserta × ' + formatCurrency(participantFee(selectedEvent));
        if (baseAmount) baseAmount.textContent = formatCurrency(total);
        if (includedRow) includedRow.classList.add('d-none');
        if (extraRow) extraRow.classList.add('d-none');
      } else if (mode === 'per_village_extra') {
        if (summary) summary.textContent = 'Paket desa + ' + extras + ' peserta tambahan · ' + formatCurrency(total);
        if (baseLabel) baseLabel.textContent = 'Paket desa';
        if (baseAmount) baseAmount.textContent = formatCurrency(villageFee(selectedEvent));
        if (includedRow) includedRow.classList.remove('d-none');
        if (includedValue) includedValue.textContent = included + ' orang';
        if (extraRow) extraRow.classList.remove('d-none');
        if (extraValue) extraValue.textContent = extras + ' × ' + formatCurrency(participantFee(selectedEvent)) + ' = ' + formatCurrency(multiplyMoney(participantFee(selectedEvent), extras));
      } else {
        if (summary) summary.textContent = count + ' peserta · ' + formatCurrency(total) + ' per desa';
        if (baseLabel) baseLabel.textContent = 'Tagihan tetap per desa';
        if (baseAmount) baseAmount.textContent = formatCurrency(villageFee(selectedEvent));
        if (includedRow) includedRow.classList.add('d-none');
        if (extraRow) extraRow.classList.add('d-none');
      }
      if (totalValue) totalValue.textContent = formatCurrency(total);

      var villagePayment = card.querySelector('[data-payment-block]');
      if (mode !== 'per_participant' && villagePayment) updatePaymentAmount(villagePayment, total, false);
    }

    function updateAllVillageBilling() {
      Array.prototype.forEach.call(cards.querySelectorAll('[data-village]'), updateVillageBilling);
    }

    function renderRestoredCards() {
      if (!restoreRows.length) return;
      if (restoreRows.length > maxVillages) {
        alertUser('Maksimal ' + maxVillages + ' desa dalam satu kali registrasi. Hanya ' + maxVillages + ' desa pertama yang dipulihkan.', {title: 'Batas Registrasi', tone: 'warning'});
        restoreRows = restoreRows.slice(0, maxVillages);
      }
      cards.innerHTML = '';
      restoreRows.forEach(function (village) {
        var card = buildVillageCard(village, true);
        cards.appendChild(card);
        initializePaymentBlocks(card);
        updateVillageBilling(card);
      });
    }

    function loadVillages(districtIds, restoreSelection) {
      var requestToken = ++villageRequestToken;
      villageSelect.disabled = true;
      villageSelect.innerHTML = '';
      if (!districtIds.length) return Promise.resolve();
      var query = districtIds.map(function (districtId) {
        return 'district_ids[]=' + encodeURIComponent(districtId);
      }).join('&');

      return requestJson(form.dataset.villagesUrl + '?' + query).then(function (response) {
        if (requestToken !== villageRequestToken) return;
        villageSelect.innerHTML = (response.data || []).map(function (village) {
          var selected = restoreSelection && restoreRows.some(function (row) { return row.id === String(village.id); });
          return '<option value="' + esc(village.id) + '" data-village-name="' + esc(village.name) + '" data-district-id="' + esc(village.district_id) + '" data-district="' + esc(village.district_name) + '" data-regency-id="' + esc(village.regency_id) + '"' + (selected ? ' selected' : '') + '>' + esc(village.district_name + ' · ' + village.name) + '</option>';
        }).join('');
        villageSelect.disabled = false;
      }).catch(function () {
        if (requestToken !== villageRequestToken) return;
        alertUser('Daftar desa gagal dimuat. Silakan coba kembali.', {title: 'Desa Gagal Dimuat', tone: 'danger'});
      });
    }

    function loadDistricts(allowRestore) {
      var selectedEvent = currentEvent();
      var requestToken = ++districtRequestToken;
      villageRequestToken++;
      districtSelect.innerHTML = '';
      villageSelect.innerHTML = '';
      districtSelect.disabled = true;
      villageSelect.disabled = true;

      if (!selectedEvent) {
        billingInfo.textContent = 'Pilih event untuk melihat biaya.';
        return;
      }
      billingInfo.textContent = billingDescription(selectedEvent);
      var regions = Array.isArray(selectedEvent.regencies) ? selectedEvent.regencies : [];
      var query = regions.map(function (region) {
        return 'regency_ids[]=' + encodeURIComponent(region.regency_id);
      }).join('&');
      if (!query) {
        alertUser('Event belum mempunyai wilayah kabupaten/kota.', {title: 'Wilayah Event Kosong', tone: 'warning'});
        return;
      }

      requestJson(form.dataset.districtsUrl + '?' + query).then(function (response) {
        if (requestToken !== districtRequestToken || String(eventSelect.value) !== String(selectedEvent.id)) return;
        var restoreDistricts = {};
        if (allowRestore && restoreSelectionsPending) {
          restoreRows.forEach(function (row) { if (row.districtId) restoreDistricts[row.districtId] = true; });
        }
        districtSelect.innerHTML = (response.data || []).map(function (district) {
          var selected = !!restoreDistricts[String(district.id)];
          return '<option value="' + esc(district.id) + '" data-regency-id="' + esc(district.regency_id) + '"' + (selected ? ' selected' : '') + '>' + esc(district.regency_name + ' · ' + district.name) + '</option>';
        }).join('');
        districtSelect.disabled = false;
        var districtIds = Object.keys(restoreDistricts);
        if (districtIds.length) loadVillages(districtIds, true);
        restoreSelectionsPending = false;
      }).catch(function () {
        if (requestToken !== districtRequestToken) return;
        alertUser('Daftar kecamatan gagal dimuat. Silakan coba kembali.', {title: 'Kecamatan Gagal Dimuat', tone: 'danger'});
      });
    }

    function applyEventChange() {
      previousEventValue = eventSelect.value;
      restoreSelectionsPending = false;
      restoreRows = [];
      oldParticipantGroups = {};
      oldNotes = {};
      oldPaymentGroups = {};
      resetPreparedCards();
      loadDistricts(false);
    }

    eventSelect.addEventListener('change', function () {
      if (cards.querySelector('[data-village]')) {
        var requestedEventValue = eventSelect.value;
        if (typeof window.simpConfirm !== 'function') {
          eventSelect.value = previousEventValue;
          return;
        }
        window.simpConfirm('Mengganti event akan menghapus data peserta dan pembayaran yang sudah diisi. Lanjutkan?', {
          title: 'Ganti Event?',
          confirmLabel: 'Ya, Ganti',
          tone: 'warning'
        }).then(function (confirmed) {
          if (!confirmed) {
            eventSelect.value = previousEventValue;
            return;
          }
          eventSelect.value = requestedEventValue;
          applyEventChange();
        });
        return;
      }
      applyEventChange();
    });

    districtSelect.addEventListener('change', function () {
      var districtIds = Array.prototype.map.call(districtSelect.selectedOptions, function (option) {
        return option.value;
      });
      loadVillages(districtIds, false);
    });

    function reconcileVillageCards(selectedVillages) {
      var existing = {};
      Array.prototype.forEach.call(cards.querySelectorAll('[data-village]'), function (card) {
        existing[String(card.dataset.village)] = card;
      });
      var selectedIds = {};
      selectedVillages.forEach(function (village) { selectedIds[village.id] = true; });
      var removed = Object.keys(existing).filter(function (id) { return !selectedIds[id]; });

      function render() {
        var fragment = document.createDocumentFragment();
        selectedVillages.forEach(function (village) {
          var card = existing[village.id] || buildVillageCard(village, false);
          fragment.appendChild(card);
          if (!existing[village.id]) initializePaymentBlocks(card);
          updateVillageBilling(card);
        });
        cards.innerHTML = '';
        cards.appendChild(fragment);
      }

      if (!removed.length) {
        render();
        return;
      }
      if (typeof window.simpConfirm !== 'function') return;
      window.simpConfirm('Desa yang tidak lagi dipilih beserta data peserta dan pembayarannya akan dihapus dari form. Lanjutkan?', {
        title: 'Perbarui Daftar Desa?',
        confirmLabel: 'Ya, Perbarui',
        tone: 'warning'
      }).then(function (confirmed) {
        if (confirmed) {
          render();
          return;
        }
        var existingIds = {};
        Object.keys(existing).forEach(function (id) { existingIds[id] = true; });
        Array.prototype.forEach.call(villageSelect.options, function (option) {
          option.selected = !!existingIds[String(option.value)];
        });
      });
    }

    prepareButton.addEventListener('click', function () {
      var selectedOptions = Array.prototype.slice.call(villageSelect.selectedOptions);
      if (!selectedOptions.length) {
        alertUser('Pilih minimal satu desa.', {title: 'Desa Belum Dipilih', tone: 'warning'});
        return;
      }
      if (selectedOptions.length > maxVillages) {
        alertUser('Maksimal ' + maxVillages + ' desa dalam satu kali registrasi.', {title: 'Terlalu Banyak Desa', tone: 'warning'});
        return;
      }
      reconcileVillageCards(selectedOptions.map(villageFromOption));
    });

    cards.addEventListener('click', function (event) {
      var villageCard = event.target.closest('[data-village]');
      if (!villageCard) return;

      if (event.target.closest('[data-add-participant]')) {
        var participantContainer = villageCard.querySelector('[data-participants]');
        if (participantContainer.querySelectorAll('[data-participant-row]').length >= maxParticipants) {
          alertUser('Maksimal ' + maxParticipants + ' peserta untuk setiap desa.', {title: 'Batas Peserta', tone: 'warning'});
          return;
        }
        participantContainer.insertAdjacentHTML('beforeend', participant(villageCard.dataset.village, '', {}, villageCard));
        var addedRow = participantContainer.lastElementChild;
        initializePaymentBlocks(addedRow);
        updateVillageBilling(villageCard);
        return;
      }

      var removeButton = event.target.closest('[data-remove-participant]');
      if (removeButton) {
        var participantRows = villageCard.querySelectorAll('[data-participant-row]');
        if (participantRows.length <= 1) {
          alertUser('Minimal satu peserta per desa.', {title: 'Peserta Belum Diisi', tone: 'warning'});
          return;
        }
        var row = removeButton.closest('[data-participant-row]');
        var paymentToggle = row.querySelector('[data-payment-toggle]');
        if (paymentToggle && paymentToggle.checked && typeof window.simpConfirm === 'function') {
          window.simpConfirm('Peserta ini memiliki data pembayaran. Hapus peserta beserta pembayaran dari form?', {
            title: 'Hapus Peserta?',
            confirmLabel: 'Ya, Hapus',
            tone: 'danger'
          }).then(function (confirmed) {
            if (confirmed) {
              row.remove();
              updateVillageBilling(villageCard);
            }
          });
        } else {
          row.remove();
          updateVillageBilling(villageCard);
        }
        return;
      }

      var fillButton = event.target.closest('[data-fill-payment]');
      if (fillButton) {
        var block = fillButton.closest('[data-payment-block]');
        var amount = block.querySelector('[data-payment-amount]');
        setMoney(amount, moneyCents(block.dataset.targetAmount) > 0n ? block.dataset.targetAmount : '');
        setMoneyValidity(amount, '');
      }
    });

    cards.addEventListener('change', function (event) {
      var block = event.target.closest('[data-payment-block]');
      if (!block) return;
      if (event.target.matches('[data-payment-toggle]')) syncPaymentBlock(block, true);
      if (event.target.matches('[data-payment-method]')) {
        filterPaymentAccounts(block);
        updateProofRequirement(block);
      }
    });

    cards.addEventListener('input', function (event) {
      var amount = moneyRaw(event.target);
      // SimpMoney memancarkan ulang `input` pada raw hidden setelah input
      // tampilan berubah. Tangani hanya event raw tersebut agar validasi tidak
      // dijalankan dua kali (sekali dari visible, sekali dari raw).
      if (!amount || event.target !== amount || !amount.matches('[data-payment-amount]')) return;
      var block = amount.closest('[data-payment-block]');
      updatePaymentAmount(block, block.dataset.targetAmount, false);
    });

    form.addEventListener('submit', function (event) {
      var preparedCards = cards.querySelectorAll('[data-village]');
      if (!preparedCards.length) {
        event.preventDefault();
        alertUser('Siapkan minimal satu form desa beserta pesertanya.', {title: 'Registrasi Belum Lengkap', tone: 'warning'});
        return;
      }
      if (preparedCards.length > maxVillages) {
        event.preventDefault();
        alertUser('Maksimal ' + maxVillages + ' desa dalam satu kali registrasi.', {title: 'Terlalu Banyak Desa', tone: 'warning'});
        return;
      }
      var participantLimitExceeded = Array.prototype.some.call(preparedCards, function (card) {
        return card.querySelectorAll('[data-participant-row]').length > maxParticipants;
      });
      if (participantLimitExceeded) {
        event.preventDefault();
        alertUser('Maksimal ' + maxParticipants + ' peserta untuk setiap desa.', {title: 'Batas Peserta', tone: 'warning'});
        return;
      }
      var duplicateParticipant = null;
      Array.prototype.some.call(preparedCards, function (card) {
        duplicateParticipant = validateUniqueParticipantNames(card, []);
        return !!duplicateParticipant;
      });
      if (duplicateParticipant) {
        event.preventDefault();
        alertUser(duplicateParticipant.message, {title: 'Nama Peserta Ganda', tone: 'warning'});
        duplicateParticipant.field.focus();
        return;
      }
      syncPositionPickers(form);
      updateAllVillageBilling();
      refreshMoney(form);
      if (!form.checkValidity()) {
        event.preventDefault();
        form.reportValidity();
        return;
      }
      if (form.dataset.submitting === '1') {
        event.preventDefault();
        return;
      }
      form.dataset.submitting = '1';
      if (submitButton) {
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fa fa-spinner fa-spin me-2"></i>Menyimpan...';
      }
    });

    if (restoreRows.length) renderRestoredCards();
    if (eventSelect.value) loadDistricts(true);
  }

  var paymentForm = document.getElementById('payment-form');
  if (paymentForm) {
    var paymentMethod = document.getElementById('payment-method');
    var paymentProof = document.getElementById('payment-proof');
    var requiredLabel = document.getElementById('proof-required-label');
    var paymentParticipant = document.getElementById('payment-participant');
    var paymentAmount = document.getElementById('payment-amount');
    var remainingLabel = document.getElementById('payment-remaining-label');

    function updateStandaloneProofRequirement() {
      var isRequired = paymentMethod.value !== 'cash';
      paymentProof.required = isRequired;
      requiredLabel.textContent = isRequired ? '*' : '';
    }

    function updateStandaloneRemainingAmount() {
      var remaining;
      if (paymentForm.dataset.mode === 'per_participant') {
        remaining = paymentParticipant && paymentParticipant.selectedOptions[0]
          ? paymentParticipant.selectedOptions[0].dataset.remaining
          : 0;
      } else {
        remaining = document.getElementById('village-payment-remaining').value;
      }

      setMoneyControl(paymentAmount, {max: remaining || ''});
      refreshMoney(paymentAmount);
      remainingLabel.textContent = remaining
        ? 'Maksimal sisa tagihan: ' + formatCurrency(remaining)
        : '';
    }

    paymentMethod.addEventListener('change', updateStandaloneProofRequirement);
    if (paymentParticipant) paymentParticipant.addEventListener('change', updateStandaloneRemainingAmount);
    updateStandaloneProofRequirement();
    updateStandaloneRemainingAmount();
  }

  /* Compact registration and payment modals used on the list/detail pages. */
  function modalJson(value, fallback) { return parseJson(value, fallback); }
  function modalOpen(id) {
    var opener = document.querySelector('[data-menu="' + id + '"]');
    if (opener) opener.click();
  }
  function modalClose(button) { if (button) button.click(); }
  function modalAlert(modalId, message, options) {
    var modal = document.getElementById(modalId);
    var shouldReopen = !!(modal && modal.classList.contains('menu-active'));
    if (typeof window.simpAlert !== 'function') return Promise.resolve();
    return Promise.resolve(window.simpAlert(message, options || {})).then(function () {
      // AppKit menutup menu aktif ketika dialog dibuka. Kembalikan form yang
      // sebelumnya aktif tanpa mereset nilai yang sudah dimasukkan pengguna.
      if (shouldReopen) modalOpen(modalId);
    });
  }

  var attendanceDateForm = document.getElementById('registration-attendance-date-form');
  document.addEventListener('click', function (event) {
    var attendanceTrigger = event.target.closest('[data-registration-attendance-open]');
    if (!attendanceTrigger) return;
    event.preventDefault();
    modalOpen('registration-attendance-date-modal');
  });
  if (attendanceDateForm) {
    attendanceDateForm.addEventListener('submit', function (event) {
      event.preventDefault();
      var dateField = document.getElementById('registration-attendance-date');
      var dateValue = dateField ? String(dateField.value || '').trim() : '';
      if (!/^\d{4}-\d{2}-\d{2}$/.test(dateValue)) {
        modalAlert('registration-attendance-date-modal', 'Pilih tanggal absen terlebih dahulu.', {
          title: 'Tanggal Belum Dipilih',
          tone: 'warning'
        });
        return;
      }

      function urlWithAttendanceDate(source) {
        try {
          var parsed = new URL(source, window.location.href);
          parsed.searchParams.set('attendance_date', dateValue);
          return parsed.toString();
        } catch (error) {
          return source + (source.indexOf('?') === -1 ? '?' : '&') + 'attendance_date=' + encodeURIComponent(dateValue);
        }
      }

      var previewUrl = urlWithAttendanceDate(attendanceDateForm.getAttribute('data-preview-url') || '');
      var pdfUrl = urlWithAttendanceDate(attendanceDateForm.getAttribute('data-pdf-url') || '');
      var printModal = document.getElementById('registration-attendance-print-modal');
      var previewTrigger = document.getElementById('registration-attendance-preview-trigger');
      if (!printModal || !previewTrigger) return;

      var frame = printModal.querySelector('[data-report-preview-frame]');
      var pdfLink = printModal.querySelector('[data-report-file-download][data-report-file-label="PDF"]');
      var shareButton = printModal.querySelector('[data-report-share-pdf]');
      var title = printModal.querySelector('#registration-attendance-print-modal-title');
      var dateParts = dateValue.split('-');
      var monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
      var dateLabel = Number(dateParts[2]) + ' ' + (monthNames[Number(dateParts[1]) - 1] || '') + ' ' + dateParts[0];

      if (frame) frame.setAttribute('data-src', previewUrl);
      if (pdfLink) pdfLink.setAttribute('href', pdfUrl);
      if (shareButton) {
        shareButton.setAttribute('data-report-pdf-url', pdfUrl);
        shareButton.setAttribute('data-report-share-title', 'Absen ' + dateLabel);
      }
      if (title) title.textContent = 'Cetak Absen · ' + dateLabel;
      previewTrigger.setAttribute('href', previewUrl);

      var closeButton = document.querySelector('#registration-attendance-date-modal .close-menu');
      modalClose(closeButton);
      window.setTimeout(function () { previewTrigger.click(); }, 220);
    });
  }

  function refreshCsrfFromPayload(payload) {
    if (!payload || !payload.csrf || !payload.csrf.hash) return;
    if (window.SIMP) {
      window.SIMP.csrfName = payload.csrf.name || window.SIMP.csrfName;
      window.SIMP.csrfHash = payload.csrf.hash;
    }
    var name = payload.csrf.name || (window.SIMP && window.SIMP.csrfName);
    if (name) document.querySelectorAll('input[name="' + name + '"]').forEach(function (input) { input.value = payload.csrf.hash; input.defaultValue = payload.csrf.hash; });
  }
  function restoreCurrentCsrf(form) {
    if (!form || !window.SIMP || !window.SIMP.csrfName || !window.SIMP.csrfHash) return;
    var input = form.querySelector('input[name="' + window.SIMP.csrfName + '"]');
    if (input) { input.value = window.SIMP.csrfHash; input.defaultValue = window.SIMP.csrfHash; }
  }
  function postModalForm(form) {
    var headers = {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'};
    if (window.SIMP && window.SIMP.csrfHash) headers['X-CSRF-TOKEN'] = window.SIMP.csrfHash;
    return fetch(form.action, {method: 'POST', credentials: 'same-origin', headers: headers, body: new FormData(form)}).then(function (response) {
      return response.text().then(function (body) {
        var contentType = String(response.headers.get('content-type') || '').toLowerCase();
        var payload = null;
        if (contentType.indexOf('application/json') !== -1 || /^\s*[\[{]/.test(body)) {
          try { payload = JSON.parse(body); } catch (ignored) { payload = null; }
        }
        if (payload) {
          refreshCsrfFromPayload(payload);
          if (!response.ok || payload.success === false) throw new Error(payload.message || 'Permintaan gagal.');
          return payload;
        }

        var responseUrl = String(response.url || '');
        if (response.redirected || /\/login(?:[/?#]|$)/i.test(responseUrl) || response.status === 401) {
          throw new Error('Sesi Anda telah berakhir. Silakan masuk kembali, lalu ulangi penyimpanan.');
        }
        if (response.status === 403 || response.status === 419) {
          throw new Error('Sesi keamanan form sudah tidak berlaku. Muat ulang halaman, lalu coba kembali.');
        }
        if (response.status >= 500) throw new Error('Permintaan belum dapat diproses karena terjadi gangguan server. Silakan coba kembali.');
        if (!response.ok) throw new Error('Permintaan tidak dapat diproses oleh server. Silakan coba kembali.');
        throw new Error('Respons server tidak dapat dibaca. Muat ulang halaman, lalu coba kembali.');
      }, function () {
        throw new Error('Respons server tidak dapat dibaca. Muat ulang halaman, lalu coba kembali.');
      });
    }, function () {
      throw new Error('Koneksi ke server terputus. Periksa jaringan lalu coba kembali.');
    });
  }
  function postRegistrationAction(url) {
    var form = document.createElement('form');
    form.method = 'post';
    form.action = url;
    if (window.SIMP && window.SIMP.csrfName) {
      var csrf = document.createElement('input');
      csrf.type = 'hidden';
      csrf.name = window.SIMP.csrfName;
      csrf.value = window.SIMP.csrfHash || '';
      form.appendChild(csrf);
    }
    return postModalForm(form);
  }
  function refreshRegistrationFragment(id, targetUrl) {
    return fetch(targetUrl || window.location.href, {
      credentials: 'same-origin',
      cache: 'no-store',
      headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html'}
    }).then(function (response) {
      var responseUrl = String(response.url || '');
      if (response.redirected || response.status === 401 || /\/login(?:[/?#]|$)/i.test(responseUrl)) {
        throw new Error('Sesi Anda telah berakhir. Silakan masuk kembali.');
      }
      if (!response.ok) throw new Error('Tampilan registrasi gagal diperbarui.');
      return response.text();
    }, function () {
      throw new Error('Koneksi ke server terputus. Tampilan registrasi belum dapat diperbarui.');
    }).then(function (html) {
      var parsed = new DOMParser().parseFromString(html, 'text/html');
      var current = document.getElementById(id);
      var next = parsed.getElementById(id);
      if (!current || !next) throw new Error('Tampilan registrasi gagal diperbarui.');
      current.replaceWith(document.importNode(next, true));
      /* Modal forms live outside the refreshed content fragment.  Refresh the
       * billing snapshots they use for the next one-click add/edit without
       * replacing their event listeners or clearing an open modal. */
      ['registration-participant-form', 'registration-inline-payment-config'].forEach(function (elementId) {
        var currentElement = document.getElementById(elementId);
        var nextElement = parsed.getElementById(elementId);
        if (!currentElement || !nextElement) return;
        Object.keys(nextElement.dataset || {}).forEach(function (key) { currentElement.dataset[key] = nextElement.dataset[key]; });
      });
    });
  }

  /* The index is deliberately paginated on the server, but moving between
   * pages only replaces its content fragment so open modals and the AppKit
   * shell stay intact. */
  var registrationPageRequest = 0;
  var registrationPageController = null;
  function refreshRegistrationPage(targetUrl) {
    var requestId = ++registrationPageRequest;
    if (registrationPageController && typeof registrationPageController.abort === 'function') registrationPageController.abort();
    registrationPageController = typeof AbortController === 'function' ? new AbortController() : null;
    var options = {
      credentials: 'same-origin',
      cache: 'no-store',
      headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html'}
    };
    if (registrationPageController) options.signal = registrationPageController.signal;
    return fetch(targetUrl, options).then(function (response) {
      var responseUrl = String(response.url || '');
      if (response.redirected || response.status === 401 || /\/login(?:[/?#]|$)/i.test(responseUrl)) throw new Error('Sesi Anda telah berakhir. Silakan masuk kembali.');
      if (!response.ok) throw new Error('Data registrasi gagal diperbarui.');
      return response.text();
    }).then(function (html) {
      if (requestId !== registrationPageRequest) return;
      var parsed = new DOMParser().parseFromString(html, 'text/html');
      var current = document.getElementById('registration-index-content');
      var next = parsed.getElementById('registration-index-content');
      if (!current || !next) throw new Error('Potongan data registrasi tidak lengkap.');
      current.replaceWith(document.importNode(next, true));
      if (window.history && window.history.replaceState) window.history.replaceState({}, '', targetUrl);
    }).catch(function (error) {
      if (error && error.name === 'AbortError') return;
      throw error;
    });
  }

  document.addEventListener('click', function (event) {
    var pageLink = event.target.closest('[data-registration-page-link]');
    if (!pageLink) return;
    event.preventDefault();
    refreshRegistrationPage(pageLink.href).catch(function (error) {
      if (typeof window.simpAlert === 'function') {
        window.simpAlert(error.message || 'Data registrasi gagal diperbarui.', {
          title: 'Paginasi Gagal',
          tone: 'danger'
        });
      }
    });
  });

  function registrationDeleteAlert(message, title, tone) {
    if (typeof window.simpAlert === 'function') return window.simpAlert(message, {title: title, tone: tone});
    return Promise.resolve();
  }

  function deleteRegistration(button) {
    if (!button || button.dataset.registrationDeleting === '1') return;
    var url = String(button.getAttribute('data-registration-delete-url') || '').trim();
    var label = String(button.getAttribute('data-registration-delete-label') || 'registrasi ini').trim();
    var participantCount = Math.max(0, parseInt(button.getAttribute('data-registration-delete-participants'), 10) || 0);
    if (!url) {
      registrationDeleteAlert('Alamat penghapusan registrasi tidak tersedia.', 'Hapus Gagal', 'danger');
      return;
    }
    if (label.length > 140) label = label.slice(0, 137) + '...';
    if (typeof window.simpConfirm !== 'function') {
      registrationDeleteAlert('Konfirmasi MVIN belum siap. Muat ulang halaman lalu coba kembali.', 'Hapus Gagal', 'danger');
      return;
    }

    window.simpConfirm(
      'Hapus registrasi ' + label + ' beserta ' + participantCount + ' peserta, seluruh pembayaran, riwayat, dan bukti terkait? Jurnal pembayaran terverifikasi juga akan dibatalkan. Tindakan ini tidak dapat dipulihkan.',
      {title: 'Hapus Registrasi?', confirmLabel: 'Ya, Hapus', tone: 'danger'}
    ).then(function (confirmed) {
      if (!confirmed) return;
      var originalText = button.innerHTML;
      button.dataset.registrationDeleting = '1';
      button.disabled = true;
      button.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> Menghapus...';

      postRegistrationAction(url).then(function (payload) {
        return refreshRegistrationPage(window.location.href).then(function () {
          return registrationDeleteAlert(payload.message, 'Berhasil', 'success');
        }, function () {
          return registrationDeleteAlert(
            payload.message + ' Namun daftar belum dapat diperbarui. Muat ulang halaman untuk melihat data terbaru.',
            'Data Sudah Dihapus',
            'warning'
          );
        });
      }).catch(function (error) {
        return registrationDeleteAlert(error.message || 'Registrasi gagal dihapus.', 'Hapus Registrasi Gagal', 'danger');
      }).then(function () {
        delete button.dataset.registrationDeleting;
        button.disabled = false;
        button.innerHTML = originalText;
      });
    });
  }

  document.addEventListener('click', function (event) {
    var deleteButton = event.target.closest('[data-registration-delete-open]');
    if (!deleteButton) return;
    event.preventDefault();
    deleteRegistration(deleteButton);
  });

  function deleteParticipant(button) {
    if (!button || button.dataset.participantDeleting === '1') return;
    var url = String(button.getAttribute('data-participant-delete-url') || '').trim();
    var name = String(button.getAttribute('data-participant-name') || 'peserta ini').trim();
    if (!url) {
      registrationDeleteAlert('Alamat penghapusan peserta tidak tersedia.', 'Hapus Gagal', 'danger');
      return;
    }
    if (name.length > 140) name = name.slice(0, 137) + '...';
    if (typeof window.simpConfirm !== 'function') {
      registrationDeleteAlert('Konfirmasi MVIN belum siap. Muat ulang halaman lalu coba kembali.', 'Hapus Gagal', 'danger');
      return;
    }

    window.simpConfirm(
      'Hapus peserta ' + name + ' beserta seluruh pembayaran, riwayat, dan bukti terkait? Jurnal pembayaran terverifikasi juga akan dibatalkan. Tindakan ini tidak dapat dipulihkan.',
      {title: 'Hapus Peserta?', confirmLabel: 'Ya, Hapus', tone: 'danger'}
    ).then(function (confirmed) {
      if (!confirmed) return;
      var originalText = button.innerHTML;
      button.dataset.participantDeleting = '1';
      button.disabled = true;
      button.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i>Menghapus...';

      postRegistrationAction(url).then(function (payload) {
        return finishRegistrationMutation(payload, 'registration-detail-content', 'Peserta Dihapus');
      }).catch(function (error) {
        return registrationDeleteAlert(error.message || 'Peserta gagal dihapus.', 'Hapus Peserta Gagal', 'danger');
      }).then(function () {
        if (!document.contains(button)) return;
        delete button.dataset.participantDeleting;
        button.disabled = false;
        button.innerHTML = originalText;
      });
    });
  }

  document.addEventListener('click', function (event) {
    var deleteButton = event.target.closest('[data-participant-delete-open]');
    if (!deleteButton) return;
    event.preventDefault();
    deleteParticipant(deleteButton);
  });

  function finishRegistrationMutation(payload, fragmentId, successTitle) {
    return refreshRegistrationFragment(fragmentId).then(function () {
      if (typeof window.simpAlert === 'function') {
        return window.simpAlert(payload.message, {title: successTitle || 'Berhasil', tone: 'success'});
      }
    }).catch(function () {
      if (typeof window.simpAlert === 'function') {
        return window.simpAlert(payload.message + ' Tampilan belum dapat diperbarui; muat ulang halaman untuk melihat data terbaru.', {
          title: 'Data Sudah Tersimpan',
          tone: 'warning'
        });
      }
    });
  }
  /* Kept outside the row builders so the same searchable picker is used by
   * both the index modal and the detail modal. */
  function modalInput(label, id, name, type, required, placeholder) {
    var maxLength = type === 'tel' ? 30 : 160;
    return '<div class="input-style has-borders no-icon input-style-always-active mb-3"><input class="form-control" type="' + type + '" id="' + id + '" name="' + name + '" ' + (required ? 'required ' : '') + 'maxlength="' + maxLength + '" placeholder="' + (placeholder || '') + '"><label for="' + id + '" class="color-highlight">' + label + '</label><i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em>' + (required ? '*' : '') + '</em></div>';
  }

  /* Reusable payment fields for the compact registration modals.  The full
   * registration page has its own richer builder; these fields intentionally
   * use the same `payments[village][target]` contract so both paths share the
   * controller/model validation and ledger transaction. */
  var inlinePaymentCounter = 0;
  function inlinePaymentToken() {
    inlinePaymentCounter += 1;
    return 'modalpay' + Date.now().toString(36) + inlinePaymentCounter;
  }
  function inlinePaymentAccounts(form) {
    var value = form && form.dataset ? form.dataset.accounts : '';
    if (!value) {
      var config = document.getElementById('registration-inline-payment-config');
      value = config && config.dataset ? config.dataset.accounts : '';
    }
    return modalJson(value, []);
  }
  function inlineAccountOptions(accounts) {
    return '<option value="">Pilih akun dana</option>' + (Array.isArray(accounts) ? accounts : []).map(function (account) {
      return '<option value="' + esc(account.id) + '" data-type="' + esc(account.type || '') + '">' + esc(account.name || '') + '</option>';
    }).join('');
  }
  function inlinePaymentMarkup(villageId, targetKey, targetAmount, targetLabel, accounts) {
    var token = inlinePaymentToken();
    var prefix = 'payments[' + esc(villageId) + '][' + esc(targetKey) + ']';
    var id = function (name) { return 'modal-payment-' + name + '-' + token; };
    var amount = canonicalMoney(targetAmount);
    return [
      '<div class="rounded-s bg-gray-light p-3 mt-3" data-inline-payment-block data-payment-token="', esc(token), '" data-target-amount="', esc(amount), '">',
        '<div class="d-flex align-items-start"><span class="icon icon-s rounded-xl bg-green-light color-green-dark me-3 flex-shrink-0"><i class="fa fa-money-bill-wave"></i></span><div class="min-width-zero"><h5 class="font-13 mb-n1">Pembayaran awal</h5><p class="font-10 opacity-60 mb-0">', esc(targetLabel || 'Target pembayaran'), ' · sisa <span data-inline-payment-maximum>', formatCurrency(amount), '</span></p></div></div>',
        '<div class="form-check icon-check mt-3 mb-1"><input class="form-check-input" type="checkbox" name="', prefix, '[enabled]" value="1" id="', id('enabled'), '" data-inline-payment-toggle><label class="form-check-label font-12 font-600" for="', id('enabled'), '">Catat pembayaran sekarang</label><i class="icon-check-1 far fa-square color-gray-dark font-16"></i><i class="icon-check-2 far fa-check-square color-highlight font-16"></i></div>',
        '<div class="mt-3 d-none" data-inline-payment-fields>',
          '<input type="hidden" name="', prefix, '[token]" value="', esc(token), '">',
          '<div class="row mb-0">',
            '<div class="col-12 col-md-6"><div class="input-style has-borders no-icon input-style-always-active mb-3"><input type="date" class="form-control" name="', prefix, '[payment_date]" value="', todayValue(), '" required data-inline-payment-required data-inline-payment-input><label class="color-highlight">Tanggal</label><em>*</em></div></div>',
            '<div class="col-12 col-md-6"><div class="input-style has-borders no-icon input-style-always-active mb-3"><label class="color-highlight">Metode</label><select name="', prefix, '[method]" required data-inline-payment-method data-inline-payment-required data-inline-payment-input><option value="cash">Tunai</option><option value="transfer">Transfer</option><option value="qris">QRIS</option></select><span><i class="fa fa-chevron-down"></i></span><em>*</em></div></div>',
            '<div class="col-12 col-md-6"><div class="input-style has-borders no-icon input-style-always-active mb-3"><label class="color-highlight">Akun penerima</label><select name="', prefix, '[account_id]" required data-inline-payment-account data-inline-payment-required data-inline-payment-input>', inlineAccountOptions(accounts), '</select><span><i class="fa fa-chevron-down"></i></span><em>*</em></div></div>',
            '<div class="col-12 col-md-6"><div class="input-style has-borders no-icon input-style-always-active mb-1"><input type="number" data-money min="1" step="1" class="form-control" name="', prefix, '[amount]" placeholder="Rp 0" required data-inline-payment-amount data-inline-payment-input><label class="color-highlight">Nominal</label><em>*</em></div><div class="text-end mb-3"><button type="button" class="btn btn-xxs border-green-dark color-green-dark rounded-s font-600" data-inline-payment-fill><i class="fa fa-check-circle me-1"></i>Isi Lunas</button></div></div>',
            '<div class="col-12"><div class="input-style has-borders no-icon input-style-always-active mb-1"><input type="file" class="form-control" name="payment_proof_', esc(token), '" accept="image/jpeg,image/png,application/pdf" style="padding-top:13px;" data-inline-payment-proof data-inline-payment-input><label class="color-highlight">Bukti pembayaran</label><em data-inline-proof-label></em></div><p class="font-10 opacity-60 mb-3">Bukti diperlukan untuk transfer atau QRIS.</p></div>',
            '<div class="col-12"><div class="input-style has-borders no-icon input-style-always-active mb-1"><textarea class="form-control" name="', prefix, '[note]" rows="2" maxlength="2000" placeholder="Catatan pembayaran" data-inline-payment-input></textarea><label class="color-highlight">Catatan pembayaran</label><em></em></div></div>',
          '</div>',
        '</div>',
      '</div>'
    ].join('');
  }
  function inlinePaymentCompatible(method, type) {
    if (method === 'cash') return type === 'cash';
    if (method === 'transfer') return type === 'bank' || type === 'personal';
    if (method === 'qris') return type === 'qris';
    return false;
  }
  function inlinePaymentFilterAccounts(block) {
    var method = block.querySelector('[data-inline-payment-method]');
    var account = block.querySelector('[data-inline-payment-account]');
    if (!method || !account) return;
    var value = account.value;
    Array.prototype.forEach.call(account.options, function (option) {
      if (!option.value) { option.hidden = false; option.disabled = false; return; }
      var compatible = inlinePaymentCompatible(method.value, option.dataset.type || '');
      option.hidden = !compatible;
      option.disabled = !compatible;
    });
    if (value && account.querySelector('option[value="' + value.replace(/"/g, '\\"') + '"]') && !inlinePaymentCompatible(method.value, account.querySelector('option[value="' + value.replace(/"/g, '\\"') + '"]').dataset.type || '')) account.value = '';
  }
  function inlinePaymentSync(block, fillAmount) {
    var toggle = block.querySelector('[data-inline-payment-toggle]');
    var fields = block.querySelector('[data-inline-payment-fields]');
    var amount = block.querySelector('[data-inline-payment-amount]');
    if (!toggle || !fields) return;
    var enabled = !!toggle.checked;
    var target = canonicalMoney(block.dataset.targetAmount || '0');
    if (enabled && moneyCents(target) <= 0n) { toggle.checked = false; enabled = false; }
    fields.classList.toggle('d-none', !enabled);
    Array.prototype.forEach.call(fields.querySelectorAll('[data-inline-payment-input]'), function (input) {
      input.disabled = !enabled;
      input.required = enabled && input.hasAttribute('data-inline-payment-required');
      if (input === amount) input.required = enabled;
    });
    inlinePaymentFilterAccounts(block);
    var method = block.querySelector('[data-inline-payment-method]');
    var proof = block.querySelector('[data-inline-payment-proof]');
    var proofLabel = block.querySelector('[data-inline-proof-label]');
    if (proof) { proof.required = enabled && method && method.value !== 'cash'; if (proofLabel) proofLabel.textContent = proof.required ? '*' : ''; }
    if (amount) {
      var old = canonicalMoney(block.dataset.targetAmount || '0');
      var current = moneyValue(amount);
      var full = current !== '' && compareMoney(current, old) === 0;
      setMoneyControl(amount, {max: target, disabled: !enabled, required: enabled});
      if (fillAmount && enabled && (current === '' || full)) setMoney(amount, moneyCents(target) > 0n ? target : '');
      if (current !== '' && compareMoney(current, target) > 0) setMoneyValidity(amount, 'Nominal melebihi sisa tagihan.'); else setMoneyValidity(amount, '');
    }
    var maximum = block.querySelector('[data-inline-payment-maximum]');
    if (maximum) maximum.textContent = formatCurrency(target);
  }
  function inlinePaymentSetTarget(block, targetAmount, fillAmount) {
    if (!block) return;
    block.dataset.targetAmount = canonicalMoney(targetAmount);
    inlinePaymentSync(block, !!fillAmount);
  }
  function initializeInlinePayments(scope) {
    if (!scope) return;
    if (window.SimpMoney && typeof window.SimpMoney.init === 'function') window.SimpMoney.init(scope);
    Array.prototype.forEach.call(scope.querySelectorAll('[data-inline-payment-block]'), function (block) { inlinePaymentSync(block, false); });
  }
  document.addEventListener('change', function (event) {
    var block = event.target.closest && event.target.closest('[data-inline-payment-block]');
    if (!block) return;
    if (event.target.matches('[data-inline-payment-method]')) inlinePaymentFilterAccounts(block);
    if (event.target.matches('[data-inline-payment-toggle], [data-inline-payment-method]')) inlinePaymentSync(block, event.target.matches('[data-inline-payment-toggle]'));
  });
  document.addEventListener('click', function (event) {
    var fill = event.target.closest && event.target.closest('[data-inline-payment-fill]');
    if (!fill) return;
    var block = fill.closest('[data-inline-payment-block]');
    if (block) { var toggle = block.querySelector('[data-inline-payment-toggle]'); if (toggle) toggle.checked = true; inlinePaymentSync(block, true); }
  });

  var modalParticipantLimit = 20;

  var registrationAddForm = document.getElementById('registration-add-form');
  if (registrationAddForm) {
    var addEvents = modalJson(registrationAddForm.dataset.events, []);
    var addPositions = modalJson(registrationAddForm.dataset.positions, []);
    var addAccounts = modalJson(registrationAddForm.dataset.accounts, []);
    var addCanRecordPayment = registrationAddForm.dataset.canRecordPayment === '1';
    var addEvent = document.getElementById('registration-add-event');
    var addDistrict = document.getElementById('registration-add-district');
    var addVillage = document.getElementById('registration-add-village');
    var addParticipants = document.getElementById('registration-add-participants');
    var addVillagePayment = document.getElementById('registration-add-village-payment');
    var addRowCounter = 0;
    var addDistrictRequest = 0;
    var addVillageRequest = 0;
    function selectedAddEvent() { return addEvents.filter(function (event) { return String(event.id) === String(addEvent && addEvent.value); })[0] || null; }
    function addBillingMode() { var event = selectedAddEvent(); return event && event.billing_mode ? String(event.billing_mode) : ''; }
    function addExpectedAmount(count) {
      var event = selectedAddEvent();
      if (!event) return '0.00';
      var village = canonicalMoney(event.village_fee), participant = canonicalMoney(event.participant_fee), included = Math.max(0, parseInt(event.included_participant_count, 10) || 0), n = Math.max(0, parseInt(count, 10) || 0);
      if (event.billing_mode === 'per_participant') return multiplyMoney(participant, n);
      if (event.billing_mode === 'per_village_extra') return addMoney(village, multiplyMoney(participant, Math.max(0, n - included)));
      return village;
    }
    function refreshAddPaymentBlocks() {
      var event = selectedAddEvent(), villageId = addVillage && addVillage.value ? String(addVillage.value) : '', mode = event && event.billing_mode ? String(event.billing_mode) : '';
      Array.prototype.forEach.call(addParticipants.querySelectorAll('[data-add-payment-slot]'), function (slot) {
        var row = slot.closest('[data-add-participant-row]'), key = row && row.dataset.addParticipantRow;
        if (!addCanRecordPayment || mode !== 'per_participant' || !villageId || !key || !event || moneyCents(canonicalMoney(event.participant_fee || '0')) <= 0n) { slot.innerHTML = ''; return; }
        if (slot.dataset.paymentVillage === villageId && slot.dataset.paymentKey === key && slot.querySelector('[data-inline-payment-block]')) return;
        slot.innerHTML = inlinePaymentMarkup(villageId, key, canonicalMoney(event.participant_fee), 'Pembayaran peserta', addAccounts);
        slot.dataset.paymentVillage = villageId; slot.dataset.paymentKey = key;
        initializeInlinePayments(slot);
      });
      if (!addVillagePayment) return;
      var villageAmount = event ? addExpectedAmount(addParticipants.children.length) : '0.00';
      if (!addCanRecordPayment || mode === 'per_participant' || !villageId || moneyCents(villageAmount) <= 0n) { addVillagePayment.innerHTML = ''; return; }
      var block = addVillagePayment.querySelector('[data-inline-payment-block]');
      if (!block || addVillagePayment.dataset.paymentVillage !== villageId) {
        addVillagePayment.innerHTML = inlinePaymentMarkup(villageId, 'village', villageAmount, 'Pembayaran tingkat desa', addAccounts);
        addVillagePayment.dataset.paymentVillage = villageId;
        initializeInlinePayments(addVillagePayment);
      } else inlinePaymentSetTarget(block, villageAmount, false);
    }
    function addParticipantRow() {
      if (addParticipants.children.length >= modalParticipantLimit) {
        modalAlert('registration-add-modal', 'Maksimal ' + modalParticipantLimit + ' peserta dapat ditambahkan sekaligus.', {title: 'Batas Peserta', tone: 'warning'});
        return false;
      }
      var key = 'p' + (++addRowCounter), prefix = 'participants[' + key + ']';
      var row = document.createElement('div');
      row.className = 'card bg-theme border rounded-s shadow-0 mb-3';
      row.dataset.addParticipantRow = key;
      row.innerHTML = '<div class="content my-3"><div class="d-flex align-items-center mb-2"><strong class="font-13">Peserta ' + addRowCounter + '</strong><button type="button" class="btn btn-xxs border-red-dark color-red-dark rounded-s ms-auto" data-remove-modal-participant><i class="fa fa-times"></i></button></div><div class="row mb-0"><div class="col-12">' + modalInput('Nama lengkap', 'registration-add-name-' + key, prefix + '[full_name]', 'text', true, 'Nama lengkap peserta') + '</div><div class="col-12">' + positionPickerMarkup(addPositions, '', 'registration-add-position-' + key, prefix + '[position_id]') + '</div><div class="col-12">' + modalInput('No. HP', 'registration-add-phone-' + key, prefix + '[phone]', 'tel', false, '08xxxxxxxxxx') + '</div></div><div data-add-payment-slot></div></div>';
      addParticipants.appendChild(row);
      if (addParticipants.children.length === 1) row.querySelector('[data-remove-modal-participant]').classList.add('d-none');
      refreshAddPaymentBlocks();
      return true;
    }
    function resetAddParticipants() { addParticipants.innerHTML = ''; addRowCounter = 0; if (addVillagePayment) { addVillagePayment.innerHTML = ''; addVillagePayment.dataset.paymentVillage = ''; } addParticipantRow(); }
    function loadAddDistricts() {
      var event = selectedAddEvent(), token = ++addDistrictRequest; addVillageRequest++;
      addDistrict.innerHTML = '<option value="">Memuat kecamatan...</option>'; addDistrict.disabled = true; addVillage.innerHTML = '<option value="">Pilih kecamatan dahulu</option>'; addVillage.disabled = true;
      if (!event || !Array.isArray(event.regencies) || !event.regencies.length) { addDistrict.innerHTML = '<option value="">Wilayah event belum diatur</option>'; return; }
      var query = event.regencies.map(function (region) { return 'regency_ids[]=' + encodeURIComponent(region.regency_id); }).join('&');
      requestJson(registrationAddForm.dataset.districtsUrl + '?' + query).then(function (payload) {
        if (token !== addDistrictRequest) return;
        addDistrict.innerHTML = '<option value="">Pilih kecamatan</option>' + (payload.data || []).map(function (district) { return '<option value="' + esc(district.id) + '">' + esc(district.regency_name + ' · ' + district.name) + '</option>'; }).join(''); addDistrict.disabled = false;
      }).catch(function (error) { if (token !== addDistrictRequest) return; addDistrict.innerHTML = '<option value="">Gagal memuat</option>'; return modalAlert('registration-add-modal', error.message, {title: 'Kecamatan Gagal Dimuat', tone: 'danger'}); });
    }
    function loadAddVillages() {
      var districtId = addDistrict.value, token = ++addVillageRequest; addVillage.disabled = true; addVillage.innerHTML = '<option value="">Memuat desa...</option>'; if (!districtId) { addVillage.innerHTML = '<option value="">Pilih kecamatan dahulu</option>'; return; }
      requestJson(registrationAddForm.dataset.villagesUrl + '?district_ids[]=' + encodeURIComponent(districtId)).then(function (payload) { if (token !== addVillageRequest) return; addVillage.innerHTML = '<option value="">Pilih desa</option>' + (payload.data || []).map(function (village) { return '<option value="' + esc(village.id) + '">' + esc(village.name) + '</option>'; }).join(''); addVillage.disabled = false; }).catch(function (error) { if (token !== addVillageRequest) return; addVillage.innerHTML = '<option value="">Gagal memuat</option>'; return modalAlert('registration-add-modal', error.message, {title: 'Desa Gagal Dimuat', tone: 'danger'}); });
    }
    addDistrict.addEventListener('change', loadAddVillages);
    addVillage.addEventListener('change', refreshAddPaymentBlocks);
    if (addEvent && !addEvent.type.match(/^hidden$/i)) addEvent.addEventListener('change', function () { resetAddParticipants(); loadAddDistricts(); });
    document.addEventListener('click', function (event) {
      if (event.target.closest('[data-registration-add-open]')) { event.preventDefault(); resetAddParticipants(); loadAddDistricts(); modalOpen('registration-add-modal'); }
      var addButton = event.target.closest('[data-add-modal-participant]'); if (addButton && addButton.closest('#registration-add-form')) { event.preventDefault(); addParticipantRow(); }
      var removeButton = event.target.closest('[data-remove-modal-participant]'); if (removeButton && removeButton.closest('#registration-add-form')) { var row = removeButton.closest('[data-add-participant-row]'); if (addParticipants.children.length > 1) { row.remove(); refreshAddPaymentBlocks(); } }
    });
    registrationAddForm.addEventListener('submit', function (event) {
      event.preventDefault();
      if (!addEvent.value || !addVillage.value || !addParticipants.querySelector('[data-add-participant-row]')) { modalAlert('registration-add-modal', 'Pilih event, kecamatan, desa, dan isi minimal satu peserta.', {title: 'Data Belum Lengkap', tone: 'warning'}); return; }
      if (addParticipants.querySelectorAll('[data-add-participant-row]').length > modalParticipantLimit) { modalAlert('registration-add-modal', 'Maksimal ' + modalParticipantLimit + ' peserta dapat ditambahkan sekaligus.', {title: 'Batas Peserta', tone: 'warning'}); return; }
      var duplicateParticipant = validateUniqueParticipantNames(addParticipants, []);
      if (duplicateParticipant) { modalAlert('registration-add-modal', duplicateParticipant.message, {title: 'Nama Peserta Ganda', tone: 'warning'}); return; }
      syncPositionPickers(registrationAddForm);
      refreshAddPaymentBlocks();
      refreshMoney(registrationAddForm);
      if (!registrationAddForm.checkValidity()) { registrationAddForm.reportValidity(); return; }
      var submit = registrationAddForm.querySelector('[data-registration-add-submit]'); if (submit) { submit.disabled = true; submit.dataset.originalText = submit.innerHTML; submit.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i>Menyimpan...'; }
      postModalForm(registrationAddForm).then(function (payload) {
        modalClose(document.querySelector('#registration-add-modal .close-menu'));
        registrationAddForm.reset();
        restoreCurrentCsrf(registrationAddForm);
        return finishRegistrationMutation(payload, 'registration-index-content');
      }, function (error) {
        return modalAlert('registration-add-modal', error.message, {title: 'Registrasi Gagal', tone: 'danger'});
      }).then(function () {
        if (submit) { submit.disabled = false; submit.innerHTML = submit.dataset.originalText || '<i class="fa fa-save me-1"></i> Simpan Registrasi'; }
      });
    });
  }

  var participantForm = document.getElementById('registration-participant-form');
  if (participantForm) {
    var detailPositions = modalJson(participantForm.dataset.positions, []), detailRows = document.getElementById('registration-participant-rows'), detailCounter = 0;
    var detailAccounts = modalJson(participantForm.dataset.accounts, []), detailCanRecordPayment = participantForm.dataset.canRecordPayment === '1';
    var detailVillagePayment = document.getElementById('registration-participant-village-payment');
    function detailBillingMode() { return String(participantForm.dataset.billingMode || 'per_village'); }
    function detailExpectedPayment() {
      var mode = detailBillingMode(), current = Math.max(0, parseInt(participantForm.dataset.currentParticipantCount, 10) || 0), included = Math.max(0, parseInt(participantForm.dataset.includedParticipantCount, 10) || 0), fee = canonicalMoney(participantForm.dataset.participantFee || '0'), rows = detailRows.querySelectorAll('[data-detail-participant-row]').length;
      var baseRemaining = canonicalMoney(participantForm.dataset.villageRemaining || '0');
      if (mode !== 'per_village_extra') return baseRemaining;
      var beforeExtra = Math.max(0, current - included), afterExtra = Math.max(0, current + rows - included);
      return addMoney(baseRemaining, multiplyMoney(fee, afterExtra - beforeExtra));
    }
    function refreshDetailPaymentBlocks() {
      var villageId = String(participantForm.dataset.villageId || ''), mode = detailBillingMode();
      Array.prototype.forEach.call(detailRows.querySelectorAll('[data-detail-payment-slot]'), function (slot) {
        var row = slot.closest('[data-detail-participant-row]'), key = row && row.dataset.detailParticipantRow;
        if (!detailCanRecordPayment || mode !== 'per_participant' || !villageId || !key || moneyCents(canonicalMoney(participantForm.dataset.participantFee || '0')) <= 0n) { slot.innerHTML = ''; return; }
        if (slot.dataset.paymentKey === key && slot.querySelector('[data-inline-payment-block]')) return;
        slot.innerHTML = inlinePaymentMarkup(villageId, key, canonicalMoney(participantForm.dataset.participantFee || '0'), 'Pembayaran peserta', detailAccounts);
        slot.dataset.paymentKey = key;
        initializeInlinePayments(slot);
      });
      if (!detailVillagePayment) return;
      var villageAmount = detailExpectedPayment();
      if (!detailCanRecordPayment || mode === 'per_participant' || !villageId || moneyCents(villageAmount) <= 0n) { detailVillagePayment.innerHTML = ''; return; }
      var block = detailVillagePayment.querySelector('[data-inline-payment-block]');
      if (!block) { detailVillagePayment.innerHTML = inlinePaymentMarkup(villageId, 'village', villageAmount, 'Pembayaran tingkat desa', detailAccounts); initializeInlinePayments(detailVillagePayment); }
      else inlinePaymentSetTarget(block, villageAmount, false);
    }
    function addDetailRow() {
      if (detailRows.children.length >= modalParticipantLimit) {
        modalAlert('registration-participant-modal', 'Maksimal ' + modalParticipantLimit + ' peserta dapat ditambahkan sekaligus.', {title: 'Batas Peserta', tone: 'warning'});
        return false;
      }
      var key = 'p' + (++detailCounter), prefix = 'participants[' + key + ']', row = document.createElement('div');
      row.className = 'card bg-theme border rounded-s shadow-0 mb-3';
      row.dataset.detailParticipantRow = key;
      row.innerHTML = '<div class="content my-3"><div class="d-flex align-items-center mb-2"><strong class="font-13">Peserta ' + detailCounter + '</strong><button type="button" class="btn btn-xxs border-red-dark color-red-dark rounded-s ms-auto" data-remove-detail-row><i class="fa fa-times"></i></button></div>' + modalInput('Nama lengkap', 'detail-name-' + key, prefix + '[full_name]', 'text', true, 'Nama lengkap peserta') + positionPickerMarkup(detailPositions, '', 'detail-position-' + key, prefix + '[position_id]') + modalInput('No. HP', 'detail-phone-' + key, prefix + '[phone]', 'tel', false, '08xxxxxxxxxx') + '<div data-detail-payment-slot></div></div>';
      detailRows.appendChild(row);
      if (detailRows.children.length === 1) row.querySelector('[data-remove-detail-row]').classList.add('d-none');
      refreshDetailPaymentBlocks();
      return true;
    }
    addDetailRow();
    document.addEventListener('click', function (event) { if (event.target.closest('[data-registration-participant-open]')) { event.preventDefault(); detailRows.innerHTML = ''; detailCounter = 0; if (detailVillagePayment) detailVillagePayment.innerHTML = ''; addDetailRow(); modalOpen('registration-participant-modal'); } if (event.target.closest('[data-detail-add-row]')) addDetailRow(); var remove = event.target.closest('[data-remove-detail-row]'); if (remove && detailRows.children.length > 1) { remove.closest('[data-detail-participant-row]').remove(); refreshDetailPaymentBlocks(); } });
    participantForm.addEventListener('submit', function (event) {
      event.preventDefault();
      if (detailRows.querySelectorAll('[data-detail-participant-row]').length > modalParticipantLimit) { modalAlert('registration-participant-modal', 'Maksimal ' + modalParticipantLimit + ' peserta dapat ditambahkan sekaligus.', {title: 'Batas Peserta', tone: 'warning'}); return; }
      var duplicateParticipant = validateUniqueParticipantNames(detailRows, activeParticipantNames(''));
      if (duplicateParticipant) { modalAlert('registration-participant-modal', duplicateParticipant.message, {title: 'Nama Peserta Ganda', tone: 'warning'}); return; }
      syncPositionPickers(participantForm);
      refreshDetailPaymentBlocks();
      refreshMoney(participantForm);
      if (!participantForm.checkValidity()) { participantForm.reportValidity(); return; }
      var submit = participantForm.querySelector('[data-detail-add-submit]');
      if (submit) { submit.disabled = true; submit.dataset.originalText = submit.innerHTML; submit.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i>Menyimpan...'; }
      postModalForm(participantForm).then(function (payload) {
        modalClose(document.querySelector('#registration-participant-modal .close-menu'));
        return finishRegistrationMutation(payload, 'registration-detail-content');
      }, function (error) {
        return modalAlert('registration-participant-modal', error.message, {title: 'Gagal Menambah Peserta', tone: 'danger'});
      }).then(function () {
        if (submit) { submit.disabled = false; submit.innerHTML = submit.dataset.originalText || '<i class="fa fa-save me-1"></i> Simpan Peserta'; }
      });
    });
  }

  var paymentModalForm = document.getElementById('registration-payment-form');
  if (paymentModalForm) {
    var paymentMethodModal = document.getElementById('registration-payment-method'), paymentAccountModal = document.getElementById('registration-payment-account'), paymentProofModal = document.getElementById('registration-payment-proof'), paymentAmountModal = document.getElementById('registration-payment-amount'), paymentDateModal = document.getElementById('registration-payment-date'), paymentNoteModal = document.getElementById('registration-payment-note'), paymentTargetLabel = paymentModalForm.parentElement.querySelector('[data-payment-target-label]'), paymentTargetRemaining = paymentModalForm.parentElement.querySelector('[data-payment-target-remaining-text]'), paymentMaxLabel = paymentModalForm.querySelector('[data-payment-max-label]'), paymentProofLabel = document.getElementById('registration-payment-proof-label'), paymentAccountsModal = modalJson(paymentModalForm.dataset.accounts, []);
    function filterModalAccounts() { var typeMap = {cash:['cash'], transfer:['bank','personal'], qris:['qris']}, allowed = typeMap[paymentMethodModal.value] || [], current = paymentAccountModal.value; paymentAccountModal.innerHTML = '<option value="">Pilih akun</option>' + paymentAccountsModal.filter(function (account) { return allowed.indexOf(account.type) !== -1; }).map(function (account) { return '<option value="' + esc(account.id) + '">' + esc(account.name) + '</option>'; }).join(''); if (paymentAccountModal.querySelector('option[value="' + current + '"]')) paymentAccountModal.value = current; var proofRequired = paymentMethodModal.value !== 'cash'; paymentProofModal.required = proofRequired; paymentProofLabel.textContent = proofRequired ? '*' : ''; }
    paymentMethodModal.addEventListener('change', filterModalAccounts); filterModalAccounts();
    document.addEventListener('click', function (event) {
      var trigger = event.target.closest('[data-payment-open]');
      if (!trigger) return;
      event.preventDefault();

      // Setiap target dimulai dari form bersih agar bukti/catatan transaksi
      // sebelumnya tidak pernah terbawa ke peserta atau desa yang berbeda.
      paymentModalForm.reset();
      if (paymentProofModal) paymentProofModal.value = '';
      if (paymentNoteModal) paymentNoteModal.value = '';
      if (paymentDateModal) paymentDateModal.value = paymentModalForm.dataset.defaultDate || todayValue();
      paymentMethodModal.value = 'cash';
      paymentAccountModal.value = '';
      filterModalAccounts();
      paymentAccountModal.value = '';
      restoreCurrentCsrf(paymentModalForm);

      document.getElementById('registration-payment-participant').value = trigger.dataset.targetType === 'participant' ? trigger.dataset.targetId : '';
      paymentTargetLabel.textContent = trigger.dataset.targetLabel || 'Registrasi';
      var remaining = canonicalMoney(trigger.dataset.targetRemaining);
      setMoney(paymentAmountModal, moneyCents(remaining) > 0n ? remaining : '');
      setMoneyControl(paymentAmountModal, {max: remaining});
      paymentTargetRemaining.textContent = formatCurrency(remaining);
      paymentMaxLabel.textContent = 'Nominal otomatis diisi lunas; ubah untuk pembayaran sebagian. Maksimal ' + formatCurrency(remaining);
      paymentModalForm.dataset.triggerId = trigger.dataset.targetId || '';
      refreshMoney(paymentModalForm);
      modalOpen('registration-payment-modal');
    });
    paymentModalForm.addEventListener('submit', function (event) {
      event.preventDefault();
      refreshMoney(paymentModalForm);
      if (!paymentModalForm.checkValidity()) { paymentModalForm.reportValidity(); return; }
      var submit = paymentModalForm.querySelector('[data-payment-submit]');
      if (submit) { submit.disabled = true; submit.dataset.originalText = submit.innerHTML; submit.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i>Menyimpan...'; }
      postModalForm(paymentModalForm).then(function (payload) {
        modalClose(document.querySelector('#registration-payment-modal .close-menu'));
        paymentModalForm.reset();
        refreshMoney(paymentModalForm);
        restoreCurrentCsrf(paymentModalForm);
        return finishRegistrationMutation(payload, 'registration-detail-content');
      }, function (error) {
        return modalAlert('registration-payment-modal', error.message, {title: 'Pembayaran Gagal', tone: 'danger'});
      }).then(function () {
        if (submit) { submit.disabled = false; submit.innerHTML = submit.dataset.originalText || '<i class="fa fa-save me-1"></i> Simpan Pembayaran'; }
      });
    });
  }

  /* Payment verification/reversal is also an inline AJAX action. */
  document.addEventListener('submit', function (event) {
    var form = event.target.closest('form[data-registration-payment-review]');
    if (!form || form.dataset.reviewSubmitting === '1') return;
    event.preventDefault();
    form.dataset.reviewSubmitting = '1';
    var button = event.submitter || form.querySelector('button[type="submit"]');
    if (button) { button.disabled = true; button.dataset.originalText = button.innerHTML; button.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i>Menyimpan...'; }
    postModalForm(form).then(function (payload) {
      return finishRegistrationMutation(payload, 'registration-detail-content');
    }, function (error) {
      if (typeof window.simpAlert === 'function') return window.simpAlert(error.message, {title: 'Status Pembayaran Gagal', tone: 'danger'});
    }).then(function () {
      form.dataset.reviewSubmitting = '0';
      if (button && document.contains(button)) { button.disabled = false; button.innerHTML = button.dataset.originalText || 'Simpan'; }
    });
  });

  /*
   * Detail registration mutations stay in AppKit modals.  The server-side
   * handlers intentionally use distinct verbs so an edit, replacement, and
   * deactivation can each apply their own audit and billing safeguards.
   */
  var participantMutationForm = document.getElementById('registration-participant-mutation-form');
  var mutationPaymentConfig = document.getElementById('registration-inline-payment-config');
  /* Keep this callback in the outer scope: the delegated edit opener below
   * runs outside the setup block on browsers that enforce block-scoped
   * function declarations. */
  var refreshMutationPayment = function () {};
  if (participantMutationForm) {
    var mutationModal = document.getElementById('registration-participant-mutation-modal');
    if (mutationModal) {
      mutationModal.classList.add('simp-full-form-modal');
      mutationModal.dataset.menuWidth = '980';
      mutationModal.dataset.menuHeight = '820';
    }
    participantMutationForm.enctype = 'multipart/form-data';
    participantMutationForm.classList.add('registration-inline-payment-form');
    var mutationPaymentWrap = document.createElement('div');
    mutationPaymentWrap.setAttribute('data-mutation-payment-wrap', '');
    var mutationReasonWrap = participantMutationForm.querySelector('#registration-participant-replace-reason-wrap');
    participantMutationForm.insertBefore(mutationPaymentWrap, mutationReasonWrap || participantMutationForm.querySelector('.row.mb-0'));
    refreshMutationPayment = function (trigger, mode) {
      mutationPaymentWrap.innerHTML = '';
      if (mode !== 'ubah' || !mutationPaymentConfig || mutationPaymentConfig.dataset.canRecordPayment !== '1') return;
      var villageId = mutationPaymentConfig.dataset.villageId || '';
      var billing = mutationPaymentConfig.dataset.billingMode || 'per_village';
      var targetKey = billing === 'per_participant' ? String(trigger.dataset.participantId || '') : 'village';
      var amount = billing === 'per_participant' ? canonicalMoney(trigger.dataset.participantRemaining || '0') : canonicalMoney(mutationPaymentConfig.dataset.villageRemaining || '0');
      var label = billing === 'per_participant' ? (trigger.dataset.participantName || 'Peserta') : 'Pembayaran tingkat desa';
      /* A fully paid target has no useful inline form.  Hiding it also keeps
       * the edit modal focused on identity changes when no balance remains. */
      if (!villageId || !targetKey || moneyCents(amount) <= 0n) return;
      mutationPaymentWrap.innerHTML = inlinePaymentMarkup(villageId, targetKey, amount, label, inlinePaymentAccounts(participantMutationForm));
      initializeInlinePayments(mutationPaymentWrap);
    };
  }
  var registrationCancelForm = document.getElementById('registration-cancel-form');
  var registrationRestoreForm = document.getElementById('registration-restore-form');
  [participantMutationForm, registrationCancelForm, registrationRestoreForm].forEach(function (mutationForm) {
    if (!mutationForm) return;
    var reasonField = mutationForm.querySelector('textarea[name="reason"]');
    if (reasonField) reasonField.maxLength = 500;
  });

  function mutationAction(base, participantId, verb) {
    return String(base || '').replace(/\/+$/, '') + '/' + encodeURIComponent(String(participantId || '')) + '/' + verb;
  }

  function setMutationPosition(form, selectedId) {
    if (!form) return;
    var picker = form.querySelector('[data-position-picker]');
    if (!picker) return;
    var search = picker.querySelector('[data-position-search]');
    var valueId = picker.getAttribute('data-position-value-id');
    var hidden = valueId ? document.getElementById(valueId) : null;
    var match = null;
    positionPickerOptions(picker).some(function (option) {
      if (String(option.dataset.positionId || '') !== String(selectedId || '')) return false;
      match = option;
      return true;
    });
    if (search) search.value = match ? String(match.dataset.positionName || '') : '';
    if (hidden) hidden.value = match ? String(match.dataset.positionId || '') : '';
    syncPositionPicker(picker);
  }

  function setSubmitBusy(button, busyText) {
    if (!button) return;
    button.disabled = true;
    button.dataset.originalText = button.innerHTML;
    button.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i>' + busyText;
  }

  function restoreSubmit(button, fallbackText) {
    if (!button || !document.contains(button)) return;
    button.disabled = false;
    button.innerHTML = button.dataset.originalText || fallbackText;
  }

  function submitRegistrationMutation(form, modalId, successTitle, errorTitle, fallbackText) {
    if (!form || form.dataset.mutationSubmitting === '1') return;
    refreshMoney(form);
    if (!form.checkValidity()) {
      form.reportValidity();
      return;
    }
    form.dataset.mutationSubmitting = '1';
    var submit = form.querySelector('button[type="submit"]');
    setSubmitBusy(submit, 'Menyimpan...');
    postModalForm(form).then(function (payload) {
      modalClose(document.querySelector('#' + modalId + ' .close-menu'));
      form.reset();
      restoreCurrentCsrf(form);
      return finishRegistrationMutation(payload, 'registration-detail-content', successTitle);
    }, function (error) {
      return modalAlert(modalId, error.message, {title: errorTitle, tone: 'danger'});
    }).then(function () {
      form.dataset.mutationSubmitting = '0';
      restoreSubmit(submit, fallbackText);
    });
  }

  if (participantMutationForm) {
    document.addEventListener('click', function (event) {
      var editTrigger = event.target.closest('[data-participant-edit-open]');
      var replaceTrigger = event.target.closest('[data-participant-replace-open]');
      if (!editTrigger && !replaceTrigger) return;
      event.preventDefault();

      var trigger = replaceTrigger || editTrigger;
      var mode = replaceTrigger ? 'ganti' : 'ubah';
      var participantId = String(trigger.dataset.participantId || '');
      if (!participantId) return;
      var name = participantMutationForm.querySelector('#registration-participant-mutation-name');
      var phone = participantMutationForm.querySelector('#registration-participant-mutation-phone');
      var id = participantMutationForm.querySelector('#registration-participant-mutation-id');
      var reason = participantMutationForm.querySelector('#registration-participant-replace-reason');
      var reasonWrap = document.getElementById('registration-participant-replace-reason-wrap');
      var reasonLabel = participantMutationForm.querySelector('[data-replace-reason-required-label]');
      var title = participantMutationForm.querySelector('[data-participant-mutation-title]');
      var eyebrow = participantMutationForm.querySelector('[data-participant-mutation-eyebrow]');
      var target = participantMutationForm.querySelector('[data-participant-mutation-target]');
      var submit = participantMutationForm.querySelector('[data-participant-mutation-submit]');

      participantMutationForm.reset();
      restoreCurrentCsrf(participantMutationForm);
      participantMutationForm.dataset.mutationMode = mode;
      participantMutationForm.action = mutationAction(participantMutationForm.dataset.actionBase, participantId, mode);
      if (id) id.value = participantId;
      if (name) name.value = trigger.dataset.participantName || '';
      if (phone) phone.value = trigger.dataset.participantPhone || '';
      setMutationPosition(participantMutationForm, trigger.dataset.participantPositionId || '');
      if (target) target.textContent = trigger.dataset.participantName || 'Peserta';
      if (eyebrow) eyebrow.textContent = mode === 'ganti' ? 'Penggantian peserta' : 'Perubahan data';
      if (title) title.textContent = mode === 'ganti' ? 'Ganti Peserta' : 'Ubah Peserta';
      if (submit) submit.innerHTML = mode === 'ganti' ? '<i class="fa fa-exchange-alt me-1"></i>Ganti Peserta' : '<i class="fa fa-save me-1"></i>Simpan Perubahan';
      if (reasonWrap) reasonWrap.classList.toggle('d-none', mode !== 'ganti');
      if (reason) {
        reason.disabled = mode !== 'ganti';
        reason.required = mode === 'ganti';
        reason.value = '';
      }
      if (reasonLabel) reasonLabel.textContent = mode === 'ganti' ? '*' : '';
      refreshMutationPayment(trigger, mode);
      modalOpen('registration-participant-mutation-modal');
    });

    participantMutationForm.addEventListener('submit', function (event) {
      event.preventDefault();
      var participantId = participantMutationForm.querySelector('input[name="participant_id"]');
      var duplicateParticipant = validateUniqueParticipantNames(
        participantMutationForm,
        activeParticipantNames(participantId ? participantId.value : '')
      );
      if (duplicateParticipant) {
        modalAlert('registration-participant-mutation-modal', duplicateParticipant.message, {title: 'Nama Peserta Ganda', tone: 'warning'});
        return;
      }
      var mode = participantMutationForm.dataset.mutationMode === 'ganti' ? 'ganti' : 'ubah';
      submitRegistrationMutation(
        participantMutationForm,
        'registration-participant-mutation-modal',
        mode === 'ganti' ? 'Peserta Berhasil Diganti' : 'Peserta Berhasil Diperbarui',
        mode === 'ganti' ? 'Ganti Peserta Gagal' : 'Perubahan Peserta Gagal',
        mode === 'ganti' ? '<i class="fa fa-exchange-alt me-1"></i>Ganti Peserta' : '<i class="fa fa-save me-1"></i>Simpan Perubahan'
      );
    });
  }

  if (registrationCancelForm) {
    document.addEventListener('click', function (event) {
      if (!event.target.closest('[data-registration-cancel-open]')) return;
      event.preventDefault();
      registrationCancelForm.reset();
      restoreCurrentCsrf(registrationCancelForm);
      modalOpen('registration-cancel-modal');
    });
    registrationCancelForm.addEventListener('submit', function (event) {
      event.preventDefault();
      submitRegistrationMutation(registrationCancelForm, 'registration-cancel-modal', 'Registrasi Dibatalkan', 'Pembatalan Registrasi Gagal', '<i class="fa fa-ban me-1"></i>Batalkan');
    });
  }

  if (registrationRestoreForm) {
    document.addEventListener('click', function (event) {
      if (!event.target.closest('[data-registration-restore-open]')) return;
      event.preventDefault();
      registrationRestoreForm.reset();
      restoreCurrentCsrf(registrationRestoreForm);
      modalOpen('registration-restore-modal');
    });
    registrationRestoreForm.addEventListener('submit', function (event) {
      event.preventDefault();
      submitRegistrationMutation(registrationRestoreForm, 'registration-restore-modal', 'Registrasi Dipulihkan', 'Pemulihan Registrasi Gagal', '<i class="fa fa-undo me-1"></i>Pulihkan');
    });
  }
})();
