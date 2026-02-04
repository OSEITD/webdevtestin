<?php
    $page_title = 'Company - Edit Vehicle';

    // Ensure session and supabase client are available before any output
    require_once __DIR__ . '/../api/supabase-client.php';
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    // Start output buffering to avoid "headers already sent" errors and allow safe redirects
    if (!ob_get_level()) {
        ob_start();
    }

    include __DIR__ . '/../includes/header.php';

    $error = null;
    $vehicle = null;

    $companyId = $_SESSION['id'] ?? null;
    $accessToken = $_SESSION['access_token'] ?? null;
    $vehicleId = isset($_GET['id']) ? trim($_GET['id']) : null;

    if (!$companyId) {
        $error = 'Not authenticated. Please log in.';
    }

    // Allowed status values: follow DB check constraint. Include legacy terms for normalization.
    $allowedStatuses = ['available', 'unavailable', 'out_for_delivery', 'active', 'inactive', 'maintenance', 'assigned'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
        $vehicleId = $_POST['id'] ?? $vehicleId;
        $name = trim($_POST['name'] ?? '');
        $plate = trim($_POST['plate_number'] ?? '');
        $status = trim(strtolower($_POST['status'] ?? 'inactive'));

        if (empty($vehicleId) || empty($name) || empty($plate)) {
            $error = 'Vehicle ID, name and plate number are required.';
        } elseif (!in_array($status, $allowedStatuses, true)) {
            $error = 'Invalid status selected.';
        } else {
            try {
                $supabase = new SupabaseClient();

                // Normalize legacy statuses to canonical DB values
                $statusMap = [
                    'active' => 'available',
                    'inactive' => 'unavailable',
                    'maintenance' => 'maintenance',
                    'assigned' => 'out_for_delivery'
                ];

                if (isset($statusMap[$status])) {
                    $status = $statusMap[$status];
                }

                $payload = [
                    'name' => $name,
                    'plate_number' => $plate,
                    'status' => $status,
                    'updated_at' => date('Y-m-d H:i:s')
                ];

                $path = "vehicle?id=eq.{$vehicleId}&company_id=eq.{$companyId}";
                $res = $supabase->put($path, $payload);

                // Build a directory-aware redirect
                $currentDir = dirname($_SERVER['PHP_SELF']);
                $redirectUrl = rtrim($currentDir, '/') . '/company-vehicles.php';

                // Clear output buffers to avoid partial output
                while (ob_get_level()) { @ob_end_clean(); }

                // Use a JS + meta-refresh fallback redirect so navigation works even if headers
                // were already sent by an include. This is robust for local development.
                $safeUrl = htmlspecialchars($redirectUrl, ENT_QUOTES);
                $jsUrl = addslashes($redirectUrl);
                echo '<!doctype html><html><head><meta http-equiv="refresh" content="0;url=' . $safeUrl . '">';
                echo '<script>window.location.replace("' . $jsUrl . '");</script></head><body>If you are not redirected, <a href="' . $safeUrl . '">click here</a>.</body></html>';
                exit;
            } catch (Exception $e) {
                error_log('Error updating vehicle: ' . $e->getMessage());
                $error = 'Failed to update vehicle: ' . $e->getMessage();
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && !$error) {
        if (empty($vehicleId)) {
            $error = 'No vehicle specified.';
        } else {
            try {
                $supabase = new SupabaseClient();
                $endpoint = 'vehicle?id=eq.' . urlencode($vehicleId) . '&company_id=eq.' . urlencode($companyId) . '&select=id,name,plate_number,status,updated_at';

                if ($accessToken) {
                    $res = $supabase->getWithToken($endpoint, $accessToken);
                } else {
                    $res = $supabase->getRecord($endpoint, true);
                }

                if (is_array($res) && count($res) > 0) {
                    $vehicle = $res[0];
                } else {
                    $error = 'Vehicle not found or access denied.';
                }
            } catch (Exception $e) {
                error_log('Error fetching vehicle for edit: ' . $e->getMessage());
                $error = 'Failed to load vehicle details: ' . $e->getMessage();
            }
        }
    }
?>

<body class="bg-gray-100 min-h-screen">
    <div class="mobile-dashboard">
        <main class="main-content">
            <div class="content-header">
                <h1>Edit Vehicle</h1>
            </div>

            <div class="form-card">
                <?php if ($error): ?>
                    <div class="form-group">
                        <label>Error</label>
                        <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                    </div>
                    <div class="form-actions">
                        <button class="action-btn secondary" onclick="window.location.href='company-vehicles.php'">
                            <i class="fas fa-arrow-left"></i> Back to Vehicles
                        </button>
                    </div>
                <?php else: ?>
                    <form method="post" action="company-edit-vehicle.php?id=<?php echo urlencode($vehicleId); ?>" id="editVehicleForm">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($vehicleId); ?>" />

                        <div class="form-group">
                            <label for="name">Vehicle Name</label>
                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($vehicle['name'] ?? ''); ?>" required />
                        </div>

                        <div class="form-group">
                            <label for="plate_number">Plate Number</label>
                            <input type="text" id="plate_number" name="plate_number" value="<?php echo htmlspecialchars($vehicle['plate_number'] ?? ''); ?>" required />
                        </div>

                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <option value="available" <?php echo (isset($vehicle['status']) && strtolower($vehicle['status']) === 'available') ? 'selected' : ''; ?>>Available</option>
                                <option value="unavailable" <?php echo (isset($vehicle['status']) && strtolower($vehicle['status']) === 'unavailable') ? 'selected' : ''; ?>>Unavailable</option>
                                <option value="out_for_delivery" <?php echo (isset($vehicle['status']) && strtolower($vehicle['status']) === 'out_for_delivery') ? 'selected' : ''; ?>>Out for Delivery</option>
                            </select>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="action-btn">Save Changes</button>
                            <button type="button" class="action-btn secondary" onclick="window.location.href='company-vehicles.php'">Cancel</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="../assets/js/company-scripts.js"></script>
</body>
</html>
