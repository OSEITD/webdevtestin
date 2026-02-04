

self.addEventListener('install', (event) => {
    console.log('[Manager SW] Installing v2.2...');
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    console.log('[Manager SW] Activating v2.2...');
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
        
        
        event.waitUntil(
            self.registration.showNotification('New Message', {
                body: 'You have a new notification',
                icon: '/outlet-app/icons/icon-192x192.png'
            })
        );
    }
});

self.addEventListener('notificationclick', (event) => {
    console.log('[Manager SW] Notification clicked:', event.action, event.notification.data);
    
    event.notification.close();
    
    const notificationData = event.notification.data || {};
    const action = event.action;
    
    
    if (notificationData.type === 'trip_assignment' || notificationData.url?.includes('/drivers/')) {
        console.log('[Manager SW] This is a driver notification, ignoring...');
        return;
    }
    
    const baseUrl = self.location.origin;
    let urlToOpen = `${baseUrl}/pages/outlet_dashboard.php`;
    
    
    switch (action) {
        case 'accept_trip':
            if (notificationData.trip_id) {
                urlToOpen = `${baseUrl}/pages/manager_trips.php?accept=${notificationData.trip_id}`;
            }
            break;
            
        case 'view_trip':
        case 'view':
            if (notificationData.trip_id) {
                urlToOpen = notificationData.url || `${baseUrl}/pages/manager_trips.php`;
            }
            break;
            
        case 'track_trip':
            if (notificationData.trip_id) {
                urlToOpen = `${baseUrl}/pages/trip_tracking.php?trip_id=${notificationData.trip_id}`;
            }
            break;
            
        case 'dismiss':
            
            return;
            
        default:
            
            urlToOpen = notificationData.url || urlToOpen;
            if (notificationData.trip_id && !notificationData.url) {
                urlToOpen = `${baseUrl}/pages/manager_trips.php`;
            }
            break;
    }
    
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

console.log('[Manager SW] Service Worker loaded - Push notifications ready');
