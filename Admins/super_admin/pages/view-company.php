<?php
session_start();
require_once '../api/supabase-client.php';

// Check if user is logged in and has super_admin role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: login.php');
    exit;
}

// Get company email from URL parameter
$companyEmail = isset($_GET['email']) ? $_GET['email'] : null;
$companyId = isset($_GET['id']) ? $_GET['id'] : null;

$company = null;

// Try to fetch by email first (from users page)
if ($companyEmail) {
    try {
        // Fetch company details from Supabase using email
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
        // Fetch company details from Supabase
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

$pageTitle = 'Admin - view-company';
require_once '../includes/header.php';
?>

    <style>
        /* Base styles */
        body {
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            position: relative;
            min-height: 100vh;
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
        }

        /* Override badge positioning for card content */
        .card-body .badge {
            position: static !important;
            top: auto !important;
            right: auto !important;
            display: inline-block !important;
            margin-left: 0.5rem;
            vertical-align: middle;
        }

        .status-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-badge.active {
            background-color: rgba(40, 167, 69, 0.1);
            color: #28A745;
        }

        .status-badge.inactive {
            background-color: rgba(220, 53, 69, 0.1);
            color: #DC3545;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Get all required elements
            const menuBtn = document.getElementById('menuBtn');
            const closeBtn = document.getElementById('closeMenu');
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('menuOverlay');
            const backToCompaniesBtn = document.getElementById('backToCompaniesBtn');
            const editCompanyBtn = document.getElementById('editCompanyBtn');
            const companyId = '<?php echo $companyId; ?>';
            
            // Function to show sidebar
            function showSidebar() {
                if (overlay && sidebar) {
                    requestAnimationFrame(() => {
                        overlay.style.visibility = 'visible';
                        overlay.style.opacity = '1';
                        sidebar.classList.add('show');
                        document.body.style.overflow = 'hidden';
                    });
                }
            }

            // Function to hide sidebar
            function hideSidebar() {
                if (overlay && sidebar) {
                    overlay.style.opacity = '0';
                    overlay.style.visibility = 'hidden';
                    sidebar.classList.remove('show');
                    document.body.style.overflow = '';
                }
            }

            // Menu button click event
            if (menuBtn) {
                menuBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    showSidebar();
                });
            }

            // Close button click event
            if (closeBtn) {
                closeBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    hideSidebar();
                });
            }

            // Overlay click event
            if (overlay) {
                overlay.addEventListener('click', function(e) {
                    e.preventDefault();
                    hideSidebar();
                });
            }

            // Navigation handlers
            if (backToCompaniesBtn) {
                backToCompaniesBtn.addEventListener('click', () => {
                    window.location.href = 'companies.php';
                });
            }

            if (editCompanyBtn) {
                editCompanyBtn.addEventListener('click', () => {
                    window.location.href = 'edit-company.php?id=' + encodeURIComponent(companyId);
                });
            }

            // Close sidebar when clicking outside
            document.addEventListener('click', function(e) {
                if (sidebar && 
                    sidebar.classList.contains('show') &&
                    !sidebar.contains(e.target) &&
                    !menuBtn.contains(e.target)) {
                    hideSidebar();
                }
            });

            // Initialize sidebar in hidden state
            hideSidebar();
        });
    </script>

    <div class="mobile-dashboard">
        <!-- Main Content -->
        <div class="main-content">
            <div class="container mt-4">
                <div class="card">
                    <div class="card-body">
                        <h1 class="h3 mb-4" style="color: #2E0D2A; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
                            <?php echo htmlspecialchars($company['name'] ?? $company['company_name'] ?? 'Company Details'); ?>
                        </h1>

                        <div class="mb-4">
                            <div class="row align-items-center">
                                <div class="col-sm-2">
                                    <span class="text-muted">Status:</span>
                                </div>
                                <div class="col-sm-10">
                                    <span class="status-badge <?php echo strtolower($company['status']) === 'active' ? 'active' : 'inactive'; ?>">
                                        <?php echo ucfirst(htmlspecialchars($company['status'])); ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="row align-items-center">
                                <div class="col-sm-2">
                                    <span class="text-muted">Company:</span>
                                </div>
                                <div class="col-sm-10">
                                    <?php echo htmlspecialchars($company['name'] ?? $company['company_name'] ?? 'N/A'); ?>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="row align-items-center">
                                <div class="col-sm-2">
                                    <span class="text-muted">Address:</span>
                                </div>
                                <div class="col-sm-10">
                                    <?php echo htmlspecialchars($company['address'] ?? 'Not provided'); ?>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="row align-items-center">
                                <div class="col-sm-2">
                                    <span class="text-muted">Commission Rate:</span>
                                </div>
                                <div class="col-sm-10">
                                    <?php echo htmlspecialchars($company['commission_rate'] ?? '0'); ?>%
                                </div>
                            </div>
                        </div>

                        <h3 class="h5 mb-3">Contact Information</h3>

                        <div class="mb-4">
                            <div class="row align-items-center">
                                <div class="col-sm-2">
                                    <span class="text-muted">Email Address:</span>
                                </div>
                                <div class="col-sm-10">
                                    <?php if (isset($company['contact_email']) && $company['contact_email']): ?>
                                    <a href="mailto:<?php echo htmlspecialchars($company['contact_email'] ?? ''); ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($company['contact_email'] ?? ''); ?>
                                    </a>
                                    <?php else: ?>
                                    <span class="text-muted">Not provided</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="row align-items-center">
                                <div class="col-sm-2">
                                    <span class="text-muted">Phone Number:</span>
                                </div>
                                <div class="col-sm-10">
                                    <?php if (isset($company['contact_phone']) && $company['contact_phone']): ?>
                                    <a href="tel:<?php echo htmlspecialchars($company['contact_phone'] ?? ''); ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($company['contact_phone'] ?? ''); ?>
                                    </a>
                                    <?php else: ?>
                                    <span class="text-muted">Not provided</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <h3 class="h5 mb-4">Company Statistics</h3>

                        <div class="row g-4">
                            <!-- Total Deliveries -->
                            <div class="col-md-6 col-lg-3">
                                <div class="p-3 bg-light rounded-3">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-truck text-primary me-2"></i>
                                        <h6 class="mb-0">Total Deliveries</h6>
                                    </div>
                                    <h3 class="mb-0">
                                        <?php
                                            $totalDeliveries = callSupabase("parcels?company_id=eq.{$companyId}&select=id");
                                            echo is_array($totalDeliveries) ? count($totalDeliveries) : '0';
                                        ?>
                                    </h3>
                                </div>
                            </div>

                            <!-- Active Outlets -->
                            <div class="col-md-6 col-lg-3">
                                <div class="p-3 bg-light rounded-3">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-store text-success me-2"></i>
                                        <h6 class="mb-0">Active Outlets</h6>
                                    </div>
                                    <h3 class="mb-0">
                                        <?php
                                            $activeOutlets = callSupabase("outlets?company_id=eq.{$companyId}&status=eq.active&select=id");
                                            echo is_array($activeOutlets) ? count($activeOutlets) : '0';
                                        ?>
                                    </h3>
                                </div>
                            </div>

                            <!-- Total Revenue -->
                            <div class="col-md-6 col-lg-3">
                                <div class="p-3 bg-light rounded-3">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-dollar-sign text-warning me-2"></i>
                                        <h6 class="mb-0">Total Revenue</h6>
                                    </div>
                                    <h3 class="mb-0">
                                        $<?php echo number_format($company['revenue'] ?? 0, 2); ?>
                                    </h3>
                                </div>
                            </div>

                            <!-- Active Drivers -->
                            <div class="col-md-6 col-lg-3">
                                <div class="p-3 bg-light rounded-3">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-user text-info me-2"></i>
                                        <h6 class="mb-0">Active Drivers</h6>
                                    </div>
                                    <h3 class="mb-0">
                                        <?php
                                            // Normalize to DB status 'available'
                                            $activeDrivers = callSupabase("drivers?company_id=eq.{$companyId}&status=eq.available&select=id");
                                            echo is_array($activeDrivers) ? count($activeDrivers) : '0';
                                        ?>
                                    </h3>
                                </div>
                            </div>
                        </div>

                        <div class="mt-5 d-flex justify-content-between">
                            <button id="backToCompaniesBtn" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Companies                            </button>
                            <button id="editCompanyBtn" class="btn btn-primary">
                                <i class="fas fa-edit"></i> Edit Company
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS and dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>
</body>
</html>
