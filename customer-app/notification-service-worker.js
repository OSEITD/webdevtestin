
// Install event
self.addEventListener('install', function(event) {
    console.log('[Customer SW] Installing...');
    self.skipWaiting();
});

// Activate event
self.addEventListener('activate', function(event) {
    console.log('[Customer SW] Activating...');
    event.waitUntil(self.clients.claim());
});
// Activate event
self.addEventListener('activate', function(event) {
    console.log('[Customer SW] Activating...');
    event.waitUntil(self.clients.claim());
});

// Push notification handler
self.addEventListener('push', function(event) {
    console.log('[Customer SW] Push Received.', event);
    
    if (!event.data) {
        console.warn('[Customer SW] Push event has no data');
        return;
    }
    
    try {
        const data = event.data.json();
        console.log('[Customer SW] Push data:', data);
        
        const notificationData = {
            body: data.body || data.message || 'Your parcel status has been updated',
            icon: data.icon || 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text y=".9em" font-size="90">📦</text></svg>',
            badge: data.badge || 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text y=".9em" font-size="90">📦</text></svg>',
            tag: data.tag || 'parcel-update',
            requireInteraction: true,
            vibrate: [200, 100, 200, 100, 200],
            data: data.data || {},
            timestamp: Date.now()
        };
        
        if (data.actions) {
            notificationData.actions = data.actions;
        }
        
        console.log('[Customer SW] Showing notification:', data.title);
        
        event.waitUntil(
            self.registration.showNotification(data.title || 'Parcel Update', notificationData)
                .then(() => console.log('[Customer SW] Notification shown successfully'))
                .catch(err => console.error('[Customer SW] Failed to show notification:', err))
        );
    } catch (error) {
        console.error('[Customer SW] Error parsing push data:', error);
        
        // Fallback notification
        event.waitUntil(
            self.registration.showNotification('Parcel Update', {
                body: 'Your parcel status has been updated',
                icon: 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text y=".9em" font-size="90">📦</text></svg>'
            })
        );
    }
});


// Notification click handler
self.addEventListener('notificationclick', function(event) {
    console.log('[Customer SW] Notification click:', event);
    
    event.notification.close();
    
    const trackingNumber = event.notification.data?.tracking_number;
    const url = trackingNumber 
        ? `/customer-app/track_parcel.php?track=${trackingNumber}`
        : '/customer-app/track_parcel.php';
    
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            // Checking if tracking page is already open
            for (const client of clientList) {
                if (client.url.includes('track_parcel') && 'focus' in client) {
                    return client.focus().then(() => {
                        if (client.navigate) {
                            return client.navigate(url);
                        }
                    });
                }
            }
            
           
            if (clients.openWindow) {
                return clients.openWindow(url);
            }
        })
    );
});

console.log('[Customer SW] Service Worker loaded - Push notifications ready');