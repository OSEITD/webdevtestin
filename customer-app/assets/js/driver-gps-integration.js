class DriverGPSIntegration {
    constructor() {
        this.trackingAPI = '../customer-app/api/gps_tracking_production.php';
        this.isTracking = false;
        this.watchId = null;
        this.currentTripId = null;
        this.driverId = null;
        this.updateInterval = 30000;
        this.lastUpdate = null;
        this.init();
    }
    init() {
        this.getDriveAndTripInfo();
        if (this.driverId && this.currentTripId) {
            this.startLocationTracking();
        }
        this.setupEventListeners();
    }
    getDriveAndTripInfo() {
        if (window.driverInfo) {
            this.driverId = window.driverInfo.id;
            this.currentTripId = window.driverInfo.currentTripId;
        }
    }
    setupEventListeners() {
        document.addEventListener('tripStarted', (event) => {
            this.currentTripId = event.detail.tripId;
            this.startLocationTracking();
        });
        document.addEventListener('tripCompleted', () => {
            this.stopLocationTracking();
        });
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.pauseTracking();
            } else {
                this.resumeTracking();
            }
        });
    }
    startLocationTracking() {
        if (this.isTracking || !navigator.geolocation) {
            return;
        }
        console.log('Starting GPS location tracking for driver:', this.driverId);
        const options = {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 30000
        };
        
        this.watchId = navigator.geolocation.watchPosition(
            (position) => this.onLocationUpdate(position),
            (error) => this.onLocationError(error),
            options
        );
        
        this.isTracking = true;
        this.updateTrackingStatus('active');
    }
    
    stopLocationTracking() {
        if (this.watchId) {
            navigator.geolocation.clearWatch(this.watchId);
            this.watchId = null;
        }
        
        this.isTracking = false;
        this.currentTripId = null;
        this.updateTrackingStatus('stopped');
        
        console.log('GPS location tracking stopped');
    }
    
    pauseTracking() {
        if (this.isTracking) {
            this.updateTrackingStatus('paused');
        }
    }
    
    resumeTracking() {
        if (this.isTracking) {
            this.updateTrackingStatus('active');
        }
    }
    
    async onLocationUpdate(position) {
        if (!this.driverId || !this.currentTripId) {
            console.warn('Missing driver ID or trip ID, cannot send location update');
            return;
        }
        
        const coords = position.coords;
        const locationData = {
            driver_id: this.driverId,
            trip_id: this.currentTripId,
            latitude: coords.latitude,
            longitude: coords.longitude,
            accuracy: coords.accuracy,
            speed: coords.speed || 0,
            heading: coords.heading || null
        };
        
        try {
            await this.sendLocationUpdate(locationData);
            this.lastUpdate = new Date();
            this.updateLocationDisplay(locationData);
            
        } catch (error) {
            console.error('Failed to send location update:', error);
            this.updateTrackingStatus('error');
        }
    }
    
    onLocationError(error) {
        console.error('GPS location error:', error);
        
        let errorMessage = 'Unknown GPS error';
        switch (error.code) {
            case error.PERMISSION_DENIED:
                errorMessage = 'GPS permission denied';
                break;
            case error.POSITION_UNAVAILABLE:
                errorMessage = 'GPS position unavailable';
                break;
            case error.TIMEOUT:
                errorMessage = 'GPS request timeout';
                break;
        }
        
        this.updateTrackingStatus('error', errorMessage);
    }
    
    async sendLocationUpdate(locationData) {
        const formData = new FormData();
        formData.append('action', 'update_location');
        
        Object.keys(locationData).forEach(key => {
            formData.append(key, locationData[key]);
        });
        
        const response = await fetch(this.trackingAPI, {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || 'Failed to update location');
        }
        
        return result;
    }
    
    updateTrackingStatus(status, message = '') {
        const statusElement = document.getElementById('gpsTrackingStatus');
        if (statusElement) {
            let statusText = '';
            let className = '';
            
            switch (status) {
                case 'active':
                    statusText = '🟢 GPS Tracking Active';
                    className = 'status-active';
                    break;
                case 'paused':
                    statusText = '🟡 GPS Tracking Paused';
                    className = 'status-paused';
                    break;
                case 'stopped':
                    statusText = '🔴 GPS Tracking Stopped';
                    className = 'status-stopped';
                    break;
                case 'error':
                    statusText = `❌ GPS Error: ${message}`;
                    className = 'status-error';
                    break;
            }
            
            statusElement.textContent = statusText;
            statusElement.className = `gps-status ${className}`;
        }
        
        const lastUpdateElement = document.getElementById('lastLocationUpdate');
        if (lastUpdateElement && this.lastUpdate) {
            lastUpdateElement.textContent = `Last update: ${this.lastUpdate.toLocaleTimeString()}`;
        }
    }
    
    updateLocationDisplay(locationData) {
        const elements = {
            'currentLatitude': locationData.latitude.toFixed(6),
            'currentLongitude': locationData.longitude.toFixed(6),
            'currentSpeed': Math.round(locationData.speed) + ' km/h',
            'currentAccuracy': Math.round(locationData.accuracy) + ' meters'
        };
        
        Object.keys(elements).forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = elements[id];
            }
        });
        
        if (window.driverMap && window.driverLocationMarker) {
            const newLatLng = [locationData.latitude, locationData.longitude];
            window.driverLocationMarker.setLatLng(newLatLng);
            window.driverMap.setView(newLatLng, window.driverMap.getZoom());
        }
    }
    
    async testLocationUpdate() {
        if (!navigator.geolocation) {
            alert('Geolocation not supported');
            return;
        }
        
        navigator.geolocation.getCurrentPosition(
            (position) => this.onLocationUpdate(position),
            (error) => this.onLocationError(error),
            { enableHighAccuracy: true, timeout: 10000 }
        );
    }
    
    getTrackingStatus() {
        return {
            isTracking: this.isTracking,
            driverId: this.driverId,
            currentTripId: this.currentTripId,
            lastUpdate: this.lastUpdate
        };
    }
}

let driverGPS;
document.addEventListener('DOMContentLoaded', () => {
    if (window.location.pathname.includes('/drivers/')) {
        driverGPS = new DriverGPSIntegration();
        window.driverGPS = driverGPS;
        console.log('Driver GPS Integration initialized');
    }
});
if (typeof module !== 'undefined' && module.exports) {
    module.exports = DriverGPSIntegration;
}