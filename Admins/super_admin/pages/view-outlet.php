<?php
session_start();
require_once __DIR__ . '/../api/supabase-client.php';

// Check if user is logged in and has super_admin role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: login.php');
    exit;
}
    
$outletEmail = $_GET['email'] ?? null;
$outletId = $_GET['id'] ?? null;
    
$outlet = null;

// Try to fetch by email first (from users page)
if ($outletEmail) {
    try {
        $result = callSupabase("all_users?contact_email=eq.$outletEmail&select=*");
        if ($result && count($result) > 0) {
            $outlet = $result[0];
            $outletId = $outlet['id'] ?? null;
            if ($outletId) {
                try {
                    $fullOutlet = callSupabaseWithServiceKey("outlets?id=eq.$outletId&select=*,companies(company_name,id)", 'GET');
                    if ($fullOutlet && count($fullOutlet) > 0) {
                        $outlet = $fullOutlet[0];
                    }
                } catch (Exception $e) {
                    // Use the all_users data if the full fetch fails
                }
            }
        }
    } catch (Exception $e) {
        $outlet = null;
    }
}

// If not found by email, try by ID (from outlets page)
if (!$outlet && $outletId) {
    try {
        $result = callSupabaseWithServiceKey("outlets?id=eq.$outletId&select=*,companies(company_name,id)", 'GET');
        if ($result && count($result) > 0) {
            $outlet = $result[0];
        }
    } catch (Exception $e) {
        $outlet = null;
    }
}

// Redirect if outlet not found
if (!$outlet) {
    header('Location: outlets.php');
    exit;
}

// Determine a safe back URL and label
$backUrl = 'outlets.php';
$backLabel = 'Outlets';
if (!empty($_GET['return'])) {
    $r = basename(parse_url($_GET['return'], PHP_URL_PATH));
    if (in_array($r, ['users.php', 'companies.php', 'outlets.php'])) {
        $backUrl = $r;
        $backLabel = ucfirst(str_replace('.php', '', $r));
    }
} else {
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    if ($ref) {
        $refFile = basename(parse_url($ref, PHP_URL_PATH));
        if ($refFile === 'users.php') {
            $backUrl = 'users.php'; $backLabel = 'Users';
        } elseif ($refFile === 'companies.php') {
            $backUrl = 'companies.php'; $backLabel = 'Companies';
        } elseif ($refFile === 'outlets.php') {
            $backUrl = 'outlets.php'; $backLabel = 'Outlets';
        }
    }
}

require_once __DIR__ . '/../includes/csrf-helper.php';
$csrfToken = CSRFHelper::getToken();

$pageTitle = 'Admin - View Outlet';
require_once __DIR__ . '/../includes/header.php';

// Prepare display values
$outletName = $outlet['name'] ?? $outlet['outlet_name'] ?? 'Unknown Outlet';
$initials = mb_strtoupper(mb_substr($outletName, 0, 2));
$statusVal = strtolower($outlet['status'] ?? 'active');
$statusClass = 'status-active';
if ($statusVal === 'inactive' || $statusVal === 'suspended') $statusClass = 'status-inactive';
if ($statusVal === 'pending') $statusClass = 'status-pending';
$companyName = $outlet['companies']['company_name'] ?? 'Not assigned';
$createdAt = !empty($outlet['created_at']) ? date('d M Y, H:i', strtotime($outlet['created_at'])) : 'N/A';
$updatedAt = !empty($outlet['updated_at']) ? date('d M Y, H:i', strtotime($outlet['updated_at'])) : 'N/A';
?>

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
            border-radius: 12px;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 600;
            flex-shrink: 0;
        }
        .profile-header-info h2 { margin: 0 0 0.25rem 0; font-size: 1.5rem; }
        .profile-header-info p { margin: 0; opacity: 0.8; font-size: 0.95rem; }
        .profile-body { padding: 2rem; }
        .detail-section { margin-bottom: 1.5rem; }
        .detail-section h3 {
            font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em;
            color: #9ca3af; margin: 0 0 0.75rem 0; padding-bottom: 0.5rem;
            border-bottom: 1px solid #f3f4f6;
        }
        .detail-row {
            display: flex; justify-content: space-between; align-items: center; padding: 0.6rem 0;
        }
        .detail-row + .detail-row { border-top: 1px solid #f9fafb; }
        .detail-label { font-weight: 500; color: #6b7280; font-size: 0.9rem; }
        .detail-value {
            color: #1f2937; font-weight: 500; text-align: right;
            max-width: 60%; word-break: break-word;
        }
        .status-pill {
            display: inline-block; padding: 0.25rem 0.75rem; border-radius: 9999px;
            font-size: 0.8rem; font-weight: 600;
        }
        .status-active { background: #d1fae5; color: #065f46; }
        .status-inactive { background: #fee2e2; color: #991b1b; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .company-pill {
            display: inline-block; padding: 0.25rem 0.75rem; border-radius: 9999px;
            font-size: 0.8rem; font-weight: 600; background: #dbeafe; color: #1e40af;
        }
        .profile-actions {
            display: flex; gap: 0.75rem; padding: 1.5rem 2rem;
            border-top: 1px solid #f3f4f6; flex-wrap: wrap;
        }
        .btn-action {
            display: inline-flex; align-items: center; gap: 0.5rem;
            padding: 0.65rem 1.25rem; border: none; border-radius: 8px;
            font-weight: 500; font-size: 0.9rem; cursor: pointer;
            text-decoration: none; transition: all 0.2s;
        }
        .btn-back { background: #f3f4f6; color: #4b5563; }
        .btn-back:hover { background: #e5e7eb; }
        .btn-edit { background: #2E0D2A; color: white; }
        .btn-edit:hover { background: #4a1545; }
        .btn-delete { background: #fee2e2; color: #991b1b; margin-left: auto; }
        .btn-delete:hover { background: #fecaca; }
        .back-link {
            display: inline-flex; align-items: center; gap: 0.5rem;
            color: #6b7280; text-decoration: none; margin-bottom: 0.5rem;
        }
        .back-link:hover { color: #374151; }
        .empty-state { text-align: center; padding: 3rem 2rem; color: #6b7280; }
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
                <a href="<?php echo htmlspecialchars($backUrl); ?>" class="back-link">
                    <i class="fas fa-arrow-left"></i> Back to <?php echo htmlspecialchars($backLabel); ?>
                </a>
                <h1>Outlet Details</h1>
            </div>

            <div class="outlet-profile-card">
                <!-- Header -->
                <div class="profile-header">
                    <div class="profile-avatar">
                        <i class="fas fa-store"></i>
                    </div>
                    <div class="profile-header-info">
                        <h2><?php echo htmlspecialchars($outletName); ?></h2>
                        <p><?php echo htmlspecialchars($companyName); ?></p>
                    </div>
                </div>

                <div class="profile-body">
                    <!-- General Info -->
                    <div class="detail-section">
                        <h3>General Information</h3>
                        <div class="detail-row">
                            <span class="detail-label">Status</span>
                            <span class="detail-value">
                                <span class="status-pill <?php echo $statusClass; ?>"><?php echo htmlspecialchars(ucfirst($statusVal)); ?></span>
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Company</span>
                            <span class="detail-value">
                                <span class="company-pill"><?php echo htmlspecialchars($companyName); ?></span>
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Address</span>
                            <span class="detail-value">
                                <?php 
                                $addrParts = [];
                                if (!empty($outlet['address'])) $addrParts[] = $outlet['address'];
                                if (!empty($outlet['city'])) $addrParts[] = $outlet['city'];
                                if (!empty($outlet['state'])) $addrParts[] = $outlet['state'];
                                if (!empty($outlet['postal_code'])) $addrParts[] = $outlet['postal_code'];
                                if (!empty($outlet['country'])) $addrParts[] = $outlet['country'];
                                echo htmlspecialchars(!empty($addrParts) ? implode(', ', $addrParts) : 'N/A'); 
                                ?>
                            </span>
                        </div>
                    </div>

                    <!-- Contact Info -->
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
                        <?php if (!empty($outlet['contact_phone'])): ?>
                        <div class="detail-row">
                            <span class="detail-label">Phone</span>
                            <span class="detail-value"><?php echo htmlspecialchars($outlet['contact_phone']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- System Info -->
                    <div class="detail-section">
                        <h3>System Information</h3>
                        <div class="detail-row">
                            <span class="detail-label">Outlet ID</span>
                            <span class="detail-value" style="font-size:0.8rem; color:#9ca3af;"><?php echo htmlspecialchars($outlet['id'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Created</span>
                            <span class="detail-value"><?php echo htmlspecialchars($createdAt); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Last Updated</span>
                            <span class="detail-value"><?php echo htmlspecialchars($updatedAt); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="profile-actions">
                    <a href="<?php echo htmlspecialchars($backUrl); ?>" class="btn-action btn-back">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                    <a href="edit-outlet.php?id=<?php echo urlencode($outlet['id'] ?? ''); ?>" class="btn-action btn-edit">
                        <i class="fas fa-pen"></i> Edit Outlet
                    </a>
                    <button class="btn-action btn-delete" id="deleteOutletBtn">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </div>
            </div>
        </main>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const deleteBtn = document.getElementById('deleteOutletBtn');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', () => {
                Swal.fire({
                    title: 'Delete Outlet?',
                    html: 'Are you sure you want to delete <strong><?php echo htmlspecialchars($outletName); ?></strong>?<br><small>This action cannot be undone.</small>',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#991b1b',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: 'Yes, delete',
                    cancelButtonText: 'Cancel'
                }).then(async (result) => {
                    if (result.isConfirmed) {
                        try {
                            const response = await fetch('../api/delete_outlet_admin.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': '<?php echo htmlspecialchars($csrfToken); ?>'
                                },
                                body: JSON.stringify({ id: '<?php echo htmlspecialchars($outlet['id'] ?? ''); ?>' })
                            });
                            const data = await response.json();

                            if (data.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Deleted!',
                                    text: 'Outlet has been deleted.',
                                    confirmButtonColor: '#2e0d2a',
                                    timer: 2000,
                                    timerProgressBar: true
                                }).then(() => {
                                    window.location.href = '<?php echo htmlspecialchars($backUrl); ?>';
                                });
                            } else {
                                Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Failed to delete outlet', confirmButtonColor: '#2e0d2a' });
                            }
                        } catch (err) {
                            Swal.fire({ icon: 'error', title: 'Error', text: 'Could not connect to the server.', confirmButtonColor: '#2e0d2a' });
                        }
                    }
                });
            });
        }
    });
    </script>
</body>
</html>
