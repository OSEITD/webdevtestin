<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Company - Add New Driver</title>
    <!-- Poppins Font -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- External CSS file -->
    <link rel="stylesheet" href="../assets/css/company.css">
</head>
<body class="bg-gray-100 min-h-screen">

    <div class="mobile-dashboard">

        <!-- Top Header Bar -->
        <header class="top-header">
            <div class="header-content">
                <img src="../assets/images/Logo.png" alt="SwiftShip" class="app-logo">
                <div class="header-icons">
                    <button class="icon-btn search-btn">
                        <i class="fas fa-search"></i>
                    </button>
                    <button class="icon-btn notification-btn">
                        <i class="fas fa-bell"></i>
                        <span class="badge">3</span>
                    </button>
                    <button class="icon-btn menu-btn" id="menuBtn">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                </div>
            </div>
        </header>

        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="menu-header">
                <div class="user-profile">
                    <div class="user-avatar">
                        <img src="https://placehold.co/40x40/FF6B6B/ffffff?text=U" alt="Company Manager Avatar">
                    </div>
                    <div>
                        <h3>Company Manager</h3>
                        <p>Online <span class="online-dot"></span></p>
                    </div>
                </div>
                <button class="close-menu" id="closeMenu">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Menu Items -->
            <ul class="menu-items">
                <li><a href="company-dashboard.html"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="company-outlets.html"><i class="fas fa-store"></i> Outlets</a></li>
                <li><a href="company-drivers.html" class="active"><i class="fas fa-truck"></i> Drivers</a></li>
                <li><a href="company-deliveries.html"><i class="fas fa-box-open"></i> Deliveries</a></li>
                <li><a href="company-reports.html"><i class="fas fa-chart-line"></i> Reports</a></li>
                <li><a href="company-settings.html"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="company-help.html"><i class="fas fa-question-circle"></i> Help</a></li>
                <li><a href="login.html"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>

        <!-- Overlay for sidebar -->

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
                        <label for="contactNumber">Contact Number</label>
                        <input type="tel" id="contactNumber" placeholder="Enter contact number" required>
                    </div>
                    <div class="form-group">
                        <label for="vehicleType">Vehicle Type</label>
                        <input type="text" id="vehicleType" placeholder="Enter vehicle type (e.g., Van, Truck, Motorcycle)" required>
                    </div>
                    <div class="form-group">
                        <label for="licensePlate">License Plate</label>
                        <input type="text" id="licensePlate" placeholder="Enter license plate number" required>
                    </div>
                    <div class="form-group">
                        <label for="assignedOutlet">Assigned Outlet</label>
                        <select id="assignedOutlet" required>
                            <option value="">Select outlet</option>
                            <option value="outlet1">Main Street Outlet</option>
                            <option value="outlet2">Downtown Depot</option>
                            <option value="outlet3">Uptown Hub</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="driverId">Driver ID</label>
                        <input type="text" id="driverId" placeholder="Enter driver ID" required>
                    </div>
                    <div class="form-group">
                        <label for="employmentStatus">Employment Status</label>
                        <select id="employmentStatus" required>
                            <option value="">Select employment status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="on-leave">On Leave</option>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="action-btn">Save</button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <!-- Link to the external JavaScript file -->
    <script src="../assets/js/company-scripts.js"></script>
</body>
</html>
