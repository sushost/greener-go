// Minimal "offline-shell" cache. Keep it tiny.
const CACHE = 'gg-v1';
const ASSETS = [
  '/greener-go/index.html',
  '/greener-go/manifest.webmanifest'
  // add tiny CSS/JS if you split them out later
];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then(c => c.addAll(ASSETS)));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))
    )
  );
});

// Cache-first for shell, network for everything else
self.addEventListener('fetch', (e) => {
  const url = new URL(e.request.url);
  if (ASSETS.includes(url.pathname)) {
    e.respondWith(caches.match(e.request).then(r => r || fetch(e.request)));
  }
});
