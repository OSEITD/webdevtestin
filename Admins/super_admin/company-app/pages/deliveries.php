<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Company - Deliveries</title>
    <!-- Poppins Font -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- External CSS files -->
    <link rel="stylesheet" href="../assets/css/company.css">
    <link rel="stylesheet" href="../assets/css/deliveries.css">
</head>
<body class="bg-gray-100 min-h-screen">

    <div class="mobile-dashboard">

        <!-- Top Header Bar -->
<?php
    $page_title = 'Company - Deliveries';
    include __DIR__ . '/../includes/header.php';
?>
 <!-- Main Content Area for Deliveries -->
        <main class="main-content">
            <div class="content-header">
                <h1>All Deliveries</h1>
            </div>

            <div class="filter-bar">
                <div class="search-input-container">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchDeliveries" placeholder="Search by tracking number, sender, receiver...">
                </div>

                <div class="filter-dropdown">
                    <select id="filterDateRange">
                        <option value="">Date Range</option>
                        <option value="today">Today</option>
                        <option value="last7days">Last 7 Days</option>
                        <option value="thismonth">This Month</option>
                    </select>
                </div>

                <div class="filter-dropdown">
                    <select id="filterDriver">
                        <option value="">Driver</option>
                        <option value="driver1">Driver 1</option>
                        <option value="driver2">Driver 2</option>
                        <option value="driver3">Driver 3</option>
                    </select>
                </div>

                <div class="filter-dropdown">
                    <select id="filterOutlet">
                        <option value="">Outlet</option>
                        <option value="outletA">Warehouse A</option>
                        <option value="outletB">Warehouse B</option>
                        <option value="outletC">Warehouse C</option>
                    </select>
                </div>
            </div>

            <!-- Error message container (match drivers page styling) -->
            <div id="errorMessage" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" style="display: none;" role="alert">
                <span class="block sm:inline"></span>
            </div>

            <!-- Parcel Details Modal -->
            <div id="parcelModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Parcel Details</h2>
                        <button class="close-modal"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="modal-body">
                        <!-- Parcel details will be populated here -->
                    </div>
                </div>
            </div>
             

            <div class="overflow-x-auto">
                <table class="data-table deliveries-table">
                    <thead>
                        <tr>
                            <th>Tracking Number</th>
                            <th>Sender</th>
                            <th>Reciever</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="deliveriesTableBody">
                        <tr id="loadingRow">
                            <td colspan="5" class="text-center py-4">
                                <div class="loading-spinner">Loading deliveries...</div>
                            </td>
                        </tr>
                        <!-- Delivery data will be populated dynamically -->
                    </tbody>
                </table>
            </div>
            <!-- Pagination Controls -->
            <div class="pagination-container" id="deliveriesPagination" style="display:flex;align-items:center;justify-content:center;gap:8px;margin-top:20px;"></div>
        </main>
    </div>
    
    <!-- Link to the external JavaScript file -->
<script src="../assets/js/company-scripts.js"></script>
    <!-- Include JavaScript -->
    <script src="../assets/js/deliveries.js?v=<?php echo time(); ?>"></script>
</body>
</html>