'use strict';

/*
 * MVIN menyimpan hanya aset tampilan publik. Halaman CI3 yang berisi sesi,
 * CSRF, peserta, transaksi, laporan, dan dokumen selalu diminta ke server.
 */
const MVIN_CACHE_PREFIX = 'mvin-public-static-';
const MVIN_CACHE_VERSION = '2026-08-28-v40';
const MVIN_STATIC_CACHE = MVIN_CACHE_PREFIX + MVIN_CACHE_VERSION;
const MVIN_SCOPE_URL = new URL(self.registration.scope);
const MVIN_APP_PATH = MVIN_SCOPE_URL.pathname.endsWith('/')
  ? MVIN_SCOPE_URL.pathname
  : MVIN_SCOPE_URL.pathname + '/';
const MVIN_OFFLINE_URL = new URL('offline.html', MVIN_SCOPE_URL).href;

const MVIN_PRECACHE = [
  'offline.html',
  'manifest.webmanifest',
  'assets/pwa/icon-180.png',
  'assets/pwa/icon-192.png',
  'assets/pwa/icon-512.png',
  'assets/pwa/icon-maskable-512.png',
  'assets/v22/styles/bootstrap.min.css',
  'assets/v22/fonts/css/fontawesome-all.min.css',
  'assets/css/simp-v22.min.css?v=59',
  'assets/v22/scripts/bootstrap.min.js',
  'assets/v22/scripts/custom.min.js?v=4',
  'assets/js/app.min.js?v=16',
  'assets/js/print-preview.min.js?v=4'
].map(function (path) {
  return new URL(path, MVIN_SCOPE_URL).href;
});

function mvinIsPublicAsset(request, url) {
  if (request.method !== 'GET' || url.origin !== self.location.origin) return false;
  if (!url.pathname.startsWith(MVIN_APP_PATH + 'assets/')) return false;
  if (request.headers.has('authorization') || request.headers.has('range')) return false;
  if (request.headers.get('x-requested-with')) return false;

  const accept = (request.headers.get('accept') || '').toLowerCase();
  if (accept.includes('application/json')) return false;

  return /\.(?:css|js|png|jpe?g|gif|svg|webp|ico|woff2?|ttf|otf)$/i.test(url.pathname);
}

function mvinCanStore(response) {
  if (!response || !response.ok || response.type !== 'basic') return false;
  const cacheControl = (response.headers.get('cache-control') || '').toLowerCase();
  return !cacheControl.includes('no-store') && !cacheControl.includes('private');
}

self.addEventListener('install', function (event) {
  event.waitUntil(
    caches.open(MVIN_STATIC_CACHE)
      .then(function (cache) { return cache.addAll(MVIN_PRECACHE); })
      .then(function () { return self.skipWaiting(); })
  );
});

self.addEventListener('activate', function (event) {
  event.waitUntil(
    caches.keys()
      .then(function (keys) {
        return Promise.all(keys.map(function (key) {
          if (key.startsWith(MVIN_CACHE_PREFIX) && key !== MVIN_STATIC_CACHE) {
            return caches.delete(key);
          }
          return Promise.resolve(false);
        }));
      })
      .then(function () { return self.clients.claim(); })
  );
});

self.addEventListener('fetch', function (event) {
  const request = event.request;
  if (request.method !== 'GET') return;

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;

  /* Navigasi tidak pernah dicache agar data pengguna lama tidak tampil
   * setelah logout atau saat perangkat dipakai oleh akun lain. */
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request).catch(function () {
        return caches.match(MVIN_OFFLINE_URL).then(function (response) {
          return response || Response.error();
        });
      })
    );
    return;
  }

  if (!mvinIsPublicAsset(request, url)) return;

  event.respondWith(
    caches.open(MVIN_STATIC_CACHE).then(function (cache) {
      return cache.match(request).then(function (cachedResponse) {
        if (cachedResponse) return cachedResponse;
        return fetch(request).then(function (response) {
          if (!mvinCanStore(response)) return response;
          return cache.put(request, response.clone()).then(function () { return response; });
        });
      });
    })
  );
});

self.addEventListener('message', function (event) {
  if (event.data && event.data.type === 'SKIP_WAITING') self.skipWaiting();
});
