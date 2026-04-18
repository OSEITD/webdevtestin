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
            error_log('Error fetching driver: ' . $e->getMessage());
            $error = 'Failed to load driver details: ' . $e->getMessage();
        }
    }
?>

<body class="bg-gray-100 min-h-screen">
    <style>
        .driver-profile-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            max-width: 700px;
            margin: 2rem auto;
            overflow: hidden;
        }
        .profile-header {
            background: linear-gradient(135deg, #2E0D2A, #4a1545);
            color: white;
            padding: 2rem;
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        .profile-avatar {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            flex-shrink: 0;
        }
        .profile-header-info h2 {
            margin: 0 0 0.25rem 0;
            font-size: 1.5rem;
        }
        .profile-header-info p {
            margin: 0;
            opacity: 0.8;
            font-size: 0.95rem;
        }
        .profile-body {
            padding: 2rem;
        }
        .detail-section {
            margin-bottom: 1.5rem;
        }
        .detail-section h3 {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #9ca3af;
            margin: 0 0 0.75rem 0;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #f3f4f6;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.6rem 0;
        }
        .detail-row + .detail-row {
            border-top: 1px solid #f9fafb;
        }
        .detail-label {
            font-weight: 500;
            color: #6b7280;
            font-size: 0.9rem;
        }
        .detail-value {
            color: #1f2937;
            font-weight: 500;
            text-align: right;
            max-width: 60%;
            word-break: break-word;
        }
        .status-pill {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .status-available { background: #d1fae5; color: #065f46; }
        .status-unavailable { background: #fee2e2; color: #991b1b; }
        .profile-actions {
            display: flex;
            gap: 0.75rem;
            padding: 1.5rem 2rem;
            border-top: 1px solid #f3f4f6;
            flex-wrap: wrap;
        }
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.65rem 1.25rem;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.9rem;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-back {
            background: #f3f4f6;
            color: #4b5563;
        }
        .btn-back:hover { background: #e5e7eb; }
        .btn-edit {
            background: #2E0D2A;
            color: white;
        }
        .btn-edit:hover { background: #4a1545; }
        .btn-delete {
            background: #fee2e2;
            color: #991b1b;
            margin-left: auto;
        }
        .btn-delete:hover { background: #fecaca; }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #6b7280;
            text-decoration: none;
            margin-bottom: 0.5rem;
        }
        .back-link:hover { color: #374151; }
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #6b7280;
        }
        .empty-state i { font-size: 3rem; margin-bottom: 1rem; color: #d1d5db; }
        @media (max-width: 640px) {
            .profile-header { flex-direction: column; text-align: center; }
            .detail-row { flex-direction: column; align-items: flex-start; gap: 0.25rem; }
            .detail-value { text-align: left; max-width: 100%; }
            .profile-actions { flex-direction: column; }
            .btn-delete { margin-left: 0; }
        }
    </style>

    <div class="mobile-dashboard">
        <main class="main-content">
            <div class="content-header">
                <a href="drivers.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Back to Drivers
                </a>
                <h1>Driver Details</h1>
            </div>

            <?php if ($error): ?>
                <div class="driver-profile-card">
                    <div class="empty-state">
                        <i class="fas fa-exclamation-circle"></i>
                        <p><?php echo htmlspecialchars($error); ?></p>
                        <a href="drivers.php" class="btn-action btn-back" style="display:inline-flex; margin-top:1rem;">
                            <i class="fas fa-arrow-left"></i> Back to Drivers
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="driver-profile-card">
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="profile-header-info">
                            <h2><?php echo htmlspecialchars($driver['driver_name'] ?? 'Unnamed Driver'); ?></h2>
                            <p><?php echo htmlspecialchars($driver['driver_email'] ?? 'No email'); ?></p>
                        </div>
                    </div>

                    <div class="profile-body">
                        <!-- Driver Information -->
                        <div class="detail-section">
                            <h3>Driver Information</h3>
                            <div class="detail-row">
                                <span class="detail-label">Driver Name</span>
                                <span class="detail-value"><?php echo htmlspecialchars($driver['driver_name'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Status</span>
                                <span class="detail-value">
                                    <span class="status-pill <?php echo strtolower($driver['status'] ?? 'unavailable') === 'available' ? 'status-available' : 'status-unavailable'; ?>">
                                        <?php echo htmlspecialchars(ucfirst($driver['status'] ?? 'Unavailable')); ?>
                                    </span>
                                </span>
                            </div>
                        </div>

                        <!-- Contact Information -->
                        <div class="detail-section">
                            <h3>Contact Information</h3>
                            <div class="detail-row">
                                <span class="detail-label">Email</span>
                                <span class="detail-value"><?php echo htmlspecialchars($driver['driver_email'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Phone</span>
                                <span class="detail-value"><?php echo htmlspecialchars($driver['driver_phone'] ?? 'N/A'); ?></span>
                            </div>
                        </div>

                        <!-- License Information -->
                        <?php if (!empty($driver['license_number'])): ?>
                        <div class="detail-section">
                            <h3>License Information</h3>
                            <div class="detail-row">
                                <span class="detail-label">License Number</span>
                                <span class="detail-value"><?php echo htmlspecialchars($driver['license_number']); ?></span>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Location Information -->
                        <div class="detail-section">
                            <h3>Location Information</h3>
                            <div class="detail-row">
                                <span class="detail-label">Address Line 1</span>
                                <span class="detail-value"><?php echo htmlspecialchars($driver['address'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">City</span>
                                <span class="detail-value"><?php echo htmlspecialchars($driver['city'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">State / Province</span>
                                <span class="detail-value"><?php echo htmlspecialchars($driver['state'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Postal Code</span>
                                <span class="detail-value"><?php echo htmlspecialchars($driver['postal_code'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Country</span>
                                <span class="detail-value"><?php echo htmlspecialchars($driver['country'] ?? 'N/A'); ?></span>
                            </div>
                        </div>

                        <!-- Activity Information -->
                        <div class="detail-section">
                            <h3>Activity Information</h3>
                            <?php if (isset($driver['latitude']) && isset($driver['longitude'])): ?>
                            <div class="detail-row">
                                <span class="detail-label">Current Location</span>
                                <span class="detail-value" style="font-size:0.85rem;"><?php echo htmlspecialchars($driver['latitude'] . ', ' . $driver['longitude']); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="detail-row">
                                <span class="detail-label">Deliveries Completed</span>
                                <span class="detail-value"><?php echo htmlspecialchars($driver['deliveries_completed'] ?? 0); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Last Activity</span>
                                <span class="detail-value"><?php echo isset($driver['updated_at']) ? date('d M Y, H:i', strtotime($driver['updated_at'])) : 'N/A'; ?></span>
                            </div>
                        </div>

                        <!-- System Information -->
                        <div class="detail-section">
                            <h3>System Information</h3>
                            <div class="detail-row">
                                <span class="detail-label">Driver ID</span>
                                <span class="detail-value" style="font-size:0.8rem; color:#9ca3af;"><?php echo htmlspecialchars($driver['id'] ?? 'N/A'); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="profile-actions">
                        <a href="drivers.php" class="btn-action btn-back">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                        <a href="company-edit-driver.php?id=<?php echo urlencode($driverId); ?>" class="btn-action btn-edit">
                            <i class="fas fa-pen"></i> Edit Driver
                        </a>
                        <button class="btn-action btn-delete" id="deleteDriverBtn">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Link to the external JavaScript file -->
    <script src="../assets/js/company-scripts.js"></script>
    <script>
    (function(){
        const delBtn = document.getElementById('deleteDriverBtn');
        if (!delBtn) return;
        delBtn.addEventListener('click', async function(){
            const result = await Swal.fire({
                title: 'Delete Driver?',
                text: 'Are you sure you want to delete this driver?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e74c3c',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it',
                cancelButtonText: 'Cancel'
            });
            if (!result.isConfirmed) return;
            try {
                delBtn.disabled = true;
                const res = await fetch('../api/delete_driver.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ id: '<?php echo addslashes($driverId); ?>' })
                });
                const json = await res.json();
                if (json && json.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Deleted!',
                        text: 'Driver deleted successfully.',
                        confirmButtonColor: '#2e0d2a',
                        timer: 2500,
                        timerProgressBar: true
                    }).then(() => {
                        window.location.href = 'drivers.php';
                    });
                    return;
                }
                throw new Error(json && json.error ? json.error : 'Failed to delete driver');
            } catch (err) {
                console.error('Delete driver error', err);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Could not delete driver: ' + (err.message || err),
                    confirmButtonColor: '#2e0d2a'
                });
                delBtn.disabled = false;
            }
        });
    })();
    </script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
