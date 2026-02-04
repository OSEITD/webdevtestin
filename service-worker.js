/**
 * Manager Push Notification Service Worker
 * Minimal service worker for push notifications only
 * Version: 2.1 - Fixed icon paths with data URIs
 */// Install - activate immediately
self.addEventListener('install', (event) => {
    console.log('[Manager SW] Installing...');
    self.skipWaiting();
});

// Activate - take control immediately
self.addEventListener('activate', (event) => {
    console.log('[Manager SW] Activating...');
    event.waitUntil(self.clients.claim());
});

// Push notification handler
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
            icon: data.icon || 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text y=".9em" font-size="90">📦</text></svg>',
            badge: data.badge || 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text y=".9em" font-size="90">📦</text></svg>',
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
        
        // Fallback: show a basic notification
        event.waitUntil(
            self.registration.showNotification('New Message', {
                body: 'You have a new notification',
                icon: '/outlet-app/icons/icon-192x192.png'
            })
        );
    }
});

// Notification click handler
self.addEventListener('notificationclick', (event) => {
    console.log('[Manager SW] Notification clicked');
    
    event.notification.close();
    
    const urlToOpen = event.notification.data?.url || '/pages/outlet_dashboard.php';
    
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
