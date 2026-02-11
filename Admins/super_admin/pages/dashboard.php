<?php
session_start();
require_once '../api/supabase-client.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: login.php');
    exit;
}

// Initialize default values for the view
$stats = [
    'total_companies' => 0,
    'active_users' => 0,
    'total_deliveries' => 0,
    'ongoing_deliveries' => 0
];
$totalRevenue = 0;
$platformCommissions = 0;  // Initialize here
$monthlyGrowth = 0;
$topCompanies = [];
$companiesData = [];

// Try to get initial data
try {
    // Fetch companies data
    $companiesData = callSupabase('companies?select=id,company_name,revenue,commission,status');
    if (is_array($companiesData)) {
        $stats['total_companies'] = count($companiesData);
        
        // Calculate total revenue and commissions from active companies
        foreach ($companiesData as $company) {
            if ($company['status'] === 'active') {
                $totalRevenue += floatval($company['revenue'] ?? 0);
                $platformCommissions += floatval($company['commission'] ?? 0);
            }
        }
    }
    
    // Get active users count
    $activeUsers = callSupabase('all_users?select=id&status=eq.active');
    if (is_array($activeUsers)) {
        $stats['active_users'] = count($activeUsers);
    }
    
    // Get delivery counts
    $deliveredParcels = callSupabase('delivered_parcels_mv?select=id');
    if (is_array($deliveredParcels)) {
        $stats['total_deliveries'] = count($deliveredParcels);
    }
    
    $inProgressParcels = callSupabase('parcels?select=id&status=neq.delivered');
    if (is_array($inProgressParcels)) {
        $stats['ongoing_deliveries'] = count($inProgressParcels);
    }
} catch (Exception $e) {
    error_log('Error fetching initial data: ' . $e->getMessage());
}

// Calculate initial company earnings for display
$companyEarnings = [];
if (!empty($companiesData)) {
    foreach ($companiesData as $company) {
        if ($company['status'] === 'active') {
            $companyEarnings[] = [
                'name' => $company['company_name'],
                'earnings' => $company['revenue'] ?? 0,
                'growth' => 0 // We'll set growth to 0 for now
            ];
        }
    }

    // Sort companies by earnings and get top 5
    usort($companyEarnings, fn($a, $b) => $b['earnings'] - $a['earnings']);
    $topCompanies = array_slice($companyEarnings, 0, 5);
}
$pageTitle = 'Admin - Dashboard';
require_once '../includes/header.php';
?>

    <div class="mobile-dashboard">
        <!-- Main Content Area for Admin Dashboard (overlay is centralized in header.php) -->
        <main class="main-content">
            <div class="content-header">
                <h1>Dashboard</h1>
            </div>

            <!-- Stats Grid -->
            <div class="dashboard-grid">
                <div class="stat-card animate-fade-in">
                    <div class="stat-icon">
                        <i class="fas fa-building"></i>
                    </div>
                    <div class="stat-content">
                        <p class="label">Total Companies</p>
                        <p class="value" data-stat="total_companies"><?php echo $stats['total_companies']; ?></p>
                        <p class="trend">Active Companies</p>
                    </div>
                </div>
                <div class="stat-card animate-fade-in">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <p class="label">Active Users</p>
                        <p class="value" data-stat="active_users"><?php echo $stats['active_users']; ?></p>
                        <p class="trend">Currently Active</p>
                    </div>
                </div>
                <div class="stat-card animate-fade-in">
                    <div class="stat-icon">
                        <i class="fas fa-truck"></i>
                    </div>
                    <div class="stat-content">
                        <p class="label">Total Deliveries</p>
                        <p class="value" data-stat="total_deliveries"><?php echo $stats['total_deliveries']; ?></p>
                        <p class="trend">Completed Deliveries</p>
                    </div>
                </div>
                <div class="stat-card animate-fade-in">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-content">
                        <p class="label">Deliveries in Progress</p>
                        <p class="value" data-stat="ongoing_deliveries"><?php echo $stats['ongoing_deliveries']; ?></p>
                        <p class="trend">Active now</p>
                    </div>
                </div>
            </div>

            <!-- Total Platform Revenue -->
            <div class="stat-card revenue-card animate-fade-in">
                <div class="stat-icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-content">
                    <p class="label">Total Platform Revenue</p>
                    <p class="value" data-stat="total_revenue">$<?php echo number_format($totalRevenue, 2); ?></p>
                    <p class="trend">Total Earnings</p>
                </div>
            </div>

            <!-- Delivery Trends Chart -->
            <div class="chart-card animate-fade-in">
                <div class="chart-header">
                    <h2>Delivery Trends</h2>
                    <div class="chart-controls">
                        <button class="chart-period-btn active">30 Days</button>
                        <button class="chart-period-btn">90 Days</button>
                        <button class="chart-period-btn">1 Year</button>
                    </div>
                </div>
                <p class="trend-info">
                    <span class="trend up">↑ 15%</span>
                    <span class="period">Last 30 Days</span>
                    <span class="details">Total Growth</span>
                </p>
                <div class="chart-container" style="height: 400px; position: relative;">
                    <canvas id="deliveryTrendsChart"></canvas>
                </div>
            </div>

            <style>
                .chart-card {
                    background: white;
                    border-radius: 12px;
                    padding: 1.5rem;
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
                    margin-bottom: 2rem;
                }

                .chart-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 1.5rem;
                }

                .chart-header h2 {
                    font-size: 1.25rem;
                    font-weight: 600;
                    color: #2E0D2A;
                    margin: 0;
                }

                .chart-controls {
                    display: flex;
                    gap: 0.5rem;
                }

                .chart-period-btn {
                    padding: 0.5rem 1rem;
                    border: 1px solid #e0e0e0;
                    background: white;
                    border-radius: 6px;
                    font-size: 0.875rem;
                    color: #666;
                    cursor: pointer;
                    transition: all 0.2s ease;
                }

                .chart-period-btn:hover {
                    border-color: #2E0D2A;
                    color: #2E0D2A;
                }

                .chart-period-btn.active {
                    background: #2E0D2A;
                    color: white;
                    border-color: #2E0D2A;
                }

                .trend-info {
                    display: flex;
                    align-items: center;
                    gap: 1rem;
                    margin-bottom: 1.5rem;
                    font-size: 0.875rem;
                }

                .trend {
                    font-weight: 600;
                    padding: 0.25rem 0.5rem;
                    border-radius: 4px;
                }

                .trend.up {
                    color: #4CAF50;
                    background: rgba(76, 175, 80, 0.1);
                }

                .trend.down {
                    color: #F44336;
                    background: rgba(244, 67, 54, 0.1);
                }

                .period {
                    color: #666;
                }

                .details {
                    color: #999;
                }

                .chart-container {
                    background: white;
                    border-radius: 8px;
                    padding: 1rem;
                }

                @media (max-width: 768px) {
                    .chart-header {
                        flex-direction: column;
                        gap: 1rem;
                    }

                    .chart-controls {
                        width: 100%;
                        justify-content: space-between;
                    }

                    .chart-period-btn {
                        flex: 1;
                        text-align: center;
                    }

                    .trend-info {
                        flex-wrap: wrap;
                    }
                }
            </style>

            <!-- Earnings Overview -->
            <div class="earnings-section animate-fade-in">
                <div class="section-header">
                    <h2>Earnings Overview</h2>
                    <div class="period-selector">
                        <select class="period-dropdown">
                            <option>This Month</option>
                            <option>Last 3 Months</option>
                            <option>This Year</option>
                        </select>
                    </div>
                </div>
                <div class="earnings-summary-grid">
                    <div class="earnings-summary-card">
                        <div class="stat-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-content">
                            <p class="label">Platform Commissions</p>
                            <p class="value">$<?php echo number_format($platformCommissions, 2); ?></p>
                            <p class="trend">Platform Total</p>
                        </div>
                    </div>
                    <div class="earnings-summary-card">
                        <div class="stat-icon">
                            <i class="fas fa-calculator"></i>
                        </div>
                        <div class="stat-content">
                            <p class="label">Average Revenue Per Company</p>
                            <p class="value">$<?php 
                                $avgRevenue = $stats['total_companies'] > 0 ? $totalRevenue / $stats['total_companies'] : 0;
                                echo number_format($avgRevenue, 2); 
                            ?></p>
                            <p class="trend">Company Average</p>
                        </div>
                    </div>
                </div>

                <h2>Top Performing Companies</h2>
                <div class="company-earnings-list" id="companyEarningsList">
                    <?php if (!empty($topCompanies)): ?>
                        <?php foreach ($topCompanies as $index => $company): ?>
                            <div class="company-earnings-item">
                                <div class="company-info">
                                    <p class="company_name"><?php echo htmlspecialchars($company['name']); ?></p>
                                    <p class="revenue">$<?php echo number_format($company['earnings'], 2); ?></p>
                                </div>
                                <div class="progress-bar-container">
                                    <div class="progress-bar" style="--progress-width: <?php echo ($totalRevenue > 0) ? (($company['earnings'] / $totalRevenue) * 100) : 0; ?>%;">
                                        <span class="progress-label"><?php echo ($totalRevenue > 0) ? round(($company['earnings'] / $totalRevenue) * 100) : 0; ?>%</span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="loading-indicator">
                            <i class="fas fa-spinner fa-spin"></i> Loading company data...
                        </div>
                    <?php endif; ?>
                </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions-section animate-fade-in">
                <h2>Quick Actions</h2>
                <div class="quick-action-buttons">
                    <a href="companies.php" class="action-btn">
                        <i class="fas fa-building"></i>
                        <span>Manage Companies</span>
                    </a>
                    <a href="reports.php" class="action-btn secondary">
                        <i class="fas fa-chart-line"></i>
                        <span>View Reports</span>
                    </a>
                </div>
            </div>
        </main>
    </div>
    <!-- Chart.js Library -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
    document.addEventListener("DOMContentLoaded", function () {
        updateDashboardStats();
        setInterval(updateDashboardStats, 30000);

        // Initialize delivery trends chart
        const ctx = document.getElementById('deliveryTrendsChart').getContext('2d');
        let currentPeriod = 30; // Default to 30 days
        let deliveryTrendsChart;

        // Function to fetch chart data from API
        async function fetchChartData(days) {
            try {
                const response = await fetch(`../api/get_dashboard_stats.php?period=${days}`);
                const data = await response.json();
                if (data.success && data.chartData) {
                    // Format dates for display
                    const formattedLabels = data.chartData.labels.map(date => {
                        const [year, month, day] = date.split('-');
                        return new Date(year, month - 1, day).toLocaleDateString('en-US', { 
                            month: 'short', 
                            day: 'numeric'
                        });
                    });
                    return {
                        labels: formattedLabels,
                        data: data.chartData.data,
                        trend: data.chartData.trend
                    };
                } else {
                    throw new Error('Failed to fetch chart data');
                }
            } catch (error) {
                console.error('Error fetching chart data:', error);
                return null;
            }
        }

        // Initialize chart with better styling
        async function initChart(days) {
            try {
                const chartData = await fetchChartData(days);
                if (!chartData) {
                    return;
                }

                if (deliveryTrendsChart) {
                    deliveryTrendsChart.destroy();
                }

                // Create new chart
                deliveryTrendsChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: chartData.labels,
                        datasets: [{
                            label: 'Deliveries',
                            data: chartData.data,
                            fill: true,
                            borderColor: '#2E0D2A',
                            backgroundColor: 'rgba(46, 13, 42, 0.1)',
                            tension: 0.4,
                            pointRadius: 4,
                            pointBackgroundColor: '#2E0D2A',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointHoverRadius: 6,
                            pointHoverBackgroundColor: '#2E0D2A',
                            pointHoverBorderColor: '#fff',
                            pointHoverBorderWidth: 2,
                            borderWidth: 3
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                mode: 'index',
                                intersect: false,
                                backgroundColor: 'rgba(46, 13, 42, 0.9)',
                                titleColor: '#fff',
                                bodyColor: '#fff',
                                titleFont: {
                                    size: 14,
                                    weight: 'bold',
                                    family: "'Poppins', sans-serif"
                                },
                                bodyFont: {
                                    size: 13,
                                    family: "'Poppins', sans-serif"
                                },
                                padding: 12,
                                cornerRadius: 8,
                                callbacks: {
                                    label: function(context) {
                                        return `${context.raw} deliveries`;
                                    }
                                }
                            }
                        },
                        interaction: {
                            mode: 'nearest',
                            axis: 'x',
                            intersect: false
                        },
                        scales: {
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    autoSkip: true,
                                    maxTicksLimit: 7,
                                    font: {
                                        family: "'Poppins', sans-serif",
                                        size: 12
                                    }
                                }
                            },
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.05)',
                                    drawBorder: false
                                },
                                ticks: {
                                    font: {
                                        family: "'Poppins', sans-serif",
                                        size: 12
                                    },
                                    padding: 10,
                                    callback: function(value) {
                                        return value + (value === 1 ? ' delivery' : ' deliveries');
                                    }
                                }
                            }
                        }
                    }
                });

                // Update trend display
                if (chartData.trend) {
                    const trendSpan = document.querySelector('.trend-info .trend');
                    const periodSpan = document.querySelector('.trend-info .period');
                    if (trendSpan) {
                        trendSpan.className = `trend ${chartData.trend.direction}`;
                        trendSpan.textContent = `${chartData.trend.direction === 'up' ? '↑' : '↓'} ${Math.abs(chartData.trend.percentage)}%`;
                    }
                    if (periodSpan) {
                        periodSpan.textContent = `Last ${days} Days`;
                    }
                }
            } catch (error) {
                console.error('Error initializing chart:', error);
            }
        }

        // Initialize with 30 days
        initChart(currentPeriod);

        // Handle period button clicks
        document.querySelectorAll('.chart-period-btn').forEach(btn => {
            btn.addEventListener('click', async function() {
                document.querySelectorAll('.chart-period-btn').forEach(b => 
                    b.classList.remove('active'));
                
                this.classList.add('active');
                
                const period = this.textContent.includes('30') ? 30 :
                              this.textContent.includes('90') ? 90 : 365;
                
                currentPeriod = period;
                await initChart(period);
            });
        });
    });

    async function updateDashboardStats() {
        try {
            console.log('Fetching dashboard stats...');
            const response = await fetch('../api/get_dashboard_stats.php', {
                credentials: 'include'
            });
            console.log('Response status:', response.status);
            const data = await response.json();
            console.log('Received data:', data);
            
            if (!response.ok) {
                throw new Error(data.message || `HTTP error! status: ${response.status}`);
            }
            if (data.success) {
                console.log('Updating stats with:', data);
                // Update statistics
                const stats = data.stats;
                const currency = data.currency || { code: 'USD', symbol: '$', rate: 1 };
                if (stats) {
                    console.log('Updating dashboard with stats:', stats);
                    for (const [key, value] of Object.entries(stats)) {
                        const element = document.querySelector(`[data-stat="${key}"]`);
                        if (element) {
                            // Only update if we have a valid value
                            const numValue = Number(value);
                            if (!isNaN(numValue)) {
                                console.log(`Updating ${key} to:`, numValue);
                                element.textContent = numValue.toLocaleString();
                            } else {
                                console.warn(`Invalid value for ${key}:`, value);
                            }
                        } else {
                            console.warn(`Element not found for stat: ${key}`);
                        }
                    }
                } else {
                    console.warn('No stats data received');
                }
                // Update total revenue
                const revenueElement = document.querySelector('[data-stat="total_revenue"]');
                if (revenueElement) {
                    revenueElement.textContent = `${currency.symbol}${Number(data.revenue?.total || 0).toLocaleString('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    })} ${currency.code}`;
                }
                // Update platform commissions in Earnings Overview section
                const commissionsCards = document.querySelectorAll('.earnings-summary-card .value');
                if (commissionsCards.length > 0 && data.revenue?.commissions !== undefined) {
                    // First card is Platform Commissions
                    commissionsCards[0].textContent = `${currency.symbol}${Number(data.revenue.commissions).toLocaleString('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    })} ${currency.code}`;
                }
                // Update average revenue (second card in earnings summary)
                const avgRevenueCards = document.querySelectorAll('.earnings-summary-card .value');
                if (avgRevenueCards.length > 1 && stats.total_companies > 0) {
                    const avgRevenue = (data.revenue?.total || 0) / stats.total_companies;
                    // Second card is Average Revenue Per Company
                    avgRevenueCards[1].textContent = `${currency.symbol}${Number(avgRevenue).toLocaleString('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    })} ${currency.code}`;
                }
                // Update company list
                if (data.topCompanies) {
                    updateCompanyList(data.topCompanies, currency);
                }
                // Update all .currency-symbol elements if present
                document.querySelectorAll('.currency-symbol').forEach(el => {
                    el.textContent = currency.symbol;
                });
            } else {
                console.error('Failed to fetch dashboard stats:', data.error || 'Unknown error');
            }
        } catch (error) {
            console.error('Error updating dashboard stats:', error.message || error);
        }
    }

function updateCompanyList(companies, currency) {
    const companyList = document.getElementById('companyEarningsList');
    if (!companyList) {
        return;
    }
    companyList.innerHTML = '';
    if (!companies.length) {
        companyList.innerHTML = "<p>No company data available</p>";
        return;
    }
    const maxEarnings = companies[0].earnings;
    companies.forEach(company => {
        const percentage = maxEarnings > 0 ? (company.earnings / maxEarnings) * 100 : 0;
        const div = document.createElement('div');
        div.classList.add('company-earnings-item');
        div.innerHTML = `
            <div class="company-info">
                <p class="company_name">${company.name}</p>
                <p class="revenue">${currency.symbol}${Number(company.earnings).toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                })} ${currency.code}</p>
            </div>
            <div class="progress-bar-container">
                <div class="progress-bar" style="--progress-width: ${percentage}%;">
                    <span class="progress-label">${Math.round(percentage)}%</span>
                </div>
            </div>
        `;
        companyList.appendChild(div);
    });
}
    </script>
</body>
</html>
