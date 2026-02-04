/**
 * Driver App Service Worker
 * Provides offline functionality and caching for the professional courier app
 */

const CACHE_NAME = 'driver-app-v1.0.1';
const LOCAL_CACHE_URLS = [
    './dashboard.php',
    './assets/css/driver-dashboard.css',
    './assets/js/driver-dashboard.js'
];

// Install event - cache core files
self.addEventListener('install', (event) => {
    console.log('Driver App SW: Installing...');
    
    event.waitUntil(
        caches.open(CACHE_NAME).then(async (cache) => {
            console.log('Driver App SW: Caching core files');
            
            // Cache local files
            try {
                await cache.addAll(LOCAL_CACHE_URLS);
                console.log('Driver App SW: Local files cached successfully');
            } catch (error) {
                console.warn('Driver App SW: Some local files failed to cache:', error);
            }
            
            // Cache external resources separately (don't fail install if they fail)
            const externalUrls = [
                'https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap',
                'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css'
            ];
            
            for (const url of externalUrls) {
                try {
                    await cache.add(url);
                    console.log('Driver App SW: Cached external resource:', url);
                } catch (error) {
                    console.warn('Driver App SW: Failed to cache external resource:', url, error);
                }
            }
        }).catch((error) => {
            console.error('Driver App SW: Cache installation failed:', error);
        })
    );
    
    // Activate immediately
    self.skipWaiting();
});

// Activate event - clean up old caches
self.addEventListener('activate', (event) => {
    console.log('Driver App SW: Activating...');
    
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== CACHE_NAME) {
                        console.log('Driver App SW: Deleting old cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => {
            // Take control of all clients
            return self.clients.claim();
        })
    );
});

// Fetch event - serve from cache when offline
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);
    
    // Skip non-GET requests
    if (request.method !== 'GET') {
        return;
    }
    
    // Skip external domains (except fonts and icons)
    if (url.origin !== location.origin && 
        !url.href.includes('fonts.googleapis.com') && 
        !url.href.includes('cdnjs.cloudflare.com')) {
        return;
    }
    
    event.respondWith(
        handleFetchRequest(request)
    );
});

async function handleFetchRequest(request) {
    const url = new URL(request.url);
    
    try {
        // API requests - try network first, then show offline message
        if (url.pathname.includes('/api/')) {
            return await handleApiRequest(request);
        }
        
        // Static assets - cache first
        if (isStaticAsset(url.pathname)) {
            return await handleStaticAsset(request);
        }
        
        // Pages - network first, fall back to cache
        return await handlePageRequest(request);
        
    } catch (error) {
        console.error('Driver App SW: Fetch error:', error);
        return await getCachedResponse(request) || createOfflineResponse();
    }
}

async function handleApiRequest(request) {
    try {
        // Try network first
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            // Cache successful API responses (for short time)
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        // Network failed, try cache
        const cachedResponse = await getCachedResponse(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // Return offline JSON response
        return new Response(JSON.stringify({
            success: false,
            error: 'Offline - please check your connection',
            offline: true
        }), {
            status: 503,
            headers: {
                'Content-Type': 'application/json'
            }
        });
    }
}

async function handleStaticAsset(request) {
    // Cache first for static assets
    const cachedResponse = await getCachedResponse(request);
    if (cachedResponse) {
        return cachedResponse;
    }
    
    // If not in cache, fetch and cache
    try {
        const networkResponse = await fetch(request);
        if (networkResponse.ok) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, networkResponse.clone());
        }
        return networkResponse;
    } catch (error) {
        console.log('Driver App SW: Static asset unavailable:', request.url);
        return new Response('', { status: 404 });
    }
}

async function handlePageRequest(request) {
    try {
        // Try network first
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            // Cache the page
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        // Network failed, try cache
        const cachedResponse = await getCachedResponse(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // Return offline page
        return createOfflineResponse();
    }
}

async function getCachedResponse(request) {
    const cache = await caches.open(CACHE_NAME);
    return await cache.match(request);
}

function isStaticAsset(pathname) {
    const staticExtensions = ['.css', '.js', '.png', '.jpg', '.jpeg', '.gif', '.svg', '.ico', '.woff', '.woff2'];
    return staticExtensions.some(ext => pathname.endsWith(ext));
}

function createOfflineResponse() {
    const offlineHTML = `
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Offline - Driver App</title>
        <style>
            body {
                font-family: 'Poppins', sans-serif;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                margin: 0;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                text-align: center;
            }
            .offline-container {
                padding: 40px;
                background: rgba(255, 255, 255, 0.1);
                border-radius: 15px;
                backdrop-filter: blur(10px);
                max-width: 400px;
            }
            .offline-icon {
                font-size: 64px;
                margin-bottom: 20px;
            }
            h1 { margin-bottom: 10px; }
            p { margin-bottom: 20px; opacity: 0.9; }
            .retry-btn {
                background: white;
                color: #2563eb;
                border: none;
                padding: 12px 24px;
                border-radius: 8px;
                font-weight: 600;
                cursor: pointer;
                font-size: 16px;
            }
            .retry-btn:hover {
                background: #f3f4f6;
            }
        </style>
    </head>
    <body>
        <div class="offline-container">
            <div class="offline-icon">📡</div>
            <h1>You're Offline</h1>
            <p>Please check your internet connection and try again.</p>
            <button class="retry-btn" onclick="window.location.reload()">
                Retry Connection
            </button>
        </div>
    </body>
    </html>
    `;
    
    return new Response(offlineHTML, {
        headers: {
            'Content-Type': 'text/html'
        }
    });
}

// Background sync for offline actions
self.addEventListener('sync', (event) => {
    console.log('Driver App SW: Background sync triggered:', event.tag);
    
    if (event.tag === 'driver-status-update') {
        event.waitUntil(syncDriverStatus());
    } else if (event.tag === 'delivery-update') {
        event.waitUntil(syncDeliveryUpdates());
    }
});

async function syncDriverStatus() {
    try {
        // Sync pending driver status updates
        const pendingUpdates = await getStoredUpdates('driver-status');
        
        for (const update of pendingUpdates) {
            try {
                const response = await fetch('api/update-driver-status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(update.data)
                });
                
                if (response.ok) {
                    await removeStoredUpdate('driver-status', update.id);
                }
            } catch (error) {
                console.error('Driver App SW: Sync failed for status update:', error);
            }
        }
    } catch (error) {
        console.error('Driver App SW: Driver status sync failed:', error);
    }
}

async function syncDeliveryUpdates() {
    try {
        // Sync pending delivery updates
        const pendingUpdates = await getStoredUpdates('delivery-updates');
        
        for (const update of pendingUpdates) {
            try {
                const response = await fetch('api/update-delivery-status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(update.data)
                });
                
                if (response.ok) {
                    await removeStoredUpdate('delivery-updates', update.id);
                }
            } catch (error) {
                console.error('Driver App SW: Sync failed for delivery update:', error);
            }
        }
    } catch (error) {
        console.error('Driver App SW: Delivery updates sync failed:', error);
    }
}

// Helper functions for IndexedDB operations
async function getStoredUpdates(store) {
    // This would integrate with IndexedDB to store/retrieve offline updates
    // For now, return empty array
    return [];
}

async function removeStoredUpdate(store, id) {
    // This would remove the synced update from IndexedDB
    // Implementation depends on IndexedDB setup
}

// Push notifications for delivery updates
self.addEventListener('push', (event) => {
    console.log('Driver App SW: Push notification received');
    
    if (!event.data) {
        return;
    }
    
    const data = event.data.json();
    const options = {
        body: data.body,
        icon: data.icon || '/outlet-app/icons/icon-192x192.png',
        badge: data.badge || '/outlet-app/icons/icon-72x72.png',
        data: data.data,
        actions: data.actions || [
            {
                action: 'view',
                title: 'View Details'
            },
            {
                action: 'dismiss',
                title: 'Dismiss'
            }
        ],
        vibrate: data.vibrate || [200, 100, 200],
        tag: data.tag || 'notification',
        requireInteraction: data.requireInteraction || false,
        silent: false,
        renotify: true,
        timestamp: Date.now()
    };
    
    event.waitUntil(
        self.registration.showNotification(data.title, options)
    );
});

// Listen for pushsubscriptionchange to handle expired subscriptions
self.addEventListener('pushsubscriptionchange', (event) => {
    console.log('[Driver SW] Push subscription expired or changed');
    console.log('[Driver SW] Old subscription:', event.oldSubscription ? 'exists' : 'none');
    console.log('[Driver SW] New subscription:', event.newSubscription ? 'exists' : 'none');
    
    event.waitUntil(
        (async () => {
            try {
                // Only remove old subscription if it exists and has an endpoint
                if (event.oldSubscription && event.oldSubscription.endpoint) {
                    console.log('[Driver SW] Removing old subscription from server');
                    await fetch('api/push/remove_subscription.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            endpoint: event.oldSubscription.endpoint
                        })
                    }).catch(err => {
                        console.error('[Driver SW] Failed to remove old subscription:', err);
                    });
                }
                
                // Use newSubscription if provided, otherwise create a new one
                let newSubscription = event.newSubscription;
                
                if (!newSubscription) {
                    console.log('[Driver SW] Creating new subscription');
                    newSubscription = await self.registration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: event.oldSubscription ? 
                            event.oldSubscription.options.applicationServerKey : 
                            urlBase64ToUint8Array('BIH-erDsh-yK9B_aJuglh52uhmz2V8otvRUZ_a7rUp2vYtgxVNWXs5ZsfmOD_RNz3ATgGVbBGnxwzH0AGnwvlh8')
                    });
                }
                
                // Send new subscription to server
                console.log('[Driver SW] Saving new subscription to server');
                const subscriptionJSON = newSubscription.toJSON();
                await fetch('api/push/save_subscription.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        subscription: subscriptionJSON,
                        endpoint: subscriptionJSON.endpoint,
                        keys: subscriptionJSON.keys
                    })
                });
                
                console.log('[Driver SW] Push subscription renewed successfully');
            } catch (error) {
                console.error('[Driver SW] Error renewing push subscription:', error);
            }
        })()
    );
});

// Helper function for converting VAPID key
function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding)
        .replace(/\-/g, '+')
        .replace(/_/g, '/');
    const rawData = atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

// Handle notification clicks
self.addEventListener('notificationclick', (event) => {
    console.log('[Driver SW] Notification clicked');
    console.log('[Driver SW] Action:', event.action);
    console.log('[Driver SW] Notification data:', event.notification.data);
    
    event.notification.close();
    
    // Handle dismiss action
    if (event.action === 'dismiss') {
        console.log('[Driver SW] Dismiss action, closing notification');
        return;
    }
    
    // Handle view action or default click (no action)
    if (event.action === 'view' || !event.action) {
        const baseUrl = self.location.origin;
        const urlToOpen = event.notification.data?.url || `${baseUrl}/drivers/dashboard.php`;
        
        console.log('[Driver SW] Opening URL:', urlToOpen);
        
        event.waitUntil(
            clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
                console.log('[Driver SW] Found', clientList.length, 'clients');
                
                // Check if dashboard is already open
                for (const client of clientList) {
                    console.log('[Driver SW] Checking client:', client.url);
                    if (client.url.includes('/drivers/') && 'focus' in client) {
                        console.log('[Driver SW] Found existing driver window, focusing and navigating');
                        return client.focus().then(() => {
                            if (client.navigate) {
                                return client.navigate(urlToOpen);
                            }
                        });
                    }
                }
                
                // No existing client found, open new window
                console.log('[Driver SW] No existing window found, opening new one');
                if (clients.openWindow) {
                    return clients.openWindow(urlToOpen);
                }
            })
        );
    }
});

console.log('Driver App SW: Service Worker loaded successfully');