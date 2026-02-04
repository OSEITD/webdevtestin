<?php
// Include the header (which also handles session and authentication)
require_once '../includes/header.php';
?>
<body class="bg-gray-100 min-h-screen">

    <div class="mobile-dashboard">
        <!-- Main Content Area for Drivers -->
        <main class="main-content">
            <div class="content-header">
                <h1>Drivers</h1>
                <button class="add-btn" id="addDriverBtn">
                    <i class="fas fa-user-plus"></i> Add Driver
                </button>
            </div>

            <div class="filter-bar">
                <div class="search-input-container">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchDrivers" placeholder="Search drivers">
                </div>
                <div class="filter-dropdown">
                    <select id="filterStatus">
                        <option value="">Status</option>
                        <option value="available">Available</option>
                        <option value="busy">On Delivery</option>
                        <option value="unavailable">Unavailable</option>
                    </select>
                </div>
            </div>

            <!-- Error message container -->
            <div id="errorMessage" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" style="display: none;" role="alert">
                <span class="block sm:inline"></span>
            </div>

            <!-- Loading spinner container -->
            <div id="loadingSpinner" class="text-center py-4" style="display: none;">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-gray-900"></div>
                <p class="mt-2">Loading drivers...</p>
            </div>

            <div class="overflow-x-auto">
                <table class="data-table drivers-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Contact</th>
                            <th>License</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="driversTableBody">
                        <!-- Data will be loaded dynamically -->
                        <tr id="loadingRow">
                            <td colspan="5" class="text-center py-4">
                                <div class="loading-spinner">Loading drivers...</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <!-- Pagination Controls -->
            <div class="pagination-container" id="driversPagination" style="display:flex;align-items:center;justify-content:center;gap:8px;margin-top:20px;"></div>
        </main>
    </div>

    <!-- Link to the external JavaScript files -->
    <script src="../assets/js/company-scripts.js"></script>
    <?php $driversJs = __DIR__ . '/../assets/js/drivers.js'; $ts = file_exists($driversJs) ? filemtime($driversJs) : time(); ?>
    <script src="../assets/js/drivers.js?v=<?php echo $ts; ?>"></script>
</body>
</html>