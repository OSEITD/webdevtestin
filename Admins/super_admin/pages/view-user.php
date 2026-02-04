<?php
session_start();
require_once '../api/supabase-client.php';

// Check if user is logged in and has super_admin role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: login.php');
    exit;
}

$pageTitle = 'Admin - View User';
require_once '../includes/header.php';
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
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Menu toggle functionality
            const menuButton = document.querySelector('.menu-toggle');
            const sidebar = document.querySelector('.sidebar');
            const menuOverlay = document.getElementById('menuOverlay');

            if (menuButton && sidebar && menuOverlay) {
                menuButton.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                    menuOverlay.classList.toggle('active');
                });

                menuOverlay.addEventListener('click', function() {
                    sidebar.classList.remove('active');
                    menuOverlay.classList.remove('active');
                });
            }
        });
    </script>
</head>
<body class="bg-gray-100 min-h-screen">

    <div class="mobile-dashboard">

         <!-- Include Header -->
        <?php include '../includes/header.php'; ?>

        <!-- Include Sidebar -->
        <?php include '../includes/sidebar.php'; ?>

        <!-- Main Content Area for User Details -->
        <main class="main-content">
            <div class="content-header">
                <h1>User Details</h1>
            </div>

            <?php
            

            require_once __DIR__ . '/../api/supabase-client.php';

            $user_email = $_GET['email'] ?? null;
            $user = null;

            if ($user_email) {
                try {
                    global $supabaseUrl, $supabaseServiceKey;
                    $client = new SupabaseClient($supabaseUrl, $supabaseServiceKey);
                    $users = $client->get('all_users', 'contact_email=eq.' . urlencode($user_email));
                    if (!empty($users)) {
                        $user = $users[0];
                    }
                } catch (Exception $e) {
                    echo '<div class="details-card"><p>Error fetching user data: ' . $e->getMessage() . '</p></div>';
                }
            }

            if ($user):
            ?>
            <div class="details-card">
                <h2><?= htmlspecialchars($user['name'] ?? 'N/A') ?></h2>

                <div class="detail-item">
                    <span class="label">Email:</span>
                    <span class="value"><?= htmlspecialchars($user['contact_email'] ?? 'N/A') ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Role(s):</span>
                    <span class="value"><?= htmlspecialchars($user['role'] ?? 'N/A') ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Status:</span>
                    <span class="value"><span class="status-badge <?= strtolower($user['status'] ?? '') === 'active' ? 'status-active' : 'status-suspended' ?>"><?= htmlspecialchars($user['status'] ?? 'N/A') ?></span></span>
                </div>
                <div class="detail-item">
                    <span class="label">Associated Company/Outlet:</span>
                    <span class="value"><?= htmlspecialchars($user['associated_entity'] ?? 'N/A') ?></span>
                </div>
                
                <div class="button-group">
                    <button class="action-btn secondary" id="backToUsersBtn">
                        <i class="fas fa-arrow-left"></i> Back to Users
                    </button>
                    <button class="action-btn" id="deleteUserBtn">
                        <i class="fas fa-trash"></i> Delete User
                    </button>
                </div>
            </div>
            <?php else: ?>
            <div class="details-card">
                <p>User not found or email not provided.</p>
                 <div class="button-group">
                    <button class="action-btn secondary" id="backToUsersBtn">
                        <i class="fas fa-arrow-left"></i> Back to Users
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Link to the external JavaScript file -->
    <script src="../assets/js/admin-scripts.js" defer></script>
    <script>
        // Specific JavaScript for this page
        document.addEventListener('DOMContentLoaded', () => {
            const backToUsersBtn = document.getElementById('backToUsersBtn');
            const deleteUserBtn = document.getElementById('deleteUserBtn'); // Changed ID

            if (backToUsersBtn) {
                backToUsersBtn.addEventListener('click', () => {
                    window.location.href = 'users.php';
                });
            }

            if (deleteUserBtn) { // Changed ID
                deleteUserBtn.addEventListener('click', () => {
                    <?php if ($user && isset($user['contact_email'])): ?>
                    const userEmail = '<?= $user['contact_email'] ?>';
                    const userId = '<?= $user['id'] ?>'; // Assuming ID is available
                    const userRole = '<?= $user['role'] ?>'; // Assuming role is available

                    if (confirm(`Are you sure you want to delete the user ${userEmail}? This action cannot be undone.`)) {
                        // Perform AJAX call to delete user
                        fetch('../api/delete_user.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({ email: userEmail, id: userId, role: userRole }),
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('User deleted successfully!');
                                window.location.href = 'users.php'; // Redirect to users list
                            } else {
                                alert('Error deleting user: ' + (data.message || 'Unknown error'));
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('An error occurred while trying to delete the user.');
                        });
                    }
                    <?php else: ?>
                    alert('User email not found.');
                    <?php endif; ?>
                });
            }
        });
    </script>
</body>
</html>
