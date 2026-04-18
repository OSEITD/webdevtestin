<?php
    // Start output buffering so we can safely redirect later with header()
    if (!ob_get_level()) ob_start();
    $page_title = 'Company - Edit Driver';

    // Start session early so header.php and subsequent logic can rely on $_SESSION
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    require_once __DIR__ . '/../api/supabase-client.php';

    // Handle AJAX POST requests early — before header.php outputs any HTML
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    $acceptsJson = isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($isAjax || $acceptsJson)) {
        while (ob_get_level()) { @ob_end_clean(); }
        header('Content-Type: application/json');

        $companyId = $_SESSION['id'] ?? null;
        if (!$companyId) {
            echo json_encode(['success' => false, 'error' => 'Not authenticated. Please log in.']);
            exit;
        }

        $driverId = $_POST['id'] ?? (isset($_GET['id']) ? trim($_GET['id']) : null);
        $driverName = trim($_POST['driver_name'] ?? '');
        $driverEmail = trim($_POST['driver_email'] ?? '');
        $driverPhone = trim($_POST['driver_phone'] ?? '');
        $licenseNumber = trim($_POST['license_number'] ?? '');
        $address = trim($_POST['address_line1'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $postal_code = trim($_POST['postal_code'] ?? '');
        $country = trim($_POST['country'] ?? '');
        $status = strtolower(trim($_POST['status'] ?? 'unavailable'));

        $allowedStatuses = ['available', 'assigned', 'out for delivery', 'unavailable', 'Available', 'Assigned', 'Out for delivery', 'Unavailable'];

        if (empty($driverId) || empty($driverName)) {
            echo json_encode(['success' => false, 'error' => 'Driver ID and name are required.']);
            exit;
        }
        if (!in_array($status, $allowedStatuses, true)) {
            echo json_encode(['success' => false, 'error' => 'Invalid status selected.']);
            exit;
        }

        try {
            $supabase = new SupabaseClient();
            $payload = [
                'driver_name' => $driverName,
                'driver_email' => $driverEmail,
                'driver_phone' => $driverPhone,
                'license_number' => $licenseNumber,
                'address' => $address,
                'city' => $city,
                'state' => $state,
                'postal_code' => $postal_code,
                'country' => $country,
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $path = "drivers?id=eq.{$driverId}&company_id=eq.{$companyId}";
            $res = $supabase->put($path, $payload);
            echo json_encode(['success' => true, 'message' => 'Driver updated successfully.']);
        } catch (Exception $e) {
            error_log('Error updating driver (AJAX): ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Failed to update driver: ' . $e->getMessage()]);
        }
        exit;
    }

    include __DIR__ . '/../includes/header.php';

    $error = null;
    $driver = null;

    $companyId = $_SESSION['id'] ?? null;
    $accessToken = $_SESSION['access_token'] ?? null;
    $driverId = isset($_GET['id']) ? trim($_GET['id']) : null;

    if (!$companyId) {
        $error = 'Not authenticated. Please log in.';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
        $driverId = $_POST['id'] ?? $driverId;
        $driverName = trim($_POST['driver_name'] ?? '');
        $driverEmail = trim($_POST['driver_email'] ?? '');
        $driverPhone = trim($_POST['driver_phone'] ?? '');
        $licenseNumber = trim($_POST['license_number'] ?? '');
        $address = trim($_POST['address_line1'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $postal_code = trim($_POST['postal_code'] ?? '');
        $country = trim($_POST['country'] ?? '');
        $status = strtolower(trim($_POST['status'] ?? 'unavailable'));

        // Allowed statuses must match the DB values (new Title Case format)
        $allowedStatuses = ['available', 'assigned', 'out for delivery', 'unavailable', 'Available', 'Assigned', 'Out for delivery', 'Unavailable'];

        if (empty($driverId) || empty($driverName)) {
            $error = 'Driver ID and name are required.';
        } elseif (!in_array($status, $allowedStatuses, true)) {
            $error = 'Invalid status selected.';
        } else {
            try {
                $supabase = new SupabaseClient();
                $payload = [
                    'driver_name' => $driverName,
                    'driver_email' => $driverEmail,
                    'driver_phone' => $driverPhone,
                    'license_number' => $licenseNumber,
                    'address' => $address,
                    'city' => $city,
                    'state' => $state,
                    'postal_code' => $postal_code,
                    'country' => $country,
                    'status' => $status,
                    'updated_at' => date('Y-m-d H:i:s')
                ];

                $path = "drivers?id=eq.{$driverId}&company_id=eq.{$companyId}";
                $res = $supabase->put($path, $payload);

                // If this is an AJAX request, return JSON
                $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
                $acceptsJson = isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
                if ($isAjax || $acceptsJson) {
                    while (ob_get_level()) { @ob_end_clean(); }
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'Driver updated successfully.']);
                    exit;
                }

                // Fallback: traditional redirect
                $dir = rtrim(dirname($_SERVER['PHP_SELF']), '\/');
                $redirectUrl = $dir . '/company-view-driver.php?id=' . urlencode($driverId);
                while (ob_get_level()) { @ob_end_clean(); }
                $safeUrl = htmlspecialchars($redirectUrl, ENT_QUOTES);
                $jsUrl = addslashes($redirectUrl);
                echo '<!doctype html><html><head><meta http-equiv="refresh" content="0;url=' . $safeUrl . '">';
                echo '<script>window.location.replace("' . $jsUrl . '");</script></head><body>If you are not redirected, <a href="' . $safeUrl . '">click here</a>.</body></html>';
                exit;
            } catch (Exception $e) {
                error_log('Error updating driver: ' . $e->getMessage());
                $error = 'Failed to update driver: ' . $e->getMessage();

                // If AJAX, return JSON error
                $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
                $acceptsJson = isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
                if ($isAjax || $acceptsJson) {
                    while (ob_get_level()) { @ob_end_clean(); }
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => $error]);
                    exit;
                }
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && !$error) {
        if (empty($driverId)) {
            $error = 'No driver specified.';
        } else {
            try {
                $supabase = new SupabaseClient();
                $endpoint = 'drivers?id=eq.' . urlencode($driverId) . '&company_id=eq.' . urlencode($companyId) . '&select=id,driver_name,driver_email,driver_phone,license_number,address,city,state,postal_code,country,status,updated_at';

                if ($accessToken) {
                    $res = $supabase->getWithToken($endpoint, $accessToken);
                } else {
                    $res = $supabase->getRecord($endpoint, true);
                }

                if (is_array($res) && count($res) > 0) {
                    $driver = $res[0];
                } else {
                    $error = 'Driver not found or access denied.';
                }
            } catch (Exception $e) {
                error_log('Error fetching driver for edit: ' . $e->getMessage());
                $error = 'Failed to load driver details: ' . $e->getMessage();
            }
        }
    }

    // Extract country code from stored phone for pre-population in dropdown
    $phoneCountry = 'ZM'; // default
    
    if (!empty($driver['driver_phone'])) {
        $phoneNumber = $driver['driver_phone'];
        $countryDialMap = [
            '+260' => 'ZM',
            '+1'   => 'US',
            '+44'  => 'UK',
            '+27'  => 'ZA',
            '+255' => 'TZ',
            '+254' => 'KE',
            '+234' => 'NG',
            '+233' => 'GH',
            '+91'  => 'IN',
            '+61'  => 'AU'
        ];
        
        foreach ($countryDialMap as $dial => $country) {
            if (strpos($phoneNumber, $dial) === 0) {
                $phoneCountry = $country;
                break;
            }
        }
    }
?>

<body class="bg-gray-100 min-h-screen">
    <div class="mobile-dashboard">
        <main class="main-content">
            <div class="content-header">
                <h1>Edit Driver</h1>
            </div>

            <div class="form-card">
                <?php if ($error): ?>
                    <div class="form-group">
                        <label>Error</label>
                        <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                    </div>
                    <div class="form-actions">
                        <button class="action-btn secondary" onclick="window.location.href='drivers.php'">
                            <i class="fas fa-arrow-left"></i> Back to Drivers
                        </button>
                    </div>
                <?php else: ?>
                    <form method="post" action="company-edit-driver.php?id=<?php echo urlencode($driverId); ?>" id="editDriverForm" novalidate>
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($driverId); ?>" />

                        <div class="form-group">
                            <label for="driver_name">Driver Name <span class="required">*</span></label>
                            <input type="text" id="driver_name" name="driver_name" class="form-input-field" value="<?php echo htmlspecialchars($driver['driver_name'] ?? ''); ?>" required />
                        </div>

                        <div class="form-group">
                            <label for="driver_email">Contact Email</label>
                            <input type="email" id="driver_email" name="driver_email" class="form-input-field" value="<?php echo htmlspecialchars($driver['driver_email'] ?? ''); ?>" />
                        </div>

                        <div class="form-group">
                            <label for="driver_phone">Contact Phone</label>
                            <input type="tel" id="driver_phone" name="driver_phone" class="form-input-field" value="<?php echo htmlspecialchars(substr($driver['driver_phone'] ?? '', -9)); ?>" placeholder="Enter phone number" />
                        </div>

                        <div class="form-group">
                            <label for="license_number">License Number</label>
                            <input type="text" id="license_number" name="license_number" class="form-input-field" value="<?php echo htmlspecialchars($driver['license_number'] ?? ''); ?>" />
                        </div>
                        <div class="form-group">
                            <label for="address_line1">Address Line 1</label>
                            <input type="text" id="address_line1" name="address_line1" class="form-input-field" value="<?php echo htmlspecialchars($driver['address'] ?? ''); ?>" />
                        </div>
                        <div class="grid-2">
                            <div class="form-group">
                                <label for="city">City / Town</label>
                                <input type="text" id="city" name="city" class="form-input-field" value="<?php echo htmlspecialchars($driver['city'] ?? ''); ?>" />
                            </div>
                            <div class="form-group">
                                <label for="state">State / Province</label>
                                <input type="text" id="state" name="state" class="form-input-field" value="<?php echo htmlspecialchars($driver['state'] ?? ''); ?>" />
                            </div>
                        </div>
                        <div class="grid-2">
                            <div class="form-group">
                                <label for="postal_code">Postal Code</label>
                                <input type="text" id="postal_code" name="postal_code" class="form-input-field" value="<?php echo htmlspecialchars($driver['postal_code'] ?? ''); ?>" />
                            </div>
                            <div class="form-group">
                                <label for="country">Country</label>
                                <select id="country" name="country" class="form-input-field" data-selected="<?php echo htmlspecialchars($driver['country'] ?? ''); ?>">
                                    <option value="">Select Country</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status" class="form-input-field">
                                    <option value="available" <?php echo (isset($driver['status']) && strtolower($driver['status']) === 'available') ? 'selected' : ''; ?>>Available</option>
                                    <option value="assigned" <?php echo (isset($driver['status']) && strtolower($driver['status']) === 'assigned') ? 'selected' : ''; ?>>Assigned</option>
                                    <option value="out_for_delivery" <?php echo (isset($driver['status']) && strtolower($driver['status']) === 'out_for_delivery') ? 'selected' : ''; ?>>Out for Delivery</option>
                                    <option value="unavailable" <?php echo (isset($driver['status']) && strtolower($driver['status']) === 'unavailable') ? 'selected' : ''; ?>>Unavailable</option>
                                </select>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="action-btn">Save Changes</button>
                            <button type="button" class="action-btn secondary" onclick="window.location.href='company-view-driver.php?id=<?php echo urlencode($driverId); ?>'">Cancel</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="../assets/js/company-scripts.js"></script>
    <script src="../../assets/js/form-validator.js?v=2"></script>
    <script>
        const validator = new FormValidator('editDriverForm', {
            driver_name:  { required: true, minLength: 2, maxLength: 100 },
            driver_email: { email: true },
            driver_phone: { phone: true },
            license_number: { minLength: 2 },
            country: { required: true }
        });

        (function populateCountries() {
            if (typeof COUNTRY_CODES === 'undefined') return;
            const sel = document.getElementById('country');
            const selectedCountry = sel.getAttribute('data-selected') || '';
            COUNTRY_CODES.forEach(c => {
                const opt = document.createElement('option');
                const val = c.name || c.code || c;
                opt.value = val;
                opt.textContent = val;
                if (val.toLowerCase() === selectedCountry.toLowerCase()) {
                    opt.selected = true;
                }
                sel.appendChild(opt);
            });
        })();

        document.getElementById('editDriverForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            if (!validator.validateAll()) return;

            const saveBtn = this.querySelector('button[type="submit"]');
            const originalText = saveBtn.textContent;
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';

            // Process phone with country code
            const phoneInput = document.getElementById('driver_phone');
            let fullPhone = phoneInput.value.replace(/\s/g, '');
            if (fullPhone) {
                const selectedCountryCode = validator._phoneCountrySelections['driver_phone'] || '<?php echo $phoneCountry; ?>';
                const country = COUNTRY_CODES.find(c => c.code === selectedCountryCode);
                fullPhone = country ? country.dial + fullPhone : '+260' + fullPhone;
            }

            const formData = new URLSearchParams(new FormData(this));
            // Override phone with the full international number
            formData.set('driver_phone', fullPhone);

            try {
                const response = await fetch(this.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'include',
                    body: formData
                });
                const result = await response.json();

                if (result.success === true) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Driver Updated!',
                        text: 'The driver has been successfully updated.',
                        confirmButtonColor: '#2e0d2a'
                    }).then(() => {
                        window.location.href = 'company-view-driver.php?id=<?php echo urlencode($driverId); ?>';
                    });
                    return;
                }
                throw new Error(result.error || 'Failed to update driver');
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message || 'An unexpected error occurred.',
                    confirmButtonColor: '#2e0d2a'
                });
            } finally {
                saveBtn.disabled = false;
                saveBtn.textContent = originalText;
            }
        });
    </script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
