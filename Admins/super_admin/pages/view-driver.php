<?php
session_start();
require_once __DIR__ . '/../api/supabase-client.php';

// Check if user is logged in and has super_admin role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: login.php');
    exit;
}

$error = null;
$driver = null;
$companyName = null;

$driver_email = $_GET['email'] ?? null;
$driver_id = $_GET['id'] ?? null;

if (!$driver_email && !$driver_id) {
    $error = 'No driver specified.';
} else {
    try {
        if ($driver_email) {
            $drivers = callSupabaseWithServiceKey("profiles?email=eq." . urlencode($driver_email) . "&select=*", 'GET');
        } elseif ($driver_id) {
            $drivers = callSupabaseWithServiceKey("profiles?id=eq." . urlencode($driver_id) . "&select=*", 'GET');
        }

        if (!empty($drivers)) {
            $driver = $drivers[0];
            // Fetch company name if company_id exists
            if (!empty($driver['company_id'])) {
                $companies = callSupabaseWithServiceKey('companies?id=eq.' . urlencode($driver['company_id']) . '&select=company_name', 'GET');
                if (!empty($companies)) {
                    $companyName = $companies[0]['company_name'] ?? null;
                }
            }
        } else {
            // Fallback to all_users view
            if ($driver_email) {
                $drivers = callSupabase("all_users?contact_email=eq." . urlencode($driver_email) . "&select=*");
            } elseif ($driver_id) {
                $drivers = callSupabase("all_users?id=eq." . urlencode($driver_id) . "&select=*");
            }
            if (!empty($drivers)) {
                $driver = $drivers[0];
                $companyName = $driver['company_name'] ?? null;
            } else {
                $error = 'Driver not found.';
            }
        }
    } catch (Exception $e) {
        error_log('Error fetching driver: ' . $e->getMessage());
        $error = 'Failed to load driver details.';
    }
}

$pageTitle = 'Admin - View Driver';
require_once __DIR__ . '/../includes/header.php';

// Prepare display values
if ($driver) {
    $fullName = $driver['full_name'] ?? $driver['name'] ?? 'Unknown Driver';
    $nameParts = explode(' ', $fullName);
    $initials = '';
    foreach ($nameParts as $part) {
        $initials .= mb_strtoupper(mb_substr($part, 0, 1));
    }
    $initials = mb_substr($initials, 0, 2);

    $statusVal = strtolower($driver['status'] ?? 'active');
    $statusClass = 'status-active';
    if ($statusVal === 'inactive' || $statusVal === 'suspended' || $statusVal === 'unavailable') $statusClass = 'status-inactive';
    if ($statusVal === 'pending') $statusClass = 'status-pending';

    $roleName = $driver['role'] ?? 'driver';
    $roleDisplay = ucwords(str_replace('_', ' ', $roleName));
    $email = $driver['email'] ?? $driver['contact_email'] ?? 'N/A';
    $phone = $driver['phone'] ?? $driver['contact_phone'] ?? 'N/A';
    $createdAt = !empty($driver['created_at']) ? date('d M Y, H:i', strtotime($driver['created_at'])) : 'N/A';
    $updatedAt = !empty($driver['updated_at']) ? date('d M Y, H:i', strtotime($driver['updated_at'])) : 'N/A';
}
?>

    <style>
        .driver-profile-card {
            background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            max-width: 700px; margin: 2rem auto; overflow: hidden;
        }
        .profile-header {
            background: linear-gradient(135deg, #2E0D2A, #4a1545); color: white;
            padding: 2rem; display: flex; align-items: center; gap: 1.5rem;
        }
        .profile-avatar {
            width: 72px; height: 72px; border-radius: 50%; background: rgba(255,255,255,0.2);
            display: flex; align-items: center; justify-content: center;
            font-size: 2rem; font-weight: 600; flex-shrink: 0;
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
        .role-pill {
            display: inline-block; padding: 0.25rem 0.75rem; border-radius: 9999px;
            font-size: 0.8rem; font-weight: 600; background: #ede9fe; color: #5b21b6;
        }
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
                <a href="users.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Back to Users
                </a>
                <h1>Driver Details</h1>
            </div>

            <?php if ($error): ?>
                <div class="driver-profile-card">
                    <div class="empty-state">
                        <i class="fas fa-user-slash"></i>
                        <p><?php echo htmlspecialchars($error); ?></p>
                        <a href="users.php" class="btn-action btn-back" style="display:inline-flex; margin-top:1rem;">
                            <i class="fas fa-arrow-left"></i> Back to Users
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="driver-profile-card">
                    <!-- Header -->
                    <div class="profile-header">
                        <div class="profile-avatar"><?php echo $initials; ?></div>
                        <div class="profile-header-info">
                            <h2><?php echo htmlspecialchars($fullName); ?></h2>
                            <p><?php echo htmlspecialchars($email); ?></p>
                        </div>
                    </div>

                    <div class="profile-body">
                        <!-- Account Info -->
                        <div class="detail-section">
                            <h3>Account Information</h3>
                            <div class="detail-row">
                                <span class="detail-label">Role</span>
                                <span class="detail-value"><span class="role-pill"><?php echo htmlspecialchars($roleDisplay); ?></span></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Status</span>
                                <span class="detail-value"><span class="status-pill <?php echo $statusClass; ?>"><?php echo htmlspecialchars(ucfirst($statusVal)); ?></span></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Driver ID</span>
                                <span class="detail-value" style="font-size:0.8rem; color:#9ca3af;"><?php echo htmlspecialchars($driver['id']); ?></span>
                            </div>
                        </div>

                        <!-- Contact Info -->
                        <div class="detail-section">
                            <h3>Contact Information</h3>
                            <div class="detail-row">
                                <span class="detail-label">Email</span>
                                <span class="detail-value"><?php echo htmlspecialchars($email); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Phone</span>
                                <span class="detail-value"><?php echo htmlspecialchars($phone); ?></span>
                            </div>
                        </div>

                        <!-- Organization -->
                        <?php if ($companyName): ?>
                        <div class="detail-section">
                            <h3>Organization</h3>
                            <div class="detail-row">
                                <span class="detail-label">Company</span>
                                <span class="detail-value"><span class="company-pill"><?php echo htmlspecialchars($companyName); ?></span></span>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Activity -->
                        <div class="detail-section">
                            <h3>Activity</h3>
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
                        <a href="users.php" class="btn-action btn-back">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                        <a href="edit-user.php?id=<?php echo urlencode($driver['id']); ?>" class="btn-action btn-edit">
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

    <script>
    <?php if ($driver): ?>
    document.addEventListener('DOMContentLoaded', () => {
        const deleteBtn = document.getElementById('deleteDriverBtn');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', () => {
                Swal.fire({
                    title: 'Delete Driver?',
                    html: 'Are you sure you want to delete <strong><?php echo htmlspecialchars($fullName); ?></strong>?<br><small>This action cannot be undone.</small>',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#991b1b',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: 'Yes, delete',
                    cancelButtonText: 'Cancel'
                }).then(async (result) => {
                    if (result.isConfirmed) {
                        try {
                            const response = await fetch('../api/delete_user.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': '<?php echo CSRFHelper::getToken(); ?>'
                                },
                                body: JSON.stringify({
                                    id: '<?php echo $driver['id']; ?>',
                                    email: '<?php echo $driver['email'] ?? $driver['contact_email'] ?? ''; ?>',
                                    role: '<?php echo $driver['role'] ?? 'driver'; ?>'
                                })
                            });
                            const data = await response.json();

                            if (data.success) {
                                Swal.fire({
                                    icon: 'success', title: 'Deleted!', text: 'Driver has been deleted.',
                                    confirmButtonColor: '#2e0d2a', timer: 2000, timerProgressBar: true
                                }).then(() => {
                                    window.location.href = 'users.php';
                                });
                            } else {
                                Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Failed to delete driver', confirmButtonColor: '#2e0d2a' });
                            }
                        } catch (err) {
                            Swal.fire({ icon: 'error', title: 'Error', text: 'Could not connect to the server.', confirmButtonColor: '#2e0d2a' });
                        }
                    }
                });
            });
        }
    });
    <?php endif; ?>
    </script>
</body>
</html>
