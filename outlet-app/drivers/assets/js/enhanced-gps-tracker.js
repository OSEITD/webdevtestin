/**
 * Enhanced GPS Tracker with High Accuracy Settings
 * Optimized for real-time driver location tracking
 */

class EnhancedGPSTracker {
    constructor(config = {}) {
        this.config = {
            enableHighAccuracy: true,
            timeout: 30000,
            maximumAge: 0,
            accuracyThreshold: 50,
            updateInterval: 15000,
            retryAttempts: 5,
            ...config
        };
        
        this.watchId = null;
        this.updateIntervalId = null;
        this.currentLocation = null;
        this.callbacks = {
            onSuccess: null,
            onError: null,
            onAccuracyImproved: null
        };
        
        this.bestAccuracy = Infinity;
        this.locationHistory = [];
        this.maxHistorySize = 10;
    }

    /**
     * Start tracking with high accuracy
     */
    startTracking(onSuccess, onError) {
        this.callbacks.onSuccess = onSuccess;
        this.callbacks.onError = onError;

        if (!navigator.geolocation) {
            this.handleError({
                code: 0,
                message: 'Geolocation not supported'
            });
            return false;
        }

        console.log('🎯 Starting enhanced GPS tracking with settings:', {
            enableHighAccuracy: this.config.enableHighAccuracy,
            timeout: this.config.timeout,
            maximumAge: this.config.maximumAge,
            accuracyThreshold: this.config.accuracyThreshold
        });

        // Get initial position with retries
        this.getInitialPosition();

        // Start continuous watching
        this.startWatchPosition();

        // Start periodic updates
        this.startPeriodicUpdates();

        return true;
    }

    /**
     * Get initial position with retry logic
     */
    async getInitialPosition(attempt = 1) {
        console.log(`📍 Getting initial GPS fix (attempt ${attempt}/${this.config.retryAttempts})...`);

        navigator.geolocation.getCurrentPosition(
            (position) => {
                console.log('✅ Initial GPS fix obtained:', {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude,
                    accuracy: position.coords.accuracy
                });
                this.handlePosition(position);
            },
            (error) => {
                console.warn(`⚠️ Initial GPS fix failed (attempt ${attempt}):`, error.message);
                
                if (attempt < this.config.retryAttempts) {
                    setTimeout(() => {
                        this.getInitialPosition(attempt + 1);
                    }, 2000 * attempt); // Exponential backoff
                } else {
                    this.handleError(error);
                }
            },
            {
                enableHighAccuracy: this.config.enableHighAccuracy,
                timeout: this.config.timeout,
                maximumAge: this.config.maximumAge
            }
        );
    }

    /**
     * Start continuous watch position
     */
    startWatchPosition() {
        if (this.watchId) {
            navigator.geolocation.clearWatch(this.watchId);
        }

        this.watchId = navigator.geolocation.watchPosition(
            (position) => this.handlePosition(position),
            (error) => this.handleError(error),
            {
                enableHighAccuracy: this.config.enableHighAccuracy,
                timeout: this.config.timeout,
                maximumAge: this.config.maximumAge
            }
        );

        console.log('👁️ Continuous GPS monitoring active (watchPosition ID:', this.watchId, ')');
    }

    /**
     * Start periodic location updates
     */
    startPeriodicUpdates() {
        if (this.updateIntervalId) {
            clearInterval(this.updateIntervalId);
        }

        this.updateIntervalId = setInterval(() => {
            if (this.currentLocation) {
                console.log('🔄 Periodic location update triggered');
                if (this.callbacks.onSuccess) {
                    this.callbacks.onSuccess(this.currentLocation);
                }
            }
        }, this.config.updateInterval);

        console.log('⏱️ Periodic updates every', this.config.updateInterval / 1000, 'seconds');
    }

    /**
     * Handle new position data
     */
    handlePosition(position) {
        const location = {
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
            accuracy: position.coords.accuracy,
            speed: position.coords.speed,
            heading: position.coords.heading,
            altitude: position.coords.altitude,
            timestamp: position.timestamp
        };

        // Validate coordinates
        if (!this.isValidLocation(location)) {
            console.warn('❌ Invalid location received:', location);
            return;
        }

        // Check accuracy threshold
        if (location.accuracy > this.config.accuracyThreshold) {
            console.warn(`⚠️ Low accuracy (${Math.round(location.accuracy)}m) - threshold is ${this.config.accuracyThreshold}m`);
            // Don't reject, but mark as low quality
            location.qualityLevel = 'low';
        } else if (location.accuracy <= 20) {
            location.qualityLevel = 'excellent';
            console.log('🎯 Excellent GPS accuracy:', Math.round(location.accuracy), 'm');
        } else if (location.accuracy <= 50) {
            location.qualityLevel = 'good';
            console.log('✅ Good GPS accuracy:', Math.round(location.accuracy), 'm');
        }

        // Track accuracy improvements
        if (location.accuracy < this.bestAccuracy) {
            this.bestAccuracy = location.accuracy;
            console.log('📈 GPS accuracy improved to:', Math.round(location.accuracy), 'm');
            if (this.callbacks.onAccuracyImproved) {
                this.callbacks.onAccuracyImproved(location.accuracy);
            }
        }

        // Add to history
        this.addToHistory(location);

        // Get smoothed location if enabled
        if (this.config.enableSmoothing && this.locationHistory.length >= 3) {
            location.smoothed = this.getSmoothLocation();
        }

        // Update current location
        this.currentLocation = location;

        // Trigger success callback
        if (this.callbacks.onSuccess) {
            this.callbacks.onSuccess(location);
        }
    }

    /**
     * Validate location coordinates
     */
    isValidLocation(location) {
        const { latitude, longitude, accuracy } = location;

        // Check for null/undefined
        if (latitude == null || longitude == null) {
            return false;
        }

        // Check for valid ranges
        if (Math.abs(latitude) > 90 || Math.abs(longitude) > 180) {
            return false;
        }

        // Check for invalid (0, 0)
        if (latitude === 0 && longitude === 0) {
            return false;
        }

        // Check for reasonable accuracy
        if (accuracy != null && (accuracy < 0 || accuracy > 10000)) {
            return false;
        }

        return true;
    }

    /**
     * Add location to history for smoothing
     */
    addToHistory(location) {
        this.locationHistory.push({
            latitude: location.latitude,
            longitude: location.longitude,
            accuracy: location.accuracy,
            timestamp: location.timestamp
        });

        // Keep only recent history
        if (this.locationHistory.length > this.maxHistorySize) {
            this.locationHistory.shift();
        }
    }

    /**
     * Get smoothed location from recent history
     */
    getSmoothLocation() {
        if (this.locationHistory.length < 2) {
            return null;
        }

        // Weight by accuracy (better accuracy = more weight)
        let totalWeight = 0;
        let weightedLat = 0;
        let weightedLng = 0;

        this.locationHistory.forEach(loc => {
            const weight = 1 / (loc.accuracy || 1);
            totalWeight += weight;
            weightedLat += loc.latitude * weight;
            weightedLng += loc.longitude * weight;
        });

        return {
            latitude: weightedLat / totalWeight,
            longitude: weightedLng / totalWeight
        };
    }

    /**
     * Handle GPS errors
     */
    handleError(error) {
        let errorMessage = 'GPS error occurred';
        
        switch (error.code) {
            case error.PERMISSION_DENIED:
                errorMessage = 'Location permission denied. Please enable location access.';
                console.error('🚫 Location permission denied');
                break;
            case error.POSITION_UNAVAILABLE:
                errorMessage = 'Location unavailable. Please check GPS settings.';
                console.error('📡 GPS signal unavailable');
                break;
            case error.TIMEOUT:
                errorMessage = 'Location timeout. Retrying...';
                console.warn('⏱️ GPS timeout');
                break;
            default:
                errorMessage = error.message || 'Unknown GPS error';
                console.error('❌ GPS error:', errorMessage);
        }

        if (this.callbacks.onError) {
            this.callbacks.onError({ code: error.code, message: errorMessage });
        }
    }

    /**
     * Stop tracking
     */
    stopTracking() {
        if (this.watchId) {
            navigator.geolocation.clearWatch(this.watchId);
            this.watchId = null;
            console.log('🛑 GPS tracking stopped');
        }

        if (this.updateIntervalId) {
            clearInterval(this.updateIntervalId);
            this.updateIntervalId = null;
        }
    }

    /**
     * Get current location (one-time)
     */
    getCurrentLocation() {
        return new Promise((resolve, reject) => {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const location = {
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude,
                        accuracy: position.coords.accuracy,
                        speed: position.coords.speed,
                        heading: position.coords.heading,
                        timestamp: position.timestamp
                    };
                    
                    if (this.isValidLocation(location)) {
                        resolve(location);
                    } else {
                        reject(new Error('Invalid location received'));
                    }
                },
                (error) => reject(error),
                {
                    enableHighAccuracy: this.config.enableHighAccuracy,
                    timeout: this.config.timeout,
                    maximumAge: this.config.maximumAge
                }
            );
        });
    }

    /**
     * Set callback for accuracy improvements
     */
    onAccuracyImproved(callback) {
        this.callbacks.onAccuracyImproved = callback;
    }

    /**
     * Get tracking status
     */
    getStatus() {
        return {
            isTracking: this.watchId !== null,
            currentAccuracy: this.currentLocation?.accuracy,
            bestAccuracy: this.bestAccuracy,
            historySize: this.locationHistory.length,
            lastUpdate: this.currentLocation?.timestamp
        };
    }
}

// Export for use in other scripts
if (typeof window !== 'undefined') {
    window.EnhancedGPSTracker = EnhancedGPSTracker;
}
