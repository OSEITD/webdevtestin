<?php
require_once __DIR__ . '/includes/config.php';
session_start();

$trackingData = null;

if (isset($_SESSION['verified_tracking_data'])) {
    $trackingData = $_SESSION['verified_tracking_data'];
} else {
    header('Location: secure_tracking.html');
    exit();
}

$parcel = $trackingData['parcel'] ?? null;
$customerRole = $trackingData['customer_role'] ?? 'unknown';
$companyName = $trackingData['company'] ?? null;

if (!empty($parcel['id'])) {
    $supabaseUrl = SUPABASE_URL;
    $supabaseKey = SUPABASE_SERVICE_ROLE_KEY;
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                "apikey: $supabaseKey",
                "Authorization: Bearer $supabaseKey"
            ]
        ]
    ]);
    
    $url = "$supabaseUrl/rest/v1/parcels?id=eq.{$parcel['id']}&select=status";
    $response = @file_get_contents($url, false, $context);
    if ($response) {
        $data = json_decode($response, true);
        if (!empty($data[0]['status'])) {
            $parcel['status'] = $data[0]['status'];
            $_SESSION['verified_tracking_data']['parcel']['status'] = $data[0]['status'];
        }
    }
}

$customerName = 'Customer';

if ($customerRole === 'sender') {
    if (!empty($parcel['sender_name'])) {
        $customerName = $parcel['sender_name'];
    } elseif (!empty($parcel['global_sender_details']['full_name'])) {
        $customerName = $parcel['global_sender_details']['full_name'];
    } elseif (!empty($parcel['sender_details']['full_name'])) {
        $customerName = $parcel['sender_details']['full_name'];
    }
    
    if ($customerName === 'Customer' && !empty($parcel['global_sender_id'])) {
        $supabaseUrl = SUPABASE_URL;
        $supabaseKey = SUPABASE_SERVICE_ROLE_KEY;
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    "apikey: $supabaseKey",
                    "Authorization: Bearer $supabaseKey"
                ]
            ]
        ]);
        
        $url = "$supabaseUrl/rest/v1/global_customers?id=eq.{$parcel['global_sender_id']}&select=full_name";
        $response = @file_get_contents($url, false, $context);
        if ($response) {
            $data = json_decode($response, true);
            if (!empty($data[0]['full_name'])) {
                $customerName = $data[0]['full_name'];
            }
        }
    }
    
} elseif ($customerRole === 'receiver') {
    if (!empty($parcel['receiver_name'])) {
        $customerName = $parcel['receiver_name'];
    } elseif (!empty($parcel['global_receiver_details']['full_name'])) {
        $customerName = $parcel['global_receiver_details']['full_name'];
    } elseif (!empty($parcel['receiver_details']['full_name'])) {
        $customerName = $parcel['receiver_details']['full_name'];
    }
    
    if ($customerName === 'Customer' && !empty($parcel['global_receiver_id'])) {
        $supabaseUrl = SUPABASE_URL;
        $supabaseKey = SUPABASE_SERVICE_ROLE_KEY;
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    "apikey: $supabaseKey",
                    "Authorization: Bearer $supabaseKey"
                ]
            ]
        ]);
        
        $url = "$supabaseUrl/rest/v1/global_customers?id=eq.{$parcel['global_receiver_id']}&select=full_name";
        $response = @file_get_contents($url, false, $context);
        if ($response) {
            $data = json_decode($response, true);
            if (!empty($data[0]['full_name'])) {
                $customerName = $data[0]['full_name'];
            }
        }
    }
}

if (empty($companyName) && !empty($parcel['company_info']['company_name'])) {
    $companyName = $parcel['company_info']['company_name'];
}

if (!$parcel) {
    header('Location: secure_tracking.html');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WD Parcel Services - Tracking Details</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="assets/css/track_details.css">
    
    <style>
        .notification-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        
        .notification-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .notification-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(255, 255, 255, 0.3);
            transition: .4s;
            border-radius: 34px;
            border: 2px solid rgba(255, 255, 255, 0.5);
        }
        
        .notification-slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 2px;
            bottom: 2px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        .notification-switch input:checked + .notification-slider {
            background-color: #10b981;
            border-color: #10b981;
        }
        
        .notification-switch input:checked + .notification-slider:before {
            transform: translateX(26px);
        }
        
        .notification-switch input:disabled + .notification-slider {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        @media (max-width: 768px) {
            .notification-subscription-card > div {
                padding: 20px !important;
            }
        }
    </style>
    
    <style>
        .company-customer-header {
            background: linear-gradient(135deg, #2e0d2a 0%, #4A1C40 100%);
            color: white;
            padding: 1.5rem 2rem;
            margin: 0;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }
        
        .company-customer-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="50" cy="50" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>') repeat;
            opacity: 0.1;
            pointer-events: none;
        }
        
        .company-customer-content {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .company-branding {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .company-logo {
            width: 48px;
            height: 48px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .company-logo i {
            font-size: 24px;
            color: white;
        }
        
        .company-info h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .company-subdomain {
            font-size: 0.85rem;
            opacity: 0.9;
            font-weight: 500;
            margin-top: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .customer-info-section {
            text-align: right;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.5rem;
        }
        
        .customer-info {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.15);
            padding: 0.5rem 1rem;
            border-radius: 25px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .customer-info i {
            font-size: 1rem;
        }
        
        .customer-role-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            text-transform: capitalize;
        }
        
        @media (max-width: 768px) {
            .company-customer-header {
                padding: 1rem;
            }
            
            .company-customer-content {
                flex-direction: column;
                align-items: flex-start;
                text-align: left;
            }
            
            .customer-info-section {
                align-items: flex-start;
                text-align: left;
            }
            
            .company-info h1 {
                font-size: 1.25rem;
            }
        }
        
        .header {
            margin-top: 0;
        }
    </style>
</head>
<body>
    <div class="company-customer-header">
        <div class="company-customer-content">
            <div class="company-branding">
                <div class="company-logo">
                    <i class="fas fa-building"></i>
                </div>
                <div class="company-info">
                    <h1><?php echo htmlspecialchars($companyName ?? 'Parcel Services'); ?></h1>
                    <div class="company-subdomain">
                        <span>Customer Tracking Portal</span>
                    </div>
                </div>
            </div>
            
            <div class="customer-info-section">
                <div class="customer-info">
                    <i class="fas fa-user-circle"></i>
                    <span><?php echo htmlspecialchars($customerName); ?></span>
                </div>
                <span class="customer-role-badge">
                    <i class="fas fa-tag"></i> <?php echo ucfirst($customerRole); ?>
                </span>
            </div>
        </div>
    </div>
    
    <div class="driver-app" id="driverApp">
    <header class="header" style="display: none;">
        <div class="header-content">
            <div class="header-left">
                <img src="assets/img/logo.png" alt="WebDev" class="header-logo">
                <h1><i class="fas fa-shield-check"></i> Secure Tracking Portal</h1>
            </div>
            <div class="security-badge">
                <i class="fas fa-user-check"></i>
                <span>Verified Access</span>
            </div>
        </div>
    </header>

    <main class="main-content">
        <div style="max-width: 1200px; margin: 2rem auto; padding: 0 2rem;">
            <h2 style="font-size: 1.8rem; font-weight: 600; color: #1e293b; margin: 0 0 0.5rem 0;">
                Welcome, <?php echo htmlspecialchars($customerName); ?>
            </h2>
            <p style="color: #64748b; font-size: 1rem; margin: 0;">
                <i class="fas fa-shield-check" style="color: #10b981;"></i>
                Your identity has been verified as the <strong><?php echo ucfirst($customerRole); ?></strong>
            </p>
        </div>
        <div class="verified-notice" style="display: none;">
            <i class="fas fa-check-circle icon"></i>
            <div>
                <h3>Identity Verified Successfully</h3>
                <p>You are viewing this parcel as the <strong><?php echo ucfirst($customerRole); ?></strong>
                <?php if ($companyName): ?>
                    • Company: <?php echo htmlspecialchars($companyName); ?>
                <?php endif; ?>
                </p>
            </div>
        </div>

        <div class="tracking-card">
            <div class="card-header">
                <div class="tracking-number">
                    <?php echo htmlspecialchars($parcel['track_number']); ?>
                </div>
                <div class="status-display">
                    <span class="status-badge status-<?php echo strtolower(str_replace([' ', '_'], '-', $parcel['status'] ?? 'default')); ?>">
                        <?php echo ucwords(str_replace('_', ' ', $parcel['status'] ?? 'Unknown')); ?>
                    </span>
                    <?php if (!empty($parcel['delivery_date'])): ?>
                        <span class="text-success">
                            <i class="fas fa-calendar-check"></i>
                            Delivered: <?php echo date('M j, Y', strtotime($parcel['delivery_date'])); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card-body">

                <div class="detail-section">
                    <h4><i class="fas fa-images"></i> Parcel Images</h4>
                    <?php if (!empty($parcel['photo_urls']) && is_array($parcel['photo_urls'])): ?>
                        <div class="image-gallery">
                            <?php foreach ($parcel['photo_urls'] as $index => $imageUrl): ?>
                                <?php if (!empty($imageUrl)): ?>
                                    <div class="image-item">
                                        <img src="<?php echo htmlspecialchars($imageUrl); ?>"
                                             alt="Parcel Image <?php echo $index + 1; ?>"
                                             onclick="openImageModal('<?php echo htmlspecialchars($imageUrl); ?>')">
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <?php if (count(array_filter($parcel['photo_urls'])) === 0): ?>
                            <p style="margin-top: 12px; color: var(--text-light);">No images available for this parcel.</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p style="margin-top: 12px; color: var(--text-light);">No images available for this parcel.</p>
                    <?php endif; ?>
                </div>

                <div class="detail-section">
                    <h4><i class="fas fa-route"></i> Delivery Timeline</h4>
                    <?php if (!empty($parcel['delivery_events']) && is_array($parcel['delivery_events'])): ?>
                    <div class="timeline">
                        <?php foreach ($parcel['delivery_events'] as $event): ?>
                            <div class="timeline-item">
                                <div class="timeline-marker">
                                    <i class="fas fa-circle"></i>
                                </div>
                                <div class="timeline-content">
                                    <div class="timeline-status"><?php echo ucwords(str_replace('_', ' ', $event['status'])); ?></div>
                                    <div class="timeline-date"><?php echo isset($event['timestamp']) ? date('M j, Y g:i A', strtotime($event['timestamp'])) : 'No timestamp'; ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p style="margin-top: 12px; color: var(--text-light);">No delivery timeline available for this parcel.</p>
                    <?php endif; ?>
                </div>

                <div class="details-grid">
                    <div class="detail-section">
                        <h4><i class="fas fa-box"></i> Parcel Details</h4>
                        
                        <?php if ($customerRole === 'sender' && !empty($parcel['receiver_name'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">Recipient</span>
                            <span class="detail-value"><?php echo htmlspecialchars($parcel['receiver_name']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($customerRole === 'receiver' && !empty($parcel['sender_name'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">Sender</span>
                            <span class="detail-value"><?php echo htmlspecialchars($parcel['sender_name']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($parcel['package_details'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">Package</span>
                            <span class="detail-value"><?php echo htmlspecialchars($parcel['package_details']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($parcel['parcel_weight'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">Weight</span>
                            <span class="detail-value"><?php echo htmlspecialchars($parcel['parcel_weight']); ?> kg</span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($parcel['parcel_length']) || !empty($parcel['parcel_width']) || !empty($parcel['parcel_height'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">Dimensions</span>
                            <span class="detail-value">
                                <?php echo ($parcel['parcel_length'] ?? '?') . ' × ' . 
                                          ($parcel['parcel_width'] ?? '?') . ' × ' . 
                                          ($parcel['parcel_height'] ?? '?') . ' cm'; ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($parcel['service_type'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">Service Type</span>
                            <span class="detail-value"><?php echo ucwords($parcel['service_type']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="detail-section">
                        <h4><i class="fas fa-money-bill-wave"></i> Financial Details</h4>

                        <?php
                        $hasFinancialData = !empty($parcel['parcel_value']) || !empty($parcel['delivery_fee']) ||
                                           !empty($parcel['insurance_amount']) || !empty($parcel['cod_amount']) ||
                                           !empty($parcel['payment_status']);
                        ?>

                        <?php if ($hasFinancialData): ?>
                            <?php if (!empty($parcel['parcel_value'])): ?>
                            <div class="detail-item">
                                <span class="detail-label">Declared Value</span>
                                <span class="detail-value">ZMW <?php echo number_format($parcel['parcel_value'], 2); ?></span>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($parcel['delivery_fee'])): ?>
                            <div class="detail-item">
                                <span class="detail-label">Delivery Fee</span>
                                <span class="detail-value">ZMW <?php echo number_format($parcel['delivery_fee'], 2); ?></span>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($parcel['insurance_amount'])): ?>
                            <div class="detail-item">
                                <span class="detail-label">Insurance</span>
                                <span class="detail-value">ZMW <?php echo number_format($parcel['insurance_amount'], 2); ?></span>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($parcel['cod_amount'])): ?>
                            <div class="detail-item">
                                <span class="detail-label">COD Amount</span>
                                <span class="detail-value">ZMW <?php echo number_format($parcel['cod_amount'], 2); ?></span>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($parcel['payment_status'])): ?>
                            <div class="detail-item">
                                <span class="detail-label">Payment Status</span>
                                <span class="detail-value">
                                    <span class="status-badge status-<?php echo strtolower($parcel['payment_status']); ?>">
                                        <?php echo ucfirst($parcel['payment_status']); ?>
                                    </span>
                                </span>
                            </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <p style="margin-top: 12px; color: var(--text-light);">No financial details available for this parcel.</p>
                        <?php endif; ?>
                    </div>

                    <div class="detail-section">
                        <h4><i class="fas fa-truck"></i> Delivery Information</h4>
                        
                        <?php if (!empty($parcel['origin_outlet'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">Origin Outlet</span>
                            <span class="detail-value">
                                <?php echo htmlspecialchars($parcel['origin_outlet']['outlet_name'] ?? 'Unknown Outlet'); ?>
                                <?php if (!empty($parcel['origin_outlet']['location'])): ?>
                                    <br><small><?php echo htmlspecialchars($parcel['origin_outlet']['location']); ?></small>
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($parcel['destination_outlet'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">Destination Outlet</span>
                            <span class="detail-value">
                                <?php echo htmlspecialchars($parcel['destination_outlet']['outlet_name'] ?? 'Unknown Outlet'); ?>
                                <?php if (!empty($parcel['destination_outlet']['location'])): ?>
                                    <br><small><?php echo htmlspecialchars($parcel['destination_outlet']['location']); ?></small>
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($customerRole === 'receiver' && !empty($parcel['receiver_address'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">Delivery Address</span>
                            <span class="detail-value"><?php echo htmlspecialchars($parcel['receiver_address']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($parcel['driver_info'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">Assigned Driver</span>
                            <span class="detail-value">
                                <?php echo htmlspecialchars($parcel['driver_info']['driver_name'] ?? 'Not Assigned'); ?>
                                <?php if (!empty($parcel['driver_info']['driver_phone'])): ?>
                                    <br><small>📱 <?php echo htmlspecialchars($parcel['driver_info']['driver_phone']); ?></small>
                                <?php endif; ?>
                                <?php if (!empty($parcel['driver_info']['status'])): ?>
                                    <br><small>Status: <?php echo ucfirst($parcel['driver_info']['status']); ?></small>
                                <?php endif; ?>
                                <?php if (isset($parcel['driver_info']['gps_status'])): ?>
                                    <?php 
                                    $gpsStatus = $parcel['driver_info']['gps_status'];
                                    $statusColors = [
                                        'live' => '#10b981',
                                        'recent' => '#f59e0b', 
                                        'stale' => '#ef4444',
                                        'very_old' => '#6b7280',
                                        'no_data' => '#6b7280',
                                        'error' => '#ef4444'
                                    ];
                                    $color = $statusColors[$gpsStatus['status']] ?? '#6b7280';
                                    ?>
                                    <br><small>
                                        <span style="color: <?php echo $color; ?>;">
                                            📍 <?php echo htmlspecialchars($gpsStatus['message']); ?>
                                        </span>
                                    </small>
                                <?php elseif (isset($parcel['driver_info']['gps_available'])): ?>
                                    <br><small>
                                        <?php if ($parcel['driver_info']['gps_available']): ?>
                                            <span style="color: #10b981;">📍 GPS Tracking Available</span>
                                        <?php else: ?>
                                            <span style="color: #f59e0b;">📍 GPS Tracking Unavailable</span>
                                        <?php endif; ?>
                                    </small>
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($parcel['estimated_delivery_date'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">Estimated Delivery</span>
                            <span class="detail-value"><?php echo date('M j, Y', strtotime($parcel['estimated_delivery_date'])); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="detail-section">
                        <h4><i class="fas fa-clock"></i> Important Dates</h4>
                        
                        <div class="detail-item">
                            <span class="detail-label">Created</span>
                            <span class="detail-value"><?php echo date('M j, Y g:i A', strtotime($parcel['created_at'])); ?></span>
                        </div>
                        
                        <?php if (!empty($parcel['updated_at']) && $parcel['updated_at'] !== $parcel['created_at']): ?>
                        <div class="detail-item">
                            <span class="detail-label">Last Updated</span>
                            <span class="detail-value"><?php echo date('M j, Y g:i A', strtotime($parcel['updated_at'])); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($parcel['delivered_at'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">Delivered</span>
                            <span class="detail-value"><?php echo date('M j, Y g:i A', strtotime($parcel['delivered_at'])); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($parcel['delivery_date'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">Delivery Date</span>
                            <span class="detail-value"><?php echo date('M j, Y', strtotime($parcel['delivery_date'])); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($parcel['special_instructions'])): ?>
                <div class="detail-section">
                    <h4><i class="fas fa-info-circle"></i> Special Instructions</h4>
                    <p style="margin-top: 12px; line-height: 1.6;">
                        <?php echo htmlspecialchars($parcel['special_instructions']); ?>
                    </p>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($parcel['payment_info'])): ?>
                <div class="detail-section">
                    <h4><i class="fas fa-credit-card"></i> Payment Information</h4>
                    
                    <?php if (!empty($parcel['payment_info']['method'])): ?>
                    <div class="detail-item">
                        <span class="detail-label">Payment Method</span>
                        <span class="detail-value"><?php echo ucwords(str_replace('_', ' ', $parcel['payment_info']['method'])); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($parcel['payment_info']['amount'])): ?>
                    <div class="detail-item">
                        <span class="detail-label">Amount Paid</span>
                        <span class="detail-value">ZMW <?php echo number_format($parcel['payment_info']['amount'], 2); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($parcel['payment_info']['transaction_ref'])): ?>
                    <div class="detail-item">
                        <span class="detail-label">Transaction Reference</span>
                        <span class="detail-value"><?php echo htmlspecialchars($parcel['payment_info']['transaction_ref']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($parcel['payment_info']['paid_at'])): ?>
                    <div class="detail-item">
                        <span class="detail-label">Payment Date</span>
                        <span class="detail-value"><?php echo date('M j, Y g:i A', strtotime($parcel['payment_info']['paid_at'])); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($parcel['company_info'])): ?>
                <div class="detail-section">
                    <h4><i class="fas fa-building"></i> Service Provider</h4>
                    
                    <div class="detail-item">
                        <span class="detail-label">Company</span>
                        <span class="detail-value"><?php echo htmlspecialchars($parcel['company_info']['company_name']); ?></span>
                    </div>
                    
                    <?php if (!empty($parcel['company_info']['contact_phone'])): ?>
                    <div class="detail-item">
                        <span class="detail-label">Contact Phone</span>
                        <span class="detail-value">📱 <?php echo htmlspecialchars($parcel['company_info']['contact_phone']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($parcel['company_info']['contact_email'])): ?>
                    <div class="detail-item">
                        <span class="detail-label">Contact Email</span>
                        <span class="detail-value">✉️ <?php echo htmlspecialchars($parcel['company_info']['contact_email']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="notification-subscription-card" style="max-width: 1200px; margin: 0 auto 2rem; padding: 0 2rem;">
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 24px; border-radius: 16px; box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);">
                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px;">
                    <div style="flex: 1; min-width: 250px;">
                        <h3 style="color: white; margin: 0 0 8px 0; display: flex; align-items: center; gap: 10px; font-size: 20px;">
                            <i class="fas fa-bell"></i>
                            <span>Push Notifications</span>
                        </h3>
                        <p style="color: rgba(255,255,255,0.9); margin: 0; font-size: 14px; line-height: 1.5;">
                            Get instant updates about <strong><?php echo htmlspecialchars($parcel['track_number']); ?></strong> on your phone
                        </p>
                        <div id="notificationStatus" style="margin-top: 12px; padding: 10px; background: rgba(255,255,255,0.15); border-radius: 8px; font-size: 13px; display: none;"></div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <label class="notification-switch">
                            <input type="checkbox" id="enableNotifications">
                            <span class="notification-slider"></span>
                        </label>
                        <button id="subscribeBtn" class="btn" style="background: white; color: #667eea; border: 2px solid white; padding: 12px 24px; font-weight: 600; display: none;">
                            <i class="fas fa-bell"></i>
                            <span>Enable Now</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="actions-section">
            <?php 
            $showGPSButton = false;
            $gpsButtonText = 'Live GPS Tracking';
            $gpsButtonClass = 'btn-gps';
            
            if (!empty($parcel['driver_info']['gps_status'])) {
                $gpsStatus = $parcel['driver_info']['gps_status']['status'];
                if (in_array($gpsStatus, ['live', 'recent', 'stale'])) {
                    $showGPSButton = true;
                    if ($gpsStatus === 'live') {
                        $gpsButtonText = '🔴 Live GPS Tracking';
                    } elseif ($gpsStatus === 'recent') {
                        $gpsButtonText = '🟡 Recent GPS Location';
                    } else {
                        $gpsButtonText = '🟠 View Last GPS Location';
                        $gpsButtonClass = 'btn-secondary';
                    }
                }
            } elseif (!empty($parcel['driver_info']['gps_available']) && $parcel['driver_info']['gps_available']) {
                $showGPSButton = true;
            }
            ?>
            
            <?php if ($showGPSButton): ?>
            <button class="btn <?php echo $gpsButtonClass; ?>" onclick="openGPSTracking()">
                <i class="fas fa-map-marker-alt"></i>
                <?php echo $gpsButtonText; ?>
            </button>
            <?php endif; ?>
            
            <button class="btn btn-primary" onclick="getTrackingHistory()">
                <i class="fas fa-history"></i>
                View Tracking History
            </button>
            
            <a href="secure_tracking.html" class="btn btn-secondary">
                <i class="fas fa-search"></i>
                Track Another Parcel
            </a>
            
            <button class="btn btn-secondary" onclick="window.print()">
                <i class="fas fa-print"></i>
                Print Details
            </button>
        </div>

        <div class="privacy-notice">
            <strong>Privacy Notice:</strong> 
            The information shown is filtered based on your verified identity as the 
            <?php echo $customerRole; ?>. Some details may be hidden for privacy and security reasons.
        </div>
    </main>

    <div id="imageModal" class="image-modal">
        <span class="image-modal-close">&times;</span>
        <div class="image-modal-content">
            <img id="modalImage" src="" alt="Parcel Image">
        </div>
    </div>

    <script>
        function openImageModal(imageUrl) {
            const modal = document.getElementById('imageModal');
            const modalImg = document.getElementById('modalImage');
            
            modal.style.display = 'block';
            modalImg.src = imageUrl;
        }

        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('imageModal');
            const closeBtn = document.querySelector('.image-modal-close');
            
            closeBtn.addEventListener('click', function() {
                modal.style.display = 'none';
            });
            
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.style.display = 'none';
                }
            });
            
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && modal.style.display === 'block') {
                    modal.style.display = 'none';
                }
            });
        });

        function openGPSTracking() {
            const parcelId = <?php echo json_encode($parcel['id']); ?>;
            const gpsUrl = `gps_tracking.html?parcel_id=${parcelId}`;
            
            window.open(gpsUrl, '_blank', 'width=1200,height=800,scrollbars=yes,resizable=yes');
        }

        async function getTrackingHistory() {
            try {
                const btn = event.target;
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
                btn.disabled = true;
                
                const deliveryEvents = <?php echo json_encode($parcel['delivery_events'] ?? []); ?>;
                
                if (deliveryEvents.length > 0) {
                    let historyHtml = '<h3>Complete Tracking History</h3><ul>';
                    deliveryEvents.forEach(event => {
                        const date = new Date(event.timestamp).toLocaleString();
                        const status = event.status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                        historyHtml += `<li><strong>${date}</strong>: ${status}</li>`;
                    });
                    historyHtml += '</ul>';
                    
                    // Create a modal-like display for the history
                    showTrackingHistoryModal(historyHtml);
                } else {
                    alert('No detailed tracking history available for this parcel.');
                }
                
                // Restore button
                btn.innerHTML = originalText;
                btn.disabled = false;
                
            } catch (error) {
                console.error('Error fetching tracking history:', error);
                alert('Error loading tracking history. Please try again.');
                
                const btn = event.target;
                btn.innerHTML = '<i class="fas fa-history"></i> View Tracking History';
                btn.disabled = false;
            }
        }

        function showTrackingHistoryModal(content) {
            const overlay = document.createElement('div');
            overlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 1000;
                display: flex;
                align-items: center;
                justify-content: center;
            `;
            
            const modal = document.createElement('div');
            modal.style.cssText = `
                background: white;
                padding: 30px;
                border-radius: 16px;
                max-width: 600px;
                max-height: 80%;
                overflow-y: auto;
                box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            `;
            
            modal.innerHTML = `
                ${content}
                <button onclick="this.closest('.history-modal-overlay').remove()" 
                        style="margin-top: 20px; padding: 10px 20px; background: #1e40af; color: white; border: none; border-radius: 8px; cursor: pointer;">
                    Close
                </button>
            `;
            
            overlay.className = 'history-modal-overlay';
            overlay.appendChild(modal);
            document.body.appendChild(overlay);
            
            overlay.addEventListener('click', function(e) {
                if (e.target === overlay) {
                    overlay.remove();
                }
            });
        }

        function enableAutoRefresh() {
            setInterval(async function() {
                try {
                    
                    // You could make an AJAX call here to check for updates
                    // and refresh the page or update specific elements if changes are detected
                    
                } catch (error) {
                    console.error('Error checking for updates:', error);
                }
            }, 60000); // Check every minute
        }

        
        
        const VAPID_PUBLIC_KEY = '<?php echo htmlspecialchars(VAPID_PUBLIC_KEY); ?>';
        const TRACKING_NUMBER = <?php echo json_encode($parcel['track_number']); ?>;
        const USER_ROLE = <?php echo json_encode($customerRole); ?>;
        
        let serviceWorkerRegistration = null;
        let pushSubscription = null;
        
        document.addEventListener('DOMContentLoaded', async function() {
            await initializePushNotifications();
        });
        
        async function initializePushNotifications() {
            const notificationToggle = document.getElementById('enableNotifications');
            const subscribeBtn = document.getElementById('subscribeBtn');
            const notificationStatus = document.getElementById('notificationStatus');
            
            if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                updateNotificationStatus('❌ Push notifications are not supported by your browser', '#ef4444');
                notificationToggle.disabled = true;
                return;
            }
            
            const notificationEnabled = window.opener?.notificationPreference?.enabled || false;
            
            try {
                serviceWorkerRegistration = await navigator.serviceWorker.register('./notification-service-worker.js', {
                    scope: './'
                });
                await navigator.serviceWorker.ready;
                
                pushSubscription = await serviceWorkerRegistration.pushManager.getSubscription();
                
                if (pushSubscription) {
                    notificationToggle.checked = true;
                    updateNotificationStatus('✅ Notifications enabled for this parcel', '#10b981');
                } else if (Notification.permission === 'granted') {
                    // Permission granted but not subscribed yet
                    if (notificationEnabled) {
                        // Auto-subscribe if user enabled it on tracking page
                        await subscribeToPushNotifications();
                    }
                } else if (Notification.permission === 'denied') {
                    updateNotificationStatus('🚫 Notifications blocked. Enable in browser settings.', '#ef4444');
                    notificationToggle.disabled = true;
                }
            } catch (error) {
                console.error('Error initializing notifications:', error);
                updateNotificationStatus('⚠️ Error initializing notifications', '#f59e0b');
            }
            
            notificationToggle.addEventListener('change', async (e) => {
                if (e.target.checked) {
                    await subscribeToPushNotifications();
                } else {
                    await unsubscribeFromPushNotifications();
                }
            });
        }
        
        async function subscribeToPushNotifications() {
            const notificationToggle = document.getElementById('enableNotifications');
            
            try {
                updateNotificationStatus('🔄 Requesting permission...', '#f59e0b');
                
                if (Notification.permission !== 'granted') {
                    const permission = await Notification.requestPermission();
                    if (permission !== 'granted') {
                        notificationToggle.checked = false;
                        updateNotificationStatus('❌ Permission denied. Enable in browser settings.', '#ef4444');
                        return;
                    }
                }
                
                updateNotificationStatus('🔄 Subscribing to notifications...', '#f59e0b');
                
                const applicationServerKey = urlBase64ToUint8Array(VAPID_PUBLIC_KEY);
                
                pushSubscription = await serviceWorkerRegistration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: applicationServerKey
                });
                
                console.log('Push subscription:', pushSubscription);
                
                const response = await fetch('api/save_push_subscription.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        subscription: pushSubscription.toJSON(),
                        tracking_number: TRACKING_NUMBER,
                        user_role: USER_ROLE
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    notificationToggle.checked = true;
                    updateNotificationStatus('✅ Notifications enabled! You\'ll receive updates about this parcel.', '#10b981');
                    
                    // Show test notification
                    new Notification('🔔 Notifications Enabled!', {
                        body: `You'll receive updates about parcel ${TRACKING_NUMBER}`,
                        icon: 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text y=".9em" font-size="90">📦</text></svg>',
                        tag: 'subscription-success'
                    });
                } else {
                    throw new Error(result.error || 'Failed to save subscription');
                }
                
            } catch (error) {
                console.error('Subscription error:', error);
                notificationToggle.checked = false;
                updateNotificationStatus('❌ Error: ' + error.message, '#ef4444');
            }
        }
        
        async function unsubscribeFromPushNotifications() {
            try {
                if (pushSubscription) {
                    await pushSubscription.unsubscribe();
                    pushSubscription = null;
                }
                updateNotificationStatus('🔕 Notifications disabled', '#94a3b8');
            } catch (error) {
                console.error('Unsubscribe error:', error);
                updateNotificationStatus('⚠️ Error unsubscribing', '#f59e0b');
            }
        }
        
        function updateNotificationStatus(message, color) {
            const notificationStatus = document.getElementById('notificationStatus');
            notificationStatus.innerHTML = message;
            notificationStatus.style.display = 'block';
            notificationStatus.style.background = color + '33';
            notificationStatus.style.color = 'white';
        }
        
        function urlBase64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding)
                .replace(/\-/g, '+')
                .replace(/_/g, '/');
            
            const rawData = window.atob(base64);
            const outputArray = new Uint8Array(rawData.length);
            
            for (let i = 0; i < rawData.length; ++i) {
                outputArray[i] = rawData.charCodeAt(i);
            }
            return outputArray;
        }
        
        function printDetails() {
            const elementsToHide = document.querySelectorAll('.actions-section, .privacy-notice, .security-badge');
            elementsToHide.forEach(el => el.style.display = 'none');
            
            window.print();
            
            setTimeout(() => {
                elementsToHide.forEach(el => el.style.display = '');
            }, 100);
        }

        document.addEventListener('DOMContentLoaded', function() {
            const printBtn = document.querySelector('button[onclick="window.print()"]');
            if (printBtn) {
                printBtn.onclick = printDetails;
            }
        });
    </script>
</body>
</html>