<?php
session_start();
require_once __DIR__ . '/../api/supabase-client.php';

// Check if user is logged in and has super_admin role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: login.php');
    exit;
}

$userId = $_GET['id'] ?? null;
$user = null;
$companyName = null;
$outletName = null;

if ($userId) {
    try {
        // Fetch user profile
        $profiles = callSupabaseWithServiceKey('profiles?id=eq.' . urlencode($userId) . '&select=*', 'GET');
        if (!empty($profiles) && is_array($profiles)) {
            $user = $profiles[0];
        }

        // If user has a company_id, fetch company name
        if ($user && !empty($user['company_id'])) {
            $companies = callSupabaseWithServiceKey('companies?id=eq.' . urlencode($user['company_id']) . '&select=company_name', 'GET');
            if (!empty($companies)) {
                $companyName = $companies[0]['company_name'] ?? null;
            }
        }

        // If user has an outlet_id, fetch outlet name
        if ($user && !empty($user['outlet_id'])) {
            $outlets = callSupabaseWithServiceKey('outlets?id=eq.' . urlencode($user['outlet_id']) . '&select=outlet_name', 'GET');
            if (!empty($outlets)) {
                $outletName = $outlets[0]['outlet_name'] ?? null;
            }
        }
    } catch (Exception $e) {
        error_log('Error fetching user: ' . $e->getMessage());
    }
}

$pageTitle = 'Admin - View User';
require_once __DIR__ . '/../includes/header.php';
?>

    <style>
        .user-profile-card {
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
            font-weight: 600;
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
        .status-pending { background: #fef3c7; color: #92400e; }
        .role-pill {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 600;
            background: #ede9fe;
            color: #5b21b6;
        }
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
                <a href="users.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Back to Users
                </a>
                <h1>User Details</h1>
            </div>

            <?php if ($user): ?>
                <?php
                    $initials = '';
                    $fullName = $user['full_name'] ?? 'Unknown';
                    $nameParts = explode(' ', $fullName);
                    foreach ($nameParts as $part) {
                        $initials .= mb_strtoupper(mb_substr($part, 0, 1));
                    }
                    $initials = mb_substr($initials, 0, 2);

                    $statusVal = strtolower($user['status'] ?? 'active');
                    $statusClass = 'status-active';
                    if ($statusVal === 'inactive' || $statusVal === 'suspended') $statusClass = 'status-inactive';
                    if ($statusVal === 'pending') $statusClass = 'status-pending';

                    $roleName = $user['role'] ?? 'N/A';
                    $roleDisplay = ucwords(str_replace('_', ' ', $roleName));
                    $createdAt = isset($user['created_at']) ? date('d M Y, H:i', strtotime($user['created_at'])) : 'N/A';
                    $updatedAt = isset($user['updated_at']) ? date('d M Y, H:i', strtotime($user['updated_at'])) : 'N/A';
                ?>

                <div class="user-profile-card">
                    <div class="profile-header">
                        <div class="profile-avatar"><?php echo $initials; ?></div>
                        <div class="profile-header-info">
                            <h2><?php echo htmlspecialchars($fullName); ?></h2>
                            <p><?php echo htmlspecialchars($user['email'] ?? ''); ?></p>
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
                                <span class="detail-label">User ID</span>
                                <span class="detail-value" style="font-size:0.8rem; color:#9ca3af;"><?php echo htmlspecialchars($user['id']); ?></span>
                            </div>
                        </div>

                        <!-- Contact Info -->
                        <div class="detail-section">
                            <h3>Contact Information</h3>
                            <div class="detail-row">
                                <span class="detail-label">Email</span>
                                <span class="detail-value"><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Phone</span>
                                <span class="detail-value"><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></span>
                            </div>
                        </div>

                        <!-- Organization -->
                        <?php if ($companyName || $outletName): ?>
                        <div class="detail-section">
                            <h3>Organization</h3>
                            <?php if ($companyName): ?>
                            <div class="detail-row">
                                <span class="detail-label">Company</span>
                                <span class="detail-value"><?php echo htmlspecialchars($companyName); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($outletName): ?>
                            <div class="detail-row">
                                <span class="detail-label">Outlet</span>
                                <span class="detail-value"><?php echo htmlspecialchars($outletName); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Timestamps -->
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

                    <div class="profile-actions">
                        <a href="users.php" class="btn-action btn-back">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                        <a href="edit-user.php?id=<?php echo urlencode($user['id']); ?>" class="btn-action btn-edit">
                            <i class="fas fa-pen"></i> Edit User
                        </a>
                        <button class="btn-action btn-delete" id="deleteUserBtn">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </div>

            <?php else: ?>
                <div class="user-profile-card">
                    <div class="empty-state">
                        <i class="fas fa-user-slash"></i>
                        <p>User not found or no ID provided.</p>
                        <a href="users.php" class="btn-action btn-back" style="display:inline-flex; margin-top:1rem;">
                            <i class="fas fa-arrow-left"></i> Back to Users
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
    <?php if ($user): ?>
    document.addEventListener('DOMContentLoaded', () => {
        const deleteBtn = document.getElementById('deleteUserBtn');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', () => {
                Swal.fire({
                    title: 'Delete User?',
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
                                    id: '<?php echo $user['id']; ?>',
                                    email: '<?php echo $user['email'] ?? ''; ?>',
                                    role: '<?php echo $user['role'] ?? ''; ?>'
                                })
                            });
                            const data = await response.json();

                            if (data.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Deleted!',
                                    text: 'User has been deleted.',
                                    confirmButtonColor: '#2e0d2a',
                                    timer: 2000,
                                    timerProgressBar: true
                                }).then(() => {
                                    window.location.href = 'users.php';
                                });
                            } else {
                                Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Failed to delete user', confirmButtonColor: '#2e0d2a' });
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
