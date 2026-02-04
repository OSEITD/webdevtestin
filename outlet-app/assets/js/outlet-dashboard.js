
let dashboardData = null;
let updateInterval = null;
let isModalOpen = false;

const CACHE_KEY = 'dashboard_data';
const CACHE_DURATION = 2 * 60 * 1000; 

function getCachedData() {
    try {
        const cached = localStorage.getItem(CACHE_KEY);
        if (!cached) return null;
        
        const data = JSON.parse(cached);
        const now = Date.now();
        
        
        if (now - data.timestamp < CACHE_DURATION) {
            console.log('Using cached dashboard data');
            return data.data;
        } else {
            console.log('Cache expired, removing...');
            localStorage.removeItem(CACHE_KEY);
            return null;
        }
    } catch (error) {
        console.error('Error reading cache:', error);
        localStorage.removeItem(CACHE_KEY);
        return null;
    }
}

function setCachedData(data) {
    try {
        const cacheObj = {
            data: data,
            timestamp: Date.now()
        };
        localStorage.setItem(CACHE_KEY, JSON.stringify(cacheObj));
        console.log('Dashboard data cached');
    } catch (error) {
        console.error('Error caching data:', error);
    }
}

function refreshDashboard() {
    console.log('Manual refresh triggered');
    localStorage.removeItem(CACHE_KEY); 
    fetchDashboardData(true); 
}

function clearDashboardCache() {
    localStorage.removeItem(CACHE_KEY);
    console.log('Dashboard cache cleared');
}

document.addEventListener('DOMContentLoaded', function() {
    initializeDashboard();
    setupEventListeners();
});

function initializeDashboard() {
    console.log('Initializing dashboard...');
    
    
    const cachedData = getCachedData();
    if (cachedData) {
        dashboardData = cachedData;
        updateDashboardUI();
        
        
        const liveIndicator = document.getElementById('liveIndicator');
        if (liveIndicator) {
            liveIndicator.classList.add('cached');
            liveIndicator.title = 'Showing cached data, refreshing...';
        }
        
        
        if (typeof window.markDashboardDataLoaded === 'function') {
            window.markDashboardDataLoaded();
        }
    } else {
        
        console.log('No cached data, will show loading briefly');
        setTimeout(() => {
            if (typeof window.markDashboardDataLoaded === 'function') {
                console.log('No data after 2s, notifying loading overlay anyway');
                window.markDashboardDataLoaded();
            }
        }, 2000); 
    }
    
    
    fetchDashboardData(cachedData ? false : true); 
    setupAutoRefresh();
    
    
    const liveIndicator = document.getElementById('liveIndicator');
    if (liveIndicator) {
        liveIndicator.classList.add('connected');
    }
}

function setupEventListeners() {
    
    const menuBtn = document.getElementById('menuBtn');
    const closeMenu = document.getElementById('closeMenu');
    const sidebar = document.getElementById('sidebar');
    const menuOverlay = document.getElementById('menuOverlay');

    if (menuBtn && sidebar && menuOverlay) {
        menuBtn.addEventListener('click', () => {
            sidebar.classList.add('active');
            menuOverlay.classList.add('active');
        });

        const closeSidebar = () => {
            sidebar.classList.remove('active');
            menuOverlay.classList.remove('active');
        };

        if (closeMenu) {
            closeMenu.addEventListener('click', closeSidebar);
        }
        menuOverlay.addEventListener('click', closeSidebar);
    }

    
    const refreshBtn = document.getElementById('refreshActivity');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', () => {
            fetchDashboardData(true);
        });
    }

    
    const activityFilter = document.getElementById('activityFilter');
    if (activityFilter) {
        activityFilter.addEventListener('change', filterActivities);
    }

    const activitySearch = document.getElementById('activitySearch');
    if (activitySearch) {
        activitySearch.addEventListener('input', debounce(filterActivities, 300));
    }

    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && isModalOpen) {
            closeDashboardModal();
        }
    });

    
    document.addEventListener('click', function(e) {
        const modal = document.getElementById('dashboardModal');
        if (e.target === modal && isModalOpen) {
            closeDashboardModal();
        }
    });
}

async function fetchDashboardData(showNotification = false) {
    let apiUrl = '../api/dashboard/dashboard_emergency.php'; 
    
    try {
        setLoadingState(true);
        
        
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 15000); 
        
        let response;
        try {
            console.log('Attempting fast API:', apiUrl);
            response = await fetch(apiUrl, {
                signal: controller.signal,
                cache: 'no-cache'
            });
            console.log('Fast API response status:', response.status);
        } catch (fastError) {
            console.log('Fast API failed:', fastError.message);
            console.log('Trying regular API...');
            apiUrl = '../api/dashboard/dashboard_stats.php';
            try {
                response = await fetch(apiUrl, {
                    signal: controller.signal,
                    cache: 'no-cache'
                });
                console.log('Regular API response status:', response.status);
            } catch (regularError) {
                console.log('Regular API also failed, trying emergency fallback...');
                apiUrl = '../api/dashboard/dashboard_emergency.php';
                response = await fetch(apiUrl, {
                    signal: controller.signal,
                    cache: 'no-cache'
                });
                console.log('Emergency API response status:', response.status);
            }
        }
        
        clearTimeout(timeoutId);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const responseText = await response.text();
        if (!responseText.trim()) {
            throw new Error('Empty response from server');
        }
        
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (jsonError) {
            console.error('JSON parsing error:', jsonError);
            console.error('Response was:', responseText.substring(0, 200));
            throw new Error('Invalid JSON response from server');
        }
        
        if (data.success) {
            dashboardData = data.data;
            
            
            if (!data.debug || data.debug.api_version !== 'emergency_minimal') {
                setCachedData(data.data);
            }
            
            
            if (data.debug) {
                console.log('Dashboard API used:', data.debug.api_version);
                console.log('Cached data:', data.cached || false);
                console.log('Execution time:', data.debug.total_execution_time || 'unknown');
            }
            
            updateDashboardUI();
            
            
            if (typeof window.markDashboardDataLoaded === 'function') {
                window.markDashboardDataLoaded();
            }

            if (showNotification) {
                showUpdateNotification();
            }

            
            const liveIndicator = document.getElementById('liveIndicator');
            if (liveIndicator) {
                liveIndicator.classList.remove('disconnected', 'cached');
                liveIndicator.classList.add('connected');
                liveIndicator.title = 'Live data - Last updated: ' + new Date().toLocaleTimeString();
            }

            
            (async function fetchFastAndReplace() {
                try {
                    const fastController = new AbortController();
                    const fastTimeout = setTimeout(() => fastController.abort(), 8000); 
                    const fastUrl = '../api/dashboard/dashboard_stats_fast.php';
                    console.log('Background: fetching fast dashboard API:', fastUrl);

                    const fastResp = await fetch(fastUrl, {
                        signal: fastController.signal,
                        cache: 'no-cache'
                    });
                    clearTimeout(fastTimeout);

                    if (!fastResp.ok) {
                        console.log('Background fast API returned HTTP', fastResp.status);
                        return;
                    }

                    const fastText = await fastResp.text();
                    if (!fastText.trim()) {
                        console.log('Background fast API returned empty response');
                        return;
                    }

                    let fastData;
                    try {
                        fastData = JSON.parse(fastText);
                    } catch (e) {
                        console.log('Background fast API JSON parse error:', e.message);
                        return;
                    }

                    if (fastData && fastData.success) {
                        
                        dashboardData = fastData.data;
                        try { setCachedData(fastData.data); } catch (e) {  }
                        updateDashboardUI();
                        console.log('Dashboard updated with fast stats in background');
                        if (showNotification) {
                            showUpdateNotification();
                        }
                    } else {
                        console.log('Background fast API returned success=false');
                    }
                } catch (err) {
                    if (err.name === 'AbortError') {
                        console.log('Background fast API fetch aborted due to timeout');
                    } else {
                        console.log('Background fast API fetch failed:', err.message || err);
                    }
                }
            })();
        } else {
            console.error('API returned error:', data.error);
            showErrorState(data.error || 'Failed to fetch dashboard data');
            
            
            if (typeof window.markDashboardDataLoaded === 'function') {
                window.markDashboardDataLoaded();
            }
        }
        
    } catch (error) {
        if (error.name === 'AbortError') {
            console.error('Dashboard fetch timeout after 15 seconds');
            console.error('Last attempted API:', apiUrl || 'unknown');
            showErrorState('Request timeout - Dashboard API is slow. Please check your database connection.');
        } else {
            console.error('Dashboard fetch error:', error);
            console.error('API URL:', apiUrl || 'unknown');
            showErrorState('Failed to load dashboard data: ' + error.message);
        }
        
        
        if (typeof window.markDashboardDataLoaded === 'function') {
            window.markDashboardDataLoaded();
        }
        
        
        const liveIndicator = document.getElementById('liveIndicator');
        if (liveIndicator) {
            liveIndicator.classList.remove('connected', 'cached');
            liveIndicator.classList.add('disconnected');
            liveIndicator.title = 'Disconnected - Using cached data if available';
        }
        
        
        if (!dashboardData) {
            const cachedData = getCachedData();
            if (cachedData) {
                console.log('Using cached data due to fetch failure');
                dashboardData = cachedData;
                updateDashboardUI();
                
                
                if (liveIndicator) {
                    liveIndicator.classList.remove('disconnected');
                    liveIndicator.classList.add('cached');
                    liveIndicator.title = 'Using cached data - Server unavailable';
                }
            }
        }
        
        
        if (error.name === 'AbortError') {
            setTimeout(() => {
                console.log('Attempting dashboard recovery...');
                
                loadMinimalDashboard();
            }, 2000);
        }
    } finally {
        setLoadingState(false);
    }
}

function loadMinimalDashboard() {
    console.log('Loading minimal dashboard fallback...');
    
    
    dashboardData = {
        parcels: { total: '...' },
        trips: { total: '...' },
        vehicles: { total: '...' },
        revenue: { total: '...' }
    };
    
    
    const statsCards = document.querySelectorAll('.stat-value');
    statsCards.forEach(card => {
        card.textContent = 'Loading...';
        card.style.opacity = '0.6';
    });
    
    
    const errorDiv = document.getElementById('errorState');
    if (errorDiv) {
        errorDiv.innerHTML = `
            <div style="background: #fef3c7; border: 1px solid #f59e0b; color: #92400e; padding: 1rem; border-radius: 8px; margin: 1rem 0;">
                <h4 style="margin: 0 0 0.5rem 0; font-size: 1rem;">Dashboard Loading Slowly</h4>
                <p style="margin: 0; font-size: 0.875rem;">
                    The dashboard is taking longer than usual to load. This might be due to a slow internet connection or server load.
                    <button onclick="location.reload()" style="background: #f59e0b; color: white; border: none; padding: 0.25rem 0.5rem; border-radius: 4px; margin-left: 0.5rem; cursor: pointer;">
                        Retry
                    </button>
                </p>
            </div>
        `;
        errorDiv.style.display = 'block';
    }
}

function updateDashboardUI() {
    if (!dashboardData) {
        console.log('No dashboard data available');
        return;
    }
    
    try {
        console.log('Updating dashboard UI with data:', dashboardData);
        
        
        updateElement('pendingAtOutletCount', dashboardData.parcels.at_outlet || dashboardData.parcels.pending_at_outlet);
        updateElement('inTransitCount', dashboardData.parcels.in_transit);
        updateElement('completedCount', dashboardData.parcels.completed);
        updateElement('delayedUrgentCount', dashboardData.parcels.delayed_urgent);
        
        
        console.log('Trip data:', dashboardData.trips);
        updateElement('upcomingTripsCount', dashboardData.trips.upcoming || dashboardData.trips.scheduled);
        updateElement('inTransitTripsCount', dashboardData.trips.in_transit);
        updateElement('completedTripsCount', dashboardData.trips.completed_today);
        
        
        console.log('Vehicle data:', dashboardData.vehicles);
        updateElement('availableVehiclesCount', dashboardData.vehicles.available);
        updateElement('unavailableVehiclesCount', dashboardData.vehicles.unavailable);
        updateElement('assignedTripsCount', dashboardData.vehicles.assigned_to_trips || dashboardData.vehicles.out_for_delivery);
        
        
        console.log('Revenue data:', dashboardData.revenue);
        const currency = 'ZMW'; 
        updateElement('revenueTodayCount', `${currency} ${parseFloat(dashboardData.revenue.today || 0).toFixed(2)}`);
        updateElement('revenueWeekCount', `${currency} ${parseFloat(dashboardData.revenue.week || 0).toFixed(2)}`);
        updateElement('codCollectionsCount', `${currency} ${parseFloat(dashboardData.revenue.cod_collections || 0).toFixed(2)}`);
        updateElement('transactionCount', dashboardData.revenue.transactions_today || 0);
        
        
        if (dashboardData.drivers) {
            console.log('Driver data:', dashboardData.drivers);
            
        }
        
        console.log('Dashboard UI updated successfully');
    } catch (error) {
        console.error('Error updating dashboard UI:', error);
        showErrorState('Error displaying dashboard data');
    }
}

function updateElement(id, value) {
    const element = document.getElementById(id);
    if (element) {
        const newValue = String(value || 0);
        if (element.textContent !== newValue) {
            element.textContent = newValue;
            element.classList.add('updated');
            setTimeout(() => element.classList.remove('updated'), 600);
        }
    }
}

function setLoadingState(loading) {
    const metricCards = document.querySelectorAll('.metric-card');
    metricCards.forEach(card => {
        if (loading) {
            card.classList.add('loading');
        } else {
            card.classList.remove('loading');
        }
    });
}

function showErrorState(message) {
    console.error('Dashboard error:', message);
    
    
    const counters = [
        'pendingAtOutletCount', 'inTransitCount', 'completedCount', 'delayedUrgentCount',
        'upcomingTripsCount', 'inTransitTripsCount', 'completedTripsCount',
        'availableVehiclesCount', 'unavailableVehiclesCount', 'assignedTripsCount',
        'revenueTodayCount', 'revenueWeekCount', 'codCollectionsCount', 'transactionCount'
    ];
    
    counters.forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = id.includes('revenue') || id.includes('cod') ? 'ZMW 0.00' : '0';
        }
    });
}

function setupAutoRefresh() {
    
    let lastDashboardHash = '';
    updateInterval = setInterval(() => {
        if (!isModalOpen) {
            
            fetch('../api/dashboard/dashboard_stats_fast.php')
                .then(res => res.json())
                .then(newData => {
                    const newHash = JSON.stringify(newData);
                    if (newHash !== lastDashboardHash) {
                        lastDashboardHash = newHash;
                        fetchDashboardData();
                    }
                })
                .catch(error => {
                    console.log('Auto-refresh check failed:', error.message);
                    
                    fetchDashboardData();
                });
        }
    }, 120000); 
}

function showUpdateNotification() {
    const notification = document.getElementById('updateNotification');
    if (notification) {
        notification.classList.add('show');
        setTimeout(() => {
            notification.classList.remove('show');
        }, 3000);
    }
}

function showParcelsPendingAtOutlet() {
    fetchParcelDetails('pending').then(parcels => {
        const title = `Parcels Pending at Outlet (${parcels.length})`;
        
        let content = '<div class="modal-summary">Parcels waiting to be dispatched from your outlet.</div>';
        
        if (parcels.length === 0) {
            content += '<div class="no-data">No parcels pending at this outlet.</div>';
        } else {
            content += `
                <table class="dashboard-table">
                    <thead>
                        <tr>
                            <th>Tracking Number</th>
                            <th>Sender</th>
                            <th>Recipient</th>
                            <th>Origin Outlet</th>
                            <th>Destination Outlet</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            parcels.forEach(parcel => {
                content += `
                    <tr>
                        <td><strong>${parcel.track_number || 'N/A'}</strong></td>
                        <td>${parcel.sender_name || 'N/A'}</td>
                        <td>${parcel.receiver_name || 'N/A'}</td>
                        <td>${parcel.origin_outlet_name || 'N/A'}</td>
                        <td>${parcel.destination_outlet_name || 'N/A'}</td>
                        <td>${formatDateTime(parcel.created_at)}</td>
                    </tr>
                `;
            });
            
            content += '</tbody></table>';
        }
        
        showDashboardModal(title, content);
    }).catch(error => {
        console.error('Error fetching pending parcels:', error);
        showMessage('Failed to load pending parcels');
    });
}

function showParcelsInTransit() {
    fetchParcelDetails('in_transit').then(parcels => {
        const title = `Parcels in Transit (${parcels.length})`;
        
        let content = '<div class="modal-summary">Parcels currently being transported on trips.</div>';
        
        if (parcels.length === 0) {
            content += '<div class="no-data">No parcels currently in transit.</div>';
        } else {
            content += `
                <table class="dashboard-table">
                    <thead>
                        <tr>
                            <th>Tracking Number</th>
                            <th>Trip Code</th>
                            <th>Vehicle</th>
                            <th>Departure</th>
                            <th>Destination</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            parcels.forEach(parcel => {
                content += `
                    <tr>
                        <td><strong>${parcel.track_number || 'N/A'}</strong></td>
                        <td>${parcel.trip_code || 'N/A'}</td>
                        <td>${parcel.vehicle_name || 'N/A'} (${parcel.vehicle_plate || 'N/A'})</td>
                        <td>${formatDateTime(parcel.departure_time)}</td>
                        <td>${parcel.destination_outlet_name || 'N/A'}</td>
                    </tr>
                `;
            });
            
            content += '</tbody></table>';
        }
        
        showDashboardModal(title, content);
    }).catch(error => {
        console.error('Error fetching in-transit parcels:', error);
        showMessage('Failed to load in-transit parcels');
    });
}

function showParcelsCompleted() {
    fetchParcelDetails('completed').then(parcels => {
        const title = `Completed Parcels Today (${parcels.length})`;
        
        let content = '<div class="modal-summary">Parcels delivered successfully today.</div>';
        
        if (parcels.length === 0) {
            content += '<div class="no-data">No parcels delivered today.</div>';
        } else {
            content += `
                <table class="dashboard-table">
                    <thead>
                        <tr>
                            <th>Tracking Number</th>
                            <th>Recipient</th>
                            <th>Delivery Date</th>
                            <th>Origin</th>
                            <th>Destination</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            parcels.forEach(parcel => {
                content += `
                    <tr>
                        <td><strong>${parcel.track_number || 'N/A'}</strong></td>
                        <td>${parcel.receiver_name || 'N/A'}</td>
                        <td>${formatDateTime(parcel.delivery_date)}</td>
                        <td>${parcel.origin_outlet_name || 'N/A'}</td>
                        <td>${parcel.destination_outlet_name || 'N/A'}</td>
                    </tr>
                `;
            });
            
            content += '</tbody></table>';
        }
        
        showDashboardModal(title, content);
    }).catch(error => {
        console.error('Error fetching completed parcels:', error);
        showMessage('Failed to load completed parcels');
    });
}

function showParcelsDelayedUrgent() {
    fetchParcelDetails('urgent').then(parcels => {
        const title = `Delayed / Urgent Parcels (${parcels.length})`;
        
        let content = '<div class="modal-summary">Parcels that are delayed or marked as urgent priority.</div>';
        
        if (parcels.length === 0) {
            content += '<div class="no-data">No delayed or urgent parcels.</div>';
        } else {
            content += `
                <table class="dashboard-table">
                    <thead>
                        <tr>
                            <th>Tracking Number</th>
                            <th>Sender</th>
                            <th>Recipient</th>
                            <th>Days Overdue</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            parcels.forEach(parcel => {
                content += `
                    <tr>
                        <td><strong>${parcel.track_number || 'N/A'}</strong></td>
                        <td>${parcel.sender_name || 'N/A'}</td>
                        <td>${parcel.receiver_name || 'N/A'}</td>
                        <td><span class="status-badge high">${parcel.days_overdue || 'N/A'} days</span></td>
                        <td><span class="status-badge ${(parcel.status || '').toLowerCase().replace(' ', '-')}">${parcel.status || 'Unknown'}</span></td>
                    </tr>
                `;
            });
            
            content += '</tbody></table>';
        }
        
        showDashboardModal(title, content);
    }).catch(error => {
        console.error('Error fetching urgent parcels:', error);
        showMessage('Failed to load urgent parcels');
    });
}

function showUpcomingTrips() {
    
    fetchTripDetails('scheduled').then(trips => {
        const title = `Upcoming Trips (${trips.length})`;
        
        let content = '<div class="modal-summary">Trips scheduled to depart from your outlet.</div>';
        
        if (trips.length === 0) {
            content += '<div class="no-data">No upcoming trips scheduled.</div>';
        } else {
            content += `
                <table class="dashboard-table">
                    <thead>
                        <tr>
                            <th>Vehicle</th>
                            <th>Status</th>
                            <th>Departure Time</th>
                            <th>Arrival Time</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            trips.forEach(trip => {
                const vehicle = trip.vehicle || {};
                const tripStatus = trip.trip_status || 'Unknown';
                
                content += `
                    <tr>
                        <td><strong>${vehicle.name || 'Unknown'} (${vehicle.plate_number || 'N/A'})</strong></td>
                        <td><span class="status-badge ${tripStatus.toLowerCase()}">${tripStatus}</span></td>
                        <td>${trip.departure_time ? formatDateTime(trip.departure_time) : 'Not set'}</td>
                        <td>${trip.arrival_time ? formatDateTime(trip.arrival_time) : 'Not set'}</td>
                    </tr>
                `;
            });
            
            content += '</tbody></table>';
        }
        
        showDashboardModal(title, content);
    }).catch(error => {
        console.error('Error fetching trip details:', error);
        showMessage('Failed to load trip details');
    });
}

function showInTransitTrips() {
    
    fetchTripDetails('in_transit').then(trips => {
        const title = `In-Transit Trips (${trips.length})`;
        
        let content = '<div class="modal-summary">Trips currently in progress that include your outlet.</div>';
        
        if (trips.length === 0) {
            content += '<div class="no-data">No trips currently in transit.</div>';
        } else {
            content += `
                <table class="dashboard-table">
                    <thead>
                        <tr>
                            <th>Vehicle</th>
                            <th>Status</th>
                            <th>Departure Time</th>
                            <th>Arrival Time</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            trips.forEach(trip => {
                const vehicle = trip.vehicle || {};
                const tripStatus = trip.trip_status || 'Unknown';
                
                content += `
                    <tr>
                        <td><strong>${vehicle.name || 'Unknown'} (${vehicle.plate_number || 'N/A'})</strong></td>
                        <td><span class="status-badge ${tripStatus.toLowerCase()}">${tripStatus}</span></td>
                        <td>${trip.departure_time ? formatDateTime(trip.departure_time) : 'Not set'}</td>
                        <td>${trip.arrival_time ? formatDateTime(trip.arrival_time) : 'Not set'}</td>
                    </tr>
                `;
            });
            
            content += '</tbody></table>';
        }
        
        showDashboardModal(title, content);
    }).catch(error => {
        console.error('Error fetching trip details:', error);
        showMessage('Failed to load trip details');
    });
}

function showCompletedTrips() {
    
    fetchTripDetails('completed').then(trips => {
        const title = `Completed Trips Today (${trips.length})`;
        
        let content = '<div class="modal-summary">Trips that were completed today.</div>';
        
        if (trips.length === 0) {
            content += '<div class="no-data">No trips completed today.</div>';
        } else {
            content += `
                <table class="dashboard-table">
                    <thead>
                        <tr>
                            <th>Vehicle</th>
                            <th>Status</th>
                            <th>Departure Time</th>
                            <th>Arrival Time</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            trips.forEach(trip => {
                const vehicle = trip.vehicle || {};
                const tripStatus = trip.trip_status || 'Unknown';
                
                content += `
                    <tr>
                        <td><strong>${vehicle.name || 'Unknown'} (${vehicle.plate_number || 'N/A'})</strong></td>
                        <td><span class="status-badge ${tripStatus.toLowerCase()}">${tripStatus}</span></td>
                        <td>${trip.departure_time ? formatDateTime(trip.departure_time) : 'Not set'}</td>
                        <td>${trip.arrival_time ? formatDateTime(trip.arrival_time) : 'Not set'}</td>
                    </tr>
                `;
            });
            
            content += '</tbody></table>';
        }
        
        showDashboardModal(title, content);
    }).catch(error => {
        console.error('Error fetching trip details:', error);
        showMessage('Failed to load trip details');
    });
}

function showVehicleAvailability() {
    
    fetchVehicleDetails('available').then(vehicles => {
        const title = `Available Vehicles (${vehicles.length})`;
        
        let content = '<div class="modal-summary">Vehicles currently available for assignments.</div>';
        
        if (vehicles.length === 0) {
            content += '<div class="no-data">No vehicles currently available.</div>';
        } else {
            content += `
                <table class="dashboard-table">
                    <thead>
                        <tr>
                            <th>License Plate</th>
                            <th>Vehicle Type</th>
                            <th>Capacity</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            vehicles.forEach(vehicle => {
                content += `
                    <tr>
                        <td><strong>${vehicle.plate_number || 'Unknown'}</strong></td>
                        <td>${vehicle.name || 'N/A'}</td>
                        <td>${vehicle.capacity || 'N/A'}</td>
                        <td><span class="status-badge ${vehicle.status || 'unknown'}">${vehicle.status || 'Unknown'}</span></td>
                    </tr>
                `;
            });
            
            content += '</tbody></table>';
        }
        
        showDashboardModal(title, content);
    }).catch(error => {
        console.error('Error fetching vehicle details:', error);
        showMessage('Failed to load vehicle details');
    });
}

function showVehicleUnavailability() {
    
    fetchVehicleDetails('unavailable').then(vehicles => {
        const title = `Unavailable Vehicles (${vehicles.length})`;
        
        let content = '<div class="modal-summary">Vehicles currently unavailable for assignments.</div>';
        
        if (vehicles.length === 0) {
            content += '<div class="no-data">All vehicles are available.</div>';
        } else {
            content += `
                <table class="dashboard-table">
                    <thead>
                        <tr>
                            <th>License Plate</th>
                            <th>Vehicle Type</th>
                            <th>Capacity</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            vehicles.forEach(vehicle => {
                content += `
                    <tr>
                        <td><strong>${vehicle.plate_number || 'Unknown'}</strong></td>
                        <td>${vehicle.name || 'N/A'}</td>
                        <td>${vehicle.capacity || 'N/A'}</td>
                        <td><span class="status-badge ${vehicle.status || 'unknown'}">${vehicle.status || 'Unknown'}</span></td>
                    </tr>
                `;
            });
            
            content += '</tbody></table>';
        }
        
        showDashboardModal(title, content);
    }).catch(error => {
        console.error('Error fetching vehicle details:', error);
        showMessage('Failed to load vehicle details');
    });
}

function showAssignedTrips() {
    fetchVehicleDetails('assigned').then(vehicles => {
        const title = `Vehicles Assigned to Trips (${vehicles.length})`;
        
        let content = '<div class="modal-summary">Vehicles currently assigned to trips.</div>';
        
        if (vehicles.length === 0) {
            content += '<div class="no-data">No vehicles currently assigned to trips.</div>';
        } else {
            content += `
                <table class="dashboard-table">
                    <thead>
                        <tr>
                            <th>Vehicle</th>
                            <th>Trip Code</th>
                            <th>Route</th>
                            <th>Status</th>
                            <th>Departure</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            vehicles.forEach(vehicle => {
                const parcelInfo = vehicle.parcel_count ? ` (${vehicle.parcel_count} parcel${vehicle.parcel_count > 1 ? 's' : ''})` : '';
                content += `
                    <tr>
                        <td><strong>${vehicle.plate_number || 'Unknown'}</strong><br><small>${vehicle.name || 'N/A'}${parcelInfo}</small></td>
                        <td><strong>${vehicle.current_trip_code || 'N/A'}</strong></td>
                        <td>${vehicle.origin_outlet_name || 'N/A'} → ${vehicle.destination_outlet_name || 'N/A'}</td>
                        <td><span class="status-badge ${(vehicle.current_trip_status || 'unknown').toLowerCase().replace(/\s+/g, '-')}">${vehicle.current_trip_status || 'Unknown'}</span></td>
                        <td>${formatDateTime(vehicle.departure_time)}</td>
                    </tr>
                `;
            });
            
            content += '</tbody></table>';
        }
        
        showDashboardModal(title, content);
    }).catch(error => {
        console.error('Error fetching assigned vehicles:', error);
        showMessage('Failed to load assigned vehicles');
    });
}

function showRevenueToday() {
    if (!dashboardData || !dashboardData.revenue_snapshot.details.todays_payments) {
        showMessage('No data available');
        return;
    }
    
    const payments = dashboardData.revenue_snapshot.details.todays_payments;
    const total = dashboardData.revenue_snapshot.total_today || 0;
    const title = `Today's Revenue: ZMW ${parseFloat(total).toFixed(2)}`;
    
    let content = '<div class="modal-summary">Revenue collected today from parcel payments.</div>';
    
    if (payments.length === 0) {
        content += '<div class="no-data">No payments received today.</div>';
    } else {
        
        const methodBreakdown = dashboardData.revenue_snapshot.payment_method_breakdown || {};
        if (Object.keys(methodBreakdown).length > 0) {
            content += '<div class="payment-methods"><h4>Payment Methods:</h4><ul>';
            Object.entries(methodBreakdown).forEach(([method, amount]) => {
                content += `<li><strong>${method.toUpperCase()}</strong>: ZMW ${parseFloat(amount).toFixed(2)}</li>`;
            });
            content += '</ul></div>';
        }
        
        content += `
            <table class="dashboard-table">
                <thead>
                    <tr>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Parcel ID</th>
                    </tr>
                </thead>
                <tbody>
        `;
        
        payments.forEach(payment => {
            content += `
                <tr>
                    <td><strong>ZMW ${parseFloat(payment.amount || 0).toFixed(2)}</strong></td>
                    <td><span class="status-badge ${payment.method || 'unknown'}">${payment.method || 'Unknown'}</span></td>
                    <td>${payment.parcel_id || 'N/A'}</td>
                </tr>
            `;
        });
        
        content += '</tbody></table>';
    }
    
    showDashboardModal(title, content);
}

function showRevenueWeek() {
    const total = dashboardData?.revenue_snapshot?.total_week || 0;
    const title = `This Week's Revenue: ZMW ${parseFloat(total).toFixed(2)}`;
    
    let content = '<div class="modal-summary">Revenue collected over the past 7 days.</div>';
    content += `<div class="revenue-summary"><h4>Total: ZMW ${parseFloat(total).toFixed(2)}</h4></div>`;
    
    showDashboardModal(title, content);
}

function showCODCollections() {
    if (!dashboardData || !dashboardData.revenue_snapshot.details.cod_collections) {
        showMessage('No data available');
        return;
    }
    
    const collections = dashboardData.revenue_snapshot.details.cod_collections;
    const total = dashboardData.revenue_snapshot.cod_collected_today || 0;
    const title = `COD Collections Today: ZMW ${parseFloat(total).toFixed(2)}`;
    
    let content = '<div class="modal-summary">Cash on Delivery collections for today.</div>';
    
    if (collections.length === 0) {
        content += '<div class="no-data">No COD collections today.</div>';
    } else {
        content += `
            <table class="dashboard-table">
                <thead>
                    <tr>
                        <th>Tracking Number</th>
                        <th>COD Amount</th>
                    </tr>
                </thead>
                <tbody>
        `;
        
        collections.forEach(collection => {
            content += `
                <tr>
                    <td><strong>${collection.track_number || 'N/A'}</strong></td>
                    <td><strong>ZMW ${parseFloat(collection.cod_amount || 0).toFixed(2)}</strong></td>
                </tr>
            `;
        });
        
        content += '</tbody></table>';
    }
    
    showDashboardModal(title, content);
}

function showTransactionCount() {
    const count = dashboardData?.revenue_snapshot?.transaction_count_today || 0;
    const codCount = dashboardData?.revenue_snapshot?.cod_transaction_count || 0;
    const title = `Transactions Today: ${count}`;
    
    let content = '<div class="modal-summary">Transaction summary for today.</div>';
    content += `
        <div class="transaction-summary">
            <div class="summary-item">
                <h4>Total Transactions: ${count}</h4>
            </div>
            <div class="summary-item">
                <h4>COD Transactions: ${codCount}</h4>
            </div>
            <div class="summary-item">
                <h4>Regular Payments: ${count - codCount}</h4>
            </div>
        </div>
    `;
    
    showDashboardModal(title, content);
}

function showDashboardModal(title, content) {
    const modal = document.getElementById('dashboardModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalBody = document.getElementById('modalBody');
    
    if (modal && modalTitle && modalBody) {
        modalTitle.textContent = title;
        modalBody.innerHTML = content;
        modal.style.display = 'block';
        isModalOpen = true;
    }
}

function closeDashboardModal() {
    const modal = document.getElementById('dashboardModal');
    if (modal) {
        modal.style.display = 'none';
        isModalOpen = false;
    }
}

function formatDateTime(dateString) {
    if (!dateString) return 'N/A';
    try {
        const date = new Date(dateString);
        return date.toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    } catch (error) {
        return 'Invalid Date';
    }
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    try {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    } catch (error) {
        return 'Invalid Date';
    }
}

function showMessage(message) {
    const overlay = document.createElement('div');
    overlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 20000;
    `;

    const messageBox = document.createElement('div');
    messageBox.style.cssText = `
        background: white;
        padding: 2rem;
        border-radius: 0.5rem;
        text-align: center;
        max-width: 90%;
        width: 400px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    `;

    const messageParagraph = document.createElement('p');
    messageParagraph.textContent = message;
    messageParagraph.style.cssText = `
        font-size: 1.25rem;
        margin-bottom: 1.5rem;
        color: #333;
    `;

    const closeButton = document.createElement('button');
    closeButton.textContent = 'OK';
    closeButton.style.cssText = `
        background-color: #2E0D2A;
        color: white;
        padding: 0.75rem 1.5rem;
        border-radius: 0.375rem;
        border: none;
        font-weight: bold;
        cursor: pointer;
        transition: background-color 0.2s;
    `;
    closeButton.onmouseover = () => closeButton.style.backgroundColor = '#4A1C40';
    closeButton.onmouseout = () => closeButton.style.backgroundColor = '#2E0D2A';
    closeButton.addEventListener('click', () => {
        document.body.removeChild(overlay);
    });

    messageBox.appendChild(messageParagraph);
    messageBox.appendChild(closeButton);
    overlay.appendChild(messageBox);
    document.body.appendChild(overlay);
}

function filterActivities() {
    
    console.log('Filter activities called');
}

async function fetchDetailedData(type, status = null) {
    try {
        const requestData = {
            type: type,
            limit: 50,
            offset: 0
        };
        
        if (status) {
            requestData.status = status;
        }
        
        const response = await fetch('../api/dashboard/dashboard_details.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(requestData)
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
            return data.data;
        } else {
            throw new Error(data.error || 'Failed to fetch detailed data');
        }
    } catch (error) {
        console.error('Error fetching detailed data:', error);
        throw error;
    }
}

async function fetchVehicleDetails(status) {
    try {
        let type;
        switch (status) {
            case 'available':
                type = 'available_vehicles';
                break;
            case 'unavailable':
                type = 'unavailable_vehicles';
                break;
            case 'assigned':
                type = 'assigned_vehicles';
                break;
            default:
                throw new Error('Invalid vehicle status');
        }
        
        return await fetchDetailedData(type);
    } catch (error) {
        console.error('Error fetching vehicle details:', error);
        throw error;
    }
}

async function fetchParcelDetails(status) {
    try {
        let type;
        switch (status) {
            case 'pending':
                type = 'pending_parcels';
                break;
            case 'in_transit':
                type = 'in_transit_parcels';
                break;
            case 'completed':
                type = 'completed_parcels';
                break;
            case 'urgent':
                type = 'urgent_parcels';
                break;
            default:
                throw new Error('Invalid parcel status');
        }
        
        return await fetchDetailedData(type);
    } catch (error) {
        console.error('Error fetching parcel details:', error);
        throw error;
    }
}

async function fetchTripDetails(status) {
    try {
        let type;
        switch (status) {
            case 'scheduled':
                type = 'trips_scheduled';
                break;
            case 'in_transit':
                type = 'trips_in_transit';
                break;
            case 'completed':
                type = 'trips_completed';
                break;
            default:
                throw new Error('Invalid trip status');
        }
        
        return await fetchDetailedData(type);
    } catch (error) {
        console.error('Error fetching trip details:', error);
        throw error;
    }
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

window.addEventListener('beforeunload', function() {
    if (updateInterval) {
        clearInterval(updateInterval);
    }
});
