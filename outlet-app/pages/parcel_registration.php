<?php
require_once '../includes/auth_guard.php';
require_once '../api/payments/lenco_config.php';

// Prevent caching of this page
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$current_user = getCurrentUser();

// Get Lenco payment configuration
$lencoPublicKey = getLencoPublicKey();
$lencoWidgetUrl = getLencoWidgetUrl();
$lencoCurrency = LENCO_CURRENCY;
$lencoEnv = LENCO_ENV;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Register New Parcel</title>

    <!-- Poppins Font -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <!-- Styles moved to parcel_registration.css -->
    
    <!-- Styles -->
    <link rel="stylesheet" href="../css/outlet-dashboard.css">
    <link rel="stylesheet" href="../css/parcel_registration.css?v=<?php echo time(); ?>">
    
    <!-- Lenco Payment Widget (Sandbox) - loaded async so it doesn't block rendering -->
    <script src="<?php echo htmlspecialchars($lencoWidgetUrl); ?>" async></script>
    
    <!-- Lenco Configuration for JavaScript -->
    <script>
        window.LENCO_CONFIG = {
            publicKey: '<?php echo htmlspecialchars($lencoPublicKey); ?>',
            currency: '<?php echo htmlspecialchars($lencoCurrency); ?>',
            environment: '<?php echo htmlspecialchars($lencoEnv); ?>',
            verifyUrl: '../api/payments/verify_lenco.php'
        };
    </script>
    
    <!-- Error Handler for Browser Extension Issues -->
    <script src="../assets/js/error-handler.js"></script>
</head>

<body class="bg-gray-100 min-h-screen">

    <div class="mobile-dashboard">

        
         <?php include '../includes/navbar.php'; ?>
        <?php include '../includes/sidebar.php'; ?>

        <!-- Main Content Area for Create New Parcel -->
        <main class="main-content">
            <div class="content-container">
                <!-- Page Header -->
                <div class="page-header">
                    <h1><i class="fas fa-box-open"></i> Register New Parcel</h1>
                    <p class="subtitle">Complete the form below to register a new parcel for delivery tracking</p>
                </div>

                <form id="newParcelForm" novalidate enctype="multipart/form-data">
                    <input type="hidden" id="originOutletId" name="originOutletId" value="<?php echo htmlspecialchars($_SESSION['outlet_id'] ?? ''); ?>">
                    <input type="hidden" id="companyId" name="companyId" value="<?php echo htmlspecialchars($_SESSION['company_id'] ?? ''); ?>">
                    
                    <!-- Left Column -->
                    <div class="form-column-left">
                        <!-- Sender Information -->
                        <div class="form-section">
                            <h3><i class="fas fa-user"></i> Sender Information</h3>
                            
                            <!-- Sender NRC (First Field) -->
                            <div class="form-group">
                                <label for="senderNRC"><i class="fas fa-id-card"></i> NRC Number</label>
                                <input type="text" id="senderNRC" name="senderNRC" placeholder="e.g., 123456/78/9" class="form-control">
                                <small class="form-help">National Registration Card number</small>
                            </div>

                            <div class="form-group">
                                <label for="senderName">Sender Name <span class="required">*</span></label>
                                <input type="text" id="senderName" name="senderName" placeholder="Enter sender's full name" required>
                            </div>

                            <div class="form-group">
                                <label for="senderPhone">Sender Phone <span class="required">*</span></label>
                                <input type="tel" id="senderPhone" name="senderPhone" placeholder="+260 XXX XXX XXX" required>
                            </div>

                            <div class="form-group">
                                <label for="senderEmail">Sender Email</label>
                                <input type="email" id="senderEmail" name="senderEmail" placeholder="sender@example.com">
                            </div>

                            <div class="form-group">
                                <label for="senderAddress">Sender Address <span class="required">*</span></label>
                                <textarea id="senderAddress" name="senderAddress" placeholder="Enter sender's complete address" required></textarea>
                            </div>
                        </div>

                        <!-- Parcel Details -->
                        <div class="form-section">
                            <h3><i class="fas fa-box"></i> Parcel Details</h3>
                            
                            <div class="form-group">
                                <label for="trackingNumber">Tracking Number</label>
                                <input type="text" id="trackingNumber" name="trackingNumber" placeholder="Auto-generated" readonly>
                                <small class="form-help">Will be auto-generated upon registration</small>
                            </div>

                            <div class="form-group">
                                <label for="itemDescription">Item Description <span class="required">*</span></label>
                                <textarea id="itemDescription" name="itemDescription" placeholder="Describe the item(s) in the parcel" required></textarea>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="parcelWeight">Weight (kg) <span class="required">*</span></label>
                                    <input type="number" id="parcelWeight" name="parcelWeight" step="0.1" min="0.1" placeholder="0.0" required>
                                </div>

                                <div class="form-group">
                                    <label for="dimensions">Dimensions (cm)</label>
                                    <input type="text" id="dimensions" name="dimensions" placeholder="L x W x H">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="value">Declared Value (K)</label>
                                <input type="number" id="value" name="value" step="0.01" min="0" placeholder="0.00">
                                <small class="form-help">For insurance purposes</small>
                            </div>

                            <div class="form-group">
                                <label for="specialInstructions">Special Instructions</label>
                                <textarea id="specialInstructions" name="specialInstructions" placeholder="Any special handling instructions"></textarea>
                            </div>
                        </div>

                        <!-- Photo Upload & Submit - Full Width -->
                        <div class="form-section photo-upload-section">
                            <h3><i class="fas fa-camera"></i> Parcel Photos & Submit</h3>
                            
                            <div class="photo-upload-grid">
                                <div class="upload-area">
                                    <div class="form-group">
                                        <label for="parcelPhotos"><i class="fas fa-images"></i> Upload Parcel Photos</label>
                                        <small class="form-help">Max 5 photos, 5MB each. Supported: JPG, PNG, GIF</small>
                                        <input type="file" id="parcelPhotos" name="parcelPhotos[]" accept="image/*" multiple style="display:none;">
                                        <div id="uploadZone" class="upload-zone">
                                            <span class="upload-icon"><i class="fas fa-cloud-upload-alt"></i></span>
                                            <p id="uploadZoneText">Click here or drag and drop images</p>
                                            <small>Supported formats: JPG, PNG, GIF</small>
                                        </div>
                                    </div>
                                    <button type="button" id="cameraBtn" class="btn-secondary">
                                        <i class="fas fa-camera"></i> Take Photo
                                    </button>
                                    <div id="photoPreview" class="photo-preview"></div>
                                </div>
                                
                                <div class="submit-area">
                                    <!-- Form Actions -->
                                    <div class="form-actions">
                                        <button type="button" class="btn-secondary" onclick="window.history.back()">
                                            <i class="fas fa-arrow-left"></i> Cancel
                                        </button>
                                        <button type="submit" class="btn-primary" id="submitBtn">
                                            <i class="fas fa-paper-plane"></i> Register Parcel
                                        </button>
                                    </div>
                                    
                                    <div class="submit-info">
                                        <p><i class="fas fa-info-circle"></i> Please review all information before submitting.</p>
                                        <p><i class="fas fa-shield-alt"></i> Your parcel will be assigned a tracking number upon registration.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                    
                    <!-- Right Column -->
                    <div class="form-column-right">
                        <!-- Recipient Information -->
                        <div class="form-section">
                            <h3><i class="fas fa-user-check"></i> Recipient Information</h3>
                            
                            <!-- Recipient NRC (First Field) -->
                            <div class="form-group">
                                <label for="recipientNRC"><i class="fas fa-id-card"></i> NRC Number</label>
                                <input type="text" id="recipientNRC" name="recipientNRC" placeholder="e.g., 654321/87/6" class="form-control">
                                <small class="form-help">Recipient's National Registration Card number</small>
                            </div>

                            <div class="form-group">
                                <label for="recipientName">Recipient Name <span class="required">*</span></label>
                                <input type="text" id="recipientName" name="recipientName" placeholder="Enter recipient's full name" required>
                            </div>

                            <div class="form-group">
                                <label for="recipientPhone">Recipient Phone <span class="required">*</span></label>
                                <input type="tel" id="recipientPhone" name="recipientPhone" placeholder="+260 XXX XXX XXX" required>
                            </div>

                            <div class="form-group">
                                <label for="recipientEmail">Recipient Email</label>
                                <input type="email" id="recipientEmail" name="recipientEmail" placeholder="recipient@example.com">
                            </div>

                            <div class="form-group">
                                <label for="destinationOutletId">Destination Outlet <span class="required">*</span></label>
                                <select id="destinationOutletId" name="destinationOutletId" required>
                                    <option value="">Select destination outlet</option>
                                    <!-- Will be populated via JavaScript -->
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="recipientAddress">Delivery Address <span class="required">*</span></label>
                                <textarea id="recipientAddress" name="recipientAddress" placeholder="Enter complete delivery address" required></textarea>
                            </div>
                        </div>

                        <!-- Financial Information -->
                        <?php
                            // Fetch company commission and billing config for fee calculation
                            $companyCommission = 0;
                            $billingConfig = null;
                            require_once __DIR__ . '/../includes/supabase-helper.php';
                            try {
                                $sup = new SupabaseHelper();
                                $cid = $_SESSION['company_id'] ?? null;
                                if ($cid) {
                                    // Fetch commission rate
                                    $comp = $sup->get('companies', 'id=eq.' . urlencode($cid) . '&select=commission_rate');
                                    if (!empty($comp) && isset($comp[0]['commission_rate'])) {
                                        $companyCommission = floatval($comp[0]['commission_rate']);
                                    } else {
                                        $companyCommission = floatval(getenv('DEFAULT_COMMISSION_RATE') ?: 0);
                                    }
                                    // Fetch billing config for auto-calculation
                                    $billingResp = $sup->get('billing_configs', 'company_id=eq.' . urlencode($cid));
                                    if (!empty($billingResp) && isset($billingResp[0])) {
                                        $billingConfig = $billingResp[0];
                                    }
                                } else {
                                    $companyCommission = floatval(getenv('DEFAULT_COMMISSION_RATE') ?: 0);
                                }
                            } catch (Exception $e) {
                                error_log('Failed to fetch company financial config for UI: ' . $e->getMessage());
                                $companyCommission = floatval(getenv('DEFAULT_COMMISSION_RATE') ?: 0);
                            }
                            // Prepare billing config JSON for JavaScript
                            $defaultBillingConfig = [
                                'base_rate' => 1000,
                                'rate_per_kg' => 200,
                                'volumetric_divisor' => 5000,
                                'currency' => 'ZMW',
                                'additional_rules' => [
                                    'delivery_options' => [
                                        'standard' => ['multiplier' => 1.0, 'base_fee' => 1000],
                                        'express' => ['multiplier' => 2.5, 'base_fee' => 2500],
                                        'sameday' => ['multiplier' => 5.0, 'base_fee' => 5000]
                                    ],
                                    'insurance_rate' => 0.02,
                                    'min_fee' => 500
                                ]
                            ];
                            if ($billingConfig) {
                                $additionalRules = [];
                                if (!empty($billingConfig['additional_rules'])) {
                                    $additionalRules = is_string($billingConfig['additional_rules'])
                                        ? (json_decode($billingConfig['additional_rules'], true) ?? [])
                                        : $billingConfig['additional_rules'];
                                }
                                $billingConfigForJs = [
                                    'base_rate' => floatval($billingConfig['base_rate'] ?? 1000),
                                    'rate_per_kg' => floatval($billingConfig['rate_per_kg'] ?? 200),
                                    'volumetric_divisor' => intval($billingConfig['volumetric_divisor'] ?? 5000),
                                    'currency' => $billingConfig['currency'] ?? 'ZMW',
                                    'additional_rules' => array_merge($defaultBillingConfig['additional_rules'], $additionalRules)
                                ];
                            } else {
                                $billingConfigForJs = $defaultBillingConfig;
                            }
                        ?>
                        <!-- Inject billing config into JS -->
                        <script>
                            window.BILLING_CONFIG = <?php echo json_encode($billingConfigForJs); ?>;
                            window.COMPANY_COMMISSION = <?php echo json_encode($companyCommission); ?>;
                        </script>

                        <div class="form-section collapsible">
                            <h3><i class="fas fa-dollar-sign"></i> Financial Information <i class="fas fa-chevron-down toggle-icon"></i></h3>
                            <div class="section-content">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="deliveryFee">Delivery Fee (K) <span class="required">*</span></label>
                                    <input type="number" id="deliveryFee" name="deliveryFee" step="0.01" min="0" placeholder="Calculating..." required>
                                    <small class="form-help" id="deliveryFeeHelp">Auto-calculated from weight &amp; delivery option. You can override.</small>
                                </div>

                                <div class="form-group">
                                    <label for="insuranceAmount">Insurance (K)</label>
                                    <input type="number" id="insuranceAmount" name="insuranceAmount" step="0.01" min="0" placeholder="0.00">
                                    <small class="form-help">Optional insurance for declared value</small>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="commissionPercentage">Commission (%)</label>
                                    <input type="number" id="commissionPercentage" name="commissionPercentage" step="0.01" min="0" max="100" value="<?php echo htmlspecialchars(number_format($companyCommission, 2, '.', '')); ?>" readonly>
                                    <small class="form-help">Company commission rate</small>
                                    <input type="hidden" id="companyCommissionHidden" name="companyCommission" value="<?php echo htmlspecialchars(number_format($companyCommission, 2, '.', '')); ?>">
                                </div>

                                <div class="form-group">
                                    <label for="commissionAmount">Commission Amount (K)</label>
                                    <input type="number" id="commissionAmount" name="commissionAmount" step="0.01" min="0" value="0.00" readonly>
                                    <small class="form-help">Deducted from delivery fee</small>
                                </div>
                            </div>

                            <!-- Payment Summary -->
                            </div>
                            <div class="payment-summary-box" id="paymentSummaryBox">
                                <h4><i class="fas fa-receipt"></i> Payment Summary</h4>
                                <div class="summary-rows">
                                    <div class="summary-row">
                                        <span>Delivery Fee:</span>
                                        <span id="summaryDeliveryFee">K 0.00</span>
                                    </div>
                                    <div class="summary-row">
                                        <span>Insurance:</span>
                                        <span id="summaryInsurance">K 0.00</span>
                                    </div>
                                    <div class="summary-row">
                                        <span>Commission (<span id="summaryCommPct">0</span>%):</span>
                                        <span id="summaryCommission">K 0.00</span>
                                    </div>
                                    <div class="summary-row total-row">
                                        <span><strong>Total Due:</strong></span>
                                        <span id="summaryTotal"><strong>K 0.00</strong></span>
                                    </div>
                                    <div class="summary-row net-row">
                                        <span>Net Amount (after commission):</span>
                                        <span id="summaryNetAmount">K 0.00</span>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="paymentMethod">Payment Method <span class="required">*</span></label>
                                <select id="paymentMethod" name="paymentMethod" required onchange="handlePaymentMethodChange(this.value)">
                                    <option value="">Select payment method</option>
                                    <option value="cash" selected>Cash Payment (At Outlet)</option>
                                    <option value="cod">Cash (To Be Paid on Delivery)</option>
                                    <option value="lenco_mobile">Mobile Money (MTN, Airtel) - Lenco</option>
                                    <option value="lenco_card">Card Payment (Visa/Mastercard) - Lenco</option>
                                </select>
                                <small class="form-help">Online payments powered by Lenco <?php echo $lencoEnv === 'sandbox' ? '<span class="badge-sandbox">(Sandbox Mode)</span>' : '<span style="color:#16a34a;font-weight:600;">&#x1f512; Secure</span>'; ?></small>
                            </div>

                            <!-- Cash Payment Section -->
                            <div id="cashPaymentSection" class="payment-method-panel" style="display: block !important; visibility: visible !important; opacity: 1 !important; background: #f0fdf4 !important; border: 2px solid #22c55e !important; border-radius: 12px !important; padding: 16px !important; margin: 16px 0 !important; height: auto !important; overflow: visible !important; position: relative !important;">
                                <h4 style="color: #15803d; margin-bottom: 8px;"><i class="fas fa-money-bill-wave"></i> CASH PAYMENT (AT OUTLET)</h4>
                                <label for="cashAmount" style="font-weight: 600; color: #15803d; display: block !important;">Cash Amount Received (K) <span class="required">*</span></label>
                                <input type="number" id="cashAmount" name="cashAmount" step="0.01" min="0" placeholder="0.00" required style="font-size: 1.1em !important; padding: 12px !important; border: 2px solid #86efac !important; border-radius: 8px !important; width: 100% !important; margin-top: 8px !important; display: block !important; box-sizing: border-box !important;">
                                <small class="form-help" style="color: #16a34a; display: block !important; margin-top: 4px;">✓ Auto-filled with total due. Adjust if customer pays a different amount.</small>
                            </div>

                            <!-- COD Payment Section -->
                            <div id="codPaymentSection" class="payment-method-panel" style="display: none !important; background: #fefce8 !important; border: 2px solid #eab308 !important; border-radius: 12px !important; padding: 16px !important; margin: 16px 0 !important;">
                                <h4 style="color: #a16207; margin-bottom: 8px;"><i class="fas fa-hand-holding-usd"></i> CASH ON DELIVERY</h4>
                                <label for="codAmount" style="font-weight: 600; color: #a16207; display: block !important;">Amount to Collect on Delivery (K) <span class="required">*</span></label>
                                <input type="number" id="codAmount" name="codAmount" step="0.01" min="0" placeholder="0.00" style="font-size: 1.1em !important; padding: 12px !important; border: 2px solid #fde047 !important; border-radius: 8px !important; width: 100% !important; margin-top: 8px !important; display: block !important; box-sizing: border-box !important;">
                                <small class="form-help" style="color: #ca8a04; display: block !important; margin-top: 4px;">Total amount to collect from receiver (shipping + item value)</small>
                            </div>

                            <!-- Lenco Mobile Money Payment Section (Hidden by default) -->
                            <div id="mobileMoneySection" class="payment-section" style="display: none;">
                                <div class="payment-method-header">
                                    <i class="fas fa-mobile-alt"></i>
                                    <h4>Mobile Money Payment</h4>
                                    <span class="secure-badge"><i class="fas fa-shield-alt"></i> Secured by Lenco</span>
                                </div>
                                
                                <?php if ($lencoEnv === 'sandbox'): ?>
                                <div class="sandbox-notice">
                                    <i class="fas fa-info-circle"></i>
                                    <span>Test Mode: Use test numbers like 0971111111 (Airtel - Success) or 0961111111 (MTN - Success)</span>
                                </div>
                                <?php endif; ?>
                                
                                <div class="mobile-money-providers">
                                    <label class="provider-label">Select Your Network:</label>
                                    <div class="provider-options">
                                        <div class="provider-option" data-provider="mtn">
                                            <input type="radio" id="mtn" name="mobileProvider" value="mtn">
                                            <label for="mtn">
                                                <div class="provider-logo mtn-logo">
                                                    <i class="fas fa-phone"></i>
                                                    <span>MTN</span>
                                                </div>
                                            </label>
                                        </div>
                                        
                                        <div class="provider-option" data-provider="airtel">
                                            <input type="radio" id="airtel" name="mobileProvider" value="airtel">
                                            <label for="airtel">
                                                <div class="provider-logo airtel-logo">
                                                    <i class="fas fa-phone"></i>
                                                    <span>Airtel</span>
                                                </div>
                                            </label>
                                        </div>
                                        
                                        <div class="provider-option" data-provider="zamtel">
                                            <input type="radio" id="zamtel" name="mobileProvider" value="zamtel">
                                            <label for="zamtel">
                                                <div class="provider-logo zamtel-logo">
                                                    <i class="fas fa-phone"></i>
                                                    <span>Zamtel</span>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="mobileNumber">Mobile Money Number <span class="required">*</span></label>
                                    <div class="input-with-icon">
                                        <i class="fas fa-mobile-alt"></i>
                                        <input type="tel" id="mobileNumber" name="mobileNumber" placeholder="09XX XXX XXX" pattern="[0-9]{10}">
                                    </div>
                                    <small class="form-help">Enter 10-digit mobile number (e.g., 0977123456)</small>
                                </div>
                                
                                <div class="payment-summary">
                                    <div class="summary-row">
                                        <span>Delivery Fee:</span>
                                        <span class="amount" id="mobileFeeAmount">K 0.00</span>
                                    </div>
                                    <div class="summary-row">
                                        <span>Transaction Fee:</span>
                                        <span class="amount" id="mobileTransactionFee">K 0.00</span>
                                    </div>
                                    <div class="summary-row total">
                                        <span>Total Amount:</span>
                                        <span class="amount" id="mobileTotalAmount">K 0.00</span>
                                    </div>
                                </div>
                                
                                <div class="payment-info">
                                    <i class="fas fa-info-circle"></i>
                                    <p>You will receive a payment prompt on your phone to authorize this transaction.</p>
                                </div>

                                <button type="button" id="mobileMoneyPayBtn" class="lenco-pay-btn" onclick="initiateLencoPayment('mobile-money')">
                                    <div class="spinner"></div>
                                    <i class="fas fa-mobile-alt"></i>
                                    <span>Pay with Mobile Money</span>
                                </button>
                            </div>

                            <!-- Lenco Card Payment Section (Hidden by default) -->
                            <div id="cardPaymentSection" class="payment-section" style="display: none;">
                                <div class="payment-method-header">
                                    <i class="fas fa-credit-card"></i>
                                    <h4>Card Payment</h4>
                                    <span class="secure-badge"><i class="fas fa-lock"></i> Secured by Lenco</span>
                                </div>
                                
                                <?php if ($lencoEnv === 'sandbox'): ?>
                                <div class="sandbox-notice">
                                    <i class="fas fa-info-circle"></i>
                                    <span>Test Mode: Use card 4622 9431 2701 3705, CVV: 838, any future expiry</span>
                                </div>
                                <?php endif; ?>
                                
                                <div class="payment-info">
                                    <i class="fas fa-info-circle"></i>
                                    <p>A secure payment popup will appear to complete your card payment.</p>
                                </div>
                                
                                <div class="payment-summary">
                                    <div class="summary-row total">
                                        <span>Amount to Pay:</span>
                                        <span class="amount" id="cardTotalAmount">K 0.00</span>
                                    </div>
                                </div>

                                <button type="button" id="cardPayBtn" class="lenco-pay-btn" onclick="initiateLencoPayment('card')">
                                    <div class="spinner"></div>
                                    <i class="fas fa-credit-card"></i>
                                    <span>Pay with Card</span>
                                </button>
                            </div>

                            <!-- Inline Payment Method Handler - works even if external JS fails -->
                            <script>
                            console.log('🔥 PAYMENT METHOD HANDLER LOADED at ' + new Date().toISOString());
                            
                            function handlePaymentMethodChange(method) {
                                console.log('💰 Payment method changed to:', method);
                                
                                // Get all payment sections
                                var sections = {
                                    cash: document.getElementById('cashPaymentSection'),
                                    cod: document.getElementById('codPaymentSection'),
                                    lenco_mobile: document.getElementById('mobileMoneySection'),
                                    lenco_card: document.getElementById('cardPaymentSection')
                                };
                                var inputs = {
                                    cash: document.getElementById('cashAmount'),
                                    cod: document.getElementById('codAmount'),
                                    mobile: document.getElementById('mobileNumber')
                                };
                                
                                console.log('📦 Sections found:', {
                                    cash: !!sections.cash,
                                    cod: !!sections.cod,
                                    mobile: !!sections.lenco_mobile,
                                    card: !!sections.lenco_card
                                });
                                
                                // Hide all sections and remove required
                                for (var key in sections) {
                                    if (sections[key]) {
                                        sections[key].style.display = 'none';
                                        sections[key].style.visibility = 'hidden';
                                    }
                                }
                                for (var key in inputs) {
                                    if (inputs[key]) inputs[key].removeAttribute('required');
                                }
                                
                                // Show the selected section with !important-level visibility
                                if (method === 'cash' && sections.cash) {
                                    sections.cash.style.display = 'block';
                                    sections.cash.style.visibility = 'visible';
                                    sections.cash.style.opacity = '1';
                                    sections.cash.style.height = 'auto';
                                    if (inputs.cash) inputs.cash.setAttribute('required', 'required');
                                    console.log('✅ CASH section shown');
                                } else if (method === 'cod' && sections.cod) {
                                    sections.cod.style.display = 'block';
                                    sections.cod.style.visibility = 'visible';
                                    sections.cod.style.opacity = '1';
                                    if (inputs.cod) inputs.cod.setAttribute('required', 'required');
                                    console.log('✅ COD section shown');
                                } else if (method === 'lenco_mobile' && sections.lenco_mobile) {
                                    sections.lenco_mobile.style.display = 'block';
                                    sections.lenco_mobile.style.visibility = 'visible';
                                    if (inputs.mobile) inputs.mobile.setAttribute('required', 'required');
                                    console.log('✅ Mobile Money section shown');
                                } else if (method === 'lenco_card' && sections.lenco_card) {
                                    sections.lenco_card.style.display = 'block';
                                    sections.lenco_card.style.visibility = 'visible';
                                    console.log('✅ Card section shown');
                                }
                                
                                // Trigger payment summary update if available
                                if (typeof updatePaymentSummarySection === 'function') {
                                    updatePaymentSummarySection();
                                }
                            }
                            
                            // Initialize on page load - show cash section by default
                            (function() {
                                console.log('🚀 Initializing payment method on page load');
                                var pm = document.getElementById('paymentMethod');
                                if (pm) {
                                    console.log('📝 Payment method element found, value:', pm.value);
                                    handlePaymentMethodChange(pm.value || 'cash');
                                } else {
                                    console.error('❌ Payment method select not found!');
                                }
                            })();
                            </script>

                            <!-- Delivery Option -->
                            <div class="form-group">
                                <label for="deliveryOption"><i class="fas fa-shipping-fast"></i> Delivery Option <span class="required">*</span></label>
                                <select id="deliveryOption" name="deliveryOption" required>
                                    <option value="standard" selected>Standard Delivery</option>
                                    <option value="express">Express Delivery</option>
                                    <option value="sameday">Same Day Delivery</option>
                                </select>
                                <small class="form-help"><i class="fas fa-info-circle"></i> Delivery speed affects the delivery fee</small>
                            </div>
                        </div>
                        
                        <!-- Trip Assignment -->
                        <!-- Trip Assignment -->
                        <div class="trip-assignment-container" id="tripAssignmentSection">
                            <h3><i class="fas fa-route"></i> Trip Assignment <span class="optional-badge">(Optional)</span></h3>
                            
                            <!-- Instruction Message (shown when destination not selected) -->
                            <div class="trip-instruction-message" id="tripInstructionMessage">
                                <i class="fas fa-info-circle"></i>
                                <p>Please select a <strong>Destination Outlet</strong> first to see available trips for that route.</p>
                            </div>
                            
                            <div class="trip-assignment-content" id="tripAssignmentContent" style="display: none;">
                                <div class="trip-selector">
                                    <label for="tripId">Assign to Trip</label>
                                    <select id="tripId" name="tripId" disabled>
                                        <option value="">-- Select Destination First --</option>
                                        <!-- Trips will be loaded via JavaScript -->
                                    </select>
                                    <small class="form-help">Only trips going to the selected destination will appear here</small>
                                </div>
                                
                                <div class="trip-info" id="tripInfo" style="display: none;">
                                    <h4><i class="fas fa-info-circle"></i> Trip Details</h4>
                                    <div class="trip-details">
                                        <div class="detail-row">
                                            <span class="label">Vehicle</span>
                                            <span class="value" id="tripVehicle">Vehicle Loading...</span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="label">Plate Number</span>
                                            <span class="value" id="tripPlateNumber">-</span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="label">Route</span>
                                            <span class="value" id="tripRoute">-</span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="label">Origin</span>
                                            <span class="value" id="tripOrigin">-</span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="label">Destination</span>
                                            <span class="value" id="tripDestination">-</span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="label">Status</span>
                                            <span class="value status-badge" id="tripStatus">-</span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="label">Departure</span>
                                            <span class="value" id="tripDeparture">-</span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="label">Manager</span>
                                            <span class="value" id="tripManager">-</span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="label">Manager Phone</span>
                                            <span class="value" id="tripManagerPhone">-</span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="label">Total Stops</span>
                                            <span class="value" id="tripTotalStops">-</span>
                                        </div>
                                    </div>
<script>

document.addEventListener('DOMContentLoaded', function() {
    const tripSelect = document.getElementById('tripId');
    if (!tripSelect) return;
    tripSelect.addEventListener('change', async function() {
        const tripId = this.value;
        const tripInfo = document.getElementById('tripInfo');
        
        if (!tripId) {
            
            if (tripInfo) tripInfo.style.display = 'none';
            return;
        }
        
        
        try {
            const tripRes = await fetch('../api/trips/fetch_trip_details.php?trip_id=' + encodeURIComponent(tripId));
            const tripData = await tripRes.json();
            
            if (!tripData.success || !tripData.trip) {
                console.error('Failed to fetch trip details:', tripData.error || 'No trip data');
                document.getElementById('tripVehicle').textContent = 'Error: ' + (tripData.error || 'No trip data');
                document.getElementById('tripPlateNumber').textContent = '-';
                document.getElementById('tripRoute').textContent = '-';
                document.getElementById('tripOrigin').textContent = '-';
                document.getElementById('tripDestination').textContent = '-';
                document.getElementById('tripStatus').textContent = '-';
                document.getElementById('tripDeparture').textContent = '-';
                document.getElementById('tripManager').textContent = '-';
                document.getElementById('tripManagerPhone').textContent = '-';
                document.getElementById('tripTotalStops').textContent = '-';
                return;
            }
            
            const trip = tripData.trip;
            console.log('Trip details fetched:', trip);
            
            
            document.getElementById('tripVehicle').textContent = trip.vehicle_name || '-';
            document.getElementById('tripPlateNumber').textContent = trip.plate_number || '-';
            document.getElementById('tripRoute').textContent = trip.route || '-';
            document.getElementById('tripOrigin').textContent = trip.origin || '-';
            document.getElementById('tripDestination').textContent = trip.destination || '-';
            
            
            const statusElement = document.getElementById('tripStatus');
            statusElement.textContent = trip.status ? trip.status.charAt(0).toUpperCase() + trip.status.slice(1) : '-';
            statusElement.className = 'value status-badge ' + (trip.status || '').toLowerCase();
            
            
            const departureElement = document.getElementById('tripDeparture');
            if (trip.departure && trip.departure !== '-') {
                try {
                    const date = new Date(trip.departure);
                    departureElement.textContent = date.toLocaleString('en-US', {
                        month: 'short',
                        day: 'numeric',
                        year: 'numeric',
                        hour: 'numeric',
                        minute: '2-digit',
                        hour12: true
                    });
                } catch (e) {
                    departureElement.textContent = trip.departure;
                }
            } else {
                departureElement.textContent = 'Not scheduled';
            }
            
            document.getElementById('tripManager').textContent = trip.manager_name || '-';
            document.getElementById('tripManagerPhone').textContent = trip.manager_phone || '-';
            document.getElementById('tripTotalStops').textContent = trip.total_stops ? trip.total_stops + ' stops' : '0 stops';
            
            
            if (tripInfo) tripInfo.style.display = 'block';
            
        } catch (err) {
            console.error('Error fetching trip details:', err);
            document.getElementById('tripVehicle').textContent = 'Error fetching trip details';
            document.getElementById('tripPlateNumber').textContent = '-';
            document.getElementById('tripRoute').textContent = '-';
            document.getElementById('tripOrigin').textContent = '-';
            document.getElementById('tripDestination').textContent = '-';
            document.getElementById('tripStatus').textContent = '-';
            document.getElementById('tripDeparture').textContent = '-';
            document.getElementById('tripManager').textContent = '-';
            document.getElementById('tripManagerPhone').textContent = '-';
            document.getElementById('tripTotalStops').textContent = '-';
        }
    });
});
</script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.form-section').forEach(section => {
            section.classList.add('collapsible');
            const header = section.querySelector('h3');
            if (!header) return;
            header.style.cursor = 'pointer';
            header.setAttribute('role', 'button');
            header.setAttribute('aria-expanded', 'false');
            let icon = header.querySelector('.toggle-icon');
            if (!icon) {
                icon = document.createElement('i');
                icon.className = 'fas fa-chevron-down toggle-icon';
                header.appendChild(icon);
            }
            // prepare content container
            const content = document.createElement('div');
            content.className = 'section-content collapsed';
            content.setAttribute('aria-hidden', 'true');
            // move siblings into content until next section or end
            let sibling = header.nextSibling;
            while (sibling) {
                const next = sibling.nextSibling;
                content.appendChild(sibling);
                sibling = next;
            }
            section.appendChild(content);

            // initialize height if expanded (none by default)
            header.addEventListener('click', () => {
                const expanded = header.getAttribute('aria-expanded') === 'true';
                header.setAttribute('aria-expanded', expanded ? 'false' : 'true');

                if (expanded) {
                    // collapse
                    content.style.maxHeight = content.scrollHeight + 'px';
                    icon.style.transform = 'rotate(-90deg)';
                    // force reflow
                    content.offsetHeight;
                    content.style.maxHeight = '0px';
                    content.classList.add('collapsed');
                    content.setAttribute('aria-hidden', 'true');
                } else {
                    // expand
                    content.classList.remove('collapsed');
                    content.style.maxHeight = content.scrollHeight + 'px';
                    icon.style.transform = 'rotate(0deg)';
                    content.setAttribute('aria-hidden', 'false');
                }
            });

            // allow clicking the whole section area (outside header) to toggle when header is empty
            section.addEventListener('click', e => {
                if (e.target === section) {
                    header.click();
                }
            });

            content.addEventListener('transitionend', function() {
                if (header.getAttribute('aria-expanded') === 'true') {
                    content.style.maxHeight = '';
                    content.setAttribute('aria-hidden', 'false');
                } else {
                    content.style.maxHeight = '0px';
                    content.setAttribute('aria-hidden', 'true');
                }
            });
        });
    });
</script>
                                </div>
                            </div>
                        </div>
                    </div>

                </form>
            </div>
        </main>

    </div>

    <!-- Success Modal -->
    <div id="successModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header success">
                <h2><i class="fas fa-check-circle"></i> Parcel Registered Successfully!</h2>
            </div>
            <div class="modal-body">
                <p>Your parcel has been registered successfully.</p>
                <p><strong>Tracking Number:</strong> <span id="modalTrackingNumber"></span></p>
                <div class="modal-actions">
                    <button type="button" class="btn-primary" onclick="closeModal()">Continue</button>
                    <button type="button" class="btn-secondary" onclick="printLabel()">Print Label</button>
                </div>
            </div>
        </div>
    </div>

<!-- Load parcel registration script LAST with cache busting -->
<script src="../assets/js/parcel_registration.js?v=<?php echo time(); ?>"></script>
<script src="../assets/js/lenco_payment.js?v=<?php echo time(); ?>"></script>
<script src="../assets/js/sidebar-toggle.js"></script>
<script src="../assets/js/notifications.js"></script>

<?php include __DIR__ . '/../includes/pwa_install_button.php'; ?>
<script src="../js/pwa-install.js"></script>
</body>
</html>
