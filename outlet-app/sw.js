

const CACHE_NAME = 'outlet-manager-v1.0.0';

self.addEventListener('install', (event) => {
    console.log('Outlet Manager SW: Installing...');
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    console.log('Outlet Manager SW: Activating...');
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', (event) => {
    console.log('Outlet Manager SW: Push notification received');
    
    if (!event.data) {
        return;
    }
    
    try {
        const data = event.data.json();
        
        const options = {
            body: data.body || data.message || 'New notification',
            icon: data.icon || '/outlet-app/icons/icon-192x192.png',
            badge: data.badge || '/outlet-app/icons/icon-72x72.png',
            data: data.data || {},
            vibrate: data.vibrate || [200, 100, 200],
            tag: data.tag || 'manager-notification',
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
        console.error('Outlet Manager SW: Error showing notification:', error);
    }
});

self.addEventListener('notificationclick', (event) => {
    console.log('Outlet Manager SW: Notification clicked', event.action);
    
    event.notification.close();
    
    const baseUrl = self.location.origin;
    const urlToOpen = event.notification.data?.url || `${baseUrl}/pages/outlet_dashboard.php`;
    
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            
            for (const client of clientList) {
                if (client.url.includes('/outlet-app/') && 'focus' in client) {
                    return client.focus().then(() => client.navigate(urlToOpen));
                }
            }
            
            
            if (clients.openWindow) {
                return clients.openWindow(urlToOpen);
            }
        })
    );
});

console.log('Outlet Manager SW: Service Worker loaded successfully');
