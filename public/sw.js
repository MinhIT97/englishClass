/* Service worker — basic offline shell + cache-first for static assets. */

const CACHE_VERSION = 'v1';
const CORE_ASSETS = [
  '/',
  '/css/theme.css',
  '/js/toast.js',
  '/manifest.json',
  '/offline',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_VERSION).then((cache) => cache.addAll(CORE_ASSETS).catch(() => {}))
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE_VERSION).map((k) => caches.delete(k)))
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const req = event.request;

  // Never cache POST/PUT/PATCH/DELETE or non-GET requests.
  if (req.method !== 'GET') return;

  const url = new URL(req.url);

  // Bypass API/auth/telegram webhooks — always go to network.
  if (
    url.pathname.startsWith('/api/') ||
    url.pathname.startsWith('/telegram/') ||
    url.pathname.startsWith('/login') ||
    url.pathname.startsWith('/register') ||
    url.pathname.startsWith('/admin/')
  ) {
    return;
  }

  // Network-first for HTML, cache-fallback to /offline.
  if (req.headers.get('accept')?.includes('text/html')) {
    event.respondWith(
      fetch(req)
        .then((res) => {
          const copy = res.clone();
          caches.open(CACHE_VERSION).then((cache) => cache.put(req, copy));
          return res;
        })
        .catch(() =>
          caches.match(req).then((cached) => cached || caches.match('/offline'))
        )
    );
    return;
  }

  // Cache-first for static assets (js/css/images).
  event.respondWith(
    caches.match(req).then((cached) => {
      if (cached) return cached;
      return fetch(req).then((res) => {
        if (res.ok && (url.pathname.startsWith('/css/') || url.pathname.startsWith('/js/') || url.pathname.startsWith('/images/'))) {
          const copy = res.clone();
          caches.open(CACHE_VERSION).then((cache) => cache.put(req, copy));
        }
        return res;
      }).catch(() => cached);
    })
  );
});