<?php
    $page_title = 'Company - View Outlet';

    // Load Supabase client and start session before any output
    require_once __DIR__ . '/../api/supabase-client.php';
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    // Start output buffering to avoid accidental "headers already sent" issues
    if (!ob_get_level()) {
        ob_start();
    }

    include __DIR__ . '/../includes/header.php';

    $error = null;
    $outlet = null;

    $companyId = $_SESSION['id'] ?? null;
    $accessToken = $_SESSION['access_token'] ?? null;
    $outletId = isset($_GET['id']) ? trim($_GET['id']) : null;

    if (!$companyId) {
        $error = 'Not authenticated. Please log in.';
    } elseif (!$outletId) {
        $error = 'No outlet specified.';
    } else {
        try {
            $supabase = new SupabaseClient();

            // Preferred: query using the session access token so RLS applies.
            $endpoint = 'outlets?id=eq.' . urlencode($outletId) . '&company_id=eq.' . urlencode($companyId) . '&select=company_id,outlet_name,address,contact_person,contact_email,contact_phone,status,created_at,updated_at';

            if ($accessToken) {
                $res = $supabase->getWithToken($endpoint, $accessToken);
            } else {
                // Fallback to service-role key if available
                $res = $supabase->getRecord($endpoint, true);
            }

            // Normalize response - getWithToken may return array of rows
            if (is_array($res) && count($res) > 0) {
                $outlet = $res[0];
            } else {
                $outlet = null;
                $error = 'Outlet not found or access denied.';
            }
        } catch (Exception $e) {
            // Log details for server-side debugging
            error_log('Error fetching outlet: ' . $e->getMessage());
            error_log('Outlet endpoint: ' . $endpoint);
            error_log('Access token present: ' . ($accessToken ? 'yes' : 'no'));

            // Surface the exception message to the page for local debugging.
            // Remove or simplify this in production to avoid leaking details.
            $error = 'Failed to load outlet details: ' . $e->getMessage();
        }
    }
?>

<body class="bg-gray-100 min-h-screen">
    <div class="mobile-dashboard">
        <!-- Main Content Area for Outlet Details -->
        <main class="main-content">
            <div class="content-header">
                <h1>Outlet Details</h1>
            </div>

            <div class="details-card">
                <?php if ($error): ?>
                    <div class="detail-item">
                        <span class="label">Error:</span>
                        <span class="value"><?php echo htmlspecialchars($error); ?></span>
                    </div>
                    <div class="details-button-group">
                        <button class="action-btn secondary" onclick="window.location.href='outlets.php'">
                            <i class="fas fa-arrow-left"></i> Back to Outlets
                        </button>
                    </div>
                <?php else: ?>
                    <h2><?php echo htmlspecialchars($outlet['outlet_name'] ?? 'Unnamed Outlet'); ?></h2>

                    <div class="detail-item">
                        <span class="label">Address:</span>
                        <span class="value"><?php echo htmlspecialchars($outlet['address'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="label">Contact Person:</span>
                        <span class="value"><?php echo htmlspecialchars($outlet['contact_person'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="label">Phone Number:</span>
                        <span class="value"><?php echo htmlspecialchars($outlet['contact_phone'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="label">Email:</span>
                        <span class="value"><?php echo htmlspecialchars($outlet['contact_email'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="label">Status:</span>
                        <span class="value"><span class="status-badge <?php echo isset($outlet['status']) && strtolower($outlet['status']) === 'active' ? 'status-active' : 'status-inactive'; ?>"><?php echo htmlspecialchars($outlet['status'] ?? 'inactive'); ?></span></span>
                    </div>
                    <div class="detail-item">
                        <span class="label">Last Updated:</span>
                        <span class="value"><?php echo isset($outlet['updated_at']) ? htmlspecialchars(date('Y-m-d H:i', strtotime($outlet['updated_at']))) : 'N/A'; ?></span>
                    </div>
                    <!-- description field removed (not present in DB) -->

                    <div class="details-button-group">
                        <button class="action-btn secondary" onclick="window.location.href='outlets.php'">
                            <i class="fas fa-arrow-left"></i> Back to Outlets
                        </button>
                        <button class="action-btn" id="editOutletBtn" onclick="window.location.href='company-edit-outlet.php?id=<?php echo urlencode($outletId); ?>'">
                            <i class="fas fa-edit"></i> Edit Outlet
                        </button>
                        <button class="action-btn danger" id="deleteOutletBtn">
                            <i class="fas fa-trash"></i> Delete Outlet
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
        const delBtn = document.getElementById('deleteOutletBtn');
        if (!delBtn) return;
        delBtn.addEventListener('click', async function(){
            if (!confirm('Are you sure you want to delete this outlet? This action cannot be undone.')) return;
            try {
                delBtn.disabled = true;
                const res = await fetch('../api/delete_outlet.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ id: '<?php echo addslashes($outletId); ?>' })
                });
                const json = await res.json();
                if (json && json.success) {
                    alert('Outlet deleted successfully');
                    window.location.href = 'outlets.php';
                    return;
                }
                throw new Error(json && json.error ? json.error : 'Failed to delete outlet');
            } catch (err) {
                console.error('Delete outlet error', err);
                alert('Could not delete outlet: ' + (err.message || err));
                delBtn.disabled = false;
            }
        });
    })();
    </script>
</body>
</html>