<?php
$page_title = 'Company - Reports';
include __DIR__ . '/../includes/header.php';
?>

<body class="bg-gray-100 min-h-screen">

    <div class="mobile-dashboard">
                
        <!-- Main Content Area for Reports -->
        <main class="main-content">
            <div class="content-header">
                <h1>Reports</h1>
                <div class="report-actions">
                    <button class="btn" id="generateReportBtn">
                        <i class="fas fa-file-export"></i> Generate Report
                    </button>
                </div>
            </div>

            <!-- Report Filters -->
    
                <div class="content-container">
            <div class="report-filters">
                <div class="filter-group">
                    <label for="reportType">Report Type</label>
                    <select id="reportType">
                        <option value="delivery">Delivery Performance</option>
                        <option value="financial">Financial Summary</option>
                        <option value="driver">Driver Performance</option>
                        <option value="outlet">Outlet Activity</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="timePeriod">Time Period</label>
                    <select id="timePeriod">
                        <option value="today">Today</option>
                        <option value="week" selected>This Week</option>
                        <option value="month">This Month</option>
                        <option value="quarter">This Quarter</option>
                        <option value="year">This Year</option>
                        <option value="custom">Custom Range</option>
                    </select>
                </div>
                
                <div class="filter-group" id="customDateRange" style="display:none;">
                    <label>Custom Range</label>
                    <div class="date-range">
                        <input type="date" id="startDate">
                        <span>to</span>
                        <input type="date" id="endDate">
                    </div>
                </div>
                
                <div class="filter-group">
                    <label for="outletFilter">Filter by Outlet</label>
                    <select id="outletFilter">
                        <option value="all" selected>All Outlets</option>
                    </select>
                </div>
            </div>

            <!-- Report Summary Cards -->
            <div class="report-summary">
                <div class="summary-card">
                    <div class="summary-icon">
                        <i class="fas fa-box-open"></i>
                    </div>
                    <div class="summary-content">
                        <h3>Total Deliveries</h3>
                        <p id="totalDeliveriesValue" class="summary-value">—</p>
                        <p class="summary-change positive"></p>
                    </div>
                </div>

                <div class="summary-card">
                    <div class="summary-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="summary-content">
                        <h3>Total Revenue</h3>
                        <p id="totalRevenueValue" class="summary-value">—</p>
                    </div>
                </div>

                <div class="summary-card">
                    <div class="summary-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="summary-content">
                        <h3>Avg. Delivery Time</h3>
                        <p id="avgDeliveryTimeValue" class="summary-value">—</p>
                        <p class="summary-change negative"></p>
                    </div>
                </div>

                <div class="summary-card">
                    <div class="summary-icon">
                        <i class="fas fa-store"></i>
                    </div>
                    <div class="summary-content">
                        <h3>Active Outlets</h3>
                        <p id="activeOutletsValue" class="summary-value">—</p>
                    </div>
                </div>

                <div class="summary-card">
                    <div class="summary-icon">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="summary-content">
                        <h3>Active Drivers</h3>
                        <p id="activeDriversValue" class="summary-value">—</p>
                    </div>
                </div>
            </div>

            <!-- Delivery Performance Report -->
            <div class="report-section">
                <div class="section-header">
                    <h2>Delivery Performance</h2>
                    <div class="section-actions">
                        <button class="action-btn secondary small">
                            <i class="fas fa-download"></i> Export
                        </button>
                    </div>
                </div>
                
                <div class="report-grid">
                    <div class="report-chart">
                        <h3>Deliveries by Day</h3>
                        <div style="height: 250px;">
                            <canvas id="deliveriesByDayChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="report-chart">
                        <h3>Delivery Status</h3>
                        <div style="height: 250px;">
                            <canvas id="deliveryStatusChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="report-table">
                        <h3>Top Performing Outlets</h3>
                        <table id="topOutletsTable">
                            <thead>
                                <tr>
                                    <th>Outlet</th>
                                    <th>Deliveries</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody id="topOutletsTableBody">
                                <tr><td colspan="3">Loading…</td></tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Live Top Outlets / Drivers widgets (populated from API if available) -->
                    <div style="width:100%;display:flex;gap:12px;margin-top:12px;">
                        <div style="flex:1;min-width:220px;">
                            <h4>Top Outlets</h4>
                            <div id="topOutletsList" style="border:1px solid #eee;padding:8px;border-radius:6px;min-height:60px;">Loading…</div>
                        </div>
                        <div style="flex:1;min-width:220px;">
                            <h4>Top Drivers</h4>
                            <div id="topDriversList" style="border:1px solid #eee;padding:8px;border-radius:6px;min-height:60px;">Loading…</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Financial Summary Report -->
            <div class="report-section">
                <div class="section-header">
                    <h2>Financial Summary</h2>
                </div>
                    
                    <div class="report-table" id="revenueBreakdownContainer">
                        <h3>Revenue Breakdown</h3>
                        <table id="revenueBreakdownTable">
                            <thead>
                                <tr>
                                    <th>Service Type</th>
                                    <th>Deliveries</th>
                                    <th>Revenue</th>
                                    <th>Avg. Price</th>
                                    <th>% of Total</th>
                                </tr>
                            </thead>
                            <tbody id="revenueBreakdownBody">
                                <tr><td colspan="5">Loading…</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Driver Performance Report -->
            <div class="report-section">
                <div class="section-header">
                    <h2>Driver Performance</h2>
                </div>
                    
                    <div class="report-table">
                        <h3>Top Performing Drivers</h3>
                        <table id="topDriversTable">
                            <thead>
                                <tr>
                                    <th>Driver</th>
                                    <th>Deliveries</th>
                                    <th>Avg. Time</th>
                                    <th>Earnings</th>
                                </tr>
                            </thead>
                            <tbody id="topDriversTableBody">
                                <tr><td colspan="4">Loading…</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Report Footer -->
            <div class="report-footer">
                <div class="report-meta">
                    <p><strong>Generated:</strong> <span id="reportDate">June 10, 2023 3:45 PM</span></p>
                    <p><strong>Prepared by:</strong> Company Manager</p>
                </div>
                <div class="report-actions">
                    <button class="action-btn secondary">
                        <i class="fas fa-envelope"></i> Email Report
                    </button>
                    <button class="action-btn">
                        <i class="fas fa-file-pdf"></i> Save as PDF
                    </button>
                </div>
            </div>
            </div>
        </main>
    </div>

    <!-- Link to the external JavaScript file -->
    <script src="../assets/js/company-scripts.js"></script>
    
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        try {
        // Toggle custom date range
        document.getElementById('timePeriod').addEventListener('change', function() {
            const customRange = document.getElementById('customDateRange');
            customRange.style.display = this.value === 'custom' ? 'block' : 'none';
        });
        
        // Initialize charts with guard to avoid calling getContext on missing canvases
        const chartOptions = {
            responsive: true,
            maintainAspectRatio: false
        };

        function initChartIfPresent(id, createConfig) {
            const el = document.getElementById(id);
            if (!el) { console.warn('Chart element not found:', id); return; }
            if (typeof el.getContext !== 'function') { console.warn('Element does not support getContext:', id); return; }
            try {
                const ctx = el.getContext('2d');
                const cfg = createConfig(chartOptions);
                new Chart(ctx, cfg);
            } catch (err) {
                console.error('Failed to initialize chart', id, err);
            }
        }

        // Deliveries by Day Chart
        // create an initially-empty chart instance and keep a reference so we can update it when API data arrives
        let deliveriesByDayChart = null;
        initChartIfPresent('deliveriesByDayChart', (opts) => ({
            type: 'bar',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Deliveries',
                    data: [0, 0, 0, 0, 0, 0, 0],
                    backgroundColor: '#6A2A62',
                    borderColor: '#4F46E5',
                    borderWidth: 1
                }]
            },
            options: opts
        }));

        // Capture the created Chart.js instance if available (Chart.getChart is v3+); otherwise try a safe fallback
        (function captureDeliveriesChart() {
            try {
                const el = document.getElementById('deliveriesByDayChart');
                if (!el) return;
                if (typeof Chart.getChart === 'function') {
                    deliveriesByDayChart = Chart.getChart(el) || null;
                } else if (Chart.instances) {
                    // older Chart.js fallback: find any chart using this canvas
                    for (const id in Chart.instances) {
                        if (Object.prototype.hasOwnProperty.call(Chart.instances, id)) {
                            const inst = Chart.instances[id];
                            if (inst && inst.canvas && inst.canvas.id === 'deliveriesByDayChart') {
                                deliveriesByDayChart = inst;
                                break;
                            }
                        }
                    }
                }
            } catch (e) {
                console.warn('Could not capture deliveriesByDayChart instance:', e);
            }
        })();

        // Delivery Status Chart
        initChartIfPresent('deliveryStatusChart', (opts) => ({
            type: 'doughnut',
            data: {
                labels: ['Completed', 'In Progress', 'Delayed', 'Cancelled'],
                datasets: [{
                    data: [1190, 45, 10, 3],
                    backgroundColor: ['#6A2A62', '#3B82F6', '#F59E0B', '#EF4444'],
                    borderWidth: 1
                }]
            },
            options: opts
        }));

       
        // Set current date/time
        const now = new Date();
        document.getElementById('reportDate').textContent = now.toLocaleString('en-US', {
            month: 'long',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: 'numeric',
            hour12: true
        });

    // Fetch company reports stats (new API) and populate summary + top lists
    async function fetchCompanyReports(params = {}) {
            try {
        // build query string from params
        const qs = new URLSearchParams(params).toString();
        const url = '../api/fetch_company_reports_stats.php' + (qs ? ('?' + qs) : '');
        const res = await fetch(url, { credentials: 'same-origin' });
                if (res.status === 401) {
                    console.warn('Reports API returned 401 — redirecting to login');
                    window.location.href = '../auth/login.php';
                    return;
                }
                if (!res.ok) {
                    const bodyText = await res.text().catch(() => '');
                    console.error('Reports API returned non-ok status', res.status, bodyText);
                    throw new Error('Failed to fetch company reports: ' + res.status);
                }
                const json = await res.json();
                if (!json || !json.success || !json.data) throw new Error('Invalid response from reports API');
                const d = json.data;

                const setText = (id, text) => { const el = document.getElementById(id); if (el) el.textContent = text; };
                // Currency aware formatting helper
                function formatCurrencyValue(value, currency) {
                    if (value == null || value === '') return '—';
                    const num = Number(value);
                    if (Number.isNaN(num)) return String(value);
                    // If currency looks like an ISO code (USD, EUR, GBP), use Intl API
                    if (typeof currency === 'string' && /^[A-Z]{3}$/.test(currency)) {
                        try {
                            return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(num);
                        } catch (e) {
                            // fallthrough to symbol prefix
                        }
                    }
                    // If currency is a symbol like '$' or '₦', prefix it
                    const symbol = currency || '';
                    return symbol + num.toLocaleString();
                }

                setText('totalDeliveriesValue', Number(d.total_deliveries || 0).toLocaleString());
                setText('totalRevenueValue', d.total_revenue != null ? formatCurrencyValue(d.total_revenue, d.currency) : '—');
                setText('avgDeliveryTimeValue', d.avg_delivery_time != null ? (d.avg_delivery_time + ' min') : '—');
                setText('activeOutletsValue', Number(d.active_outlets || 0).toLocaleString());
                setText('activeDriversValue', Number(d.active_drivers || 0).toLocaleString());

                // Populate top outlets (list widget)
                const topOutletsEl = document.getElementById('topOutletsList');
                if (topOutletsEl && Array.isArray(d.top_outlets)) {
                    if (d.top_outlets.length === 0) topOutletsEl.textContent = 'No data';
                    else topOutletsEl.innerHTML = d.top_outlets.map(o => `<div style="padding:6px;border-bottom:1px solid #f2f2f2"><strong>${o.name||o.outlet_name||o.outlet_id||'Outlet'}</strong><div style="font-size:0.9em;color:#666">Deliveries: ${o.deliveries||o.count||0}</div></div>`).join('');
                }

                // Populate top outlets table (detailed)
                const topOutletsTableBody = document.getElementById('topOutletsTableBody');
                if (topOutletsTableBody) {
                    if (!Array.isArray(d.top_outlets) || d.top_outlets.length === 0) {
                        topOutletsTableBody.innerHTML = '<tr><td colspan="3">No data</td></tr>';
                    } else {
                        topOutletsTableBody.innerHTML = d.top_outlets.map(o => {
                            const name = o.name || o.outlet_name || o.outlet_id || o.origin_outlet_id || 'Outlet';
                            const deliveries = o.deliveries || o.count || 0;
                            const revenue = o.revenue != null ? formatCurrencyValue(o.revenue, d.currency || o.currency) : '—';
                            return `<tr><td>${name}</td><td>${deliveries}</td><td>${revenue}</td></tr>`;
                        }).join('');
                    }
                }

                // Populate top drivers (list widget)
                const topDriversEl = document.getElementById('topDriversList');
                if (topDriversEl && Array.isArray(d.top_drivers)) {
                    if (d.top_drivers.length === 0) topDriversEl.textContent = 'No data';
                    else topDriversEl.innerHTML = d.top_drivers.map(dr => `<div style="padding:6px;border-bottom:1px solid #f2f2f2"><strong>${dr.name||dr.driver_name||dr.driver_id||'Driver'}</strong><div style="font-size:0.9em;color:#666">Deliveries: ${dr.deliveries||dr.count||0}</div></div>`).join('');
                }

                // Populate top drivers table
                const topDriversTableBody = document.getElementById('topDriversTableBody');
                if (topDriversTableBody) {
                    if (!Array.isArray(d.top_drivers) || d.top_drivers.length === 0) {
                        topDriversTableBody.innerHTML = '<tr><td colspan="4">No data</td></tr>';
                    } else {
                        topDriversTableBody.innerHTML = d.top_drivers.map(dr => {
                            const name = dr.name || dr.driver_name || dr.driver_id || 'Driver';
                            const deliveries = dr.deliveries || dr.count || 0;
                            const avgTime = dr.avg_time != null ? (dr.avg_time + ' min') : (dr.avg_delivery_time ? dr.avg_delivery_time + ' min' : '—');
                            const earnings = dr.earnings != null ? ('$' + Number(dr.earnings).toLocaleString()) : '—';
                            return `<tr><td>${name}</td><td>${deliveries}</td><td>${avgTime}</td><td>${earnings}</td></tr>`;
                        }).join('');
                    }
                }

                // Populate revenue breakdown table if provided, otherwise attempt a best-effort from parcels/deliveries
                const revenueTableBody = document.getElementById('revenueBreakdownBody');
                if (revenueTableBody) {
                    if (Array.isArray(d.revenue_breakdown) && d.revenue_breakdown.length > 0) {
                            revenueTableBody.innerHTML = d.revenue_breakdown.map(r => {
                                const svc = r.service_type || r.type || 'Service';
                                const deliveries = r.deliveries || r.count || 0;
                                const revenue = (r.revenue != null) ? formatCurrencyValue(r.revenue, d.currency) : '—';
                                const avg = (r.avg_price != null) ? formatCurrencyValue(r.avg_price, d.currency) : '—';
                                const pct = (r.percent != null) ? (Number(r.percent).toFixed(1) + '%') : '—';
                                return `<tr><td>${svc}</td><td>${deliveries}</td><td>${revenue}</td><td>${avg}</td><td>${pct}</td></tr>`;
                            }).join('');
                        } else {
                        // Fallback: try to build from d.top_outlets or parcels/deliveries if available
                        if (Array.isArray(d.top_outlets) && d.top_outlets.length > 0) {
                            revenueTableBody.innerHTML = d.top_outlets.slice(0,5).map(o => `<tr><td>${o.name||o.outlet_name||o.origin_outlet_id||'Outlet'}</td><td>${o.deliveries||o.count||0}</td><td>${o.revenue!=null?formatCurrencyValue(o.revenue, d.currency || o.currency):'—'}</td><td>—</td><td>—</td></tr>`).join('');
                        } else {
                            revenueTableBody.innerHTML = '<tr><td colspan="5">No data</td></tr>';
                        }
                    }
                }

                console.info('Company reports stats loaded', d);
                // Update Deliveries by Day chart if data present
                try {
                    // Accept several possible shapes provided by different deployments
                    // Preferred: d.deliveries_by_day = [{ day: 'Mon', count: 120 }, ...]
                    // Alternate: d.deliveries_by_day = { Mon:120, Tue:130, ... }
                    // Alternate: d.daily = [{ label: 'Mon', value: 120 }, ...]
                    let series = null;
                    if (Array.isArray(d.deliveries_by_day)) {
                        series = d.deliveries_by_day;
                    } else if (d.deliveries_by_day && typeof d.deliveries_by_day === 'object') {
                        // object map
                        series = Object.keys(d.deliveries_by_day).map(k => ({ day: k, count: d.deliveries_by_day[k] }));
                    } else if (Array.isArray(d.daily)) {
                        series = d.daily.map(i => ({ day: i.label || i.day, count: i.value || i.count || 0 }));
                    }

                    if (series && series.length > 0 && typeof deliveriesByDayChart === 'object' && deliveriesByDayChart !== null) {
                        // Normalize order to Mon..Sun if possible, otherwise use provided order
                        const weekdayOrder = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
                        // derive labels and data
                        const labels = series.map(s => (s.day || '').toString());
                        const data = series.map(s => Number(s.count || s.value || 0));

                        // If labels look like full weekday names, normalize to short names
                        const normalizedLabels = labels.map(l => {
                            if (!l) return l;
                            const short = l.substring(0,3);
                            return short.charAt(0).toUpperCase() + short.slice(1);
                        });

                        deliveriesByDayChart.data.labels = normalizedLabels;
                        if (!deliveriesByDayChart.data.datasets || deliveriesByDayChart.data.datasets.length === 0) deliveriesByDayChart.data.datasets = [{ label: 'Deliveries', data: [] }];
                        deliveriesByDayChart.data.datasets[0].data = data;
                        deliveriesByDayChart.update();
                    } else if (series && series.length > 0) {
                        console.warn('deliveries_by_day data present but chart instance missing');
                    }
                } catch (chartErr) {
                    console.warn('Error updating deliveriesByDayChart:', chartErr);
                }
            } catch (err) {
                console.warn('Could not load company reports stats:', err);
                const topOutletsEl = document.getElementById('topOutletsList'); if (topOutletsEl) topOutletsEl.textContent = 'Unavailable';
                const topDriversEl = document.getElementById('topDriversList'); if (topDriversEl) topDriversEl.textContent = 'Unavailable';
            }
        }

        // Hook filters and Generate Report button
        const applyFiltersBtn = document.getElementById('applyFiltersBtn');
        const generateReportBtn = document.getElementById('generateReportBtn');
        function readFilters() {
            const reportType = document.getElementById('reportType')?.value || 'delivery';
            const timePeriod = document.getElementById('timePeriod')?.value || 'week';
            const outletFilter = document.getElementById('outletFilter')?.value || 'all';
            const startDate = document.getElementById('startDate')?.value || '';
            const endDate = document.getElementById('endDate')?.value || '';
            const params = { timePeriod };
            params.reportType = reportType;
            if (startDate) params.startDate = startDate;
            if (endDate) params.endDate = endDate;
            if (outletFilter && outletFilter !== 'all') params.outletFilter = outletFilter;
            return params;
        }

        if (applyFiltersBtn) applyFiltersBtn.addEventListener('click', function (e) { e.preventDefault(); fetchCompanyReports(readFilters()); });
        if (generateReportBtn) generateReportBtn.addEventListener('click', async function (e) {
            e.preventDefault();
            const btn = this;
            btn.disabled = true;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
            try {
                const filters = readFilters();
                // Submit as form POST to receive a PDF or HTML fallback
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '../api/generate_company_report.php';
                form.target = '_blank';
                Object.keys(filters).forEach(k => {
                    const inp = document.createElement('input');
                    inp.type = 'hidden'; inp.name = k; inp.value = filters[k]; form.appendChild(inp);
                });
                document.body.appendChild(form);
                form.submit();
                form.remove();
            } catch (err) {
                console.error('Failed to generate report', err);
                alert('Failed to generate report: ' + (err.message || err));
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        });

        // Populate outlet filter from server then fetch reports
        async function populateOutletFilter() {
            const sel = document.getElementById('outletFilter');
            if (!sel) return;
            try {
                const res = await fetch('../api/fetch_outlets.php', { credentials: 'same-origin' });
                if (res.status === 401) {
                    console.warn('Outlets API returned 401 — redirecting to login');
                    window.location.href = '../auth/login.php';
                    return;
                }
                if (!res.ok) {
                    console.warn('Failed to fetch outlets', res.status);
                    return;
                }
                const json = await res.json();
                if (!json || !json.success || !Array.isArray(json.data)) return;
                const outlets = json.data;
                // Append outlets as options
                outlets.forEach(o => {
                    try {
                        const opt = document.createElement('option');
                        opt.value = o.id ?? o.outlet_id ?? o.outletId ?? '';
                        opt.textContent = o.outlet_name ?? o.name ?? o.outletName ?? opt.value;
                        sel.appendChild(opt);
                    } catch (e) {
                        console.warn('Failed to append outlet option', e, o);
                    }
                });
            } catch (err) {
                console.warn('Could not load outlets for filter:', err);
            }
        }

        // initial population and fetch on page load
        populateOutletFilter().then(() => fetchCompanyReports()).catch(() => fetchCompanyReports());
        } catch (err) {
            console.error('Reports page initialization error:', err);
        }
    });
    </script>
</body>
</html>