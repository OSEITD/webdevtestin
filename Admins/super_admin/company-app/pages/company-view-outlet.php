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
            $endpoint = 'outlets?id=eq.' . urlencode($outletId) . '&company_id=eq.' . urlencode($companyId) . '&deleted_at=is.null&select=company_id,outlet_name,address,city,state,postal_code,country,contact_person,contact_email,contact_phone,status,created_at,updated_at';

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
    <style>
        .outlet-profile-card {
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
        .status-active { background: #d1fae5; color: #065f46; }
        .status-inactive { background: #fee2e2; color: #991b1b; }
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
                <a href="outlets.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Back to Outlets
                </a>
                <h1>Outlet Details</h1>
            </div>

            <?php if ($error): ?>
                <div class="outlet-profile-card">
                    <div class="empty-state">
                        <i class="fas fa-exclamation-circle"></i>
                        <p><?php echo htmlspecialchars($error); ?></p>
                        <a href="outlets.php" class="btn-action btn-back" style="display:inline-flex; margin-top:1rem;">
                            <i class="fas fa-arrow-left"></i> Back to Outlets
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="outlet-profile-card">
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <i class="fas fa-store"></i>
                        </div>
                        <div class="profile-header-info">
                            <h2><?php echo htmlspecialchars($outlet['outlet_name'] ?? 'Unnamed Outlet'); ?></h2>
                            <p>
                                <?php 
                                    $addrParts = [];
                                    if (!empty($outlet['address'])) $addrParts[] = $outlet['address'];
                                    if (!empty($outlet['city'])) $addrParts[] = $outlet['city'];
                                    if (!empty($outlet['state'])) $addrParts[] = $outlet['state'];
                                    if (!empty($outlet['postal_code'])) $addrParts[] = $outlet['postal_code'];
                                    if (!empty($outlet['country'])) $addrParts[] = $outlet['country'];
                                    echo htmlspecialchars(!empty($addrParts) ? implode(', ', $addrParts) : 'No address provided'); 
                                ?>
                            </p>
                        </div>
                    </div>

                    <div class="profile-body">
                        <!-- Outlet Information -->
                        <div class="detail-section">
                            <h3>Outlet Information</h3>
                            <div class="detail-row">
                                <span class="detail-label">Outlet Name</span>
                                <span class="detail-value"><?php echo htmlspecialchars($outlet['outlet_name'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Status</span>
                                <span class="detail-value"><span class="status-pill <?php echo isset($outlet['status']) && strtolower($outlet['status']) === 'active' ? 'status-active' : 'status-inactive'; ?>"><?php echo htmlspecialchars(ucfirst($outlet['status'] ?? 'inactive')); ?></span></span>
                            </div>
                        </div>

                        <!-- Location Information -->
                        <div class="detail-section">
                            <h3>Location Information</h3>
                            <div class="detail-row">
                                <span class="detail-label">Address Line 1</span>
                                <span class="detail-value"><?php echo htmlspecialchars($outlet['address'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">City</span>
                                <span class="detail-value"><?php echo htmlspecialchars($outlet['city'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">State / Province</span>
                                <span class="detail-value"><?php echo htmlspecialchars($outlet['state'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Postal Code</span>
                                <span class="detail-value"><?php echo htmlspecialchars($outlet['postal_code'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Country</span>
                                <span class="detail-value"><?php echo htmlspecialchars($outlet['country'] ?? 'N/A'); ?></span>
                            </div>
                        </div>

                        <!-- Contact Information -->
                        <div class="detail-section">
                            <h3>Contact Information</h3>
                            <div class="detail-row">
                                <span class="detail-label">Contact Person</span>
                                <span class="detail-value"><?php echo htmlspecialchars($outlet['contact_person'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Email</span>
                                <span class="detail-value"><?php echo htmlspecialchars($outlet['contact_email'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Phone</span>
                                <span class="detail-value"><?php echo htmlspecialchars($outlet['contact_phone'] ?? 'N/A'); ?></span>
                            </div>
                        </div>

                        <!-- Timestamps -->
                        <div class="detail-section">
                            <h3>Activity</h3>
                            <div class="detail-row">
                                <span class="detail-label">Created</span>
                                <span class="detail-value"><?php echo isset($outlet['created_at']) ? date('d M Y, H:i', strtotime($outlet['created_at'])) : 'N/A'; ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Last Updated</span>
                                <span class="detail-value"><?php echo isset($outlet['updated_at']) ? date('d M Y, H:i', strtotime($outlet['updated_at'])) : 'N/A'; ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="profile-actions">
                        <a href="outlets.php" class="btn-action btn-back">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                        <a href="company-edit-outlet.php?id=<?php echo urlencode($outletId); ?>" class="btn-action btn-edit">
                            <i class="fas fa-pen"></i> Edit Outlet
                        </a>
                        <button class="btn-action btn-delete" id="deleteOutletBtn">
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
        const delBtn = document.getElementById('deleteOutletBtn');
        if (!delBtn) return;
        delBtn.addEventListener('click', async function(){
            const result = await Swal.fire({
                title: 'Delete Outlet?',
                text: 'Are you sure you want to delete this outlet?',
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
                const res = await fetch('../api/delete_outlet.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ id: '<?php echo addslashes($outletId); ?>' })
                });
                const json = await res.json();
                if (json && json.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Deleted!',
                        text: 'Outlet deleted successfully.',
                        confirmButtonColor: '#2e0d2a',
                        timer: 2500,
                        timerProgressBar: true
                    }).then(() => {
                        window.location.href = 'outlets.php';
                    });
                    return;
                }
                throw new Error(json && json.error ? json.error : 'Failed to delete outlet');
            } catch (err) {
                console.error('Delete outlet error', err);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Could not delete outlet: ' + (err.message || err),
                    confirmButtonColor: '#2e0d2a'
                });
                delBtn.disabled = false;
            }
        });
    })();
    </script>
</body>
</html>
