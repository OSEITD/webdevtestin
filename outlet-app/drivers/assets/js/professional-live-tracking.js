/**
 * Professional Live GPS Tracking System
 * Real-time driver location with outlet stops and routes
 */

class ProfessionalLiveTracking {
    constructor() {
        this.map = null;
        this.gpsTracker = null;
        this.driverId = document.querySelector('meta[name="driver-id"]')?.content;
        
        // Markers and layers
        this.driverMarker = null;
        this.accuracyCircle = null;
        this.outletMarkers = [];
        this.routeLine = null;
        
        // Layer groups
        this.layers = {
            driver: null,
            outlets: null,
            route: null,
            accuracy: null
        };
        
        // Current data
        this.currentLocation = null;
        this.activeTrip = null;
        this.stops = [];
        
        // Update interval
        this.updateInterval = null;
        this.locationSaveInterval = null;
        
        this.init();
    }

    async init() {
        console.log('🚀 Initializing Professional Live Tracking...');
        
        try {
            // Initialize map
            this.initializeMap();
            
            // Initialize GPS tracker
            await this.initializeGPS();
            
            // Load active trip data
            await this.loadActiveTrip();
            
            // Setup event listeners
            this.setupEventListeners();
            
            // Start auto-refresh
            this.startAutoRefresh();
            
            // Hide loading overlay
            this.hideLoading();
            
            this.showToast('GPS tracking initialized successfully', 'success');
            
        } catch (error) {
            console.error('Initialization error:', error);
            this.showToast('Failed to initialize GPS tracking', 'error');
            this.hideLoading();
        }
    }

    /**
     * Initialize Leaflet map
     */
    initializeMap() {
        console.log('🗺️ Initializing map...');
        
        // Create map centered on Zambia
        this.map = L.map('map', {
            center: [-15.3875, 28.3228],
            zoom: 13,
            zoomControl: false
        });

        // Add tile layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap contributors'
        }).addTo(this.map);

        // Add zoom control to bottom right
        L.control.zoom({
            position: 'bottomright'
        }).addTo(this.map);

        // Initialize layer groups
        this.layers.driver = L.layerGroup().addTo(this.map);
        this.layers.outlets = L.layerGroup().addTo(this.map);
        this.layers.route = L.layerGroup().addTo(this.map);
        this.layers.accuracy = L.layerGroup().addTo(this.map);

        console.log('✅ Map initialized');
    }

    /**
     * Initialize GPS tracker with high accuracy
     */
    async initializeGPS() {
        console.log('📡 Initializing GPS tracker...');

        if (!window.EnhancedGPSTracker) {
            throw new Error('EnhancedGPSTracker not loaded');
        }

        this.gpsTracker = new EnhancedGPSTracker({
            enableHighAccuracy: true,
            timeout: 30000,
            maximumAge: 0,
            accuracyThreshold: 50,
            updateInterval: 15000,
            enableSmoothing: true
        });

        // Start tracking
        this.gpsTracker.startTracking(
            (location) => this.handleLocationUpdate(location),
            (error) => this.handleLocationError(error)
        );

        // Monitor accuracy improvements
        this.gpsTracker.onAccuracyImproved((accuracy) => {
            console.log('📈 GPS accuracy improved to:', Math.round(accuracy), 'm');
            if (accuracy <= 20) {
                this.showToast(`Excellent GPS accuracy: ±${Math.round(accuracy)}m`, 'success');
            }
        });

        console.log('✅ GPS tracker initialized');
    }

    /**
     * Handle GPS location updates
     */
    handleLocationUpdate(location) {
        console.log('📍 Location update:', {
            lat: location.latitude,
            lng: location.longitude,
            accuracy: location.accuracy,
            quality: location.qualityLevel
        });

        this.currentLocation = location;

        // Update driver marker
        this.updateDriverMarker(location);

        // Update GPS stats display
        this.updateGPSStats(location);

        // Update tracking status
        document.getElementById('trackingStatus').textContent = 'Tracking Active';

        // Save location to server if trip is active
        if (this.activeTrip) {
            this.saveLocationToServer(location);
        }
    }

    /**
     * Handle GPS errors
     */
    handleLocationError(error) {
        console.error('GPS Error:', error);
        
        document.getElementById('trackingStatus').textContent = 'GPS Error';
        
        const errorMessages = {
            1: 'Location permission denied. Please enable location access.',
            2: 'GPS signal unavailable. Trying again...',
            3: 'GPS timeout. Please ensure GPS is enabled.'
        };
        
        this.showToast(errorMessages[error.code] || error.message, 'error');
    }

    /**
     * Update driver marker on map
     */
    updateDriverMarker(location) {
        const { latitude, longitude, accuracy, heading } = location;

        // Clear existing driver layer
        this.layers.driver.clearLayers();
        this.layers.accuracy.clearLayers();

        // Create driver marker
        const driverIcon = L.divIcon({
            className: 'driver-marker',
            html: '<i class="fas fa-truck"></i>',
            iconSize: [50, 50],
            iconAnchor: [25, 25]
        });

        this.driverMarker = L.marker([latitude, longitude], {
            icon: driverIcon
        }).addTo(this.layers.driver);

        // Add popup
        const popupContent = `
            <div style="font-family: Poppins; padding: 5px;">
                <h4 style="margin: 0 0 8px 0; color: #111827;">
                    <i class="fas fa-truck"></i> Your Location
                </h4>
                <p style="margin: 4px 0; font-size: 0.85rem; color: #6b7280;">
                    <strong>Coordinates:</strong> ${latitude.toFixed(6)}, ${longitude.toFixed(6)}
                </p>
                <p style="margin: 4px 0; font-size: 0.85rem; color: #6b7280;">
                    <strong>Accuracy:</strong> ±${Math.round(accuracy)}m
                </p>
                ${heading ? `<p style="margin: 4px 0; font-size: 0.85rem; color: #6b7280;">
                    <strong>Heading:</strong> ${Math.round(heading)}°
                </p>` : ''}
                <p style="margin: 4px 0; font-size: 0.85rem; color: #6b7280;">
                    <strong>Quality:</strong> ${location.qualityLevel || 'Good'}
                </p>
            </div>
        `;

        this.driverMarker.bindPopup(popupContent);

        // Add accuracy circle
        if (accuracy && document.getElementById('toggleAccuracy').checked) {
            this.accuracyCircle = L.circle([latitude, longitude], {
                radius: accuracy,
                color: '#667eea',
                fillColor: '#667eea',
                fillOpacity: 0.15,
                weight: 2
            }).addTo(this.layers.accuracy);
        }
    }

    /**
     * Update GPS statistics display
     */
    updateGPSStats(location) {
        const { accuracy, speed, heading } = location;

        // Update accuracy
        document.getElementById('accuracyValue').innerHTML = `
            ${Math.round(accuracy)}m 
            <span class="accuracy-indicator ${this.getAccuracyClass(accuracy)}" id="accuracyIndicator"></span>
        `;

        // Update speed
        const speedKmh = speed !== null && speed !== undefined ? Math.round(speed * 3.6) : 0;
        document.getElementById('speedValue').textContent = `${speedKmh} km/h`;

        // Update heading
        if (heading !== null && heading !== undefined) {
            const direction = this.getDirection(heading);
            document.getElementById('headingValue').textContent = `${Math.round(heading)}° (${direction})`;
        } else {
            document.getElementById('headingValue').textContent = '--°';
        }

        // Update last update time
        const now = new Date();
        document.getElementById('lastUpdateValue').textContent = now.toLocaleTimeString();
    }

    /**
     * Get accuracy CSS class
     */
    getAccuracyClass(accuracy) {
        if (accuracy <= 20) return 'accuracy-excellent';
        if (accuracy <= 50) return 'accuracy-good';
        if (accuracy <= 100) return 'accuracy-fair';
        return 'accuracy-poor';
    }

    /**
     * Get compass direction from heading
     */
    getDirection(heading) {
        const directions = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'];
        const index = Math.round(heading / 45) % 8;
        return directions[index];
    }

    /**
     * Load active trip data
     */
    async loadActiveTrip() {
        console.log('🚛 Loading active trip...');

        try {
            const response = await fetch('../api/driver_dashboard.php', {
                credentials: 'include'
            });
            const data = await response.json();

            if (data.success && data.active_trips && data.active_trips.length > 0) {
                this.activeTrip = data.active_trips[0];
                this.stops = this.activeTrip.route_stops_with_coords || [];

                console.log('✅ Active trip loaded:', this.activeTrip.id);

                // Display trip info
                this.displayTripInfo();

                // Display outlets on map
                this.displayOutlets();

                // Display route line
                this.displayRoute();

                // Fit map to show everything
                this.fitMapToContent();

            } else {
                console.log('ℹ️ No active trip found');
                this.showToast('No active trip. Start a trip to begin tracking.', 'info');
            }

        } catch (error) {
            console.error('Error loading active trip:', error);
            this.showToast('Failed to load trip data', 'error');
        }
    }

    /**
     * Display trip information panel
     */
    displayTripInfo() {
        const tripInfo = document.getElementById('tripInfo');
        const tripTitle = document.getElementById('tripTitle');
        const stopsList = document.getElementById('stopsList');

        tripTitle.textContent = `Trip #${this.activeTrip.id.substring(0, 8)}`;
        
        // Clear existing stops
        stopsList.innerHTML = '';

        // Render stops
        this.stops.forEach((stop, index) => {
            const stopItem = document.createElement('div');
            stopItem.className = `stop-item ${this.getStopClass(stop)}`;

            const isCompleted = stop.departure_time !== null;
            const isActive = !isCompleted && (index === 0 || this.stops[index - 1].departure_time !== null);

            stopItem.innerHTML = `
                <div class="stop-icon ${this.getStopIconClass(stop, isCompleted, isActive)}">
                    ${isCompleted ? '<i class="fas fa-check"></i>' : (isActive ? '<i class="fas fa-clock"></i>' : (index + 1))}
                </div>
                <div class="stop-details">
                    <div class="stop-name">${stop.outlet_name || `Stop ${index + 1}`}</div>
                    <div class="stop-address">${stop.address || 'Address not available'}</div>
                    <div class="stop-meta">
                        ${stop.arrival_time ? `<span><i class="fas fa-sign-in-alt"></i> Arrived: ${new Date(stop.arrival_time).toLocaleTimeString()}</span>` : ''}
                        ${stop.departure_time ? `<span><i class="fas fa-sign-out-alt"></i> Departed: ${new Date(stop.departure_time).toLocaleTimeString()}</span>` : ''}
                        ${stop.parcel_count ? `<span><i class="fas fa-box"></i> ${stop.parcel_count} parcels</span>` : ''}
                    </div>
                    ${isActive && !isCompleted ? `
                        <div class="stop-actions">
                            ${!stop.arrival_time ? `
                                <button class="stop-action-btn arrive" onclick="liveTracking.arriveAtStop('${stop.id}', '${stop.outlet_name}')">
                                    <i class="fas fa-map-marker-alt"></i> Arrive
                                </button>
                            ` : ''}
                            ${stop.arrival_time ? `
                                <button class="stop-action-btn complete" onclick="liveTracking.completeStop('${stop.id}', '${stop.outlet_name}')">
                                    <i class="fas fa-check"></i> Complete & Depart
                                </button>
                            ` : ''}
                        </div>
                    ` : ''}
                </div>
            `;

            stopsList.appendChild(stopItem);
        });

        tripInfo.style.display = 'block';
    }

    /**
     * Get stop CSS class
     */
    getStopClass(stop) {
        if (stop.departure_time) return 'completed';
        if (stop.arrival_time) return 'active';
        return '';
    }

    /**
     * Get stop icon class
     */
    getStopIconClass(stop, isCompleted, isActive) {
        if (isCompleted) return 'completed';
        if (isActive) return 'active';
        return 'pending';
    }

    /**
     * Display outlets on map
     */
    displayOutlets() {
        console.log('🏢 Displaying outlets...');

        // Clear existing outlet markers
        this.layers.outlets.clearLayers();
        this.outletMarkers = [];

        this.stops.forEach((stop, index) => {
            if (!stop.latitude || !stop.longitude) return;

            const isCompleted = stop.departure_time !== null;
            const isActive = !isCompleted && (index === 0 || this.stops[index - 1].departure_time !== null);

            // Create outlet marker
            const outletIcon = L.divIcon({
                className: `outlet-marker ${isCompleted ? 'completed' : (isActive ? 'active' : '')}`,
                html: isCompleted ? '<i class="fas fa-check"></i>' : (isActive ? '<i class="fas fa-clock"></i>' : (index + 1)),
                iconSize: [40, 40],
                iconAnchor: [20, 20]
            });

            const marker = L.marker([stop.latitude, stop.longitude], {
                icon: outletIcon
            }).addTo(this.layers.outlets);

            // Add popup
            const popupContent = `
                <div style="font-family: Poppins; padding: 5px; min-width: 200px;">
                    <h4 style="margin: 0 0 8px 0; color: #111827;">
                        Stop ${index + 1}: ${stop.outlet_name}
                    </h4>
                    <p style="margin: 4px 0; font-size: 0.85rem; color: #6b7280;">
                        <i class="fas fa-map-marker-alt"></i> ${stop.address || 'No address'}
                    </p>
                    ${stop.parcel_count ? `
                        <p style="margin: 4px 0; font-size: 0.85rem; color: #6b7280;">
                            <i class="fas fa-box"></i> ${stop.parcel_count} parcels
                        </p>
                    ` : ''}
                    <p style="margin: 4px 0; font-size: 0.85rem;">
                        <strong style="color: ${isCompleted ? '#10b981' : (isActive ? '#3b82f6' : '#6b7280')};">
                            ${isCompleted ? '✓ Completed' : (isActive ? '• Current Stop' : '○ Pending')}
                        </strong>
                    </p>
                    ${stop.arrival_time ? `
                        <p style="margin: 4px 0; font-size: 0.8rem; color: #9ca3af;">
                            Arrived: ${new Date(stop.arrival_time).toLocaleString()}
                        </p>
                    ` : ''}
                    ${stop.departure_time ? `
                        <p style="margin: 4px 0; font-size: 0.8rem; color: #9ca3af;">
                            Departed: ${new Date(stop.departure_time).toLocaleString()}
                        </p>
                    ` : ''}
                </div>
            `;

            marker.bindPopup(popupContent);
            this.outletMarkers.push(marker);
        });

        console.log(`✅ Displayed ${this.outletMarkers.length} outlet markers`);
    }

    /**
     * Display route line on map
     */
    displayRoute() {
        console.log('🛣️ Displaying route...');

        // Clear existing route
        this.layers.route.clearLayers();

        if (this.stops.length < 2) {
            console.log('⚠️ Not enough stops for route line');
            return;
        }

        // Get coordinates
        const coordinates = this.stops
            .filter(stop => stop.latitude && stop.longitude)
            .map(stop => [stop.latitude, stop.longitude]);

        if (coordinates.length < 2) {
            console.log('⚠️ Not enough valid coordinates for route line');
            return;
        }

        // Find last completed stop
        let lastCompletedIndex = -1;
        this.stops.forEach((stop, index) => {
            if (stop.departure_time) {
                lastCompletedIndex = index;
            }
        });

        // Draw completed segment (green)
        if (lastCompletedIndex >= 0) {
            const completedCoords = coordinates.slice(0, lastCompletedIndex + 1);
            if (completedCoords.length >= 2) {
                L.polyline(completedCoords, {
                    color: '#10b981',
                    weight: 6,
                    opacity: 0.8,
                    lineCap: 'round',
                    lineJoin: 'round'
                }).addTo(this.layers.route);
            }
        }

        // Draw remaining segment (blue dashed)
        if (lastCompletedIndex < coordinates.length - 1) {
            const remainingCoords = coordinates.slice(lastCompletedIndex >= 0 ? lastCompletedIndex : 0);
            if (remainingCoords.length >= 2) {
                L.polyline(remainingCoords, {
                    color: '#3b82f6',
                    weight: 5,
                    opacity: 0.7,
                    dashArray: '10, 10',
                    lineCap: 'round',
                    lineJoin: 'round'
                }).addTo(this.layers.route);
            }
        }

        console.log('✅ Route displayed');
    }

    /**
     * Save location to server
     */
    async saveLocationToServer(location) {
        if (!this.activeTrip) return;

        try {
            const response = await fetch('../api/update_driver_location.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    trip_id: this.activeTrip.id,
                    latitude: location.latitude,
                    longitude: location.longitude,
                    accuracy: location.accuracy,
                    speed: location.speed || 0,
                    heading: location.heading || 0
                })
            });

            const result = await response.json();
            
            if (!result.success) {
                console.warn('Failed to save location:', result.error);
            } else {
                console.log('✅ Location saved to server');
            }

        } catch (error) {
            console.error('Error saving location:', error);
        }
    }

    /**
     * Arrive at stop
     */
    async arriveAtStop(stopId, stopName) {
        try {
            // Get current location for arrival marker
            let locationData = {};
            if (this.currentLocation) {
                locationData = {
                    latitude: this.currentLocation.latitude,
                    longitude: this.currentLocation.longitude,
                    accuracy: this.currentLocation.accuracy,
                    speed: this.currentLocation.speed || 0,
                    heading: this.currentLocation.heading || 0
                };
            }

            const response = await fetch('../api/arrive_at_stop.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    stop_id: stopId,
                    trip_id: this.activeTrip.id,
                    outlet_id: this.stops.find(s => s.id === stopId)?.outlet_id,
                    ...locationData
                })
            });

            const result = await response.json();

            if (result.success) {
                const parcelCount = result.data.parcels_updated || 0;
                this.showToast(
                    `✓ Arrived at ${stopName}. ${parcelCount} parcel(s) marked as 'at_outlet'`, 
                    'success'
                );
                
                // Reload trip data to reflect updates
                await this.loadActiveTrip();
                
                // Log success details
                console.log('📦 Arrival details:', result.data);
            } else {
                throw new Error(result.error || 'Failed to mark arrival');
            }

        } catch (error) {
            console.error('Error marking arrival:', error);
            this.showToast('Failed to mark arrival: ' + error.message, 'error');
        }
    }

    /**
     * Complete stop
     */
    async completeStop(stopId, stopName) {
        try {
            const response = await fetch('../api/complete_trip_stop.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'include',
                body: JSON.stringify({
                    stop_id: stopId,
                    completion_time: new Date().toISOString(),
                    action: 'complete'
                })
            });

            const result = await response.json();

            if (result.success) {
                this.showToast(`✓ Completed stop at ${stopName}`, 'success');
                await this.loadActiveTrip();
                
                if (result.trip_completed) {
                    this.showToast('🎉 Trip completed!', 'success');
                }
            } else {
                throw new Error(result.error || 'Failed to complete stop');
            }

        } catch (error) {
            console.error('Error completing stop:', error);
            this.showToast('Failed to complete stop', 'error');
        }
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Toggle controls
        document.getElementById('toggleRoute').addEventListener('change', (e) => {
            if (e.target.checked) {
                this.map.addLayer(this.layers.route);
            } else {
                this.map.removeLayer(this.layers.route);
            }
        });

        document.getElementById('toggleOutlets').addEventListener('change', (e) => {
            if (e.target.checked) {
                this.map.addLayer(this.layers.outlets);
            } else {
                this.map.removeLayer(this.layers.outlets);
            }
        });

        document.getElementById('toggleAccuracy').addEventListener('change', (e) => {
            if (e.target.checked) {
                this.map.addLayer(this.layers.accuracy);
            } else {
                this.map.removeLayer(this.layers.accuracy);
            }
        });

        // Action buttons
        document.getElementById('centerBtn').addEventListener('click', () => {
            this.centerOnDriver();
        });

        document.getElementById('fitBtn').addEventListener('click', () => {
            this.fitMapToContent();
        });

        document.getElementById('saveLocationBtn').addEventListener('click', () => {
            this.manualSaveLocation();
        });
    }

    /**
     * Center map on driver
     */
    centerOnDriver() {
        if (this.currentLocation) {
            this.map.setView([this.currentLocation.latitude, this.currentLocation.longitude], 16, {
                animate: true
            });
            if (this.driverMarker) {
                this.driverMarker.openPopup();
            }
            this.showToast('Centered on your location', 'info');
        } else {
            this.showToast('Location not available yet', 'warning');
        }
    }

    /**
     * Fit map to show all content
     */
    fitMapToContent() {
        const bounds = L.latLngBounds([]);
        let hasPoints = false;

        // Add driver location
        if (this.currentLocation) {
            bounds.extend([this.currentLocation.latitude, this.currentLocation.longitude]);
            hasPoints = true;
        }

        // Add outlet markers
        this.outletMarkers.forEach(marker => {
            bounds.extend(marker.getLatLng());
            hasPoints = true;
        });

        if (hasPoints) {
            this.map.fitBounds(bounds, { padding: [50, 50] });
            this.showToast('Map fitted to show all locations', 'info');
        } else {
            this.showToast('No locations to display', 'warning');
        }
    }

    /**
     * Manual save location
     */
    async manualSaveLocation() {
        if (!this.currentLocation) {
            this.showToast('No location data available', 'warning');
            return;
        }

        const btn = document.getElementById('saveLocationBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

        await this.saveLocationToServer(this.currentLocation);

        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Save Location';
        
        this.showToast('Location saved successfully', 'success');
    }

    /**
     * Start auto-refresh
     */
    startAutoRefresh() {
        // Refresh trip data every 30 seconds
        this.updateInterval = setInterval(async () => {
            console.log('🔄 Auto-refreshing trip data...');
            await this.loadActiveTrip();
        }, 30000);
    }

    /**
     * Show toast notification
     */
    showToast(message, type = 'info') {
        const toast = document.getElementById('toast');
        const toastMessage = document.getElementById('toastMessage');
        
        toast.className = `toast ${type} show`;
        toastMessage.textContent = message;

        setTimeout(() => {
            toast.classList.remove('show');
        }, 3000);
    }

    /**
     * Hide loading overlay
     */
    hideLoading() {
        document.getElementById('loadingOverlay').style.display = 'none';
    }

    /**
     * Cleanup
     */
    destroy() {
        if (this.gpsTracker) {
            this.gpsTracker.stopTracking();
        }
        if (this.updateInterval) {
            clearInterval(this.updateInterval);
        }
        if (this.locationSaveInterval) {
            clearInterval(this.locationSaveInterval);
        }
    }
}

// Initialize on page load
let liveTracking;
document.addEventListener('DOMContentLoaded', () => {
    liveTracking = new ProfessionalLiveTracking();
});

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    if (liveTracking) {
        liveTracking.destroy();
    }
});
