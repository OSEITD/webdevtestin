<?php
session_start();
require_once '../api/supabase-client.php';

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
        // Fetch outlet details using email from all_users table
        $result = callSupabase("all_users?contact_email=eq.$outletEmail&select=*");
        
        if ($result && count($result) > 0) {
            $outlet = $result[0];
        }
    } catch (Exception $e) {
        $outlet = null;
    }
}

// If not found by email, try by ID (from outlets page)
if (!$outlet && $outletId) {
    try {
        // Fetch outlet details using ID
        $result = callSupabaseWithServiceKey("outlets?id=eq.$outletId&select=*", 'GET');
        
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

$pageTitle = 'Admin - View Outlet';
require_once '../includes/header.php';
?>

    <div class="mobile-dashboard">
        <!-- Main Content Area for Outlet Details -->
        <main class="main-content">
            <div class="content-header">
                <h1>Outlet Details</h1>
            </div>
            <div class="details-card">
                <h2><?php echo htmlspecialchars($outlet['name'] ?? $outlet['outlet_name'] ?? 'Outlet Details'); ?></h2>
                
                <div class="info-section">
                    <div class="info-row">
                        <div class="info-label">Status:</div>
                        <div class="info-value">
                            <div class="status-badge <?php echo $outlet['status'] === 'active' ? 'active' : 'inactive'; ?>">
                                <?php echo strtoupper($outlet['status'] ?? 'N/A'); ?>
                            </div>
                        </div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Company:</div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($outlet['companies']['company_name'] ?? 'Not specified'); ?>
                        </div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Address:</div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($outlet['address'] ?? 'Not specified'); ?>
                        </div>
                    </div>
                </div>

                <div class="section-divider">
                    <h3>Contact Information</h3>
                </div>

                <div class="info-section">
                    <div class="info-row">
                        <div class="info-label">Contact Person:</div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($outlet['contact_person'] ?? 'Not specified'); ?>
                        </div>
                    </div>

                    <?php if (!empty($outlet['contact_phone'])): ?>
                    <div class="info-row">
                        <div class="info-label">Alternative Phone:</div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($outlet['contact_phone']); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="info-row">
                        <div class="info-label">Email Address:</div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($outlet['contact_email'] ?? 'Not specified'); ?>
                        </div>
                    </div>
                </div>

                <div class="section-divider">
                    <h3>System Information</h3>
                </div>

                <div class="info-section">
                    <div class="info-row">
                        <div class="info-label">Outlet ID:</div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($outlet['id'] ?? 'N/A'); ?>
                        </div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Created:</div>
                        <div class="info-value">
                            <?php echo !empty($outlet['created_at']) ? date('F j, Y g:i A', strtotime($outlet['created_at'])) : 'Not specified'; ?>
                        </div>
                    </div>

                    <?php if (!empty($outlet['updated_at'])): ?>
                    <div class="info-row">
                        <div class="info-label">Last Updated:</div>
                        <div class="info-value">
                            <?php echo date('F j, Y g:i A', strtotime($outlet['updated_at'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="action-buttons">
                    <button class="btn btn-secondary" onclick="window.location.href='users.php'">
                        <i class="fas fa-arrow-left"></i> Back to Users
                    </button>
                    <button class="btn btn-primary" onclick="window.location.href='edit-outlet.php?id=<?php echo htmlspecialchars($outlet['id'] ?? ''); ?>'">
                        <i class="fas fa-edit"></i> Edit Outlet
                    </button>
                </div>
            </div>
        </main>
    </div>

        </body>
        </html>
