<?php
require_once '../includes/auth_guard.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$current_user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Trips at Outlet </title>
   
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../css/outlet-dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/trips.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/trips_enhanced.css?v=<?php echo time(); ?>">
    
    <script>
        if ('caches' in window) {
            caches.keys().then(names => names.forEach(name => caches.delete(name)));
        }
        try { localStorage.clear(); } catch(e) {}
        try { sessionStorage.clear(); } catch(e) {}
    </script>
    
    <style>
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

        
        .manager-action-btn {
            padding: 0.5rem 1rem !important;
            border-radius: 8px !important;
            font-size: 0.875rem !important;
            font-weight: 600 !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 0.5rem !important;
            cursor: pointer !important;
            transition: transform 0.12s ease, box-shadow 0.12s ease !important;
        }
        .manager-action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(16,24,40,0.08);
        }

        
        .status-pill {
            color: #fff !important;
            padding: 0.45rem 0.9rem !important;
            border-radius: 8px !important;
            font-weight: 700 !important;
            font-size: 0.85rem !important;
            text-transform: none !important; 
            letter-spacing: 0.02em;
        }

        
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9998;
            animation: fadeIn 0.2s ease;
        }

        .modal-overlay.show {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .modal-container {
            background: white;
            border-radius: 12px;
            max-width: 900px;
            width: 100%;
            max-height: 90vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            animation: slideUp 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 700;
            color: #111827;
        }

        .modal-close-btn {
            background: none;
            border: none;
            color: #6b7280;
            cursor: pointer;
            padding: 0.5rem;
            font-size: 1.5rem;
            line-height: 1;
            transition: color 0.2s;
        }

        .modal-close-btn:hover {
            color: #111827;
        }

        .modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 1.5rem;
        }

        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .parcel-selector {
            display: grid;
            gap: 1rem;
        }

        .parcel-item {
            background: #f9fafb;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 1rem;
            transition: all 0.2s;
            cursor: pointer;
        }

        .parcel-item:hover {
            border-color: #3b82f6;
            background: #f0f9ff;
        }

        .parcel-item.selected {
            border-color: #3b82f6;
            background: #dbeafe;
        }

        .parcel-item-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 0.75rem;
        }

        .parcel-checkbox {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .modal-actions {
            display: flex;
            gap: 0.75rem;
        }

        .modal-btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .modal-btn-primary {
            background: #3b82f6;
            color: white;
        }

        .modal-btn-primary:hover {
            background: #2563eb;
        }

        .modal-btn-primary:disabled {
            background: #9ca3af;
            cursor: not-allowed;
        }

        .modal-btn-secondary {
            background: #f3f4f6;
            color: #374151;
        }

        .modal-btn-secondary:hover {
            background: #e5e7eb;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6b7280;
        }

        .empty-state i {
            font-size: 3rem;
            color: #d1d5db;
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .main-content {
                padding-left: 0.5rem;
                padding-right: 0.5rem;
                max-width: 100%;
            }

            .content-container {
                margin: 10px 0.5rem;
                padding: 20px 15px;
                border-radius: 8px;
            }

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

            .modal-container {
                max-width: 100%;
                max-height: 100vh;
                border-radius: 0;
            }

            .modal-footer {
                flex-direction: column;
            }

            .modal-actions {
                width: 100%;
                flex-direction: column;
            }

            .modal-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">

    <div class="mobile-dashboard">
        <?php include '../includes/navbar.php'; ?>
        <?php include '../includes/sidebar.php'; ?>
        <div class="menu-overlay" id="menuOverlay"></div>

        <main class="main-content">
            <div class="content-container">
                <h1><i class="fas fa-route"></i> Trips at Outlet</h1>
                <p class="subtitle">View and manage all trips that start from or pass through your outlet and their parcel contents.</p>

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
                            <button class="filter-btn filter-btn-reset" onclick="fetchOutletTrips(true)" style="margin-left: auto;">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                        </div>

                        <div class="active-filters" id="activeFilters" style="display: none;"></div>
                    </div>
                </div>

                <div id="tripsSection" style="margin-bottom: 2rem;">
                    <div id="tripsList" style="display: grid; gap: 1rem;">
                        <p style="text-align: center; color: #6b7280;"><i class="fas fa-spinner fa-spin"></i> Loading trips...</p>
                    </div>
                </div>
            </div>
        </main>

        <!-- Add Parcels Modal -->
        <div class="modal-overlay" id="addParcelsModal">
            <div class="modal-container">
                <div class="modal-header">
                    <h3><i class="fas fa-plus-circle"></i> Add Parcels to Trip</h3>
                    <button class="modal-close-btn" onclick="closeAddParcelsModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="info-box" style="background: #e0f2fe; border: 1px solid #3b82f6; border-radius: 8px; padding: 1rem; margin-bottom: 1rem;">
                        <h4 style="margin: 0 0 0.5rem 0; color: #1e40af; font-size: 0.875rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-info-circle"></i> Smart Route Filtering
                        </h4>
                        <p style="margin: 0; color: #1e3a8a; font-size: 0.875rem;">
                            Showing parcels from <strong>any outlet on this trip's route</strong> whose destinations are also on the route. 
                            Parcels must have status "pending" or "scheduled" and must not be assigned to other active trips.
                        </p>
                        <div id="tripRouteInfo" style="margin-top: 0.75rem; padding: 0.75rem; background: rgba(255,255,255,0.5); border-radius: 4px; font-size: 0.8rem; color: #1e3a8a;">
                            <strong><i class="fas fa-route"></i> Trip Route:</strong>
                            <span id="tripRouteOutlets">Loading...</span>
                        </div>
                    </div>
                    <div id="availableParcelsLoading" style="text-align: center; padding: 2rem; color: #6b7280;">
                        <i class="fas fa-spinner fa-spin" style="font-size: 2rem; margin-bottom: 0.5rem;"></i>
                        <p>Loading available parcels...</p>
                    </div>
                    <div id="availableParcelsContainer" class="parcel-selector" style="display: none;">
                        
                    </div>
                </div>
                <div class="modal-footer">
                    <div>
                        <span id="selectedCount" style="color: #6b7280; font-weight: 600;">0 parcels selected</span>
                    </div>
                    <div class="modal-actions">
                        <button class="modal-btn modal-btn-secondary" onclick="closeAddParcelsModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button class="modal-btn modal-btn-primary" id="confirmAddParcelsBtn" onclick="confirmAddParcels()" disabled>
                            <i class="fas fa-check"></i> Add Selected Parcels
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
       
        const menuBtn = document.getElementById('menuBtn');
        const closeMenu = document.getElementById('closeMenu');
        const sidebar = document.getElementById('sidebar');
        const menuOverlay = document.getElementById('menuOverlay');

        function toggleMenu() {
            if (!sidebar || !menuOverlay) return;
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

        if (closeMenu) {
            closeMenu.addEventListener('click', toggleMenu);
        }
        if (menuOverlay) {
            menuOverlay.addEventListener('click', toggleMenu);
        }

        const menuItems = document.querySelectorAll('.menu-items a');
        if (menuItems && menuItems.length) {
            menuItems.forEach(item => {
                item.addEventListener('click', () => {
                    if (typeof toggleMenu === 'function') toggleMenu();
                });
            });
        }

        const outlet_id = <?php echo json_encode($current_user['outlet_id'] ?? ''); ?>;
        const userRole = <?php echo json_encode($current_user['role'] ?? ''); ?>;
        
        
        window.currentOutletId = outlet_id;
        
        let allTrips = [];
        let filteredTrips = [];
        let selectedParcels = new Set();
        let currentTripId = null;

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

        function setupFilterListeners() {
            document.getElementById('filterStatus').addEventListener('change', function() {
                applyFilters();
            });

            document.getElementById('filterDriver').addEventListener('change', function() {
                applyFilters();
            });

            document.getElementById('filterDateFrom').addEventListener('change', function() {
                applyFilters();
            });

            document.getElementById('filterDateTo').addEventListener('change', function() {
                applyFilters();
            });

            let searchTimeout;
            document.getElementById('filterSearch').addEventListener('input', function() {
                clearTimeout(searchTimeout);
                const searchValue = this.value;
                searchTimeout = setTimeout(() => {
                    applyFilters();
                }, 300);
            });
        }

        let currentPage = 1;
        const pageSize = 25; 
        let loadingMore = false;

        async function fetchOutletTrips(refresh = false) {
            if (refresh) {
                currentPage = 1;
                allTrips = [];
                filteredTrips = [];
            }

            try {
                const container = document.getElementById('tripsList');
                if (currentPage === 1) {
                    container.innerHTML = '<p style="text-align: center; color: #6b7280;"><i class="fas fa-spinner fa-spin"></i> Loading trips...</p>';
                } else {
                
                    renderLoadMoreButton(true);
                }

              
                const apiUrl = `../api/trips/fetch_outlet_trips.php?page=${currentPage}&page_size=${pageSize}` + (refresh ? `&v=${Date.now()}` : '');

                const response = await fetch(apiUrl, {
                    credentials: 'same-origin'
                });

                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }

                const data = await response.json();

                if (data.success && Array.isArray(data.trips)) {
                    if (currentPage === 1) {
                        allTrips = data.trips;
                    } else {
                        
                        allTrips = allTrips.concat(data.trips);
                    }

                    filteredTrips = [...allTrips];

                    populateDriverFilter();
                    updateSummary();
                    displayTrips(filteredTrips);

                    
                    const moreLikely = (typeof data.is_last_page !== 'undefined') ? !data.is_last_page : (data.trips.length === pageSize);
                    renderLoadMoreButton(!moreLikely);

                    
                    if (moreLikely) {
                        currentPage += 1;
                    }
                } else {
                    
                    if (currentPage === 1) {
                        allTrips = [];
                        filteredTrips = [];
                        container.innerHTML = `
                            <div style="text-align: center; padding: 2rem; background: #f9fafb; border-radius: 8px; border: 1px solid #e5e7eb;">
                                <i class="fas fa-paper-plane" style="font-size: 3rem; color: #d1d5db; margin-bottom: 1rem;"></i>
                                <p style="color: #6b7280; margin: 0; font-weight: 600; margin-bottom: 0.5rem;">No Trips</p>
                                <p style="color: #9ca3af; margin: 0; font-size: 0.875rem;">${data.message || 'No trips involving this outlet.'}</p>
                                <p style="color: #9ca3af; margin: 0.5rem 0 0 0; font-size: 0.875rem;">${outlet_id ? 'Trips will appear here when parcels are assigned to routes through your outlet.' : 'Create a new trip from the <a href="trip_wizard.php" style="color: #3b82f6; text-decoration: underline;">Trip Wizard</a>'}</p>
                            </div>
                        `;
                        document.getElementById('tripsSummary').style.display = 'none';
                    } else {
                        
                        renderLoadMoreButton(true);
                    }
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
            } finally {
                loadingMore = false;
            }
        }

        function renderLoadMoreButton(hideButton = false) {
            const existing = document.getElementById('loadMoreContainer');
            if (existing) existing.remove();

            if (hideButton) return;

            const container = document.getElementById('tripsSection');
            const div = document.createElement('div');
            div.id = 'loadMoreContainer';
            div.style.cssText = 'text-align: center; margin-top: 1rem;';
            div.innerHTML = `<button id="loadMoreBtn" style="background: #3b82f6; color: white; border: none; padding: 0.75rem 1.25rem; border-radius: 8px; font-weight: 600; cursor: pointer;">Load more trips</button>`;
            container.appendChild(div);

            document.getElementById('loadMoreBtn').addEventListener('click', async () => {
                if (loadingMore) return;
                loadingMore = true;
                document.getElementById('loadMoreBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
                await fetchOutletTrips(false);
                document.getElementById('loadMoreBtn')?.remove();
            });
        }

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

        function applyFilters() {
            const status = document.getElementById('filterStatus').value;
            const driver = document.getElementById('filterDriver').value;
            const dateFrom = document.getElementById('filterDateFrom').value;
            const dateTo = document.getElementById('filterDateTo').value;
            const search = document.getElementById('filterSearch').value.toLowerCase();

            filteredTrips = allTrips.filter(trip => {
                if (status && trip.trip_status !== status) {
                    return false;
                }

                if (driver && trip.driver?.id !== driver) {
                    return false;
                }

                if (dateFrom && trip.departure_time) {
                    const tripDate = new Date(trip.departure_time).toISOString().split('T')[0];
                    if (tripDate < dateFrom) {
                        return false;
                    }
                }
                
                if (dateTo && trip.departure_time) {
                    const tripDate = new Date(trip.departure_time).toISOString().split('T')[0];
                    if (tripDate > dateTo) {
                        return false;
                    }
                }

                if (search) {
                    const tripId = (trip.id || '').toLowerCase();
                    const driverName = trip.driver?.driver_name?.toLowerCase() || '';
                    const vehicleName = trip.vehicle?.name?.toLowerCase() || '';
                    const trackNumbers = (trip.parcels || []).map(p => (p.track_number || '').toLowerCase());
                    
                    const matches = tripId.includes(search) || 
                                  driverName.includes(search) || 
                                  vehicleName.includes(search) ||
                                  trackNumbers.some(tn => tn.includes(search));
                    
                    if (!matches) {
                        return false;
                    }
                }

                return true;
            });

            displayTrips(filteredTrips);
            updateSummary();
            updateActiveFilters();
        }

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

        function displayTrips(trips) {
            const container = document.getElementById('tripsList');
            container.innerHTML = '';

            if (trips.length === 0) {
                container.innerHTML = `
                    <div style="text-align: center; padding: 2rem; background: #f9fafb; border-radius: 8px; border: 1px solid #e5e7eb;">
                        <i class="fas fa-filter" style="font-size: 3rem; color: #d1d5db; margin-bottom: 1rem;"></i>
                        <p style="color: #6b7280; margin: 0;">No trips match your filters. Try adjusting your search criteria.</p>
                    </div>
                `;
                return;
            }

            trips.forEach(trip => {
                const tripCard = createTripCard(trip);
                container.appendChild(tripCard);
            });
        }

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

            const statusColors = {
                'scheduled': '#fbbf24',
                'in_transit': '#3b82f6',
                'completed': '#10b981',
                'at_outlet': '#8b5cf6',
                'cancelled': '#ef4444'
            };
            const statusColor = statusColors[trip.trip_status] || '#6b7280';

            const header = document.createElement('div');
            header.style.cssText = 'display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;';

            
            const currentStop = (trip.stops || []).find(s => s.outlet && String(s.outlet.id) === String(outlet_id));
            const arrived = currentStop && currentStop.arrival_time;
            const departed = currentStop && currentStop.departure_time;

            
            let managerActionButtons = '';

            const tripAuth = trip.authorized_actions || {};
            const stopAuth = currentStop && currentStop.authorized_actions ? currentStop.authorized_actions : {};

            
            if (currentStop) {
                if (!arrived && (trip.trip_status === 'in_transit' || trip.trip_status === 'accepted') && stopAuth.can_arrive) {
                    managerActionButtons += `
                        <button class="manager-action-btn" onclick="managerArriveStop('${trip.id}', '${currentStop.id}', '${currentStop.outlet.id}')"
                           style="background: #f59e0b; color: white; border: none; font-size: 0.875rem; padding: 0.5rem 1rem; transition: background 0.2s;"
                           onmouseover="this.style.background='#d97706'"
                           onmouseout="this.style.background='#f59e0b'"
                           title="Mark arrival at this stop">
                            <i class="fas fa-map-pin"></i> Mark Arrived
                        </button>
                    `;
                } else if (arrived && !departed && stopAuth.can_depart) {
                    managerActionButtons += `
                        <button class="manager-action-btn" onclick="managerDepartStop('${trip.id}', '${currentStop.id}', '${currentStop.outlet.id}')"
                           style="background: #10b981; color: white; border: none; font-size: 0.875rem; padding: 0.5rem 1rem; transition: background 0.2s;"
                           onmouseover="this.style.background='#059669'"
                           onmouseout="this.style.background='#10b981'"
                           title="Mark departure from this stop">
                            <i class="fas fa-flag"></i> Mark Departed
                        </button>
                    `;
                }
            }

            header.innerHTML = `
                <div>
                    <h4 style="margin: 0 0 0.5rem 0; color: #111827; font-size: 1.125rem;">
                        <i class="fas fa-route"></i> Trip #${trip.id.substring(0, 8)}...
                        ${trip.is_origin_outlet ? '<span style="background: #3b82f6; color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem; margin-left: 0.5rem;">ORIGIN</span>' : ''}
                    </h4>
                    <p style="margin: 0; color: #6b7280; font-size: 0.875rem;">
                        <i class="fas fa-box"></i> ${trip.total_parcels} parcel${trip.total_parcels !== 1 ? 's' : ''}
                        • Stop #${trip.outlet_stop_order || '?'} of ${trip.stops.length}
                    </p>
                </div>
                <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                    ${(['scheduled', 'at_outlet', 'accepted'].includes(trip.trip_status) && (trip.is_origin_outlet || trip.is_part_of_route)) ? `
                    <button onclick="openAddParcelsModal('${trip.id}')"
                       style="background: #8b5cf6; color: white; border: none; font-size: 0.875rem; font-weight: 600; padding: 0.5rem 1rem; border-radius: 6px; transition: background 0.2s; cursor: pointer; display: flex; align-items: center; gap: 0.5rem;"
                       onmouseover="this.style.background='#7c3aed'"
                       onmouseout="this.style.background='#8b5cf6'"
                       title="Add parcels to this trip (route-matched only)">
                        <i class="fas fa-plus-circle"></i> Add Parcels
                    </button>
                    ` : ''}
                    ${ (trip.driver && trip.driver.id && userRole !== 'outlet_manager') ? `
                    <button onclick="openGPSTracking('${trip.id}', '${trip.driver.driver_name || 'Unknown Driver'}')"
                       style="background: #10b981; color: white; border: none; font-size: 0.875rem; font-weight: 600; padding: 0.5rem 1rem; border-radius: 6px; transition: background 0.2s; cursor: pointer; display: flex; align-items: center; gap: 0.5rem;"
                       onmouseover="this.style.background='#059669'"
                       onmouseout="this.style.background='#10b981'"
                       title="Track Trip GPS">
                        <i class="fas fa-map-marker-alt"></i> GPS Track
                    </button>
                    ` : ''}

                    ${managerActionButtons}

                    <span class="status-pill" style="background: ${statusColor};">
                        ${trip.trip_status.replace('_', ' ')}
                    </span>
                </div>
            `;
            card.appendChild(header);

            const infoGrid = document.createElement('div');
            infoGrid.style.cssText = 'display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem;';
            
            const driverName = trip.driver ? trip.driver.driver_name : 'Not Assigned';
            const vehicleName = trip.vehicle ? `${trip.vehicle.name} (${trip.vehicle.plate_number || 'N/A'})` : 'Not Assigned';
            const departureTime = trip.departure_time ? new Date(trip.departure_time).toLocaleString() : 'Not Scheduled';
            const arrivalTime = trip.arrival_time ? new Date(trip.arrival_time).toLocaleString() : 'Not Scheduled';

            infoGrid.innerHTML = `
                <div>
                    <p style="margin: 0; color: #6b7280; font-size: 0.875rem;"><i class="fas fa-user"></i> Driver</p>
                    <p style="margin: 0.25rem 0 0 0; color: #111827; font-weight: 500;">${driverName}</p>
                </div>
                <div>
                    <p style="margin: 0; color: #6b7280; font-size: 0.875rem;"><i class="fas fa-truck"></i> Vehicle</p>
                    <p style="margin: 0.25rem 0 0 0; color: #111827; font-weight: 500;">${vehicleName}</p>
                </div>
                <div>
                    <p style="margin: 0; color: #6b7280; font-size: 0.875rem;"><i class="fas fa-calendar-check"></i> Departure</p>
                    <p style="margin: 0.25rem 0 0 0; color: #111827; font-weight: 500; font-size: 0.875rem;">${departureTime}</p>
                </div>
                <div>
                    <p style="margin: 0; color: #6b7280; font-size: 0.875rem;"><i class="fas fa-calendar-times"></i> Arrival</p>
                    <p style="margin: 0.25rem 0 0 0; color: #111827; font-weight: 500; font-size: 0.875rem;">${arrivalTime}</p>
                </div>
            `;
            card.appendChild(infoGrid);

            const routeSection = document.createElement('div');
            routeSection.style.cssText = 'border-top: 1px solid #e5e7eb; padding-top: 1rem; margin-bottom: 1rem;';
            const routeToggle = document.createElement('button');
            routeToggle.style.cssText = 'background: none; border: none; color: #3b82f6; cursor: pointer; padding: 0; font-weight: 600; display: flex; align-items: center; gap: 0.5rem;';
            routeToggle.innerHTML = '<i class="fas fa-chevron-down"></i> Route Details';
            
            const routeContent = document.createElement('div');
            routeContent.style.cssText = 'display: none; margin-top: 1rem;';
            
            let routeHtml = '<div style="position: relative; padding-left: 2rem;">';
            trip.stops.forEach((stop, index) => {
                const isCurrentOutlet = stop.outlet.id === outlet_id;
                routeHtml += `
                    <div style="position: relative; padding: 0.75rem 0; ${index < trip.stops.length - 1 ? 'border-left: 2px solid #e5e7eb;' : ''}">
                        <div style="position: absolute; left: -0.625rem; top: 1rem; width: 1.25rem; height: 1.25rem; border-radius: 50%; background: ${isCurrentOutlet ? '#3b82f6' : '#10b981'}; border: 3px solid white;"></div>
                        <div style="margin-left: 1rem;">
                            <p style="margin: 0; color: #111827; font-weight: 600;">
                                Stop ${stop.stop_order}: ${stop.outlet.outlet_name}
                                ${isCurrentOutlet ? '<span style="background: #dbeafe; color: #1e40af; padding: 0.125rem 0.5rem; border-radius: 4px; font-size: 0.75rem; margin-left: 0.5rem;">THIS OUTLET</span>' : ''}
                            </p>
                            <p style="margin: 0.25rem 0 0 0; color: #6b7280; font-size: 0.875rem;">${stop.outlet.location || 'Location not set'}</p>
                        </div>
                    </div>
                `;
            });
            routeHtml += '</div>';
            routeContent.innerHTML = routeHtml;

            routeToggle.addEventListener('click', () => {
                const isHidden = routeContent.style.display === 'none';
                routeContent.style.display = isHidden ? 'block' : 'none';
                routeToggle.querySelector('i').className = isHidden ? 'fas fa-chevron-up' : 'fas fa-chevron-down';
            });

            routeSection.appendChild(routeToggle);
            routeSection.appendChild(routeContent);
            card.appendChild(routeSection);

            if (trip.parcels.length > 0) {
                const parcelsSection = document.createElement('div');
                parcelsSection.style.cssText = 'border-top: 1px solid #e5e7eb; padding-top: 1rem;';
                
                const parcelsToggle = document.createElement('button');
                parcelsToggle.style.cssText = 'background: none; border: none; color: #3b82f6; cursor: pointer; padding: 0; font-weight: 600; display: flex; align-items: center; gap: 0.5rem;';
                parcelsToggle.innerHTML = `<i class="fas fa-chevron-down"></i> View Parcels (${trip.parcels.length})`;
                
                const parcelsContent = document.createElement('div');
                parcelsContent.style.cssText = 'display: none; margin-top: 1rem;';
                parcelsContent.id = `parcels-content-${trip.id}`;
                
                
                parcelsContent.innerHTML = '<p style="text-align: center; color: #6b7280; padding: 1rem;"><i class="fas fa-spinner fa-spin"></i> Loading parcels...</p>';
                
                let parcelsLoaded = false;
                
                parcelsToggle.addEventListener('click', () => {
                    const isHidden = parcelsContent.style.display === 'none';
                    parcelsContent.style.display = isHidden ? 'block' : 'none';
                    parcelsToggle.querySelector('i').className = isHidden ? 'fas fa-chevron-up' : 'fas fa-chevron-down';
                    
                    
                    if (isHidden && !parcelsLoaded) {
                        renderParcelsForTrip(trip, parcelsContent);
                        parcelsLoaded = true;
                    }
                });

                parcelsSection.appendChild(parcelsToggle);
                parcelsSection.appendChild(parcelsContent);
                card.appendChild(parcelsSection);
            } else {
                const noParcels = document.createElement('div');
                noParcels.style.cssText = 'border-top: 1px solid #e5e7eb; padding-top: 1rem; text-align: center; color: #6b7280; font-size: 0.875rem;';
                noParcels.innerHTML = '<i class="fas fa-inbox"></i> No parcels assigned to this trip yet';
                card.appendChild(noParcels);
            }

            return card;
        }

        function openGPSTracking(tripId, driverName) {
            const gpsUrl = `../gps_tracking.php?trip_id=${encodeURIComponent(tripId)}&driver_name=${encodeURIComponent(driverName)}`;
            window.open(gpsUrl, '_blank', 'width=1200,height=800,scrollbars=yes,resizable=yes');
        }

        
        async function managerArriveStop(tripId, stopId, outletId) {
            if (!confirm('Mark arrival at this outlet?')) return;
            try {
                const timestamp = new Date().toISOString();
                const resp = await fetch('../api/manager_update_trip_stop.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ trip_id: tripId, stop_id: stopId, action: 'arrive', timestamp })
                });
                const data = await resp.json();
                if (data.success) {
                    alert('Arrival recorded');
                    fetchOutletTrips(true);
                } else {
                    alert('Error: ' + (data.error || 'Failed to record arrival'));
                }
            } catch (err) {
                console.error('Arrive error', err);
                alert('Failed to mark arrival: ' + err.message);
            }
        }

        async function managerDepartStop(tripId, stopId, outletId) {
            if (!confirm('Mark departure from this outlet?')) return;
            try {
                const timestamp = new Date().toISOString();
                const resp = await fetch('../api/manager_update_trip_stop.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ trip_id: tripId, stop_id: stopId, action: 'depart', timestamp })
                });
                const data = await resp.json();
                if (data.success) {
                    alert('Departure recorded');
                    fetchOutletTrips(true);
                } else {
                    alert('Error: ' + (data.error || 'Failed to record departure'));
                }
            } catch (err) {
                console.error('Depart error', err);
                alert('Failed to mark departure: ' + err.message);
            }
        }

        
        function renderParcelsForTrip(trip, container) {
            const statusClass = {
                'pending': 'background: #fef3c7; color: #92400e;',
                'assigned': 'background: #dbeafe; color: #1e40af;',
                'in_transit': 'background: #e0e7ff; color: #3730a3;',
                'completed': 'background: #d1fae5; color: #065f46;',
                'cancelled': 'background: #fee2e2; color: #991b1b;',
                'at_outlet': 'background: #e0e7ff; color: #5b21b6;',
                'out_for_delivery': 'background: #dbeafe; color: #1e40af;',
                'delivered': 'background: #d1fae5; color: #065f46;'
            };

            let parcelsHtml = '<div style="display: grid; gap: 0.75rem;">';
            trip.parcels.forEach(parcel => {
                const statusStyle = statusClass[parcel.parcel_list_status] || 'background: #f3f4f6; color: #374151;';
                const hasBarcode = parcel.barcode_url ? '<i class="fas fa-check-circle" style="color: #10b981;" title="Barcode Generated"></i>' : '<i class="fas fa-times-circle" style="color: #ef4444;" title="No Barcode"></i>';
                
                
                const isIncoming = parcel.origin_outlet_id !== window.currentOutletId;
                const incomingBadge = isIncoming ? '<span style="background: #f59e0b; color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem; margin-left: 0.5rem; font-weight: 600;"><i class="fas fa-arrow-circle-down"></i> INCOMING</span>' : '';
                
                parcelsHtml += `
                    <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 1rem; transition: all 0.2s;">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.75rem;">
                            <div style="flex: 1;">
                                <p style="margin: 0; font-weight: 600; color: #111827; font-size: 1rem;">
                                    <i class="fas fa-barcode"></i> ${parcel.track_number}
                                    ${incomingBadge}
                                    ${hasBarcode}
                                </p>
                                <p style="margin: 0.25rem 0 0 0; color: #6b7280; font-size: 0.875rem;">
                                    <i class="fas fa-user"></i> <strong>From:</strong> ${parcel.sender_name}
                                </p>
                                <p style="margin: 0.25rem 0 0 0; color: #6b7280; font-size: 0.875rem;">
                                    <i class="fas fa-user-check"></i> <strong>To:</strong> ${parcel.receiver_name}
                                </p>
                                ${parcel.receiver_phone ? `<p style="margin: 0.25rem 0 0 0; color: #6b7280; font-size: 0.875rem;"><i class="fas fa-phone"></i> ${parcel.receiver_phone}</p>` : ''}
                                ${parcel.receiver_address ? `<p style="margin: 0.25rem 0 0 0; color: #6b7280; font-size: 0.875rem;"><i class="fas fa-map-marker-alt"></i> ${parcel.receiver_address}</p>` : ''}
                            </div>
                            <div style="text-align: right;">
                                <span style="${statusStyle} padding: 0.375rem 0.75rem; border-radius: 6px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; display: inline-block;">
                                    ${parcel.parcel_list_status.replace('_', ' ')}
                                </span>
                            </div>
                        </div>
                        
                        <div style="background: white; border: 1px solid #e5e7eb; border-radius: 6px; padding: 0.75rem; margin-bottom: 0.75rem;">
                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                <i class="fas fa-route" style="color: #3b82f6;"></i>
                                <span style="font-weight: 600; color: #374151; font-size: 0.875rem;">Route</span>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; gap: 0.5rem;">
                                <div style="text-align: left;">
                                    <p style="margin: 0; color: #6b7280; font-size: 0.75rem;">Origin</p>
                                    <p style="margin: 0.25rem 0 0 0; color: #111827; font-weight: 600; font-size: 0.875rem;">${parcel.origin_outlet_name}</p>
                                </div>
                                <div style="text-align: center;">
                                    <i class="fas fa-arrow-right" style="color: #3b82f6; font-size: 1.25rem;"></i>
                                </div>
                                <div style="text-align: right;">
                                    <p style="margin: 0; color: #6b7280; font-size: 0.75rem;">Destination</p>
                                    <p style="margin: 0.25rem 0 0 0; color: #111827; font-weight: 600; font-size: 0.875rem;">${parcel.destination_outlet_name}</p>
                                </div>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 0.75rem; font-size: 0.875rem; margin-bottom: 0.75rem;">
                            <div style="background: white; padding: 0.5rem; border-radius: 4px; border: 1px solid #e5e7eb;">
                                <div style="display: flex; align-items: center; gap: 0.25rem; color: #6b7280; margin-bottom: 0.25rem;">
                                    <i class="fas fa-weight"></i>
                                    <span style="font-size: 0.75rem;">Weight</span>
                                </div>
                                <p style="margin: 0; color: #111827; font-weight: 600;">${parcel.parcel_weight || 0} kg</p>
                            </div>
                            <div style="background: white; padding: 0.5rem; border-radius: 4px; border: 1px solid #e5e7eb;">
                                <div style="display: flex; align-items: center; gap: 0.25rem; color: #6b7280; margin-bottom: 0.25rem;">
                                    <i class="fas fa-dollar-sign"></i>
                                    <span style="font-size: 0.75rem;">Delivery Fee</span>
                                </div>
                                <p style="margin: 0; color: #111827; font-weight: 600;">ZMW ${parcel.delivery_fee || 0}</p>
                            </div>
                            ${parcel.status ? `
                            <div style="background: white; padding: 0.5rem; border-radius: 4px; border: 1px solid #e5e7eb;">
                                <div style="display: flex; align-items: center; gap: 0.25rem; color: #6b7280; margin-bottom: 0.25rem;">
                                    <i class="fas fa-info-circle"></i>
                                    <span style="font-size: 0.75rem;">Parcel Status</span>
                                </div>
                                <p style="margin: 0; color: #111827; font-weight: 600; font-size: 0.75rem; text-transform: capitalize;">${parcel.status.replace('_', ' ')}</p>
                            </div>
                            ` : ''}
                        </div>

                        <div style="display: flex; gap: 0.5rem; align-items: center; padding-top: 0.75rem; border-top: 1px solid #e5e7eb;">
                            <button onclick="viewParcelDetails('${parcel.id}')"
                               style="flex: 1; text-align: center; background: #3b82f6; color: white; border: none; font-size: 0.875rem; font-weight: 600; padding: 0.5rem 1rem; border-radius: 6px; transition: background 0.2s; cursor: pointer;"
                               onmouseover="this.style.background='#2563eb'"
                               onmouseout="this.style.background='#3b82f6'">
                                <i class="fas fa-eye"></i> View Details
                            </button>
                            ${parcel.barcode_url ? `
                            <a href="${parcel.barcode_url}" target="_blank"
                               style="background: #8b5cf6; color: white; text-decoration: none; font-size: 0.875rem; font-weight: 600; padding: 0.5rem 1rem; border-radius: 6px; transition: background 0.2s; display: inline-block;"
                               onmouseover="this.style.background='#7c3aed'"
                               onmouseout="this.style.background='#8b5cf6'"
                               title="View Barcode">
                                <i class="fas fa-barcode"></i>
                            </a>
                            ` : ''}
                            ${(parcel.parcel_list_status === 'pending' || parcel.parcel_list_status === 'assigned') ? `
                            <button onclick="removeParcelFromTrip('${parcel.parcel_list_id}', '${parcel.track_number}', '${trip.id}')"
                               style="background: #ef4444; color: white; border: none; font-size: 0.875rem; font-weight: 600; padding: 0.5rem 1rem; border-radius: 6px; transition: background 0.2s; cursor: pointer;"
                               onmouseover="this.style.background='#dc2626'"
                               onmouseout="this.style.background='#ef4444'"
                               title="Remove parcel from trip">
                                <i class="fas fa-times-circle"></i>
                            </button>
                            ` : ''}
                        </div>
                    </div>
                `;
            });
            parcelsHtml += '</div>';
            container.innerHTML = parcelsHtml;
        }

        
        
        async function openAddParcelsModal(tripId) {
            currentTripId = tripId;
            selectedParcels.clear();
            
            const modal = document.getElementById('addParcelsModal');
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
            
            
            document.getElementById('availableParcelsLoading').style.display = 'block';
            document.getElementById('availableParcelsContainer').style.display = 'none';
            
            
            const trip = filteredTrips.find(t => t.id === tripId) || allTrips.find(t => t.id === tripId);
            if (trip && trip.stops) {
                const routeOutlets = trip.stops.map(stop => stop.outlet?.outlet_name || 'Unknown').join(' → ');
                document.getElementById('tripRouteOutlets').textContent = routeOutlets;
            } else {
                document.getElementById('tripRouteOutlets').textContent = 'Loading route information...';
            }
            
            
            try {
                const response = await fetch(`../api/trips/fetch_available_parcels.php?trip_id=${encodeURIComponent(tripId)}&v=${Date.now()}`, {
                    credentials: 'same-origin',
                    cache: 'no-store'
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const text = await response.text();
                let data;
                
                try {
                    data = JSON.parse(text);
                } catch (parseError) {
                    console.error('JSON Parse Error:', parseError);
                    console.error('Response text:', text.substring(0, 500));
                    throw new Error('Invalid JSON response from server');
                }
                
                console.log('Available parcels response:', data);
                
                
                if (data.valid_destinations && data.valid_destinations.length > 0) {
                    
                    
                    const filterMsg = data.filtered_by_route ? ` (${data.valid_destinations.length} destination outlets)` : ' (all destinations)';
                    const currentRouteText = document.getElementById('tripRouteOutlets').textContent;
                    if (currentRouteText === 'Loading route information...') {
                        document.getElementById('tripRouteOutlets').textContent = `${data.valid_destinations.length} outlet(s) on route`;
                    }
                }
                
                if (data.success && data.parcels && data.parcels.length > 0) {
                    renderAvailableParcels(data.parcels);
                } else {
                    
                    let debugMsg = data.message || 'No parcels available to add';
                    if (data.debug_info) {
                        console.log('Debug info:', data.debug_info);
                        if (data.debug_info.parcel_statuses) {
                            const statusList = Object.entries(data.debug_info.parcel_statuses)
                                .map(([status, count]) => `${count} ${status}`)
                                .join(', ');
                            debugMsg += `<br><small style="color: #6b7280;">Found at outlet: ${statusList}</small>`;
                        }
                    }
                    showNoParcelsMessage(debugMsg);
                }
            } catch (error) {
                console.error('Error fetching available parcels:', error);
                showErrorMessage('Failed to load available parcels: ' + error.message);
            }
        }

        function closeAddParcelsModal() {
            const modal = document.getElementById('addParcelsModal');
            modal.classList.remove('show');
            document.body.style.overflow = '';
            currentTripId = null;
            selectedParcels.clear();
            updateSelectedCount();
        }

        function renderAvailableParcels(parcels) {
            const container = document.getElementById('availableParcelsContainer');
            
            container.innerHTML = parcels.map(parcel => `
                <div class="parcel-item" onclick="toggleParcelSelection('${parcel.id}')" id="parcel-${parcel.id}">
                    <div class="parcel-item-header">
                        <div style="flex: 1;">
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="checkbox" 
                                       class="parcel-checkbox" 
                                       id="checkbox-${parcel.id}"
                                       onchange="toggleParcelSelection('${parcel.id}')"
                                       onclick="event.stopPropagation()">
                                <span style="font-weight: 700; color: #111827; font-size: 1rem;">
                                    <i class="fas fa-barcode"></i> ${parcel.track_number}
                                </span>
                            </label>
                            <p style="margin: 0.5rem 0 0 1.75rem; color: #6b7280; font-size: 0.875rem;">
                                <strong>From:</strong> ${parcel.sender_name}
                            </p>
                            <p style="margin: 0.25rem 0 0 1.75rem; color: #6b7280; font-size: 0.875rem;">
                                <strong>To:</strong> ${parcel.receiver_name}
                            </p>
                            ${parcel.receiver_address ? `
                            <p style="margin: 0.25rem 0 0 1.75rem; color: #6b7280; font-size: 0.875rem;">
                                <i class="fas fa-map-marker-alt"></i> ${parcel.receiver_address}
                            </p>
                            ` : ''}
                        </div>
                    </div>
                    
                    <div style="margin: 0.75rem 0; padding: 0.75rem; background: white; border-radius: 6px; border: 1px solid #e5e7eb;">
                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                            <i class="fas fa-map-marked-alt" style="color: #3b82f6;"></i>
                            <span style="font-weight: 600; color: #374151; font-size: 0.875rem;">Destination</span>
                        </div>
                        <p style="margin: 0; color: #111827; font-weight: 600;">
                            ${parcel.destination_outlet ? parcel.destination_outlet.outlet_name : 'Not Set'}
                        </p>
                        ${parcel.destination_outlet && parcel.destination_outlet.location ? `
                        <p style="margin: 0.25rem 0 0 0; color: #6b7280; font-size: 0.875rem;">
                            ${parcel.destination_outlet.location}
                        </p>
                        ` : ''}
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 0.5rem; font-size: 0.875rem;">
                        <div style="background: white; padding: 0.5rem; border-radius: 4px; border: 1px solid #e5e7eb;">
                            <span style="color: #6b7280; font-size: 0.75rem;">Weight</span>
                            <p style="margin: 0.25rem 0 0 0; color: #111827; font-weight: 600;">${parcel.parcel_weight || 0} kg</p>
                        </div>
                        <div style="background: white; padding: 0.5rem; border-radius: 4px; border: 1px solid #e5e7eb;">
                            <span style="color: #6b7280; font-size: 0.75rem;">Fee</span>
                            <p style="margin: 0.25rem 0 0 0; color: #111827; font-weight: 600;">ZMW ${parcel.delivery_fee || 0}</p>
                        </div>
                        ${parcel.cod_amount > 0 ? `
                        <div style="background: white; padding: 0.5rem; border-radius: 4px; border: 1px solid #e5e7eb;">
                            <span style="color: #6b7280; font-size: 0.75rem;">Cash to Collect</span>
                            <p style="margin: 0.25rem 0 0 0; color: #111827; font-weight: 600;">ZMW ${parcel.cod_amount}</p>
                        </div>
                        ` : ''}
                    </div>
                </div>
            `).join('');
            
            document.getElementById('availableParcelsLoading').style.display = 'none';
            container.style.display = 'grid';
        }

        function showNoParcelsMessage(message) {
            const container = document.getElementById('availableParcelsContainer');
            container.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p style="font-weight: 600; margin-bottom: 0.5rem;">No Available Parcels</p>
                    <p style="font-size: 0.875rem;">${message}</p>
                    <p style="font-size: 0.875rem; margin-top: 1rem; color: #3b82f6;">
                        Showing parcels from any outlet on this trip's route where both origin and destination are on the route, with status "pending" or "scheduled", and not already assigned to other trips.
                    </p>
                </div>
            `;
            document.getElementById('availableParcelsLoading').style.display = 'none';
            container.style.display = 'block';
        }

        function showErrorMessage(message) {
            const container = document.getElementById('availableParcelsContainer');
            container.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i>
                    <p style="font-weight: 600; color: #ef4444; margin-bottom: 0.5rem;">Error Loading Parcels</p>
                    <p style="font-size: 0.875rem;">${message}</p>
                </div>
            `;
            document.getElementById('availableParcelsLoading').style.display = 'none';
            container.style.display = 'block';
        }

        function toggleParcelSelection(parcelId) {
            const checkbox = document.getElementById(`checkbox-${parcelId}`);
            const parcelItem = document.getElementById(`parcel-${parcelId}`);
            
            if (selectedParcels.has(parcelId)) {
                selectedParcels.delete(parcelId);
                checkbox.checked = false;
                parcelItem.classList.remove('selected');
            } else {
                selectedParcels.add(parcelId);
                checkbox.checked = true;
                parcelItem.classList.add('selected');
            }
            
            updateSelectedCount();
        }

        function updateSelectedCount() {
            const count = selectedParcels.size;
            document.getElementById('selectedCount').textContent = `${count} parcel${count !== 1 ? 's' : ''} selected`;
            document.getElementById('confirmAddParcelsBtn').disabled = count === 0;
        }

        async function confirmAddParcels() {
            if (selectedParcels.size === 0 || !currentTripId) {
                return;
            }

            const btn = document.getElementById('confirmAddParcelsBtn');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding Parcels...';

            try {
                const response = await fetch('../api/trips/add_parcel_to_trip.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        trip_id: currentTripId,
                        parcel_ids: Array.from(selectedParcels)
                    })
                });

                const data = await response.json();

                if (data.success) {
                    
                    alert(`✅ Successfully added ${data.added_count} parcel(s) to the trip!`);
                    
                    
                    closeAddParcelsModal();
                    
                    
                    await fetchOutletTrips();
                } else {
                    alert(`❌ Error: ${data.error}\n\n${data.failed_parcels ? 'Failed parcels: ' + data.failed_parcels.map(p => p.track_number).join(', ') : ''}`);
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
            } catch (error) {
                console.error('Error adding parcels to trip:', error);
                alert('❌ Failed to add parcels: ' + error.message);
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }

        
        document.getElementById('addParcelsModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAddParcelsModal();
            }
        });

        function viewParcelDetails(parcelId) {
            // For now, show a simple alert. In a full implementation, you'd create a modal similar to parcelpool.php
            alert('Parcel details functionality would be implemented here. Parcel ID: ' + parcelId);
        }

        /**
         * Remove a parcel from a trip
         */
        async function removeParcelFromTrip(parcelListId, trackNumber, tripId) {
            if (!confirm(`Are you sure you want to remove parcel "${trackNumber}" from this trip?\n\nThe parcel status will be reset to 'pending' and it can be added to another trip.`)) {
                return;
            }

            try {
                const response = await fetch('../api/trips/remove_parcel_from_trip.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        parcel_list_id: parcelListId
                    })
                });

                const data = await response.json();

                if (data.success) {
                    alert(`✅ Successfully removed parcel "${trackNumber}" from the trip!`);
                    
                    // Refresh the trips list
                    await fetchOutletTrips();
                } else {
                    alert(`❌ Error: ${data.error}`);
                }
            } catch (error) {
                console.error('Error removing parcel from trip:', error);
                alert('❌ Failed to remove parcel: ' + error.message);
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            setupFilterListeners();
            fetchOutletTrips();
        });
    </script>

</body>
</html>
