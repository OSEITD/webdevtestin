<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once '../api/supabase-client.php';

// Check if user is logged in and has super_admin role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    error_log("Unauthorized access attempt to reports.php. Session role: " . ($_SESSION['role'] ?? 'none'));
    header('Location: ../auth/login.php');
    exit;
}

// Initialize data containers
$deliveryStats = [
    'total' => 0,
    'success_rate' => 0,
    'avg_time' => 0
];

$revenueStats = [
    'total_revenue' => 0,
    'monthly_growth' => 0
];

$userStats = [
    'total_users' => 0,
    'active_users' => 0
];

$outletStats = [
    'total_outlets' => 0,
    'performance_rate' => 0
];

try {
    // Data is now fetched by get_report_stats.php
} catch (Exception $e) {
    error_log("Error fetching report data: " . $e->getMessage());
}


$pageTitle = 'Admin - Reports';
require_once '../includes/header.php';
?>

    <div class="mobile-dashboard">
        <!-- Main Content Area for Reports -->
        <main class="main-content">
            <div class="content-header">
                <h1>Reports & Analytics</h1>
            </div>

            <div class="report-card">
                <h2><i class="fas fa-chart-bar"></i> Delivery Performance Report</h2>
                <div class="report-stats">
                    <div class="stat-item">
                        <span class="stat-label">Total Deliveries:</span>
                        <span class="stat-value" data-stat="delivery-total"><?php echo number_format($deliveryStats['total']); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Success Rate:</span>
                        <span class="stat-value" data-stat="delivery-success-rate"><?php echo number_format($deliveryStats['success_rate'], 1); ?>%</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Avg. Delivery Time:</span>
                        <span class="stat-value" data-stat="delivery-avg-time"><?php echo $deliveryStats['avg_time'] ?: 'N/A'; ?></span>
                    </div>
                </div>
                <div class="report-actions">
                    <form onsubmit="return generateReport(event, 'delivery')" class="report-form">
                        <input type="hidden" name="report_type" value="delivery">
                        <div class="date-range">
                            <input type="date" name="start_date" required>
                            <span>to</span>
                            <input type="date" name="end_date" required>
                        </div>
                        <input type="hidden" name="format" value="pdf">
                        <button type="submit" class="report-btn">
                            <i class="fas fa-file-alt"></i> Generate Report
                        </button>
                        <button type="button" class="report-btn secondary" onclick="viewReportHistory('delivery')">
                            <i class="fas fa-history"></i> View History
                        </button>
                    </form>
                </div>
            </div>

            <div class="report-card">
                <h2><i class="fas fa-dollar-sign"></i> Revenue & Earnings Report</h2>
                <div class="report-stats">
                    <div class="stat-item">
                        <span class="stat-label">Total Revenue:</span>
                        <span class="stat-value" data-stat="revenue-total">$<?php echo number_format($revenueStats['total_revenue'], 2); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Monthly Growth:</span>
                        <span class="stat-value" data-stat="revenue-growth"><?php echo number_format($revenueStats['monthly_growth'], 1); ?>%</span>
                    </div>
                </div>
                <div class="report-actions">
                    <form onsubmit="return generateReport(event, 'revenue')" class="report-form">
                        <input type="hidden" name="report_type" value="revenue">
                        <div class="date-range">
                            <input type="date" name="start_date" required>
                            <span>to</span>
                            <input type="date" name="end_date" required>
                        </div>
                        <input type="hidden" name="format" value="pdf">
                        <button type="submit" class="report-btn">
                            <i class="fas fa-file-invoice-dollar"></i> Generate Report
                        </button>
                        <button type="button" class="report-btn secondary" onclick="viewReportHistory('revenue')">
                            <i class="fas fa-history"></i> View History
                        </button>
                    </form>
                </div>
            </div>

            <div class="report-card">
                <h2><i class="fas fa-users"></i> User Activity Report</h2>
                <div class="report-stats">
                    <div class="stat-item">
                        <span class="stat-label">Total Users:</span>
                        <span class="stat-value" data-stat="users-total"><?php echo number_format($userStats['total_users']); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Active Users:</span>
                        <span class="stat-value" data-stat="users-active"><?php echo number_format($userStats['active_users']); ?></span>
                    </div>
                </div>
                <div class="report-actions">
                    <form onsubmit="return generateReport(event, 'user')" class="report-form">
                        <input type="hidden" name="report_type" value="user">
                        <div class="date-range">
                            <input type="date" name="start_date" required>
                            <span>to</span>
                            <input type="date" name="end_date" required>
                        </div>
                        <input type="hidden" name="format" value="pdf">
                        <button type="submit" class="report-btn">
                            <i class="fas fa-user-check"></i> Generate Report
                        </button>
                        <button type="button" class="report-btn secondary" onclick="viewReportHistory('users')">
                            <i class="fas fa-history"></i> View History
                        </button>
                    </form>
                </div>
            </div>

            <div class="report-card">
                <h2><i class="fas fa-map-marked-alt"></i> Outlet & Company Performance</h2>
                <div class="report-stats">
                    <div class="stat-item">
                        <span class="stat-label">Total Outlets:</span>
                        <span class="stat-value" data-stat="outlets-total"><?php echo number_format($outletStats['total_outlets']); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Avg. Performance:</span>
                        <span class="stat-value" data-stat="outlets-performance"><?php echo number_format($outletStats['performance_rate'], 1); ?>%</span>
                    </div>
                </div>
                <div class="report-actions">
                    <form onsubmit="return generateReport(event, 'outlet')" class="report-form">
                        <input type="hidden" name="report_type" value="outlet">
                        <div class="date-range">
                            <input type="date" name="start_date" required>
                            <span>to</span>
                            <input type="date" name="end_date" required>
                        </div>
                        <input type="hidden" name="format" value="pdf">
                        <button type="submit" class="report-btn">
                            <i class="fas fa-chart-pie"></i> Generate Report
                        </button>
                        <button type="button" class="report-btn secondary" onclick="viewReportHistory('outlets')">
                            <i class="fas fa-history"></i> View History
                        </button>
                    </form>
                </div>
            </div>

        </main>
    </div>

    <style>
        .content-header {
            padding: 20px;
            background: var(--primary-color);
            color: white;
            margin-bottom: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .content-header h1 {
            font-size: 24px;
            font-weight: 600;
            margin: 0;
            color: white;
        }

        .report-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .report-card h2 {
            color: var(--primary-color);
            font-size: 20px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .report-card h2 i {
            color: var(--secondary-color);
        }

        .report-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 15px 0;
            padding: 15px;
            background: var(--bg-color);
            border-radius: 8px;
        }

        .stat-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .stat-label {
            display: block;
            font-size: 14px;
            color: var(--text-light);
            margin-bottom: 5px;
        }

        .stat-value {
            font-size: 20px;
            font-weight: 600;
            color: var(--primary-color);
        }

        .report-actions {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid var(--border-color);
        }

        .report-form {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }

        .date-range {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .date-range input[type="date"] {
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            font-size: 14px;
            color: var(--text-color);
        }

        .date-range span {
            color: var(--text-light);
        }

        .report-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .report-btn:not(.secondary) {
            background: var(--primary-color);
            color: white;
        }

        .report-btn:not(.secondary):hover {
            background: var(--primary-light);
        }

        .report-btn.secondary {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-color);
        }

        .report-btn.secondary:hover {
            background: #2E0D2A;
        }

        .report-btn i {
            font-size: 16px;
        }

        .report-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .report-history-dialog {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .dialog-content {
            background: white;
            padding: 20px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
        }

        .dialog-content h3 {
            color: var(--primary-color);
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color);
        }

        .report-list {
            margin: 15px 0;
        }

        .report-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid var(--border-color);
        }

        .report-date {
            color: var(--text-color);
        }

        .download-link {
            color: var(--primary-color);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .download-link:hover {
            color: var(--primary-light);
        }

        .close-btn {
            width: 100%;
            padding: 10px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 5px;
            margin-top: 15px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .close-btn:hover {
            background: var(--primary-light);
        }

        @media (max-width: 768px) {
            .report-stats {
                grid-template-columns: 1fr;
            }

            .report-form {
                flex-direction: column;
                align-items: stretch;
            }

            .date-range {
                flex-direction: column;
                align-items: stretch;
            }

            .report-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            updateReportStats();
        });

        // report generation handled by generateReport(event, reportType)

        async function generateReport(event, reportType) {
            event.preventDefault();
            const form = event.target;
            const submitButton = form.querySelector('button[type="submit"]');
            const originalButtonText = submitButton.innerHTML;
            const formData = new FormData(form);

            try {
                // Show loading state
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';

                const response = await fetch('../api/generate_report.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'include'
                });

                if (response.ok) {
                    // Get the PDF blob
                    const blob = await response.blob();
                    
                    // Create a download link
                    const url = window.URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = `report_${reportType}_${new Date().getTime()}.pdf`;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    window.URL.revokeObjectURL(url);
                } else {
                    // Handle error response
                    const text = await response.text();
                    console.error('Server error:', text);
                    showMessageBox(`Failed to generate report: ${response.statusText}`);
                }
            } catch (error) {
                console.error('Error generating report:', error);
                showMessageBox(`Error generating report: ${error.message}`);
            } finally {
                // Restore button state
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonText;
            }

            return false; // Prevent form submission
        }

      async function updateReportStats() {
            try {
                console.log('Fetching report stats...');
                const response = await fetch('../api/get_report_stats.php', {
                    method: 'GET',
                    credentials: 'include',
                    headers: {
                        'Accept': 'application/json',
                        'Cache-Control': 'no-cache',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                let responseText = await response.text();
                console.log('Raw server response:', responseText);

                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (e) {
                    console.error('Failed to parse JSON response:', e);
                    throw new Error('Invalid JSON response from server');
                }

                if (!response.ok || !data.success) {
                    throw new Error(data.error || `HTTP error! status: ${response.status}`);
                }

                const stats = data.stats;
                if (!stats) {
                    throw new Error('No statistics data received');
                }

                // Update stats with error checking
                const updateStat = (selector, value, format = 'number') => {
                    const element = document.querySelector(`[data-stat="${selector}"]`);
                    if (!element) {
                        console.error(`Element with selector [data-stat="${selector}"] not found`);
                        return;
                    }
                    
                    try {
                        switch(format) {
                            case 'number':
                                element.textContent = Number(value || 0).toLocaleString();
                                break;
                            case 'currency':
                                element.textContent = `$${Number(value || 0).toLocaleString('en-US', { 
                                    minimumFractionDigits: 2, 
                                    maximumFractionDigits: 2 
                                })}`;
                                break;
                            case 'percentage':
                                element.textContent = `${Number(value || 0).toFixed(1)}%`;
                                break;
                            case 'time':
                                element.textContent = value ? `${value} hours` : 'N/A';
                                break;
                        }
                    } catch (e) {
                        console.error(`Error updating stat ${selector}:`, e);
                        element.textContent = 'Error';
                    }
                };

                // Delivery Stats
                updateStat('delivery-total', stats.delivery?.total);
                updateStat('delivery-success-rate', stats.delivery?.success_rate, 'percentage');
                updateStat('delivery-avg-time', stats.delivery?.avg_time, 'time');

                // Revenue Stats
                updateStat('revenue-total', stats.revenue?.total_revenue, 'currency');
                updateStat('revenue-growth', stats.revenue?.monthly_growth, 'percentage');

                // User Stats
                updateStat('users-total', stats.users?.total_users);
                updateStat('users-active', stats.users?.active_users);

                // Outlet Stats
                updateStat('outlets-total', stats.outlets?.total_outlets);
                updateStat('outlets-performance', stats.outlets?.performance_rate, 'percentage');

            } catch (error) {
                console.error('Error updating report stats:', error);
                showMessageBox(`Failed to load statistics: ${error.message}`);
            }
        }

        // Report generation functionality
        function viewReportHistory(reportType) {
            fetch(`../api/get_report_history.php?type=${reportType}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showReportHistoryDialog(data.reports, reportType);
                    } else {
                        showMessageBox(data.error || 'Failed to load report history');
                    }
                })
                .catch(error => showMessageBox('Error loading report history: ' + error.message));
        }

        function showReportHistoryDialog(reports, reportType) {
            const dialog = document.createElement('div');
            dialog.className = 'report-history-dialog';
            dialog.innerHTML = `
                <div class="dialog-content">
                    <h3>${reportType.charAt(0).toUpperCase() + reportType.slice(1)} Report History</h3>
                    <div class="report-list">
                        ${reports && reports.length ? reports.map(report => `
                            <div class="report-item">
                                <span class="report-date">${new Date(report.created_at).toLocaleDateString()}</span>
                                <a href="../api/download_report.php?id=${report.id}" class="download-link">
                                    <i class="fas fa-download"></i> Download
                                </a>
                            </div>
                        `).join('') : '<div>No reports found.</div>'}
                    </div>
                    <button onclick="this.closest('.report-history-dialog').remove()" class="close-btn">
                        Close
                    </button>
                </div>
            `;
            document.body.appendChild(dialog);
        }

        // Set default date range to last 30 days
        document.querySelectorAll('input[type="date"]').forEach(input => {
            const end = new Date();
            const start = new Date();
            start.setDate(start.getDate() - 30);
            
            if (input.name === 'end_date') {
                input.value = end.toISOString().split('T')[0];
            } else if (input.name === 'start_date') {
                input.value = start.toISOString().split('T')[0];
            }
        });

        // Menu functionality handled by admin-scripts.js
        // Function to display a custom message box instead of alert()
        function showMessageBox(message) {
            const overlay = document.createElement('div');
            overlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.5);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 1000;
            `;
            overlay.id = 'messageBoxOverlay';

            const messageBox = document.createElement('div');
            messageBox.style.cssText = `
                background-color: white;
                padding: 2rem;
                border-radius: 0.5rem;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                text-align: center;
                max-width: 90%;
                width: 400px;
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
                background-color: #3b82f6; /* Blue-600 */
                color: white;
                padding: 0.75rem 1.5rem;
                border-radius: 0.375rem;
                font-weight: bold;
                cursor: pointer;
                transition: background-color 0.2s;
            `;
            closeButton.onmouseover = () => closeButton.style.backgroundColor = '#2563eb';
            closeButton.onmouseout = () => closeButton.style.backgroundColor = '#3b82f6';
            closeButton.addEventListener('click', () => {
                document.body.removeChild(overlay);
            });

            messageBox.appendChild(messageParagraph);
            messageBox.appendChild(closeButton);
            overlay.appendChild(messageBox);
            document.body.appendChild(overlay);
        }
</script>
</body>
</html>
