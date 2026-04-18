<?php
session_start();
require_once __DIR__ . '/../api/supabase-client.php';

// Check if user is logged in and has super_admin role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: login.php');
    exit;
}

$companyEmail = isset($_GET['email']) ? $_GET['email'] : null;
$companyId = isset($_GET['id']) ? $_GET['id'] : null;

$company = null;

// Try to fetch by email first (from users page)
if ($companyEmail) {
    try {
        $companies = callSupabase("all_users?contact_email=eq.{$companyEmail}&select=*");
        if (!empty($companies)) {
            $company = $companies[0];
            $companyId = $company['id'] ?? null;
        }
    } catch (Exception $e) {
        $company = null;
    }
}

// If not found by email, try by ID (from companies page)
if (!$company && $companyId) {
    try {
        $companies = callSupabase("companies?id=eq.{$companyId}&select=*");
        if (!empty($companies)) {
            $company = $companies[0];
        }
    } catch (Exception $e) {
        $company = null;
    }
}

// Redirect if company not found
if (!$company) {
    header('Location: companies.php');
    exit;
}

// Back URL logic
$backUrl = 'companies.php';
$backLabel = 'Companies';
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
        if ($refFile === 'users.php') { $backUrl = 'users.php'; $backLabel = 'Users'; }
        elseif ($refFile === 'companies.php') { $backUrl = 'companies.php'; $backLabel = 'Companies'; }
        elseif ($refFile === 'outlets.php') { $backUrl = 'outlets.php'; $backLabel = 'Outlets'; }
    }
}

require_once __DIR__ . '/../includes/csrf-helper.php';
$csrfToken = CSRFHelper::getToken();
require_once __DIR__ . '/../includes/currency-helper.php';

$pageTitle = 'Admin - View Company';
require_once __DIR__ . '/../includes/header.php';

// Prepare display values
$companyName = $company['name'] ?? $company['company_name'] ?? 'Unknown Company';
$initials = mb_strtoupper(mb_substr($companyName, 0, 2));
$statusVal = strtolower($company['status'] ?? 'active');
$statusClass = 'status-active';
if ($statusVal === 'inactive' || $statusVal === 'suspended') $statusClass = 'status-inactive';
if ($statusVal === 'pending') $statusClass = 'status-pending';
$commRate = $company['commission_rate'] ?? 0;
$createdAt = !empty($company['created_at']) ? date('d M Y, H:i', strtotime($company['created_at'])) : 'N/A';
$updatedAt = !empty($company['updated_at']) ? date('d M Y, H:i', strtotime($company['updated_at'])) : 'N/A';

// Fetch stats
$totalDeliveries = 0;
$activeOutlets = 0;
$activeDrivers = 0;
try {
    $d = callSupabase("parcels?company_id=eq.{$companyId}&select=id");
    $totalDeliveries = is_array($d) ? count($d) : 0;
} catch (Exception $e) {}
try {
    $o = callSupabase("outlets?company_id=eq.{$companyId}&status=eq.active&select=id");
    $activeOutlets = is_array($o) ? count($o) : 0;
} catch (Exception $e) {}
try {
    $dr = callSupabase("drivers?company_id=eq.{$companyId}&status=eq.available&select=id");
    $activeDrivers = is_array($dr) ? count($dr) : 0;
} catch (Exception $e) {}
?>

    <style>
        .company-profile-card {
            background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            max-width: 700px; margin: 2rem auto; overflow: hidden;
        }
        .profile-header {
            background: linear-gradient(135deg, #2E0D2A, #4a1545); color: white;
            padding: 2rem; display: flex; align-items: center; gap: 1.5rem;
        }
        .profile-avatar {
            width: 72px; height: 72px; border-radius: 12px; background: rgba(255,255,255,0.2);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; font-weight: 600; flex-shrink: 0;
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

        /* Stats grid */
        .stats-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 0.5rem;
        }
        .stat-card {
            background: #f9fafb; border-radius: 10px; padding: 1rem 1.25rem;
            display: flex; align-items: center; gap: 0.75rem;
        }
        .stat-icon {
            width: 40px; height: 40px; border-radius: 8px; display: flex;
            align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0;
        }
        .stat-icon.deliveries { background: #dbeafe; color: #1d4ed8; }
        .stat-icon.outlets { background: #d1fae5; color: #065f46; }
        .stat-icon.revenue { background: #fef3c7; color: #92400e; }
        .stat-icon.drivers { background: #ede9fe; color: #5b21b6; }
        .stat-info .stat-value { font-size: 1.25rem; font-weight: 700; color: #1f2937; }
        .stat-info .stat-label { font-size: 0.75rem; color: #6b7280; }

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
        @media (max-width: 640px) {
            .profile-header { flex-direction: column; text-align: center; }
            .detail-row { flex-direction: column; align-items: flex-start; gap: 0.25rem; }
            .detail-value { text-align: left; max-width: 100%; }
            .stats-grid { grid-template-columns: 1fr; }
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
                <h1>Company Details</h1>
            </div>

            <div class="company-profile-card">
                <!-- Header -->
                <div class="profile-header">
                    <div class="profile-avatar">
                        <i class="fas fa-building"></i>
                    </div>
                    <div class="profile-header-info">
                        <h2><?php echo htmlspecialchars($companyName); ?></h2>
                        <p><?php echo htmlspecialchars($company['contact_email'] ?? ''); ?></p>
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
                            <span class="detail-label">Address</span>
                            <span class="detail-value">
                                <?php 
                                $addrParts = [];
                                if (!empty($company['address'])) $addrParts[] = $company['address'];
                                if (!empty($company['city'])) $addrParts[] = $company['city'];
                                if (!empty($company['state'])) $addrParts[] = $company['state'];
                                if (!empty($company['postal_code'])) $addrParts[] = $company['postal_code'];
                                if (!empty($company['country'])) $addrParts[] = $company['country'];
                                echo htmlspecialchars(!empty($addrParts) ? implode(', ', $addrParts) : 'N/A'); 
                                ?>
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Commission Rate</span>
                            <span class="detail-value"><strong><?php echo number_format($commRate, 2); ?>%</strong></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Currency</span>
                            <span class="detail-value"><?php echo htmlspecialchars($company['currency'] ?? 'N/A'); ?></span>
                        </div>
                    </div>

                    <!-- Contact Info -->
                    <div class="detail-section">
                        <h3>Contact Information</h3>
                        <div class="detail-row">
                            <span class="detail-label">Email</span>
                            <span class="detail-value"><?php echo htmlspecialchars($company['contact_email'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Phone</span>
                            <span class="detail-value"><?php echo htmlspecialchars($company['contact_phone'] ?? 'N/A'); ?></span>
                        </div>
                    </div>

                    <!-- Company Statistics -->
                    <div class="detail-section">
                        <h3>Company Statistics</h3>
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-icon deliveries"><i class="fas fa-truck"></i></div>
                                <div class="stat-info">
                                    <div class="stat-value"><?php echo $totalDeliveries; ?></div>
                                    <div class="stat-label">Total Deliveries</div>
                                </div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon outlets"><i class="fas fa-store"></i></div>
                                <div class="stat-info">
                                    <div class="stat-value"><?php echo $activeOutlets; ?></div>
                                    <div class="stat-label">Active Outlets</div>
                                </div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon revenue"><i class="fas fa-coins"></i></div>
                                <div class="stat-info">
                                    <div class="stat-value" <?php echo getCurrencyDataAttributes($company['revenue'] ?? 0, $company['currency'] ?? null, $companyId); ?>>
                                        <?php echo formatCurrency($company['revenue'] ?? 0, $company['currency'] ?? null, $companyId); ?>
                                    </div>
                                    <div class="stat-label">Total Revenue</div>
                                </div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon drivers"><i class="fas fa-id-badge"></i></div>
                                <div class="stat-info">
                                    <div class="stat-value"><?php echo $activeDrivers; ?></div>
                                    <div class="stat-label">Active Drivers</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- System Info -->
                    <div class="detail-section">
                        <h3>System Information</h3>
                        <div class="detail-row">
                            <span class="detail-label">Company ID</span>
                            <span class="detail-value" style="font-size:0.8rem; color:#9ca3af;"><?php echo htmlspecialchars($company['id'] ?? 'N/A'); ?></span>
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
                    <a href="edit-company.php?id=<?php echo urlencode($companyId); ?>" class="btn-action btn-edit">
                        <i class="fas fa-pen"></i> Edit Company
                    </a>
                    <button class="btn-action btn-delete" id="deleteCompanyBtn">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </div>
            </div>
        </main>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const deleteBtn = document.getElementById('deleteCompanyBtn');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', () => {
                Swal.fire({
                    title: 'Delete Company?',
                    html: 'Are you sure you want to delete <strong><?php echo htmlspecialchars($companyName); ?></strong>?<br><small>This action cannot be undone.</small>',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#991b1b',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: 'Yes, delete',
                    cancelButtonText: 'Cancel'
                }).then(async (result) => {
                    if (result.isConfirmed) {
                        try {
                            const response = await fetch('../api/delete_company.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': '<?php echo htmlspecialchars($csrfToken); ?>'
                                },
                                body: JSON.stringify({ id: '<?php echo htmlspecialchars($companyId); ?>' })
                            });
                            const data = await response.json();

                            if (data.success) {
                                Swal.fire({
                                    icon: 'success', title: 'Deleted!', text: 'Company has been deleted.',
                                    confirmButtonColor: '#2e0d2a', timer: 2000, timerProgressBar: true
                                }).then(() => {
                                    window.location.href = '<?php echo htmlspecialchars($backUrl); ?>';
                                });
                            } else {
                                Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Failed to delete company', confirmButtonColor: '#2e0d2a' });
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
<?php include __DIR__ . '/../includes/footer.php'; ?>
