(function () {
  'use strict';

  var body = document.body;
  window.SIMP = window.SIMP || {
    baseUrl: body.dataset.baseUrl || '/',
    csrfName: body.dataset.csrfName || '',
    csrfHash: body.dataset.csrfHash || ''
  };

  /*
   * AppKit expects every menu/modal to be a sibling of .page-content. When a
   * menu closes, the template leaves an identity transform on .page-content;
   * a fixed modal nested below that transformed element would then be centred
   * against the full document height instead of the viewport on its next open.
   * Keep view-owned modals in AppKit's original DOM position before its
   * DOMContentLoaded initialiser measures and binds them.
   */
  function portalPageModals() {
    var page = document.getElementById('page');
    if (!page) return;
    var hider = page.querySelector('.menu-hider');
    document.querySelectorAll('.page-content .menu.menu-box-modal').forEach(function (menu) {
      page.insertBefore(menu, hider || null);
    });
  }

  window.SIMP.portalPageModals = portalPageModals;
  portalPageModals();

  function applyTheme(theme) {
    var dark = theme === 'dark';
    body.classList.toggle('theme-dark', dark);
    body.classList.toggle('theme-light', !dark);
    document.querySelectorAll('input[data-toggle-theme]').forEach(function (input) {
      input.checked = dark;
    });
  }

  var savedTheme = '';
  try { savedTheme = localStorage.getItem('SIMP-Theme') || ''; } catch (error) { savedTheme = ''; }
  if (savedTheme === 'dark-mode') applyTheme('dark');

  /* Keep AppKit menu dialogs out of the accessibility tree while hidden. */
  document.querySelectorAll('.menu[role="dialog"]').forEach(function (menu) {
    menu.setAttribute('aria-hidden', menu.classList.contains('menu-active') ? 'false' : 'true');
  });
  document.addEventListener('click', function (event) {
    var opener = event.target.closest('[data-menu]');
    if (opener) {
      var target = document.getElementById(opener.getAttribute('data-menu'));
      if (target && target.matches('.menu[role="dialog"]')) {
        document.querySelectorAll('.menu[role="dialog"]').forEach(function (menu) {
          menu.setAttribute('aria-hidden', menu === target ? 'false' : 'true');
        });
      }
    }
    var closer = event.target.closest('.close-menu');
    if (closer) {
      var closedDialog = closer.closest('.menu[role="dialog"]');
      if (closedDialog) closedDialog.setAttribute('aria-hidden', 'true');
    }
    if (event.target.closest('.menu-hider')) {
      document.querySelectorAll('.menu[role="dialog"]').forEach(function (menu) {
        menu.setAttribute('aria-hidden', 'true');
      });
    }
  });

  document.addEventListener('click', function (event) {
    var themeControl = event.target.closest('[data-toggle-theme]');
    if (!themeControl) return;
    event.preventDefault();
    var nextTheme = body.classList.contains('theme-dark') ? 'light' : 'dark';
    applyTheme(nextTheme);
    try { localStorage.setItem('SIMP-Theme', nextTheme + '-mode'); } catch (error) {}
  });

  var dialog = document.getElementById('menu-simp-dialog');
  var dialogOpener = document.getElementById('simp-dialog-opener');
  var dialogHider = document.querySelector('.menu-hider');
  var dialogTitle = document.getElementById('simp-dialog-title');
  var dialogMessage = document.getElementById('simp-dialog-message');
  var dialogIcon = document.getElementById('simp-dialog-icon');
  var dialogConfirmActions = document.getElementById('simp-dialog-confirm-actions');
  var dialogAlertActions = document.getElementById('simp-dialog-alert-actions');
  var dialogCancel = document.getElementById('simp-dialog-cancel');
  var dialogConfirm = document.getElementById('simp-dialog-confirm');
  var dialogOk = document.getElementById('simp-dialog-ok');
  var dialogResolver = null;
  var dialogPreviousFocus = null;

  function closeDialog(result) {
    if (dialog) dialog.setAttribute('aria-hidden', 'true');
    var resolver = dialogResolver;
    dialogResolver = null;
    if (dialogPreviousFocus && typeof dialogPreviousFocus.focus === 'function') dialogPreviousFocus.focus();
    dialogPreviousFocus = null;
    if (resolver) resolver(result);
  }

  function dialogTone(tone) {
    var tones = {
      danger: {icon: 'fa-exclamation-triangle', color: 'color-red-dark', button: 'color-red-dark border-red-dark'},
      warning: {icon: 'fa-exclamation-circle', color: 'color-yellow-dark', button: 'color-yellow-dark border-yellow-dark'},
      success: {icon: 'fa-check-circle', color: 'color-green-dark', button: 'color-green-dark border-green-dark'},
      info: {icon: 'fa-info-circle', color: 'color-blue-dark', button: 'color-blue-dark border-blue-dark'}
    };
    return tones[tone] || {icon: 'fa-question-circle', color: 'color-blue-dark', button: 'color-green-dark border-green-dark'};
  }

  function openDialog(message, options, alertOnly) {
    options = options || {};
    if (!dialog) return Promise.resolve(alertOnly ? true : false);
    if (dialogResolver && dialogCancel) dialogCancel.click();

    var tone = dialogTone(options.tone || (alertOnly ? 'info' : 'confirm'));
    dialogPreviousFocus = document.activeElement;
    dialogTitle.textContent = options.title || (alertOnly ? 'Informasi' : 'Mohon Konfirmasi');
    dialogMessage.textContent = String(message || '');
    dialogIcon.className = 'fa fa-3x ' + tone.icon + ' scale-box ' + tone.color + ' shadow-xl rounded-circle';
    dialogConfirm.textContent = options.confirmLabel || 'Lanjutkan';
    dialogConfirm.className = 'btn close-menu btn-full btn-m w-100 ' + tone.button + ' font-600 rounded-s';
    dialogOk.textContent = options.okLabel || 'Mengerti';
    dialogOk.className = 'btn close-menu btn-full btn-m w-100 ' + tone.button + ' font-600 rounded-s';
    dialogConfirmActions.classList.toggle('d-none', alertOnly);
    dialogAlertActions.classList.toggle('d-none', !alertOnly);
    dialog.setAttribute('aria-hidden', 'false');
    if (dialogOpener) dialogOpener.click();

    return new Promise(function (resolve) {
      dialogResolver = resolve;
      window.setTimeout(function () {
        (alertOnly ? dialogOk : dialogCancel).focus();
      }, 50);
    });
  }

  window.simpConfirm = function (message, options) {
    return openDialog(message, options, false);
  };

  window.simpAlert = function (message, options) {
    return openDialog(message, options, true);
  };

  if (dialogCancel) dialogCancel.addEventListener('click', function () { closeDialog(false); });
  if (dialogConfirm) dialogConfirm.addEventListener('click', function () { closeDialog(true); });
  if (dialogOk) dialogOk.addEventListener('click', function () { closeDialog(true); });
  if (dialogHider) dialogHider.addEventListener('click', function () {
    if (dialogResolver) closeDialog(false);
  });
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && dialog && dialog.classList.contains('menu-active')) {
      event.preventDefault();
      dialogCancel.click();
    }
  });

  function confirmationOptions(target) {
    return {
      title: target.getAttribute('data-confirm-title') || 'Mohon Konfirmasi',
      confirmLabel: target.getAttribute('data-confirm-button') || 'Lanjutkan',
      tone: target.getAttribute('data-confirm-tone') || 'confirm'
    };
  }

  var confirmedForms = new WeakSet();

  document.addEventListener('submit', function (event) {
    var form = event.target.closest('form[data-confirm]');
    if (!form) return;
    if (confirmedForms.has(form)) {
      confirmedForms.delete(form);
      return;
    }
    if (event.defaultPrevented) return;

    event.preventDefault();
    event.stopImmediatePropagation();
    var submitter = event.submitter;
    window.simpConfirm(form.getAttribute('data-confirm'), confirmationOptions(form)).then(function (confirmed) {
      if (!confirmed) return;
      confirmedForms.add(form);
      try {
        if (typeof form.requestSubmit === 'function') {
          if (submitter && submitter.form === form && !submitter.disabled) form.requestSubmit(submitter);
          else form.requestSubmit();
        } else {
          form.submit();
        }
      } finally {
        confirmedForms.delete(form);
      }
    });
  });

  document.addEventListener('click', function (event) {
    var target = event.target.closest('a[data-confirm]');
    if (!target) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    window.simpConfirm(target.getAttribute('data-confirm'), confirmationOptions(target)).then(function (confirmed) {
      if (confirmed) window.location.assign(target.href);
    });
  });

  /*
   * Rupiah inputs keep the original input as a hidden, canonical value so its
   * id/name/data attributes and every existing form/AJAX contract stay intact.
   * SimpMoney.set(target, value) is silent by default; pass true, an event name,
   * an array of event names, or {input:true, change:true} when listeners must run.
   */
  (function installMoneyInputs() {
    var rawToDisplay = new WeakMap();
    var displayToRaw = new WeakMap();
    var lastState = new WeakMap();
    var resetValue = new WeakMap();
    var externalValidity = new WeakMap();
    var nativeSetValidity = new WeakMap();
    var dispatchingRaw = new WeakSet();
    var displaySequence = 0;

    function trimLeadingZeroes(value) {
      value = String(value || '').replace(/^0+(?=\d)/, '');
      return value === '' ? '0' : value;
    }

    function groupThousands(value) {
      var groups = [];
      var cursor = String(value || '0');
      while (cursor.length > 3) {
        groups.unshift(cursor.slice(-3));
        cursor = cursor.slice(0, -3);
      }
      groups.unshift(cursor || '0');
      return groups.join('.');
    }

    function canonicalState(value) {
      var text = value === null || typeof value === 'undefined' ? '' : String(value).trim();
      if (text === '') {
        return {empty:true, syntaxValid:true, major:'', fraction:'', raw:'', hadSeparator:false};
      }
      if (!/^\d+(?:\.\d+)?$/.test(text)) {
        return {empty:false, syntaxValid:false, major:'', fraction:'', raw:text, original:text, hadSeparator:false};
      }
      var parts = text.split('.');
      var major = trimLeadingZeroes(parts[0]);
      var fraction = parts.length > 1 ? parts[1] : '';
      return {
        empty:false,
        syntaxValid:true,
        major:major,
        fraction:fraction,
        raw:major + (fraction !== '' ? '.' + fraction : ''),
        hadSeparator:parts.length > 1,
        majorTooLong:major.length > 16,
        fractionTooLong:fraction.length > 2
      };
    }

    /* Count only numeric characters when translating a caret position from
     * the user's unformatted text to the newly grouped Rupiah text. */
    function countDigits(value) {
      var matches = String(value || '').match(/\d/g);
      return matches ? matches.length : 0;
    }

    function caretForDigits(formatted, majorDigits, fractionDigits, inFraction) {
      var text = String(formatted || '');
      var comma = text.indexOf(',');
      if (inFraction && comma >= 0) {
        var seenFraction = 0;
        for (var fractionIndex = comma + 1; fractionIndex < text.length; fractionIndex++) {
          if (!/\d/.test(text.charAt(fractionIndex))) continue;
          seenFraction += 1;
          if (seenFraction >= fractionDigits) return fractionIndex + 1;
        }
        return text.length;
      }

      var seenMajor = 0;
      var majorEnd = comma >= 0 ? comma : text.length;
      for (var majorIndex = 0; majorIndex < majorEnd; majorIndex++) {
        if (!/\d/.test(text.charAt(majorIndex))) continue;
        seenMajor += 1;
        if (seenMajor >= majorDigits) return majorIndex + 1;
      }
      /* A caret before the first digit belongs after the "Rp " prefix. */
      return majorDigits > 0 ? majorEnd : Math.min(3, text.length);
    }

    function restoreCaret(visible, oldValue, oldStart, oldEnd, formatted) {
      if (!visible || document.activeElement !== visible || oldStart === null || typeof oldStart === 'undefined') return;
      var comma = String(oldValue || '').indexOf(',');
      var startInFraction = comma >= 0 && oldStart > comma;
      var endInFraction = comma >= 0 && oldEnd > comma;
      var startMajorDigits = countDigits(comma >= 0 && oldStart > comma ? oldValue.slice(0, comma) : oldValue.slice(0, oldStart));
      var endMajorDigits = countDigits(comma >= 0 && oldEnd > comma ? oldValue.slice(0, comma) : oldValue.slice(0, oldEnd));
      var startFractionDigits = startInFraction ? countDigits(oldValue.slice(comma + 1, oldStart)) : 0;
      var endFractionDigits = endInFraction ? countDigits(oldValue.slice(comma + 1, oldEnd)) : 0;
      var nextStart = caretForDigits(formatted, startMajorDigits, startFractionDigits, startInFraction);
      var nextEnd = caretForDigits(formatted, endMajorDigits, endFractionDigits, endInFraction);
      try { visible.setSelectionRange(nextStart, Math.max(nextStart, nextEnd)); } catch (error) {}
    }

    function displayState(value) {
      var text = value === null || typeof value === 'undefined' ? '' : String(value);
      var withoutCurrency = text.replace(/^\s*rp\.?\s*/i, '').replace(/\s+/g, '');
      if (withoutCurrency === '') {
        return {empty:true, syntaxValid:true, major:'', fraction:'', raw:'', hadSeparator:false};
      }

      /*
       * The visible field is Indonesian-formatted: periods group thousands
       * and a comma introduces the optional decimal fraction.  While a user
       * is typing, a grouped value necessarily passes through transient forms
       * such as `1.0000` and `1.000000`; insisting on groups of exactly three
       * digits makes the next keystroke invalid (the bug that displayed
       * `Rp 1.000000`).  Accept any digit groups around periods and normalize
       * them below.  Canonical values supplied by the application still use a
       * dot as a decimal separator and are handled by canonicalState().
       */
      var commaMatches = withoutCurrency.match(/,/g) || [];
      var commaIndex = withoutCurrency.indexOf(',');
      var majorText = commaIndex >= 0 ? withoutCurrency.slice(0, commaIndex) : withoutCurrency;
      var fractionText = commaIndex >= 0 ? withoutCurrency.slice(commaIndex + 1) : '';
      var decimalOnly = majorText === '' && commaIndex === 0;
      var majorSyntax = /^\d+(?:\.\d+)*\.?$/.test(majorText);
      var syntaxValid = commaMatches.length <= 1 && majorSyntax && /^\d*$/.test(fractionText);
      if (decimalOnly) syntaxValid = commaMatches.length === 1 && /^\d*$/.test(fractionText);
      var majorDigits = majorText.replace(/\./g, '').replace(/\D/g, '');
      var fractionDigits = fractionText.replace(/\D/g, '');
      if (majorDigits === '' && fractionDigits === '') {
        if (decimalOnly) {
          return {empty:false, syntaxValid:true, major:'0', fraction:'', raw:'0', hadSeparator:true, original:text, fromDisplay:true};
        }
        return {empty:false, syntaxValid:false, major:'', fraction:'', raw:'', hadSeparator:false, original:text, fromDisplay:true};
      }
      var major = trimLeadingZeroes(majorDigits || '0');
      return {
        empty:false,
        syntaxValid:syntaxValid,
        major:major,
        fraction:fractionDigits,
        raw:major + (fractionDigits !== '' ? '.' + fractionDigits : ''),
        hadSeparator:commaIndex !== -1,
        majorTooLong:major.length > 16,
        fractionTooLong:fractionDigits.length > 2,
        original:text,
        fromDisplay:true
      };
    }

    function formatState(state, preserveTypedFraction) {
      if (!state || state.empty) return '';
      if (!state.syntaxValid || state.major === '') return state.original || state.raw || '';
      var fraction = state.fraction || '';
      if (!preserveTypedFraction && fraction !== '' && /^0+$/.test(fraction)) fraction = '';
      return 'Rp ' + groupThousands(state.major) +
        (fraction !== '' ? ',' + fraction : (preserveTypedFraction && state.hadSeparator ? ',' : ''));
    }

    function comparableFraction(state) {
      return ((state && state.fraction) || '') + '00';
    }

    function compareStates(left, right) {
      if (left.major.length !== right.major.length) return left.major.length < right.major.length ? -1 : 1;
      if (left.major !== right.major) return left.major < right.major ? -1 : 1;
      var leftFraction = comparableFraction(left).slice(0, 2);
      var rightFraction = comparableFraction(right).slice(0, 2);
      if (leftFraction === rightFraction) return 0;
      return leftFraction < rightFraction ? -1 : 1;
    }

    function resolveElement(target) {
      if (!target) return null;
      if (typeof target !== 'string') return target.nodeType === 1 ? target : null;
      var byId = document.getElementById(target);
      if (byId) return byId;
      try { return document.querySelector(target); } catch (error) { return null; }
    }

    function rawInput(target) {
      var element = resolveElement(target);
      if (!element) return null;
      if (rawToDisplay.has(element)) return element;
      if (displayToRaw.has(element)) return displayToRaw.get(element);
      if (element.matches && element.matches('input[data-money]')) {
        initialiseInput(element);
        return rawToDisplay.has(element) ? element : null;
      }
      return null;
    }

    function displayInput(target) {
      var raw = rawInput(target);
      return raw ? rawToDisplay.get(raw) || null : null;
    }

    function constraintState(raw, attribute) {
      var value = raw.getAttribute(attribute);
      if (value === null || value === '') return null;
      var state = canonicalState(value);
      return state.syntaxValid && !state.empty && !state.majorTooLong && !state.fractionTooLong ? state : null;
    }

    function internalValidationMessage(raw, state) {
      if (!state) return '';
      if (!state.syntaxValid) return 'Nominal Rupiah tidak valid.';
      if (state.empty) return '';
      if (state.majorTooLong) return 'Nominal maksimal 16 digit sebelum desimal.';
      if (state.fractionTooLong) return 'Nominal maksimal memiliki 2 angka desimal.';
      var step = raw.getAttribute('step');
      if (step && /^1(?:\.0+)?$/.test(step) && state.fraction && !/^0+$/.test(state.fraction)) {
        return 'Nominal harus berupa Rupiah penuh tanpa angka desimal.';
      }
      var minimum = constraintState(raw, 'min');
      var maximum = constraintState(raw, 'max');
      if (minimum && compareStates(state, minimum) < 0) return 'Nominal minimal ' + formatState(minimum, false) + '.';
      if (maximum && compareStates(state, maximum) > 0) return 'Nominal maksimal ' + formatState(maximum, false) + '.';
      return '';
    }

    function applyValidity(raw, state) {
      var visible = rawToDisplay.get(raw);
      if (!visible) return;
      var message = externalValidity.get(raw) || internalValidationMessage(raw, state);
      var nativeSetter = nativeSetValidity.get(raw);
      if (nativeSetter) nativeSetter(message);
      visible.setCustomValidity(message);
      visible.setAttribute('aria-invalid', message ? 'true' : 'false');
    }

    function copyBooleanConstraint(raw, visible, attribute, property) {
      visible[property] = !!raw[property];
      if (raw[property]) visible.setAttribute(attribute, attribute);
      else visible.removeAttribute(attribute);
    }

    function syncConstraints(raw) {
      var visible = rawToDisplay.get(raw);
      if (!visible) return;
      copyBooleanConstraint(raw, visible, 'required', 'required');
      copyBooleanConstraint(raw, visible, 'disabled', 'disabled');
      copyBooleanConstraint(raw, visible, 'readonly', 'readOnly');
      ['min', 'max', 'step'].forEach(function (attribute) {
        if (raw.hasAttribute(attribute)) visible.setAttribute(attribute, raw.getAttribute(attribute));
        else visible.removeAttribute(attribute);
      });
      visible.setAttribute('aria-required', raw.required ? 'true' : 'false');
      visible.setAttribute('aria-disabled', raw.disabled ? 'true' : 'false');
      applyValidity(raw, lastState.get(raw) || canonicalState(raw.value));
    }

    function refreshInput(raw, force) {
      var visible = rawToDisplay.get(raw);
      if (!visible) return null;
      var pendingState = lastState.get(raw);
      if (!force && pendingState && pendingState.fromDisplay && !pendingState.syntaxValid) {
        visible.value = pendingState.original || '';
        syncConstraints(raw);
        return raw;
      }
      var state = canonicalState(raw.value);
      if (state.syntaxValid) raw.value = state.raw;
      lastState.set(raw, state);
      visible.value = formatState(state, false);
      syncConstraints(raw);
      return raw;
    }

    function rawEvent(raw, type) {
      var event;
      try { event = new Event(type, {bubbles:true}); }
      catch (error) {
        event = document.createEvent('Event');
        event.initEvent(type, true, false);
      }
      dispatchingRaw.add(raw);
      try { raw.dispatchEvent(event); }
      finally { dispatchingRaw.delete(raw); }
    }

    function requestedEvents(option) {
      if (!option) return [];
      if (option === true) return ['input'];
      if (typeof option === 'string') return [option];
      if (Array.isArray(option)) return option;
      var events = [];
      if (option.input) events.push('input');
      if (option.change) events.push('change');
      return events;
    }

    function syncFromVisible(visible, dispatchInput) {
      var raw = displayToRaw.get(visible);
      if (!raw) return null;
      var oldValue = visible.value;
      var oldStart = visible.selectionStart;
      var oldEnd = visible.selectionEnd;
      externalValidity.delete(raw);
      var state = displayState(visible.value);
      lastState.set(raw, state);
      var formatted = formatState(state, true);
      visible.value = formatted;
      restoreCaret(visible, oldValue, oldStart, oldEnd, formatted);
      applyValidity(raw, state);
      if (!state.syntaxValid) return raw;
      raw.value = state.raw;
      if (dispatchInput) {
        var beforeEvent = raw.value;
        rawEvent(raw, 'input');
        if (raw.value !== beforeEvent) refreshInput(raw, true);
      }
      return raw;
    }

    function uniqueDisplayId(raw) {
      var base = raw.id ? raw.id + '-money-display' : 'simp-money-display';
      var candidate = base;
      while (document.getElementById(candidate)) {
        displaySequence += 1;
        candidate = base + '-' + displaySequence;
      }
      return candidate;
    }

    function associateLabels(raw, visible) {
      if (!raw.id || !visible) return;
      var visited = [];
      function updateLabels(root) {
        if (!root || visited.indexOf(root) !== -1) return;
        visited.push(root);
        if (root.nodeType === 1 && root.matches('label') && root.getAttribute('for') === raw.id) {
          root.setAttribute('for', visible.id);
        }
        if (!root.querySelectorAll) return;
        Array.prototype.forEach.call(root.querySelectorAll('label'), function (label) {
          if (label.getAttribute('for') === raw.id) label.setAttribute('for', visible.id);
        });
      }

      /* Dynamic form cards are often initialised before their tree is attached. */
      updateLabels(typeof raw.getRootNode === 'function' ? raw.getRootNode() : raw.parentNode);
      updateLabels(document);
    }

    function initialiseInput(raw) {
      if (!raw || raw.nodeType !== 1 || !raw.matches('input[data-money]')) return null;
      if (rawToDisplay.has(raw)) {
        associateLabels(raw, rawToDisplay.get(raw));
        return raw;
      }
      var initialDefault = canonicalState(raw.defaultValue);
      resetValue.set(raw, initialDefault.syntaxValid ? initialDefault.raw : raw.defaultValue);
      var visible = raw.cloneNode(false);
      Array.prototype.slice.call(visible.attributes).forEach(function (attribute) {
        if (attribute.name.indexOf('data-') === 0 || attribute.name.indexOf('on') === 0) visible.removeAttribute(attribute.name);
      });
      visible.type = 'text';
      visible.removeAttribute('name');
      visible.id = uniqueDisplayId(raw);
      visible.setAttribute('data-simp-money-display', '');
      visible.setAttribute('inputmode', 'decimal');
      visible.setAttribute('spellcheck', 'false');
      visible.removeAttribute('pattern');

      raw.type = 'hidden';
      raw.setAttribute('aria-hidden', 'true');
      raw.parentNode.insertBefore(visible, raw.nextSibling);
      rawToDisplay.set(raw, visible);
      displayToRaw.set(visible, raw);

      var nativeSetter = raw.setCustomValidity.bind(raw);
      nativeSetValidity.set(raw, nativeSetter);
      try {
        raw.setCustomValidity = function (message) {
          setValidity(raw, message);
        };
      } catch (ignored) {}

      visible.addEventListener('input', function () { syncFromVisible(visible, true); });
      visible.addEventListener('change', function () {
        syncFromVisible(visible, false);
        visible.value = formatState(lastState.get(raw), false);
        if (lastState.get(raw) && lastState.get(raw).syntaxValid) rawEvent(raw, 'change');
      });
      visible.addEventListener('blur', function () { refreshInput(raw, false); });
      raw.addEventListener('input', function () {
        if (!dispatchingRaw.has(raw)) refreshInput(raw, true);
      });
      raw.addEventListener('change', function () {
        if (!dispatchingRaw.has(raw)) refreshInput(raw, true);
      });

      associateLabels(raw, visible);
      refreshInput(raw, true);
      visible.defaultValue = visible.value;
      return raw;
    }

    function restoreResetValues(scope) {
      moneyInputs(scope).forEach(function (raw) {
        if (!rawToDisplay.has(raw)) return;
        raw.value = resetValue.has(raw) ? resetValue.get(raw) : '';
        externalValidity.delete(raw);
        refreshInput(raw, true);
      });
    }

    function moneyInputs(scope) {
      var root = scope && (scope.nodeType === 1 || scope.nodeType === 9) ? scope : document;
      var inputs = [];
      if (root.nodeType === 1 && root.matches('input[data-money]')) inputs.push(root);
      if (root.querySelectorAll) {
        Array.prototype.forEach.call(root.querySelectorAll('input[data-money]'), function (input) { inputs.push(input); });
      }
      return inputs;
    }

    function init(scope) {
      var root = resolveElement(scope) || (scope && scope.nodeType ? scope : document);
      return moneyInputs(root).map(initialiseInput).filter(function (input) { return !!input; });
    }

    function refresh(targetOrScope) {
      var direct = rawInput(targetOrScope);
      if (direct) return refreshInput(direct, false);
      var root = resolveElement(targetOrScope) || (targetOrScope && targetOrScope.nodeType ? targetOrScope : document);
      init(root);
      var refreshed = [];
      moneyInputs(root).forEach(function (raw) {
        if (rawToDisplay.has(raw)) refreshed.push(refreshInput(raw, false));
      });
      return refreshed;
    }

    function setValue(target, value, dispatchOption) {
      var raw = rawInput(target);
      if (!raw) return null;
      externalValidity.delete(raw);
      var state = canonicalState(value);
      raw.value = state.syntaxValid ? state.raw : (value === null || typeof value === 'undefined' ? '' : String(value));
      refreshInput(raw, true);
      requestedEvents(dispatchOption).forEach(function (type) {
        if (type === 'input' || type === 'change') rawEvent(raw, type);
      });
      refreshInput(raw, true);
      return raw;
    }

    function setValidity(target, message) {
      var raw = rawInput(target);
      if (!raw) return false;
      message = message === null || typeof message === 'undefined' ? '' : String(message);
      if (message) externalValidity.set(raw, message);
      else externalValidity.delete(raw);
      applyValidity(raw, lastState.get(raw) || canonicalState(raw.value));
      return true;
    }

    function format(value) {
      var state = canonicalState(value);
      if (!state.syntaxValid || state.empty || state.majorTooLong || state.fractionTooLong) return '';
      return formatState(state, false);
    }

    window.SimpMoney = {
      init:init,
      refresh:refresh,
      set:setValue,
      raw:rawInput,
      display:displayInput,
      setValidity:setValidity,
      format:format
    };

    document.addEventListener('submit', function (event) {
      if (!event.target || !event.target.querySelectorAll) return;
      event.target.querySelectorAll('[data-simp-money-display]').forEach(function (visible) {
        syncFromVisible(visible, false);
      });
    }, true);

    document.addEventListener('reset', function (event) {
      if (event.defaultPrevented) return;
      restoreResetValues(event.target);
      window.setTimeout(function () { refresh(event.target); }, 0);
    });

    if (window.MutationObserver) {
      new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
          if (mutation.type === 'childList') {
            Array.prototype.forEach.call(mutation.addedNodes, function (node) {
              if (node.nodeType === 1) init(node);
            });
          } else if (mutation.type === 'attributes') {
            var target = mutation.target;
            if (mutation.attributeName === 'data-money' && target.matches('input[data-money]')) initialiseInput(target);
            else if (rawToDisplay.has(target)) syncConstraints(target);
          }
        });
      }).observe(document.documentElement, {
        childList:true,
        subtree:true,
        attributes:true,
        attributeFilter:['data-money', 'required', 'disabled', 'readonly', 'min', 'max', 'step']
      });
    }

    init(document);
  })();

  function refreshCsrf(hash, name) {
    if (name) window.SIMP.csrfName = name;
    if (!hash) return;
    window.SIMP.csrfHash = hash;
    document.querySelectorAll('input[name="' + window.SIMP.csrfName + '"]').forEach(function (field) {
      field.value = hash;
      field.defaultValue = hash;
    });
  }

  window.simpFetch = function (url, options) {
    options = options || {};
    options.headers = options.headers || {};
    if (options.method && options.method.toUpperCase() !== 'GET') {
      options.headers['X-CSRF-TOKEN'] = window.SIMP.csrfHash;
    }
    return fetch(url, options).then(function (response) {
      return response.text().then(function (raw) {
        var body = null;
        try { body = raw ? JSON.parse(raw) : null; } catch (error) { body = null; }
        if (!body || typeof body !== 'object' || Array.isArray(body)) {
          var responseUrl = String(response.url || '');
          if (response.redirected || response.status === 401 || /\/login(?:[/?#]|$)/i.test(responseUrl)) {
            throw new Error('Sesi Anda telah berakhir. Silakan masuk kembali, lalu ulangi pemuatan data.');
          }
          if (response.status === 403 || response.status === 419) {
            throw new Error('Sesi keamanan form sudah tidak berlaku. Muat ulang halaman, lalu coba kembali.');
          }
          throw new Error('Respons server tidak dapat dibaca. Muat ulang halaman, lalu coba kembali.');
        }
        if (body.csrf) refreshCsrf(body.csrf.hash, body.csrf.name);
        if (!response.ok || body.success === false) throw new Error(body.message || 'Permintaan gagal.');
        return body;
      });
    });
  };

  /*
   * Android memakai prompt resmi browser. iOS tidak menyediakan API prompt,
   * sehingga panduannya ditampilkan melalui alert AppKit bawaan MVIN.
   */
  var deferredInstallPrompt = null;
  var androidInstallButtons = Array.prototype.slice.call(document.querySelectorAll('[data-pwa-install-android]'));
  var iosInstallButtons = Array.prototype.slice.call(document.querySelectorAll('[data-pwa-install-ios]'));
  var pwaInstallPanels = Array.prototype.slice.call(document.querySelectorAll('[data-pwa-install-panel]'));
  var pwaStatusElements = Array.prototype.slice.call(document.querySelectorAll('[data-pwa-install-status]'));

  function isPwaStandalone() {
    return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
  }

  function isIosDevice() {
    return /iphone|ipad|ipod/i.test(window.navigator.userAgent || '') ||
      (window.navigator.platform === 'MacIntel' && window.navigator.maxTouchPoints > 1);
  }

  function isIosSafari() {
    var userAgent = window.navigator.userAgent || '';
    return isIosDevice() && /safari/i.test(userAgent) && !/(crios|fxios|edgios|opios)/i.test(userAgent);
  }

  function setPwaStatus(message) {
    pwaStatusElements.forEach(function (element) {
      element.textContent = message;
    });
  }

  function setPwaPanelInstalled(installed) {
    pwaInstallPanels.forEach(function (panel) {
      panel.hidden = installed;
    });
  }

  function setAndroidInstallBusy(busy) {
    androidInstallButtons.forEach(function (button) {
      button.disabled = busy;
      button.setAttribute('aria-disabled', busy ? 'true' : 'false');
    });
  }

  function showPwaMessage(message, options) {
    setPwaStatus(message);
    if (typeof window.simpAlert === 'function') {
      return window.simpAlert(message, options || {title: 'Instal MVIN', tone: 'info'});
    }
    return Promise.resolve(true);
  }

  function showIosInstallInstructions() {
    if (isPwaStandalone()) {
      return showPwaMessage('MVIN sudah terpasang di perangkat ini.', {
        title: 'Aplikasi Terpasang',
        tone: 'success'
      });
    }

    if (!isIosDevice()) {
      return showPwaMessage('Untuk memasang MVIN di iPhone atau iPad, buka alamat MVIN melalui Safari lalu ketuk tombol Instal iOS.', {
        title: 'Instal MVIN di iOS',
        tone: 'info'
      });
    }

    if (!isIosSafari()) {
      return showPwaMessage('Buka MVIN melalui Safari. Setelah itu ketuk Bagikan, pilih Tambahkan ke Layar Utama, lalu ketuk Tambah.', {
        title: 'Buka di Safari',
        tone: 'info'
      });
    }

    return showPwaMessage('Di Safari, ketuk Bagikan, pilih Tambahkan ke Layar Utama, lalu ketuk Tambah. Ikon MVIN akan muncul di layar utama.', {
      title: 'Instal MVIN di iOS',
      tone: 'info'
    });
  }

  if (isPwaStandalone()) {
    setPwaPanelInstalled(true);
    setPwaStatus('MVIN sudah terpasang dan sedang dibuka sebagai aplikasi.');
  }

  window.addEventListener('beforeinstallprompt', function (event) {
    event.preventDefault();
    deferredInstallPrompt = event;
    setAndroidInstallBusy(false);
    setPwaStatus('MVIN siap dipasang langsung melalui browser.');
  });

  androidInstallButtons.forEach(function (button) {
    button.addEventListener('click', function () {
      if (isPwaStandalone()) {
        showPwaMessage('MVIN sudah terpasang di perangkat ini.', {
          title: 'Aplikasi Terpasang',
          tone: 'success'
        });
        return;
      }

      if (isIosDevice()) {
        showPwaMessage('Instal Android harus dilakukan melalui Chrome pada perangkat Android.', {
          title: 'Instal Android',
          tone: 'info'
        });
        return;
      }

      if (!window.isSecureContext) {
        showPwaMessage('Pemasangan Android memerlukan koneksi HTTPS. Buka alamat MVIN yang aman melalui Chrome, lalu coba kembali.', {
          title: 'Instal Belum Tersedia',
          tone: 'warning'
        });
        return;
      }

      if (!deferredInstallPrompt) {
        showPwaMessage('Prompt instal belum tersedia. Buka MVIN melalui Chrome di perangkat Android, lalu coba lagi atau pilih Instal aplikasi dari menu Chrome.', {
          title: 'Instal Android',
          tone: 'info'
        });
        return;
      }

      var promptEvent = deferredInstallPrompt;
      deferredInstallPrompt = null;
      setAndroidInstallBusy(true);

      try {
        Promise.resolve(promptEvent.prompt()).then(function () {
          return promptEvent.userChoice;
        }).then(function (choice) {
          setAndroidInstallBusy(false);
          if (choice && choice.outcome === 'accepted') {
            showPwaMessage('Pemasangan MVIN sedang diproses dan akan muncul di layar utama perangkat.', {
              title: 'Pemasangan Dimulai',
              tone: 'success'
            });
          } else {
            showPwaMessage('Pemasangan dibatalkan. Anda dapat mencoba kembali melalui menu Instal aplikasi di Chrome.', {
              title: 'Pemasangan Dibatalkan',
              tone: 'info'
            });
          }
        }).catch(function () {
          setAndroidInstallBusy(false);
          showPwaMessage('Pemasangan belum dapat dimulai. Muat ulang halaman, lalu coba kembali melalui Chrome.', {
            title: 'Instal Belum Berhasil',
            tone: 'warning'
          });
        });
      } catch (error) {
        setAndroidInstallBusy(false);
        showPwaMessage('Pemasangan belum dapat dimulai. Muat ulang halaman, lalu coba kembali melalui Chrome.', {
          title: 'Instal Belum Berhasil',
          tone: 'warning'
        });
      }
    });
  });

  iosInstallButtons.forEach(function (button) {
    button.addEventListener('click', function (event) {
      event.preventDefault();
      showIosInstallInstructions();
    });
  });

  window.addEventListener('appinstalled', function () {
    deferredInstallPrompt = null;
    setAndroidInstallBusy(false);
    setPwaPanelInstalled(true);
    showPwaMessage('MVIN berhasil dipasang dan sudah tersedia di layar utama.', {
      title: 'Aplikasi Terpasang',
      tone: 'success'
    });
  });

  var displayModeQuery = window.matchMedia('(display-mode: standalone)');
  var handleDisplayModeChange = function (event) {
    if (event.matches) {
      setPwaPanelInstalled(true);
      setPwaStatus('MVIN sudah terpasang dan sedang dibuka sebagai aplikasi.');
    }
  };
  if (typeof displayModeQuery.addEventListener === 'function') {
    displayModeQuery.addEventListener('change', handleDisplayModeChange);
  } else if (typeof displayModeQuery.addListener === 'function') {
    displayModeQuery.addListener(handleDisplayModeChange);
  }

  if ('serviceWorker' in window.navigator && window.isSecureContext) {
    window.addEventListener('load', function () {
      var baseUrl = String(window.SIMP.baseUrl || '/');
      if (baseUrl.charAt(baseUrl.length - 1) !== '/') baseUrl += '/';
      var workerUrl = window.SIMP.serviceWorkerUrl || (baseUrl + 'service-worker.js');
      var workerScope = window.SIMP.serviceWorkerScope || baseUrl;

      window.navigator.serviceWorker.register(workerUrl, {scope: workerScope}).catch(function () {
        setPwaStatus('Mode aplikasi belum aktif. Muat ulang halaman saat koneksi tersedia.');
      });
    });
  }
})();
