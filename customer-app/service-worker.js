const CACHE_NAME = 'wdparcel-customer-cache-v4';
const urlsToCache = [
  './',
  './secure_tracking.html',
  './gps_tracking.html',
  './assets/css/secure_tracking.css',
  './assets/css/track_details.css',
  './assets/css/styles.css',
  './assets/img/logo.png',
  './assets/img/app-icon-192.png',
  './assets/img/app-icon-512.png',
  './manifest.json',
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache =>
      Promise.all(
        urlsToCache.map((url) =>
          cache.add(url).catch((error) => {
            console.warn('[Service Worker] Skipping cache for', url, error);
          })
        )
      )
    )
  );
  self.skipWaiting();
});

self.addEventListener('fetch', event => {
  if (event.request.method !== 'GET') {
    event.respondWith(fetch(event.request));
    return;
  }

  const requestUrl = new URL(event.request.url);
  const isHtmlRequest = event.request.mode === 'navigate'
    || (event.request.headers.get('accept') || '').includes('text/html');
  const isPhpRequest = requestUrl.pathname.endsWith('.php');

  if (isPhpRequest || isHtmlRequest) {
    // Always hit the network for dynamic pages, fallback to the secure tracker on failure.
    event.respondWith(
      fetch(event.request)
        .catch(() => caches.match('./secure_tracking.html'))
    );
    return;
  }

  if (event.request.url.includes('login.php') || event.request.url.includes('register.php')) {
    event.respondWith(
      fetch(event.request)
        .then(response => {
          return caches.open(CACHE_NAME).then(cache => {
            cache.put(event.request, response.clone());
            return response;
          });
        })
        .catch(() => caches.match(event.request))
    );
    return;
  }

  event.respondWith(
    caches.match(event.request)
      .then(response => response || fetch(event.request))
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(
        keys.map(key => (key !== CACHE_NAME ? caches.delete(key) : null))
      )
    )
  );
  self.clients.claim();
});
