

class ProfessionalGPSTracker {
    constructor(options = {}) {
        
        this.DEFAULT_COORDINATES = {
            latitude: -15.3875,
            longitude: 28.3228,
            city: 'Lusaka',
            country: 'Zambia'
        };
        
        
        this.ZAMBIAN_BOUNDS = {
            north: -8.2,
            south: -18.1,
            east: 33.7,
            west: 21.9
        };
        
        this.options = {
            enableHighAccuracy: true,
            timeout: 15000,
            maximumAge: 5000,
            retryAttempts: 3,
            retryDelay: 2000,
            fallbackToDefault: true,
            validateBounds: true,
            enableLocationCache: true,
            driverId: null, 
            ...options
        };
        
        this.currentPosition = null;
        this.watchId = null;
        this.retryCount = 0;
        this.locationCache = null;
        
        this.callbacks = {
            onSuccess: [],
            onError: [],
            onFallback: []
        };
        
        
        if (this.options.enableLocationCache) {
            this.initializeLocationCache();
        }
        
        console.log('🌍 Professional GPS Tracker initialized for Zambia with location caching');
    }
    
    
    initializeLocationCache() {
        try {
            
            const driverId = this.options.driverId || this.getDriverIdFromSession();
            
            if (driverId) {
                this.locationCache = new LocationCacheService(driverId, {
                    maxCacheAge: 30 * 60 * 1000, 
                    fallbackToServer: true
                });
                console.log('🗄️ Location cache initialized for driver:', driverId);
            } else {
                console.warn('⚠️ No driver ID available, location caching disabled');
                this.options.enableLocationCache = false;
            }
        } catch (error) {
            console.warn('⚠️ Failed to initialize location cache:', error);
            this.options.enableLocationCache = false;
        }
    }
    
    
    getDriverIdFromSession() {
        
        if (window.driverSession && window.driverSession.user_id) {
            return window.driverSession.user_id;
        }
        
        if (sessionStorage.getItem('driver_id')) {
            return sessionStorage.getItem('driver_id');
        }
        
        if (localStorage.getItem('driver_id')) {
            return localStorage.getItem('driver_id');
        }
        
        
        const driverMeta = document.querySelector('meta[name="driver-id"]');
        if (driverMeta) {
            return driverMeta.content;
        }
        
        return null;
    }
    
    
    async getCurrentLocation(options = {}) {
        const config = { ...this.options, ...options };
        
        return new Promise((resolve, reject) => {
            
            if (!navigator.geolocation) {
                console.warn('📍 Geolocation not supported, using default Zambian location');
                const fallbackLocation = this.getFallbackLocation();
                this.triggerCallbacks('onFallback', fallbackLocation);
                resolve(fallbackLocation);
                return;
            }
            
            this.attemptLocationAccess(config, resolve, reject, 1);
        });
    }
    
    
    attemptLocationAccess(config, resolve, reject, attemptNumber) {
        console.log(`📡 GPS attempt ${attemptNumber}/${config.retryAttempts}`);
        
        
        const geoOptions = {
            enableHighAccuracy: attemptNumber === 1 ? config.enableHighAccuracy : false,
            timeout: attemptNumber === 1 ? config.timeout : config.timeout / 2,
            maximumAge: attemptNumber === 1 ? config.maximumAge : config.maximumAge * 2
        };
        
        navigator.geolocation.getCurrentPosition(
            (position) => {
                console.log('✅ GPS location obtained:', position.coords);
                const locationData = this.processLocationData(position, config);
                
                if (locationData.valid) {
                    this.currentPosition = locationData;
                    
                    
                    if (this.locationCache) {
                        this.locationCache.cacheLocation(locationData);
                    }
                    
                    this.triggerCallbacks('onSuccess', locationData);
                    resolve(locationData);
                } else {
                    console.warn('⚠️ Location outside Zambian bounds, using fallback');
                    this.getFallbackLocation().then(fallbackLocation => {
                        this.triggerCallbacks('onFallback', fallbackLocation);
                        resolve(fallbackLocation);
                    });
                }
            },
            (error) => {
                console.error(`❌ GPS attempt ${attemptNumber} failed:`, error);
                
                if (attemptNumber < config.retryAttempts) {
                    setTimeout(() => {
                        this.attemptLocationAccess(config, resolve, reject, attemptNumber + 1);
                    }, config.retryDelay);
                } else {
                    this.triggerCallbacks('onError', error);
                    
                    if (config.fallbackToDefault) {
                        console.log('🏠 Using intelligent fallback after GPS failure');
                        this.getFallbackLocation().then(fallbackLocation => {
                            this.triggerCallbacks('onFallback', fallbackLocation);
                            resolve(fallbackLocation);
                        });
                    } else {
                        reject(error);
                    }
                }
            },
            geoOptions
        );
    }
    
    
    processLocationData(position, config) {
        const coords = position.coords;
        const isValid = config.validateBounds ? 
            this.isWithinZambia(coords.latitude, coords.longitude) : true;
        
        const locationData = {
            latitude: coords.latitude,
            longitude: coords.longitude,
            accuracy: coords.accuracy,
            speed: coords.speed,
            heading: coords.heading,
            timestamp: new Date().toISOString(),
            valid: isValid,
            source: 'gps',
            accuracy_level: this.getAccuracyLevel(coords.accuracy),
            nearest_city: isValid ? this.findNearestZambianCity(coords.latitude, coords.longitude) : null
        };
        
        console.log('📍 Processed location:', locationData);
        return locationData;
    }
    
    
    isWithinZambia(latitude, longitude) {
        return (
            latitude >= this.ZAMBIAN_BOUNDS.south &&
            latitude <= this.ZAMBIAN_BOUNDS.north &&
            longitude >= this.ZAMBIAN_BOUNDS.west &&
            longitude <= this.ZAMBIAN_BOUNDS.east
        );
    }
    
    
    async getFallbackLocation() {
        console.log('🔄 Getting intelligent fallback location...');
        
        
        if (this.locationCache) {
            try {
                const fallbackLocation = await this.locationCache.getFallbackLocation();
                if (fallbackLocation) {
                    console.log('🎯 Using cached fallback location:', this.formatCoordinates(fallbackLocation.latitude, fallbackLocation.longitude));
                    return {
                        ...fallbackLocation,
                        valid: true,
                        accuracy_level: fallbackLocation.accuracy ? this.getAccuracyLevel(fallbackLocation.accuracy) : 'cached'
                    };
                }
            } catch (error) {
                console.warn('⚠️ Cache fallback failed:', error);
            }
        }
        
        
        console.log('🏠 Using default Zambian location');
        return {
            latitude: this.DEFAULT_COORDINATES.latitude,
            longitude: this.DEFAULT_COORDINATES.longitude,
            accuracy: null,
            speed: null,
            heading: null,
            timestamp: new Date().toISOString(),
            valid: true,
            source: 'default',
            accuracy_level: 'default',
            city: this.DEFAULT_COORDINATES.city,
            country: this.DEFAULT_COORDINATES.country,
            nearest_city: {
                name: 'Lusaka',
                distance: 0,
                province: 'Lusaka'
            },
            fallback_reason: 'default_zambian'
        };
    }
    
    
    formatCoordinates(lat, lng) {
        return `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
    }
    
    
    getAccuracyLevel(accuracy) {
        if (!accuracy) return 'unknown';
        if (accuracy <= 50) return 'high';
        if (accuracy <= 200) return 'medium';
        if (accuracy <= 1000) return 'low';
        return 'very_low';
    }
    
    
    findNearestZambianCity(latitude, longitude) {
        const cities = {
            'Lusaka': { lat: -15.3875, lng: 28.3228, province: 'Lusaka' },
            'Kitwe': { lat: -12.8024, lng: 28.2132, province: 'Copperbelt' },
            'Ndola': { lat: -12.9587, lng: 28.6366, province: 'Copperbelt' },
            'Kabwe': { lat: -14.4469, lng: 28.4464, province: 'Central' },
            'Chingola': { lat: -12.5289, lng: 27.8818, province: 'Copperbelt' },
            'Livingstone': { lat: -17.8419, lng: 25.8561, province: 'Southern' }
        };
        
        let nearestCity = null;
        let shortestDistance = Infinity;
        
        for (const [name, coords] of Object.entries(cities)) {
            const distance = this.calculateDistance(latitude, longitude, coords.lat, coords.lng);
            if (distance < shortestDistance) {
                shortestDistance = distance;
                nearestCity = {
                    name: name,
                    distance: distance,
                    province: coords.province
                };
            }
        }
        
        return nearestCity;
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
    
    
    startTracking(options = {}) {
        if (this.watchId) {
            console.log('📍 GPS tracking already active');
            return;
        }
        
        if (!navigator.geolocation) {
            console.error('🚫 Geolocation not supported');
            return;
        }
        
        const config = { ...this.options, ...options };
        
        this.watchId = navigator.geolocation.watchPosition(
            (position) => {
                const locationData = this.processLocationData(position, config);
                this.currentPosition = locationData;
                
                
                if (this.locationCache && locationData.valid) {
                    this.locationCache.cacheLocation(locationData);
                }
                
                this.triggerCallbacks('onSuccess', locationData);
            },
            (error) => {
                console.error('📍 GPS tracking error:', error);
                this.triggerCallbacks('onError', error);
            },
            {
                enableHighAccuracy: config.enableHighAccuracy,
                timeout: config.timeout,
                maximumAge: config.maximumAge
            }
        );
        
        console.log('🎯 GPS tracking started');
    }
    
    
    stopTracking() {
        if (this.watchId) {
            navigator.geolocation.clearWatch(this.watchId);
            this.watchId = null;
            console.log('⏹️ GPS tracking stopped');
        }
    }
    
    
    on(event, callback) {
        if (this.callbacks[event]) {
            this.callbacks[event].push(callback);
        }
    }
    
    
    off(event, callback) {
        if (this.callbacks[event]) {
            const index = this.callbacks[event].indexOf(callback);
            if (index > -1) {
                this.callbacks[event].splice(index, 1);
            }
        }
    }
    
    
    triggerCallbacks(event, data) {
        if (this.callbacks[event]) {
            this.callbacks[event].forEach(callback => {
                try {
                    callback(data);
                } catch (error) {
                    console.error(`Error in ${event} callback:`, error);
                }
            });
        }
    }
    
    
    getCurrentPosition() {
        return this.currentPosition;
    }
    
    
    formatLocation(location = null) {
        const loc = location || this.currentPosition;
        if (!loc) return 'Location not available';
        
        const coords = `${loc.latitude.toFixed(6)}, ${loc.longitude.toFixed(6)}`;
        const accuracy = loc.accuracy ? ` (±${Math.round(loc.accuracy)}m)` : '';
        const city = loc.nearest_city ? ` near ${loc.nearest_city.name}` : '';
        
        return `${coords}${accuracy}${city}`;
    }
    
    
    getLocationStatus() {
        return {
            hasPosition: !!this.currentPosition,
            isTracking: !!this.watchId,
            lastUpdate: this.currentPosition?.timestamp,
            source: this.currentPosition?.source,
            accuracy: this.currentPosition?.accuracy_level,
            valid: this.currentPosition?.valid
        };
    }
}

window.ProfessionalGPSTracker = ProfessionalGPSTracker;