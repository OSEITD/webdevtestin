<?php
require_once __DIR__ . '/../../auth/session-check.php';
$page_title = 'Company - Add Vehicle';        
include __DIR__ . '/../includes/header.php';        
?>

<body class="bg-gray-100 min-h-screen">

    <div class="mobile-dashboard">

        <!-- Main Content Area for Add New Vehicle -->
        <main class="main-content">
            <div class="content-header">
                <h1>Add New Vehicle</h1>
            </div>

            <div class="form-card">
                <p class="text-gray-600 mb-6">Register a new delivery vehicle for your fleet.</p>
                <form id="addVehicleForm">
                    <div class="form-group">
                        <label for="name">Name</label>
                        <input type="text" id="name" placeholder="Enter vehicle name" required>
                    </div>
                    <div class="form-group">
                        <label for="plate_number">Plate Number</label>
                        <input type="text" id="plate_number" placeholder="Enter plate number" required>
                    </div>
                    <div class="form-group">
                        <label for="status">Vehicle Status</label>
                        <select id="status" required>
                            <option value="">Select status</option>
                            <option value="available">Available</option>
                            <option value="unavailable">Unavailable</option>
                            <option value="out_for_delivery">Out for Delivery</option>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="action-btn secondary" onclick="history.back()">
                            <i class="fas fa-arrow-left"></i> Back
                        </button>
                        <button type="submit" class="action-btn">
                            <i class="fas fa-plus"></i> Add Vehicle
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <!-- Link to the external JavaScript files -->
    <script src="../assets/js/company-scripts.js"></script>
    <script src="../assets/js/add-vehicle.js"></script>
</body>
</html>