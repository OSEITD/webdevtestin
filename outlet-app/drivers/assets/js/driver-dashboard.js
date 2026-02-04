/**
 * Professional Driver Dashboard JavaScript
 * Handles all dashboard functionality with clean, maintainable code
 */

class DriverDashboard {
    constructor() {
        this.mapInstance = null;
        this.gpsInterval = null;
        this.driverMarker = null;
        
        // Trip map properties
        this.tripMapInstance = null;
        this.tripDriverMarker = null;
        this.tripGpsInterval = null;
        
        // Location tracking properties
        this.locationRetryTimer = null;
        this.currentPosition = null;
        this.locationPermissionChecked = false; // NEW: Track if permission was already checked
        this.locationPermissionGranted = false; // NEW: Track permission state
        
        // Auto-refresh control
        this.autoRefreshStopped = false; // Flag to permanently stop auto-refresh after trip acceptance
        
        // Professional GPS tracker
        this.gpsTracker = null;
        
        // Location cache service
        this.locationCache = null;
        
        // Stop management state
        this.selectedStopId = null;
        this.lastRenderedStops = [];
        
        // Performance snapshot state preservation
        this.cachedPerformanceStats = null;
        this.loadCachedPerformanceStats();

        this.init();
    }

    init() {
        document.addEventListener('DOMContentLoaded', () => {
            this.loadDashboardData();
            this.bindEventListeners();
            this.updateDriverStatusDisplay('available'); // Initialize driver status as available
            // Location tracking will start only when trip is accepted
            this.setupDashboardRefresh(); // Setup auto-refresh
            this.setupFullscreenListener(); // Setup fullscreen change listener
        });
    }

    /**
     * Setup fullscreen change listener to update button icon when user exits via ESC
     */
    setupFullscreenListener() {
        const updateFullscreenIcon = () => {
            const fullscreenBtn = document.querySelector('.fullscreen-btn i');
            if (fullscreenBtn) {
                fullscreenBtn.className = document.fullscreenElement ? 'fas fa-compress' : 'fas fa-expand';
            }
            // Invalidate map size after fullscreen change
            setTimeout(() => {
                if (this.tripMapInstance) {
                    this.tripMapInstance.invalidateSize();
                }
            }, 100);
        };
        
        document.addEventListener('fullscreenchange', updateFullscreenIcon);
        document.addEventListener('webkitfullscreenchange', updateFullscreenIcon);
        document.addEventListener('mozfullscreenchange', updateFullscreenIcon);
        document.addEventListener('MSFullscreenChange', updateFullscreenIcon);
    }

    /**
     * Initialize location tracking on dashboard load
     */
    initializeLocationTracking() {
        console.log('[DEBUG] Initializing professional location tracking...');
        
        // Check if permission was already handled
        if (this.locationPermissionChecked) {
            console.log('[GPS] Location permission already checked, skipping...');
            return;
        }
        
        // Initialize professional GPS tracker with caching
        this.gpsTracker = new ProfessionalGPSTracker({
            enableHighAccuracy: true,
            timeout: 30000,
            maximumAge: 0,
            retryAttempts: 5,
            retryDelay: 3000,
            fallbackToDefault: true,
            validateBounds: true,
            enableLocationCache: true,
            driverId: this.getDriverId()
        });
        
        // Mark as checked to prevent repeated attempts
        this.locationPermissionChecked = true;
        
        // Set up GPS tracker callbacks
        this.gpsTracker.on('onSuccess', (location) => {
            console.log('🎯 GPS location obtained:', location);
            this.locationPermissionGranted = true; // Mark permission as granted
            this.currentPosition = {
                lat: location.latitude,
                lng: location.longitude,
                accuracy: location.accuracy
            };
            
            // Hide any existing warning
            this.hideLocationWarning();
            
            // this.showNotification(`📍 GPS location: ${location.nearest_city?.name || 'Zambia'} (${location.accuracy_level} accuracy)`, 'success');
            this.updateMapsWithLocation(location);
            
            // Update trip GPS stats if trip map is visible
            this.updateTripGPSStats(location);
        });
        
        this.gpsTracker.on('onError', (error) => {
            console.error('❌ GPS error:', error);
            this.locationPermissionGranted = false;
            
            let errorMessage = '📍 GPS access failed';
            let showHelp = false;
            
            switch(error.code) {
                case error.PERMISSION_DENIED:
                    errorMessage = 'Location access denied. Using default location.';
                    showHelp = true;
                    break;
                case error.POSITION_UNAVAILABLE:
                    errorMessage = 'GPS signal unavailable. Using default location.';
                    break;
                case error.TIMEOUT:
                    errorMessage = 'GPS timeout. Using default location.';
                    break;
            }
            
            // Show persistent warning banner instead of notification
            this.showLocationWarning(errorMessage, showHelp);
        });
        
        this.gpsTracker.on('onFallback', (location) => {
            console.log('🏠 Using fallback location:', location);
            this.currentPosition = {
                lat: location.latitude,
                lng: location.longitude,
                accuracy: location.accuracy
            };
            
            this.showNotification(`📍 Using default location: ${location.city}, ${location.country}`, 'info');
            this.updateMapsWithLocation(location);
        });
        
        // Start location tracking
        this.startProfessionalLocationTracking();
    }
    
    /**
     * Start professional location tracking
     */
    async startProfessionalLocationTracking() {
        try {
            console.log('🎯 Starting professional GPS tracking...');
            const location = await this.gpsTracker.getCurrentLocation();
            
            // Start continuous tracking if initial location was successful
            if (location.source === 'gps') {
                this.gpsTracker.startTracking();
                console.log('🔄 Continuous GPS tracking activated');
            }
            
            // Don't show simple live location map - we use trip map instead
            // this.showLiveLocationMap();
            
        } catch (error) {
            console.error('Failed to start location tracking:', error);
            this.showNotification('Failed to initialize GPS tracking', 'error');
        }
    }
    
    /**
     * Check if there's an accepted trip
     */
    hasAcceptedTrip() {
        if (!this.lastDashboardData || !this.lastDashboardData.active_trips) {
            return false;
        }
        const trip = this.lastDashboardData.active_trips[0];
        const status = trip?.status || 'scheduled';
        return status === 'accepted' || status === 'in_transit' || status === 'at_outlet';
    }
    
    /**
     * Get driver ID from various sources
     */
    getDriverId() {
        // Try to get from various possible sources
        if (window.driverSession && window.driverSession.user_id) {
            return window.driverSession.user_id;
        }
        
        if (sessionStorage.getItem('driver_id')) {
            return sessionStorage.getItem('driver_id');
        }
        
        if (localStorage.getItem('driver_id')) {
            return localStorage.getItem('driver_id');
        }
        
        // Try to extract from meta tags
        const driverMeta = document.querySelector('meta[name="driver-id"]');
        if (driverMeta) {
            return driverMeta.content;
        }
        
        // Try to get from PHP session via AJAX (as fallback)
        try {
            fetch('api/get_session_info.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.user_id) {
                        sessionStorage.setItem('driver_id', data.user_id);
                        return data.user_id;
                    }
                })
                .catch(error => console.warn('Could not fetch session info:', error));
        } catch (error) {
            console.warn('Failed to get driver ID:', error);
        }
        
        return 'unknown_driver';
    }
    
    /**
     * Update maps with location data
     */
    updateMapsWithLocation(location) {
        // Update trip map if available
        if (this.tripMapInstance) {
            this.updateTripMapLocation(
                location.latitude, 
                location.longitude, 
                location.accuracy,
                location.speed,
                location.heading
            );
        }
        
        // Update live location map if available
        if (this.mapInstance) {
            this.updateLiveMapLocation(
                location.latitude, 
                location.longitude, 
                location.accuracy
            );
        }
    }
    
    /**
     * Use default location fallback when GPS access is denied
     */
    useDefaultLocationFallback() {
        console.log('[DEBUG] Using default location fallback for Zambia');
        
        // Default location: Lusaka, Zambia (capital city)
        const defaultLocation = {
            lat: -15.3875,
            lng: 28.3228,
            accuracy: null,
            city: 'Lusaka',
            country: 'Zambia'
        };
        
        this.currentPosition = defaultLocation;
        
        // Show maps with default location
        this.showLiveLocationMap();
        
        // Update maps with default location
        if (this.tripMapInstance) {
            this.updateTripMapLocation(
                defaultLocation.lat, 
                defaultLocation.lng, 
                null
            );
        }
        
        if (this.mapInstance) {
            this.updateLiveMapLocation(
                defaultLocation.lat, 
                defaultLocation.lng, 
                null
            );
        }
        
        // this.showNotification('📍 Using default location (Lusaka, Zambia). Enable GPS for accurate tracking.', 'warning');
        
        // Set up periodic retry for location access
        this.startLocationRetryTimer();
    }
    
    /**
     * Start location retry timer - periodically check if GPS permission is granted
     */
    startLocationRetryTimer() {
        console.log('[DEBUG] Starting location retry timer...');
        
        // Clear any existing retry timer
        if (this.locationRetryTimer) {
            clearInterval(this.locationRetryTimer);
        }
        
        // Retry every 30 seconds
        this.locationRetryTimer = setInterval(() => {
            if (navigator.permissions) {
                navigator.permissions.query({name: 'geolocation'}).then((result) => {
                    if (result.state === 'granted') {
                        console.log('[DEBUG] Location permission granted, switching to GPS...');
                        clearInterval(this.locationRetryTimer);
                        this.locationRetryTimer = null;
                        this.requestLocationAccess();
                    }
                }).catch(() => {
                    // Permissions API failed, try direct access
                    navigator.geolocation.getCurrentPosition(
                        position => {
                            console.log('[DEBUG] Location access restored, switching to GPS...');
                            clearInterval(this.locationRetryTimer);
                            this.locationRetryTimer = null;
                            this.requestLocationAccess();
                        },
                        () => {
                            // Still denied, continue with fallback
                        },
                        { timeout: 5000, maximumAge: 60000 }
                    );
                });
            }
        }, 30000); // Check every 30 seconds
    }
    
    /**
     * Set up map control event listeners that work after auto-refresh
     */
    setupMapControlEventListeners() {
        console.log('[DEBUG] Setting up map control event listeners...');
        
        // Remove old onclick attributes and add proper event listeners
        const mapControls = document.querySelectorAll('.map-control-btn');
        mapControls.forEach((button, index) => {
            button.removeAttribute('onclick'); // Remove old onclick attributes
            
            const newButton = button.cloneNode(true);
            button.parentNode.replaceChild(newButton, button);
            
            // Add event listeners based on button position/icon
            const icon = newButton.querySelector('i');
            if (icon) {
                if (icon.classList.contains('fa-crosshairs')) {
                    newButton.addEventListener('click', (e) => {
                        e.preventDefault();
                        this.centerOnDriver();
                    });
                } else if (icon.classList.contains('fa-map-signs')) {
                    newButton.addEventListener('click', (e) => {
                        e.preventDefault();
                        this.toggleRouteStops();
                    });
                } else if (icon.classList.contains('fa-building')) {
                    newButton.addEventListener('click', (e) => {
                        e.preventDefault();
                        this.toggleOutletMarkers();
                    });
                } else if (icon.classList.contains('fa-expand-arrows-alt')) {
                    newButton.addEventListener('click', (e) => {
                        e.preventDefault();
                        this.fitMapToContent();
                    });
                }
            }
        });
        
        console.log('[DEBUG] Map control event listeners set up successfully');
    }

    /**
     * Setup automatic dashboard refresh
     */
    setupDashboardRefresh() {
        console.log('[DEBUG] Setting up dashboard refresh...');
        
        // Refresh dashboard data every 45 seconds (increased from 30s for better performance)
        this.dashboardRefreshInterval = setInterval(() => {
            if (!this.autoRefreshStopped) {
                console.log('[DEBUG] Auto-refreshing dashboard data...');
                this.loadDashboardData();
            }
        }, 45000);

        // Reduce location refresh frequency
        this.locationRefreshInterval = setInterval(() => {
            if (this.locationWatchId) {
                console.log('[DEBUG] Location tracking is active - continuing...');
            }
        }, 30000); // Increased from 20s to 30s

        // Handle page visibility changes
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                console.log('[DEBUG] Page hidden - pausing refresh');
                this.pauseRefresh();
            } else {
                console.log('[DEBUG] Page visible - resuming refresh');
                this.resumeRefresh();
            }
        });
    }

    /**
     * Pause dashboard refresh when page is hidden
     */
    pauseRefresh() {
        if (this.dashboardRefreshInterval) {
            clearInterval(this.dashboardRefreshInterval);
            this.dashboardRefreshInterval = null;
        }
    }

    /**
     * Stop dashboard refresh permanently (e.g., when trip is accepted)
     */
    stopDashboardRefresh() {
        if (this.dashboardRefreshInterval) {
            clearInterval(this.dashboardRefreshInterval);
            this.dashboardRefreshInterval = null;
            console.log('[DEBUG] Dashboard auto-refresh stopped permanently');
            
            // Set flag to permanently stop auto-refresh
            this.autoRefreshStopped = true;
            
            // Add visual indicator that auto-refresh is stopped
            this.showNotification('🔄 Auto-refresh stopped - Trip accepted', 'info');
        }
        
        if (this.locationRefreshInterval) {
            clearInterval(this.locationRefreshInterval);
            this.locationRefreshInterval = null;
        }
    }

    /**
     * Resume dashboard refresh when page becomes visible
     */
    resumeRefresh() {
        // Don't resume if auto-refresh was permanently stopped (trip accepted)
        if (this.autoRefreshStopped) {
            console.log('[DEBUG] Auto-refresh resumption blocked - trip accepted');
            return;
        }
        
        if (!this.dashboardRefreshInterval) {
            this.setupDashboardRefresh();
        }
        
        // Immediately refresh when page becomes visible
        this.loadDashboardData();
    }

    /**
     * Restart auto-refresh (when trip is completed or cancelled)
     */
    restartAutoRefresh() {
        console.log('[DEBUG] Restarting auto-refresh - trip completed/cancelled');
        this.autoRefreshStopped = false;
        this.setupDashboardRefresh();
        this.showNotification('🔄 Auto-refresh resumed', 'info');
    }

    /**
     * Stop GPS polling
     */
    stopGpsPolling() {
        if (this.gpsInterval) {
            console.log('[DEBUG] Stopping GPS polling for trip:', this.currentGpsTrackingTripId);
            clearInterval(this.gpsInterval);
            this.gpsInterval = null;
            this.currentGpsTrackingTripId = null;
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
        // Prefer full route string if available
        if (trip.route && trip.route !== '') {
            return trip.route;
        }
        // If trip_stops are available, build route from outlet names
        if (trip.route_stops && Array.isArray(trip.route_stops) && trip.route_stops.length > 0) {
            return trip.route_stops.join(' → ');
        }
        // Fallback to origin/destination outlet names if present
        if (trip.origin_outlet_name && trip.destination_outlet_name) {
            return `${trip.origin_outlet_name} → ${trip.destination_outlet_name}`;
        }
        return '-';
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
     * Show persistent location warning banner
     */
    showLocationWarning(message, showHelp = false) {
        // Remove existing warning if present
        this.hideLocationWarning();
        
        // Create warning banner
        const banner = document.createElement('div');
        banner.id = 'location-warning-banner';
        banner.className = 'location-warning-banner';
        
        // Banner content
        const content = `
            <div class="location-warning-content">
                <div class="location-warning-icon">
                    <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                    </svg>
                </div>
                <div class="location-warning-message">${message}</div>
                <div class="location-warning-actions">
                    ${showHelp ? '<button class="location-help-btn" onclick="driverDashboard.showLocationHelp()">How to Enable</button>' : ''}
                    <button class="location-dismiss-btn" onclick="driverDashboard.hideLocationWarning()">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                        </svg>
                    </button>
                </div>
            </div>
        `;
        
        banner.innerHTML = content;
        
        // Style the banner
        Object.assign(banner.style, {
            position: 'fixed',
            top: '0',
            left: '0',
            right: '0',
            backgroundColor: '#fff3cd',
            borderBottom: '2px solid #ffc107',
            padding: '1rem',
            zIndex: '9999',
            boxShadow: '0 2px 8px rgba(0,0,0,0.1)',
            transform: 'translateY(-100%)',
            transition: 'transform 0.3s ease-in-out'
        });
        
        // Insert banner at top of body
        document.body.insertBefore(banner, document.body.firstChild);
        
        // Add styles for banner content (inline to avoid CSS file dependency)
        if (!document.getElementById('location-warning-styles')) {
            const styles = document.createElement('style');
            styles.id = 'location-warning-styles';
            styles.textContent = `
                .location-warning-content {
                    display: flex;
                    align-items: center;
                    gap: 1rem;
                    max-width: 1200px;
                    margin: 0 auto;
                }
                .location-warning-icon {
                    flex-shrink: 0;
                    color: #f59e0b;
                    display: flex;
                    align-items: center;
                }
                .location-warning-message {
                    flex: 1;
                    color: #856404;
                    font-size: 0.875rem;
                    font-weight: 500;
                }
                .location-warning-actions {
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                }
                .location-help-btn {
                    background: #f59e0b;
                    color: white;
                    border: none;
                    padding: 0.5rem 1rem;
                    border-radius: 6px;
                    font-size: 0.875rem;
                    font-weight: 500;
                    cursor: pointer;
                    transition: background 0.2s;
                }
                .location-help-btn:hover {
                    background: #d97706;
                }
                .location-dismiss-btn {
                    background: transparent;
                    border: none;
                    color: #856404;
                    cursor: pointer;
                    padding: 0.25rem;
                    display: flex;
                    align-items: center;
                    transition: opacity 0.2s;
                }
                .location-dismiss-btn:hover {
                    opacity: 0.7;
                }
                @media (max-width: 640px) {
                    .location-warning-content {
                        flex-wrap: wrap;
                        gap: 0.75rem;
                    }
                    .location-warning-actions {
                        flex: 1 1 100%;
                        justify-content: flex-end;
                    }
                }
            `;
            document.head.appendChild(styles);
        }
        
        // Animate in
        setTimeout(() => {
            banner.style.transform = 'translateY(0)';
        }, 100);
        
        console.log('[Location Warning] Banner shown:', message);
    }

    /**
     * Hide location warning banner
     */
    hideLocationWarning() {
        const banner = document.getElementById('location-warning-banner');
        if (banner) {
            banner.style.transform = 'translateY(-100%)';
            setTimeout(() => {
                if (banner.parentNode) {
                    banner.parentNode.removeChild(banner);
                }
            }, 300);
            console.log('[Location Warning] Banner hidden');
        }
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
            left: '50%',
            transform: 'translateX(-50%) translateY(-100px)',
            padding: '1rem 1.5rem',
            borderRadius: '8px',
            color: 'white',
            fontSize: '0.875rem',
            fontWeight: '500',
            zIndex: '10000',
            transition: 'transform 0.3s ease',
            maxWidth: '400px',
            textAlign: 'center',
            boxShadow: '0 4px 12px rgba(0,0,0,0.15)'
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
            notification.style.transform = 'translateX(-50%) translateY(0)';
        }, 100);

        // Remove after delay
        setTimeout(() => {
            notification.style.transform = 'translateX(-50%) translateY(-100px)';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, 3000);
    }

    /**
     * Safe JSON parsing with error handling
     */
    async safeJsonParse(response) {
        let text = '';
        try {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            text = await response.text();
            if (!text) {
                throw new Error('Empty response from server');
            }
            
            // Log the raw response for debugging
            console.log('Raw server response:', text);
            
            // Check if response starts with HTML error
            if (text.trim().startsWith('<')) {
                console.error('Server returned HTML instead of JSON:', text.substring(0, 200));
                throw new Error('Server error: Expected JSON but received HTML');
            }
            
            return JSON.parse(text);
        } catch (error) {
            console.error('JSON parsing error:', error);
            console.error('Response text:', text?.substring(0, 200));
            throw new Error('Failed to parse server response: ' + error.message);
        }
    }

    /**
     * Load dashboard data
     */
    async loadDashboardData() {
        try {
            const response = await fetch('api/driver_dashboard.php', {
                credentials: 'include'
            });
            const data = await response.json();
            
            console.log('Driver Dashboard API response:', data);
            
            if (!data.success) {
                this.showError('Error loading dashboard: ' + (data.error || 'Unknown error'));
                // Notify loading overlay even on error
                if (typeof window.markDriverDashboardLoaded === 'function') {
                    window.markDriverDashboardLoaded();
                }
                return;
            }

            // Cache dashboard data for map usage
            this.lastDashboardData = data;
            
            this.renderActiveTrip(data.active_trips);
            this.renderUpcomingTrips(data.upcoming_trips || []);
            
            // Performance Snapshot state preservation logic
            const perf = data.performance;
            const hasValidPerformanceData = perf && (
                typeof perf.trips_today !== 'undefined' || 
                typeof perf.trips_week !== 'undefined' || 
                typeof perf.parcels_delivered !== 'undefined' || 
                typeof perf.parcels_returned !== 'undefined'
            );
            
            if (hasValidPerformanceData) {
                // Update with fresh data from API
                this.renderPerformanceStats(perf);
            } else if (!this.cachedPerformanceStats) {
                // Only fetch from dedicated endpoint if we have no cached data at all
                this.fetchAndRenderPerformanceStats();
            }
            // Otherwise: preserve existing cached state (don't overwrite with empty)
            
            // Notify loading overlay that data is ready
            if (typeof window.markDriverDashboardLoaded === 'function') {
                window.markDriverDashboardLoaded();
            }
            
        } catch (error) {
            console.error('Dashboard loading error:', error);
            this.showError('Failed to load dashboard: ' + error.message);
            
            // Notify loading overlay even on error to prevent stuck screen
            if (typeof window.markDriverDashboardLoaded === 'function') {
                window.markDriverDashboardLoaded();
            }
        }
    }

    /**
     * Format trip status with professional styling
     */
    formatTripStatus(status, includeIcon = true) {
        const statusConfig = {
            'scheduled': {
                label: 'Scheduled',
                icon: 'fas fa-calendar-alt',
                class: 'trip-status-scheduled'
            },
            'accepted': {
                label: 'Accepted',
                icon: 'fas fa-clipboard-check',
                class: 'trip-status-accepted'
            },
            'in_transit': {
                label: 'In Transit',
                icon: 'fas fa-truck',
                class: 'trip-status-in_transit'
            },
            'completed': {
                label: 'Completed',
                icon: 'fas fa-check-circle',
                class: 'trip-status-completed'
            },
            'at_outlet': {
                label: 'At Outlet',
                icon: 'fas fa-building',
                class: 'trip-status-at_outlet'
            },
            'cancelled': {
                label: 'Cancelled',
                icon: 'fas fa-times-circle',
                class: 'trip-status-cancelled'
            }
        };

        const config = statusConfig[status] || statusConfig['scheduled'];
        const iconHtml = includeIcon ? `<i class="${config.icon}"></i>` : '';
        
        return `<span class="trip-status-badge ${config.class}">
            ${iconHtml}
            <span>${config.label}</span>
        </span>`;
    }

    /**
     * Update trip status with server sync and driver status management
     */
    async updateTripStatus(tripId, newStatus, additionalData = {}) {
        try {
            const response = await fetch('api/update_trip_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    trip_id: tripId,
                    status: newStatus,
                    timestamp: new Date().toISOString(),
                    ...additionalData
                })
            });

            const result = await this.safeJsonParse(response);

            if (result.success) {
                this.showNotification(`Trip status updated to ${newStatus.replace('_', ' ')}`, 'success');
                
                // Update driver status based on trip status
                if (newStatus === 'completed' || newStatus === 'at_outlet') {
                    await this.updateDriverStatus('available');
                    this.updateDriverStatusDisplay('available');
                } else if (newStatus === 'in_transit') {
                    await this.updateDriverStatus('unavailable');
                    this.updateDriverStatusDisplay('unavailable');
                }
                
                // Refresh dashboard to show updated status
                this.loadDashboardData();
                
                return true;
            } else {
                throw new Error(result.error || 'Failed to update trip status');
            }
        } catch (error) {
            console.error('Error updating trip status:', error);
            this.showNotification('Failed to update trip status: ' + error.message, 'error');
            return false;
        }
    }

    /**
     * Update driver status
     */
    async updateDriverStatus(newStatus) {
        try {
            const response = await fetch('api/update_driver_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    status: newStatus,
                    timestamp: new Date().toISOString()
                })
            });

            const result = await this.safeJsonParse(response);

            if (result.success) {
                console.log(`Driver status updated to ${newStatus}`);
                return true;
            } else {
                throw new Error(result.error || 'Failed to update driver status');
            }
        } catch (error) {
            console.error('Error updating driver status:', error);
            return false;
        }
    }

    /**
     * Render status timeline for trip
     */
    renderStatusTimeline(currentStatus) {
        const statuses = [
            { key: 'scheduled', label: 'Scheduled', icon: 'fas fa-calendar-alt' },
            { key: 'accepted', label: 'Accepted', icon: 'fas fa-clipboard-check' },
            { key: 'in_transit', label: 'In Transit', icon: 'fas fa-truck' },
            { key: 'at_outlet', label: 'At Outlet', icon: 'fas fa-building' },
            { key: 'completed', label: 'Completed', icon: 'fas fa-check-circle' }
        ];

        const currentIndex = statuses.findIndex(s => s.key === currentStatus);
        
        let timelineHtml = '<div class="status-timeline">';
        
        statuses.forEach((status, index) => {
            const isCompleted = index <= currentIndex;
            const isActive = index === currentIndex;
            const statusClass = isCompleted ? 'completed' : (isActive ? 'active' : 'pending');
            
            timelineHtml += `
                <div class="status-timeline-item ${statusClass}">
                    <div class="timeline-icon ${statusClass}">
                        <i class="${status.icon}"></i>
                    </div>
                    <div class="timeline-label ${statusClass}">
                        ${status.label}
                    </div>
                </div>
            `;
        });
        
        timelineHtml += '</div>';
        return timelineHtml;
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
            
            // Check trip status - only proceed with full rendering for accepted trips
            const tripStatus = trip.status || 'scheduled';
            const isAccepted = tripStatus === 'accepted' || tripStatus === 'in_transit' || tripStatus === 'at_outlet';
            
            // Check if this is the same trip as currently displayed to avoid map destruction
            const existingTripId = activeTripDetails.getAttribute('data-trip-id');
            const currentTripId = trip.id;
            
            if (existingTripId === currentTripId && this.tripMapInstance) {
                console.log('[DEBUG] Same trip detected, updating data without destroying map...');
                this.updateActiveTripData(trip);
                return;
            }
            
            // Clean up existing map instance before recreating HTML
            if (this.tripMapInstance) {
                console.log('[DEBUG] Cleaning up existing trip map instance...');
                this.tripMapInstance.remove();
                this.tripMapInstance = null;
                
                // Clean up health check interval
                if (this.mapHealthCheckInterval) {
                    clearInterval(this.mapHealthCheckInterval);
                    this.mapHealthCheckInterval = null;
                }
            }
            
            // Start location tracking only for accepted trips
            if (isAccepted && !this.locationPermissionChecked) {
                console.log('[DEBUG] Trip is accepted, initializing location tracking...');
                this.initializeLocationTracking();
            }

            // Get origin and destination names from route_stops
            let originName = '-';
            let destinationName = '-';
            if (Array.isArray(trip.route_stops) && trip.route_stops.length > 0) {
                originName = trip.route_stops[0];
                destinationName = trip.route_stops[trip.route_stops.length - 1];
            }

            activeTripDetails.innerHTML = `
                <div class="trip-card active-trip-professional" data-trip-id="${trip.id}">
                    <div class="active-trip-header">
                        <div class="trip-badge">
                            <i class="fas fa-route"></i>
                            <span>Active Trip</span>
                        </div>
                        <div class="trip-id">Trip #${trip.id.slice(0, 8)}</div>
                    </div>
                    
                    ${this.formatTripStatus(trip.status || 'scheduled')}
                    ${this.renderStatusTimeline(trip.status || 'scheduled')}
                    
                    <div class="trip-info-grid">
                        <div class="trip-info-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <div>
                                <span class="info-label">Route</span>
                                <span class="info-value">${this.getRouteInfo(trip)}</span>
                            </div>
                        </div>
                        <div class="trip-info-item">
                            <i class="fas fa-box"></i>
                            <div>
                                <span class="info-label">Parcels</span>
                                <span class="info-value">${trip.parcel_count || 'N/A'}</span>
                            </div>
                        </div>
                        <div class="trip-info-item">
                            <i class="fas fa-clock"></i>
                            <div>
                                <span class="info-label">Departure</span>
                                <span class="info-value">${this.formatDate(trip.departure_time)}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="trip-controls-professional">
                        <button id="saveLocationBtn" class="trip-action-btn primary">
                            <i class="fas fa-map-pin"></i> Save Location
                        </button>
                    </div>
                    
                    <!-- Professional Map Container - Only shown for accepted trips -->
                    <div class="trip-map-container-professional" style="display: ${isAccepted ? 'block' : 'none'}">
                        <div class="map-header">
                            <h4 class="map-title">
                                <i class="fas fa-map-marked-alt"></i> 
                                Live Route Navigation
                            </h4>
                            <div class="map-status">
                                <span class="status-indicator active"></span>
                                <span>Live Tracking</span>
                            </div>
                        </div>
                        <div id="tripMap" class="trip-map-professional"></div>
                        <div class="map-controls-professional">
                            <button class="map-control-btn" onclick="window.driverDashboard?.centerOnDriver()" title="Center on My Location">
                                <i class="fas fa-crosshairs"></i>
                            </button>
                            <button class="map-control-btn" onclick="window.driverDashboard?.toggleRouteStops()" title="Toggle Route Stops">
                                <i class="fas fa-map-signs"></i>
                            </button>
                            <button class="map-control-btn" onclick="window.driverDashboard?.toggleOutletMarkers()" title="Toggle Outlets">
                                <i class="fas fa-building"></i>
                            </button>
                            <button class="map-control-btn" onclick="window.driverDashboard?.fitMapToContent()" title="Fit to Content">
                                <i class="fas fa-expand-arrows-alt"></i>
                            </button>
                            <button class="map-control-btn fullscreen-btn" onclick="window.driverDashboard?.toggleMapFullscreen()" title="Toggle Fullscreen">
                                <i class="fas fa-expand"></i>
                            </button>
                        </div>
                        
                        <!-- GPS Stats Panel -->
                        <div class="trip-gps-stats">
                            <div class="gps-stat-item">
                                <span class="gps-stat-label"><i class="fas fa-crosshairs"></i> Accuracy</span>
                                <span class="gps-stat-value" id="tripAccuracyValue">-- m</span>
                            </div>
                            <div class="gps-stat-item">
                                <span class="gps-stat-label"><i class="fas fa-tachometer-alt"></i> Speed</span>
                                <span class="gps-stat-value" id="tripSpeedValue">-- km/h</span>
                            </div>
                            <div class="gps-stat-item">
                                <span class="gps-stat-label"><i class="fas fa-compass"></i> Heading</span>
                                <span class="gps-stat-value" id="tripHeadingValue">--°</span>
                            </div>
                        </div>
                        
                        <!-- Trip Route -->
                        <div id="tripStopsContainer" class="trip-stops-container">
                            <div class="trip-stops-header" onclick="window.driverDashboard?.toggleStopsPanel()">
                                <h4><i class="fas fa-route"></i> Route</h4>
                                <button class="trip-stops-toggle">
                                    <i class="fas fa-chevron-down"></i>
                                </button>
                            </div>
                            <div id="tripStopsList" class="trip-stops-list"></div>
                            <div id="tripStopActionPanel" class="trip-stop-action-panel hidden"></div>
                            <div id="tripCompletionFooter" class="trip-completion-footer hidden"></div>
                        </div>
                    </div>
                </div>
            `;
            
            // Store the trip ID for future comparisons
            activeTripDetails.setAttribute('data-trip-id', currentTripId);

            // Add event listeners with improved feedback
            setTimeout(() => {
                const saveBtn = document.getElementById('saveLocationBtn');
                if (saveBtn) {
                    saveBtn.addEventListener('click', () => {
                        this.startGpsPolling(trip.trip_id || trip.id);
                        this.showNotification('📍 Location tracking activated! GPS coordinates are being saved every 20 seconds.', 'success');
                        saveBtn.innerHTML = '<i class="fas fa-check"></i> Tracking Active';
                        saveBtn.disabled = true;
                        saveBtn.classList.add('active');
                    });
                    
                    // Auto-update button state if GPS tracking is already active
                    if (this.gpsInterval && this.currentGpsTrackingTripId === currentTripId) {
                        console.log('[DEBUG] GPS tracking already active, updating button state');
                        saveBtn.innerHTML = '<i class="fas fa-check"></i> Tracking Active';
                        saveBtn.disabled = true;
                        saveBtn.classList.add('active');
                    }
                }
                
                // Ensure simple live location map is hidden
                const simpleMapContainer = document.getElementById('liveLocationMapContainer');
                if (simpleMapContainer) {
                    simpleMapContainer.style.display = 'none';
                }
                
                // Set up map control event listeners
                this.setupMapControlEventListeners();
                
                // Initialize the professional trip map only for accepted trips
                if (isAccepted) {
                    console.log('[DEBUG] Trip accepted, initializing map...');
                    this.initializeTripMap();
                } else {
                    console.log('[DEBUG] Trip not yet accepted (status: ' + tripStatus + '), map hidden');
                }
                
                // Load and display trip stops with arrival buttons
                this.loadAndDisplayTripStops();
                
                // Auto-start GPS tracking for active trips
                console.log('[DEBUG] Auto-starting GPS tracking for active trip:', currentTripId);
                this.startGpsPolling(currentTripId);
                
                // Force focus on the trip map container to ensure it's visible
                const tripMapContainer = document.querySelector('.trip-map-container-professional');
                if (tripMapContainer) {
                    tripMapContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
                
                // Add event listeners for map controls after a short delay
                setTimeout(() => {
                    this.addMapControlEventListeners();
                }, 200);
            }, 100);
        } else {
            // No active trips - hide the card and cleanup
            activeTripCard.style.display = 'none';
            
            // Clear the trip ID attribute
            activeTripDetails.removeAttribute('data-trip-id');
            
            // Hide the map when no active trip and cleanup trip map
            const mapContainer = document.getElementById('liveLocationMapContainer');
            if (mapContainer) {
                mapContainer.style.display = 'none';
            }

            // Cleanup trip map tracking
            if (this.tripGpsInterval) {
                clearInterval(this.tripGpsInterval);
                this.tripGpsInterval = null;
            }

            // Reset trip map instance
            if (this.tripMapInstance) {
                console.log('[DEBUG] No active trips - cleaning up trip map instance');
                this.tripMapInstance.remove();
                this.tripMapInstance = null;
                this.tripDriverMarker = null;
                
                // Clean up health check interval
                if (this.mapHealthCheckInterval) {
                    clearInterval(this.mapHealthCheckInterval);
                    this.mapHealthCheckInterval = null;
                }
            }
        }
    }
    
    /**
     * Update active trip data without destroying the map
     */
    updateActiveTripData(trip) {
        console.log('[DEBUG] Updating active trip data without map destruction');
        
        // Update trip status
        const statusElement = document.querySelector('.trip-status');
        if (statusElement) {
            statusElement.innerHTML = this.formatTripStatus(trip.status || 'scheduled');
        }
        
        // Update status timeline
        const timelineElement = document.querySelector('.status-timeline');
        if (timelineElement) {
            timelineElement.innerHTML = this.renderStatusTimeline(trip.status || 'scheduled');
        }
        
        // Update parcel count
        const parcelCountElement = document.querySelector('.trip-info-item .info-value');
        if (parcelCountElement) {
            parcelCountElement.textContent = trip.parcel_count || 'N/A';
        }
        
        // Update departure time
        const departureElements = document.querySelectorAll('.trip-info-item .info-value');
        if (departureElements.length >= 3) {
            departureElements[2].textContent = this.formatDate(trip.departure_time);
        }
        
        // Update route info
        const routeElement = document.querySelector('.trip-info-item .info-value');
        if (routeElement) {
            routeElement.textContent = this.getRouteInfo(trip);
        }
        
        // Update map status indicator if needed
        const statusIndicator = document.querySelector('.status-indicator');
        if (statusIndicator) {
            statusIndicator.className = `status-indicator ${trip.status === 'in_transit' ? 'active' : 'pending'}`;
        }
        
        // Refresh location data on existing map
        if (this.tripMapInstance) {
            this.getCurrentLocationForTripMap();
        }
    }

    /**
     * Add event listeners for map control buttons
     */
    addMapControlEventListeners() {
        console.log('[DEBUG] Adding map control event listeners');
        
        // Get all map control buttons
        const centerBtn = document.querySelector('.map-control-btn[title="Center on My Location"]');
        const toggleStopsBtn = document.querySelector('.map-control-btn[title="Toggle Route Stops"]');
        const toggleOutletsBtn = document.querySelector('.map-control-btn[title="Toggle Outlets"]');
        const fitContentBtn = document.querySelector('.map-control-btn[title="Fit to Content"]');
        
        if (centerBtn) {
            centerBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.centerOnDriver();
            });
        }
        
        if (toggleStopsBtn) {
            toggleStopsBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.toggleRouteStops();
            });
        }
        
        if (toggleOutletsBtn) {
            toggleOutletsBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.toggleOutletMarkers();
            });
        }
        
        if (fitContentBtn) {
            fitContentBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.fitMapToContent();
            });
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
                    <div class="trip-card" data-trip-id="${trip.id}">
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
                            <i class="fas fa-box"></i> Parcels: ${trip.parcels_count || 'N/A'}
                        </div>
                        <div class="trip-info">
                            <i class="fas fa-clock"></i> Departure: ${this.formatDate(trip.departure_time)}
                        </div>
                        <button class="trip-action-btn" data-trip-id="${trip.id}" data-trip-status="${trip.trip_status}">
                            ${trip.trip_status === 'scheduled' ? '<i class="fas fa-play"></i> Start Trip' : 
                              trip.trip_status === 'in_transit' ? '<i class="fas fa-route"></i> View Route' : 
                              '<i class="fas fa-info-circle"></i> View Details'}
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
     * Render performance statistics with state preservation
     */
    renderPerformanceStats(performance) {
        // Validate incoming data - skip if all values are null/undefined
        if (!performance || 
            (typeof performance.trips_today === 'undefined' && 
             typeof performance.trips_week === 'undefined' && 
             typeof performance.parcels_delivered === 'undefined' && 
             typeof performance.parcels_returned === 'undefined')) {
            console.log('[Performance] Skipping render - no valid data provided');
            return;
        }
        
        // Merge with cached state to preserve any missing fields
        const metrics = {
            trips_today: performance.trips_today ?? this.cachedPerformanceStats?.trips_today ?? 0,
            trips_week: performance.trips_week ?? this.cachedPerformanceStats?.trips_week ?? 0,
            parcels_delivered: performance.parcels_delivered ?? this.cachedPerformanceStats?.parcels_delivered ?? 0,
            parcels_returned: performance.parcels_returned ?? this.cachedPerformanceStats?.parcels_returned ?? 0
        };

        // Update cached state immediately
        this.cachedPerformanceStats = { ...metrics };

        // Debounce rapid updates to avoid flicker
        if (this._perfUpdateTimer) clearTimeout(this._perfUpdateTimer);
        this._perfUpdateTimer = setTimeout(() => {
            const mapping = {
                trips_today: 'tripsToday',
                trips_week: 'tripsWeek',
                parcels_delivered: 'parcelsDelivered',
                parcels_returned: 'parcelsReturned'
            };

            let didUpdate = false;
            Object.keys(mapping).forEach(key => {
                const value = metrics[key];
                if (typeof value !== 'undefined' && value !== null) {
                    const elementId = mapping[key];
                    const element = document.getElementById(elementId);
                    if (element) {
                        element.textContent = String(value);
                        if (!Number.isNaN(Number(value))) this.animateCounter(element, value);
                        didUpdate = true;
                    }
                }
            });

            // Persist to localStorage when successfully updated
            if (didUpdate) {
                try { 
                    localStorage.setItem('driverPerformanceSnapshot', JSON.stringify(metrics)); 
                    console.log('[Performance] Saved to cache:', metrics);
                } catch(e) {
                    console.warn('[Performance] Failed to save to localStorage:', e);
                }
            }
        }, 300); // 300ms debounce
    }
    
    /**
     * Load cached performance stats from localStorage on init
     */
    loadCachedPerformanceStats() {
        try {
            const cached = localStorage.getItem('driverPerformanceSnapshot');
            if (cached) {
                this.cachedPerformanceStats = JSON.parse(cached);
                console.log('[Performance] Loaded from cache:', this.cachedPerformanceStats);
            }
        } catch(e) {
            console.warn('[Performance] Failed to load cached stats:', e);
        }
    }

    /**
     * Fetch and render performance stats from API (fallback)
     */
    async fetchAndRenderPerformanceStats() {
        try {
            console.log('[Performance] Fetching from dedicated endpoint...');
            const resp = await fetch('api/performance_stats.php', { credentials: 'include' });
            if (!resp.ok) {
                console.warn('[Performance Stats] Fetch failed with HTTP:', resp.status);
                return;
            }
            const json = await resp.json();
            if (json && json.success && json.stats) {
                console.log('[Performance] Received stats from API:', json.stats);
                this.renderPerformanceStats(json.stats);
            } else {
                console.warn('[Performance Stats] No stats returned from API');
            }
        } catch (err) {
            console.error('[Performance Stats] Error fetching stats:', err);
        }
    }

    /**
     * Fetch trip route information
     */
    async fetchTripRoute(trip) {
        try {
            const response = await fetch(`api/fetch_trip_route.php?trip_id=${trip.id}&company_id=${trip.company_id}`, {
                credentials: 'include'
            });
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
        const tripStatus = button.getAttribute('data-trip-status');
        const originalText = button.textContent;
        
        if (tripStatus === 'scheduled') {
            // Start Trip directly - no acceptance step needed
            await this.handleStartTripDirect(button, tripId);
        } else if (tripStatus === 'accepted' || tripStatus === 'in_transit') {
            // View Route for active trip (show panel)
            this.showRouteStopsPanel();
        } else {
            // View Details flow
            this.showTripDetails(tripId);
        }
    }
    
    /**
     * Handle starting a trip directly from scheduled status
     */
    async handleStartTripDirect(button, tripId) {
        const originalText = button.textContent;
        
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Starting...';

        try {
            // OPTIMIZATION: Start trip with single API call
            const startResponse = await fetch(`api/trip.php?action=start&trip_id=${tripId}`, {
                credentials: 'include'
            });
            const startResult = await startResponse.json();

            if (!startResult.success) {
                throw new Error(startResult.error || 'Failed to start trip');
            }

            button.innerHTML = '<i class="fas fa-check"></i> Started!';
            button.classList.add('success');
            
            this.showNotification('🚗 Trip started successfully!', 'success');
            
            // Stop auto-refresh
            this.stopDashboardRefresh();
            
            // Start GPS tracking immediately (don't wait)
            this.startGpsPolling(tripId);
            
            // Load dashboard in background while showing feedback
            this.loadDashboardData().then(() => {
                // Show route stops panel after data loads
                setTimeout(() => {
                    this.showRouteStopsPanel();
                    this.loadAndDisplayTripStops();
                }, 500);
            });
            
        } catch (error) {
            console.error('Error starting trip:', error);
            button.disabled = false;
            button.innerHTML = originalText;
            this.showError('Failed to start trip: ' + error.message);
        }
    }

    /**
     * Handle accepting a scheduled trip - DEPRECATED, kept for backward compatibility
     */
    async handleAcceptTrip(button, tripId) {
        const originalText = button.textContent;
        
        button.disabled = true;
        button.textContent = 'Accepting...';

        try {
            const response = await fetch(`api/trip.php?action=accept&trip_id=${tripId}`, {
                credentials: 'include'
            });
            const result = await response.json();

            if (result.success) {
                button.textContent = 'Accepted!';
                button.classList.add('success');
                
                // Show success message
                this.showNotification('✅ Trip accepted!', 'success');
                
                // Stop auto-refresh once trip is accepted
                this.stopDashboardRefresh();
                console.log('[DEBUG] Auto-refresh stopped - trip accepted');

                // Refresh dashboard once more to render active trip details and map
                await this.loadDashboardData();
                console.log('[DEBUG] Dashboard refreshed once after acceptance to display map UI');

                // Show the trip route modal with Start Trip functionality
                setTimeout(() => {
                    this.showAcceptedTripModal(tripId);
                }, 600);
                
                // Update the trip card to show "accepted" status
                const tripCard = button.closest('.trip-card');
                if (tripCard) {
                    const statusBadge = tripCard.querySelector('.trip-status-badge');
                    if (statusBadge) {
                        statusBadge.innerHTML = '<i class="fas fa-check"></i><span>Accepted</span>';
                        statusBadge.className = 'trip-status-badge trip-status-accepted';
                    }
                    
                    // Update button text
                    button.textContent = 'View Route';
                    button.setAttribute('data-trip-status', 'accepted');
                    button.disabled = false;
                    button.classList.remove('success');
                }
                
            } else {
                throw new Error(result.error || 'Failed to accept trip');
            }
            
        } catch (error) {
            console.error('Error accepting trip:', error);
            button.disabled = false;
            button.textContent = originalText;
            this.showError('Failed to accept trip: ' + error.message);
        }
    }

    /**
     * Show modal for accepted trip with route and Start Trip functionality
     */
    async showAcceptedTripModal(tripId) {
        try {
            // Get trip details and route
            const response = await fetch(`api/get_trip_route.php?trip_id=${tripId}`, {
                credentials: 'include'
            });
            const routeData = await response.json();
            
            if (!routeData.success) {
                throw new Error(routeData.error || 'Failed to load trip route');
            }

            const trip = routeData.trip;
            const stops = routeData.stops || [];

            // Create modal
            const modal = document.createElement('div');
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-content trip-route-modal">
                    <div class="modal-header">
                        <h3><i class="fas fa-route"></i> Trip Route - ${trip.id.slice(0, 8)}</h3>
                        <button class="modal-close">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="trip-info-section">
                            <div class="trip-details">
                                <p><strong>Status:</strong> <span class="status-accepted">✅ Accepted</span></p>
                                <p><strong>Vehicle:</strong> ${trip.vehicle_name || 'N/A'}</p>
                                <p><strong>Departure:</strong> ${this.formatDate(trip.departure_time)}</p>
                                <p><strong>Total Stops:</strong> ${stops.length}</p>
                            </div>
                        </div>
                        
                        <div class="route-stops-section">
                            <h4><i class="fas fa-map-marker-alt"></i> Route Stops</h4>
                            <div id="modalRouteStops" class="modal-stops-list">
                                ${this.generateModalStopsHTML(stops)}
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn-secondary view-map-btn" type="button">
                            <i class="fas fa-map-marked-alt"></i> Open Live Map
                        </button>
                        <button class="btn-secondary" onclick="this.closest('.modal').remove()">Close</button>
                        <button class="btn-primary start-trip-btn" data-trip-id="${tripId}">
                            <i class="fas fa-play"></i> Start Trip
                        </button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            // Add event listeners
            const closeBtn = modal.querySelector('.modal-close');
            closeBtn.addEventListener('click', () => modal.remove());
            
            // Close on background click
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.remove();
                }
            });
            
            // View live map button functionality
            const viewMapBtn = modal.querySelector('.view-map-btn');
            if (viewMapBtn) {
                viewMapBtn.addEventListener('click', () => {
                    this.scrollToTripMap();
                    setTimeout(() => modal.remove(), 400);
                });
            }

            // Start Trip button functionality
            const startTripBtn = modal.querySelector('.start-trip-btn');
            startTripBtn.addEventListener('click', async () => {
                const firstStop = stops[0];
                if (firstStop) {
                    const firstStopId = firstStop.stop_id || firstStop.id;
                    startTripBtn.disabled = true;
                    startTripBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Starting Trip...';
                    
                    await this.handleStartTrip(
                        firstStopId,
                        firstStop.outlet_name, 
                        firstStop.outlet_id, 
                        startTripBtn
                    );
                    
                    // Close modal after starting trip
                    setTimeout(() => {
                        modal.remove();
                        // Show the Route Stops panel
                        this.showRouteStopsPanel();
                    }, 2000);
                } else {
                    this.showNotification('No stops found for this trip', 'error');
                }
            });
            
            console.log('[DEBUG] Accepted trip modal displayed for trip:', tripId);
            
        } catch (error) {
            console.error('Error showing accepted trip modal:', error);
            this.showNotification('Failed to load trip route: ' + error.message, 'error');
        }
    }

    /**
     * Generate HTML for stops in modal
     */
    generateModalStopsHTML(stops) {
        if (!stops || stops.length === 0) {
            return '<p>No stops found for this trip.</p>';
        }
        
        let html = '';
        stops.forEach((stop, index) => {
            const isFirstStop = index === 0;
            const stopNumber = index + 1;
            
            html += `
                <div class="modal-stop-item ${isFirstStop ? 'first-stop' : ''}">
                    <div class="stop-number">${stopNumber}</div>
                    <div class="stop-details">
                        <div class="stop-name">${stop.outlet_name || `Stop ${stopNumber}`}</div>
                        <div class="stop-address">${stop.address || 'Address not available'}</div>
                        <div class="stop-parcels">
                            <i class="fas fa-box"></i> ${stop.parcel_count || 0} parcel(s)
                        </div>
                        ${isFirstStop ? '<div class="first-stop-indicator"><i class="fas fa-play-circle"></i> Starting Point</div>' : ''}
                    </div>
                </div>
            `;
        });
        
        return html;
    }

    /**
     * Scroll to the live map and highlight it
     */
    scrollToTripMap() {
        const mapContainer = document.querySelector('.trip-map-container-professional');
        if (!mapContainer) {
            this.showNotification('Live map is not ready yet. Please start the trip first.', 'warning');
            return;
        }

        mapContainer.classList.add('highlighted');
        mapContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });

        setTimeout(() => {
            mapContainer.classList.remove('highlighted');
        }, 2000);

        console.log('[DEBUG] Scrolled to live map container');
    }
    
    /**
     * Show route stops panel (for active trips)
     */
    showRouteStopsPanel() {
        // Scroll to route stops panel and expand it
        const stopsContainer = document.getElementById('tripStopsContainer');
        if (stopsContainer) {
            stopsContainer.classList.remove('collapsed');
            stopsContainer.scrollIntoView({ behavior: 'smooth' });
            this.loadAndDisplayTripStops();
            // this.showNotification('📍 Route stops panel opened', 'info');
        } else {
            this.showNotification('⚠️ Route stops not available - start GPS tracking first', 'warning');
        }
    }
    
    /**
     * Show trip details (fallback)
     */
    showTripDetails(tripId) {
        this.showNotification('📋 Trip details view - feature coming soon', 'info');
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
            completeTripBtn: () => this.handleCompleteTripAction()
        };

        Object.keys(quickActions).forEach(buttonId => {
            const button = document.getElementById(buttonId);
            if (button) {
                button.addEventListener('click', quickActions[buttonId]);
            }
        });
    }

    /**
     * Handle complete trip action
     */
    async handleCompleteTripAction() {
        try {
            const response = await fetch('api/driver_dashboard.php', {
                credentials: 'include'
            });
            const data = await this.safeJsonParse(response);
            
            if (data.success && data.active_trips && data.active_trips.length > 0) {
                const activeTrip = data.active_trips[0];
                
                // Check if all stops are completed
                const routeResponse = await fetch(`api/get_trip_route.php?trip_id=${activeTrip.id}`, {
                    credentials: 'include'
                });
                const routeData = await this.safeJsonParse(routeResponse);
                
                if (routeData.success && routeData.stops) {
                    const allCompleted = routeData.stops.every(stop => stop.departure_time);
                    
                    if (allCompleted) {
                        // Force complete the trip
                        const completeResponse = await fetch('api/complete_trip.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            credentials: 'include',
                            body: JSON.stringify({ trip_id: activeTrip.id })
                        });
                        
                        const result = await this.safeJsonParse(completeResponse);
                        
                        if (result.success) {
                            this.showNotification('🎉 Trip completed successfully!', 'success');
                            this.loadDashboardData(); // Refresh dashboard
                        } else {
                            throw new Error(result.error || 'Failed to complete trip');
                        }
                    } else {
                        this.showNotification('⚠️ Complete all stops before finishing trip', 'warning');
                        this.showTripProgressModal(activeTrip, routeData.stops);
                    }
                } else {
                    throw new Error('Failed to load route data');
                }
            } else {
                this.showNotification('No active trip to complete', 'warning');
            }
        } catch (error) {
            console.error('Error completing trip:', error);
            this.showNotification('❌ Failed to complete trip: ' + error.message, 'error');
        }
    }

    /**
     * Show trip progress modal
     */
    showTripProgressModal(trip, stops = null) {
        // Create modal
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class="fas fa-route"></i> Trip Progress - ${trip.id.slice(0, 8)}</h3>
                    <button class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="trip-progress-info">
                        <p><strong>Status:</strong> ${this.getStatusLabel(trip.trip_status)}</p>
                        <p><strong>Vehicle:</strong> ${trip.vehicle_name || 'N/A'}</p>
                        <p><strong>Departure:</strong> ${this.formatDate(trip.departure_time)}</p>
                    </div>
                    <div id="tripStopsProgress">
                        <p>Loading stops...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn-secondary" onclick="this.closest('.modal').remove()">Close</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Load stops if not provided
        if (!stops) {
            fetch(`api/get_trip_route.php?trip_id=${trip.id}`, {
                credentials: 'include'
            })
                .then(response => this.safeJsonParse(response))
                .then(data => {
                    if (data.success && data.stops) {
                        this.displayStopsInModal(data.stops);
                    }
                })
                .catch(error => {
                    console.error('Error loading stops:', error);
                    document.getElementById('tripStopsProgress').innerHTML = '<p>Error loading stops</p>';
                });
        } else {
            this.displayStopsInModal(stops);
        }
        
        // Close modal functionality
        modal.querySelector('.modal-close').addEventListener('click', () => {
            modal.remove();
        });
        
        // Close on background click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.remove();
            }
        });
    }

    /**
     * Display stops in modal
     */
    displayStopsInModal(stops) {
        const container = document.getElementById('tripStopsProgress');
        if (!container) return;
        
        let html = '<h4>Route Stops:</h4><div class="stops-list">';
        
        stops.forEach((stop, index) => {
            const hasArrived = stop.arrival_time;
            const hasDeparted = stop.departure_time;
            let status = 'pending';
            let statusIcon = '⏳';
            
            if (hasDeparted) {
                status = 'completed';
                statusIcon = '✅';
            } else if (hasArrived) {
                status = 'at-stop';
                statusIcon = '📍';
            }
            
            html += `
                <div class="stop-item ${status}">
                    <div class="stop-info">
                        <span class="stop-icon">${statusIcon}</span>
                        <strong>${stop.outlet_name || `Stop ${index + 1}`}</strong>
                    </div>
                    <div class="stop-times">
                        ${hasArrived ? `<small>Arrived: ${this.formatTime(stop.arrival_time)}</small>` : ''}
                        ${hasDeparted ? `<small>Departed: ${this.formatTime(stop.departure_time)}</small>` : ''}
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        container.innerHTML = html;
    }

    /**
     * Format time only (without date)
     */
    formatTime(timestamp) {
        if (!timestamp) return '';
        const date = new Date(timestamp);
        return date.toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
        });
    }

    /**
     * Toggle route stops display
     */
    toggleRouteStops() {
        this.showNotification('Route stops toggle - feature will be implemented', 'info');
    }

    /**
     * Toggle fullscreen map
     */
    toggleFullscreen() {
        const mapContainer = document.getElementById('liveLocationMapContainer');
        if (!mapContainer) return;
        
        if (!document.fullscreenElement) {
            mapContainer.requestFullscreen().catch(err => {
                console.log('Error attempting to enable fullscreen:', err);
                this.showNotification('Fullscreen not supported', 'warning');
            });
        } else {
            document.exitFullscreen();
        }
    }

    /**
     * Show trip progress
     */
    showTripProgress() {
        this.showNotification('Trip progress view - feature will be implemented', 'info');
    }

    /**
     * Start location tracking
     */
    startTracking() {
        // Don't request permission again if already checked
        if (this.locationPermissionChecked) {
            if (this.locationPermissionGranted) {
                // Permission already granted, just start tracking
                if (!this.gpsInterval) {
                    this.gpsInterval = setInterval(() => {
                        navigator.geolocation.getCurrentPosition(
                            (pos) => {
                                this.updateDriverLocation(pos.coords.latitude, pos.coords.longitude);
                            },
                            (error) => {
                                console.error('Location update error:', error);
                            },
                            { enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 }
                        );
                    }, 10000);
                    console.log('[GPS] Tracking resumed with existing permission');
                }
            }
            return; // Don't request permission again
        }
        
        if (!navigator.geolocation) {
            this.showNotification('❌ Geolocation not supported by your browser', 'error');
            this.locationPermissionChecked = true;
            return;
        }

        // Mark as checked to prevent repeated requests
        this.locationPermissionChecked = true;
        
        // Request initial location to check permissions
        navigator.geolocation.getCurrentPosition(
            (position) => {
                // Success - permission granted
                this.locationPermissionGranted = true;
                this.updateDriverLocation(position.coords.latitude, position.coords.longitude);
                
                // Start continuous tracking
                this.gpsInterval = setInterval(() => {
                    navigator.geolocation.getCurrentPosition(
                        (pos) => {
                            this.updateDriverLocation(pos.coords.latitude, pos.coords.longitude);
                        },
                        (error) => {
                            console.error('Location update error:', error);
                            // Don't spam notifications on every update failure
                        },
                        { 
                            enableHighAccuracy: true, 
                            timeout: 10000, 
                            maximumAge: 30000 
                        }
                    );
                }, 10000); // Update every 10 seconds
                
                this.showNotification('✅ Location tracking started', 'success');
            },
            (error) => {
                // Error handling based on error code
                this.locationPermissionGranted = false;
                let message = '';
                switch(error.code) {
                    case error.PERMISSION_DENIED:
                        message = '🚫 Location access denied. Please enable location permissions in your browser settings and refresh the page.';
                        break;
                    case error.POSITION_UNAVAILABLE:
                        message = '📍 Location information unavailable. Please check your device GPS settings.';
                        break;
                    case error.TIMEOUT:
                        message = '⏱️ Location request timed out. Please try again.';
                        break;
                    default:
                        message = '❌ Unable to get your location. Error: ' + error.message;
                }
                
                console.error('Geolocation error:', error);
                this.showNotification(message, 'error');
                
                // Show instructions modal only once
                this.showLocationPermissionHelp();
            },
            { 
                enableHighAccuracy: true, 
                timeout: 10000, 
                maximumAge: 0 
            }
        );
    }

    /**
     * Show location help (wrapper for banner button)
     */
    showLocationHelp() {
        this.showLocationPermissionHelp();
    }

    /**
     * Show help modal for enabling location permissions
     */
    showLocationPermissionHelp() {
        const helpMessage = `
            <div style="text-align: left; line-height: 1.6;">
                <h4 style="margin-top: 0; color: #667eea;">📍 How to Enable Location Access</h4>
                
                <p><strong>Chrome/Edge:</strong></p>
                <ol>
                    <li>Click the 🔒 lock icon in the address bar</li>
                    <li>Find "Location" and set to "Allow"</li>
                    <li>Refresh the page</li>
                </ol>
                
                <p><strong>Firefox:</strong></p>
                <ol>
                    <li>Click the 🔒 lock icon in the address bar</li>
                    <li>Click "Clear This Permission"</li>
                    <li>Refresh and click "Allow" when prompted</li>
                </ol>
                
                <p><strong>Safari:</strong></p>
                <ol>
                    <li>Safari → Settings → Websites → Location</li>
                    <li>Find this site and set to "Allow"</li>
                    <li>Refresh the page</li>
                </ol>
                
                <p style="margin-top: 15px; padding: 10px; background: #fff3cd; border-radius: 5px; font-size: 0.9em;">
                    💡 <strong>Note:</strong> Location tracking is required for route navigation and trip tracking features.
                </p>
            </div>
        `;
        
        // Create modal if it doesn't exist
        let modal = document.getElementById('locationHelpModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'locationHelpModal';
            modal.style.cssText = `
                position: fixed; 
                top: 50%; 
                left: 50%; 
                transform: translate(-50%, -50%); 
                background: white; 
                padding: 25px; 
                border-radius: 12px; 
                box-shadow: 0 10px 40px rgba(0,0,0,0.3); 
                z-index: 10000; 
                max-width: 500px; 
                width: 90%;
                max-height: 80vh;
                overflow-y: auto;
            `;
            document.body.appendChild(modal);
            
            // Add backdrop
            const backdrop = document.createElement('div');
            backdrop.id = 'locationHelpBackdrop';
            backdrop.style.cssText = `
                position: fixed; 
                top: 0; 
                left: 0; 
                width: 100%; 
                height: 100%; 
                background: rgba(0,0,0,0.5); 
                z-index: 9999;
            `;
            document.body.appendChild(backdrop);
            
            backdrop.onclick = () => {
                modal.remove();
                backdrop.remove();
            };
        }
        
        modal.innerHTML = helpMessage + `
            <button onclick="this.closest('#locationHelpModal').remove(); document.getElementById('locationHelpBackdrop').remove();" 
                    style="margin-top: 20px; padding: 10px 20px; background: #667eea; color: white; border: none; border-radius: 6px; cursor: pointer; width: 100%;">
                Got it!
            </button>
        `;
    }

    /**
     * Update driver location on map
     */
    updateDriverLocation(lat, lng) {
        if (!this.mapInstance) return;
        
        if (this.driverMarker) {
            this.driverMarker.setLatLng([lat, lng]);
        } else {
            this.driverMarker = L.marker([lat, lng]).addTo(this.mapInstance)
                .bindPopup('Your Location');
        }
        
        this.mapInstance.setView([lat, lng], 15);
    }
    
    /**
     * Handle start trip action (from first stop)
     */
    async handleStartTrip(stopId, stopName, outletId, buttonElement = null) {
        try {
            if (buttonElement) {
                buttonElement.disabled = true;
                buttonElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Starting...';
            }

            // Get active trip from cached data
            let activeTrip = this.lastDashboardData?.active_trips?.[0];
            if (!activeTrip) {
                await this.loadDashboardData();
                activeTrip = this.lastDashboardData?.active_trips?.[0];
                if (!activeTrip) {
                    throw new Error('No active trip found');
                }
            }

            console.log('[DEBUG] Starting trip:', activeTrip.id);
            
            // OPTIMIZATION: Single API call to start trip
            const startResponse = await fetch(`api/trip.php?action=start&trip_id=${activeTrip.id}`, {
                credentials: 'include'
            });
            const startResult = await startResponse.json();
            
            if (!startResult.success) {
                throw new Error(startResult.error || 'Failed to start trip');
            }
            
            // Show immediate feedback
            this.showNotification('🚗 Trip started! GPS tracking active.', 'success');
            
            if (buttonElement) {
                buttonElement.innerHTML = '<i class="fas fa-check"></i> Started';
            }
            
            // Start GPS tracking (non-blocking)
            this.startGpsPolling(activeTrip.id);
            
            // OPTIMIZATION: Only reload stops list, not entire dashboard
            setTimeout(() => {
                this.loadAndDisplayTripStops();
            }, 500);
            
        } catch (error) {
            console.error('Error starting trip:', error);
            this.showNotification('Failed to start trip: ' + error.message, 'error');
            
            if (buttonElement) {
                buttonElement.disabled = false;
                buttonElement.innerHTML = '<i class="fas fa-play"></i> Start Trip';
            }
        }
    }

    /**
     * Focus on the next active stop in the route
     */
    focusOnNextStop() {
        console.log('[DEBUG] focusOnNextStop called');
        const stopsList = document.getElementById('tripStopsList');
        if (!stopsList) {
            console.log('[DEBUG] tripStopsList element not found');
            return;
        }

        console.log('[DEBUG] Current lastRenderedStops:', this.lastRenderedStops);
        
        // Find the next stop that needs action (not yet completed)
        const nextStopData = (this.lastRenderedStops || []).find(stop => {
            const isCompleted = stop.departure_time !== null;
            console.log('[DEBUG] Checking stop:', stop.outlet_name, 'completed:', isCompleted, 'departure_time:', stop.departure_time);
            return !isCompleted;
        });
        
        console.log('[DEBUG] Next stop data found:', nextStopData);
        
        if (nextStopData) {
            console.log('[DEBUG] Focusing on next stop:', nextStopData.outlet_name);
            
            // Open the action panel for the next stop
            this.openStopActionPanel(nextStopData);
            
            const nextStopId = nextStopData.stop_id || nextStopData.id;
            const nextStopElement = nextStopId ? stopsList.querySelector(`.trip-stop-item[data-stop-id="${nextStopId}"]`) : null;
            
            console.log('[DEBUG] Next stop element found:', !!nextStopElement, 'with ID:', nextStopId);

            if (nextStopElement) {
                // Scroll to the next stop
                nextStopElement.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'center' 
                });

                // Add visual emphasis
                nextStopElement.style.transform = 'scale(1.02)';
                nextStopElement.style.boxShadow = '0 4px 12px rgba(59, 130, 246, 0.3)';
                nextStopElement.style.border = '2px solid #3b82f6';
                nextStopElement.style.transition = 'all 0.3s ease';

                // Find and highlight the appropriate action button
                const hasArrived = nextStopData.arrival_time;
                console.log('[DEBUG] Next stop has arrived:', !!hasArrived);
                
                const actionButton = hasArrived 
                    ? nextStopElement.querySelector('.trip-stop-action-btn.complete')
                    : nextStopElement.querySelector('.trip-stop-action-btn.arrive, .trip-stop-action-btn.start');
                
                console.log('[DEBUG] Action button found:', !!actionButton, 'type:', actionButton?.textContent);
                
                if (actionButton) {
                    actionButton.style.backgroundColor = '#3b82f6';
                    actionButton.style.color = 'white';
                    actionButton.style.animation = 'pulse 2s infinite';
                    
                    // Add CSS keyframes for pulse animation if not exists
                    if (!document.querySelector('#pulse-animation-style')) {
                        const style = document.createElement('style');
                        style.id = 'pulse-animation-style';
                        style.textContent = `
                            @keyframes pulse {
                                0% { transform: scale(1); }
                                50% { transform: scale(1.05); }
                                100% { transform: scale(1); }
                            }
                        `;
                        document.head.appendChild(style);
                    }
                }

                // Show notification about next stop
                const stopName = nextStopElement.querySelector('.trip-stop-name')?.textContent || 'Next Stop';
                const actionText = hasArrived ? 'Complete & Depart from' : 'Arrive at';
                this.showNotification(`📍 ${actionText}: ${stopName}`, 'info');

                // Remove emphasis after a few seconds
                setTimeout(() => {
                    nextStopElement.style.transform = '';
                    nextStopElement.style.boxShadow = '';
                    nextStopElement.style.border = '';
                    if (actionButton) {
                        actionButton.style.animation = '';
                        actionButton.style.backgroundColor = '';
                        actionButton.style.color = '';
                    }
                }, 5000);

                console.log('[DEBUG] Focused on next stop:', stopName);
            } else {
                console.log('[DEBUG] No next stop element found in DOM for ID:', nextStopId);
                // Try to re-render the stops if the element is missing
                setTimeout(() => {
                    console.log('[DEBUG] Attempting to re-render stops...');
                    this.loadAndDisplayTripStops();
                }, 1000);
            }
        } else {
            console.log('[DEBUG] No next stop found to focus on - all stops completed');
            
            // Check if there are any pending stops at all
            const pendingStops = (this.lastRenderedStops || []).filter(stop => !stop.departure_time);
            console.log('[DEBUG] Pending stops count:', pendingStops.length);
            
            if (pendingStops.length === 0) {
                // All stops completed - show trip completion
                this.showNotification('🎉 All stops completed! Ready to complete trip.', 'success');
                
                // Update trip completion footer to enable completion
                this.updateTripCompletionFooter(this.lastRenderedStops || []);
            } else {
                // There are pending stops but none found - might be a data issue
                console.warn('[DEBUG] Pending stops exist but next stop not found:', pendingStops);
                
                // Fallback: open action panel for first pending stop
                if (pendingStops.length > 0) {
                    console.log('[DEBUG] Opening action panel for first pending stop:', pendingStops[0].outlet_name);
                    this.openStopActionPanel(pendingStops[0]);
                }
            }
        }
    }

    /**
     * Handle complete stop action (combines arrive and depart)
     */
    async handleCompleteStop(stopData) {
        if (!stopData || !stopData.id) {
            this.showNotification('No stop data available', 'error');
            return;
        }

        try {
            // Show loading state
            const completeBtn = document.getElementById('completeStopBtn');
            if (completeBtn) {
                completeBtn.disabled = true;
                completeBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Completing...';
            }

            // Get current timestamp
            const timestamp = new Date().toISOString();

            // Call API to complete stop (marks both arrival and departure)
            const response = await fetch('api/complete_trip_stop.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify({
                    stop_id: stopData.id,
                    completion_time: timestamp,
                    action: 'complete'
                })
            });

            const result = await this.safeJsonParse(response);

            if (result.success) {
                if (result.trip_completed) {
                    this.showNotification(`🎉 Trip completed! All ${result.total_stops} stops finished. Well done!`, 'success');
                    
                    // Stop GPS tracking if running
                    if (this.gpsInterval) {
                        clearInterval(this.gpsInterval);
                        this.gpsInterval = null;
                    }
                    
                    // Clear trip map tracking
                    if (this.tripGpsInterval) {
                        clearInterval(this.tripGpsInterval);
                        this.tripGpsInterval = null;
                    }
                    
                    // Reload dashboard to show completion
                    setTimeout(() => this.loadDashboardData(), 500); // Faster refresh
                } else {
                    this.showNotification('✅ Completed stop at ' + (result.outlet_name || stopData.outlet_name) + ' successfully!', 'success');
                    
                    // OPTIMIZATION: Only reload stops list, not entire dashboard
                    setTimeout(() => {
                        this.loadAndDisplayTripStops();
                        // Update trip map if available
                        if (this.tripMapInstance) {
                            this.loadActiveTripRoute();
                        }
                    }, 500);
                }
                
                // Close any open modals
                const modal = document.querySelector('.modal');
                if (modal) {
                    modal.remove();
                }
            } else {
                throw new Error(result.error || 'Failed to complete stop');
            }

        } catch (error) {
            console.error('Error completing stop:', error);
            this.showNotification('❌ Failed to complete stop: ' + error.message, 'error');
        } finally {
            // Reset button state
            const completeBtn = document.getElementById('completeStopBtn');
            if (completeBtn) {
                completeBtn.disabled = false;
                completeBtn.innerHTML = '<i class="fas fa-check-circle"></i> <span>Complete Stop</span>';
            }
        }
    }

    /**
     * Handle depart from stop action
     */
    async handleDepartFromStop(stopData) {
        if (!stopData || !stopData.id) {
            this.showNotification('No stop data available', 'error');
            return;
        }

        try {
            // Show loading state
            const departBtn = document.getElementById('departStopBtn');
            if (departBtn) {
                departBtn.disabled = true;
                departBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Departing...';
            }

            // Get current timestamp
            const timestamp = new Date().toISOString();

            // Call API to mark departure
            const response = await fetch('api/update_trip_stop.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify({
                    stop_id: stopData.id,
                    action: 'depart',
                    timestamp: timestamp
                })
            });

            const result = await this.safeJsonParse(response);

            if (result.success) {
                let message = '🚚 Departed from ' + result.outlet_name + ' successfully!';
                if (result.trip_completed) {
                    message += ' Trip completed! 🎉';
                }
                this.showNotification(message, 'success');
                
                // Refresh dashboard data to show updated status
                this.loadDashboardData();
                
                // Close any open modals
                const modal = document.querySelector('.modal');
                if (modal) {
                    modal.remove();
                }
            } else {
                throw new Error(result.error || 'Failed to mark departure');
            }

        } catch (error) {
            console.error('Error marking departure:', error);
            this.showNotification('❌ Failed to mark departure: ' + error.message, 'error');
        } finally {
            // Reset button state
            const departBtn = document.getElementById('departStopBtn');
            if (departBtn) {
                departBtn.disabled = false;
                departBtn.innerHTML = '<i class="fas fa-truck"></i> Depart from Stop';
            }
        }
    }

    /**
     * Initialize the trip map in the trip card
     */
    initializeTripMap() {
        console.log('[DEBUG] initializeTripMap called');
        const mapElement = document.getElementById('tripMap');
        if (!mapElement) {
            console.log('[DEBUG] tripMap element not found');
            return;
        }
        if (this.tripMapInstance) {
            console.log('[DEBUG] tripMapInstance already exists');
            return;
        }

        console.log('[DEBUG] Initializing trip map...');
        // Load Leaflet if not already loaded
        if (!window.L) {
            console.log('[DEBUG] Loading Leaflet...');
            const script = document.createElement('script');
            script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
            script.onload = () => setTimeout(() => this.createTripMap(), 100);
            document.body.appendChild(script);
        } else {
            console.log('[DEBUG] Leaflet already loaded, creating map...');
            this.createTripMap();
        }
    }

    /**
     * Create the trip map instance with enhanced features
     */
    createTripMap() {
        console.log('[DEBUG] createTripMap called');
        const mapElement = document.getElementById('tripMap');
        if (!mapElement) {
            console.log('[DEBUG] tripMap element not found in createTripMap');
            return;
        }

        // Check if the element is visible and has dimensions
        const rect = mapElement.getBoundingClientRect();
        console.log('[DEBUG] Map element dimensions:', rect.width, 'x', rect.height);
        
        if (rect.width === 0 || rect.height === 0) {
            console.log('[DEBUG] Map element has zero dimensions, waiting and retrying...');
            setTimeout(() => this.createTripMap(), 500);
            return;
        }

        try {
            console.log('[DEBUG] Creating Leaflet map instance...');
            // Create map instance with Zambia as default center (Lusaka)
            this.tripMapInstance = L.map('tripMap').setView([-15.3875, 28.3228], 12);
        
        // Add tile layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap contributors'
        }).addTo(this.tripMapInstance);

        console.log('[DEBUG] Trip map created, forcing size calculation...');
        
        // Force map to recalculate size after a short delay
        setTimeout(() => {
            if (this.tripMapInstance) {
                console.log('[DEBUG] Calling invalidateSize() to fix map rendering...');
                this.tripMapInstance.invalidateSize();
            }
        }, 100);
        
        // Add another attempt after a longer delay in case the container isn't fully rendered
        setTimeout(() => {
            if (this.tripMapInstance) {
                console.log('[DEBUG] Second invalidateSize() call...');
                this.tripMapInstance.invalidateSize();
            }
        }, 1000);

        console.log('[DEBUG] Trip map created, initializing layers...');
        
        // Add a test marker immediately to verify map is working
        L.marker([-15.3875, 28.3228]).addTo(this.tripMapInstance)
            .bindPopup('🚚 Trip Map Active - Lusaka, Zambia')
            .openPopup();
        
        // Initialize map layers
        this.initializeMapLayers();
        
        // Get and display current location
        this.getCurrentLocationForTripMap();
        
        // Load trip route if active trip exists (shows only trip outlets, not all company outlets)
        this.loadActiveTripRoute();
        
        // Start location tracking for trip map
        this.startTripMapTracking();
        
        console.log('[DEBUG] Trip map fully initialized');
        
        // Set up interval to check if map container still exists and is visible
        this.mapHealthCheckInterval = setInterval(() => {
            const mapElement = document.getElementById('tripMap');
            if (!mapElement || !this.tripMapInstance) {
                console.log('[DEBUG] Map container missing - clearing health check');
                if (this.mapHealthCheckInterval) {
                    clearInterval(this.mapHealthCheckInterval);
                    this.mapHealthCheckInterval = null;
                }
                return;
            }
            
            // Check if map tiles are loading properly
            const rect = mapElement.getBoundingClientRect();
            if (rect.width > 0 && rect.height > 0) {
                // Ensure map size is correct
                this.tripMapInstance.invalidateSize();
            }
        }, 10000); // Check every 10 seconds
        
        } catch (error) {
            console.error('[DEBUG] Error creating trip map:', error);
            // Show error message to user
            const mapElement = document.getElementById('tripMap');
            if (mapElement) {
                mapElement.innerHTML = `
                    <div style="display: flex; align-items: center; justify-content: center; height: 100%; 
                                background: rgba(0,0,0,0.1); border-radius: 10px; color: #666;">
                        <div style="text-align: center;">
                            <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 0.5rem;"></i>
                            <br>Map initialization failed
                            <br><small>Please refresh the page</small>
                        </div>
                    </div>
                `;
            }
        }
    }

    /**
     * Initialize map layers for organizing markers
     */
    initializeMapLayers() {
        this.mapLayers = {
            driver: L.layerGroup().addTo(this.tripMapInstance),
            outlets: L.layerGroup().addTo(this.tripMapInstance),
            route: L.layerGroup().addTo(this.tripMapInstance),
            tripStops: L.layerGroup().addTo(this.tripMapInstance)
        };
    }

    /**
     * Load and display company outlets on the map
     */
    async loadCompanyOutlets() {
        try {
            console.log('[DEBUG] Loading company outlets...');
            const response = await fetch('api/fetch_company_outlets.php');
            const data = await response.json();
            
            if (data.success && data.outlets) {
                console.log('[DEBUG] Got outlets:', data.outlets.length);
                // Display outlets directly since API now returns coordinates
                this.displayOutletsOnMap(data.outlets);
            } else {
                console.log('[DEBUG] No outlets found or error:', data);
            }
        } catch (error) {
            console.error('Error loading company outlets:', error);
        }
    }

    /**
     * Fetch outlet coordinates from database
     */
    async fetchOutletCoordinates(outlets) {
        try {
            const outletDetails = [];
            
            for (const outlet of outlets) {
                try {
                    // Use the enhanced trip route API to get outlet details with coordinates
                    const response = await fetch(`../api/outlets.php?action=details&id=${outlet.id}`);
                    const result = await response.json();
                    
                    if (result.success && result.outlet) {
                        outletDetails.push({
                            id: outlet.id,
                            name: result.outlet.outlet_name,
                            address: result.outlet.address,
                            latitude: parseFloat(result.outlet.latitude),
                            longitude: parseFloat(result.outlet.longitude)
                        });
                    }
                } catch (err) {
                    console.warn(`Could not fetch details for outlet ${outlet.id}:`, err);
                }
            }
            
            return outletDetails;
        } catch (error) {
            console.error('Error fetching outlet coordinates:', error);
            return [];
        }
    }

    /**
     * Display outlets on the map
     */
    displayOutletsOnMap(outlets) {
        console.log('[DEBUG] Displaying outlets on map:', outlets);
        
        if (!this.mapLayers || !this.mapLayers.outlets) {
            console.log('[DEBUG] Map layers not ready');
            return;
        }
        
        this.mapLayers.outlets.clearLayers();
        
        let validOutlets = 0;
        outlets.forEach(outlet => {
            if (outlet.latitude && outlet.longitude && 
                !isNaN(outlet.latitude) && !isNaN(outlet.longitude)) {
                
                console.log('[DEBUG] Adding outlet marker:', outlet.outlet_name, outlet.latitude, outlet.longitude);
                validOutlets++;
                
                // Create outlet marker
                const outletIcon = L.divIcon({
                    className: 'outlet-marker',
                    html: `<div class="outlet-marker-icon">
                        <i class="fas fa-building"></i>
                    </div>`,
                    iconSize: [30, 30],
                    iconAnchor: [15, 30]
                });
                
                const marker = L.marker([outlet.latitude, outlet.longitude], { 
                    icon: outletIcon 
                }).addTo(this.mapLayers.outlets);
                
                // Add popup with outlet information
                marker.bindPopup(`
                    <div class="outlet-popup">
                        <h4>${outlet.outlet_name || outlet.name || 'Outlet'}</h4>
                        <p><i class="fas fa-map-marker-alt"></i> ${outlet.address || 'Address not available'}</p>
                        <p><small>Lat: ${outlet.latitude.toFixed(6)}, Lng: ${outlet.longitude.toFixed(6)}</small></p>
                    </div>
                `);
            } else {
                console.log('[DEBUG] Skipping outlet without valid coordinates:', outlet);
            }
        });
        
        console.log('[DEBUG] Added', validOutlets, 'outlet markers to map');
        
        // Add outlets layer to map
        if (this.tripMapInstance && validOutlets > 0) {
            this.tripMapInstance.addLayer(this.mapLayers.outlets);
        }
    }

    /**
     * Load and display active trip route
     */
    async loadActiveTripRoute() {
        try {
            console.log('[DEBUG] Loading active trip route...');
            
            // Check if we have active trip data from dashboard
            if (this.lastDashboardData && this.lastDashboardData.active_trips && this.lastDashboardData.active_trips.length > 0) {
                const activeTrip = this.lastDashboardData.active_trips[0];
                console.log('[DEBUG] Using cached active trip data:', activeTrip);
                
                if (activeTrip.route_stops_with_coords && activeTrip.route_stops_with_coords.length > 0) {
                    console.log('[DEBUG] Displaying route with', activeTrip.route_stops_with_coords.length, 'outlets from trip stops');
                    this.displayTripRoute(activeTrip.route_stops_with_coords);
                    return;
                }
            }
            
            // Fallback: Get active trip data from API
            const response = await fetch('api/driver_dashboard.php', {
                credentials: 'include'
            });
            const data = await response.json();
            
            console.log('[DEBUG] Dashboard data response:', data);
            
            if (data.success && data.active_trips && data.active_trips.length > 0) {
                const activeTrip = data.active_trips[0];
                console.log('[DEBUG] Active trip found:', activeTrip);
                
                if (activeTrip.route_stops_with_coords && activeTrip.route_stops_with_coords.length > 0) {
                    console.log('[DEBUG] Displaying route with', activeTrip.route_stops_with_coords.length, 'outlet stops');
                    this.displayTripRoute(activeTrip.route_stops_with_coords);
                } else {
                    console.log('[DEBUG] No route stops with coordinates found');
                }
            } else {
                console.log('[DEBUG] No active trip found:', data);
            }
        } catch (error) {
            console.error('Error loading trip route:', error);
        }
    }

    /**
     * Display trip route and stops on the map with Uber-like visualization
     */
    displayTripRoute(stops) {
        console.log('[DEBUG] Displaying trip route with', stops.length, 'stops:', stops);
        
        if (!this.mapLayers || !this.mapLayers.tripStops || !this.mapLayers.route) {
            console.error('[DEBUG] Map layers not ready for route display');
            return;
        }
        
        this.mapLayers.tripStops.clearLayers();
        this.mapLayers.route.clearLayers();
        
        const routeCoordinates = [];
        const completedCoordinates = [];
        const pendingCoordinates = [];
        let lastCompletedIndex = -1;
        let validStops = 0;
        
        // Process stops and build coordinate arrays
        stops.forEach((stop, index) => {
            console.log('[DEBUG] Processing stop', index + 1, ':', stop.outlet_name, stop.latitude, stop.longitude, stop.status);
            
            if (stop.latitude && stop.longitude && 
                !isNaN(stop.latitude) && !isNaN(stop.longitude)) {
                
                validStops++;
                const coord = [stop.latitude, stop.longitude];
                routeCoordinates.push(coord);
                
                // Track completed vs pending segments
                if (stop.status === 'completed' || stop.departure_time) {
                    completedCoordinates.push(coord);
                    lastCompletedIndex = index;
                } else {
                    pendingCoordinates.push(coord);
                }
                
                // Create enhanced stop marker
                const isCompleted = stop.status === 'completed' || stop.departure_time;
                const isCurrent = !isCompleted && (index === lastCompletedIndex + 1);
                
                const stopIcon = L.divIcon({
                    className: `trip-stop-marker ${isCompleted ? 'completed' : isCurrent ? 'current' : 'pending'}`,
                    html: `<div class="trip-stop-icon ${isCompleted ? 'completed' : isCurrent ? 'current' : 'pending'}">
                        <span class="stop-number">${index + 1}</span>
                        ${isCompleted ? '<i class="fas fa-check"></i>' : isCurrent ? '<i class="fas fa-clock"></i>' : '<i class="fas fa-circle"></i>'}
                    </div>`,
                    iconSize: [40, 40],
                    iconAnchor: [20, 40]
                });
                
                const marker = L.marker([stop.latitude, stop.longitude], { 
                    icon: stopIcon 
                }).addTo(this.mapLayers.tripStops);
                
                console.log('[DEBUG] Added stop marker for:', stop.outlet_name);
                
                // Enhanced popup with more details
                const statusColor = isCompleted ? '#22c55e' : isCurrent ? '#f59e0b' : '#6b7280';
                marker.bindPopup(`
                    <div class="trip-stop-popup">
                        <h4>Stop ${index + 1}: ${stop.outlet_name}</h4>
                        <p><i class="fas fa-map-marker-alt"></i> ${stop.address}</p>
                        <p><i class="fas fa-info-circle" style="color: ${statusColor}"></i> 
                           Status: <span class="status-${stop.status}" style="color: ${statusColor}; font-weight: bold;">
                           ${isCompleted ? 'Completed' : isCurrent ? 'Current Stop' : 'Pending'}
                           </span>
                        </p>
                        ${stop.arrival_time ? `<p><i class="fas fa-clock"></i> Arrived: ${new Date(stop.arrival_time).toLocaleTimeString()}</p>` : ''}
                        ${stop.departure_time ? `<p><i class="fas fa-sign-out-alt"></i> Departed: ${new Date(stop.departure_time).toLocaleTimeString()}</p>` : ''}
                        ${stop.parcel_count ? `<p><i class="fas fa-box"></i> Parcels: ${stop.parcel_count}</p>` : ''}
                    </div>
                `);
            } else {
                console.warn('[DEBUG] Skipping stop with invalid coordinates:', stop);
            }
        });
        
        console.log('[DEBUG] Valid stops:', validStops, 'Route coordinates:', routeCoordinates.length);
        
        // Draw enhanced route lines with Uber-like styling
        if (routeCoordinates.length > 0) {
            this.drawEnhancedRoute(routeCoordinates, completedCoordinates, pendingCoordinates, lastCompletedIndex);
            
            // Fit map to show entire route with padding
            const group = new L.featureGroup();
            routeCoordinates.forEach(coord => {
                group.addLayer(L.marker(coord));
            });
            this.tripMapInstance.fitBounds(group.getBounds(), { padding: [30, 30] });
            
            console.log('[DEBUG] Route visualization complete');
        } else {
            console.warn('[DEBUG] No valid route coordinates to display');
        }
    }

    /**
     * Draw enhanced route with different colors for completed/pending segments
     */
    drawEnhancedRoute(allCoordinates, completedCoordinates, pendingCoordinates, lastCompletedIndex) {
        console.log('[DEBUG] Drawing enhanced route:', {
            totalCoords: allCoordinates.length,
            completedCoords: completedCoordinates.length,
            pendingCoords: pendingCoordinates.length,
            lastCompletedIndex
        });
        
        if (allCoordinates.length < 2) {
            console.warn('[DEBUG] Not enough coordinates for route line');
            return;
        }
        
        // Draw completed route segment (green)
        if (completedCoordinates.length > 1) {
            console.log('[DEBUG] Drawing completed route segment');
            const completedRoute = L.polyline(completedCoordinates, {
                color: '#22c55e',
                weight: 6,
                opacity: 0.8,
                lineCap: 'round',
                lineJoin: 'round'
            }).addTo(this.mapLayers.route);
            
            // Add subtle shadow effect
            L.polyline(completedCoordinates, {
                color: '#16a34a',
                weight: 8,
                opacity: 0.3,
                lineCap: 'round',
                lineJoin: 'round'
            }).addTo(this.mapLayers.route);
        }
        
        // Draw pending route segment (blue/gray)
        if (lastCompletedIndex < allCoordinates.length - 1) {
            const pendingStart = Math.max(0, lastCompletedIndex);
            const pendingCoords = allCoordinates.slice(pendingStart);
            
            console.log('[DEBUG] Drawing pending route segment, coords:', pendingCoords.length);
            
            if (pendingCoords.length > 1) {
                const pendingRoute = L.polyline(pendingCoords, {
                    color: '#3b82f6',
                    weight: 5,
                    opacity: 0.7,
                    lineCap: 'round',
                    lineJoin: 'round',
                    dashArray: '15, 10'
                }).addTo(this.mapLayers.route);
                
                // Add subtle shadow for pending route
                L.polyline(pendingCoords, {
                    color: '#1e40af',
                    weight: 7,
                    opacity: 0.2,
                    lineCap: 'round',
                    lineJoin: 'round',
                    dashArray: '15, 10'
                }).addTo(this.mapLayers.route);
            }
        }
        
        // Draw full route outline for context (very subtle)
        console.log('[DEBUG] Drawing context route outline');
        L.polyline(allCoordinates, {
            color: '#e5e7eb',
            weight: 3,
            opacity: 0.4,
            lineCap: 'round',
            lineJoin: 'round'
        }).addTo(this.mapLayers.route);
        
        console.log('[DEBUG] Route drawing complete');
    }

    /**
     * Get current location for trip map with enhanced GPS tracking
     */
    getCurrentLocationForTripMap() {
        console.log('[DEBUG] Getting current location for trip map...');
        
        if (navigator.geolocation) {
            // Get initial location with high accuracy
            navigator.geolocation.getCurrentPosition(
                position => {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    const accuracy = position.coords.accuracy;
                    
                    console.log('[DEBUG] Initial location received:', lat, lng, 'accuracy:', accuracy);
                    
                    this.updateTripMapLocation(lat, lng, accuracy);
                    
                    // Center map on actual location if coordinates are valid
                    if (lat !== 0 && lng !== 0 && Math.abs(lat) <= 90 && Math.abs(lng) <= 180) {
                        this.tripMapInstance.setView([lat, lng], 14);
                        console.log('[DEBUG] Map centered on user location');
                    } else {
                        // Default to Zambia if location is invalid
                        this.tripMapInstance.setView([-15.3875, 28.3228], 12);
                        console.log('[DEBUG] Using default Zambian location (Lusaka)');
                    }
                },
                error => {
                    console.warn('Location access error for trip map:', error);
                    // Default to Zambia (Lusaka area)
                    this.tripMapInstance.setView([-15.3875, 28.3228], 12);
                    this.showNotification('Location access denied. Using default location.', 'warning');
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 30000
                }
            );
            
            // Start continuous location watching
            this.startContinuousLocationTracking();
        } else {
            console.warn('Geolocation not supported');
            // Geolocation not supported, default to Zambia
            this.tripMapInstance.setView([-15.3875, 28.3228], 12);
            this.showNotification('GPS not supported on this device', 'warning');
        }
    }

    /**
     * Start continuous location tracking like customer app
     */
    startContinuousLocationTracking() {
        console.log('[DEBUG] Starting continuous location tracking...');
        
        if (this.locationWatchId) {
            navigator.geolocation.clearWatch(this.locationWatchId);
        }
        
        this.locationWatchId = navigator.geolocation.watchPosition(
            position => {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                const accuracy = position.coords.accuracy;
                const speed = position.coords.speed;
                const heading = position.coords.heading;
                
                console.log('[DEBUG] Location update:', lat, lng, 'accuracy:', accuracy);
                
                // Store current position for other methods
                this.currentPosition = { lat, lng, accuracy };
                
                // Update location display
                this.updateLocationDisplay();
                
                // Update trip map location if available
                this.updateTripMapLocation(lat, lng, accuracy, speed, heading);
                
                // Update live location map if available
                if (this.mapInstance) {
                    this.updateLiveMapLocation(lat, lng, accuracy);
                }
                
                // Save location to server if tracking is active
                if (this.tripGpsInterval) {
                    this.saveLocationToServer(lat, lng, accuracy, speed, heading);
                }
            },
            error => {
                console.warn('Continuous location tracking error:', error);
                switch(error.code) {
                    case error.PERMISSION_DENIED:
                        this.showNotification('Location access denied by user', 'error');
                        break;
                    case error.POSITION_UNAVAILABLE:
                        this.showNotification('Location information unavailable', 'warning');
                        break;
                    case error.TIMEOUT:
                        console.log('[DEBUG] Location timeout, will retry...');
                        break;
                }
            },
            {
                enableHighAccuracy: true,
                timeout: 12000,
                maximumAge: 10000 // Use fresher location data for continuous tracking
            }
        );
    }

    /**
     * Update live location map with current driver position
     */
    updateLiveMapLocation(lat, lng, accuracy = null) {
        if (!this.mapInstance) return;
        
        console.log('[DEBUG] Updating live map location:', lat, lng);
        
        // Remove existing driver marker
        if (this.liveDriverMarker) {
            this.mapInstance.removeLayer(this.liveDriverMarker);
        }
        
        // Create enhanced driver marker
        const driverIcon = L.divIcon({
            className: 'driver-location-marker-live',
            html: `
                <div class="driver-marker-container">
                    <div class="driver-marker-pulse"></div>
                    <div class="driver-marker-icon">
                        <i class="fas fa-truck"></i>
                    </div>
                </div>
            `,
            iconSize: [30, 30],
            iconAnchor: [15, 15]
        });
        
        // Add driver marker to live map
        this.liveDriverMarker = L.marker([lat, lng], { icon: driverIcon }).addTo(this.mapInstance);
        
        // Update popup with current info
        let popupContent = `
            <div class="driver-location-popup">
                <h4><i class="fas fa-truck"></i> Your Current Location</h4>
                <p><i class="fas fa-map-marker-alt"></i> ${lat.toFixed(6)}, ${lng.toFixed(6)}</p>
                ${accuracy ? `<p><i class="fas fa-crosshairs"></i> Accuracy: ±${Math.round(accuracy)}m</p>` : ''}
                <p><i class="fas fa-clock"></i> Updated: ${new Date().toLocaleTimeString()}</p>
            </div>
        `;
        
        this.liveDriverMarker.bindPopup(popupContent);
        
        // Center map on driver location
        this.mapInstance.setView([lat, lng], 15);
        
        // Add accuracy circle if available
        if (accuracy && accuracy < 100) {
            if (this.liveAccuracyCircle) {
                this.mapInstance.removeLayer(this.liveAccuracyCircle);
            }
            
            this.liveAccuracyCircle = L.circle([lat, lng], {
                radius: accuracy,
                color: '#3b82f6',
                fillColor: '#3b82f6',
                fillOpacity: 0.1,
                weight: 2
            }).addTo(this.mapInstance);
        }
    }

    /**
     * Update driver location on trip map with enhanced visual feedback
     */
    updateTripMapLocation(lat, lng, accuracy = null, speed = null, heading = null) {
        if (!this.tripMapInstance) return;
        
        // Validate coordinates
        if (isNaN(lat) || isNaN(lng) || Math.abs(lat) > 90 || Math.abs(lng) > 180) {
            console.warn('Invalid coordinates received:', lat, lng);
            return;
        }
        
        if (this.tripDriverMarker) {
            // Update existing marker
            this.tripDriverMarker.setLatLng([lat, lng]);
            
            // Update accuracy circle if provided
            if (accuracy && this.accuracyCircle) {
                this.accuracyCircle.setLatLng([lat, lng]);
                this.accuracyCircle.setRadius(accuracy);
            }
        } else {
            // Create enhanced driver marker
            const driverIcon = L.divIcon({
                className: 'driver-marker-enhanced',
                html: `<div class="driver-marker-container">
                    <div class="driver-marker-pulse"></div>
                    <div class="driver-marker-icon">
                        <i class="fas fa-location-arrow"></i>
                    </div>
                </div>`,
                iconSize: [40, 40],
                iconAnchor: [20, 20]
            });
            
            this.tripDriverMarker = L.marker([lat, lng], { icon: driverIcon })
                .addTo(this.mapLayers.driver);
            
            // Add accuracy circle
            if (accuracy) {
                this.accuracyCircle = L.circle([lat, lng], {
                    radius: accuracy,
                    color: '#3b82f6',
                    fillColor: '#3b82f6',
                    fillOpacity: 0.1,
                    weight: 2
                }).addTo(this.mapLayers.driver);
            }
        }
        
        // Update popup with current info
        let popupContent = `
            <div class="driver-location-popup">
                <h4><i class="fas fa-truck"></i> Your Current Location</h4>
                <p><i class="fas fa-map-marker-alt"></i> ${lat.toFixed(6)}, ${lng.toFixed(6)}</p>
                ${accuracy ? `<p><i class="fas fa-crosshairs"></i> Accuracy: ±${Math.round(accuracy)}m</p>` : ''}
                ${speed !== null && speed > 0 ? `<p><i class="fas fa-tachometer-alt"></i> Speed: ${Math.round(speed * 3.6)} km/h</p>` : ''}
                <p><i class="fas fa-clock"></i> Updated: ${new Date().toLocaleTimeString()}</p>
            </div>
        `;
        
        this.tripDriverMarker.bindPopup(popupContent);
        
        // Center map on driver location on first update
        if (!this.mapCentered) {
            this.tripMapInstance.setView([lat, lng], 15);
            this.mapCentered = true;
        }
    }

    /**
     * Save location to server
     */
    async saveLocationToServer(lat, lng, accuracy, speed, heading) {
        try {
            const formData = new FormData();
            formData.append('latitude', lat);
            formData.append('longitude', lng);
            formData.append('accuracy', accuracy || 0);
            formData.append('speed', speed || 0);
            formData.append('heading', heading || 0);
            
            const response = await fetch('api/update_driver_location.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            if (!result.success) {
                console.warn('Failed to save location:', result.error);
            }
        } catch (error) {
            console.error('Error saving location:', error);
        }
    }

    /**
     * Center map on driver's current location
     */
    centerOnDriver() {
        console.log('[DEBUG] centerOnDriver called');
        
        if (this.tripDriverMarker && this.tripMapInstance) {
            // Center on driver's current location
            const latLng = this.tripDriverMarker.getLatLng();
            this.tripMapInstance.setView(latLng, 16, { animate: true });
            
            // Show popup briefly
            this.tripDriverMarker.openPopup();
            setTimeout(() => {
                this.tripDriverMarker.closePopup();
            }, 3000);
            
            this.showNotification('📍 Centered on your location', 'info');
        } else if (this.currentPosition && this.tripMapInstance) {
            // Use stored current position
            this.tripMapInstance.setView([this.currentPosition.lat, this.currentPosition.lng], 16, { animate: true });
            this.showNotification('📍 Centered on your location', 'info');
        } else if (this.tripMapInstance) {
            // Try to get current location if no marker exists
            this.getCurrentLocationForTripMap();
            this.showNotification('🔍 Getting your location...', 'info');
        } else {
            this.showNotification('⚠️ Map not ready yet', 'warning');
        }
    }

    /**
     * Toggle route stops visibility
     */
    toggleRouteStops() {
        console.log('[DEBUG] toggleRouteStops called');
        
        if (this.mapLayers && this.mapLayers.tripStops && this.tripMapInstance) {
            if (this.tripMapInstance.hasLayer(this.mapLayers.tripStops)) {
                this.tripMapInstance.removeLayer(this.mapLayers.tripStops);
                if (this.mapLayers.route) {
                    this.tripMapInstance.removeLayer(this.mapLayers.route);
                }
                this.showNotification('🚫 Route stops hidden', 'info');
            } else {
                this.tripMapInstance.addLayer(this.mapLayers.tripStops);
                if (this.mapLayers.route) {
                    this.tripMapInstance.addLayer(this.mapLayers.route);
                }
                this.showNotification('✅ Route stops visible', 'info');
            }
        } else {
            console.warn('[DEBUG] No route stops available or map not ready');
            this.showNotification('⚠️ No route stops available', 'warning');
            
            // Try to reload route data
            this.loadActiveTripRoute();
        }
    }

    /**
     * Toggle outlet markers visibility
     */
    toggleOutletMarkers() {
        console.log('[DEBUG] toggleOutletMarkers called');
        
        if (this.mapLayers && this.mapLayers.outlets && this.tripMapInstance) {
            if (this.tripMapInstance.hasLayer(this.mapLayers.outlets)) {
                this.tripMapInstance.removeLayer(this.mapLayers.outlets);
                this.showNotification('🚫 Outlet markers hidden', 'info');
            } else {
                this.tripMapInstance.addLayer(this.mapLayers.outlets);
                this.showNotification('✅ Outlet markers visible', 'info');
            }
        } else {
            console.warn('[DEBUG] No outlets available or map not ready');
            this.showNotification('⚠️ No outlet markers available - only trip stops are shown', 'info');
        }
    }

    /**
     * Fit map to show all elements
     */
    fitMapToContent() {
        console.log('[DEBUG] fitMapToContent called');
        
        if (!this.tripMapInstance) {
            this.showNotification('⚠️ Map not ready yet', 'warning');
            return;
        }
        
        const group = new L.featureGroup();
        let addedLayers = 0;
        
        // Add driver marker
        if (this.tripDriverMarker) {
            group.addLayer(this.tripDriverMarker);
            addedLayers++;
        }
        
        // Add visible layers
        if (this.mapLayers) {
            if (this.mapLayers.outlets && this.tripMapInstance.hasLayer(this.mapLayers.outlets)) {
                this.mapLayers.outlets.eachLayer(layer => {
                    group.addLayer(layer);
                    addedLayers++;
                });
            }
            if (this.mapLayers.tripStops && this.tripMapInstance.hasLayer(this.mapLayers.tripStops)) {
                this.mapLayers.tripStops.eachLayer(layer => {
                    group.addLayer(layer);
                    addedLayers++;
                });
            }
        }
        
        if (addedLayers > 0) {
            this.tripMapInstance.fitBounds(group.getBounds(), { 
                padding: [30, 30],
                maxZoom: 16 
            });
            this.showNotification(`📍 Showing all ${addedLayers} locations`, 'info');
        } else {
            // Fallback to current position
            if (this.currentPosition) {
                this.tripMapInstance.setView([this.currentPosition.lat, this.currentPosition.lng], 14);
                this.showNotification('📍 Centered on your location', 'info');
            } else {
                this.showNotification('⚠️ No locations to display', 'warning');
            }
        }
    }

    /**
     * Toggle map fullscreen mode
     */
    toggleMapFullscreen() {
        const mapContainer = document.querySelector('.trip-map-container-professional');
        const fullscreenBtn = document.querySelector('.fullscreen-btn i');
        
        if (!mapContainer) {
            this.showNotification('⚠️ Map container not found', 'warning');
            return;
        }
        
        if (!document.fullscreenElement) {
            // Enter fullscreen
            if (mapContainer.requestFullscreen) {
                mapContainer.requestFullscreen();
            } else if (mapContainer.webkitRequestFullscreen) {
                mapContainer.webkitRequestFullscreen();
            } else if (mapContainer.msRequestFullscreen) {
                mapContainer.msRequestFullscreen();
            }
            
            // Update button icon
            if (fullscreenBtn) {
                fullscreenBtn.className = 'fas fa-compress';
            }
            
            // Invalidate map size after entering fullscreen
            setTimeout(() => {
                if (this.tripMapInstance) {
                    this.tripMapInstance.invalidateSize();
                }
            }, 100);
        } else {
            // Exit fullscreen
            if (document.exitFullscreen) {
                document.exitFullscreen();
            } else if (document.webkitExitFullscreen) {
                document.webkitExitFullscreen();
            } else if (document.msExitFullscreen) {
                document.msExitFullscreen();
            }
            
            // Update button icon
            if (fullscreenBtn) {
                fullscreenBtn.className = 'fas fa-expand';
            }
        }
    }

    startTripMapTracking() {
        if (this.tripGpsInterval) {
            clearInterval(this.tripGpsInterval);
        }
        this.tripGpsInterval = setInterval(() => {
            if (!navigator.geolocation) return;
            navigator.geolocation.getCurrentPosition(position => {
                const { latitude, longitude } = position.coords;
                this.updateTripMapLocation(latitude, longitude);
            });
        }, 10000); // Update every 10 seconds (reduced frequency for performance)
    }

    /**
     * Send driver location to backend with offline caching fallback
     */
    async sendLocationUpdate(tripId, latitude, longitude, accuracy, speed, heading) {
        try {
            console.log('[DEBUG] sendLocationUpdate called', { tripId, latitude, longitude, accuracy, speed, heading });
            
            // Store the latest location locally for fallback
            this.cacheLatestLocation(tripId, latitude, longitude, accuracy, speed, heading);
            
            const locationData = {
                trip_id: tripId,
                latitude,
                longitude,
                accuracy,
                speed,
                heading,
                timestamp: new Date().toISOString()
            };
            
            // Check if we're online
            if (!navigator.onLine) {
                console.log('[DEBUG] Offline - caching location for later sync');
                if (window.locationCacheService) {
                    await window.locationCacheService.cacheLocation(locationData);
                }
                this.showNotification('📍 Location saved offline', 'info');
                return;
            }
            
            const response = await fetch('api/update_driver_location.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(locationData)
            });
            const result = await response.json();
            console.log('[DEBUG] sendLocationUpdate response', result);
            const saveBtn = document.getElementById('saveLocationBtn');
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save Location';
            }
            if (!result.success) {
                console.warn('Failed to update driver location:', result.error);
                // Cache the failed location for retry
                if (window.locationCacheService) {
                    await window.locationCacheService.cacheLocation(locationData);
                }
            }
        } catch (err) {
            console.error('Error sending driver location:', err);
            // Cache location on network error
            if (window.locationCacheService) {
                await window.locationCacheService.cacheLocation({
                    trip_id: tripId,
                    latitude,
                    longitude,
                    accuracy,
                    speed,
                    heading,
                    timestamp: new Date().toISOString()
                });
                console.log('[DEBUG] Location cached due to network error');
            }
            const saveBtn = document.getElementById('saveLocationBtn');
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save Location';
            }
        }
    }

    /**
     * Center map on driver location
     */
    centerOnDriver() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(position => {
                if (this.tripMapInstance) {
                    this.updateTripMapLocation(position.coords.latitude, position.coords.longitude);
                    this.showNotification('Centered on your location', 'success');
                }
            });
        } else {
            this.showNotification('Location access not available', 'error');
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
     * Cache the latest location locally for fallback when GPS fails or offline
     */
    cacheLatestLocation(tripId, latitude, longitude, accuracy, speed, heading) {
        const locationData = {
            tripId,
            latitude,
            longitude,
            accuracy,
            speed,
            heading,
            timestamp: Date.now(),
            cachedAt: new Date().toISOString()
        };
        
        try {
            localStorage.setItem('lastKnownLocation', JSON.stringify(locationData));
            this.lastCachedLocation = locationData;
            console.log('[DEBUG] Location cached locally:', locationData);
        } catch (e) {
            console.warn('[DEBUG] Failed to cache location to localStorage:', e);
        }
    }

    /**
     * Get the last cached location from localStorage
     */
    getLastCachedLocation() {
        try {
            const cached = localStorage.getItem('lastKnownLocation');
            if (cached) {
                const location = JSON.parse(cached);
                // Check if cache is recent (within 30 minutes)
                const cacheAge = Date.now() - location.timestamp;
                if (cacheAge < 30 * 60 * 1000) { // 30 minutes
                    return location;
                }
            }
        } catch (e) {
            console.warn('[DEBUG] Failed to get cached location:', e);
        }
        return this.lastCachedLocation || null;
    }

    /**
     * Fetch the last known location from server database as final fallback
     */
    async fetchServerLastKnownLocation() {
        try {
            console.log('[DEBUG] Fetching last known location from server...');
            const response = await fetch('api/get_driver_location.php?action=last_known');
            const data = await response.json();
            
            if (data.success && data.location) {
                console.log('[DEBUG] Server returned last known location:', data.location);
                return {
                    latitude: data.location.latitude,
                    longitude: data.location.longitude,
                    accuracy: data.location.accuracy || 100,
                    speed: data.location.speed || 0,
                    heading: data.location.heading,
                    source: 'server',
                    ageMinutes: data.age_minutes
                };
            }
            console.log('[DEBUG] No valid location from server:', data.error || 'Unknown error');
            return null;
        } catch (e) {
            console.warn('[DEBUG] Failed to fetch server location:', e);
            return null;
        }
    }

    /**
     * Start GPS polling with offline/fallback support
     */
    startGpsPolling(tripId) {
        this.stopGpsPolling(); // Clear any existing interval
        console.log('[DEBUG] startGpsPolling called with tripId:', tripId);
        
        if (!tripId) {
            console.warn('[DEBUG] No tripId provided for GPS polling');
            return;
        }
        
        // Store the current trip ID for GPS tracking
        this.currentGpsTrackingTripId = tripId;
        
        // Function to get location with multi-level fallback: GPS -> localStorage -> server
        const getLocationWithFallback = async (callback) => {
            // Try GPS first
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    position => {
                        const { latitude, longitude, accuracy, speed, heading } = position.coords;
                        callback({ latitude, longitude, accuracy, speed, heading, source: 'gps' });
                    }, 
                    async (error) => {
                        console.warn('[DEBUG] GPS error, trying fallback:', error.message);
                        
                        // Try localStorage cache first
                        const cachedLocation = this.getLastCachedLocation();
                        if (cachedLocation) {
                            console.log('[DEBUG] Using localStorage cached location as fallback');
                            callback({
                                latitude: cachedLocation.latitude,
                                longitude: cachedLocation.longitude,
                                accuracy: cachedLocation.accuracy || 100,
                                speed: cachedLocation.speed || 0,
                                heading: cachedLocation.heading,
                                source: 'cache'
                            });
                            return;
                        }
                        
                        // Final fallback: try fetching last known location from server
                        console.log('[DEBUG] No local cache, trying server fallback...');
                        const serverLocation = await this.fetchServerLastKnownLocation();
                        if (serverLocation) {
                            console.log('[DEBUG] Using server last known location as fallback');
                            callback(serverLocation);
                            return;
                        }
                        
                        console.warn('[DEBUG] No fallback location available from any source');
                    }, 
                    {
                        enableHighAccuracy: true,
                        timeout: 15000,
                        maximumAge: 5000
                    }
                );
            } else {
                // No geolocation API available, try fallbacks
                const cachedLocation = this.getLastCachedLocation();
                if (cachedLocation) {
                    callback({
                        latitude: cachedLocation.latitude,
                        longitude: cachedLocation.longitude,
                        accuracy: cachedLocation.accuracy || 100,
                        speed: cachedLocation.speed || 0,
                        heading: cachedLocation.heading,
                        source: 'cache'
                    });
                    return;
                }
                
                const serverLocation = await this.fetchServerLastKnownLocation();
                if (serverLocation) {
                    callback(serverLocation);
                }
            }
        };
        
        // Start immediate location update
        getLocationWithFallback(location => {
            console.log('[DEBUG] Initial GPS position for trip', tripId, location);
            this.updateDriverLocation(location.latitude, location.longitude);
            this.sendLocationUpdate(tripId, location.latitude, location.longitude, location.accuracy, location.speed, location.heading);
            if (location.source === 'cache') {
                this.showNotification('📍 Using locally cached location', 'info');
            } else if (location.source === 'server') {
                this.showNotification(`📍 Using server location (${location.ageMinutes || '?'}min ago)`, 'info');
            }
        });
        
        // Set up interval for regular updates with fallback
        this.gpsInterval = setInterval(() => {
            getLocationWithFallback(location => {
                console.log('[DEBUG] GPS position polled for trip', tripId, location);
                this.updateDriverLocation(location.latitude, location.longitude);
                this.sendLocationUpdate(tripId, location.latitude, location.longitude, location.accuracy, location.speed, location.heading);
            });
        }, 30000); // Update every 30 seconds (increased from 20s for better performance)
        
        console.log('[DEBUG] GPS polling started for trip:', tripId);
    }

    /**
     * Update driver status display in header
     */
    updateDriverStatusDisplay(status = 'available') {
        const statusContainer = document.querySelector('.driver-status-display');
        if (!statusContainer) {
            // Create status display if it doesn't exist
            const header = document.querySelector('.dashboard-header') || document.querySelector('header') || document.body;
            if (header) {
                const statusElement = document.createElement('div');
                statusElement.className = 'driver-status-display';
                header.appendChild(statusElement);
            }
        }
        
        const statusElement = document.querySelector('.driver-status-display');
        if (statusElement) {
            const statusConfig = {
                'available': {
                    label: 'Available',
                    icon: 'fas fa-check-circle',
                    class: 'driver-status-available'
                },
                'unavailable': {
                    label: 'Unavailable',
                    icon: 'fas fa-times-circle',
                    class: 'driver-status-unavailable'
                }
            };
            
            const config = statusConfig[status] || statusConfig['available'];
            
            statusElement.innerHTML = `
                <div class="driver-status-badge ${config.class}">
                    <i class="${config.icon}"></i>
                    <span>Driver: ${config.label}</span>
                </div>
            `;
        }
        
        // Also update location display if position is available
        this.updateLocationDisplay();
    }

    /**
     * Update location display with current coordinates
     */
    updateLocationDisplay() {
        if (!this.currentPosition) return;
        
        const locationContainer = document.querySelector('.location-display');
        if (!locationContainer) {
            // Create location display
            const statusContainer = document.querySelector('.driver-status-display');
            if (statusContainer) {
                const locationElement = document.createElement('div');
                locationElement.className = 'location-display';
                statusContainer.appendChild(locationElement);
            }
        }
        
        const locationElement = document.querySelector('.location-display');
        if (locationElement && this.currentPosition) {
            const { lat, lng, accuracy } = this.currentPosition;
            locationElement.innerHTML = `
                <div class="location-info">
                    <div class="location-coords">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>${lat.toFixed(6)}, ${lng.toFixed(6)}</span>
                    </div>
                    ${accuracy ? `<div class="location-accuracy">±${Math.round(accuracy)}m</div>` : ''}
                </div>
            `;
        }
    }

    /**
     * Start a trip
     */
    async startTrip(tripId) {
        if (confirm('Are you sure you want to start this trip?')) {
            const success = await this.updateTripStatus(tripId, 'in_transit', {
                start_location: this.currentPosition
            });
            
            if (success) {
                this.showNotification('Trip started successfully!', 'success');
            }
        }
    }

    /**
     * End a trip
     */
    async endTrip(tripId) {
        if (confirm('Are you sure you want to end this trip?')) {
            const success = await this.updateTripStatus(tripId, 'completed', {
                end_location: this.currentPosition
            });
            
            if (success) {
                this.showNotification('Trip completed successfully!', 'success');
            }
        }
    }

    /**
     * Open navigation for trip
     */
    openNavigation(tripId) {
        // Get the first incomplete stop for navigation
        const activeTrips = this.dashboardData.active_trips || [];
        const trip = activeTrips.find(t => t.id === tripId);
        
        if (!trip || !trip.stops || trip.stops.length === 0) {
            this.showNotification('No destination found for navigation', 'warning');
            return;
        }

        // Find first incomplete stop
        const nextStop = trip.stops.find(stop => !stop.is_completed);
        if (!nextStop) {
            this.showNotification('All stops completed', 'info');
            return;
        }

        // Open Google Maps navigation
        if (nextStop.latitude && nextStop.longitude) {
            const url = `https://www.google.com/maps/dir/?api=1&destination=${nextStop.latitude},${nextStop.longitude}&travelmode=driving`;
            window.open(url, '_blank');
        } else {
            this.showNotification('Stop location not available', 'warning');
        }
    }
    
    /**
     * Load and display trip stops with arrival functionality
     */
    async loadAndDisplayTripStops() {
        console.log('[DEBUG] loadAndDisplayTripStops called');
        
        if (!this.lastDashboardData || !this.lastDashboardData.active_trips || this.lastDashboardData.active_trips.length === 0) {
            console.log('[DEBUG] No active trips data available');
            return;
        }
        
        const activeTrip = this.lastDashboardData.active_trips[0];
        console.log('[DEBUG] Active trip:', activeTrip);
        console.log('[DEBUG] Origin outlet:', activeTrip.origin_outlet_name || activeTrip.origin_name);
        console.log('[DEBUG] Destination outlet:', activeTrip.destination_outlet_name || activeTrip.destination_name);
        console.log('[DEBUG] Origin ID:', activeTrip.origin_outlet_id);
        console.log('[DEBUG] Destination ID:', activeTrip.destination_outlet_id);
        
        const stops = activeTrip.route_stops_with_coords || [];
        console.log('[DEBUG] Trip stops from route_stops_with_coords:', stops);
        
        if (stops.length === 0) {
            console.log('[DEBUG] No stops found in route_stops_with_coords');
            
            // Try alternative field names
            const alternativeStops = activeTrip.stops || activeTrip.route_stops || activeTrip.trip_stops || [];
            console.log('[DEBUG] Trying alternative stops field:', alternativeStops);
            
            if (alternativeStops.length > 0) {
                console.log('[DEBUG] Found stops in alternative field, using those');
                return this.displayStops(alternativeStops);
            }
            
            // Fallback: Create display stops from origin and destination
            if (activeTrip.origin_outlet_id && activeTrip.destination_outlet_id && 
                (activeTrip.origin_outlet_name || activeTrip.origin_name) && 
                (activeTrip.destination_outlet_name || activeTrip.destination_name)) {
                
                console.log('[DEBUG] Creating fallback stops from origin and destination');
                
                const fallbackStops = [
                    {
                        id: 'origin-' + activeTrip.id,
                        stop_id: 'origin-' + activeTrip.id,
                        outlet_id: activeTrip.origin_outlet_id,
                        outlet_name: activeTrip.origin_outlet_name || activeTrip.origin_name,
                        address: activeTrip.origin_location || '',
                        stop_order: 1,
                        arrival_time: null,
                        departure_time: activeTrip.trip_status !== 'scheduled' ? activeTrip.departure_time : null,
                        latitude: activeTrip.origin_latitude,
                        longitude: activeTrip.origin_longitude,
                        parcel_count: 0
                    }
                ];
                
                // Only add destination if different from origin
                if (activeTrip.origin_outlet_id !== activeTrip.destination_outlet_id) {
                    fallbackStops.push({
                        id: 'destination-' + activeTrip.id,
                        stop_id: 'destination-' + activeTrip.id,
                        outlet_id: activeTrip.destination_outlet_id,
                        outlet_name: activeTrip.destination_outlet_name || activeTrip.destination_name,
                        address: activeTrip.destination_location || '',
                        stop_order: 2,
                        arrival_time: activeTrip.trip_status === 'completed' ? activeTrip.arrival_time : null,
                        departure_time: null,
                        latitude: activeTrip.destination_latitude,
                        longitude: activeTrip.destination_longitude,
                        parcel_count: 0
                    });
                }
                
                console.log('[DEBUG] Displaying fallback stops:', fallbackStops);
                return this.displayStops(fallbackStops);
            }
            
            // Show route overview with origin and destination if available
            console.log('[DEBUG] No outlet names available, showing route overview');
            this.showRouteOverview(activeTrip);
            return;
        }
        
        this.displayStops(stops);

        if (!this.selectedStopId) {
            this.focusOnNextStop();
        }
    }
    
    /**
     * Show route overview when no stops exist
     */
    showRouteOverview(trip) {
        const stopsList = document.getElementById('tripStopsList');
        if (!stopsList) return;
        
        if (trip && (trip.origin_name || trip.origin_outlet_name) && (trip.destination_name || trip.destination_outlet_name)) {
            const originName = trip.origin_name || trip.origin_outlet_name || 'Not set';
            const destName = trip.destination_name || trip.destination_outlet_name || 'Not set';
            
            stopsList.innerHTML = `
                <div class="trip-stop-item" style="opacity: 0.9;">
                    <div class="trip-stop-icon">
                        <i class="fas fa-circle" style="font-size: 10px;"></i>
                    </div>
                    <div class="trip-stop-details">
                        <div class="trip-stop-name">Origin</div>
                        <div class="trip-stop-address">${originName}</div>
                    </div>
                </div>
                <div class="trip-stop-item" style="opacity: 0.9;">
                    <div class="trip-stop-icon">
                        <i class="fas fa-flag-checkered" style="font-size: 10px;"></i>
                    </div>
                    <div class="trip-stop-details">
                        <div class="trip-stop-name">Destination</div>
                        <div class="trip-stop-address">${destName}</div>
                    </div>
                </div>
            `;
        } else {
            stopsList.innerHTML = '<p style="padding: 15px; text-align: center; color: #6b7280;">No route information available for this trip.</p>';
        }
    }
    
    /**
     * Update stop status in UI immediately without full refresh
     */
    updateStopStatusInUI(stopId, statusUpdate) {
        console.log('[DEBUG] Updating stop status in UI:', stopId, statusUpdate);
        
        // Update the cached data
        if (this.lastRenderedStops) {
            const stopIndex = this.lastRenderedStops.findIndex(stop => 
                (stop.stop_id || stop.id) === stopId
            );
            
            if (stopIndex >= 0) {
                this.lastRenderedStops[stopIndex] = {
                    ...this.lastRenderedStops[stopIndex],
                    ...statusUpdate
                };
                console.log('[DEBUG] Updated cached stop data');
            }
        }
        
        // Update in dashboard data
        if (this.lastDashboardData?.active_trips?.[0]?.route_stops_with_coords) {
            const stopIndex = this.lastDashboardData.active_trips[0].route_stops_with_coords.findIndex(stop =>
                (stop.stop_id || stop.id) === stopId
            );
            
            if (stopIndex >= 0) {
                this.lastDashboardData.active_trips[0].route_stops_with_coords[stopIndex] = {
                    ...this.lastDashboardData.active_trips[0].route_stops_with_coords[stopIndex],
                    ...statusUpdate
                };
                console.log('[DEBUG] Updated dashboard data stop');
            }
        }
        
        // Update the specific stop element in UI
        const stopElement = document.querySelector(`[data-stop-id="${stopId}"]`);
        if (stopElement) {
            if (statusUpdate.hasArrived || statusUpdate.arrival_time) {
                stopElement.classList.add('arrived');
                
                // Update arrive button
                const arriveBtn = stopElement.querySelector('.trip-stop-action-btn.arrive, .trip-stop-action-btn.start');
                if (arriveBtn) {
                    arriveBtn.innerHTML = '<i class="fas fa-check"></i> Arrived';
                    arriveBtn.classList.add('arrived');
                    arriveBtn.disabled = true;
                }
                
                // Enable complete button
                const completeBtn = stopElement.querySelector('.trip-stop-action-btn.complete');
                if (completeBtn) {
                    completeBtn.disabled = false;
                    completeBtn.innerHTML = '<i class="fas fa-check-circle"></i> Complete & Depart';
                }
                
                // Add arrival time display
                const arrivalTimeSpan = stopElement.querySelector('.arrival-time');
                if (arrivalTimeSpan && statusUpdate.arrival_time) {
                    arrivalTimeSpan.innerHTML = `<i class="fas fa-sign-in-alt"></i> Arrived: ${new Date(statusUpdate.arrival_time).toLocaleTimeString()}`;
                } else if (statusUpdate.arrival_time) {
                    // Add arrival time if element doesn't exist
                    const timeContainer = stopElement.querySelector('.trip-stop-time, .trip-stop-meta');
                    if (timeContainer) {
                        timeContainer.innerHTML += `<span class="arrival-time"><i class="fas fa-sign-in-alt"></i> Arrived: ${new Date(statusUpdate.arrival_time).toLocaleTimeString()}</span>`;
                    }
                }
            }
            
            if (statusUpdate.departure_time || statusUpdate.isCompleted) {
                stopElement.classList.add('completed');
                stopElement.classList.remove('active');
                
                // Update all action buttons to show completed
                const allActionBtns = stopElement.querySelectorAll('.trip-stop-action-btn');
                allActionBtns.forEach(btn => {
                    btn.innerHTML = '<i class="fas fa-check"></i> Completed';
                    btn.disabled = true;
                    btn.classList.add('completed');
                });
                
                // Add departure time display
                if (statusUpdate.departure_time) {
                    const timeContainer = stopElement.querySelector('.trip-stop-meta');
                    if (timeContainer && !timeContainer.querySelector('.departure-time')) {
                        timeContainer.innerHTML += `<span class="departure-time"><i class="fas fa-sign-out-alt"></i> Departed: ${new Date(statusUpdate.departure_time).toLocaleTimeString()}</span>`;
                    }
                }
                
                // Remove any visual emphasis
                stopElement.style.transform = '';
                stopElement.style.boxShadow = '';
                stopElement.style.border = '';
            }
        }
        
        // Update trip map route visualization if available
        if (this.tripMapInstance) {
            setTimeout(() => {
                this.loadActiveTripRoute();
            }, 100);
        }
    }

    /**
     * Display stops in the UI
     */
    displayStops(stops) {
        console.log('[DEBUG] Displaying', stops.length, 'stops');

        this.lastRenderedStops = (stops || []).map(rawStop => {
            const derivedId = rawStop.stop_id || rawStop.id;
            return {
                ...rawStop,
                stop_id: derivedId,
                id: derivedId,
                trip_id: rawStop.trip_id || this.lastDashboardData?.active_trips?.[0]?.id || null
            };
        });

        const normalizedStops = this.lastRenderedStops;

        // Show stops container
        const stopsContainer = document.getElementById('tripStopsContainer');
        if (stopsContainer) {
            stopsContainer.style.display = 'block';
        }
        
        // Render stops list
        const stopsList = document.getElementById('tripStopsList');
        if (!stopsList) {
            console.log('[DEBUG] tripStopsList element not found');
            return;
        }
        
        stopsList.innerHTML = '';
        const activeTripId = this.lastDashboardData?.active_trips?.[0]?.id || null;

        normalizedStops.forEach((stop, index) => {
            const isCompleted = stop.departure_time !== null;
            const hasArrived = stop.arrival_time !== null;
            const isFirstStop = index === 0;
            
            // A stop is "active" if:
            // 1. It's not completed yet, AND
            // 2. Either it's the first stop OR all previous stops are completed
            const allPreviousCompleted = index === 0 || normalizedStops.slice(0, index).every(prevStop => prevStop.departure_time !== null);
            const isActive = !isCompleted && allPreviousCompleted;
            
            const stopId = stop.stop_id || stop.id;
            const hasValidStopId = stopId !== undefined && stopId !== null && stopId !== '' && stopId !== 'undefined';
            
            console.log(`[DEBUG] Stop ${index + 1}:`, {
                name: stop.outlet_name,
                isCompleted,
                isActive,
                hasArrived,
                isFirstStop,
                id: stopId,
                allPreviousCompleted
            });

            let actionButtonsHtml = '';
            if (isActive && !isCompleted) {
                const actionButtons = [];

                // Show arrival button if not yet arrived (includes first stop as "Start Trip")
                if (!hasArrived) {
                    if (hasValidStopId) {
                        actionButtons.push(`
                            <button class="trip-stop-action-btn ${isFirstStop ? 'start' : 'arrive'}" 
                                    data-stop-id="${stopId}" 
                                    data-stop-name="${stop.outlet_name}" 
                                    data-outlet-id="${stop.outlet_id}">
                                <i class="fas fa-${isFirstStop ? 'play' : 'map-marker-alt'}"></i> ${isFirstStop ? 'Start Trip' : 'Arrive'}
                            </button>
                        `);
                    } else {
                        actionButtons.push('<span class="trip-stop-action-note">Stop reference unavailable</span>');
                    }
                }

                // Show completion button if arrived but not yet departed
                if (hasArrived && !isCompleted && hasValidStopId) {
                    actionButtons.push(`
                        <button class="trip-stop-action-btn complete" data-stop-id="${stopId}" data-stop-name="${stop.outlet_name}">
                            <i class="fas fa-check-circle"></i> Complete & Depart
                        </button>
                    `);
                }

                if (actionButtons.length > 0) {
                    actionButtonsHtml = `<div class="trip-stop-actions">${actionButtons.join('')}</div>`;
                }
            } else if (isCompleted) {
                // Show completed status for completed stops
                actionButtonsHtml = `
                    <div class="trip-stop-actions">
                        <span class="trip-stop-status-badge completed">
                            <i class="fas fa-check-circle"></i> Completed
                        </span>
                    </div>
                `;
            }
            
            const stopItem = document.createElement('div');
            stopItem.className = `trip-stop-item ${isCompleted ? 'completed' : isActive ? 'active' : ''}`;
            stopItem.dataset.stopId = stopId;
            stopItem.setAttribute('tabindex', '0');
            if (this.selectedStopId && this.selectedStopId === stopId) {
                stopItem.classList.add('selected');
            }
            
            stopItem.innerHTML = `
                <div class="trip-stop-icon">
                    ${isCompleted ? '<i class="fas fa-check"></i>' : (index + 1)}
                </div>
                <div class="trip-stop-details">
                    <div class="trip-stop-name">${stop.outlet_name || `Stop ${index + 1}`}</div>
                    <div class="trip-stop-address">${stop.address || 'Address not available'}</div>
                    <div class="trip-stop-meta">
                        ${hasArrived ? `<span><i class="fas fa-sign-in-alt"></i> Arrived: ${new Date(stop.arrival_time).toLocaleTimeString()}</span>` : ''}
                        ${isCompleted ? `<span><i class="fas fa-sign-out-alt"></i> Departed: ${new Date(stop.departure_time).toLocaleTimeString()}</span>` : ''}
                        ${stop.parcel_count ? `<span><i class="fas fa-box"></i> ${stop.parcel_count} parcels</span>` : ''}
                    </div>
                    ${actionButtonsHtml}
                </div>
            `;
            
            stopsList.appendChild(stopItem);

            stopItem.addEventListener('click', (event) => {
                if (event.target.closest('.trip-stop-action-btn')) {
                    return;
                }
                this.openStopActionPanel(stop);
            });

            stopItem.addEventListener('keydown', (event) => {
                if (event.target.closest('.trip-stop-action-btn')) {
                    return;
                }
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    this.openStopActionPanel(stop);
                }
            });
        });
        
        console.log('[DEBUG] Added', stops.length, 'stops to the list');
        
        // Add event listeners for arrive buttons
        stopsList.querySelectorAll('.trip-stop-action-btn.arrive').forEach(btn => {
            btn.addEventListener('click', () => {
                const stopId = btn.getAttribute('data-stop-id');
                if (!stopId || stopId === 'undefined' || stopId === 'null') {
                    this.showNotification('Missing stop reference for this destination. Please refresh and try again.', 'error');
                    return;
                }

                const stopName = btn.getAttribute('data-stop-name');
                const outletId = btn.getAttribute('data-outlet-id');
                const normalizedOutletId = outletId && outletId !== 'undefined' && outletId !== 'null' ? outletId : null;
                const payload = {
                    id: stopId,
                    stop_id: stopId,
                    outlet_id: normalizedOutletId,
                    outlet_name: stopName,
                    trip_id: activeTripId
                };

                this.handleArriveAtStop(payload, null, null, btn);
            });
        });
        
        // Add event listeners for start trip buttons (first stop)
        stopsList.querySelectorAll('.trip-stop-action-btn.start').forEach(btn => {
            console.log('[DEBUG] Adding start trip event listener to button:', btn);
            
            // Make start trip button visually interactive
            btn.style.backgroundColor = '#10b981'; // Green background
            btn.style.color = 'white';
            btn.style.cursor = 'pointer';
            btn.style.transition = 'all 0.3s ease';
            btn.style.boxShadow = '0 2px 4px rgba(16, 185, 129, 0.3)';
            
            // Add hover effect
            btn.addEventListener('mouseenter', () => {
                btn.style.backgroundColor = '#059669';
                btn.style.transform = 'translateY(-1px)';
                btn.style.boxShadow = '0 4px 8px rgba(16, 185, 129, 0.4)';
            });
            
            btn.addEventListener('mouseleave', () => {
                if (!btn.disabled) {
                    btn.style.backgroundColor = '#10b981';
                    btn.style.transform = 'translateY(0)';
                    btn.style.boxShadow = '0 2px 4px rgba(16, 185, 129, 0.3)';
                }
            });
            
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                console.log('[DEBUG] Start trip button clicked');
                const stopId = btn.getAttribute('data-stop-id');
                const stopName = btn.getAttribute('data-stop-name');
                const outletId = btn.getAttribute('data-outlet-id');
                console.log('[DEBUG] Start trip data:', { stopId, stopName, outletId });
                
                // Show loading state immediately
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Starting Trip...';
                
                this.handleStartTrip(stopId, stopName, outletId, btn);
            });
        });
        
        // Add event listeners for complete buttons
        stopsList.querySelectorAll('.trip-stop-action-btn.complete').forEach(btn => {
            btn.addEventListener('click', () => {
                const stopId = btn.getAttribute('data-stop-id');
                const stopName = btn.getAttribute('data-stop-name');
                this.handleRouteStopComplete(stopId, stopName, btn);
            });
        });

        this.updateTripCompletionFooter(normalizedStops);
        this.highlightSelectedStop();

        if (this.selectedStopId) {
            const selectedStop = normalizedStops.find(s => (s.stop_id || s.id) === this.selectedStopId);
            if (selectedStop) {
                this.renderStopActionPanel(selectedStop);
            } else {
                this.closeStopActionPanel();
            }
        }
    }

    highlightSelectedStop() {
        const stopItems = document.querySelectorAll('.trip-stop-item');
        stopItems.forEach(item => {
            const stopId = item.getAttribute('data-stop-id');
            const isSelected = this.selectedStopId && stopId === this.selectedStopId;
            item.classList.toggle('selected', isSelected);
            item.setAttribute('aria-selected', isSelected ? 'true' : 'false');
        });
    }

    openStopActionPanel(stop) {
        if (!stop) {
            return;
        }

        const stopId = stop.stop_id || stop.id;
        if (!stopId) {
            return;
        }

        this.selectedStopId = stopId;
        this.highlightSelectedStop();
        this.renderStopActionPanel(stop);
    }

    renderStopActionPanel(stop) {
        const panel = document.getElementById('tripStopActionPanel');
        if (!panel) {
            return;
        }

        const stopId = stop.stop_id || stop.id;
        const tripId = stop.trip_id || this.lastDashboardData?.active_trips?.[0]?.id || null;
        const outletName = stop.outlet_name || 'Outlet';
        const stopAddress = stop.address || 'Address not available';
        const parcelCount = stop.parcel_count || 0;
        const isArrived = Boolean(stop.arrival_time);
        const isCompleted = Boolean(stop.departure_time);
        const sequenceIndex = this.lastRenderedStops.findIndex(s => (s.stop_id || s.id) === stopId);
        const stopNumber = sequenceIndex >= 0 ? sequenceIndex + 1 : '?';

        const arrivalDisplay = isArrived ? new Date(stop.arrival_time).toLocaleString() : 'Not yet arrived';
        const departureDisplay = isCompleted ? new Date(stop.departure_time).toLocaleString() : 'Not yet completed';

        const canArrive = !isArrived;
        const canComplete = isArrived && !isCompleted;

        panel.classList.remove('hidden');
        panel.innerHTML = `
            <div class="stop-action-card" data-stop-id="${stopId}">
                <div class="stop-action-header">
                    <div>
                        <p class="stop-action-title"><i class="fas fa-store"></i> ${outletName}</p>
                        <p class="stop-action-subtitle">Stop ${stopNumber} • ${parcelCount} parcel${parcelCount === 1 ? '' : 's'}</p>
                    </div>
                    <button class="stop-action-close" type="button" title="Close stop actions">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="stop-action-body">
                    <p class="stop-action-address"><i class="fas fa-map-marker-alt"></i> ${stopAddress}</p>
                    <div class="stop-action-status-grid">
                        <div class="stop-action-status-item">
                            <span class="stop-action-status-label"><i class="fas fa-sign-in-alt"></i> Arrival</span>
                            <span class="stop-action-status-value ${isArrived ? 'status-success' : 'status-pending'}">${arrivalDisplay}</span>
                        </div>
                        <div class="stop-action-status-item">
                            <span class="stop-action-status-label"><i class="fas fa-sign-out-alt"></i> Departure</span>
                            <span class="stop-action-status-value ${isCompleted ? 'status-success' : 'status-pending'}">${departureDisplay}</span>
                        </div>
                        <div class="stop-action-status-item">
                            <span class="stop-action-status-label"><i class="fas fa-box"></i> Parcels</span>
                            <span class="stop-action-status-value">${parcelCount}</span>
                        </div>
                    </div>
                </div>
                <div class="stop-action-buttons">
                    ${canArrive ? `<button class="stop-action-btn primary" data-action="arrive">
                        <i class="fas fa-map-marker-alt"></i> Mark Arrived
                    </button>` : `<button class="stop-action-btn ghost" disabled>
                        <i class="fas fa-check"></i> Arrival recorded
                    </button>`}
                    ${canComplete ? `<button class="stop-action-btn success" data-action="complete">
                        <i class="fas fa-check-circle"></i> Complete Stop
                    </button>` : ''}
                    ${!canArrive && !canComplete ? `<button class="stop-action-btn ghost" disabled>
                        <i class="fas fa-check-double"></i> Stop completed
                    </button>` : ''}
                </div>
            </div>
        `;

        const closeBtn = panel.querySelector('.stop-action-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => this.closeStopActionPanel());
        }

        const arriveBtn = panel.querySelector('[data-action="arrive"]');
        if (arriveBtn) {
            arriveBtn.addEventListener('click', () => {
                const payload = {
                    ...stop,
                    stop_id: stopId,
                    id: stopId,
                    trip_id: tripId,
                    outlet_id: stop.outlet_id || null,
                    outlet_name: outletName
                };
                this.handleArriveAtStop(payload, outletName, payload.outlet_id, arriveBtn);
            });
        }

        const completeBtn = panel.querySelector('[data-action="complete"]');
        if (completeBtn) {
            completeBtn.addEventListener('click', () => {
                this.handleRouteStopComplete(stopId, outletName, completeBtn);
            });
        }

        panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    closeStopActionPanel() {
        const panel = document.getElementById('tripStopActionPanel');
        if (panel) {
            panel.classList.add('hidden');
            panel.innerHTML = '';
        }
        this.selectedStopId = null;
        this.highlightSelectedStop();
    }

    updateTripCompletionFooter(stops) {
        const footer = document.getElementById('tripCompletionFooter');
        if (!footer) {
            return;
        }

        if (!Array.isArray(stops) || stops.length === 0) {
            footer.classList.add('hidden');
            footer.innerHTML = '';
            return;
        }

        const totalStops = stops.length;
        const arrivedStops = stops.filter(stop => stop.arrival_time);
        const completedStops = stops.filter(stop => stop.departure_time);
        const remainingArrivals = totalStops - arrivedStops.length;
        const remainingDepartures = totalStops - completedStops.length;
        const allCompleted = remainingDepartures === 0;

        footer.classList.remove('hidden');
        footer.innerHTML = `
            <div class="trip-completion-summary">
                <div>
                    <p class="trip-completion-title"><i class="fas fa-flag-checkered"></i> Trip Progress</p>
                    <p class="trip-completion-subtitle">${completedStops.length} of ${totalStops} stops completed</p>
                </div>
                <div class="trip-completion-metrics">
                    <span class="metric-chip ${remainingArrivals === 0 ? 'metric-success' : ''}">
                        <i class="fas fa-sign-in-alt"></i> ${remainingArrivals} arrival${remainingArrivals === 1 ? '' : 's'} pending
                    </span>
                    <span class="metric-chip ${remainingDepartures === 0 ? 'metric-success' : ''}">
                        <i class="fas fa-sign-out-alt"></i> ${remainingDepartures} departure${remainingDepartures === 1 ? '' : 's'} pending
                    </span>
                </div>
            </div>
            <div class="trip-completion-actions">
                <button id="confirmTripCompletionButton" class="trip-completion-button" ${allCompleted ? '' : 'disabled'}>
                    <i class="fas fa-check"></i> Confirm Trip Completion
                </button>
                ${allCompleted ? '' : '<small class="trip-completion-hint">Complete all arrivals and departures to enable trip completion.</small>'}
            </div>
        `;

        const confirmBtn = document.getElementById('confirmTripCompletionButton');
        if (confirmBtn) {
            confirmBtn.addEventListener('click', () => this.confirmTripCompletion(confirmBtn));
        }
    }

    async confirmTripCompletion(buttonElement = null) {
        const button = buttonElement || document.getElementById('confirmTripCompletionButton');
        if (button) {
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Completing trip...';
        }

        try {
            await this.handleCompleteTripAction();
        } finally {
            if (button) {
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-check"></i> Confirm Trip Completion';
            }
        }
    }
    
    /**
     * Handle complete stop action from route stops panel
     */
    async handleRouteStopComplete(stopId, stopName, buttonElement = null) {
        console.log('[DEBUG] handleRouteStopComplete called with:', { stopId, stopName });
        
        const buttonRef = buttonElement || (typeof event !== 'undefined' ? event.target?.closest('.trip-stop-action-btn') : null);
        
        try {
            if (buttonRef) {
                buttonRef.disabled = true;
                buttonRef.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Completing...';
            }

            const response = await fetch('api/complete_trip_stop.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({
                    stop_id: stopId,
                    completion_time: new Date().toISOString()
                })
            });

            const result = await this.safeJsonParse(response);
            console.log('[DEBUG] Complete stop API response:', result);

            if (result.success) {
                this.showNotification(`✅ Completed stop at ${stopName} successfully!`, 'success');
                
                // Update UI immediately
                this.updateStopStatusInUI(stopId, { 
                    departure_time: new Date().toISOString(),
                    isCompleted: true 
                });
                
                if (buttonRef) {
                    buttonRef.innerHTML = '<i class="fas fa-check"></i> Completed';
                    buttonRef.disabled = true;
                    buttonRef.classList.add('completed');
                }
                
                // Check if trip is completed
                if (result.trip_completed) {
                    console.log('[DEBUG] Trip completed - cleaning up');
                    // Trip completed - stop GPS tracking and refresh everything
                    if (this.gpsInterval) {
                        clearInterval(this.gpsInterval);
                        this.gpsInterval = null;
                    }
                    
                    if (this.tripGpsInterval) {
                        clearInterval(this.tripGpsInterval);
                        this.tripGpsInterval = null;
                    }
                    
                    // Restart auto-refresh since trip is completed
                    this.restartAutoRefresh();
                    
                    setTimeout(() => {
                        this.loadDashboardData();
                        this.showNotification('🎉 Trip completed! All stops finished.', 'success');
                    }, 1000);
                } else {
                    console.log('[DEBUG] Trip not completed - refreshing data and focusing on next stop');
                    // Trip not completed - refresh data and focus on next stop
                    
                    // First refresh the dashboard data
                    await this.loadDashboardData();
                    console.log('[DEBUG] Dashboard data refreshed');
                    
                    // Then reload and display trip stops
                    await this.loadAndDisplayTripStops();
                    console.log('[DEBUG] Trip stops reloaded and displayed');
                    
                    // Close the action panel since this stop is completed
                    this.closeStopActionPanel();
                    console.log('[DEBUG] Action panel closed');
                    
                    // Focus on the next stop after a short delay to ensure UI is updated
                    setTimeout(() => {
                        console.log('[DEBUG] Calling focusOnNextStop...');
                        this.focusOnNextStop();
                    }, 800);
                    
                    // Update trip map if available
                    if (this.tripMapInstance) {
                        this.loadActiveTripRoute();
                    }
                    
                    this.showNotification(`🚚 Ready for next stop. ${result.completed_stops || 1} of ${result.total_stops || 1} stops completed.`, 'info');
                }

            } else {
                throw new Error(result.error || 'Failed to complete stop');
            }

        } catch (error) {
            console.error('Error completing route stop:', error);
            this.showNotification('❌ Failed to complete stop: ' + error.message, 'error');
        } finally {
            // Only reset button state if there was an error
            if (buttonRef && !buttonRef.classList.contains('completed')) {
                buttonRef.disabled = false;
                buttonRef.innerHTML = '<i class="fas fa-check"></i> Complete & Depart';
            }
        }
    }
    
    /**
     * Handle arrive at stop action
     */
    async handleArriveAtStop(stopInput, stopNameOverride = null, outletIdOverride = null, buttonElement = null) {
        const isObjectInput = stopInput !== null && typeof stopInput === 'object';
        const resolvedStopId = isObjectInput ? (stopInput.stop_id ?? stopInput.id ?? stopInput.trip_stop_id) : stopInput;
        const resolvedTripId = isObjectInput
            ? (stopInput.trip_id ?? this.lastDashboardData?.active_trips?.[0]?.id)
            : this.lastDashboardData?.active_trips?.[0]?.id;
        const resolvedOutletId = outletIdOverride ?? (isObjectInput ? stopInput.outlet_id ?? null : null);
        const resolvedStopName = stopNameOverride
            ?? (isObjectInput ? (stopInput.outlet_name || stopInput.name || stopInput.label) : null)
            ?? 'Stop';

        if (!resolvedStopId) {
            this.showNotification('Missing stop identifier. Please refresh the trip and try again.', 'error');
            return;
        }

        if (!resolvedTripId) {
            this.showNotification('Unable to determine the active trip. Refresh the dashboard and retry.', 'error');
            return;
        }

        const arriveBtn = buttonElement || document.getElementById('arriveStopBtn');
        const completeBtn = buttonElement
            ? buttonElement.closest('.trip-stop-item')?.querySelector('.trip-stop-action-btn.complete')
            : document.getElementById('completeStopBtn');

        const originalArriveHtml = arriveBtn ? arriveBtn.innerHTML : null;
        const originalArriveDisabled = arriveBtn ? arriveBtn.disabled : null;
        const originalCompleteHtml = completeBtn ? completeBtn.innerHTML : null;
        const originalCompleteDisabled = completeBtn ? completeBtn.disabled : null;

        let locationData = {};
        if (this.currentPosition) {
            locationData = {
                latitude: this.currentPosition.lat,
                longitude: this.currentPosition.lng,
                accuracy: this.currentPosition.accuracy
            };
        }

        let success = false;

        try {
            if (arriveBtn) {
                arriveBtn.disabled = true;
                arriveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Arriving...</span>';
            }

            if (completeBtn) {
                completeBtn.disabled = true;
            }

            const arrivePayload = {
                stop_id: resolvedStopId,
                trip_id: resolvedTripId,
                action: 'arrive',
                ...locationData
            };

            if (resolvedOutletId) {
                arrivePayload.outlet_id = resolvedOutletId;
            }

            const response = await fetch('api/arrive_at_stop.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(arrivePayload)
            });

            console.log('[DEBUG] Arrival API status:', response.status, response.statusText);

            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`Arrival API error (${response.status}): ${errorText || response.statusText}`);
            }

            const result = await this.safeJsonParse(response);

            if (!result.success) {
                throw new Error(result.error || 'Failed to mark arrival');
            }

            success = true;

            const parcelCount = result.data?.parcels_updated ?? 0;
            this.showNotification(
                `✓ Arrived at ${resolvedStopName}. ${parcelCount} parcel(s) marked as at_outlet`,
                'success'
            );

            if (arriveBtn) {
                arriveBtn.innerHTML = '<i class="fas fa-check"></i> <span>Arrived</span>';
                arriveBtn.classList.add('arrived');
            }

            if (completeBtn) {
                completeBtn.disabled = false;
                completeBtn.innerHTML = '<i class="fas fa-check-circle"></i> <span>Complete & Depart</span>';
            }

            // OPTIMIZATION: Only reload stops list and map, not entire dashboard
            setTimeout(() => {
                this.loadAndDisplayTripStops();
                
                if (this.tripMapInstance) {
                    this.loadActiveTripRoute();
                }
                
                // Force update the stop status in UI immediately
                this.updateStopStatusInUI(resolvedStopId, { 
                    arrival_time: new Date().toISOString(),
                    hasArrived: true 
                });
                
                this.focusOnNextStop();
            }, 200); // Reduced from 300ms to 200ms

        } catch (error) {
            console.error('Error marking arrival:', error);
            this.showNotification('Failed to mark arrival: ' + error.message, 'error');

        } finally {
            if (!success && arriveBtn) {
                arriveBtn.disabled = typeof originalArriveDisabled === 'boolean' ? originalArriveDisabled : false;
                arriveBtn.innerHTML = originalArriveHtml || '<i class="fas fa-map-marker-alt"></i> <span>Arrive at Stop</span>';
            } else if (success && arriveBtn) {
                arriveBtn.disabled = true;
            }

            if (!success && completeBtn) {
                completeBtn.disabled = typeof originalCompleteDisabled === 'boolean' ? originalCompleteDisabled : false;
                if (originalCompleteHtml) {
                    completeBtn.innerHTML = originalCompleteHtml;
                }
            }
        }
    }
    
    /**
     * Toggle stops panel visibility
     */
    toggleStopsPanel() {
        const stopsContainer = document.getElementById('tripStopsContainer');
        if (stopsContainer) {
            const wasCollapsed = stopsContainer.classList.contains('collapsed');
            stopsContainer.classList.toggle('collapsed');
            
            // If expanding (was collapsed, now not collapsed), load the stops
            if (wasCollapsed) {
                console.log('[DEBUG] Panel expanded, loading trip stops...');
                this.loadAndDisplayTripStops();
            }
        }
    }
    
    /**
     * Update GPS stats display in trip map
     */
    updateTripGPSStats(location) {
        const accuracyValue = document.getElementById('tripAccuracyValue');
        const speedValue = document.getElementById('tripSpeedValue');
        const headingValue = document.getElementById('tripHeadingValue');
        
        if (accuracyValue) {
            accuracyValue.textContent = location.accuracy ? `${Math.round(location.accuracy)}m` : '-- m';
        }
        
        if (speedValue) {
            const speedKmh = location.speed !== null && location.speed !== undefined ? Math.round(location.speed * 3.6) : 0;
            speedValue.textContent = `${speedKmh} km/h`;
        }
        
        if (headingValue) {
            if (location.heading !== null && location.heading !== undefined) {
                const direction = this.getDirection(location.heading);
                headingValue.textContent = `${Math.round(location.heading)}° (${direction})`;
            } else {
                headingValue.textContent = '--°';
            }
        }
    }
    
    /**
     * Debug function to force refresh and focus on next stop
     */
    async debugForceRefreshAndFocus() {
        console.log('[DEBUG] Force refresh and focus called');
        try {
            await this.loadDashboardData();
            console.log('[DEBUG] Dashboard data loaded');
            
            await this.loadAndDisplayTripStops();
            console.log('[DEBUG] Trip stops loaded and displayed');
            
            setTimeout(() => {
                console.log('[DEBUG] Attempting to focus on next stop...');
                this.focusOnNextStop();
            }, 500);
        } catch (error) {
            console.error('[DEBUG] Error in force refresh:', error);
        }
    }

    /**
     * Get compass direction from heading
     */
    getDirection(heading) {
        const directions = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'];
        const index = Math.round(heading / 45) % 8;
        return directions[index];
    }
}

// Initialize the dashboard
const driverDashboard = new DriverDashboard();

// Make it globally accessible for debugging and onclick handlers
window.driverDashboard = driverDashboard;
window.dashboard = driverDashboard;