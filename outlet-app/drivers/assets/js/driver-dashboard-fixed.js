/**
 * Professional Driver Dashboard JavaScript
 * Handles all dashboard functionality with clean, maintainable code
 */

class DriverDashboard {
    constructor() {
        this.mapInstance = null;
        this.gpsInterval = null;
        this.driverMarker = null;
        
        this.init();
    }

    /**
     * Initialize the dashboard
     */
    init() {
        document.addEventListener('DOMContentLoaded', () => {
            this.loadDashboardData();
            this.bindEventListeners();
        });
    }

    /**
     * Load dashboard data from API
     */
    async loadDashboardData() {
        try {
            const response = await fetch('api/driver_dashboard.php');
            const data = await response.json();
            
            console.log('Driver Dashboard API response:', data);
            
            if (!data.success) {
                this.showError('Error loading dashboard: ' + (data.error || 'Unknown error'));
                return;
            }

            this.renderActiveTrip(data.active_trips);
            this.renderUpcomingTrips(data.upcoming_trips || []);
            this.renderPerformanceStats(data.performance || {});
            
        } catch (error) {
            console.error('Dashboard loading error:', error);
            this.showError('Failed to load dashboard: ' + error.message);
        }
    }

    /**
     * Render active trip section
     */
    renderActiveTrip(activeTrips) {
        const activeTripCard = document.getElementById('activeTripCard');
        const activeTripDetails = document.getElementById('activeTripDetails');
        
        if (activeTrips && activeTrips.length > 0) {
            const trip = activeTrips[0];
            activeTripCard.style.display = 'block';
            
            activeTripDetails.innerHTML = `
                <div class="trip-card">
                    <div class="trip-header">
                        <i class="fas fa-truck"></i> Trip #${trip.id.slice(0, 8)}
                    </div>
                    <div class="${this.getStatusColor(trip.trip_status)} status-label">
                        ${this.getStatusLabel(trip.trip_status)}
                    </div>
                    <div class="trip-info">
                        <i class="fas fa-map-marker-alt"></i> Route: ${this.getRouteInfo(trip)}
                    </div>
                    <div class="trip-info">
                        <i class="fas fa-box"></i> Parcels: ${trip.parcels_count !== undefined && trip.parcels_count !== null ? trip.parcels_count : 'N/A'}
                    </div>
                    <div class="trip-info">
                        <i class="fas fa-clock"></i> Departure: ${this.formatDate(trip.departure_time)}
                    </div>
                </div>
            `;
        }
    }

    /**
     * Render upcoming trips section
     */
    async renderUpcomingTrips(upcomingTrips) {
        const upcomingList = document.getElementById('upcomingTripsList');
        
        if (upcomingTrips.length === 0) {
            upcomingList.innerHTML = '<p class="no-trips">No upcoming trips assigned.</p>';
            return;
        }

        try {
            // Fetch route names for each trip
            const tripCards = await Promise.all(upcomingTrips.map(async trip => {
                const routeNames = await this.fetchTripRoute(trip);
                
                return `
                    <div class="trip-card">
                        <div class="trip-header">
                            <i class="fas fa-truck"></i> Trip #${trip.id.slice(0, 8)}
                        </div>
                        <div class="${this.getStatusColor(trip.trip_status)} status-label">
                            ${this.getStatusLabel(trip.trip_status)}
                        </div>
                        <div class="trip-info">
                            <i class="fas fa-map-marker-alt"></i> Route: ${routeNames}
                        </div>
                        <div class="trip-info">
                            <i class="fas fa-box"></i> Parcels: ${trip.parcels_count !== undefined && trip.parcels_count !== null ? trip.parcels_count : 'N/A'}
                        </div>
                        <div class="trip-info">
                            <i class="fas fa-clock"></i> Departure: ${this.formatDate(trip.departure_time)}
                        </div>
                        <button class="trip-action-btn" data-trip-id="${trip.id}">
                            ${trip.trip_status === 'scheduled' ? 'Start Trip' : 'View Details'}
                        </button>
                    </div>
                `;
            }));

            upcomingList.innerHTML = tripCards.join('');
            this.bindTripActionButtons();
            
        } catch (error) {
            console.error('Error rendering upcoming trips:', error);
            upcomingList.innerHTML = '<p class="error">Error loading upcoming trips.</p>';
        }
    }

    /**
     * Render performance statistics
     */
    renderPerformanceStats(performance) {
        const statsElements = {
            tripsToday: performance.trips_today || '0',
            tripsWeek: performance.trips_week || '0',
            parcelsDelivered: performance.parcels_delivered || '0',
            parcelsReturned: performance.parcels_returned || '0'
        };

        Object.keys(statsElements).forEach(elementId => {
            const element = document.getElementById(elementId);
            if (element) {
                element.textContent = statsElements[elementId];
                this.animateCounter(element, statsElements[elementId]);
            }
        });
    }

    /**
     * Fetch trip route information
     */
    async fetchTripRoute(trip) {
        try {
            const response = await fetch(`api/fetch_trip_route.php?trip_id=${trip.id}&company_id=${trip.company_id}`);
            const routeData = await response.json();
            
            if (routeData.success && routeData.route) {
                return routeData.route;
            }
            
            return this.getRouteInfo(trip);
        } catch (error) {
            console.warn('Error fetching trip route:', error);
            return this.getRouteInfo(trip);
        }
    }

    /**
     * Bind event listeners for trip action buttons
     */
    bindTripActionButtons() {
        document.querySelectorAll('.trip-action-btn').forEach(btn => {
            btn.addEventListener('click', () => this.handleTripAction(btn));
        });
    }

    /**
     * Handle trip action button clicks
     */
    async handleTripAction(button) {
        const tripId = button.getAttribute('data-trip-id');
        const originalText = button.textContent;
        
        button.disabled = true;
        button.textContent = 'Starting...';

        try {
            // Call start_trip.php which sends notifications
            const response = await fetch(`api/trips/start_trip.php`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({trip_id: tripId})
            });
            const result = await response.json();

            if (result.success) {
                button.textContent = 'Started!';
                button.classList.add('success');
                
                // Show notification summary
                const managerCount = result.notifications?.manager_notifications?.length || 0;
                const customerCount = result.notifications?.customer_notifications?.length || 0;
                const totalNotifications = managerCount + customerCount;
                
                if (totalNotifications > 0) {
                    this.showNotification(`Trip started! ${totalNotifications} notifications sent (${managerCount} manager, ${customerCount} customer).`);
                } else {
                    this.showNotification('Trip started successfully!');
                }
                
                // Show live map after successful start
                setTimeout(() => {
                    this.showLiveLocationMap();
                }, 500);
                
                // Optionally trigger resume trip modal
                const resumeBtn = document.getElementById('resumeTripBtn');
                if (resumeBtn) {
                    resumeBtn.click();
                }
                
            } else {
                throw new Error(result.error || 'Failed to start trip');
            }
            
        } catch (error) {
            console.error('Trip action error:', error);
            button.textContent = originalText;
            button.disabled = false;
            this.showError('Failed to start trip: ' + error.message);
        }
    }

    /**
     * Handle arriving at trip stop with notifications
     */
    async arriveAtOutlet(stopId, tripId, outletId, button) {
        const originalText = button ? button.textContent : 'Arrive at Stop';
        if (button) {
            button.disabled = true;
            button.textContent = 'Recording...';
        }
        
        try {
            // Get current location if available
            let location = {};
            if (this.currentLocation) {
                location = {
                    latitude: this.currentLocation.lat,
                    longitude: this.currentLocation.lng,
                    accuracy: this.currentLocation.accuracy
                };
            }
            
            // Step 1: Record arrival time and update parcels
            const arrivalResponse = await fetch('api/arrive_at_stop_simple.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    stop_id: stopId,
                    trip_id: tripId,
                    outlet_id: outletId,
                    ...location
                })
            });
            
            const arrivalResult = await arrivalResponse.json();
            
            if (!arrivalResult.success) {
                throw new Error(arrivalResult.error || 'Failed to record arrival');
            }
            
            // Step 2: Send notifications to manager and customers
            const notifyResponse = await fetch('api/trips/arrive_at_outlet.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({trip_id: tripId, outlet_id: outletId})
            });
            
            const notifyResult = await notifyResponse.json();
            
            if (notifyResult.success) {
                const notifCount = notifyResult.notifications_sent || 0;
                this.showNotification(`Arrival recorded! ${notifCount} notifications sent.`);
                
                if (button) {
                    button.textContent = 'Arrived ✓';
                    button.classList.add('completed');
                    button.disabled = true;
                }
                
                // Refresh trip details to show updated stop status
                setTimeout(() => {
                    this.loadDashboardData();
                }, 1000);
            } else {
                // Arrival was recorded but notifications failed
                this.showNotification('Arrival recorded (notifications may have failed)');
                console.warn('Notification error:', notifyResult.error);
            }
            
        } catch (error) {
            console.error('Arrive at outlet error:', error);
            if (button) {
                button.textContent = originalText;
                button.disabled = false;
            }
            this.showError('Failed to record arrival: ' + error.message);
        }
    }

    /**
     * Bind all event listeners
     */
    bindEventListeners() {
        // Quick action buttons
        const quickActions = {
            startTripBtn: () => this.showNotification('Start New Trip feature coming soon'),
            scanParcelBtn: () => this.showNotification('Scan Parcel feature coming soon'),
            searchParcelBtn: () => this.showNotification('Search Parcel feature coming soon'),
            tripHistoryBtn: () => this.showNotification('Trip History feature coming soon'),
            resumeTripBtn: () => this.handleResumeTripAction()
        };

        Object.keys(quickActions).forEach(buttonId => {
            const button = document.getElementById(buttonId);
            if (button) {
                button.addEventListener('click', quickActions[buttonId]);
            }
        });
    }

    /**
     * Handle resume trip action
     */
    async handleResumeTripAction() {
        const button = document.getElementById('resumeTripBtn');
        const originalText = button.textContent;
        
        button.disabled = true;
        button.textContent = 'Loading...';

        try {
            const response = await fetch('api/active_trip.php');
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.error || 'Could not fetch active trip');
            }

            this.showResumeTripModal(data);
            
        } catch (error) {
            console.error('Resume trip error:', error);
            this.showError('Error loading trip: ' + error.message);
        } finally {
            button.disabled = false;
            button.textContent = originalText;
        }
    }

    /**
     * Show resume trip modal
     */
    showResumeTripModal(tripData) {
        const totalStops = tripData.stops.length;
        const completedStops = tripData.stops.filter(s => s.departure_time).length;
        const progressPercent = totalStops ? Math.round((completedStops / totalStops) * 100) : 0;

        const modalHTML = `
            <div id="resumeTripModal" class="modal-overlay">
                <div class="modal-content">
                    <button id="closeResumeTripModal" class="modal-close" title="Close">&times;</button>
                    <h2>Resume Trip</h2>
                    
                    <div class="trip-details">
                        <p><strong>Trip #${tripData.trip.trip_id || tripData.trip.id}</strong></p>
                        <p>Status: ${tripData.trip.trip_status}</p>
                        <p>Route: ${tripData.stops.map(s => s.outlet_name).join(' → ') || '-'}</p>
                    </div>
                    
                    <div class="progress-section">
                        <h3>Trip Progress</h3>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: ${progressPercent}%"></div>
                        </div>
                        <p>${completedStops} of ${totalStops} stops completed (${progressPercent}%)</p>
                    </div>
                    
                    ${tripData.next_stop ? `
                        <div class="next-stop">
                            <h3>Next Stop</h3>
                            <p>${tripData.next_stop.outlet_name || tripData.next_stop.outlet_id}</p>
                            <p>${tripData.next_stop_parcels.length} parcels to deliver</p>
                            <div class="stop-actions">
                                <button id="arriveStopBtn" class="dashboard-action-btn">Arrive at Stop</button>
                                <button id="completeStopBtn" class="dashboard-action-btn">Complete Stop</button>
                            </div>
                        </div>
                    ` : ''}
                    
                    <div class="remaining-deliveries">
                        <p><strong>Remaining Deliveries:</strong> ${tripData.remaining_deliveries}</p>
                    </div>
                    
                    <button id="closeResumeTripModalBottom" class="dashboard-action-btn">Close</button>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHTML);
        this.bindModalEvents(tripData);
        this.startGpsPolling(tripData.trip.trip_id || tripData.trip.id);
    }

    /**
     * Bind modal event listeners
     */
    bindModalEvents(tripData) {
        const modal = document.getElementById('resumeTripModal');
        const closeButtons = ['closeResumeTripModal', 'closeResumeTripModalBottom'];
        
        closeButtons.forEach(buttonId => {
            const button = document.getElementById(buttonId);
            if (button) {
                button.addEventListener('click', () => {
                    modal.remove();
                    this.stopGpsPolling();
                });
            }
        });

        // Arrive at stop button
        const arriveBtn = document.getElementById('arriveStopBtn');
        if (arriveBtn) {
            arriveBtn.addEventListener('click', () => this.handleArriveAtStop(tripData.next_stop));
        }
    }

    /**
     * Show live location map
     */
    showLiveLocationMap() {
        const mapContainer = document.getElementById('liveLocationMapContainer');
        if (!mapContainer) return;

        mapContainer.style.display = 'block';
        
        // Load Leaflet if not already loaded
        if (!window.L) {
            const script = document.createElement('script');
            script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
            script.onload = () => setTimeout(() => this.initializeMap(), 100);
            document.body.appendChild(script);
        } else {
            this.initializeMap();
        }
    }

    /**
     * Initialize the map
     */
    initializeMap() {
        const mapElement = document.getElementById('liveLocationMap');
        if (!mapElement || this.mapInstance) return;

        this.mapInstance = L.map('liveLocationMap').setView([0, 0], 13);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap contributors'
        }).addTo(this.mapInstance);

        // Get initial location
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(position => {
                this.updateDriverLocation(position.coords.latitude, position.coords.longitude);
            });
        }
    }

    /**
     * Update driver location on map
     */
    updateDriverLocation(lat, lng) {
        if (!this.mapInstance) return;

        if (this.driverMarker) {
            this.driverMarker.setLatLng([lat, lng]);
        } else {
            this.driverMarker = L.marker([lat, lng])
                .addTo(this.mapInstance)
                .bindPopup('Driver Location')
                .openPopup();
        }

        this.mapInstance.setView([lat, lng], 13);
    }

    /**
     * Start GPS polling
     */
    startGpsPolling(tripId) {
        this.stopGpsPolling(); // Clear any existing interval
        
        this.gpsInterval = setInterval(() => {
            if (!navigator.geolocation) return;

            navigator.geolocation.getCurrentPosition(async position => {
                const { latitude, longitude } = position.coords;
                
                // Update map
                this.updateDriverLocation(latitude, longitude);
                
                // Send to backend (implement as needed)
                // await this.sendLocationUpdate(tripId, latitude, longitude);
            });
        }, 20000); // Update every 20 seconds
    }

    /**
     * Stop GPS polling
     */
    stopGpsPolling() {
        if (this.gpsInterval) {
            clearInterval(this.gpsInterval);
            this.gpsInterval = null;
        }
    }

    // ========== Utility Functions ==========

    /**
     * Format date for display
     */
    formatDate(dateString) {
        if (!dateString) return '-';
        
        const date = new Date(dateString.replace(' ', 'T'));
        return date.toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
        }).replace(',', ' •');
    }

    /**
     * Get status color class
     */
    getStatusColor(status) {
        const statusColors = {
            'scheduled': 'status-blue',
            'in_transit': 'status-orange',
            'completed': 'status-green',
            'cancelled': 'status-red'
        };
        return statusColors[status] || 'status-gray';
    }

    /**
     * Get status label
     */
    getStatusLabel(status) {
        const statusLabels = {
            'scheduled': 'Scheduled',
            'in_transit': 'In Transit',
            'completed': 'Completed',
            'cancelled': 'Cancelled'
        };
        return statusLabels[status] || status;
    }

    /**
     * Get route information
     */
    getRouteInfo(trip) {
        if (trip.route && trip.route !== '') {
            return trip.route;
        }
        return trip.outlet_name || '-';
    }

    /**
     * Animate counter
     */
    animateCounter(element, targetValue) {
        const start = parseInt(element.textContent) || 0;
        const target = parseInt(targetValue) || 0;
        const duration = 1000;
        const step = (target - start) / (duration / 16);
        
        let current = start;
        const timer = setInterval(() => {
            current += step;
            if ((step > 0 && current >= target) || (step < 0 && current <= target)) {
                element.textContent = target;
                clearInterval(timer);
            } else {
                element.textContent = Math.floor(current);
            }
        }, 16);
    }

    /**
     * Show error message
     */
    showError(message) {
        this.showNotification(message, 'error');
    }

    /**
     * Show notification
     */
    showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        
        // Style the notification
        Object.assign(notification.style, {
            position: 'fixed',
            top: '20px',
            right: '20px',
            padding: '1rem 1.5rem',
            borderRadius: '8px',
            color: 'white',
            fontSize: '0.875rem',
            fontWeight: '500',
            zIndex: '10000',
            transform: 'translateX(100%)',
            transition: 'transform 0.3s ease'
        });

        // Set background color based on type
        const colors = {
            info: '#3b82f6',
            error: '#ef4444',
            success: '#10b981',
            warning: '#f59e0b'
        };
        notification.style.backgroundColor = colors[type] || colors.info;

        document.body.appendChild(notification);

        // Animate in
        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
        }, 100);

        // Remove after delay
        setTimeout(() => {
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, 3000);
    }
}

// Initialize the dashboard
const driverDashboard = new DriverDashboard();

// Expose arriveAtOutlet globally for inline button onclick handlers
window.arriveAtOutlet = function(stopId, tripId, outletId, button) {
    driverDashboard.arriveAtOutlet(stopId, tripId, outletId, button);
};