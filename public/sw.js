// Bump the version to purge old caches (v1 cached authenticated dashboard HTML).
const CACHE_NAME = 'racunin-static-v4';
const PRECACHE_ASSETS = [
  '/manifest.json',
  '/pwa-192x192.png',
  '/pwa-512x512.png'
];

const OFFLINE_HTML = `<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Offline — Racunin</title>
<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;font-family:system-ui,sans-serif;background:#F5EFFA;color:#22092F;text-align:center;padding:16px}
h1{font-size:1.25rem;margin:0 0 .5rem}p{color:#6D5A78;margin:0 0 1rem}button{background:#E0186C;color:#fff;border:0;border-radius:8px;padding:.7rem 1.2rem;font-weight:700}</style></head>
<body><div><h1>Anda sedang offline</h1><p>Periksa koneksi internet lalu coba lagi.</p><button onclick="location.reload()">Coba Lagi</button></div></body></html>`;

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.addAll(PRECACHE_ASSETS))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((names) => Promise.all(names.filter((name) => name !== CACHE_NAME).map((name) => caches.delete(name))))
      .then(() => self.clients.claim())
  );
});

function isStaticAsset(url) {
  return url.pathname.startsWith('/build/')
    || PRECACHE_ASSETS.includes(url.pathname)
    || /\.(?:png|jpg|jpeg|webp|svg|ico|woff2?)$/.test(url.pathname);
}

self.addEventListener('fetch', (event) => {
  const request = event.request;

  if (request.method !== 'GET') {
    return;
  }

  const url = new URL(request.url);

  // Page navigations: always network (pages may be private or user-specific); offline fallback only
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request).catch(() => new Response(OFFLINE_HTML, {
        status: 503,
        headers: { 'Content-Type': 'text/html; charset=utf-8' }
      }))
    );
    return;
  }

  // Same-origin static assets: cache-first (Vite builds are content-hashed)
  if (url.origin === self.location.origin && isStaticAsset(url)) {
    event.respondWith(
      caches.match(request).then((cached) => cached || fetch(request).then((response) => {
        if (response && response.status === 200 && response.type === 'basic') {
          const copy = response.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
        }
        return response;
      }))
    );
  }

  // Everything else (Livewire updates, short-link redirects, APIs) goes straight to the network
});
