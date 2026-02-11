const CACHE_NAME = 'wd-admin-v1';
const CRITICAL_ASSETS = [
    '/WDParcelSendReceiverPWA/Admins/super_admin/offline.html',
    '/WDParcelSendReceiverPWA/Admins/super_admin/assets/css/admin-dashboard.css',
    '/WDParcelSendReceiverPWA/Admins/super_admin/assets/css/dashboard-improvements.css',
    '/WDParcelSendReceiverPWA/Admins/super_admin/assets/css/view-details.css',
    '/WDParcelSendReceiverPWA/Admins/super_admin/assets/css/sidebar.css',
    '/WDParcelSendReceiverPWA/Admins/super_admin/assets/js/admin-scripts.js',
    '/WDParcelSendReceiverPWA/Admins/super_admin/assets/img/Logo.png',
    '/WDParcelSendReceiverPWA/Admins/super_admin/company-app/assets/images/icon-192x192.png',
    '/WDParcelSendReceiverPWA/Admins/super_admin/company-app/assets/images/icon-512x512.png',
    '/WDParcelSendReceiverPWA/Admins/super_admin/company-app/assets/css/company.css',
    '/WDParcelSendReceiverPWA/Admins/super_admin/company-app/assets/js/company-scripts.js'
];

const OPTIONAL_ASSETS = [
    'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
    'https://code.jquery.com/jquery-3.6.0.min.js',
    'https://cdn.jsdelivr.net/npm/sweetalert2@11',
    'https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2',
    'https://cdn.jsdelivr.net/npm/chart.js'
];

// Install event - cache assets
self.addEventListener('install', event => {
    console.log('[Service Worker] Installing...');
    // Force immediate activation
    self.skipWaiting();

    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(async cache => {
                console.log('[Service Worker] Caching critical assets');

                // Cache critical assets first - if these fail, installation fails
                try {
                    await cache.addAll(CRITICAL_ASSETS);
                    console.log('[Service Worker] Critical assets cached');
                } catch (err) {
                    console.error('[Service Worker] Failed to cache critical assets:', err);
                }

                // Cache optional assets - don't fail if these can't be cached
                try {
                    await cache.addAll(OPTIONAL_ASSETS);
                    console.log('[Service Worker] Optional assets cached');
                } catch (err) {
                    console.warn('[Service Worker] Failed to cache some optional assets:', err);
                }
            })
    );
});

// Activate event - cleanup old caches
self.addEventListener('activate', event => {
    console.log('[Service Worker] Activating...');
    // Take control of all pages immediately
    event.waitUntil(clients.claim());

    const cacheWhitelist = [CACHE_NAME];
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheWhitelist.indexOf(cacheName) === -1) {
                        console.log('[Service Worker] Deleting old cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
});

// Fetch event - serving cached content
self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);

    // Skip cross-origin requests that are not in our optional assets list
    if (url.origin !== location.origin && !OPTIONAL_ASSETS.includes(event.request.url)) {
        return;
    }

    // For navigation requests (HTML pages)
    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request)
                .then(response => {
                    // Check if we received a valid response
                    if (!response || response.status !== 200 || response.type !== 'basic') {
                        return response;
                    }

                    // Clone the response and put it in the cache (Runtime Caching)
                    const responseToCache = response.clone();
                    caches.open(CACHE_NAME)
                        .then(cache => {
                            cache.put(event.request, responseToCache);
                        });

                    return response;
                })
                .catch(() => {
                    // Network failed, try to match in cache
                    return caches.match(event.request)
                        .then(response => {
                            if (response) return response;
                            // If not in cache, show offline page
                            return caches.match('/WDParcelSendReceiverPWA/Admins/super_admin/offline.html');
                        });
                })
        );
        return;
    }

    // For API requests - network first, fall back to cache
    if (url.pathname.includes('/api/')) {
        event.respondWith(
            fetch(event.request)
                .then(response => {
                    // Cache successful API responses
                    if (response.ok) {
                        const responseToCache = response.clone();
                        caches.open(CACHE_NAME).then(cache => {
                            cache.put(event.request, responseToCache);
                        });
                    }
                    return response;
                })
                .catch(() => {
                    // If network fails, try cache
                    return caches.match(event.request);
                })
        );
        return;
    }

    // For other requests (CSS, JS, images) - cache first, then network
    event.respondWith(
        caches.match(event.request)
            .then(response => {
                if (response) {
                    return response;
                }
                return fetch(event.request)
                    .then(response => {
                        // Cache the fetched resource
                        if (response && response.status === 200) {
                            const responseToCache = response.clone();
                            caches.open(CACHE_NAME).then(cache => {
                                cache.put(event.request, responseToCache);
                            });
                        }
                        return response;
                    });
            })
    );
});
