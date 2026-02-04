
class CustomerGPSTracker {
    constructor(containerId, options = {}) {
        this.containerId = containerId;
        this.map = null;
        this.markers = {
            driver: null,
            origin: null,
            destination: null,
            stops: []
        };
        this.DEFAULT_COORDINATES = {
            latitude: -15.3875,
            longitude: 28.3228,
            zoom: 12
        };
        this.options = {
            trackingInterval: 20000,
            autoCenter: true,
            showRoute: true,
            showStops: true,
            ...options
        };
        this.trackingData = null;
        this.trackingInterval = null;
        console.log('\ud83d\ude9a Customer GPS Tracker initialized for Zambia');
    }
    initializeMap() {
        const container = document.getElementById(this.containerId);
        if (!container) {
            console.error('Map container not found:', this.containerId);
            return false;
        }
        this.map = L.map(this.containerId).setView([
            this.DEFAULT_COORDINATES.latitude,
            this.DEFAULT_COORDINATES.longitude
        ], this.DEFAULT_COORDINATES.zoom);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '\u00a9 OpenStreetMap contributors'
        }).addTo(this.map);
        L.marker([this.DEFAULT_COORDINATES.latitude, this.DEFAULT_COORDINATES.longitude])
            .addTo(this.map)
            .bindPopup('📍 Lusaka, Zambia - Parcel tracking center')
            .openPopup();
        console.log('🗺️ Customer tracking map initialized');
        return true;
    }
        L.marker([this.DEFAULT_COORDINATES.latitude, this.DEFAULT_COORDINATES.longitude])
            .addTo(this.map)
            .bindPopup('📍 Lusaka, Zambia - Parcel tracking center')
            .openPopup();
        
        console.log('🗺️ Customer tracking map initialized');
        return true;
    }
    
    /**
     * Start tracking a parcel
     */
    async startTracking(parcelId) {
        if (!this.map) {
            if (!this.initializeMap()) {
                console.error('Failed to initialize map');
                return false;
            }
        }
        
        console.log('🔍 Starting parcel tracking for:', parcelId);
        
        // Initial load
        await this.updateTrackingData(parcelId);
        
        // Start periodic updates
        if (this.trackingInterval) {
            clearInterval(this.trackingInterval);
        }
        
        this.trackingInterval = setInterval(() => {
            this.updateTrackingData(parcelId);
        }, this.options.trackingInterval);
        
        return true;
    }
    
    /**
     * Stop tracking
     */
    stopTracking() {
        if (this.trackingInterval) {
            clearInterval(this.trackingInterval);
            this.trackingInterval = null;
        }
        console.log('⏹️ Parcel tracking stopped');
    }
    
    /**
     * Update tracking data from server
     */
    async updateTrackingData(parcelId) {
        try {
            const response = await fetch(`api/gps_tracking.php?action=track_parcel&parcel_id=${encodeURIComponent(parcelId)}`);
            const data = await response.json();
            
            if (data.success && data.tracking_available) {
                this.trackingData = data;
                this.updateMapDisplay();
                this.updateTrackingInfo();
            } else {
                console.warn('No tracking data available for parcel:', parcelId);
                this.showNoTrackingMessage();
            }
        } catch (error) {
            console.error('Error fetching tracking data:', error);
            this.showErrorMessage();
        }
    }
    
    /**
     * Update map display with tracking data
     */
    updateMapDisplay() {
        if (!this.trackingData || !this.map) return;
        
        // Clear existing markers
        this.clearMarkers();
        
        const { driver_location, route_data } = this.trackingData;
        
        // Show driver location if available
        if (driver_location && driver_location.latitude && driver_location.longitude) {
            this.addDriverMarker(driver_location);
        }
        
        // Show route stops
        if (route_data && this.options.showStops) {
            this.addRouteMarkers(route_data);
        }
        
        // Auto-center map if enabled
        if (this.options.autoCenter) {
            this.centerMapOnRoute();
        }
    }
    
    /**
     * Add driver marker to map
     */
    addDriverMarker(location) {
        const driverIcon = L.divIcon({
            className: 'driver-marker',
            html: `<div class="driver-marker-icon">
                <i class="fas fa-truck"></i>
            </div>`,
            iconSize: [40, 40],
            iconAnchor: [20, 40]
        });
        
        const timestamp = new Date(location.timestamp).toLocaleString();
        const accuracy = location.accuracy ? `±${Math.round(location.accuracy)}m` : 'Unknown accuracy';
        const speed = location.speed ? `${Math.round(location.speed * 3.6)} km/h` : 'Speed unknown';
        
        this.markers.driver = L.marker([location.latitude, location.longitude], { icon: driverIcon })
            .addTo(this.map)
            .bindPopup(`
                <div class="driver-popup">
                    <h4>🚚 Your Driver</h4>
                    <p><strong>Location:</strong> ${location.latitude.toFixed(6)}, ${location.longitude.toFixed(6)}</p>
                    <p><strong>Accuracy:</strong> ${accuracy}</p>
                    <p><strong>Speed:</strong> ${speed}</p>
                    <p><strong>Last Update:</strong> ${timestamp}</p>
                </div>
            `);
        
        // Add accuracy circle if available
        if (location.accuracy && location.accuracy < 1000) {
            L.circle([location.latitude, location.longitude], {
                radius: location.accuracy,
                color: '#007bff',
                fillColor: '#007bff',
                fillOpacity: 0.1,
                weight: 2
            }).addTo(this.map);
        }
    }
    
    /**
     * Add route markers (origin, destination, stops)
     */
    addRouteMarkers(routeData) {
        // Origin marker
        if (routeData.origin && routeData.origin.latitude && routeData.origin.longitude) {
            const originIcon = L.divIcon({
                className: 'origin-marker',
                html: `<div class="origin-marker-icon">
                    <i class="fas fa-play"></i>
                </div>`,
                iconSize: [30, 30],
                iconAnchor: [15, 30]
            });
            
            this.markers.origin = L.marker([routeData.origin.latitude, routeData.origin.longitude], { icon: originIcon })
                .addTo(this.map)
                .bindPopup(`
                    <div class="route-popup">
                        <h4>📦 Origin</h4>
                        <p><strong>${routeData.origin.outlet_name}</strong></p>
                        <p>${routeData.origin.address || 'Address not available'}</p>
                    </div>
                `);
        }
        
        // Destination marker
        if (routeData.destination && routeData.destination.latitude && routeData.destination.longitude) {
            const destIcon = L.divIcon({
                className: 'destination-marker',
                html: `<div class="destination-marker-icon">
                    <i class="fas fa-flag-checkered"></i>
                </div>`,
                iconSize: [30, 30],
                iconAnchor: [15, 30]
            });
            
            this.markers.destination = L.marker([routeData.destination.latitude, routeData.destination.longitude], { icon: destIcon })
                .addTo(this.map)
                .bindPopup(`
                    <div class="route-popup">
                        <h4>🏁 Destination</h4>
                        <p><strong>${routeData.destination.outlet_name}</strong></p>
                        <p>${routeData.destination.address || 'Address not available'}</p>
                    </div>
                `);
        }
        
        // Stop markers
        if (routeData.stops && Array.isArray(routeData.stops)) {
            routeData.stops.forEach((stop, index) => {
                if (stop.latitude && stop.longitude) {
                    const stopIcon = L.divIcon({
                        className: 'stop-marker',
                        html: `<div class="stop-marker-icon">
                            <span>${index + 1}</span>
                        </div>`,
                        iconSize: [25, 25],
                        iconAnchor: [12, 25]
                    });
                    
                    const marker = L.marker([stop.latitude, stop.longitude], { icon: stopIcon })
                        .addTo(this.map)
                        .bindPopup(`
                            <div class="route-popup">
                                <h4>🏪 Stop ${index + 1}</h4>
                                <p><strong>${stop.outlet_name}</strong></p>
                                <p>${stop.address || 'Address not available'}</p>
                            </div>
                        `);
                    
                    this.markers.stops.push(marker);
                }
            });
        }
    }
    
    /**
     * Clear all markers from map
     */
    clearMarkers() {
        if (this.markers.driver) {
            this.map.removeLayer(this.markers.driver);
            this.markers.driver = null;
        }
        
        if (this.markers.origin) {
            this.map.removeLayer(this.markers.origin);
            this.markers.origin = null;
        }
        
        if (this.markers.destination) {
            this.map.removeLayer(this.markers.destination);
            this.markers.destination = null;
        }
        
        this.markers.stops.forEach(marker => this.map.removeLayer(marker));
        this.markers.stops = [];
    }
    
    /**
     * Center map on route
     */
    centerMapOnRoute() {
        const locations = [];
        
        // Add driver location
        if (this.trackingData.driver_location) {
            locations.push([
                this.trackingData.driver_location.latitude,
                this.trackingData.driver_location.longitude
            ]);
        }
        
        // Add route locations
        if (this.trackingData.route_data) {
            const { origin, destination, stops } = this.trackingData.route_data;
            
            if (origin && origin.latitude && origin.longitude) {
                locations.push([origin.latitude, origin.longitude]);
            }
            
            if (destination && destination.latitude && destination.longitude) {
                locations.push([destination.latitude, destination.longitude]);
            }
            
            if (stops && Array.isArray(stops)) {
                stops.forEach(stop => {
                    if (stop.latitude && stop.longitude) {
                        locations.push([stop.latitude, stop.longitude]);
                    }
                });
            }
        }
        
        if (locations.length > 0) {
            const group = new L.featureGroup(locations.map(loc => L.marker(loc)));
            this.map.fitBounds(group.getBounds().pad(0.1));
        } else {
            // No locations available, center on default Zambian location
            this.map.setView([this.DEFAULT_COORDINATES.latitude, this.DEFAULT_COORDINATES.longitude], this.DEFAULT_COORDINATES.zoom);
        }
    }
    
    /**
     * Update tracking information display
     */
    updateTrackingInfo() {
        const infoElement = document.getElementById('trackingInfo');
        if (!infoElement || !this.trackingData) return;
        
        const { parcel, driver_location } = this.trackingData;
        
        let html = `
            <div class="tracking-info">
                <h3>📦 Parcel: ${parcel.track_number}</h3>
                <p><strong>Status:</strong> ${parcel.status}</p>
                <p><strong>From:</strong> ${parcel.sender_name || 'Unknown'}</p>
                <p><strong>To:</strong> ${parcel.receiver_name || 'Unknown'}</p>
        `;
        
        if (driver_location) {
            const lastUpdate = new Date(driver_location.timestamp).toLocaleString();
            html += `
                <div class="driver-info">
                    <h4>🚚 Driver Location</h4>
                    <p><strong>Last Update:</strong> ${lastUpdate}</p>
                    <p><strong>Coordinates:</strong> ${driver_location.latitude.toFixed(6)}, ${driver_location.longitude.toFixed(6)}</p>
                    ${driver_location.accuracy ? `<p><strong>Accuracy:</strong> ±${Math.round(driver_location.accuracy)}m</p>` : ''}
                    ${driver_location.speed ? `<p><strong>Speed:</strong> ${Math.round(driver_location.speed * 3.6)} km/h</p>` : ''}
                </div>
            `;
        } else {
            html += `
                <div class="no-driver-info">
                    <p>🚫 Driver location not available</p>
                    <p>Your parcel may not be in transit yet.</p>
                </div>
            `;
        }
        
        html += '</div>';
        infoElement.innerHTML = html;
    }
    
    /**
     * Show no tracking message
     */
    showNoTrackingMessage() {
        const infoElement = document.getElementById('trackingInfo');
        if (infoElement) {
            infoElement.innerHTML = `
                <div class="no-tracking">
                    <h3>📦 Parcel Tracking</h3>
                    <p>🚫 No tracking information available for this parcel.</p>
                    <p>Your parcel may not be assigned to a driver yet.</p>
                </div>
            `;
        }
    }
    
    /**
     * Show error message
     */
    showErrorMessage() {
        const infoElement = document.getElementById('trackingInfo');
        if (infoElement) {
            infoElement.innerHTML = `
                <div class="tracking-error">
                    <h3>📦 Parcel Tracking</h3>
                    <p>❌ Error loading tracking information.</p>
                    <p>Please try again later.</p>
                </div>
            `;
        }
    }
    
    /**
     * Get current tracking status
     */
    getTrackingStatus() {
        return {
            isTracking: !!this.trackingInterval,
            hasData: !!this.trackingData,
            driverLocationAvailable: !!(this.trackingData?.driver_location),
            lastUpdate: this.trackingData?.driver_location?.timestamp
        };
    }
}

// Export for global use
window.CustomerGPSTracker = CustomerGPSTracker;