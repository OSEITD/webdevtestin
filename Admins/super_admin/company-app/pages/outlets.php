<?php
require_once __DIR__ . '/../../auth/session-check.php';
$page_title = 'Company - Outlets';
require_once '../includes/header.php';
?>
<body class="bg-gray-100 min-h-screen">
    <div class="mobile-dashboard">
        <!-- Main Content Area for Outlets -->
        <main class="main-content">
            <div class="content-header">
                <h1>Outlets</h1>
                <button class="add-btn" id="addOutletBtn">
                    <i class="fas fa-plus-circle"></i> New Outlet
                </button>
            </div>

            <div class="filter-bar">
                <div class="search-input-container">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchOutlets" placeholder="Search outlets">
                </div>
                <div class="filter-dropdown">
                    <select id="filterStatus">
                        <option value="">Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="data-table outlets-table">
                    <thead>
                        <tr>
                            <th>Outlet Name</th>
                            <th>Address</th>
                            <th>Contact Person</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="outletsTableBody">
                        <tr>
                            <td colspan="5" class="text-center">Loading outlets...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <!-- Pagination Controls -->
            <div class="pagination-container" id="outletsPagination" style="display:flex;align-items:center;justify-content:center;gap:8px;margin-top:20px;"></div>
            
            <!-- Loading Spinner -->
            <div id="loadingSpinner" class="loading-spinner" style="display: none;">
                <div class="spinner"></div>
            </div>

            <!-- Error Message -->
            <div id="errorMessage" class="error-message" style="display: none;"></div>
        </main>
    </div>

    <!-- Link to the external JavaScript files -->
    <script src="../assets/js/company-scripts.js"></script>
    <script src="../assets/js/outlets.js"></script>
</body>
</html>