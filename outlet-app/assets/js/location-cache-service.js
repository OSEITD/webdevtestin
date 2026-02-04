

class LocationCacheService {
    constructor(driverId, options = {}) {
        this.driverId = driverId;
        this.options = {
            cacheKey: `driver_location_${driverId}`,
            maxCacheAge: 30 * 60 * 1000, 
            fallbackToServer: true,
            ...options
        };
        
        this.cache = this.loadFromStorage();
        console.log('🗄️ Location cache service initialized for driver:', driverId);
    }
    
    
    cacheLocation(location) {
        const cachedLocation = {
            ...location,
            cached_at: new Date().toISOString(),
            driver_id: this.driverId,
            cache_source: 'gps'
        };
        
        this.cache = cachedLocation;
        this.saveToStorage(cachedLocation);
        
        console.log('💾 Location cached:', this.formatCoordinates(location.latitude, location.longitude));
        return cachedLocation;
    }
    
    
    getCachedLocation() {
        if (!this.cache || !this.cache.cached_at) {
            console.log('📭 No cached location available');
            return null;
        }
        
        const cacheAge = Date.now() - new Date(this.cache.cached_at).getTime();
        const isExpired = cacheAge > this.options.maxCacheAge;
        
        if (isExpired) {
            console.log('⏰ Cached location expired (age:', Math.round(cacheAge / 60000), 'minutes)');
            this.clearCache();
            return null;
        }
        
        console.log('✅ Using cached location (age:', Math.round(cacheAge / 60000), 'minutes)');
        return {
            ...this.cache,
            source: 'cache',
            cache_age_minutes: Math.round(cacheAge / 60000)
        };
    }
    
    
    async getFallbackLocation() {
        console.log('🔄 Getting fallback location...');
        
        
        const cachedLocation = this.getCachedLocation();
        if (cachedLocation) {
            return {
                ...cachedLocation,
                fallback_reason: 'cached_location'
            };
        }
        
        
        if (this.options.fallbackToServer) {
            try {
                const serverLocation = await this.getServerLocation();
                if (serverLocation) {
                    return {
                        ...serverLocation,
                        source: 'server_cache',
                        fallback_reason: 'server_location'
                    };
                }
            } catch (error) {
                console.warn('⚠️ Failed to get server location:', error.message);
            }
        }
        
        
        console.log('🏠 Using default Zambian location');
        return {
            latitude: -15.3875,
            longitude: 28.3228,
            accuracy: null,
            speed: null,
            heading: null,
            timestamp: new Date().toISOString(),
            source: 'default',
            fallback_reason: 'default_zambian',
            city: 'Lusaka',
            country: 'Zambia',
            nearest_city: {
                name: 'Lusaka',
                distance: 0,
                province: 'Lusaka'
            }
        };
    }
    
    
    async getServerLocation() {
        try {
            console.log('📡 Fetching last known location from server...');
            
            const response = await fetch(`api/get_driver_location.php?driver_id=${this.driverId}&action=last_known`);
            const data = await response.json();
            
            if (data.success && data.location) {
                const location = data.location;
                console.log('🎯 Server location found:', this.formatCoordinates(location.latitude, location.longitude));
                
                
                this.cacheLocation({
                    latitude: parseFloat(location.latitude),
                    longitude: parseFloat(location.longitude),
                    accuracy: location.accuracy ? parseFloat(location.accuracy) : null,
                    speed: location.speed ? parseFloat(location.speed) : null,
                    heading: location.heading ? parseFloat(location.heading) : null,
                    timestamp: location.timestamp || new Date().toISOString()
                });
                
                return {
                    latitude: parseFloat(location.latitude),
                    longitude: parseFloat(location.longitude),
                    accuracy: location.accuracy ? parseFloat(location.accuracy) : null,
                    speed: location.speed ? parseFloat(location.speed) : null,
                    heading: location.heading ? parseFloat(location.heading) : null,
                    timestamp: location.timestamp || new Date().toISOString(),
                    server_age_minutes: data.age_minutes || 0
                };
            }
            
            console.log('📭 No server location available');
            return null;
            
        } catch (error) {
            console.error('❌ Error fetching server location:', error);
            return null;
        }
    }
    
    
    loadFromStorage() {
        try {
            const cached = localStorage.getItem(this.options.cacheKey);
            if (cached) {
                const location = JSON.parse(cached);
                console.log('📂 Loaded cached location from storage');
                return location;
            }
        } catch (error) {
            console.warn('⚠️ Failed to load cached location:', error);
        }
        return null;
    }
    
    
    saveToStorage(location) {
        try {
            localStorage.setItem(this.options.cacheKey, JSON.stringify(location));
            console.log('💾 Location saved to browser storage');
        } catch (error) {
            console.warn('⚠️ Failed to save location to storage:', error);
        }
    }
    
    
    clearCache() {
        this.cache = null;
        try {
            localStorage.removeItem(this.options.cacheKey);
            console.log('🗑️ Location cache cleared');
        } catch (error) {
            console.warn('⚠️ Failed to clear cache:', error);
        }
    }
    
    
    getCacheStatus() {
        const cachedLocation = this.getCachedLocation();
        
        return {
            has_cache: !!this.cache,
            cache_valid: !!cachedLocation,
            cache_age_minutes: this.cache && this.cache.cached_at ? 
                Math.round((Date.now() - new Date(this.cache.cached_at).getTime()) / 60000) : null,
            cache_location: cachedLocation ? {
                latitude: cachedLocation.latitude,
                longitude: cachedLocation.longitude,
                formatted: this.formatCoordinates(cachedLocation.latitude, cachedLocation.longitude)
            } : null,
            fallback_ready: true
        };
    }
    
    
    updateCacheSettings(newOptions) {
        this.options = { ...this.options, ...newOptions };
        console.log('⚙️ Cache settings updated:', newOptions);
    }
    
    
    formatCoordinates(lat, lng) {
        return `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
    }
    
    
    isValidZambianLocation(latitude, longitude) {
        const bounds = {
            north: -8.2,
            south: -18.1,
            east: 33.7,
            west: 21.9
        };
        
        return (
            latitude >= bounds.south &&
            latitude <= bounds.north &&
            longitude >= bounds.west &&
            longitude <= bounds.east
        );
    }
    
    
    calculateDistance(lat1, lon1, lat2, lon2) {
        const R = 6371; 
        const dLat = this.deg2rad(lat2 - lat1);
        const dLon = this.deg2rad(lon2 - lon1);
        
        const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                  Math.cos(this.deg2rad(lat1)) * Math.cos(this.deg2rad(lat2)) *
                  Math.sin(dLon/2) * Math.sin(dLon/2);
        
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        return R * c;
    }
    
    deg2rad(deg) {
        return deg * (Math.PI / 180);
    }
}

window.LocationCacheService = LocationCacheService;