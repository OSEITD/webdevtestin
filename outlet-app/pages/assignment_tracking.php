<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['company_id'])) {
    $_SESSION['user_id'] = 'test-user';
    $_SESSION['company_id'] = 'O-100';

    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$company_id = $_SESSION['company_id'];
$user_name = $_SESSION['user_name'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Assignment Tracking - WD Parcel Management</title>

    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../css/outlet-dashboard.css">
    <link rel="stylesheet" href="../css/assignment-tracking.css">
</head>
<body class="bg-gray-100 min-h-screen">

    <div class="mobile-dashboard">
        <?php include '../includes/navbar.php'; ?>
        <?php include '../includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="content-container">
                <div class="page-header">
                    <h1><i class="fas fa-route"></i> Assignment Tracking</h1>
                    <p>Monitor parcel assignments and vehicle details</p>
                </div>

                <div class="filter-section">
                    <div class="filter-controls">
                        <div class="filter-group">
                            <label for="statusFilter">Status</label>
                            <select id="statusFilter">
                                <option value="">All Statuses</option>
                                <option value="assigned">Assigned</option>
                                <option value="pending">Pending</option>
                                <option value="delivered">Delivered</option>
                                <option value="at_outlet">At Outlet</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="vehicleFilter">Vehicle</label>
                            <select id="vehicleFilter">
                                <option value="">All Vehicles</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="trackFilter">Track Number</label>
                            <input type="text" id="trackFilter" placeholder="Enter track number...">
                        </div>
                        <div class="filter-group">
                            <label for="viewMode">View Mode</label>
                            <select id="viewMode" onchange="toggleViewMode()">
                                <option value="table">Table View</option>
                                <option value="cards">Card View</option>
                            </select>
                        </div>
                        <button class="refresh-btn" onclick="loadAssignments()">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                </div>

                <div class="assignments-content-container">
                <div id="tableView" class="view-active">
                    <div class="view-toggle">
                        <label>View: Table Mode</label>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="assignments-table">
                            <thead>
                                <tr>
                                    <th>Track Number</th>
                                    <th>Status</th>
                                    <th>Parcel Details</th>
                                    <th>Vehicle Information</th>
                                    <th>Vehicle Details</th>
                                    <th>Assignment Details</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="assignmentsTableBody">
                                <tr>
                                    <td colspan="7" class="text-center py-8">
                                        <div class="loading-spinner">
                                            <i class="fas fa-spinner fa-spin fa-2x"></i>
                                            <p class="mt-4">Loading assignments...</p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="cardView" class="view-hidden">
                    <div class="view-toggle">
                        <label>View: Card Mode</label>
                    </div>
                    <div class="loading-spinner text-center py-8">
                        <i class="fas fa-spinner fa-spin fa-2x"></i>
                        <p class="mt-4">Loading assignments...</p>
                    </div>
                </div>
                </div>
                </div>
            </div>
        </main>
    </div>

    <div id="assignmentModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-box"></i> Parcel Assignment Details</h2>
                <button class="modal-close" onclick="closeAssignmentModal()">&times;</button>
            </div>
            <div class="modal-body" id="modalContent">
                <div class="loading-spinner">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                    <p>Loading assignment details...</p>
                </div>
            </div>
        </div>
    </div>

    <script src="../js/assignment-tracking.js"></script>
</body>
</html>
