/**
 * Outlet App Service Worker
 * Provides offline functionality, caching, and background sync for outlet operations
 * Handles: Parcel creation, trip management, status updates when offline
 */

const CACHE_NAME = 'outlet-app-v1.0.0';
const DATA_CACHE_NAME = 'outlet-app-data-v1.0.0';

// Core files to cache for offline use
const CORE_CACHE_URLS = [
    '/outlet-app/',
    '/outlet-app/login.php',
    '/outlet-app/pages/dashboard.php',
    '/outlet-app/pages/parcel_registration.php',
    '/outlet-app/pages/trips.php',
    '/outlet-app/pages/trip_wizard.php',
    '/outlet-app/css/outlet-dashboard.css',
    '/outlet-app/css/parcel_registration.css',
    '/outlet-app/css/trips.css',
    '/outlet-app/manifest.json',
    // External resources
    'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css'
];

// Install event - cache core resources
self.addEventListener('install', (event) => {
    console.log('[SW] Installing service worker...');
    
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                console.log('[SW] Caching core resources');
                // Use addAll with error handling
                return cache.addAll(CORE_CACHE_URLS.map(url => new Request(url, { cache: 'reload' })))
                    .catch(error => {
                        console.error('[SW] Failed to cache some resources:', error);
                        // Continue installation even if some resources fail
                    });
            })
            .then(() => self.skipWaiting())
    );
});

// Activate event - clean old caches and take control
self.addEventListener('activate', (event) => {
    console.log('[SW] Activating service worker...');
    
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames
                    .filter(cacheName => 
                        cacheName !== CACHE_NAME && cacheName !== DATA_CACHE_NAME
                    )
                    .map(cacheName => {
                        console.log('[SW] Deleting old cache:', cacheName);
                        return caches.delete(cacheName);
                    })
            );
        }).then(() => {
            console.log('[SW] Service worker activated');
            return self.clients.claim();
        })
    );
});

// Fetch event - handle offline requests
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);
    
    // Skip non-GET requests for now (they'll be handled by background sync)
    if (request.method !== 'GET') {
        return;
    }
    
    // Skip external domains (except fonts and CDN)
    if (url.origin !== self.location.origin && 
        !url.href.includes('fonts.googleapis.com') && 
        !url.href.includes('cdnjs.cloudflare.com') &&
        !url.href.includes('fonts.gstatic.com')) {
        return;
    }
    
    event.respondWith(handleFetch(request));
});

async function handleFetch(request) {
    const url = new URL(request.url);
    
    try {
        // API requests - network first, then cache
        if (url.pathname.includes('/api/')) {
            return await networkFirstStrategy(request, DATA_CACHE_NAME);
        }
        
        // Static assets - cache first
        if (isStaticAsset(url.pathname)) {
            return await cacheFirstStrategy(request, CACHE_NAME);
        }
        
        // PHP pages - network first, fall back to cache
        return await networkFirstStrategy(request, CACHE_NAME);
        
    } catch (error) {
        console.error('[SW] Fetch error:', error);
        
        // Try to serve from cache
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // Return offline page for navigation requests
        if (request.mode === 'navigate') {
            return createOfflinePage();
        }
        
        // Return offline JSON for API requests
        if (url.pathname.includes('/api/')) {
            return createOfflineApiResponse();
        }
        
        return new Response('Offline', { status: 503, statusText: 'Service Unavailable' });
    }
}

// Network first strategy - try network, fall back to cache
async function networkFirstStrategy(request, cacheName) {
    try {
        const networkResponse = await fetch(request);
        
        // Cache successful responses
        if (networkResponse && networkResponse.status === 200) {
            const cache = await caches.open(cacheName);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        // Network failed, try cache
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            console.log('[SW] Serving from cache:', request.url);
            return cachedResponse;
        }
        throw error;
    }
}

// Cache first strategy - check cache first, then network
async function cacheFirstStrategy(request, cacheName) {
    const cachedResponse = await caches.match(request);
    
    if (cachedResponse) {
        return cachedResponse;
    }
    
    try {
        const networkResponse = await fetch(request);
        
        if (networkResponse && networkResponse.status === 200) {
            const cache = await caches.open(cacheName);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        console.error('[SW] Network and cache failed:', request.url);
        throw error;
    }
}

// Check if URL is a static asset
function isStaticAsset(pathname) {
    const staticExtensions = ['.css', '.js', '.png', '.jpg', '.jpeg', '.gif', '.svg', '.ico', '.woff', '.woff2', '.ttf'];
    return staticExtensions.some(ext => pathname.endsWith(ext));
}

// Create offline page response
function createOfflinePage() {
    const offlineHTML = `
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Offline - Outlet App</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: 'Poppins', -apple-system, BlinkMacSystemFont, sans-serif;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 100vh;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    text-align: center;
                    padding: 20px;
                }
                .offline-container {
                    max-width: 500px;
                }
                .offline-icon {
                    font-size: 80px;
                    margin-bottom: 20px;
                    animation: pulse 2s infinite;
                }
                @keyframes pulse {
                    0%, 100% { opacity: 1; }
                    50% { opacity: 0.5; }
                }
                h1 { font-size: 2.5rem; margin-bottom: 1rem; }
                p { font-size: 1.1rem; margin-bottom: 2rem; opacity: 0.9; }
                button {
                    background: white;
                    color: #667eea;
                    border: none;
                    padding: 15px 40px;
                    font-size: 1.1rem;
                    font-weight: 600;
                    border-radius: 50px;
                    cursor: pointer;
                    transition: transform 0.2s;
                }
                button:hover { transform: scale(1.05); }
                .status {
                    margin-top: 30px;
                    padding: 15px;
                    background: rgba(255,255,255,0.1);
                    border-radius: 10px;
                    font-size: 0.9rem;
                }
            </style>
        </head>
        <body>
            <div class="offline-container">
                <div class="offline-icon">📡</div>
                <h1>You're Offline</h1>
                <p>Don't worry! Your data is safe. Any changes you make will be synced automatically when you reconnect.</p>
                <button onclick="location.reload()">Try Again</button>
                <div class="status">
                    <strong>Offline Mode Active</strong><br>
                    Parcels created while offline will be submitted automatically when connection is restored.
                </div>
            </div>
        </body>
        </html>
    `;
    
    return new Response(offlineHTML, {
        status: 200,
        statusText: 'OK',
        headers: new Headers({
            'Content-Type': 'text/html'
        })
    });
}

// Create offline API response
function createOfflineApiResponse() {
    return new Response(JSON.stringify({
        success: false,
        offline: true,
        error: 'No internet connection. Your data will be synced when connection is restored.',
        queued: true
    }), {
        status: 200,
        headers: new Headers({
            'Content-Type': 'application/json'
        })
    });
}

// Background Sync - sync pending operations when online
self.addEventListener('sync', (event) => {
    console.log('[SW] Background sync triggered:', event.tag);
    
    if (event.tag === 'sync-parcels') {
        event.waitUntil(syncPendingParcels());
    } else if (event.tag === 'sync-trips') {
        event.waitUntil(syncPendingTrips());
    } else if (event.tag === 'sync-all') {
        event.waitUntil(syncAllPendingOperations());
    }
});

// Sync pending parcel creations
async function syncPendingParcels() {
    console.log('[SW] Syncing pending parcels...');
    
    try {
        const db = await openDatabase();
        const parcels = await getPendingItems(db, 'pending-parcels');
        
        console.log(`[SW] Found ${parcels.length} pending parcels to sync`);
        
        for (const item of parcels) {
            try {
                const response = await fetch('/outlet-app/api/parcels/create_parcel.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(item.data)
                });
                
                if (response.ok) {
                    await removeItem(db, 'pending-parcels', item.id);
                    console.log('[SW] Successfully synced parcel:', item.id);
                    
                    // Notify user
                    self.registration.showNotification('Parcel Synced', {
                        body: `Parcel created: ${item.data.trackingNumber || 'New parcel'}`,
                        icon: '/outlet-app/icons/icon-192x192.png',
                        badge: '/outlet-app/icons/icon-72x72.png'
                    });
                } else {
                    console.error('[SW] Failed to sync parcel:', await response.text());
                }
            } catch (error) {
                console.error('[SW] Error syncing parcel:', error);
                // Keep item in queue for next sync
            }
        }
    } catch (error) {
        console.error('[SW] Error in syncPendingParcels:', error);
    }
}

// Sync pending trip updates
async function syncPendingTrips() {
    console.log('[SW] Syncing pending trips...');
    
    try {
        const db = await openDatabase();
        const trips = await getPendingItems(db, 'pending-trips');
        
        console.log(`[SW] Found ${trips.length} pending trip operations to sync`);
        
        for (const item of trips) {
            try {
                const response = await fetch(item.endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(item.data)
                });
                
                if (response.ok) {
                    await removeItem(db, 'pending-trips', item.id);
                    console.log('[SW] Successfully synced trip operation:', item.id);
                } else {
                    console.error('[SW] Failed to sync trip operation:', await response.text());
                }
            } catch (error) {
                console.error('[SW] Error syncing trip operation:', error);
            }
        }
    } catch (error) {
        console.error('[SW] Error in syncPendingTrips:', error);
    }
}

// Sync all pending operations
async function syncAllPendingOperations() {
    await syncPendingParcels();
    await syncPendingTrips();
}

// IndexedDB operations
function openDatabase() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('outlet-app-offline-db', 1);
        
        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);
        
        request.onupgradeneeded = (event) => {
            const db = event.target.result;
            
            // Create object stores for different types of pending operations
            if (!db.objectStoreNames.contains('pending-parcels')) {
                db.createObjectStore('pending-parcels', { keyPath: 'id', autoIncrement: true });
            }
            if (!db.objectStoreNames.contains('pending-trips')) {
                db.createObjectStore('pending-trips', { keyPath: 'id', autoIncrement: true });
            }
        };
    });
}

function getPendingItems(db, storeName) {
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(storeName, 'readonly');
        const store = transaction.objectStore(storeName);
        const request = store.getAll();
        
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

function removeItem(db, storeName, id) {
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(storeName, 'readwrite');
        const store = transaction.objectStore(storeName);
        const request = store.delete(id);
        
        request.onsuccess = () => resolve();
        request.onerror = () => reject(request.error);
    });
}

// Message event - handle messages from clients
self.addEventListener('message', (event) => {
    console.log('[SW] Message received:', event.data);
    
    if (event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    } else if (event.data.type === 'SYNC_NOW') {
        syncAllPendingOperations();
    }
});

// Push notification event - handle incoming push notifications
self.addEventListener('push', (event) => {
    console.log('[SW] Push notification received');
    
    if (!event.data) {
        console.log('[SW] Push event but no data');
        return;
    }
    
    try {
        const data = event.data.json();
        console.log('[SW] Push data:', data);
        
        const options = {
            body: data.body || data.message || 'New notification',
            icon: data.icon || '/outlet-app/icons/icon-192x192.png',
            badge: data.badge || '/outlet-app/icons/icon-72x72.png',
            data: data.data || {},
            vibrate: data.vibrate || [200, 100, 200],
            tag: data.tag || 'outlet-notification',
            requireInteraction: data.requireInteraction || false,
            timestamp: Date.now()
        };
        
        // Add actions if provided
        if (data.actions) {
            options.actions = data.actions;
        }
        
        event.waitUntil(
            self.registration.showNotification(data.title || 'Outlet Manager', options)
        );
    } catch (error) {
        console.error('[SW] Error handling push notification:', error);
    }
});

// Notification click event - handle notification clicks
self.addEventListener('notificationclick', (event) => {
    console.log('[SW] Notification clicked:', event.action);
    
    event.notification.close();
    
    // Get the URL from notification data
    const urlToOpen = event.notification.data?.url || 'http://acme.localhost/outlet-app/pages/outlet_dashboard.php';
    
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            // Check if dashboard is already open
            for (const client of clientList) {
                if (client.url.includes('/outlet-app/') && 'focus' in client) {
                    return client.focus().then(() => {
                        // Navigate to the URL if possible
                        if (client.navigate) {
                            return client.navigate(urlToOpen);
                        }
                    });
                }
            }
            
            // No existing client found, open new window
            if (clients.openWindow) {
                return clients.openWindow(urlToOpen);
            }
        })
    );
});

console.log('[SW] Service Worker script loaded');
