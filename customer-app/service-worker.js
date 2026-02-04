const CACHE_NAME = 'wdparcel-customer-cache-v2';
const urlsToCache = [
  './',
  './index.php',
  './track.php',
  './contact-support.php',
  './settings.php',
  './change-address.php',
  './view_delivery.php',
  './assets/css/dashboard.css',
  './assets/img/logo.png',
  './assets/img/logo-192.png',
  './assets/img/logo-512.png',
  './manifest.json',
  
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(urlsToCache))
  );
});

self.addEventListener('fetch', event => {
  
  if (event.request.method !== 'GET') {
    event.respondWith(fetch(event.request));
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
