<?php
require_once '../includes/auth_guard.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$current_user = getCurrentUser();

$date = $_GET['date'] ?? date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revenue Report - <?php echo htmlspecialchars($date); ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <style>
        body { 
            font-family: 'Poppins', sans-serif; 
            margin: 20px; 
            background: white; 
        }
        .header { 
            text-align: center; 
            border-bottom: 2px solid #4CAF50; 
            padding-bottom: 20px; 
            margin-bottom: 30px; 
        }
        .summary { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 20px; 
            margin-bottom: 30px; 
        }
        .summary-card { 
            background: #f9f9f9; 
            padding: 20px; 
            border-radius: 8px; 
            text-align: center; 
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 20px; 
        }
        th, td { 
            border: 1px solid #ddd; 
            padding: 12px; 
            text-align: left; 
        }
        th { 
            background: #4CAF50; 
            color: white; 
        }
        .total-row { 
            background: #e8f5e8; 
            font-weight: bold; 
        }
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><?php echo htmlspecialchars($current_user['company_name']); ?></h1>
        <h2>Daily Revenue Report</h2>
        <p><strong>Date:</strong> <?php echo htmlspecialchars($date); ?></p>
        <p><strong>Outlet:</strong> <?php echo htmlspecialchars($current_user['outlet_name'] ?? 'Main Outlet'); ?></p>
    </div>
    
    <div class="no-print" style="text-align: center; margin-bottom: 20px;">
        <button onclick="window.print()" style="background: #4CAF50; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">
            Print Report
        </button>
        <button onclick="window.close()" style="background: #666; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin-left: 10px;">
            Close
        </button>
    </div>

    <div id="report-content">
        <div class="summary" id="summary">
            <div class="summary-card">
                <h3>Loading...</h3>
                <p>Please wait while we generate your report</p>
            </div>
        </div>
        
        <div id="detailed-table">
            <!-- Table will be populated by JavaScript -->
        </div>
    </div>

    <script>
        
        async function loadRevenueReport() {
            try {
                const response = await fetch('./api/delivered_parcels_today.php');
                const data = await response.json();
                
                if (data.success && data.data) {
                    const parcels = data.data.parcels;
                    const summary = data.data.summary;
                    
                    
                    document.getElementById('summary').innerHTML = `
                        <div class="summary-card">
                            <h3>K ${summary.total_revenue.toFixed(2)}</h3>
                            <p>Total Revenue</p>
                        </div>
                        <div class="summary-card">
                            <h3>${summary.total_parcels}</h3>
                            <p>Parcels Delivered</p>
                        </div>
                        <div class="summary-card">
                            <h3>K ${summary.average_fee.toFixed(2)}</h3>
                            <p>Average Delivery Fee</p>
                        </div>
                        <div class="summary-card">
                            <h3>${summary.total_weight.toFixed(1)} kg</h3>
                            <p>Total Weight Delivered</p>
                        </div>
                    `;
                    
                    
                    let tableHtml = `
                        <h3>Detailed Parcel List</h3>
                        <table>
                            <thead>
                                <tr>
                                    <th>Track Number</th>
                                    <th>Sender</th>
                                    <th>Receiver</th>
                                    <th>Delivery Fee</th>
                                    <th>Weight</th>
                                    <th>Delivered Time</th>
                                </tr>
                            </thead>
                            <tbody>
                    `;
                    
                    parcels.forEach(parcel => {
                        const deliveredTime = new Date(parcel.delivery_date).toLocaleString();
                        tableHtml += `
                            <tr>
                                <td>${parcel.track_number}</td>
                                <td>${parcel.sender_name || 'N/A'}</td>
                                <td>${parcel.receiver_name || 'N/A'}</td>
                                <td>K ${parseFloat(parcel.delivery_fee || 0).toFixed(2)}</td>
                                <td>${parseFloat(parcel.parcel_weight || 0).toFixed(1)} kg</td>
                                <td>${deliveredTime}</td>
                            </tr>
                        `;
                    });
                    
                    tableHtml += `
                            <tr class="total-row">
                                <td colspan="3"><strong>TOTAL</strong></td>
                                <td><strong>K ${summary.total_revenue.toFixed(2)}</strong></td>
                                <td><strong>${summary.total_weight.toFixed(1)} kg</strong></td>
                                <td><strong>${summary.total_parcels} parcels</strong></td>
                            </tr>
                            </tbody>
                        </table>
                    `;
                    
                    document.getElementById('detailed-table').innerHTML = tableHtml;
                } else {
                    document.getElementById('report-content').innerHTML = '<p style="color: red; text-align: center;">Error loading report data.</p>';
                }
            } catch (error) {
                document.getElementById('report-content').innerHTML = '<p style="color: red; text-align: center;">Error connecting to server.</p>';
            }
        }
        
        
        document.addEventListener('DOMContentLoaded', loadRevenueReport);
    </script>
</body>
</html>
