<?php
     $page_title = 'Company - View Delivery';
     include __DIR__ . '/../includes/header.php';
 ?>

<body class="bg-gray-100 min-h-screen">

    <div class="mobile-dashboard">

        <!-- Main Content Area for Delivery Details -->
        <main class="main-content">
            <div class="content-header">
                <h1>Delivery Details: TRK123456</h1>
            </div>

            <div class="details-card">
                <h2>Parcel Information</h2>

                <div class="detail-item">
                    <span class="label">Tracking Number:</span>
                    <span class="value">TRK123456</span>
                </div>
                <div class="detail-item">
                    <span class="label">Origin:</span>
                    <span class="value">Warehouse A (123 Industrial Rd, City, Country)</span>
                </div>
                <div class="detail-item">
                    <span class="label">Destination:</span>
                    <span class="value">Customer Address 1 (456 Residential Ave, Town, Country)</span>
                </div>
                <div class="detail-item">
                    <span class="label">Recipient Name:</span>
                    <span class="value">John Doe</span>
                </div>
                <div class="detail-item">
                    <span class="label">Recipient Phone:</span>
                    <span class="value">+1 (555) 987-6543</span>
                </div>
                <div class="detail-item">
                    <span class="label">Status:</span>
                    <span class="value"><span class="status-badge status-in-transit">In Transit</span></span>
                </div>
                <div class="detail-item">
                    <span class="label">Assigned Driver:</span>
                    <span class="value">Driver 1 (Ethan Carter)</span>
                </div>
                <div class="detail-item">
                    <span class="label">Delivery Date/Time:</span>
                    <span class="value">2024-07-20 10:00 AM</span>
                </div>
                <div class="detail-item">
                    <span class="label">Service Type:</span>
                    <span class="value">Standard Delivery</span>
                </div>
                <div class="detail-item">
                    <span class="label">Weight:</span>
                    <span class="value">5 kg</span>
                </div>
                <div class="detail-item">
                    <span class="label">Dimensions (LxWxH):</span>
                    <span class="value">30x20x15 cm</span>
                </div>
                <div class="detail-item">
                    <span class="label">Special Instructions:</span>
                    <span class="value">Leave at front door if no answer.</span>
                </div>

                <div class="details-button-group">
                    <button class="action-btn secondary" id="backToDeliveriesBtn"  onclick="window.location.href='deliveries.html'">
                        <i class="fas fa-arrow-left"></i> Back to Deliveries
                    </button>
                    <button class="action-btn" id="editDeliveryBtn">
                        <i class="fas fa-edit"></i> Edit Delivery
                    </button>
                </div>
            </div>
        </main>
    </div>

    <!-- Link to the external JavaScript file -->
    <script src="company-manager-scripts.js"></script>
</body>
</html>