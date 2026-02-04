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
        $address = trim($_POST['address'] ?? '');
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
                $endpoint = 'outlets?id=eq.' . urlencode($outletId) . '&company_id=eq.' . urlencode($companyId) . '&select=id,company_id,outlet_name,address,contact_person,contact_email,contact_phone,status,created_at,updated_at';

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
                    <form method="post" action="company-edit-outlet.php?id=<?php echo urlencode($outletId); ?>" class="" id="editOutletForm">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($outletId); ?>" />

                        <div class="form-group">
                            <label for="outlet_name">Outlet Name</label>
                            <input type="text" id="outlet_name" name="outlet_name" value="<?php echo htmlspecialchars($outlet['outlet_name'] ?? ''); ?>" required />
                        </div>

                        <div class="form-group">
                            <label for="address">Address</label>
                            <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($outlet['address'] ?? ''); ?>" />
                        </div>

                        <div class="form-group">
                            <label for="contact_person">Contact Person</label>
                            <input type="text" id="contact_person" name="contact_person" value="<?php echo htmlspecialchars($outlet['contact_person'] ?? ''); ?>" />
                        </div>

                        <div class="form-group">
                            <label for="contact_email">Contact Email</label>
                            <input type="email" id="contact_email" name="contact_email" value="<?php echo htmlspecialchars($outlet['contact_email'] ?? ''); ?>" />
                        </div>

                        <div class="form-group">
                            <label for="contact_phone">Contact Phone</label>
                            <input type="text" id="contact_phone" name="contact_phone" value="<?php echo htmlspecialchars($outlet['contact_phone'] ?? ''); ?>" />
                        </div>

                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <option value="active" <?php echo (isset($outlet['status']) && strtolower($outlet['status']) === 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo (!isset($outlet['status']) || strtolower($outlet['status']) !== 'active') ? 'selected' : ''; ?>>Inactive</option>
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
</body>
</html>
