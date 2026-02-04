

self.addEventListener('install', (event) => {
    console.log('[Manager SW] Installing...');
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    console.log('[Manager SW] Activating...');
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', (event) => {
    console.log('[Manager SW] Push notification received', event);
    
    if (!event.data) {
        console.warn('[Manager SW] Push event has no data');
        return;
    }
    
    try {
        const data = event.data.json();
        console.log('[Manager SW] Push data:', data);
        
        const options = {
            body: data.body || data.message || 'New notification',
            icon: data.icon || '/outlet-app/icons/icon-192x192.png',
            badge: data.badge || '/outlet-app/icons/icon-72x72.png',
            data: data.data || {},
            vibrate: data.vibrate || [200, 100, 200],
            tag: data.tag || 'manager-notification',
            requireInteraction: false,
            timestamp: Date.now()
        };
        
        if (data.actions) {
            options.actions = data.actions;
        }
        
        console.log('[Manager SW] Showing notification with title:', data.title);
        
        event.waitUntil(
            self.registration.showNotification(data.title || 'Outlet Manager', options)
                .then(() => console.log('[Manager SW] Notification shown successfully'))
                .catch(err => console.error('[Manager SW] Failed to show notification:', err))
        );
    } catch (error) {
        console.error('[Manager SW] Error processing push:', error);
        
        
        event.waitUntil(
            self.registration.showNotification('New Message', {
                body: 'You have a new notification',
                icon: '/outlet-app/icons/icon-192x192.png'
            })
        );
    }
});

self.addEventListener('notificationclick', (event) => {
    console.log('[Manager SW] Notification clicked');
    
    event.notification.close();
    
    const baseUrl = self.location.origin;
    const urlToOpen = event.notification.data?.url || `${baseUrl}/pages/outlet_dashboard.php`;
    
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (const client of clientList) {
                if (client.url.includes('outlet_dashboard.php') && 'focus' in client) {
                    return client.focus().then(() => {
                        if (client.navigate) {
                            return client.navigate(urlToOpen);
                        }
                    });
                }
            }
            
            if (clients.openWindow) {
                return clients.openWindow(urlToOpen);
            }
        })
    );
});

console.log('[Manager SW] Service Worker loaded - Push notifications ready');
    const { request } = event;
    const url = new URL(request.url);
    
    
    if (request.method !== 'GET') {
        return;
    }
    
    
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
        
        if (url.pathname.includes('/api/')) {
            return await networkFirstStrategy(request, DATA_CACHE_NAME);
        }
        
        
        if (isStaticAsset(url.pathname)) {
            return await cacheFirstStrategy(request, CACHE_NAME);
        }
        
        
        return await networkFirstStrategy(request, CACHE_NAME);
        
    } catch (error) {
        console.error('[SW] Fetch error:', error);
        
        
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        
        if (request.mode === 'navigate') {
            return createOfflinePage();
        }
        
        
        if (url.pathname.includes('/api/')) {
            return createOfflineApiResponse();
        }
        
        return new Response('Offline', { status: 503, statusText: 'Service Unavailable' });
    }
}

async function networkFirstStrategy(request, cacheName) {
    try {
        const networkResponse = await fetch(request);
        
        
        if (networkResponse && networkResponse.status === 200) {
            const cache = await caches.open(cacheName);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            console.log('[SW] Serving from cache:', request.url);
            return cachedResponse;
        }
        throw error;
    }
}

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

function isStaticAsset(pathname) {
    const staticExtensions = ['.css', '.js', '.png', '.jpg', '.jpeg', '.gif', '.svg', '.ico', '.woff', '.woff2', '.ttf'];
    return staticExtensions.some(ext => pathname.endsWith(ext));
}

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
                
            }
        }
    } catch (error) {
        console.error('[SW] Error in syncPendingParcels:', error);
    }
}

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

async function syncAllPendingOperations() {
    await syncPendingParcels();
    await syncPendingTrips();
}

function openDatabase() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('outlet-app-offline-db', 1);
        
        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);
        
        request.onupgradeneeded = (event) => {
            const db = event.target.result;
            
            
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

self.addEventListener('message', (event) => {
    console.log('[SW] Message received:', event.data);
    
    if (event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    } else if (event.data.type === 'SYNC_NOW') {
        syncAllPendingOperations();
    }
});

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

self.addEventListener('notificationclick', (event) => {
    console.log('[SW] Notification clicked:', event.action);
    
    event.notification.close();
    
    const baseUrl = self.location.origin;
    const urlToOpen = event.notification.data?.url || `${baseUrl}/pages/outlet_dashboard.php`;
    
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            
            for (const client of clientList) {
                if (client.url.includes('/outlet-app/') && 'focus' in client) {
                    return client.focus().then(() => {
                        
                        if (client.navigate) {
                            return client.navigate(urlToOpen);
                        }
                    });
                }
            }
            
            
            if (clients.openWindow) {
                return clients.openWindow(urlToOpen);
            }
        })
    );
});

console.log('[SW] Service Worker script loaded');
