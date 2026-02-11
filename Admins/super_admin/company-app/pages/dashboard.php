<?php
// Include the header (which also handles session and authentication)
require_once '../includes/header.php';
?>
    <body>class="bg-gray-100 min-h-screen">
    <div class="mobile-dashboard">
        <!-- Main Content Area for Company Dashboard -->
        <main class="main-content">
            <div class="content-header">
                <h1>Dashboard</h1>
            </div>

            <!-- Stats Grid -->
            <div class="dashboard-grid">
                <div class="stat-card">
                    <p class="label">Active Outlets</p>
                    <p class="value" id="statActiveOutlets">—</p>
                   
                </div>
                <div class="stat-card">
                    <p class="label">Active Drivers</p>
                    <p class="value" id="statActiveDrivers">—</p>
                    
                </div>
                <div class="stat-card">
                    <p class="label">Total Parcels</p>
                    <p class="value" id="statTotalDeliveries">—</p>
                   
                </div>
                <div class="stat-card">
                    <p class="label">Parcels In Progress</p>
                    <p class="value" id="statInProgress">—</p>
            
                </div>

            </div>

            <!-- Quick Actions -->
            <div class="quick-actions-section">
                <h2>Quick Actions</h2>
                <div class="quick-action-buttons">
                    <a class="action-btn" id="addOutletBtn" href="company-add-outlet.php">
                        <i class="fas fa-store"></i> Add New Outlet
                    </a>
                    <a class="action-btn" id="addDriverBtn" href="company-add-driver.php">
                        <i class="fas fa-user-plus"></i> Add New Driver
                    </a>
                    <a class="action-btn secondary" id="viewDeliveryReportsBtn" href="company-reports.php">
                        <i class="fas fa-chart-bar"></i> View Delivery Reports
                    </a>
                    <a class="action-btn secondary" id="manageCompanySettingsBtn" href="settings.php">
                        <i class="fas fa-cog"></i> Manage Settings
                    </a>
                </div>
            </div>

            <!-- Delivery Trends Chart -->
            <div class="chart-container">
                <h2>Delivery Trends</h2>
                <p class="text-gray-600 mb-4">Deliveries Over Time: 12,500 <span class="text-green-500">+15%</span></p>
                <div style="height: 300px;">
                    <canvas id="deliveryTrendChart" class="chart-canvas"></canvas>
                </div>
            </div>

            <!-- Earnings Tracking Chart -->
            <div class="chart-container">
                <h2>Earnings Tracking</h2>
                <div class="filter-bar" style="padding: 0; box-shadow: none; margin-bottom: 15px;">
                    <div class="filter-dropdown" style="width: auto;">
                            <select id="earningsFilterOutlet">
                                <option value="">Filter By Outlet</option>
                                <option value="">All Outlets</option>
                                <!-- real outlet options will be populated dynamically if available -->
                            </select>
                        </div>
                </div>
                <p class="text-gray-600 mb-4">Revenue Over Time: $50,000 <span class="text-green-500">+12%</span></p>
                <div style="height: 300px;">
                    <canvas id="revenueTrendChart" class="chart-canvas"></canvas>
                </div>
            </div>
        </main>
    </div>

    <!-- Small script to populate dashboard stats -->
    <script>
        async function loadDashboardStats() {
                try {
                    const res = await fetch('../api/fetch_dashboard_stats.php');
                    // If unauthorized, redirect to login for re-authentication
                    if (res.status === 401) {
                        console.warn('Dashboard API returned 401, redirecting to login');
                        window.location.href = '../../auth/login.php';
                        return;
                    }

                    const json = await res.json();
                    if (!json.success) {
                        // If the server reports session expiration, redirect to login
                        const msg = json.error || '';
                        console.warn('Dashboard API error:', msg);
                        if (msg.toLowerCase().includes('session expired') || msg.toLowerCase().includes('jwt expired')) {
                            window.location.href = '../../auth/login.php';
                            return;
                        }
                        throw new Error(msg || 'Failed to load stats');
                    }

                    const d = json.data || {};
                    document.getElementById('statActiveOutlets').textContent = d.active_outlets ?? '0';
                    document.getElementById('statActiveDrivers').textContent = d.active_drivers ?? '0';
                    document.getElementById('statTotalDeliveries').textContent = d.total_deliveries ?? '0';
                    document.getElementById('statInProgress').textContent = d.deliveries_in_progress ?? '0';
                } catch (err) {
                    console.error('Failed to load dashboard stats', err);
                    // If an HTTP error with message about JWT is present, redirect
                    const message = (err && err.message) ? err.message : '';
                    if (message.toLowerCase().includes('jwt') || message.toLowerCase().includes('session expired')) {
                        window.location.href = '../../auth/login.php';
                    }
                }
        }
        document.addEventListener('DOMContentLoaded', loadDashboardStats);
    </script>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Keep chart instances to safely destroy/recreate on reloads
        window.__deliveryChart = window.__deliveryChart || null;
        window.__revenueChart = window.__revenueChart || null;

        async function loadDashboardCharts() {
            try {
                // Read earnings filters
                const outlet = document.getElementById('earningsFilterOutlet') ? document.getElementById('earningsFilterOutlet').value : '';
                const service = document.getElementById('earningsFilterService') ? document.getElementById('earningsFilterService').value : '';

                const params = new URLSearchParams();
                if (outlet) params.append('outlet', outlet);
                if (service) params.append('service', service);

                const res = await fetch('../api/fetch_dashboard_chart.php' + (params.toString() ? ('?' + params.toString()) : ''));
                if (res.status === 401) return; // session handling is elsewhere
                const json = await res.json();
                if (!json.success) return;
                const d = json.data || {};
                // Destroy existing charts if present to avoid "canvas already in use" error
                try {
                    // Prefer Chart.getChart to find any chart instance bound to the canvas element
                    const existingDelivery = Chart.getChart('deliveryTrendChart');
                    if (existingDelivery) {
                        try { existingDelivery.destroy(); } catch (err) { console.warn('Error destroying existingDelivery', err); }
                        window.__deliveryChart = null;
                    } else if (window.__deliveryChart) {
                        try { window.__deliveryChart.destroy(); } catch (err) { console.warn('Error destroying window.__deliveryChart', err); }
                        window.__deliveryChart = null;
                    }
                } catch (e) {
                    console.warn('Failed to safely destroy existing deliveryChart', e);
                    window.__deliveryChart = null;
                }

                try {
                    const existingRevenue = Chart.getChart('revenueTrendChart');
                    if (existingRevenue) {
                        try { existingRevenue.destroy(); } catch (err) { console.warn('Error destroying existingRevenue', err); }
                        window.__revenueChart = null;
                    } else if (window.__revenueChart) {
                        try { window.__revenueChart.destroy(); } catch (err) { console.warn('Error destroying window.__revenueChart', err); }
                        window.__revenueChart = null;
                    }
                } catch (e) {
                    console.warn('Failed to safely destroy existing revenueChart', e);
                    window.__revenueChart = null;
                }

                // Delivery trends chart
                const deliveryCanvas = document.getElementById('deliveryTrendChart');
                if (deliveryCanvas && deliveryCanvas.getContext) {
                    const ctx1 = deliveryCanvas.getContext('2d');
                    window.__deliveryChart = new Chart(ctx1, {
                        type: 'line',
                        data: {
                            labels: d.labels,
                            datasets: [{
                                label: 'Deliveries',
                                data: d.deliveries,
                                borderColor: '#4F46E5',
                                backgroundColor: 'rgba(79,70,229,0.08)',
                                fill: true,
                                tension: 0.3
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: { legend: { display: false } },
                            scales: { x: { display: true }, y: { beginAtZero: true } }
                        }
                    });
                }

                // Revenue chart
                const revenueCanvas = document.getElementById('revenueTrendChart');
                if (revenueCanvas && revenueCanvas.getContext) {
                    const ctx2 = revenueCanvas.getContext('2d');
                    window.__revenueChart = new Chart(ctx2, {
                        type: 'bar',
                        data: {
                            labels: d.labels,
                            datasets: [{
                                label: 'Estimated Revenue',
                                data: d.revenue,
                                backgroundColor: 'rgba(34,197,94,0.8)'
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: { legend: { display: false } },
                            scales: { x: { display: true }, y: { beginAtZero: true } }
                        }
                    });
                }

            } catch (err) {
                console.error('Failed to load dashboard charts', err);
            }
        }
        document.addEventListener('DOMContentLoaded', function () {
            loadDashboardCharts();
            // Re-load charts when earnings filters change
            const outletSel = document.getElementById('earningsFilterOutlet');
            const serviceSel = document.getElementById('earningsFilterService');
            if (outletSel) {
                outletSel.addEventListener('change', function() {
                    console.log('Outlet filter changed to:', this.value);
                    loadDashboardCharts();
                });
            }
            if (serviceSel) {
                serviceSel.addEventListener('change', function() {
                    console.log('Service filter changed to:', this.value);
                    loadDashboardCharts();
                });
            }

            // Attempt to populate outlet options from /api/fetch_company_outlets.php
            try {
                fetch('../api/fetch_company_outlets.php')
                    .then(r => {
                        if (!r.ok) throw new Error('Response not ok: ' + r.status);
                        return r.json();
                    })
                    .then(j => {
                        if (!j) {
                            console.warn('No outlets returned from API');
                            return;
                        }
                        const rows = Array.isArray(j) ? j : (Array.isArray(j.data) ? j.data : (Array.isArray(j.outlets) ? j.outlets : null));
                        if (!rows || rows.length === 0) {
                            console.warn('No outlet rows found');
                            return;
                        }
                        const sel = document.getElementById('earningsFilterOutlet');
                        if (!sel) {
                            console.warn('Outlet select element not found');
                            return;
                        }
                        // preserve first two placeholder options
                        const firstOption = sel.options[0]; // Filter By Outlet
                        const secondOption = sel.options[1]; // All Outlets
                        sel.innerHTML = '';
                        if (firstOption) sel.appendChild(firstOption);
                        if (secondOption) sel.appendChild(secondOption);
                        
                        rows.forEach(o => {
                            const opt = document.createElement('option');
                            opt.value = o.id || o.outlet_id;
                            opt.textContent = o.outlet_name || o.name || o.display_name || (o.address ?? (o.id || o.outlet_id));
                            sel.appendChild(opt);
                        });
                        console.log('Populated ' + rows.length + ' outlets');
                    }).catch((err) => {
                        console.error('Failed to load outlets:', err);
                    });
            } catch (e) {
                console.error('Exception loading outlets:', e);
            }
        });
    </script>

    <!-- Link to the external JavaScript file -->
<script src="../assets/js/company-scripts.js"></script>
</body>
</html>
