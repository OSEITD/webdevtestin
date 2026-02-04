

class OfflineSyncManager {
    constructor() {
        this.dbName = 'outlet-app-offline-db';
        this.dbVersion = 1;
        this.db = null;
        this.isOnline = navigator.onLine;
        this.syncInProgress = false;
        
        this.init();
    }
    
    async init() {
        
        window.addEventListener('online', () => this.handleOnline());
        window.addEventListener('offline', () => this.handleOffline());
        
        
        await this.openDatabase();
        
        
        if ('serviceWorker' in navigator) {
            try {
                const registration = await navigator.serviceWorker.register('/outlet-app/service-worker.js', {
                    scope: '/outlet-app/'
                });
                console.log('[OfflineSync] Service worker registered:', registration.scope);
                
                
                registration.addEventListener('updatefound', () => {
                    console.log('[OfflineSync] Service worker update found');
                });
            } catch (error) {
                console.error('[OfflineSync] Service worker registration failed:', error);
            }
        }
        
        
        if (this.isOnline) {
            await this.syncAll();
        }
    }
    
    async openDatabase() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(this.dbName, this.dbVersion);
            
            request.onerror = () => {
                console.error('[OfflineSync] Database open error:', request.error);
                reject(request.error);
            };
            
            request.onsuccess = () => {
                this.db = request.result;
                console.log('[OfflineSync] Database opened successfully');
                resolve(this.db);
            };
            
            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                
                
                if (!db.objectStoreNames.contains('pending-parcels')) {
                    const parcelStore = db.createObjectStore('pending-parcels', { 
                        keyPath: 'id', 
                        autoIncrement: true 
                    });
                    parcelStore.createIndex('timestamp', 'timestamp', { unique: false });
                }
                
                if (!db.objectStoreNames.contains('pending-trips')) {
                    const tripStore = db.createObjectStore('pending-trips', { 
                        keyPath: 'id', 
                        autoIncrement: true 
                    });
                    tripStore.createIndex('timestamp', 'timestamp', { unique: false });
                }
                
                if (!db.objectStoreNames.contains('pending-operations')) {
                    const opsStore = db.createObjectStore('pending-operations', { 
                        keyPath: 'id', 
                        autoIncrement: true 
                    });
                    opsStore.createIndex('timestamp', 'timestamp', { unique: false });
                    opsStore.createIndex('type', 'type', { unique: false });
                }
                
                console.log('[OfflineSync] Database schema created');
            };
        });
    }
    
    handleOnline() {
        console.log('[OfflineSync] Connection restored');
        this.isOnline = true;
        
        
        this.showNotification('✅ Back Online', 'Syncing pending operations...', 'success');
        
        
        this.syncAll();
    }
    
    handleOffline() {
        console.log('[OfflineSync] Connection lost');
        this.isOnline = false;
        
        
        this.showNotification('📡 Offline Mode', 'Your changes will be synced when connection is restored.', 'warning');
    }
    
    
    async queueParcel(parcelData) {
        try {
            const item = {
                data: parcelData,
                timestamp: Date.now(),
                type: 'create_parcel',
                endpoint: '/outlet-app/api/parcels/create_parcel.php'
            };
            
            const id = await this.addItem('pending-parcels', item);
            console.log('[OfflineSync] Parcel queued for sync:', id);
            
            
            if ('serviceWorker' in navigator && 'sync' in registration) {
                const registration = await navigator.serviceWorker.ready;
                await registration.sync.register('sync-parcels');
                console.log('[OfflineSync] Background sync registered');
            }
            
            return { success: true, queued: true, id };
        } catch (error) {
            console.error('[OfflineSync] Error queuing parcel:', error);
            throw error;
        }
    }
    
    
    async queueTripOperation(operation, data, endpoint) {
        try {
            const item = {
                data: data,
                timestamp: Date.now(),
                type: operation,
                endpoint: endpoint
            };
            
            const id = await this.addItem('pending-trips', item);
            console.log('[OfflineSync] Trip operation queued for sync:', operation, id);
            
            
            if ('serviceWorker' in navigator && 'sync' in registration) {
                const registration = await navigator.serviceWorker.ready;
                await registration.sync.register('sync-trips');
            }
            
            return { success: true, queued: true, id };
        } catch (error) {
            console.error('[OfflineSync] Error queuing trip operation:', error);
            throw error;
        }
    }
    
    
    async queueOperation(type, data, endpoint) {
        try {
            const item = {
                data: data,
                timestamp: Date.now(),
                type: type,
                endpoint: endpoint
            };
            
            const id = await this.addItem('pending-operations', item);
            console.log('[OfflineSync] Operation queued for sync:', type, id);
            
            
            if ('serviceWorker' in navigator && 'sync' in registration) {
                const registration = await navigator.serviceWorker.ready;
                await registration.sync.register('sync-all');
            }
            
            return { success: true, queued: true, id };
        } catch (error) {
            console.error('[OfflineSync] Error queuing operation:', error);
            throw error;
        }
    }
    
    
    addItem(storeName, item) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(storeName, 'readwrite');
            const store = transaction.objectStore(storeName);
            const request = store.add(item);
            
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }
    
    
    getPendingItems(storeName) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(storeName, 'readonly');
            const store = transaction.objectStore(storeName);
            const request = store.getAll();
            
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }
    
    
    removeItem(storeName, id) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(storeName, 'readwrite');
            const store = transaction.objectStore(storeName);
            const request = store.delete(id);
            
            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    }
    
    
    async syncAll() {
        if (this.syncInProgress) {
            console.log('[OfflineSync] Sync already in progress');
            return;
        }
        
        if (!this.isOnline) {
            console.log('[OfflineSync] Cannot sync while offline');
            return;
        }
        
        this.syncInProgress = true;
        
        try {
            await this.syncPendingParcels();
            await this.syncPendingTrips();
            await this.syncPendingOperations();
            
            console.log('[OfflineSync] All pending items synced');
            this.showNotification('✅ Sync Complete', 'All pending operations have been synced.', 'success');
        } catch (error) {
            console.error('[OfflineSync] Sync error:', error);
            this.showNotification('⚠️ Sync Warning', 'Some operations could not be synced.', 'warning');
        } finally {
            this.syncInProgress = false;
        }
    }
    
    async syncPendingParcels() {
        const parcels = await this.getPendingItems('pending-parcels');
        console.log(`[OfflineSync] Syncing ${parcels.length} pending parcels`);
        
        for (const item of parcels) {
            try {
                const response = await fetch(item.endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(item.data)
                });
                
                const result = await response.json();
                
                if (response.ok && result.success) {
                    await this.removeItem('pending-parcels', item.id);
                    console.log('[OfflineSync] Parcel synced successfully:', item.id);
                    
                    
                    this.showNotification(
                        '✅ Parcel Created',
                        `Tracking: ${result.trackingNumber || 'N/A'}`,
                        'success'
                    );
                } else {
                    console.error('[OfflineSync] Parcel sync failed:', result.error);
                }
            } catch (error) {
                console.error('[OfflineSync] Error syncing parcel:', error);
                
            }
        }
    }
    
    async syncPendingTrips() {
        const trips = await this.getPendingItems('pending-trips');
        console.log(`[OfflineSync] Syncing ${trips.length} pending trip operations`);
        
        for (const item of trips) {
            try {
                const response = await fetch(item.endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(item.data)
                });
                
                const result = await response.json();
                
                if (response.ok && result.success) {
                    await this.removeItem('pending-trips', item.id);
                    console.log('[OfflineSync] Trip operation synced successfully:', item.id);
                } else {
                    console.error('[OfflineSync] Trip sync failed:', result.error);
                }
            } catch (error) {
                console.error('[OfflineSync] Error syncing trip operation:', error);
            }
        }
    }
    
    async syncPendingOperations() {
        const operations = await this.getPendingItems('pending-operations');
        console.log(`[OfflineSync] Syncing ${operations.length} pending operations`);
        
        for (const item of operations) {
            try {
                const response = await fetch(item.endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(item.data)
                });
                
                const result = await response.json();
                
                if (response.ok && result.success) {
                    await this.removeItem('pending-operations', item.id);
                    console.log('[OfflineSync] Operation synced successfully:', item.type, item.id);
                } else {
                    console.error('[OfflineSync] Operation sync failed:', result.error);
                }
            } catch (error) {
                console.error('[OfflineSync] Error syncing operation:', error);
            }
        }
    }
    
    
    async getPendingCount() {
        try {
            const parcels = await this.getPendingItems('pending-parcels');
            const trips = await this.getPendingItems('pending-trips');
            const operations = await this.getPendingItems('pending-operations');
            
            return {
                parcels: parcels.length,
                trips: trips.length,
                operations: operations.length,
                total: parcels.length + trips.length + operations.length
            };
        } catch (error) {
            console.error('[OfflineSync] Error getting pending count:', error);
            return { parcels: 0, trips: 0, operations: 0, total: 0 };
        }
    }
    
    
    showNotification(title, message, type = 'info') {
        
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification(title, {
                body: message,
                icon: '/outlet-app/icons/icon-192x192.png',
                badge: '/outlet-app/icons/icon-72x72.png',
                tag: 'offline-sync'
            });
        }
        
        
        this.showInPageNotification(title, message, type);
    }
    
    
    showInPageNotification(title, message, type) {
        
        const existing = document.getElementById('offline-sync-notification');
        if (existing) {
            existing.remove();
        }
        
        const colors = {
            success: { bg: '#d1fae5', border: '#10b981', text: '#065f46' },
            warning: { bg: '#fef3c7', border: '#f59e0b', text: '#92400e' },
            error: { bg: '#fee2e2', border: '#ef4444', text: '#991b1b' },
            info: { bg: '#dbeafe', border: '#3b82f6', text: '#1e40af' }
        };
        
        const color = colors[type] || colors.info;
        
        const notification = document.createElement('div');
        notification.id = 'offline-sync-notification';
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${color.bg};
            border: 2px solid ${color.border};
            color: ${color.text};
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 10000;
            max-width: 350px;
            animation: slideIn 0.3s ease-out;
        `;
        
        notification.innerHTML = `
            <style>
                @keyframes slideIn {
                    from {
                        transform: translateX(400px);
                        opacity: 0;
                    }
                    to {
                        transform: translateX(0);
                        opacity: 1;
                    }
                }
            </style>
            <strong style="display: block; margin-bottom: 5px;">${title}</strong>
            <div style="font-size: 0.9rem;">${message}</div>
        `;
        
        document.body.appendChild(notification);
        
        
        setTimeout(() => {
            notification.style.animation = 'slideIn 0.3s ease-out reverse';
            setTimeout(() => notification.remove(), 300);
        }, 5000);
    }
    
    
    checkOnlineStatus() {
        return navigator.onLine;
    }
}

const offlineSyncManager = new OfflineSyncManager();

if (typeof module !== 'undefined' && module.exports) {
    module.exports = OfflineSyncManager;
}
