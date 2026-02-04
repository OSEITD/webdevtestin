<<?php
$page_title = 'Company - Add Driver';        
include __DIR__ . '/../includes/header.php';        
?>
<body class="bg-gray-100 min-h-screen">

    <div class="mobile-dashboard">

     <!-- Main Content Area for Add New Driver -->
        <main class="main-content">
            <div class="content-header">
                <h1>Add New Driver</h1>
            </div>

            <div class="form-card">
                <p class="text-gray-600 mb-6">Provide the necessary details for the new driver.</p>
                <form id="addDriverForm">
                    <div class="form-group">
                        <label for="driverName">Driver Name</label>
                        <input type="text" id="driverName" placeholder="Enter driver's full name" required>
                    </div>
                    <div class="form-group">
                        <label for="driver_phone">Phone</label>
                        <input type="tel" id="driver_phone" placeholder="Enter contact number" required>
                    </div>
                    <div class="form-group">
                        <label for="driver_email">Email</label>
                        <input type="text" id="driver_email" placeholder="Enter email" required>
                    </div>
                    <div class="form-group">
                        <label for="license_number">License</label>
                        <input type="text" id="license_number" placeholder="Enter license number" required>
                    </div>
                                        <div class="form-group">
                        <label for="password">Enter Password</label>
                        <input type="password" id="password" name="password" placeholder="Enter password" required minlength="8">
                        <small class="form-text text-muted">Password must be at least 8 characters long</small>
                    </div>

                    <div class="form-group">
                        <label for="confirmPassword">Confirm Password</label>
                        <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirm the password" required>
                        <small class="form-text text-muted">Re-enter the password to confirm</small>
                    </div>
                    <div class="form-group">
                        <label for="employmentStatus">Employment Status</label>
                        <select id="employmentStatus" required>
                            <option value="">Select employment status</option>
                            <option value="available">Available</option>
                            <option value="busy">Busy</option>
                            <option value="offline">Offline</option>
                        </select>
                    </div>

                       

                    <div class="form-actions">

                    <button class="action-btn secondary"  onclick="history.back()">
                        <i class="fas fa-arrow-left" ></i> Back </button>
                      
                    
                     <button type="submit" class="action-btn">Save</button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <!-- Link to the external JavaScript files -->
    <script src="../assets/js/company-scripts.js"></script>
    <script src="../assets/js/add-driver.js"></script>
</body>
</html>