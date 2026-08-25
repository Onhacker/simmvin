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

  document.querySelectorAll('[data-rupiah]').forEach(function (input) {
    var target = input.form && input.form.querySelector('input[name="' + input.getAttribute('data-rupiah') + '"]');
    function sync() {
      var value = (input.value || '').replace(/[^0-9]/g, '');
      if (target) target.value = value;
      input.value = value ? new Intl.NumberFormat('id-ID').format(value) : '';
    }
    input.addEventListener('input', sync);
    sync();
  });

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
