<?php
session_start();
require_once '../api/supabase-client.php';

// Check if user is logged in and has super_admin role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: login.php');
    exit;
}

$pageTitle = 'Admin - View Driver';
require_once '../includes/header.php';

$error = null;
$driver = null;

$driver_email = $_GET['email'] ?? null;
$driver_id = $_GET['id'] ?? null;

if (!$driver_email && !$driver_id) {
    $error = 'No driver specified.';
} else {
    try {
        if ($driver_email) {
            $drivers = callSupabase("all_users?contact_email=eq." . urlencode($driver_email) . "&select=*");
        } elseif ($driver_id) {
            $drivers = callSupabase("all_users?id=eq." . urlencode($driver_id) . "&select=*");
        }
        
        if (!empty($drivers)) {
            $driver = $drivers[0];
        } else {
            $error = 'Driver not found.';
        }
    } catch (Exception $e) {
        error_log('Error fetching driver: ' . $e->getMessage());
        $error = 'Failed to load driver details: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $pageTitle; ?></title>
    <!-- Poppins Font -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- External CSS file -->
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <style>
        /* Driver details styling to match outlet view */
        body { background-color: #f8f9fa; font-family: 'Poppins', sans-serif; }

        .details-card {
            background: #fff;
            border-radius: 8px;
            padding: 24px;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.06);
            max-width: 980px;
            margin: 20px auto;
        }

        .content-header h1 { margin: 0 0 12px 0; }

        /* Generic info layout used on outlet page */
        .info-section, .detail-list {
            display: block;
            margin-top: 16px;
        }

        .info-row, .detail-item {
            display: grid;
            grid-template-columns: 160px 1fr;
            gap: 12px;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eef2f7;
        }

        .info-row:last-child, .detail-item:last-child { border-bottom: none; }

        .info-label, .label {
            color: #6b7280;
            font-size: 0.92rem;
            font-weight: 600;
            text-transform: none;
        }

        .info-value, .value {
            color: #111827;
            font-size: 0.95rem;
        }

        .section-divider { margin-top: 18px; }
        .section-divider h3 { margin: 0 0 8px 0; font-size: 1rem; color: #374151; }

        .status-badge {
            display: inline-block;
            padding: 0.4rem 0.8rem;
            border-radius: 999px;
            font-weight: 700;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }
        .status-badge.status-active, .status-badge.active { background: rgba(59,130,246,0.08); color: #0369a1; }
        .status-badge.status-suspended, .status-badge.inactive { background: rgba(220,53,69,0.06); color: #b91c1c; }

        .button-group { margin-top: 18px; display:flex; gap:12px; }
        .action-btn { padding: 8px 14px; border-radius:6px; border: none; cursor: pointer; }
        .action-btn.secondary { background:#eef2ff; color:#1e3a8a; }
        .action-btn.danger { background:#fee2e2; color:#7f1d1d; }

        @media (max-width: 640px) {
            .info-row, .detail-item { grid-template-columns: 1fr; }
            .info-label, .label { margin-bottom: 6px; }
            .details-card { padding: 16px; margin: 12px; }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">

    <div class="mobile-dashboard">

         <!-- Include Header -->
        <?php include '../includes/header.php'; ?>

        <!-- Include Sidebar -->
        <?php include '../includes/sidebar.php'; ?>

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
                <h2><?php echo htmlspecialchars($driver['name'] ?? 'Unnamed Driver'); ?></h2>

                <div class="detail-item">
                    <span class="label">Driver ID:</span>
                    <span class="value"><?php echo htmlspecialchars($driver['id'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Contact Email:</span>
                    <span class="value"><?php echo htmlspecialchars($driver['contact_email'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Role(s):</span>
                    <span class="value"><?php echo htmlspecialchars($driver['role'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Status:</span>
                    <span class="value">
                        <span class="status-badge <?= strtolower($driver['status'] ?? '') === 'active' ? 'status-active' : 'status-suspended' ?>">
                            <?php echo htmlspecialchars($driver['status'] ?? 'N/A'); ?>
                        </span>
                    </span>
                </div>
                <div class="detail-item">
                    <span class="label">Associated Company/Outlet:</span>
                    <span class="value"><?php echo htmlspecialchars($driver['associated_entity'] ?? 'N/A'); ?></span>
                </div>

                <div class="button-group">
                    <button class="action-btn secondary" id="backToUsersBtn">
                        <i class="fas fa-arrow-left"></i> Back to Users
                    </button>
                    <button class="action-btn" id="deleteDriverBtn">
                        <i class="fas fa-trash"></i> Delete Driver
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Link to the external JavaScript file -->
    <script src="../assets/js/admin-scripts.js" defer></script>
    <script>
        const csrfToken = '<?php echo CSRFHelper::getToken(); ?>';
        // Specific JavaScript for this page
        document.addEventListener('DOMContentLoaded', () => {
            const backToUsersBtn = document.getElementById('backToUsersBtn');
            const deleteDriverBtn = document.getElementById('deleteDriverBtn');

            if (backToUsersBtn) {
                backToUsersBtn.addEventListener('click', () => {
                    window.location.href = 'users.php';
                });
            }

            if (deleteDriverBtn) {
                deleteDriverBtn.addEventListener('click', async function() {
                    <?php if ($driver && isset($driver['contact_email'])): ?>
                    const driverEmail = '<?= $driver['contact_email'] ?>';
                    const driverId = '<?= $driver['id'] ?? '' ?>';
                    const driverRole = '<?= $driver['role'] ?>';

                    if (!confirm(`Are you sure you want to delete the driver ${driverEmail}? This action cannot be undone.`)) return;

                    try {
                        deleteDriverBtn.disabled = true;
                        const response = await fetch('../api/delete_user.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken
                            },
                            body: JSON.stringify({ email: driverEmail, id: driverId, role: driverRole }),
                        });
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            alert('Driver deleted successfully!');
                            window.location.href = 'users.php';
                        } else {
                            alert('Error deleting driver: ' + (data.message || 'Unknown error'));
                            deleteDriverBtn.disabled = false;
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        alert('An error occurred while trying to delete the driver.');
                        deleteDriverBtn.disabled = false;
                    }
                    <?php else: ?>
                    alert('Driver information not found.');
                    <?php endif; ?>
                });
            }
        });
    </script>
</body>
</html>
