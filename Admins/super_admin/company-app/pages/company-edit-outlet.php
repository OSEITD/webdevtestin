<?php
    $page_title = 'Company - Edit Outlet';

    // Ensure session and supabase client are available before any output
    require_once __DIR__ . '/../api/supabase-client.php';
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    // Start output buffering to avoid "headers already sent" errors and allow safe redirects
    if (!ob_get_level()) {
        ob_start();
    }

    require_once __DIR__ . '/../includes/header.php';

    $error = null;
    $success = null;
    $outlet = null;

    $companyId = $_SESSION['id'] ?? null;
    $accessToken = $_SESSION['access_token'] ?? null;
    $outletId = isset($_GET['id']) ? trim($_GET['id']) : null;

    if (!$companyId) {
        $error = 'Not authenticated. Please log in.';
    }

    // On POST, validate and attempt update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
        $outletId = $_POST['id'] ?? $outletId;
        $outletName = trim($_POST['outlet_name'] ?? '');
        $address = trim($_POST['address_line1'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $postal_code = trim($_POST['postal_code'] ?? '');
        $country = trim($_POST['country'] ?? '');
        $contactPerson = trim($_POST['contact_person'] ?? '');
        $contactEmail = trim($_POST['contact_email'] ?? '');
        $contactPhone = trim($_POST['contact_phone'] ?? '');
        $status = trim($_POST['status'] ?? 'inactive');

        // Basic validation
        if (empty($outletId) || empty($outletName)) {
            $error = 'Outlet ID and name are required.';
        } else {
            try {
                $supabase = new SupabaseClient();

                // Build the patch payload
                $payload = [
                    'outlet_name' => $outletName,
                    'address' => $address,
                    'city' => $city,
                    'state' => $state,
                    'postal_code' => $postal_code,
                    'country' => $country,
                    'contact_person' => $contactPerson,
                    'contact_email' => $contactEmail,
                    'contact_phone' => $contactPhone,
                    'status' => $status,
                    'updated_at' => date('Y-m-d H:i:s')
                ];

                // Use service role for update if available else fallback to supabase key
                $path = "outlets?id=eq.{$outletId}&company_id=eq.{$companyId}";
                $res = $supabase->put($path, $payload);

                // put() returns parsed response or true; assume success if no exception
                $success = 'Outlet updated successfully.';

                // Redirect back to view page to show updated details
                // Build a directory-aware URL (works even if this file is in a subfolder)
                $currentDir = dirname($_SERVER['PHP_SELF']);
                $redirectUrl = rtrim($currentDir, '/') . '/company-view-outlet.php?id=' . urlencode($outletId);

                // Clean output buffers to avoid partial output
                while (ob_get_level()) {
                    @ob_end_clean();
                }

                // Some includes may have already sent output (sidebar/header). Use a JS/meta redirect
                // so the browser will navigate even when PHP headers can't be sent.
                $safeUrl = htmlspecialchars($redirectUrl, ENT_QUOTES);
                $jsUrl = addslashes($redirectUrl);
                echo '<!doctype html><html><head><meta http-equiv="refresh" content="0;url=' . $safeUrl . '">';
                echo '<script>window.location.replace("' . $jsUrl . '");</script></head><body>If you are not redirected, <a href="' . $safeUrl . '">click here</a>.</body></html>';
                exit;
            } catch (Exception $e) {
                error_log('Error updating outlet: ' . $e->getMessage());
                $error = 'Failed to update outlet: ' . $e->getMessage();
            }
        }
    }

    // On GET, fetch outlet details for the form
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && !$error) {
        if (empty($outletId)) {
            $error = 'No outlet specified.';
        } else {
            try {
                $supabase = new SupabaseClient();
                $endpoint = 'outlets?id=eq.' . urlencode($outletId) . '&company_id=eq.' . urlencode($companyId) . '&select=id,company_id,outlet_name,address,city,state,postal_code,country,contact_person,contact_email,contact_phone,status,created_at,updated_at';

                if ($accessToken) {
                    $res = $supabase->getWithToken($endpoint, $accessToken);
                } else {
                    $res = $supabase->getRecord($endpoint, true);
                }

                if (is_array($res) && count($res) > 0) {
                    $outlet = $res[0];
                } else {
                    $error = 'Outlet not found or access denied.';
                }
            } catch (Exception $e) {
                error_log('Error fetching outlet for edit: ' . $e->getMessage());
                $error = 'Failed to load outlet details: ' . $e->getMessage();
            }
        }
    }

    // Extract country code from stored phone for pre-population in dropdown
    $phoneCountry = 'ZM'; // default
    
    if (!empty($outlet['contact_phone'])) {
        $phoneNumber = $outlet['contact_phone'];
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
                <h1>Edit Outlet</h1>
            </div>

            <div class="form-card">
                <?php if ($error): ?>
                    <div class="form-group">
                        <label>Error</label>
                        <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                    </div>
                    <div class="form-actions">
                        <button class="action-btn secondary" onclick="window.location.href='outlets.php'">
                            <i class="fas fa-arrow-left"></i> Back to Outlets
                        </button>
                    </div>
                <?php else: ?>
                    <form method="post" action="company-edit-outlet.php?id=<?php echo urlencode($outletId); ?>" id="editOutletForm" novalidate>
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($outletId); ?>" />

                        <div class="form-group">
                            <label for="outlet_name">Outlet Name <span class="required">*</span></label>
                            <input type="text" id="outlet_name" name="outlet_name" class="form-input-field" value="<?php echo htmlspecialchars($outlet['outlet_name'] ?? ''); ?>" required />
                        </div>

                        <div class="form-group">
                            <input type="hidden" id="address" name="address" value="<?php echo htmlspecialchars($outlet['address'] ?? ''); ?>" />
                        <div class="form-group">
                            <label for="address_line1">Address Line 1</label>
                            <input type="text" id="address_line1" name="address_line1" class="form-input-field" value="<?php echo htmlspecialchars($outlet['address'] ?? ''); ?>" />
                        </div>
                        <div class="form-group">
                            <label for="city">City / Town</label>
                            <input type="text" id="city" name="city" class="form-input-field" value="<?php echo htmlspecialchars($outlet['city'] ?? ''); ?>" />
                        </div>
                        <div class="form-group">
                            <label for="state">State / Province</label>
                            <input type="text" id="state" name="state" class="form-input-field" value="<?php echo htmlspecialchars($outlet['state'] ?? ''); ?>" />
                        </div>
                        <div class="form-group">
                            <label for="postal_code">Postal Code</label>
                            <input type="text" id="postal_code" name="postal_code" class="form-input-field" value="<?php echo htmlspecialchars($outlet['postal_code'] ?? ''); ?>" />
                        </div>
                        <div class="form-group">
                            <label for="country">Country</label>
                            <select id="country" name="country" class="form-input-field" data-selected="<?php echo htmlspecialchars($outlet['country'] ?? ''); ?>">
                                <option value="">Select Country</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="contact_person">Contact Person</label>
                            <input type="text" id="contact_person" name="contact_person" class="form-input-field" value="<?php echo htmlspecialchars($outlet['contact_person'] ?? ''); ?>" />
                        </div>

                        <div class="form-group">
                            <label for="contact_email">Contact Email</label>
                            <input type="email" id="contact_email" name="contact_email" class="form-input-field" value="<?php echo htmlspecialchars($outlet['contact_email'] ?? ''); ?>" />
                        </div>

                        <div class="form-group">
                            <label for="contact_phone">Contact Phone</label>
                            <input type="tel" id="contact_phone" name="contact_phone" class="form-input-field" value="<?php echo htmlspecialchars(substr($outlet['contact_phone'] ?? '', -9)); ?>" placeholder="Enter phone number" />
                        </div>

                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status" class="form-input-field">
                                <option value="active" <?php echo (isset($outlet['status']) && strtolower($outlet['status']) === 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo (isset($outlet['status']) && strtolower($outlet['status']) === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                <option value="maintenance" <?php echo (isset($outlet['status']) && strtolower($outlet['status']) === 'maintenance') ? 'selected' : ''; ?>>Maintenance</option>
                            </select>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="action-btn">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                            <button type="button" class="action-btn secondary" onclick="window.location.href='company-view-outlet.php?id=<?php echo urlencode($outletId); ?>'">
                                Cancel
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="../assets/js/company-scripts.js"></script>
    <script src="../../assets/js/form-validator.js?v=2"></script>
    <script>
        const validator = new FormValidator('editOutletForm', {
            outlet_name:    { required: true, minLength: 2, maxLength: 100 },
            address_line1:  { minLength: 3 },
            contact_person: { minLength: 2, maxLength: 100 },
            contact_email:  { email: true },
            contact_phone:  { phone: true },
            country:        { required: true }
        });

        // Single submit handler: validate → combine phone → build address → submit
        document.getElementById('editOutletForm').addEventListener('submit', function(e) {
            if (!validator.validateAll()) {
                e.preventDefault();
                return;
            }

            // Combine phone with country code before submitting
            const phoneVal = document.getElementById('contact_phone').value.replace(/\s/g, '');
            if (phoneVal) {
                const selectedCountryCode = validator._phoneCountrySelections['contact_phone'] || 'ZM';
                const country = COUNTRY_CODES.find(c => c.code === selectedCountryCode);
                const fullPhone = country ? country.dial + phoneVal : '+260' + phoneVal;
                document.getElementById('contact_phone').value = fullPhone;
            }

            // Build hidden combined address field
            buildHiddenAddress();
        });

        (function populateCountries() {
            if (typeof COUNTRY_CODES === 'undefined') return;
            const sel = document.getElementById('country');
            COUNTRY_CODES.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.name || c.code || c;
                opt.textContent = c.name || c.code || c;
                sel.appendChild(opt);
            });
        })();

        function buildHiddenAddress() {
            const parts = [];
            const a1 = document.getElementById('address_line1').value.trim(); if (a1) parts.push(a1);
            const city = document.getElementById('city').value.trim(); if (city) parts.push(city);
            const state = document.getElementById('state').value.trim(); if (state) parts.push(state);
            const postal = document.getElementById('postal_code').value.trim(); if (postal) parts.push(postal);
            const country = document.getElementById('country').value; if (country) parts.push(country);
            document.getElementById('address').value = parts.join(', ');
        }

        async function parseStoredAddress() {
            const raw = (document.getElementById('address').value || '').trim();
            if (!raw) return;
            try {
                const resp = await fetch('../api/normalize_address.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ address: raw })
                });
                const data = await resp.json();
                if (data && data.success) {
                    if (data.address_line1) document.getElementById('address_line1').value = data.address_line1;
                    if (data.city) document.getElementById('city').value = data.city;
                    if (data.state) document.getElementById('state').value = data.state;
                    if (data.postal_code) document.getElementById('postal_code').value = data.postal_code;
                    if (data.country) {
                        const sel = document.getElementById('country');
                        for (let i=0;i<sel.options.length;i++) {
                            if (sel.options[i].textContent.toLowerCase() === data.country.toLowerCase()) {
                                sel.selectedIndex = i; break;
                            }
                        }
                    }
                    return;
                }
            } catch (e) {
                // fall through to heuristic fallback
            }

            // Fallback heuristic
            const tokens = raw.split(',').map(t => t.trim()).filter(Boolean);
            if (tokens.length > 0) document.getElementById('address_line1').value = tokens[0] || document.getElementById('address_line1').value;
            if (tokens.length > 1) document.getElementById('city').value = tokens[1] || '';
            if (tokens.length > 2) {
                const t2 = tokens[2];
                if (/^[0-9A-Z \-]{3,10}$/i.test(t2) && tokens.length === 3) {
                    document.getElementById('postal_code').value = t2;
                } else {
                    document.getElementById('state').value = t2;
                }
            }
            if (tokens.length > 3) document.getElementById('postal_code').value = tokens[3] || document.getElementById('postal_code').value;
            const last = raw.split(',').map(t=>t.trim()).filter(Boolean).pop();
            const sel = document.getElementById('country');
            for (let i=0;i<sel.options.length;i++) {
                if (sel.options[i].textContent.toLowerCase() === (last||'').toLowerCase()) {
                    sel.selectedIndex = i; break;
                }
            }
        }

        // parse on load
        document.addEventListener('DOMContentLoaded', parseStoredAddress);


    </script>
</body>
</html>
