<?php

// Use shared session-check and header for company-app pages
require_once __DIR__ . '/../../auth/session-check.php';
// Ensure session started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// If the session-check did not find a user, redirect to login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}
$page_title = 'Trips';
include __DIR__ . '/../includes/header.php';

// Build current_user from session
$current_user = [
    'user_id' => $_SESSION['user_id'] ?? null,
    'outlet_id' => $_SESSION['outlet_id'] ?? null,
    'company_id' => $_SESSION['company_id'] ?? null,
];
error_log("Outlet ID in trips.php: " . ($current_user['outlet_id'] ?? 'not set'));
?>
    
    <style>
        /* Filter Panel Styles */
        .filters-container {
            background: white;
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .filters-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .filters-header h3 {
            margin: 0;
            color: #374151;
            font-size: 1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filter-toggle-btn {
            background: none;
            border: none;
            color: #3b82f6;
            cursor: pointer;
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-group label {
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filter-group select,
        .filter-group input {
            padding: 0.5rem 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.875rem;
            font-family: 'Poppins', sans-serif;
            transition: border-color 0.2s;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filter-btn-apply {
            background: #3b82f6;
            color: white;
        }

        .filter-btn-apply:hover {
            background: #2563eb;
        }

        .filter-btn-reset {
            background: #f3f4f6;
            color: #374151;
        }

        .filter-btn-reset:hover {
            background: #e5e7eb;
        }

        .active-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e5e7eb;
        }

        .filter-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: #dbeafe;
            color: #1e40af;
            padding: 0.375rem 0.75rem;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .filter-tag button {
            background: none;
            border: none;
            color: #1e40af;
            cursor: pointer;
            padding: 0;
            display: flex;
            align-items: center;
            font-size: 1rem;
        }

        .trips-summary {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .summary-card {
            flex: 1;
            min-width: 150px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem;
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .summary-card.status-scheduled {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }

        .summary-card.status-in-transit {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        }

        .summary-card.status-completed {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .summary-card-label {
            font-size: 0.75rem;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .summary-card-value {
            font-size: 1.75rem;
            font-weight: 700;
        }

        @media (max-width: 768px) {
            .filters-grid {
                grid-template-columns: 1fr;
            }

            .filter-actions {
                width: 100%;
            }

            .filter-btn {
                flex: 1;
                justify-content: center;
            }
        }
    </style>
<body class="bg-gray-100 min-h-screen">

    <div class="mobile-dashboard">
        <!-- Trip preview modal (hidden by default) -->
        <div id="tripPreviewModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
            <div id="tripPreviewContent" style="max-width:900px; width:95%; margin:auto;"> 
                <!-- Filled dynamically -->
            </div>
        </div>
    
        <main class="main-content">
            <div class="content-container">
                <h1><i class="fas fa-route"></i> Trip Management</h1>
                <p class="subtitle">View and manage all trips that originate from your outlet and their parcel contents.</p>

                <!-- Summary Cards -->
                <div class="trips-summary" id="tripsSummary" style="display: none;">
                    <div class="summary-card">
                        <span class="summary-card-label">Total Trips</span>
                        <span class="summary-card-value" id="totalTrips">0</span>
                    </div>
                    <div class="summary-card status-scheduled">
                        <span class="summary-card-label">Scheduled</span>
                        <span class="summary-card-value" id="scheduledTrips">0</span>
                    </div>
                    <div class="summary-card status-in-transit">
                        <span class="summary-card-label">In Transit</span>
                        <span class="summary-card-value" id="inTransitTrips">0</span>
                    </div>
                    <div class="summary-card status-completed">
                        <span class="summary-card-label">Completed</span>
                        <span class="summary-card-value" id="completedTrips">0</span>
                    </div>
                </div>

                <!-- Filters Section -->
                <div class="filters-container">
                    <div class="filters-header">
                        <h3>
                            <i class="fas fa-filter"></i> Filter Trips
                        </h3>
                        <button class="filter-toggle-btn" id="filterToggleBtn">
                            <i class="fas fa-chevron-up"></i>
                            <span>Hide Filters</span>
                        </button>
                    </div>

                    <div id="filtersContent">
                        <div class="filters-grid">
                            <div class="filter-group">
                                <label><i class="fas fa-info-circle"></i> Status</label>
                                <select id="filterStatus">
                                    <option value="">All Statuses</option>
                                    <option value="scheduled">Scheduled</option>
                                    <option value="in_transit">In Transit</option>
                                    <option value="completed">Completed</option>
                                    <option value="at_outlet">At Outlet</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label><i class="fas fa-user"></i> Driver</label>
                                <select id="filterDriver">
                                    <option value="">All Drivers</option>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label><i class="fas fa-calendar"></i> From Date</label>
                                <input type="date" id="filterDateFrom" />
                            </div>

                            <div class="filter-group">
                                <label><i class="fas fa-calendar"></i> To Date</label>
                                <input type="date" id="filterDateTo" />
                            </div>

                            <div class="filter-group">
                                <label><i class="fas fa-search"></i> Search</label>
                                <input type="text" id="filterSearch" placeholder="Trip ID, tracking number..." />
                            </div>
                        </div>

                        <div class="filter-actions">
                            <button class="filter-btn filter-btn-apply" onclick="applyFilters()">
                                <i class="fas fa-check"></i> Apply Filters
                            </button>
                            <button class="filter-btn filter-btn-reset" onclick="resetFilters()">
                                <i class="fas fa-times"></i> Reset
                            </button>
                            <button class="filter-btn filter-btn-reset" onclick="fetchOutletTrips()" style="margin-left: auto;">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                        </div>

                        <div class="active-filters" id="activeFilters" style="display: none;"></div>
                    </div>
                </div>

                <!-- Available Trips Section -->
                <div id="tripsSection" style="margin-bottom: 2rem;">
                    <div id="tripsList" style="display: grid; gap: 1rem;">
                        <p style="text-align: center; color: #6b7280;
                            "><i class="fas fa-spinner fa-spin"></i> Loading trips...</p>
                    </div>
                        <!-- Pagination Controls -->
                        <div class="pagination-container" id="tripsPagination" style="display:flex;align-items:center;justify-content:center;gap:8px;margin-top:20px;">
                        </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        const menuBtn = document.getElementById('menuBtn');
        const closeMenu = document.getElementById('closeMenu');
        const sidebar = document.getElementById('sidebar');
        const menuOverlay = document.getElementById('menuOverlay');

        function toggleMenu() {
            sidebar.classList.toggle('show');
            menuOverlay.classList.toggle('show');
            document.body.style.overflow = sidebar.classList.contains('show') ? 'hidden' : '';
        }

        if (menuBtn) {
            menuBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                toggleMenu();
            });
        }

        if (closeMenu) closeMenu.addEventListener('click', toggleMenu);
        if (menuOverlay) menuOverlay.addEventListener('click', toggleMenu);

        document.querySelectorAll('.menu-items a').forEach(item => {
            item.addEventListener('click', toggleMenu);
        });

        const outlet_id = <?php echo json_encode($current_user['outlet_id'] ?? ''); ?>;

        // Global trips data
        let allTrips = [];
        let filteredTrips = [];
        // Pagination state for trips
        let tripsCurrentPage = 1;
        const tripsItemsPerPage = 25;

        // Filter toggle functionality
        document.getElementById('filterToggleBtn').addEventListener('click', function() {
            const content = document.getElementById('filtersContent');
            const icon = this.querySelector('i');
            const text = this.querySelector('span');
            
            if (content.style.display === 'none') {
                content.style.display = 'block';
                icon.className = 'fas fa-chevron-up';
                text.textContent = 'Hide Filters';
            } else {
                content.style.display = 'none';
                icon.className = 'fas fa-chevron-down';
                text.textContent = 'Show Filters';
            }
        });

        // Setup filter event listeners for auto-filtering
        function setupFilterListeners() {
            console.log('🎯 Setting up filter event listeners...');
            
            // Status filter
            document.getElementById('filterStatus').addEventListener('change', function() {
                console.log('🔄 Status filter changed to:', this.value);
                applyFilters();
            });

            // Driver filter
            document.getElementById('filterDriver').addEventListener('change', function() {
                console.log('🔄 Driver filter changed to:', this.value);
                applyFilters();
            });

            // Date filters
            document.getElementById('filterDateFrom').addEventListener('change', function() {
                console.log('🔄 From date changed to:', this.value);
                applyFilters();
            });

            document.getElementById('filterDateTo').addEventListener('change', function() {
                console.log('🔄 To date changed to:', this.value);
                applyFilters();
            });

            // Search filter with debounce
            let searchTimeout;
            document.getElementById('filterSearch').addEventListener('input', function() {
                clearTimeout(searchTimeout);
                const searchValue = this.value;
                console.log('🔍 Search input:', searchValue);
                searchTimeout = setTimeout(() => {
                    console.log('🔄 Applying search filter:', searchValue);
                    applyFilters();
                }, 300); // 300ms debounce
            });

            console.log('✅ Filter listeners setup complete');
        }

        // Fetch outlet trips with parcels
        async function fetchOutletTrips() {
            // FORCE CACHE CLEAR - Remove this after confirming it works
            if ('caches' in window) {
                caches.keys().then(names => {
                    names.forEach(name => {
                        caches.delete(name);
                        console.log('🗑️ Deleted cache:', name);
                    });
                });
            }
            
            try {
                const container = document.getElementById('tripsList');
                container.innerHTML = '<p style="text-align: center; color: #6b7280;">Loading trips...</p>';

                // Use company-level fetch_trips API
                const apiUrl = `../api/fetch_trips.php?v=${Date.now()}`;
                console.log('🔍 Attempting to fetch from:', apiUrl);
                console.log('🔍 Current page URL:', window.location.href);
                console.log('🔍 Full API URL will be:', new URL(apiUrl, window.location.href).href);

                const response = await fetch(apiUrl, {
                    credentials: 'same-origin',
                    cache: 'no-store'  // Force no caching
                });
                const data = await response.json();

                if (data.success && Array.isArray(data.trips) && data.trips.length > 0) {
                    allTrips = data.trips;
                            filteredTrips = [...allTrips];

                            // Batch-fetch outlet names for origin/destination ids present in the trips
                            (async function loadOutletNames() {
                                try {
                                    const ids = new Set();
                                    allTrips.forEach(t => {
                                        if (t.origin_outlet_id) ids.add(t.origin_outlet_id);
                                        if (t.destination_outlet_id) ids.add(t.destination_outlet_id);
                                    });
                                    const idList = Array.from(ids).filter(Boolean);
                                    window.__outletNameMap = window.__outletNameMap || {};
                                    if (idList.length) {
                                        const resp = await fetch(`../api/fetch_outlets_by_ids.php?ids=${encodeURIComponent(idList.join(','))}`, { credentials: 'same-origin' });
                                        const jsonOut = await resp.json();
                                        if (jsonOut.success && Array.isArray(jsonOut.outlets)) {
                                            jsonOut.outlets.forEach(o => {
                                                const name = o.outlet_name || o.name || o.display_name || o.outlet || o.id;
                                                window.__outletNameMap[o.id] = name;
                                            });
                                        }
                                    }
                                } catch (err) {
                                    console.debug('Failed to load outlet names', err);
                                } finally {
                                    // Populate driver filter
                                    populateDriverFilter();
                                    // Update summary
                                    updateSummary();
                                    // Display trips
                                    displayTrips(filteredTrips);
                                }
                            })();
                } else {
                    allTrips = [];
                    filteredTrips = [];
                    container.innerHTML = `
                        <div style="text-align: center; padding: 2rem; background: #f9fafb; border-radius: 8px; border: 1px solid #e5e7eb;">
                            <i class="fas fa-paper-plane" style="font-size: 3rem; color: #d1d5db; margin-bottom: 1rem;"></i>
                            <p style="color: #6b7280; margin: 0; font-weight: 600; margin-bottom: 0.5rem;">No Outgoing Trips</p>
                            <p style="color: #9ca3af; margin: 0; font-size: 0.875rem;">${data.message || 'No trips originating from this outlet.'}</p>
                            <p style="color: #9ca3af; margin: 0.5rem 0 0 0; font-size: 0.875rem;">Create a new trip from the <a href="create-trip.php" style="color: #3b82f6; text-decoration: underline;">Trip Wizard</a></p>
                        </div>
                    `;
                    document.getElementById('tripsSummary').style.display = 'none';
                }
            } catch (error) {
                console.error('Error fetching outlet trips:', error);
                const container = document.getElementById('tripsList');
                container.innerHTML = `
                    <div style="text-align: center; padding: 2rem; background: #fef2f2; border-radius: 8px; border: 1px solid #fecaca;">
                        <i class="fas fa-exclamation-triangle" style="color: #dc2626; font-size: 2rem; margin-bottom: 0.5rem;"></i>
                        <p style="color: #dc2626; margin: 0;">Error loading trips: ${error.message}</p>
                    </div>
                `;
            }
        }

        // Populate driver filter dropdown
        function populateDriverFilter() {
            const driverSelect = document.getElementById('filterDriver');
            const drivers = new Set();
            
            allTrips.forEach(trip => {
                if (trip.driver && trip.driver.driver_name) {
                    drivers.add(JSON.stringify({
                        id: trip.driver.id,
                        name: trip.driver.driver_name
                    }));
                }
            });

            driverSelect.innerHTML = '<option value="">All Drivers</option>';
            Array.from(drivers).map(d => JSON.parse(d)).sort((a, b) => a.name.localeCompare(b.name)).forEach(driver => {
                const option = document.createElement('option');
                option.value = driver.id;
                option.textContent = driver.name;
                driverSelect.appendChild(option);
            });
        }

        // Update summary cards
        function updateSummary() {
            const total = filteredTrips.length;
            const scheduled = filteredTrips.filter(t => t.trip_status === 'scheduled').length;
            const inTransit = filteredTrips.filter(t => t.trip_status === 'in_transit').length;
            const completed = filteredTrips.filter(t => t.trip_status === 'completed').length;

            document.getElementById('totalTrips').textContent = total;
            document.getElementById('scheduledTrips').textContent = scheduled;
            document.getElementById('inTransitTrips').textContent = inTransit;
            document.getElementById('completedTrips').textContent = completed;
            document.getElementById('tripsSummary').style.display = total > 0 ? 'flex' : 'none';
        }

        // Apply filters
        function applyFilters() {
            console.log('🔧 Applying filters...');
            const status = document.getElementById('filterStatus').value;
            const driver = document.getElementById('filterDriver').value;
            const dateFrom = document.getElementById('filterDateFrom').value;
            const dateTo = document.getElementById('filterDateTo').value;
            const search = document.getElementById('filterSearch').value.toLowerCase();

            console.log('📊 Filter values:', { status, driver, dateFrom, dateTo, search });
            console.log('📦 Total trips before filter:', allTrips.length);

            filteredTrips = allTrips.filter(trip => {
                // Status filter
                if (status && trip.trip_status !== status) {
                    console.log(`  ❌ Trip ${trip.id?.substring(0, 8)} filtered out by status`);
                    return false;
                }

                // Driver filter
                if (driver && trip.driver?.id !== driver) {
                    console.log(`  ❌ Trip ${trip.id?.substring(0, 8)} filtered out by driver`);
                    return false;
                }

                // Date range filter
                if (dateFrom && trip.departure_time) {
                    const tripDate = new Date(trip.departure_time).toISOString().split('T')[0];
                    if (tripDate < dateFrom) {
                        console.log(`  ❌ Trip ${trip.id?.substring(0, 8)} filtered out by from date`);
                        return false;
                    }
                }
                if (dateTo && trip.departure_time) {
                    const tripDate = new Date(trip.departure_time).toISOString().split('T')[0];
                    if (tripDate > dateTo) {
                        console.log(`  ❌ Trip ${trip.id?.substring(0, 8)} filtered out by to date`);
                        return false;
                    }
                }

                // Search filter
                if (search) {
                    const tripId = (trip.id || '').toLowerCase();
                    const driverName = trip.driver?.driver_name?.toLowerCase() || '';
                    const vehicleName = trip.vehicle ? (trip.vehicle.name || '').toLowerCase() : '';
                    const trackNumbers = (trip.parcels || []).map(p => (p.track_number || '').toLowerCase());
                    
                    const matches = tripId.includes(search) || 
                                  driverName.includes(search) || 
                                  vehicleName.includes(search) ||
                                  trackNumbers.some(tn => tn.includes(search));
                    
                    if (!matches) {
                        console.log(`  ❌ Trip ${trip.id?.substring(0, 8)} filtered out by search`);
                        return false;
                    }
                }

                console.log(`  ✅ Trip ${trip.id?.substring(0, 8)} passed filters`);
                return true;
            });

            console.log('✅ Filtered trips:', filteredTrips.length);
            displayTrips(filteredTrips);
            updateSummary();
            updateActiveFilters();
        }

        // Reset filters
        function resetFilters() {
            document.getElementById('filterStatus').value = '';
            document.getElementById('filterDriver').value = '';
            document.getElementById('filterDateFrom').value = '';
            document.getElementById('filterDateTo').value = '';
            document.getElementById('filterSearch').value = '';
            
            filteredTrips = [...allTrips];
            displayTrips(filteredTrips);
            updateSummary();
            updateActiveFilters();
        }

        // Update active filters display
        function updateActiveFilters() {
            const activeFiltersDiv = document.getElementById('activeFilters');
            const filters = [];

            const status = document.getElementById('filterStatus').value;
            const driver = document.getElementById('filterDriver').value;
            const dateFrom = document.getElementById('filterDateFrom').value;
            const dateTo = document.getElementById('filterDateTo').value;
            const search = document.getElementById('filterSearch').value;

            if (status) {
                const statusText = document.getElementById('filterStatus').selectedOptions[0].text;
                filters.push({ label: 'Status', value: statusText, clear: () => document.getElementById('filterStatus').value = '' });
            }
            if (driver) {
                const driverText = document.getElementById('filterDriver').selectedOptions[0].text;
                filters.push({ label: 'Driver', value: driverText, clear: () => document.getElementById('filterDriver').value = '' });
            }
            if (dateFrom) {
                filters.push({ label: 'From', value: dateFrom, clear: () => document.getElementById('filterDateFrom').value = '' });
            }
            if (dateTo) {
                filters.push({ label: 'To', value: dateTo, clear: () => document.getElementById('filterDateTo').value = '' });
            }
            if (search) {
                filters.push({ label: 'Search', value: search, clear: () => document.getElementById('filterSearch').value = '' });
            }

            if (filters.length > 0) {
                activeFiltersDiv.innerHTML = filters.map(f => `
                    <div class="filter-tag">
                        <span><strong>${f.label}:</strong> ${f.value}</span>
                        <button onclick="removeFilter('${f.label}')" title="Remove filter">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `).join('');
                activeFiltersDiv.style.display = 'flex';
            } else {
                activeFiltersDiv.style.display = 'none';
            }
        }

        // Remove individual filter
        function removeFilter(label) {
            switch(label) {
                case 'Status':
                    document.getElementById('filterStatus').value = '';
                    break;
                case 'Driver':
                    document.getElementById('filterDriver').value = '';
                    break;
                case 'From':
                    document.getElementById('filterDateFrom').value = '';
                    break;
                case 'To':
                    document.getElementById('filterDateTo').value = '';
                    break;
                case 'Search':
                    document.getElementById('filterSearch').value = '';
                    break;
            }
            applyFilters();
        }

        // Display trips
        function renderTripsPagination(totalItems) {
            const container = document.getElementById('tripsPagination');
            if (!container) return;
            const totalPages = Math.max(1, Math.ceil(totalItems / tripsItemsPerPage));
            let html = '';
            html += `<a href="?page=1" class="pagination-btn" style="${tripsCurrentPage === 1 ? 'opacity: 0.5; cursor: not-allowed;' : ''}" ${tripsCurrentPage === 1 ? 'onclick="return false;"' : 'onclick="changeTripsPage(1);return false;"'}><i class="fas fa-chevron-left"></i> First</a>`;
            html += `<a href="?page=${Math.max(1, tripsCurrentPage-1)}" class="pagination-btn" style="${tripsCurrentPage === 1 ? 'opacity: 0.5; cursor: not-allowed;' : ''}" ${tripsCurrentPage === 1 ? 'onclick="return false;"' : `onclick="changeTripsPage(${Math.max(1, tripsCurrentPage-1)});return false;"`}> <i class="fas fa-chevron-left"></i> Previous</a>`;
            let startPage = Math.max(1, tripsCurrentPage - 3);
            let endPage = Math.min(totalPages, startPage + 6);
            if (endPage - startPage < 6) {
                startPage = Math.max(1, endPage - 6);
            }
            for (let p = startPage; p <= endPage; p++) {
                if (p === tripsCurrentPage) html += `<a href="?page=${p}" class="page-number" style="background-color: #3b82f6; color: white; border-radius: 4px; padding: 5px 10px; cursor: default;" onclick="return false;">${p}</a>`;
                else html += `<a href="?page=${p}" class="page-number" style="padding: 5px 10px; border-radius: 4px; border: 1px solid #d1d5db;" onclick="changeTripsPage(${p});return false;">${p}</a>`;
            }
            html += `<a href="?page=${Math.min(totalPages, tripsCurrentPage+1)}" class="pagination-btn" style="${tripsCurrentPage >= totalPages ? 'opacity: 0.5; cursor: not-allowed;' : ''}" ${tripsCurrentPage >= totalPages ? 'onclick="return false;"' : `onclick="changeTripsPage(${Math.min(totalPages, tripsCurrentPage+1)});return false;"`}>Next <i class="fas fa-chevron-right"></i></a>`;
            html += `<a href="?page=${totalPages}" class="pagination-btn" style="${tripsCurrentPage >= totalPages ? 'opacity: 0.5; cursor: not-allowed;' : ''}" ${tripsCurrentPage >= totalPages ? 'onclick="return false;"' : `onclick="changeTripsPage(${totalPages});return false;"`}>Last <i class="fas fa-chevron-right"></i></a>`;
            container.innerHTML = html;
        }

        function changeTripsPage(page) {
            tripsCurrentPage = page;
            displayTrips(filteredTrips);
        }

        // Display trips (paginated)
        function displayTrips(trips) {
            const container = document.getElementById('tripsList');
            container.innerHTML = '';

            if (!trips || trips.length === 0) {
                container.innerHTML = `
                    <div style="text-align: center; padding: 2rem; background: #f9fafb; border-radius: 8px; border: 1px solid #e5e7eb;">
                        <i class="fas fa-filter" style="font-size: 3rem; color: #d1d5db; margin-bottom: 1rem;"></i>
                        <p style="color: #6b7280; margin: 0;">No trips match your filters. Try adjusting your search criteria.</p>
                    </div>
                `;
                renderTripsPagination(0);
                return;
            }

            const total = trips.length;
            const totalPages = Math.max(1, Math.ceil(total / tripsItemsPerPage));
            if (tripsCurrentPage > totalPages) tripsCurrentPage = totalPages;
            const start = (tripsCurrentPage - 1) * tripsItemsPerPage;
            const slice = trips.slice(start, start + tripsItemsPerPage);

            slice.forEach(trip => {
                const tripCard = createTripCard(trip);
                container.appendChild(tripCard);
            });

            renderTripsPagination(total);
        }

        // Create trip card element
        function createTripCard(trip) {
            const card = document.createElement('div');
            card.className = 'trip-card';
            card.style.cssText = `
                background: white;
                border: 2px solid ${trip.is_origin_outlet ? '#3b82f6' : '#e5e7eb'};
                border-radius: 12px;
                padding: 1.5rem;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                transition: all 0.3s ease;
            `;

            // Normalize common derived fields so rendering doesn't rely on DB naming
            const stopsLen = Array.isArray(trip.stops) ? trip.stops.length : 0;
            // Normalize parcels: may be returned as parcel_list rows with nested parcel objects
            let parcelItems = [];
            if (Array.isArray(trip.parcels) && trip.parcels.length) {
                parcelItems = trip.parcels.map(pl => (pl.parcel ? pl.parcel : pl));
            }
            const totalParcels = (typeof trip.total_parcels === 'number') ? trip.total_parcels : parcelItems.length;
            const assignedCount = (typeof trip.assigned_parcels_count === 'number') ? trip.assigned_parcels_count : parcelItems.length;

            // Status badge color
            const statusColors = {
                'scheduled': '#fbbf24',
                'in_transit': '#3b82f6',
                'completed': '#10b981',
                'at_outlet': '#8b5cf6',
                'cancelled': '#ef4444'
            };
            const statusColor = statusColors[trip.trip_status] || '#6b7280';

            // Header section
            const header = document.createElement('div');
            header.style.cssText = 'display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;';
            header.innerHTML = `
                <div>
                    <h4 style="margin: 0 0 0.5rem 0; color: #111827; font-size: 1.125rem;">
                        <i class="fas fa-route"></i> Trip #${trip.id.substring(0, 8)}...
                        ${trip.is_origin_outlet ? '<span style="background: #3b82f6; color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem; margin-left: 0.5rem;">ORIGIN</span>' : ''}
                    </h4>
                    <p style="margin: 0; color: #6b7280; font-size: 0.875rem;">
                        <i class="fas fa-box"></i> ${totalParcels} parcel${totalParcels !== 1 ? 's' : ''}
                        • Stop #${trip.outlet_stop_order || '?'} of ${stopsLen}
                    </p>
                </div>
                <span style="background: ${statusColor}; color: white; padding: 0.5rem 1rem; border-radius: 6px; font-weight: 600; font-size: 0.875rem; text-transform: uppercase;">
                    ${trip.trip_status.replace('_', ' ')}
                </span>
            `;
            card.appendChild(header);

            // Info grid
            const infoGrid = document.createElement('div');
            infoGrid.style.cssText = 'display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem;';
            
            // driver may come as an array ([{...}]) or object; normalize to object
            const driverObj = Array.isArray(trip.driver) ? (trip.driver[0] || null) : (trip.driver || null);
            const driverName = driverObj ? (driverObj.driver_name || driverObj.name || 'Unknown') : 'Not Assigned';
            // vehicle may come as an array or object; normalize to object
            const vehicleObj = Array.isArray(trip.vehicle) ? (trip.vehicle[0] || null) : (trip.vehicle || null);
            const vehicleName = vehicleObj ? ((vehicleObj.name || vehicleObj.vehicle_name || 'Vehicle') + ` (${vehicleObj.plate_number || vehicleObj.plate || 'N/A'})`) : 'Not Assigned';
            const departureTime = trip.departure_time ? new Date(trip.departure_time).toLocaleString() : 'Not Scheduled';
            const arrivalTime = trip.arrival_time ? new Date(trip.arrival_time).toLocaleString() : 'Not Scheduled';
            infoGrid.innerHTML = `
                <div>
                    <p style="margin: 0; color: #6b7280; font-size: 0.875rem;">Driver: <strong>${driverName}</strong></p>
                    <p style="margin: 0; color: #9ca3af; font-size: 0.8rem;">Departure: ${departureTime}</p>
                </div>
                <div>
                    <p style="margin: 0; color: #6b7280; font-size: 0.875rem;">Vehicle: <strong>${vehicleName}</strong></p>
                    <p style="margin: 0; color: #9ca3af; font-size: 0.8rem;">Expected Arrival: ${arrivalTime}</p>
                </div>
                <div>
                    <p style="margin: 0; color: #6b7280; font-size: 0.875rem;">Stops: <strong>${stopsLen}</strong></p>
                    <p style="margin: 0; color: #9ca3af; font-size: 0.8rem;">Current Stop: ${trip.outlet_stop_order || 'N/A'}</p>
                </div>
                <div>
                    <p style="margin: 0; color: #6b7280; font-size: 0.875rem;">Parcels: <strong>${totalParcels}</strong></p>
                    <p style="margin: 0; color: #9ca3af; font-size: 0.8rem;">Assigned: ${assignedCount || 0}</p>
                </div>
            `;
            card.appendChild(infoGrid);

            // Route summary: show origin and destination outlets (prefer outlet names fetched by id)
            const outletMap = window.__outletNameMap || {};
            const originOutletId = trip.origin_outlet_id || trip.origin_outlet || trip.from_outlet_id || null;
            const destinationOutletId = trip.destination_outlet_id || trip.destination_outlet || trip.to_outlet_id || null;
            const originOutlet = outletMap[originOutletId] || trip.origin_outlet_name || trip.origin_name || (Array.isArray(trip.stops) && trip.stops[0] ? (trip.stops[0].outlet_name || trip.stops[0].name) : 'Unknown');
            const destinationOutlet = outletMap[destinationOutletId] || trip.destination_outlet_name || trip.destination_name || (Array.isArray(trip.stops) && trip.stops.length ? (trip.stops[trip.stops.length - 1].outlet_name || trip.stops[trip.stops.length - 1].name) : 'Unknown');
            const routeSummary = document.createElement('div');
            routeSummary.style.cssText = 'display:flex; gap:1rem; align-items:center; margin-top:0.75rem; padding:0.75rem; background:#fbfbfb; border:1px solid #eef2f7; border-radius:8px;';
            routeSummary.innerHTML = `
                <div style="flex:1;">
                    <div style="font-size:0.75rem; color:#6b7280;">Origin</div>
                    <div style="font-weight:600; color:#111827;">${originOutlet}</div>
                </div>
                <div style="text-align:center; color:#9ca3af; font-size:1.25rem;">&rarr;</div>
                <div style="flex:1; text-align:right;">
                    <div style="font-size:0.75rem; color:#6b7280;">Destination</div>
                    <div style="font-weight:600; color:#111827;">${destinationOutlet}</div>
                </div>
            `;
            card.appendChild(routeSummary);

            // Parcel details removed from trip card to simplify the UI.

            // Parcels are shown by default; removed toggle control

            // Actions
            const actions = document.createElement('div');
            actions.style.cssText = 'display:flex; gap:0.5rem; margin-top:1rem; justify-content:flex-end;';

            const viewBtn = document.createElement('button');
            viewBtn.className = 'filter-btn';
            viewBtn.style.cssText = 'background:#3b82f6;color:white;padding:0.5rem 0.75rem;border-radius:6px;border:none;cursor:pointer;';
            viewBtn.textContent = 'View';
            viewBtn.addEventListener('click', () => showTripModal(trip));
            actions.appendChild(viewBtn);

            const editBtn = document.createElement('a');
            // Open the trip editor page we wired for create/update
            editBtn.href = `create-trip.php?edit=${trip.id}`;
            editBtn.className = 'filter-btn';
            editBtn.style.cssText = 'background:#10b981;color:white;padding:0.5rem 0.75rem;border-radius:6px;text-decoration:none;';
            editBtn.textContent = 'Edit';
            actions.appendChild(editBtn);

            card.appendChild(actions);

            return card;
        }

        // Show in-page modal with trip preview similar to create-trip success summary
        function showTripModal(trip) {
            const modal = document.getElementById('tripPreviewModal');
            const content = document.getElementById('tripPreviewContent');
            if (!modal || !content) return;

            // Normalize parcel items similarly to createTripCard
            let parcelItems = [];
            if (Array.isArray(trip.parcels) && trip.parcels.length) {
                parcelItems = trip.parcels.map(pl => (pl.parcel ? pl.parcel : pl));
            }
            const stopsLen = Array.isArray(trip.stops) ? trip.stops.length : 0;

            // Debug: print parcel objects so we can inspect available fields in the browser console
            try {
                // expose the parcel items on window for easy copying from DevTools
                window.__lastParcelItems = parcelItems;
                console.log('showTripModal - parcelItems sample (first 3):', window.__lastParcelItems.slice(0, 3));
            } catch (e) {
                // ignore logging errors in older browsers
            }

            // Build a resilient parcels list that prefers tracking number fields.
            // Intentionally do not fall back to parcel id here so the modal
            // doesn't display internal IDs when no tracking number exists.
            const parcelsListHtml = parcelItems.map(p => {
                const track = p.track_number || p.tracking_number || p.trackNumber || p.trackingNumber || p.track_no || p.tracking_no || p.tracking || p.trackingNo || p.track || p.parcel_number || p.parcel_no || p.parcelNo || p.parcel_number_alt || null;
                const recipient = p.recipient_name || p.name || p.receiver_name || p.to_name || 'Unknown';
                const trackDisplay = track ? track : '—';
                // Make track number a clickable link to parcel details page
                const trackLink = track ? `<a href="parcel_details.php?track=${encodeURIComponent(track)}" style="color:#3b82f6;text-decoration:none;font-weight:600;cursor:pointer;"><strong>${trackDisplay}</strong></a>` : `<strong>${trackDisplay}</strong>`;
                return `<div style="padding:8px;border-bottom:1px solid #f1f5f9;">${trackLink} — ${recipient}</div>`;
            }).join('');

            const html = `
                <div style="background: white; padding: 24px; border-radius: 12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <h2 style="margin:0;"><i class="fas fa-route"></i> Trip Preview</h2>
                        <div>
                            <button id="closeTripPreviewBtn" class="filter-btn">Close</button>
                        </div>
                    </div>
                    <div style="margin-top:16px; display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:12px;">
                        <div style="padding:12px; border:1px solid #e5e7eb; border-radius:8px;">
                            <strong>Trip ID</strong>
                            <div>${trip.id}</div>
                        </div>
                        <div style="padding:12px; border:1px solid #e5e7eb; border-radius:8px;">
                            <strong>Stops</strong>
                            <div>${stopsLen}</div>
                        </div>
                        <div style="padding:12px; border:1px solid #e5e7eb; border-radius:8px;">
                            <strong>Parcels</strong>
                            <div>${parcelItems.length}</div>
                        </div>
                    </div>
                    <div style="margin-top:16px;">
                        <strong>Parcels:</strong>
                        <div style="margin-top:8px; max-height:200px; overflow:auto;">
                            ${parcelsListHtml}
                        </div>
                    </div>
                </div>
            `;

            content.innerHTML = html;
            modal.style.display = 'flex';

            const closeBtn = document.getElementById('closeTripPreviewBtn');
            if (closeBtn) {
                closeBtn.addEventListener('click', () => {
                    modal.style.display = 'none';
                });
            }

            // Note: openTripPageBtn may not exist on this modal; check before binding
            const openTripBtn = document.getElementById('openTripPageBtn');
            if (openTripBtn) {
                openTripBtn.addEventListener('click', () => {
                    window.location.href = `view_trip.php?id=${trip.id}`;
                });
            }
        }

        // Initialize paget
        (function init() {
            try {
                setupFilterListeners();
                fetchOutletTrips();
            } catch (e) {
                console.error('Initialization error:', e);
            }
        })();
    </script>
</body>
</html>
