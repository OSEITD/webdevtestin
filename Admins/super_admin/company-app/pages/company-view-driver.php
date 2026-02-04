<?php
    $page_title = 'Company - View Driver';

    // Start session early so header.php and subsequent logic can rely on $_SESSION
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    require_once __DIR__ . '/../api/supabase-client.php';
    include __DIR__ . '/../includes/header.php';

    $error = null;
    $driver = null;

    $companyId = $_SESSION['id'] ?? null;
    $accessToken = $_SESSION['access_token'] ?? null;
    $driverId = isset($_GET['id']) ? trim($_GET['id']) : null;

    if (!$companyId) {
        $error = 'Not authenticated. Please log in.';
    } else if (empty($driverId)) {
        $error = 'No driver specified.';
    } else {
        try {
            $supabase = new SupabaseClient();
            $endpoint = 'drivers?id=eq.' . urlencode($driverId) . '&company_id=eq.' . urlencode($companyId) . '&select=id,driver_name,driver_email,driver_phone,license_number,status,updated_at';

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
            error_log('Error fetching driver: ' . $e->getMessage());
            $error = 'Failed to load driver details: ' . $e->getMessage();
        }
    }
?>

<body class="bg-gray-100 min-h-screen">
    <div class="mobile-dashboard">


         <!-- Main Content Area for Driver Details -->
        <main class="main-content">



            <div class="content-header">
                <h1>Driver Details</h1>
            </div>

            <div class="details-card">
                <?php if ($error): ?>
                    <div class="detail-item">
                        <span class="label">Error:</span>
                        <span class="value"><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php else: ?>
                <h2><?php echo htmlspecialchars($driver['driver_name'] ?? 'Unnamed Driver'); ?></h2>

                <div class="detail-item">
                    <span class="label">Driver ID:</span>
                    <span class="value"><?php echo htmlspecialchars($driver['id'] ?? ''); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Contact Email:</span>
                    <span class="value"><?php echo htmlspecialchars($driver['driver_email'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Contact Phone:</span>
                    <span class="value"><?php echo htmlspecialchars($driver['driver_phone'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Status:</span>
                    <span class="value"><?php
                        $s = strtolower($driver['status'] ?? 'unavailable');
                        if ($s === 'available') {
                            echo '<span class="status-badge status-active">Available</span>';
                        } else {
                            echo '<span class="status-badge status-inactive">Unavailable</span>';
                        }
                    ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Current Location:</span>
                    <span class="value"><?php echo isset($driver['latitude']) && isset($driver['longitude']) ? htmlspecialchars($driver['latitude'] . ', ' . $driver['longitude']) : 'N/A'; ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Total Deliveries Completed:</span>
                    <span class="value"><?php echo htmlspecialchars($driver['deliveries_completed'] ?? 0); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Last Activity:</span>
                    <span class="value"><?php echo isset($driver['updated_at']) ? htmlspecialchars(date('Y-m-d H:i', strtotime($driver['updated_at']))) : 'N/A'; ?></span>
                </div>

                <div class="details-button-group">
                    <button class="action-btn secondary" id="backToDriversBtn"  onclick="window.location.href='drivers.php'">
                        <i class="fas fa-arrow-left" ></i> Back
                    </button>
                    <button class="action-btn" id="editDriverBtn" onclick="window.location.href='company-edit-driver.php?id=<?php echo urlencode($driverId); ?>'">
                        <i class="fas fa-edit"></i> Edit Driver
                    </button>
                    <button class="action-btn danger" id="deleteDriverBtn">
                        <i class="fas fa-trash"></i> Delete Driver
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Link to the external JavaScript file -->
    <script src="../assets/js/company-scripts.js"></script>
    <script>
    (function(){
        const delBtn = document.getElementById('deleteDriverBtn');
        if (!delBtn) return;
        delBtn.addEventListener('click', async function(){
            if (!confirm('Are you sure you want to delete this driver? This action cannot be undone.')) return;
            try {
                delBtn.disabled = true;
                const res = await fetch('../api/delete_driver.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ id: '<?php echo addslashes($driverId); ?>' })
                });
                const json = await res.json();
                if (json && json.success) {
                    alert('Driver deleted successfully');
                    window.location.href = 'drivers.php';
                    return;
                }
                throw new Error(json && json.error ? json.error : 'Failed to delete driver');
            } catch (err) {
                console.error('Delete driver error', err);
                alert('Could not delete driver: ' + (err.message || err));
                delBtn.disabled = false;
            }
        });
    })();
    </script>
</body>
</html>