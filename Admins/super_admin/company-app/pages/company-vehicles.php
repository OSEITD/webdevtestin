<?php
require_once __DIR__ . '/../../auth/session-check.php';

$page_title = 'Company - Vehicles';        
include __DIR__ . '/../includes/header.php';        
?>

<body class="bg-gray-100 min-h-screen">
    <div class="mobile-dashboard">
        <!-- Main Content Area -->
        <main class="main-content">
            <div class="content-header">
                <h1>Vehicle Management</h1>
                <button onclick="window.location.href='company-add-vehicle.php'" class="add-button">
                    <i class="fas fa-plus"></i> Add Vehicle
                </button>
            </div>

            <div class="filter-bar" style="margin-bottom:12px;display:flex;gap:8px;align-items:center;">
                <div style="flex:1;display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-search"></i>
                    <input id="vehicleSearch" type="text" placeholder="Search vehicles by name or plate" style="flex:1;padding:6px;border-radius:6px;border:1px solid #ddd;" />
                </div>
                <div>
                    <select id="vehicleStatusFilter" style="padding:6px;border-radius:6px;border:1px solid #ddd;">
                        <option value="">All statuses</option>
                        <option value="available">Available</option>
                        <option value="assigned">Assigned</option>
                        <option value="out_for_delivery">Out for Delivery</option>
                        <option value="unavailable">Unavailable</option>
                    </select>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="data-table vehicles-table">
                    <thead>
                        <tr>
                            <th>Vehicle Name</th>
                            <th>Plate Number</th>
                            <th>Status</th>
                            <th>Date Added</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="vehiclesTableBody">
                        <tr id="loadingIndicator">
                            <td colspan="5" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading vehicles...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <!-- Pagination Controls -->
            <div class="pagination-container" id="vehiclesPagination" style="display:flex;align-items:center;justify-content:center;gap:8px;margin-top:20px;"></div>
        </main>
    </div>
    
    <!-- Link to external CSS -->
    <link rel="stylesheet" href="../assets/css/vehicles.css">

    <!-- Include JavaScript -->
    <?php $v = file_exists(__DIR__ . '/../assets/js/vehicles.js') ? filemtime(__DIR__ . '/../assets/js/vehicles.js') : time(); ?>
    <script src="../assets/js/vehicles.js?v=<?php echo $v; ?>"></script>
    <script src="../assets/js/company-scripts.js"></script>
</body>
</html>