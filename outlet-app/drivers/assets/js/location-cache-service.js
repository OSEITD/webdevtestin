/**
 * Location Cache Service
 * Handles offline GPS location storage and sync when network returns
 */

class LocationCacheService {
    constructor() {
        this.dbName = 'DriverLocationCache';
        this.storeName = 'locations';
        this.db = null;
        this.syncInProgress = false;
        this.maxCacheSize = 1000; // Maximum cached locations
        this.syncEndpoint = '../api/save-driver-location.php';
        
        this.init();
    }

    /**
     * Initialize IndexedDB for offline storage
     */
    async init() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(this.dbName, 1);
            
            request.onerror = () => {
                console.error('IndexedDB failed to open:', request.error);
                reject(request.error);
            };
            
            request.onsuccess = () => {
                this.db = request.result;
                console.log('Location cache service initialized');
                this.startAutoSync();
                resolve(this.db);
            };
            
            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                
                if (!db.objectStoreNames.contains(this.storeName)) {
                    const objectStore = db.createObjectStore(this.storeName, { 
                        keyPath: 'id', 
                        autoIncrement: true 
                    });
                    
                    objectStore.createIndex('timestamp', 'timestamp', { unique: false });
                    objectStore.createIndex('synced', 'synced', { unique: false });
                    objectStore.createIndex('tripId', 'tripId', { unique: false });
                }
            };
        });
    }

    /**
     * Cache a location for later sync
     */
    async cacheLocation(locationData) {
        if (!this.db) {
            console.warn('Database not initialized, waiting...');
            await this.init();
        }

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.storeName], 'readwrite');
            const objectStore = transaction.objectStore(this.storeName);
            
            const record = {
                ...locationData,
                synced: false,
                cachedAt: new Date().toISOString(),
                retryCount: 0
            };
            
            const request = objectStore.add(record);
            
            request.onsuccess = () => {
                console.log('Location cached offline:', request.result);
                this.cleanupOldCache();
                resolve(request.result);
            };
            
            request.onerror = () => {
                console.error('Failed to cache location:', request.error);
                reject(request.error);
            };
        });
    }

    /**
     * Get all unsynced locations
     */
    async getUnsyncedLocations() {
        if (!this.db) await this.init();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.storeName], 'readonly');
            const objectStore = transaction.objectStore(this.storeName);
            const index = objectStore.index('synced');
            const request = index.getAll(false);
            
            request.onsuccess = () => {
                resolve(request.result);
            };
            
            request.onerror = () => {
                reject(request.error);
            };
        });
    }

    /**
     * Mark location as synced
     */
    async markAsSynced(id) {
        if (!this.db) await this.init();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.storeName], 'readwrite');
            const objectStore = transaction.objectStore(this.storeName);
            const request = objectStore.get(id);
            
            request.onsuccess = () => {
                const record = request.result;
                if (record) {
                    record.synced = true;
                    record.syncedAt = new Date().toISOString();
                    
                    const updateRequest = objectStore.put(record);
                    updateRequest.onsuccess = () => resolve(true);
                    updateRequest.onerror = () => reject(updateRequest.error);
                } else {
                    resolve(false);
                }
            };
            
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Delete synced location
     */
    async deleteSyncedLocation(id) {
        if (!this.db) await this.init();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.storeName], 'readwrite');
            const objectStore = transaction.objectStore(this.storeName);
            const request = objectStore.delete(id);
            
            request.onsuccess = () => resolve(true);
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Attempt to sync cached locations to server
     */
    async syncLocations() {
        if (this.syncInProgress) {
            console.log('Sync already in progress');
            return { success: false, message: 'Sync in progress' };
        }

        if (!navigator.onLine) {
            console.log('No network connection, sync deferred');
            return { success: false, message: 'Offline' };
        }

        this.syncInProgress = true;
        const results = {
            total: 0,
            synced: 0,
            failed: 0,
            errors: []
        };

        try {
            const unsyncedLocations = await this.getUnsyncedLocations();
            results.total = unsyncedLocations.length;

            if (results.total === 0) {
                console.log('No locations to sync');
                this.syncInProgress = false;
                return results;
            }

            console.log(`Syncing ${results.total} cached locations...`);

            for (const location of unsyncedLocations) {
                try {
                    // Remove cache metadata before sending
                    const { id, synced, cachedAt, syncedAt, retryCount, ...locationData } = location;
                    
                    const response = await fetch(this.syncEndpoint, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(locationData)
                    });

                    const result = await response.json();

                    if (result.success) {
                        await this.deleteSyncedLocation(id);
                        results.synced++;
                        console.log(`Location ${id} synced successfully`);
                    } else {
                        results.failed++;
                        results.errors.push({ id, error: result.error });
                        console.error(`Failed to sync location ${id}:`, result.error);
                    }
                } catch (error) {
                    results.failed++;
                    results.errors.push({ id: location.id, error: error.message });
                    console.error(`Error syncing location ${location.id}:`, error);
                }
            }

            console.log('Sync complete:', results);
        } catch (error) {
            console.error('Sync process error:', error);
        } finally {
            this.syncInProgress = false;
        }

        return results;
    }

    /**
     * Save location with automatic fallback to cache
     */
    async saveLocation(locationData) {
        // Try to save directly first if online
        if (navigator.onLine) {
            try {
                const response = await fetch(this.syncEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(locationData)
                });

                const result = await response.json();

                if (result.success) {
                    console.log('Location saved online');
                    // Try to sync any cached locations while we're online
                    this.syncLocations().catch(err => console.error('Background sync failed:', err));
                    return { success: true, mode: 'online', result };
                } else {
                    console.warn('Online save failed, caching:', result.error);
                    await this.cacheLocation(locationData);
                    return { success: true, mode: 'cached', message: result.error };
                }
            } catch (error) {
                console.error('Network error, caching location:', error);
                await this.cacheLocation(locationData);
                return { success: true, mode: 'cached', error: error.message };
            }
        } else {
            // Offline - cache immediately
            console.log('Offline mode - caching location');
            await this.cacheLocation(locationData);
            return { success: true, mode: 'cached', message: 'Saved offline' };
        }
    }

    /**
     * Start automatic sync when online
     */
    startAutoSync() {
        // Sync when coming back online
        window.addEventListener('online', () => {
            console.log('Network connection restored, syncing cached locations...');
            this.syncLocations();
        });

        // Periodic sync every 2 minutes if online
        setInterval(() => {
            if (navigator.onLine && !this.syncInProgress) {
                this.syncLocations();
            }
        }, 120000); // 2 minutes
    }

    /**
     * Clean up old cached locations to prevent database bloat
     */
    async cleanupOldCache() {
        try {
            const transaction = this.db.transaction([this.storeName], 'readwrite');
            const objectStore = transaction.objectStore(this.storeName);
            const countRequest = objectStore.count();

            countRequest.onsuccess = async () => {
                const count = countRequest.result;
                
                if (count > this.maxCacheSize) {
                    // Delete oldest synced locations
                    const index = objectStore.index('timestamp');
                    const cursorRequest = index.openCursor();
                    let deleteCount = count - this.maxCacheSize;

                    cursorRequest.onsuccess = (event) => {
                        const cursor = event.target.result;
                        if (cursor && deleteCount > 0) {
                            if (cursor.value.synced) {
                                cursor.delete();
                                deleteCount--;
                            }
                            cursor.continue();
                        }
                    };
                }
            };
        } catch (error) {
            console.error('Cleanup error:', error);
        }
    }

    /**
     * Get cache statistics
     */
    async getCacheStats() {
        if (!this.db) await this.init();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.storeName], 'readonly');
            const objectStore = transaction.objectStore(this.storeName);
            
            const countRequest = objectStore.count();
            const syncedIndex = objectStore.index('synced');
            const unsyncedRequest = syncedIndex.count(IDBKeyRange.only(false));

            Promise.all([
                new Promise((res) => { countRequest.onsuccess = () => res(countRequest.result); }),
                new Promise((res) => { unsyncedRequest.onsuccess = () => res(unsyncedRequest.result); })
            ]).then(([total, unsynced]) => {
                resolve({
                    total,
                    unsynced,
                    synced: total - unsynced,
                    online: navigator.onLine
                });
            }).catch(reject);
        });
    }
}

// Create global instance
window.locationCacheService = new LocationCacheService();
