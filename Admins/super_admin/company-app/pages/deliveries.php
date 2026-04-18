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
    <link rel="stylesheet" href="../assets/css/company.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/deliveries.css?v=<?php echo time(); ?>">
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
                <div class="modal-content" style="background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 90%; max-width: 780px; border-radius: 8px; position: relative;">
                    <span class="close-modal" id="closeParcelModal" style="color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
                    <h2>Parcel Details</h2>
                    <p class="subtitle" id="modalTrackingNumber">Tracking Number: Loading...</p>
                    <div id="modalParcelStatusDisplay" class="parcel-status-display status-pending" style="display: inline-block; padding: 0.35rem 0.75rem; border-radius: 999px; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; margin-bottom: 20px; letter-spacing: 0.02em;">Loading...</div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                        <div class="detail-section" style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                            <h3 style="margin-top: 0; color: #2E0D2A; border-bottom: 2px solid #2E0D2A; padding-bottom: 0.5rem;">Recipient Information</h3>
                            <div class="detail-item" style="display: flex; align-items: center; margin-bottom: 1rem;">
                                <i class="fas fa-user" style="width: 20px; margin-right: 10px; color: #4A1C40;"></i>
                                <div>
                                    <p class="label" style="margin: 0; font-weight: 600; color: #666;">Recipient Name</p>
                                    <p class="value" id="modalRecipientName" style="margin: 0.25rem 0 0 0; font-weight: 500;">Loading...</p>
                                </div>
                            </div>
                            <div class="detail-item" style="display: flex; align-items: center; margin-bottom: 1rem;">
                                <i class="fas fa-map-marker-alt" style="width: 20px; margin-right: 10px; color: #4A1C40;"></i>
                                <div>
                                    <p class="label" style="margin: 0; font-weight: 600; color: #666;">Delivery Address</p>
                                    <p class="value" id="modalDeliveryAddress" style="margin: 0.25rem 0 0 0; font-weight: 500;">Loading...</p>
                                </div>
                            </div>
                            <div class="detail-item" style="display: flex; align-items: center; margin-bottom: 1rem;">
                                <i class="fas fa-phone" style="width: 20px; margin-right: 10px; color: #4A1C40;"></i>
                                <div>
                                    <p class="label" style="margin: 0; font-weight: 600; color: #666;">Contact Number</p>
                                    <p class="value" id="modalContactNumber" style="margin: 0.25rem 0 0 0; font-weight: 500;">Loading...</p>
                                </div>
                            </div>
                        </div>

                        <div class="detail-section" style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                            <h3 style="margin-top: 0; color: #2E0D2A; border-bottom: 2px solid #2E0D2A; padding-bottom: 0.5rem;">Parcel Information</h3>
                            <div class="detail-item" style="display: flex; align-items: center; margin-bottom: 1rem;">
                                <i class="fas fa-weight-hanging" style="width: 20px; margin-right: 10px; color: #4A1C40;"></i>
                                <div>
                                    <p class="label" style="margin: 0; font-weight: 600; color: #666;">Weight</p>
                                    <p class="value" id="modalParcelWeight" style="margin: 0.25rem 0 0 0; font-weight: 500;">Loading...</p>
                                </div>
                            </div>
                            <div class="detail-item" style="display: flex; align-items: center; margin-bottom: 1rem;">
                                <i class="fas fa-dollar-sign" style="width: 20px; margin-right: 10px; color: #4A1C40;"></i>
                                <div>
                                    <p class="label" style="margin: 0; font-weight: 600; color: #666;">Delivery Fee</p>
                                    <p class="value" id="modalDeliveryFee" style="margin: 0.25rem 0 0 0; font-weight: 500;">Loading...</p>
                                </div>
                            </div>
                            <div class="detail-item" style="display: flex; align-items: center; margin-bottom: 1rem;">
                                <i class="fas fa-info-circle" style="width: 20px; margin-right: 10px; color: #4A1C40;"></i>
                                <div>
                                    <p class="label" style="margin: 0; font-weight: 600; color: #666;">Special Instructions</p>
                                    <p class="value" id="modalSpecialInstructions" style="margin: 0.25rem 0 0 0; font-weight: 500;">Loading...</p>
                                </div>
                            </div>
                        </div>

                        <div class="detail-section" style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                            <h3 style="margin-top: 0; color: #2E0D2A; border-bottom: 2px solid #2E0D2A; padding-bottom: 0.5rem;">Sender Information</h3>
                            <div class="detail-item" style="display: flex; align-items: center; margin-bottom: 1rem;">
                                <i class="fas fa-user" style="width: 20px; margin-right: 10px; color: #4A1C40;"></i>
                                <div>
                                    <p class="label" style="margin: 0; font-weight: 600; color: #666;">Sender Name</p>
                                    <p class="value" id="modalSenderName" style="margin: 0.25rem 0 0 0; font-weight: 500;">Loading...</p>
                                </div>
                            </div>
                            <div class="detail-item" style="display: flex; align-items: center; margin-bottom: 1rem;">
                                <i class="fas fa-phone" style="width: 20px; margin-right: 10px; color: #4A1C40;"></i>
                                <div>
                                    <p class="label" style="margin: 0; font-weight: 600; color: #666;">Sender Phone</p>
                                    <p class="value" id="modalSenderPhone" style="margin: 0.25rem 0 0 0; font-weight: 500;">Loading...</p>
                                </div>
                            </div>
                        </div>

                        <div class="detail-section" style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                            <h3 style="margin-top: 0; color: #2E0D2A; border-bottom: 2px solid #2E0D2A; padding-bottom: 0.5rem;">Route Information</h3>
                            <div class="detail-item" style="display: flex; align-items: center; margin-bottom: 1rem;">
                                <i class="fas fa-building" style="width: 20px; margin-right: 10px; color: #4A1C40;"></i>
                                <div>
                                    <p class="label" style="margin: 0; font-weight: 600; color: #666;">Origin Outlet</p>
                                    <p class="value" id="modalOriginOutlet" style="margin: 0.25rem 0 0 0; font-weight: 500;">Loading...</p>
                                </div>
                            </div>
                            <div class="detail-item" style="display: flex; align-items: center; margin-bottom: 1rem;">
                                <i class="fas fa-building" style="width: 20px; margin-right: 10px; color: #4A1C40;"></i>
                                <div>
                                    <p class="label" style="margin: 0; font-weight: 600; color: #666;">Destination Outlet</p>
                                    <p class="value" id="modalDestinationOutlet" style="margin: 0.25rem 0 0 0; font-weight: 500;">Loading...</p>
                                </div>
                            </div>
                        </div>
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
<?php include __DIR__ . '/../../includes/footer.php'; ?>
