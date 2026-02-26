<?php
require_once '../includes/auth_guard.php';
require_once '../includes/OutletAwareSupabaseHelper.php';

// only logged in outlet users
auth_guard();

$current_user = getCurrentUser();
$companyId = $_SESSION['company_id'] ?? null;
$outletId = $_SESSION['outlet_id'] ?? null;

$sup = new OutletAwareSupabaseHelper();

// fetch recent transactions for this outlet (limit 100)
$filter = 'company_id=eq.' . urlencode($companyId);
if ($outletId) {
    $filter .= '&outlet_id=eq.' . urlencode($outletId);
}
// order by payment date first (most recent at top) but fall back to created_at
$filter .= '&order=paid_at.desc,created_at.desc&limit=100';

$txns = $sup->get('payment_transactions', $filter,
    'id,tx_ref,amount,payment_method,mobile_network,mobile_number,status,paid_at,created_at,user_id,customer_name');

// gather user ids to resolve names
$userIds = array_filter(array_column($txns, 'user_id'));
$profileMap = [];
if (!empty($userIds)) {
    $in = implode(',', array_map('urlencode', $userIds));
    $profiles = $sup->get('profiles', "id=in.($in)", 'id,full_name');
    foreach ($profiles as $p) {
        $profileMap[$p['id']] = $p['full_name'];
    }
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction History</title>
    <!-- fonts & icons used across the app -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../css/outlet-dashboard.css">
    <link rel="stylesheet" href="../assets/css/search-notifications.css">
    <style>
    /* header card matching parcel-pool style */
    .page-header {
        background: linear-gradient(135deg, #2E0D2A 0%, #4A1C40 100%);
        color: white;
        padding: 2rem;
        border-radius: 1rem;
        margin: 20px auto;
        box-shadow: 0 10px 30px rgba(46, 13, 42, 0.3);
        max-width: 1400px;
        text-align: center;
    }
    .page-header h1, .page-header .subtitle {
        color: white;
    }

    /* widen main content container */
    .content-container { max-width: 1400px; }

    /* custom table styling for transaction history */
    #transactionTableWrapper {
        overflow-x: auto;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        padding: 16px;
        margin-top: 20px; /* give breathing room from header */
    }
    #transactionTableWrapper table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        /* allow horizontal scroll if necessary, but columns will respect max-width/ellipsis */
        transition: all 0.2s ease;
    }
    #transactionTableWrapper th,
    #transactionTableWrapper td {
        padding: 10px 12px;
        text-align: left;
        vertical-align: middle;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    /* specific widths to keep columns from colliding */
    #transactionTableWrapper td:nth-child(2), #transactionTableWrapper th:nth-child(2) { /* reference */
        max-width: 300px;
    }
    #transactionTableWrapper td:nth-child(3), #transactionTableWrapper th:nth-child(3) { /* amount */
        width: 120px;
    }
    #transactionTableWrapper thead th {
        background: linear-gradient(135deg, #4A1C40 0%, #2E0D2A 100%);
        color: #fff;
        position: sticky;
        top: 0;
        z-index: 2;
    }
    #transactionTableWrapper tbody tr:nth-child(even) {
        background: #f8f9fa;
    }
    #transactionTableWrapper tbody tr:hover {
        background: #e2e8f0;
    }

    /* badge styles for transaction status */
    .status-badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        /* default background for unknown/empty statuses */
        background-color: #6c757d;
        color: #fff;
    }
    .status-pending { background-color: #FFC107; color: #000; }
    .status-paid, .status-completed, .status-success { background-color: #28A745; }
    .status-failed, .status-cancelled, .status-error { background-color: #DC3545; }
    .status-refunded { background-color: #17A2B8; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <?php include '../includes/navbar.php'; ?>
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="content-container">
            <div class="page-header">
                <h1><i class="fas fa-receipt"></i> Transaction History</h1>
                <p class="subtitle">Payments processed through this outlet</p>
            </div>

            <div class="dashboard-content">
                <div class="activity-table-wrapper" id="transactionTableWrapper">
                    <div class="form-section">
                        <table class="recent-activity-table data-table" style="min-width:900px;">
                        <thead>
                            <tr>
                                <th>Date / Time</th>
                                <th>Reference</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Network</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>By</th>
                                <th>Customer</th>
                            </tr>
                        </thead>
                        <tbody>
<?php foreach ($txns as $t):
    $dt = $t['paid_at'] ?: $t['created_at'] ?? '';
    $by = $profileMap[$t['user_id']] ?? '-';
    $cust = htmlspecialchars($t['customer_name'] ?? '-', ENT_QUOTES);
?>
                            <tr>
                                <td><?php echo htmlspecialchars($dt); ?></td>
                                <td><?php echo htmlspecialchars($t['tx_ref']); ?></td>
                                <td><?php echo 'ZMW ' . number_format($t['amount'],2); ?></td>
                                <td><?php echo htmlspecialchars($t['payment_method']); ?></td>
                                <td><?php echo htmlspecialchars($t['mobile_network'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($t['mobile_number'] ?? '-'); ?></td>
                                <td><?php 
                                    $stat = $t['status'] ?? '';
                                    $class = strtolower(str_replace([' ', '_'], '-', $stat));
                                ?>
                                <span class="status-badge <?php echo htmlspecialchars($class); ?>"><?php echo htmlspecialchars($stat); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($by); ?></td>
                                <td><?php echo $cust; ?></td>
                            </tr>
<?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
    <style>
        #transactionTableWrapper { overflow-x: auto; }
        #transactionTableWrapper table td, #transactionTableWrapper table th { white-space: nowrap; }
        #transactionTableWrapper table td {
            word-wrap: break-word;
            max-width: 180px;
        }
        #transactionTableWrapper table th {
            padding: 12px 15px;
            background: linear-gradient(135deg, rgba(46,13,42,0.95), rgba(74,28,64,0.85));
            color: white;
        }
    </style>

    <script src="../assets/js/sidebar-toggle.js"></script>
    <script src="../assets/js/notifications.js"></script>

    <?php include __DIR__ . '/../includes/pwa_install_button.php'; ?>
    <script src="../js/pwa-install.js"></script>
</body>
</html>