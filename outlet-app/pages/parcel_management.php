<?php
require_once '../includes/auth_guard.php';

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
    <title>Parcel Management</title>

    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../css/outlet-dashboard.css">
    <link rel="stylesheet" href="../css/parcel_management.css">
    <link rel="stylesheet" href="../css/notifications.css">

    <style>
        .notifications-popup.show {
            opacity: 1 !important;
            visibility: visible !important;
            transform: translateY(0) scale(1) !important;
            display: block !important;
        }

        .notifications-popup {
            position: fixed !important;
            z-index: 99999 !important;
        }
    </style>

    <style>
        .detail-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }

        .detail-card p {
            margin: 5px 0;
            font-size: 14px;
            line-height: 1.4;
        }

        .detail-card strong {
            color: #495057;
            font-weight: 600;
        }

        .status-badge {
            display: inline-block;
        }

        .status-badge.pending { background: #fff3cd; color: #856404; }
        .status-badge.at_outlet { background: #d4edda; color: #155724; }
        .status-badge.in_transit { background: #cce5ff; color: #004085; }
        .status-badge.delivered { background: #d4edda; color: #155724; }
        .status-badge.cancelled { background: #f8d7da; color: #721c24; }
        .status-badge.ready_for_dispatch { background: #e2e3ff; color: #383d41; }

        .payment-status-badge {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .payment-status-badge.paid { background: #d4edda; color: #155724; }
        .payment-status-badge.pending { background: #fff3cd; color: #856404; }
        .payment-status-badge.failed { background: #f8d7da; color: #721c24; }

        .action-btn {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 14px;
            min-width: 140px;
            background: linear-gradient(45deg, #667eea 0%, #764ba2 100%);
        }

        .action-btn.check-in {
            background: linear-gradient(45deg, #56ab2f 0%, #a8e6cf 100%);
        }

        .action-btn.check-out {
            background: linear-gradient(45deg, #ff6b6b 0%, #ffa726 100%);
        }

        .action-btn.delivered {
            background: linear-gradient(45deg, #4facfe 0%, #00f2fe 100%);
        }

        .action-btn.pending {
            background: linear-gradient(45deg, #ffa726 0%, #ffcc02 100%);
        }

        .action-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .action-btn:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .scanner-container h1 {
            color: #2d3748;
            margin-bottom: 10px;
        }

        .scan-instructions {
            color: #718096;
            font-size: 16px;
            margin-bottom: 25px;
        }

        .scan-history {
            width: 100%;
            max-width: calc(100% - 40px);
            margin: 20px auto 0;
            box-sizing: border-box;
        }

        .scan-history .table-wrapper {
            overflow-x: auto;
        }

        .scan-history table {
            margin-top: 15px;
            border-collapse: collapse;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            width: 100%;
            min-width: 700px; /* keeps columns readable on larger viewports, allows horizontal scroll on small */
        }

        .scan-history th, .scan-history td {
            padding: 12px 15px;
            text-align: left;
            vertical-align: middle;
        }

        @media (max-width: 768px) {
            /* Make table responsive: convert to stacked cards */
            .scan-history table, .scan-history thead, .scan-history tbody, .scan-history th, .scan-history td, .scan-history tr {
                display: block;
            }
            .scan-history thead tr {
                position: absolute;
                top: -9999px;
                left: -9999px;
            }
            .scan-history tr {
                margin: 0 0 12px 0;
                border-radius: 8px;
                background: #fff;
                box-shadow: 0 1px 4px rgba(0,0,0,0.05);
                padding: 10px;
            }
            .scan-history td {
                border: none;
                position: relative;
                padding-left: 50%;
                white-space: normal;
            }
            .scan-history td::before {
                position: absolute;
                left: 12px;
                top: 12px;
                width: 45%;
                padding-right: 10px;
                white-space: nowrap;
                font-weight: 600;
                color: #4a5568;
            }
            .scan-history td:nth-of-type(1)::before { content: "Tracking Number"; }
            .scan-history td:nth-of-type(2)::before { content: "Action Type"; }
            .scan-history td:nth-of-type(3)::before { content: "Status Details"; }
            .scan-history td:nth-of-type(4)::before { content: "Time"; }
            .scan-history td:nth-of-type(5)::before { content: "Staff & Customer"; }
        }

        .scan-history th {
            background: #4a5568;
            color: white;
            padding: 12px 15px;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 1px;
        }

        .scan-history td {
            padding: 12px 15px;
            border-bottom: 1px solid #e2e8f0;
        }

        .scan-history tr:hover {
            background: #f8f9fa;
        }

        .scan-history tr:last-child td {
            border-bottom: none;
        }
    </style>

    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

</head>
<body class="bg-gray-100 min-h-screen">

    <div class="mobile-dashboard">

        <?php include '../includes/navbar.php'; ?>
        <?php include '../includes/sidebar.php'; ?>

        <div class="menu-overlay" id="menuOverlay"></div>

        <div id="scanner" class="content-section active">
            <div class="content-body">
                <div class="scanner-container">
                    <h1>Parcel Management</h1>
                    <p class="scan-instructions">Use the device's camera to scan parcel barcodes for check-in or check-out, or enter manually.</p>
                    <div id="reader" style="width: 300px; margin: 0 auto 20px;"></div>
                    <div id="qr-reader-results" style="display:none; margin-bottom: 20px; text-align: left; max-width: 900px; margin-left: auto; margin-right: auto; background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #f0f0f0; padding-bottom: 15px;">
                            <h2 style="margin: 0; color: #2d3748; font-size: 24px; font-weight: 600;">
                                <i class="fas fa-box" style="margin-right: 10px; color: #4a5568;"></i>
                                Parcel Details
                            </h2>
                            <span id="detailStatus" class="status-badge" style="padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase;"></span>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 25px;">
                            <div class="detail-card">
                                <h3 style="margin: 0 0 10px 0; color: #4a5568; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">
                                    <i class="fas fa-barcode" style="margin-right: 5px;"></i>Tracking Info
                                </h3>
                                <p style="margin: 5px 0;"><strong>Track Number:</strong> <span id="detailTrackNumber" style="font-family: monospace; background: #f7fafc; padding: 2px 6px; border-radius: 4px;"></span></p>
                                <p style="margin: 5px 0;"><strong>Created:</strong> <span id="detailCreatedAt"></span></p>
                                <p style="margin: 5px 0;"><strong>Delivery Option:</strong> <span id="detailDeliveryOption"></span></p>
                            </div>

                            <div class="detail-card">
                                <h3 style="margin: 0 0 10px 0; color: #4a5568; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">
                                    <i class="fas fa-user" style="margin-right: 5px;"></i>Sender Info
                                </h3>
                                <p style="margin: 5px 0;"><strong>Name:</strong> <span id="detailSenderName"></span></p>
                                <p style="margin: 5px 0;"><strong>Phone:</strong> <span id="detailSenderPhone"></span></p>
                                <p style="margin: 5px 0;"><strong>Address:</strong> <span id="detailSenderAddress"></span></p>
                            </div>

                            <div class="detail-card">
                                <h3 style="margin: 0 0 10px 0; color: #4a5568; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">
                                    <i class="fas fa-user-tag" style="margin-right: 5px;"></i>Receiver Info
                                </h3>
                                <p style="margin: 5px 0;"><strong>Name:</strong> <span id="detailReceiverName"></span></p>
                                <p style="margin: 5px 0;"><strong>Phone:</strong> <span id="detailReceiverPhone"></span></p>
                                <p style="margin: 5px 0;"><strong>Address:</strong> <span id="detailReceiverAddress"></span></p>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 25px;">
                            <div class="detail-card">
                                <h3 style="margin: 0 0 10px 0; color: #4a5568; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">
                                    <i class="fas fa-box-open" style="margin-right: 5px;"></i>Package Info
                                </h3>
                                <p style="margin: 5px 0;"><strong>Contents:</strong> <span id="detailPackageDetails"></span></p>
                                <p style="margin: 5px 0;"><strong>Weight:</strong> <span id="detailParcelWeight"></span> kg</p>
                                <p style="margin: 5px 0;"><strong>Dimensions:</strong> <span id="detailDimensions">-</span></p>
                                <p style="margin: 5px 0;"><strong>Value:</strong> ZMW <span id="detailDeclaredValue">0</span></p>
                            </div>

                            <div class="detail-card">
                                <h3 style="margin: 0 0 10px 0; color: #4a5568; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">
                                    <i class="fas fa-route" style="margin-right: 5px;"></i>Route Info
                                </h3>
                                <p style="margin: 5px 0;"><strong>Origin:</strong> <span id="detailOriginOutlet">-</span></p>
                                <p style="margin: 5px 0;"><strong>Destination:</strong> <span id="detailDestinationOutlet">-</span></p>
                                <p style="margin: 5px 0;"><strong>Est. Delivery:</strong> <span id="detailEstimatedDelivery">-</span></p>
                            </div>

                            <div class="detail-card">
                                <h3 style="margin: 0 0 10px 0; color: #4a5568; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">
                                    <i class="fas fa-dollar-sign" style="margin-right: 5px;"></i>Payment Info
                                </h3>
                                <p style="margin: 5px 0;"><strong>Delivery Fee:</strong> ZMW <span id="detailDeliveryFee">0</span></p>
                                <p style="margin: 5px 0;"><strong>Insurance:</strong> ZMW <span id="detailInsuranceAmount">0</span></p>
                                <p style="margin: 5px 0;"><strong>Cash to Collect:</strong> ZMW <span id="detailCodAmount">0</span></p>
                                <p style="margin: 5px 0;"><strong>Payment Status:</strong> <span id="detailPaymentStatus" class="payment-status-badge"></span></p>
                            </div>
                        </div>

                        <div id="detailInstructionsContainer" style="display: none; margin-bottom: 20px; padding: 15px; background: #fef5e7; border-left: 4px solid #ed8936; border-radius: 6px;">
                            <h4 style="margin: 0 0 8px 0; color: #c05621; font-size: 14px;">
                                <i class="fas fa-exclamation-triangle" style="margin-right: 5px;"></i>Special Instructions
                            </h4>
                            <p id="detailInstructions" style="margin: 0; color: #744210; font-style: italic;"></p>
                        </div>

                        <div id="detailPhotosContainer" style="display: none; margin-bottom: 20px; padding: 15px; background: #f7fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                            <h4 style="margin: 0 0 12px 0; color: #4a5568; font-size: 14px; font-weight: 600;">
                                <i class="fas fa-camera" style="margin-right: 5px;"></i>Parcel Photos
                            </h4>
                            <div id="detailPhotos" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px;"></div>
                        </div>

                        <div style="display: flex; flex-wrap: wrap; gap: 12px; justify-content: center; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                            <button id="checkInBtn" class="action-btn check-in" disabled>
                                <i class="fas fa-sign-in-alt" style="margin-right: 5px;"></i>Check-in Parcel
                            </button>
                            <button id="pendingBtn" class="action-btn pending" disabled>
                                <i class="fas fa-clock" style="margin-right: 5px;"></i>Mark as Pending
                            </button>
                            <button id="markDeliveredBtn" class="action-btn delivered" disabled>
                                <i class="fas fa-check-circle" style="margin-right: 5px;"></i>Mark as Delivered
                            </button>
                        </div>
                    </div>
                    <div id="errorMessage" style="display:none; color:red; font-weight:bold; margin-bottom: 20px;"></div>
                    <div class="flex flex-col items-stretch space-y-4 mt-6">
                        <input type="text" id="manualBarcodeInput" placeholder="Enter barcode manually" class="pl-4 pr-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primaryDark focus:border-transparent">
                        <button id="manualScanBtn" class="bg-primaryDark hover:bg-primaryLight text-white font-bold py-2 px-6 rounded-lg shadow-md transition-colors duration-200">
                            Scan Barcode
                        </button>
                </div>

                <div class="scan-history">
                    <h2 style="color: #2d3748; font-size: 20px; margin-bottom: 15px; display: flex; align-items: center; justify-content: space-between;">
                        <span>
                            <i class="fas fa-history" style="margin-right: 10px; color: #4a5568;"></i>
                            Recent Scan Activity
                        </span>
                        <small style="color: #718096; font-size: 12px; font-weight: normal; display: flex; align-items: center;">
                            <i class="fas fa-sync-alt" style="margin-right: 5px; font-size: 11px;"></i>
                            Auto-refreshing every 30s
                        </small>
                    </h2>
                    <div class="table-wrapper">
                        <table style="width: 100%;">
                            <thead>
                                <tr>
                                    <th style="width: 20%;">Tracking Number</th>
                                    <th style="width: 15%;">Action Type</th>
                                    <th style="width: 30%;">Status Change Details</th>
                                    <th style="width: 15%;">Time</th>
                                    <th style="width: 20%;">Staff & Customer</th>
                                </tr>
                            </thead>
                            <tbody id="scanHistoryTableBody">
                                <tr>
                                    <td colspan="5" style="text-align: center; color: #718096; padding: 20px;">
                                        <i class="fas fa-spinner fa-spin" style="margin-right: 8px;"></i>
                                        Loading scan history...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
document.addEventListener('DOMContentLoaded', () => {
    const manualBarcodeInput = document.getElementById('manualBarcodeInput');
    const manualScanBtn = document.getElementById('manualScanBtn');
    const scanHistoryTableBody = document.getElementById('scanHistoryTableBody');
    const outletId = '<?php echo htmlspecialchars($_SESSION["outlet_id"] ?? ""); ?>';
    const companyId = '<?php echo htmlspecialchars($_SESSION["company_id"] ?? ""); ?>';
    const userRole = '<?php echo htmlspecialchars($_SESSION["role"] ?? ""); ?>';
    const qrReaderDiv = document.getElementById('reader');
    const qrResultDiv = document.getElementById('qr-reader-results');

    function displayParcelDetails(parcel) {
        qrResultDiv.style.display = 'block';

        document.getElementById('detailTrackNumber').textContent = parcel.track_number || '-';
        document.getElementById('detailCreatedAt').textContent = parcel.created_at ?
            new Date(parcel.created_at).toLocaleDateString('en-US', {
                year: 'numeric', month: 'short', day: 'numeric',
                hour: '2-digit', minute: '2-digit'
            }) : '-';
        document.getElementById('detailDeliveryOption').textContent = parcel.delivery_option || 'Standard';

        document.getElementById('detailSenderName').textContent = parcel.sender_name || '-';
        document.getElementById('detailSenderPhone').textContent = parcel.sender_phone || '-';
        document.getElementById('detailSenderAddress').textContent = parcel.sender_address || '-';

        document.getElementById('detailReceiverName').textContent = parcel.receiver_name || '-';
        document.getElementById('detailReceiverPhone').textContent = parcel.receiver_phone || '-';
        document.getElementById('detailReceiverAddress').textContent = parcel.receiver_address || '-';

        document.getElementById('detailPackageDetails').textContent = parcel.package_details || '-';
        document.getElementById('detailParcelWeight').textContent = parcel.parcel_weight || '0';

        const dimensions = [];
        if (parcel.parcel_length) dimensions.push(`L: ${parcel.parcel_length}cm`);
        if (parcel.parcel_width) dimensions.push(`W: ${parcel.parcel_width}cm`);
        if (parcel.parcel_height) dimensions.push(`H: ${parcel.parcel_height}cm`);
        document.getElementById('detailDimensions').textContent = dimensions.length > 0 ? dimensions.join(' Ã— ') : '-';

        document.getElementById('detailDeclaredValue').textContent = parcel.declared_value || '0';

        document.getElementById('detailOriginOutlet').textContent = parcel.origin_outlet_name || 'Loading...';
        document.getElementById('detailDestinationOutlet').textContent = parcel.destination_outlet_name || 'Loading...';
        document.getElementById('detailEstimatedDelivery').textContent = parcel.estimated_delivery_date ?
            new Date(parcel.estimated_delivery_date).toLocaleDateString() : '-';

        document.getElementById('detailDeliveryFee').textContent = parcel.delivery_fee || '0';
        document.getElementById('detailInsuranceAmount').textContent = parcel.insurance_amount || '0';
        document.getElementById('detailCodAmount').textContent = parcel.cod_amount || '0';

        const paymentStatusElement = document.getElementById('detailPaymentStatus');
        const paymentStatus = parcel.payment_status || 'pending';
        paymentStatusElement.textContent = paymentStatus.replace('_', ' ').toUpperCase();
        paymentStatusElement.className = `payment-status-badge ${paymentStatus}`;

        const statusElement = document.getElementById('detailStatus');
        const status = parcel.status || 'pending';
        statusElement.textContent = status.replace('_', ' ').toUpperCase();
        statusElement.className = `status-badge ${status}`;

        const instructionsContainer = document.getElementById('detailInstructionsContainer');
        const instructionsElement = document.getElementById('detailInstructions');
        if (parcel.special_instructions && parcel.special_instructions.trim()) {
            instructionsElement.textContent = parcel.special_instructions;
            instructionsContainer.style.display = 'block';
        } else {
            instructionsContainer.style.display = 'none';
        }

        
        const photosContainer = document.getElementById('detailPhotosContainer');
        const photosElement = document.getElementById('detailPhotos');
        if (parcel.photo_urls && Array.isArray(parcel.photo_urls) && parcel.photo_urls.length > 0) {
            photosElement.innerHTML = parcel.photo_urls.map((url, index) => `
                <div style="position: relative; overflow: hidden; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <img src="${url}" alt="Parcel Photo ${index + 1}" 
                         style="width: 100%; height: 150px; object-fit: cover; cursor: pointer; transition: transform 0.2s;"
                         onmouseover="this.style.transform='scale(1.05)'"
                         onmouseout="this.style.transform='scale(1)'"
                         onclick="window.open('${url}', '_blank')">
                </div>
            `).join('');
            photosContainer.style.display = 'block';
        } else {
            photosContainer.style.display = 'none';
        }

        if (parcel.origin_outlet_id || parcel.destination_outlet_id) {
            resolveOutletNames(parcel.origin_outlet_id, parcel.destination_outlet_id);
        }

        const checkInBtn = document.getElementById('checkInBtn');
        const pendingBtn = document.getElementById('pendingBtn');
        const checkOutBtn = document.getElementById('checkOutBtn');
        const markDeliveredBtn = document.getElementById('markDeliveredBtn');

        if (checkInBtn) {
            checkInBtn.onclick = () => performScanWithBarcode('check-in', parcel.track_number);
            checkInBtn.disabled = false;
        }
        if (pendingBtn) {
            pendingBtn.onclick = () => performScanWithBarcode('pending', parcel.track_number);
            pendingBtn.disabled = false;
        }
        if (checkOutBtn) {
            checkOutBtn.onclick = () => performScanWithBarcode('check-out', parcel.track_number);
            checkOutBtn.disabled = false;
        }
        if (markDeliveredBtn) {
            markDeliveredBtn.onclick = () => performScanWithBarcode('mark-delivered', parcel.track_number);
            markDeliveredBtn.disabled = false;
        }
    }

    async function resolveOutletNames(originId, destinationId) {
        try {
            const promises = [];

            if (originId) {
                promises.push(
                    fetch(`../api/outlets/get_outlets.php?id=${originId}`, { credentials: 'same-origin' })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success && data.outlets && data.outlets[0]) {
                                document.getElementById('detailOriginOutlet').textContent = data.outlets[0].outlet_name;
                            }
                        })
                        .catch(() => {
                            document.getElementById('detailOriginOutlet').textContent = 'Unknown Outlet';
                        })
                );
            }

            if (destinationId) {
                promises.push(
                    fetch(`../api/outlets/get_outlets.php?id=${destinationId}`, { credentials: 'same-origin' })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success && data.outlets && data.outlets[0]) {
                                document.getElementById('detailDestinationOutlet').textContent = data.outlets[0].outlet_name;
                            }
                        })
                        .catch(() => {
                            document.getElementById('detailDestinationOutlet').textContent = 'Unknown Outlet';
                        })
                );
            }

            await Promise.all(promises);
        } catch (error) {
            console.warn('Error resolving outlet names:', error);
        }
    }

    function clearParcelDetails() {
        qrResultDiv.style.display = 'none';
    }

    function showError(message, type = 'error') {
        const errorDiv = document.getElementById('errorMessage');
        const iconClass = type === 'error' ? 'fas fa-exclamation-triangle' :
                         type === 'success' ? 'fas fa-check-circle' : 'fas fa-info-circle';
        const bgColor = type === 'error' ? '#fed7d7' :
                       type === 'success' ? '#c6f6d5' : '#bee3f8';
        const textColor = type === 'error' ? '#c53030' :
                         type === 'success' ? '#2f855a' : '#2b6cb0';
        const borderColor = type === 'error' ? '#e53e3e' :
                           type === 'success' ? '#38a169' : '#3182ce';

        errorDiv.innerHTML = `
            <div style="padding: 15px; border-radius: 8px; margin-bottom: 15px; font-size: 14px; display: flex; align-items: center; background: ${bgColor}; color: ${textColor}; border-left: 4px solid ${borderColor}; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <i class="${iconClass}" style="margin-right: 10px; font-size: 16px;"></i>
                <span style="flex: 1;">${message}</span>
                <button onclick="this.parentElement.parentElement.style.display='none'"
                        style="margin-left: auto; background: none; border: none; cursor: pointer; font-size: 18px; color: ${textColor}; opacity: 0.7; hover: opacity: 1;">
                    &times;
                </button>
            </div>
        `;
        errorDiv.style.display = 'block';

        if (type === 'success') {
            setTimeout(() => {
                errorDiv.style.display = 'none';
            }, 5000);
        }
    }

    function performScanWithBarcode(actionType, barcode) {
        if (!barcode || !actionType || !outletId) {
            showError('Missing required parameters: barcode, action, or outlet ID.');
            return;
        }

        let button = null;
        if (actionType === 'check-in') {
            button = document.getElementById('checkInBtn');
        } else if (actionType === 'pending') {
            button = document.getElementById('pendingBtn');
        } else if (actionType === 'check-out') {
            button = document.getElementById('checkOutBtn');
        } else if (actionType === 'mark-delivered') {
            button = document.getElementById('markDeliveredBtn');
        }

        const originalText = button ? button.textContent : null;
        if (button) {
            button.textContent = 'Processing...';
            button.disabled = true;
        }

        fetch('../api/parcels/update_parcel_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                barcode: barcode,
                action: actionType,
                outlet_id: outletId
            })
        })
        .then(res => res.json())
        .then(result => {
            if (button) {
                button.textContent = originalText;
                button.disabled = false;
            }

            if (result.success) {
                handleStatusUpdateSuccess(result, actionType, barcode);
            } else {
                showError(result.error || 'Unknown error');
            }
        })
        .catch(error => {
            if (button) {
                button.textContent = originalText;
                button.disabled = false;
            }
            showError(`Network error: ${error.message}`);
        });
    }

    manualScanBtn.addEventListener('click', () => {
        const barcode = manualBarcodeInput.value.trim();
        if (barcode) {
            fetch(`../api/parcels/fetch_parcel.php?barcode=${encodeURIComponent(barcode)}`, {
                credentials: 'same-origin'
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        displayParcelDetails(data.parcel);
                        document.getElementById('errorMessage').style.display = 'none';
                    } else {
                        if (data.error && data.error.includes('company')) {
                            showError(`Access denied: This parcel does not belong to your company`);
                        } else {
                            showError(`Parcel not found for barcode: ${barcode}`);
                        }
                        clearParcelDetails();
                    }
                })
                .catch(error => {
                    showError(`Error fetching parcel: ${error.message}`);
                    clearParcelDetails();
                });
        } else {
            showError('Please enter a barcode.');
        }
    });

    const html5QrCodeScanner = new Html5QrcodeScanner("reader", { fps: 10, qrbox: { width: 250, height: 250 } });
    html5QrCodeScanner.render((decodedText) => {
        fetch(`../api/parcels/fetch_parcel.php?barcode=${encodeURIComponent(decodedText)}`, {
            credentials: 'same-origin'
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    displayParcelDetails(data.parcel);
                    document.getElementById('errorMessage').style.display = 'none';
                } else {
                    if (data.error && data.error.includes('company')) {
                        showError(`Access denied: This parcel does not belong to your company`);
                    } else {
                        showError(`Parcel not found for barcode: ${decodedText}`);
                    }
                    clearParcelDetails();
                }
            })
            .catch(error => {
                showError(`Error fetching parcel: ${error.message}`);
                clearParcelDetails();
            });
    });

    function fetchScanHistory() {
        if (!outletId) {
            showError('Missing outlet ID for fetching scan history.');
            return;
        }

        scanHistoryTableBody.innerHTML = `
            <tr>
                <td colspan="5" style="text-align: center; color: #718096; padding: 20px;">
                    <i class="fas fa-spinner fa-spin" style="margin-right: 8px;"></i>
                    Loading scan history...
                </td>
            </tr>
        `;

        fetch('../api/parcels/fetch_scan_history.php?outlet_id=' + encodeURIComponent(outletId), {
            credentials: 'same-origin'
        })
            .then(res => res.json())
            .then(data => {
                if (data.success === false) {
                    showError('Error fetching scan history: ' + (data.error || 'Unknown error'));
                    scanHistoryTableBody.innerHTML = `
                        <tr>
                            <td colspan="5" style="text-align: center; color: #e53e3e; padding: 20px;">
                                <i class="fas fa-exclamation-triangle" style="margin-right: 8px;"></i>
                                Error loading scan history
                            </td>
                        </tr>
                    `;
                    return;
                }

                if (Array.isArray(data) && data.length > 0) {
                    scanHistoryTableBody.innerHTML = '';
                    data.forEach(item => {
                        const row = document.createElement('tr');
                        const timeDisplay = formatTimeAgo(item.timestamp);

                        const recentIndicator = item.is_recent ?
                            '<span style="background: #48bb78; color: white; padding: 2px 6px; border-radius: 10px; font-size: 9px; margin-left: 5px;">NEW</span>' : '';

                        const changeIndicator = item.is_status_change ?
                            '<i class="fas fa-arrow-right" style="color: #4299e1; margin-right: 5px;"></i>' :
                            '<i class="fas fa-plus" style="color: #48bb78; margin-right: 5px;"></i>';

                        row.innerHTML = `
                            <td style="font-family: monospace; font-weight: 600; color: #2d3748;">
                                ${item.track_number || '-'}${recentIndicator}
                            </td>
                            <td>
                                <span style="padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase;
                                    background: ${getActionColor(item.action)};">
                                    ${item.action || 'Unknown'}
                                </span>
                            </td>
                            <td style="color: #4a5568; font-weight: 500;">
                                ${changeIndicator}${item.status_change || '-'}
                                ${item.action_description ? `<br><small style="color: #718096; font-style: italic;">${item.action_description}</small>` : ''}
                            </td>
                            <td style="color: #718096; font-size: 13px;" title="${item.timestamp || 'Unknown'}">${timeDisplay}</td>
                            <td style="color: #4a5568;">
                                ${item.staff_name || 'System'}
                                ${item.customer_info ? `<br><small style="color: #718096;">${item.customer_info}</small>` : ''}
                            </td>
                        `;
                        scanHistoryTableBody.appendChild(row);
                    });
                } else {
                    scanHistoryTableBody.innerHTML = `
                        <tr>
                            <td colspan="5" style="text-align: center; color: #718096; padding: 20px;">
                                <i class="fas fa-inbox" style="margin-right: 8px;"></i>
                                No scan activity recorded yet
                            </td>
                        </tr>
                    `;
                }
            })
            .catch(error => {
                showError(`Network error: ${error.message}`);
                scanHistoryTableBody.innerHTML = `
                    <tr>
                        <td colspan="5" style="text-align: center; color: #e53e3e; padding: 20px;">
                            <i class="fas fa-exclamation-triangle" style="margin-right: 8px;"></i>
                            Failed to load scan history
                        </td>
                    </tr>
                `;
            });
    }

    function getActionColor(action) {
        const actionColors = {
            'check-in': '#c6f6d5; color: #22543d',
            'pending': '#fff3cd; color: #744210',
            'check-out': '#fed7d7; color: #742a2a',
            'mark-delivered': '#bee3f8; color: #2a4365',
            'delivered': '#bee3f8; color: #2a4365',
            'registered': '#e6fffa; color: #234e52',
            'assignment': '#fdf2e9; color: #c05621',
            'arrival': '#e6fffa; color: #065f46',
            'scan': '#f0fff4; color: #22543d',
            'status-update': '#fef5e7; color: #744210'
        };
        return actionColors[action] || '#f7fafc; color: #4a5568';
    }

    function formatTimeAgo(timestamp) {
        if (!timestamp) return 'Unknown';

        const now = new Date();
        const time = new Date(timestamp);
        const diffInSeconds = Math.floor((now - time) / 1000);

        if (diffInSeconds < 60) return 'Just now';
        if (diffInSeconds < 3600) return Math.floor(diffInSeconds / 60) + 'm ago';
        if (diffInSeconds < 86400) return Math.floor(diffInSeconds / 3600) + 'h ago';
        if (diffInSeconds < 604800) return Math.floor(diffInSeconds / 86400) + 'd ago';

        return time.toLocaleDateString('en-US', {
            month: 'short', day: 'numeric',
            hour: '2-digit', minute: '2-digit'
        });
    }

    function showMessageBox(message) {
        alert(message);
    }

    let autoRefreshInterval;
    let isPageVisible = true;

    document.addEventListener('visibilitychange', function() {
        isPageVisible = !document.hidden;
        if (isPageVisible) {
            startAutoRefresh();
        } else {
            clearInterval(autoRefreshInterval);
        }
    });

    function startAutoRefresh() {
        clearInterval(autoRefreshInterval);

        autoRefreshInterval = setInterval(() => {
            if (isPageVisible) {
                fetchScanHistory();

                if (window.notificationSystemInstance && typeof window.notificationSystemInstance.refreshNotifications === 'function') {
                    window.notificationSystemInstance.refreshNotifications();
                }

                if (window.notificationSystem && typeof window.notificationSystem.refreshNotifications === 'function') {
                    window.notificationSystem.refreshNotifications();
                }

                if (typeof window.notificationSystemInstance.startFrequentRefresh === 'function') {
                    setTimeout(() => {
                        window.notificationSystemInstance.startFrequentRefresh();
                    }, 1500);
                }

                if (typeof window.notificationSystem.startFrequentRefresh === 'function') {
                    setTimeout(() => {
                        window.notificationSystem.startFrequentRefresh();
                    }, 1500);
                }

                setTimeout(() => {
                    fetch('../api/notifications/notifications.php?page=1&limit=1', { credentials: 'same-origin' })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success && data.unread_count !== undefined) {
                                const badge = document.querySelector('.notification-btn .badge');
                                if (badge) {
                                    if (data.unread_count > 0) {
                                        badge.textContent = data.unread_count > 99 ? '99+' : data.unread_count;
                                        badge.style.display = 'flex';
                                        badge.classList.add('new');
                                        setTimeout(() => badge.classList.remove('new'), 1000);
                                    } else {
                                        badge.style.display = 'none';
                                    }
                                }
                            }
                        })
                        .catch(error => console.log('Badge refresh error:', error));
                }, 1500);

                const historyTitle = document.querySelector('.scan-history h2');
                if (historyTitle) {
                    const icon = historyTitle.querySelector('i');
                    if (icon) {
                        icon.className = 'fas fa-sync-alt fa-spin';
                        setTimeout(() => {
                            icon.className = 'fas fa-history';
                        }, 1000);
                    }
                }
            }
        }, 30000);
    }

    function handleStatusUpdateSuccess(result, actionType, barcode) {
        const statusMessage = `âœ… Parcel ${barcode} status changed to "${result.new_status || actionType}"`;
        showError(statusMessage, 'success');

        fetchScanHistory();

        clearParcelDetails();

        console.log('âœ” Forcing notification refresh after status change...');

        if (window.notificationSystemInstance) {
            console.log('âœ” Using notificationSystemInstance.refreshNotifications()');
            setTimeout(() => {
                window.notificationSystemInstance.refreshNotifications();
            }, 500);

            setTimeout(() => {
                console.log('âœ” Force loading notifications with reset=true');
                window.notificationSystemInstance.loadNotifications(true);
            }, 1000);

            if (typeof window.notificationSystemInstance.startFrequentRefresh === 'function') {
                setTimeout(() => {
                    window.notificationSystemInstance.startFrequentRefresh();
                }, 1500);
            }
        }

        if (window.notificationSystem && typeof window.notificationSystem.refreshNotifications === 'function') {
            console.log('âœ” Using notificationSystem.refreshNotifications()');
            setTimeout(() => {
                window.notificationSystem.refreshNotifications();
            }, 500);

            setTimeout(() => {
                window.notificationSystem.loadNotifications(true);
            }, 1000);

            if (typeof window.notificationSystem.startFrequentRefresh === 'function') {
                setTimeout(() => {
                    window.notificationSystem.startFrequentRefresh();
                }, 1500);
            }
        }

        setTimeout(() => {
            console.log('âœ” Manual badge refresh via API call');
            fetch('../api/notifications/notifications.php?page=1&limit=1', { credentials: 'same-origin' })
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.unread_count !== undefined) {
                        console.log('âœ” Manual badge update - unread count:', data.unread_count);
                        const badge = document.querySelector('.notification-btn .badge');
                        if (badge) {
                            if (data.unread_count > 0) {
                                badge.textContent = data.unread_count > 99 ? '99+' : data.unread_count;
                                badge.style.display = 'flex';
                                badge.classList.add('new');
                                setTimeout(() => badge.classList.remove('new'), 1000);
                            } else {
                                badge.style.display = 'none';
                            }
                        }
                    }
                })
                .catch(error => console.log('Badge refresh error:', error));
        }, 1500);

        try {
            window.parent.postMessage({
                type: 'parcel_status_updated',
                barcode: barcode,
                old_status: result.old_status,
                new_status: result.new_status,
                action: actionType,
                timestamp: new Date().toISOString()
            }, window.location.origin);
        } catch (e) {
            console.log('Could not notify parent window:', e);
        }
    }

    fetchScanHistory();
    startAutoRefresh();

    setTimeout(() => {
        console.log('=== NOTIFICATION DEBUG INFO ===');
        console.log('NotificationSystem available:', typeof window.NotificationSystem !== 'undefined');
        console.log('Instance exists:', window.notificationSystemInstance ? 'YES' : 'NO');

        const notifBtn = document.querySelector('.notification-btn, #notificationButton');
        console.log('Notification button found:', notifBtn ? 'YES' : 'NO');
        if (notifBtn) {
            console.log('Button element:', notifBtn);
            console.log('Button parent:', notifBtn.parentElement);
        }

        const popup = document.getElementById('notificationsPopup');
        console.log('Popup exists:', popup ? 'YES' : 'NO');
        if (popup) {
            console.log('Popup element:', popup);
            console.log('Popup parent:', popup.parentElement);
        }
    }, 2000);
});
</script>
<script src="../assets/js/sidebar-toggle.js"></script>
<script src="../assets/js/notifications.js"></script>

<?php include __DIR__ . '/../includes/pwa_install_button.php'; ?>
<script src="../js/pwa-install.js"></script>
</body>
</html>
