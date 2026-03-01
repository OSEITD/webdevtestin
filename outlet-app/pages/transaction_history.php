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
    'id,tx_ref,receipt_number,amount,total_amount,transaction_fee,commission_amount,net_amount,vat_amount,' .
    'currency,payment_method,payment_type,mobile_network,mobile_number,card_last4,card_type,' .
    'status,settlement_status,lenco_status,' .
    'customer_name,customer_email,customer_phone,' .
    'parcel_id,outlet_id,user_id,' .
    'paid_at,created_at,verified_at,error_message');

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
        overflow-y: auto;
        max-height: calc(100vh - 320px);
        -webkit-overflow-scrolling: touch;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        padding: 16px;
        margin-top: 20px; /* give breathing room from header */
        position: relative;
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
    $dt   = $t['paid_at'] ?: $t['created_at'] ?? '';
    $by   = $profileMap[$t['user_id']] ?? '-';
    $cust = htmlspecialchars($t['customer_name'] ?? '-', ENT_QUOTES);
    // Build safe JSON for data attribute
    $rowData = json_encode([
        'id'                 => $t['id'] ?? '',
        'tx_ref'             => $t['tx_ref'] ?? '',
        'receipt_number'     => $t['receipt_number'] ?? '',
        'amount'             => $t['amount'] ?? 0,
        'total_amount'       => $t['total_amount'] ?? $t['amount'] ?? 0,
        'transaction_fee'    => $t['transaction_fee'] ?? 0,
        'commission_amount'  => $t['commission_amount'] ?? 0,
        'net_amount'         => $t['net_amount'] ?? 0,
        'vat_amount'         => $t['vat_amount'] ?? 0,
        'currency'           => $t['currency'] ?? 'ZMW',
        'payment_method'     => $t['payment_method'] ?? '',
        'payment_type'       => $t['payment_type'] ?? '',
        'mobile_network'     => $t['mobile_network'] ?? '',
        'mobile_number'      => $t['mobile_number'] ?? '',
        'card_last4'         => $t['card_last4'] ?? '',
        'card_type'          => $t['card_type'] ?? '',
        'status'             => $t['status'] ?? '',
        'settlement_status'  => $t['settlement_status'] ?? '',
        'lenco_status'       => $t['lenco_status'] ?? '',
        'customer_name'      => $t['customer_name'] ?? '',
        'customer_email'     => $t['customer_email'] ?? '',
        'customer_phone'     => $t['customer_phone'] ?? '',
        'parcel_id'          => $t['parcel_id'] ?? '',
        'staff_name'         => $by,
        'paid_at'            => $t['paid_at'] ?? '',
        'created_at'         => $t['created_at'] ?? '',
        'verified_at'        => $t['verified_at'] ?? '',
        'error_message'      => $t['error_message'] ?? '',
    ]);
?>
                            <tr class="txn-row" data-txn="<?php echo htmlspecialchars($rowData, ENT_QUOTES); ?>" style="cursor:pointer;" title="Click to view details">
                                <td><?php echo htmlspecialchars($dt); ?></td>
                                <td><?php echo htmlspecialchars($t['tx_ref']); ?></td>
                                <td><?php echo ($t['currency'] ?? 'ZMW') . ' ' . number_format($t['amount'],2); ?></td>
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
        #transactionTableWrapper { overflow-x: auto; overflow-y: auto; max-height: calc(100vh - 320px); -webkit-overflow-scrolling: touch; }
        #transactionTableWrapper table td, #transactionTableWrapper table th { white-space: nowrap; }
        #transactionTableWrapper table td {
            word-wrap: break-word;
            max-width: 180px;
        }
        #transactionTableWrapper table th {
            padding: 12px 15px;
            background: linear-gradient(135deg, rgba(46,13,42,0.95), rgba(74,28,64,0.85));
            color: white;
            position: sticky;
            top: 0;
            z-index: 3;
        }
    </style>

    <script src="../assets/js/sidebar-toggle.js"></script>
    <script src="../assets/js/notifications.js"></script>

    <!-- Transaction Detail Modal -->
    <div id="txnModal" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.55); overflow-y:auto; padding:20px 10px;">
        <div style="max-width:760px; margin:40px auto; background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 20px 60px rgba(0,0,0,0.3); animation:txnSlideIn .25s ease;">
            <!-- Header -->
            <div id="txnModalHeader" style="background:linear-gradient(135deg,#2E0D2A 0%,#4A1C40 100%); color:#fff; padding:20px 24px; display:flex; justify-content:space-between; align-items:flex-start;">
                <div>
                    <div style="font-size:11px; text-transform:uppercase; letter-spacing:1px; opacity:.75; margin-bottom:4px;"><i class="fas fa-receipt"></i> Transaction Details</div>
                    <div id="txnModalRef" style="font-size:18px; font-weight:700; font-family:monospace;"></div>
                    <div id="txnModalReceipt" style="font-size:12px; opacity:.7; margin-top:3px;"></div>
                </div>
                <div style="display:flex; gap:8px; align-items:center;">
                    <span id="txnModalStatus" style="padding:5px 14px; border-radius:20px; font-size:12px; font-weight:700; text-transform:uppercase;"></span>
                    <button onclick="closeTxnModal()" style="background:rgba(255,255,255,.15); border:none; color:#fff; width:32px; height:32px; border-radius:50%; cursor:pointer; font-size:16px; display:flex; align-items:center; justify-content:center;">&times;</button>
                </div>
            </div>
            <!-- Body -->
            <div style="padding:24px; display:grid; grid-template-columns:1fr 1fr; gap:16px;" id="txnModalBody">
                <!-- Cards injected by JS -->
            </div>
            <!-- Footer -->
            <div style="padding:16px 24px; background:#f8f9fa; border-top:1px solid #e9ecef; display:flex; justify-content:flex-end; gap:10px;">
                <button onclick="closeTxnModal()" style="padding:9px 22px; background:#fff; border:1px solid #dee2e6; border-radius:8px; cursor:pointer; font-size:14px; font-weight:600; color:#495057;">Close</button>
                <button id="txnViewParcelBtn" style="display:none; padding:9px 22px; background:linear-gradient(135deg,#2E0D2A,#4A1C40); border:none; color:#fff; border-radius:8px; cursor:pointer; font-size:14px; font-weight:600;">
                    <i class="fas fa-box"></i> View Parcel
                </button>
            </div>
        </div>
    </div>

    <style>
    @keyframes txnSlideIn { from { opacity:0; transform:translateY(-20px); } to { opacity:1; transform:translateY(0); } }
    .txn-row:hover td { background:#f0e8ef !important; }
    .txn-detail-card {
        background:#f8f9fa; border-radius:10px; padding:14px 16px;
        border:1px solid #e9ecef; font-size:13.5px;
    }
    .txn-detail-card h4 {
        font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.8px;
        color:#6c757d; margin:0 0 10px; display:flex; align-items:center; gap:6px;
    }
    .txn-detail-card h4 i { color:#4A1C40; }
    .txn-detail-row { display:flex; justify-content:space-between; align-items:center; padding:4px 0; border-bottom:1px solid #e9ecef; }
    .txn-detail-row:last-child { border-bottom:none; }
    .txn-detail-label { color:#6c757d; font-size:12.5px; }
    .txn-detail-value { font-weight:600; color:#212529; text-align:right; max-width:60%; word-break:break-all; }
    .txn-amount-big { font-size:28px; font-weight:800; color:#2E0D2A; }
    .txn-status-pill {
        display:inline-block; padding:3px 10px; border-radius:12px;
        font-size:11px; font-weight:700; text-transform:uppercase;
    }
    .txn-status-pill.successful,.txn-status-pill.paid,.txn-status-pill.completed { background:#d4edda; color:#155724; }
    .txn-status-pill.pending { background:#fff3cd; color:#856404; }
    .txn-status-pill.failed,.txn-status-pill.cancelled,.txn-status-pill.error { background:#f8d7da; color:#721c24; }
    .txn-status-pill.refunded { background:#d1ecf1; color:#0c5460; }
    .txn-status-pill.settled { background:#d4edda; color:#155724; }
    @media(max-width:600px){
        #txnModalBody { grid-template-columns:1fr !important; }
    }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.txn-row').forEach(row => {
            row.addEventListener('click', function() {
                try {
                    const txn = JSON.parse(this.dataset.txn);
                    openTxnModal(txn);
                } catch(e) { console.error('Parse error', e); }
            });
        });
    });

    function fmt(val, digits = 2) {
        const n = parseFloat(val);
        return isNaN(n) ? '-' : n.toFixed(digits);
    }
    function fmtDate(d) {
        if (!d) return '-';
        return new Date(d).toLocaleString('en-GB', { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' });
    }
    function statusPill(s) {
        if (!s) return '-';
        const cls = s.toLowerCase().replace(/[_ ]+/g, '-');
        return `<span class="txn-status-pill ${cls}">${s.replace(/_/g,' ')}</span>`;
    }
    function card(icon, title, rows) {
        const rowsHtml = rows.map(([label, val]) =>
            `<div class="txn-detail-row"><span class="txn-detail-label">${label}</span><span class="txn-detail-value">${val ?? '-'}</span></div>`
        ).join('');
        return `<div class="txn-detail-card"><h4><i class="fas fa-${icon}"></i> ${title}</h4>${rowsHtml}</div>`;
    }

    function openTxnModal(t) {
        const cur = t.currency || 'ZMW';

        // Header
        document.getElementById('txnModalRef').textContent = t.tx_ref || t.id?.substring(0, 12) || '-';
        document.getElementById('txnModalReceipt').textContent = t.receipt_number ? 'Receipt: ' + t.receipt_number : '';
        const statusEl = document.getElementById('txnModalStatus');
        statusEl.textContent = (t.status || '').replace(/_/g, ' ').toUpperCase();
        const sMap = { successful:'#d4edda', paid:'#d4edda', completed:'#d4edda',
                       pending:'#fff3cd', failed:'#f8d7da', cancelled:'#f8d7da',
                       refunded:'#d1ecf1', error:'#f8d7da' };
        const sTxt = { successful:'#155724', paid:'#155724', completed:'#155724',
                       pending:'#856404', failed:'#721c24', cancelled:'#721c24',
                       refunded:'#0c5460', error:'#721c24' };
        const sk = (t.status || '').toLowerCase();
        statusEl.style.background = sMap[sk] || '#e9ecef';
        statusEl.style.color = sTxt[sk] || '#495057';

        // Body cards
        let paymentDetail = '';
        if (t.mobile_number || t.mobile_network) {
            paymentDetail = `${t.mobile_network || ''} ${t.mobile_number || ''}`.trim();
        } else if (t.card_last4) {
            paymentDetail = `${t.card_type || 'Card'} •••• ${t.card_last4}`;
        }

        const body = document.getElementById('txnModalBody');
        body.innerHTML = [
            card('dollar-sign', 'Amounts', [
                ['Total Charged',  `<span class="txn-amount-big">${cur} ${fmt(t.total_amount)}</span>`],
                ['Base Amount',    `${cur} ${fmt(t.amount)}`],
                ['Transaction Fee',`${cur} ${fmt(t.transaction_fee)}`],
                ['VAT (16%)',      `${cur} ${fmt(t.vat_amount)}`],
                ['Commission',     `${cur} ${fmt(t.commission_amount)}`],
                ['Net to Outlet',  `${cur} ${fmt(t.net_amount)}`],
            ]),
            card('credit-card', 'Payment', [
                ['Method',         (t.payment_method || '-').replace(/_/g,' ')],
                ['Type',           t.payment_type || '-'],
                ['Details',        paymentDetail || '-'],
                ['Settlement',     statusPill(t.settlement_status)],
                ['Gateway Status', t.lenco_status || '-'],
            ]),
            card('user', 'Customer', [
                ['Name',  t.customer_name || '-'],
                ['Email', t.customer_email ? `<a href="mailto:${t.customer_email}" style="color:#4A1C40">${t.customer_email}</a>` : '-'],
                ['Phone', t.customer_phone || '-'],
                ['Staff', t.staff_name || '-'],
            ]),
            card('clock', 'Timestamps', [
                ['Created',  fmtDate(t.created_at)],
                ['Paid At',  fmtDate(t.paid_at)],
                ['Verified', fmtDate(t.verified_at)],
                ['Error',    t.error_message || 'None'],
            ]),
        ].join('');

        // "View Parcel" button
        const vpBtn = document.getElementById('txnViewParcelBtn');
        if (t.parcel_id) {
            vpBtn.style.display = 'inline-flex';
            vpBtn.onclick = () => { window.location.href = `parcel_management.php?parcel_id=${encodeURIComponent(t.parcel_id)}`; };
        } else {
            vpBtn.style.display = 'none';
        }

        document.getElementById('txnModal').style.display = 'block';
        document.body.style.overflow = 'hidden';
    }

    function closeTxnModal() {
        document.getElementById('txnModal').style.display = 'none';
        document.body.style.overflow = '';
    }

    // Close on backdrop click
    document.getElementById('txnModal').addEventListener('click', function(e) {
        if (e.target === this) closeTxnModal();
    });
    // Close on Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeTxnModal();
    });
    </script>

    <?php include __DIR__ . '/../includes/pwa_install_button.php'; ?>
    <script src="../js/pwa-install.js"></script>
</body>
</html>