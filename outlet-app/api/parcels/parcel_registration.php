<?php
require_once '../includes/auth_guard.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$current_user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Register New Parcel</title>

    <!-- Poppins Font -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <!-- Styles moved to parcel_registration.css -->
    
    <link rel="stylesheet" href="../css/outlet-dashboard.css">
    <link rel="stylesheet" href="../css/parcel_registration.css">
    <link rel="stylesheet" href="../css/trip_parcel_bridge.css">
    <link rel="stylesheet" href="../assets/css/create_parcel_enhanced.css">
    
    <!-- Error Handler for Browser Extension Issues -->
    <script src="../assets/js/error-handler.js"></script>
   
</head>

<body class="bg-gray-100 min-h-screen">

    <div class="mobile-dashboard">

        
         <?php include '../includes/navbar.php'; ?>
        <?php include '../includes/sidebar.php'; ?>

        <!-- Main Content Area for Create New Parcel -->
        <main class="main-content">
            <div class="content-container">
                <div style="text-align: center; margin-bottom: 30px;">
                    <h1>Register New Parcel</h1>
                    <p class="subtitle">Fill in the details below to register a new parcel for delivery.</p>
                </div>

                <form id="newParcelForm" enctype="multipart/form-data">
                    <input type="hidden" id="originOutletId" name="originOutletId" value="<?php echo htmlspecialchars($_SESSION['outlet_id'] ?? ''); ?>">
                    <input type="hidden" id="companyId" name="companyId" value="<?php echo htmlspecialchars($_SESSION['company_id'] ?? ''); ?>">
                    
                    <!-- Left Column -->
                    <div class="form-column-left">
                        <!-- Sender Information -->
                        <div class="form-section">
                            <h3><i class="fas fa-user"></i> Sender Information</h3>
                            
                            <!-- Sender NRC (First Field) -->
                            <div class="form-group">
                                <label for="senderNRC"><i class="fas fa-id-card"></i> NRC Number</label>
                                <input type="text" id="senderNRC" name="senderNRC" placeholder="e.g., 123456/78/9" class="form-control">
                                <small class="form-help">National Registration Card number</small>
                            </div>

                            <div class="form-group">
                                <label for="senderName">Sender Name <span class="required">*</span></label>
                                <input type="text" id="senderName" name="senderName" placeholder="Enter sender's full name" required>
                            </div>

                            <div class="form-group">
                                <label for="senderPhone">Sender Phone <span class="required">*</span></label>
                                <input type="tel" id="senderPhone" name="senderPhone" placeholder="+260 XXX XXX XXX" required>
                            </div>

                            <div class="form-group">
                                <label for="senderEmail">Sender Email</label>
                                <input type="email" id="senderEmail" name="senderEmail" placeholder="sender@example.com">
                            </div>

                            <div class="form-group">
                                <label for="senderAddress">Sender Address <span class="required">*</span></label>
                                <textarea id="senderAddress" name="senderAddress" placeholder="Enter sender's complete address" required></textarea>
                            </div>
                        </div>

                        <!-- Parcel Details -->
                        <div class="form-section">
                            <h3><i class="fas fa-box"></i> Parcel Details</h3>
                            
                            <div class="form-group">
                                <label for="trackingNumber">Tracking Number</label>
                                <input type="text" id="trackingNumber" name="trackingNumber" placeholder="Auto-generated" readonly>
                                <small class="form-help">Will be auto-generated upon registration</small>
                            </div>

                            <div class="form-group">
                                <label for="itemDescription">Item Description <span class="required">*</span></label>
                                <textarea id="itemDescription" name="itemDescription" placeholder="Describe the item(s) in the parcel" required></textarea>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="parcelWeight">Weight (kg) <span class="required">*</span></label>
                                    <input type="number" id="parcelWeight" name="parcelWeight" step="0.1" min="0.1" placeholder="0.0" required>
                                </div>

                                <div class="form-group">
                                    <label for="dimensions">Dimensions (cm)</label>
                                    <input type="text" id="dimensions" name="dimensions" placeholder="L x W x H">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="value">Declared Value (K)</label>
                                <input type="number" id="value" name="value" step="0.01" min="0" placeholder="0.00">
                                <small class="form-help">For insurance purposes</small>
                            </div>

                            <div class="form-group">
                                <label for="specialInstructions">Special Instructions</label>
                                <textarea id="specialInstructions" name="specialInstructions" placeholder="Any special handling instructions"></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Column -->
                    <div class="form-column-right">
                        <!-- Recipient Information -->
                        <div class="form-section">
                            <h3><i class="fas fa-user-check"></i> Recipient Information</h3>
                            
                            <!-- Recipient NRC (First Field) -->
                            <div class="form-group">
                                <label for="recipientNRC"><i class="fas fa-id-card"></i> NRC Number</label>
                                <input type="text" id="recipientNRC" name="recipientNRC" placeholder="e.g., 654321/87/6" class="form-control">
                                <small class="form-help">Recipient's National Registration Card number</small>
                            </div>

                            <div class="form-group">
                                <label for="recipientName">Recipient Name <span class="required">*</span></label>
                                <input type="text" id="recipientName" name="recipientName" placeholder="Enter recipient's full name" required>
                            </div>

                            <div class="form-group">
                                <label for="recipientPhone">Recipient Phone <span class="required">*</span></label>
                                <input type="tel" id="recipientPhone" name="recipientPhone" placeholder="+260 XXX XXX XXX" required>
                            </div>

                            <div class="form-group">
                                <label for="recipientEmail">Recipient Email</label>
                                <input type="email" id="recipientEmail" name="recipientEmail" placeholder="recipient@example.com">
                            </div>

                            <div class="form-group">
                                <label for="destinationOutletId">Destination Outlet <span class="required">*</span></label>
                                <select id="destinationOutletId" name="destinationOutletId" required>
                                    <option value="">Select destination outlet</option>
                                    <!-- Will be populated via JavaScript -->
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="recipientAddress">Delivery Address <span class="required">*</span></label>
                                <textarea id="recipientAddress" name="recipientAddress" placeholder="Enter complete delivery address" required></textarea>
                            </div>
                        </div>

                        <!-- Financial Information -->
                        <div class="form-section">
                            <h3><i class="fas fa-dollar-sign"></i> Financial Information</h3>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="deliveryFee">Delivery Fee (K) <span class="required">*</span></label>
                                    <input type="number" id="deliveryFee" name="deliveryFee" step="0.01" min="0" placeholder="0.00" required>
                                </div>

                                <div class="form-group">
                                    <label for="insuranceAmount">Insurance (K)</label>
                                    <input type="number" id="insuranceAmount" name="insuranceAmount" step="0.01" min="0" placeholder="0.00">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="codAmount">Cash on Delivery (K)</label>
                                <input type="number" id="codAmount" name="codAmount" step="0.01" min="0" placeholder="0.00">
                            </div>

                            <div class="form-group">
                                <label for="paymentMethod">Payment Method <span class="required">*</span></label>
                                <select id="paymentMethod" name="paymentMethod" required>
                                    <option value="">Select payment method</option>
                                    <option value="cash">Cash</option>
                                    <option value="mobile_money">Mobile Money</option>
                                    <option value="visa">Visa</option>
                                    <option value="cod">Cash on Delivery</option>
                                </select>
                            </div>

                            <!-- Delivery Option: always standard, hide select -->
                            <div class="form-group">
                                <label for="deliveryOption">Delivery Option <span class="required">*</span></label>
                                <input type="hidden" id="deliveryOption" name="deliveryOption" value="standard">
                                <span style="font-weight:500;">Standard</span>
                            </div>
                        </div>
                        
                        <!-- Trip Assignment -->
                        <div class="trip-assignment-container" id="tripAssignmentSection">
                            <h3><i class="fas fa-route"></i> Trip Assignment <span class="optional-badge">(Optional)</span></h3>
                            
                            <!-- Instruction Message (shown when destination not selected) -->
                            <div class="trip-instruction-message" id="tripInstructionMessage">
                                <i class="fas fa-info-circle"></i>
                                <p>Please select a <strong>Destination Outlet</strong> first to see available trips for that route.</p>
                            </div>
                            
                            <div class="trip-assignment-content" id="tripAssignmentContent" style="display: none;">
                                <div class="trip-selector">
                                    <label for="tripId">Assign to Trip</label>
                                    <select id="tripId" name="tripId" disabled>
                                        <option value="">-- Select Destination First --</option>
                                        <!-- Trips will be loaded via JavaScript -->
                                    </select>
                                    <small class="form-help">Only trips going to the selected destination will appear here</small>
                                </div>
                                
                                <div class="trip-info" id="tripInfo" style="display: none;">
                                    <h4><i class="fas fa-info-circle"></i> Trip Details</h4>
                                    <div class="trip-details">
                                        <div class="detail-row">
                                            <span class="label">Vehicle</span>
                                            <span class="value" id="tripVehicle">Vehicle Loading...</span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="label">Plate Number</span>
                                            <span class="value" id="tripPlateNumber">-</span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="label">Route</span>
                                            <span class="value" id="tripRoute">-</span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="label">Origin</span>
                                            <span class="value" id="tripOrigin">-</span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="label">Destination</span>
                                            <span class="value" id="tripDestination">-</span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="label">Status</span>
                                            <span class="value status-badge" id="tripStatus">-</span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="label">Departure</span>
                                            <span class="value" id="tripDeparture">-</span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="label">Manager</span>
                                            <span class="value" id="tripManager">-</span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="label">Manager Phone</span>
                                            <span class="value" id="tripManagerPhone">-</span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="label">Total Stops</span>
                                            <span class="value" id="tripTotalStops">-</span>
                                        </div>
                                    </div>
<script>

document.addEventListener('DOMContentLoaded', function() {
    const tripSelect = document.getElementById('tripId');
    if (!tripSelect) return;
    tripSelect.addEventListener('change', async function() {
        const tripId = this.value;
        const tripInfo = document.getElementById('tripInfo');
        
        if (!tripId) {
            
            if (tripInfo) tripInfo.style.display = 'none';
            return;
        }
        
        
        try {
            const tripRes = await fetch('../api/trips/fetch_trip_details.php?trip_id=' + encodeURIComponent(tripId));
            const tripData = await tripRes.json();
            
            if (!tripData.success || !tripData.trip) {
                console.error('Failed to fetch trip details:', tripData.error || 'No trip data');
                document.getElementById('tripVehicle').textContent = 'Error: ' + (tripData.error || 'No trip data');
                document.getElementById('tripPlateNumber').textContent = '-';
                document.getElementById('tripRoute').textContent = '-';
                document.getElementById('tripOrigin').textContent = '-';
                document.getElementById('tripDestination').textContent = '-';
                document.getElementById('tripStatus').textContent = '-';
                document.getElementById('tripDeparture').textContent = '-';
                document.getElementById('tripManager').textContent = '-';
                document.getElementById('tripManagerPhone').textContent = '-';
                document.getElementById('tripTotalStops').textContent = '-';
                return;
            }
            
            const trip = tripData.trip;
            
            
            document.getElementById('tripVehicle').textContent = trip.vehicle_name || '-';
            document.getElementById('tripPlateNumber').textContent = trip.plate_number || '-';
            document.getElementById('tripRoute').textContent = trip.route || '-';
            document.getElementById('tripOrigin').textContent = trip.origin || '-';
            document.getElementById('tripDestination').textContent = trip.destination || '-';
            
            
            const statusElement = document.getElementById('tripStatus');
            statusElement.textContent = trip.status ? trip.status.charAt(0).toUpperCase() + trip.status.slice(1) : '-';
            statusElement.className = 'value status-badge ' + (trip.status || '').toLowerCase();
            
            
            const departureElement = document.getElementById('tripDeparture');
            if (trip.departure && trip.departure !== '-') {
                try {
                    const date = new Date(trip.departure);
                    departureElement.textContent = date.toLocaleString('en-US', {
                        month: 'short',
                        day: 'numeric',
                        year: 'numeric',
                        hour: 'numeric',
                        minute: '2-digit',
                        hour12: true
                    });
                } catch (e) {
                    departureElement.textContent = trip.departure;
                }
            } else {
                departureElement.textContent = 'Not scheduled';
            }
            
            document.getElementById('tripManager').textContent = trip.manager_name || '-';
            document.getElementById('tripManagerPhone').textContent = trip.manager_phone || '-';
            document.getElementById('tripTotalStops').textContent = trip.total_stops ? trip.total_stops + ' stops' : '0 stops';
            
            
            if (tripInfo) tripInfo.style.display = 'block';
            
        } catch (err) {
            console.error('Error fetching trip details:', err);
            document.getElementById('tripVehicle').textContent = 'Error fetching trip details';
            document.getElementById('tripPlateNumber').textContent = '-';
            document.getElementById('tripRoute').textContent = '-';
            document.getElementById('tripOrigin').textContent = '-';
            document.getElementById('tripDestination').textContent = '-';
            document.getElementById('tripStatus').textContent = '-';
            document.getElementById('tripDeparture').textContent = '-';
            document.getElementById('tripManager').textContent = '-';
            document.getElementById('tripManagerPhone').textContent = '-';
            document.getElementById('tripTotalStops').textContent = '-';
        }
    });
});
</script>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Photo Upload & Submit - Full Width -->
                    <div class="form-section photo-upload-section" style="grid-column: 1 / -1;">
                        <h3><i class="fas fa-camera"></i> Parcel Photos & Submit</h3>
                        
                        <div class="photo-upload-grid">
                            <div class="upload-area">
                                <div class="form-group">
                                    <label>Upload Parcel Photos (Max 5 photos, 5MB each)</label>
                                            <label for="parcelPhotos"><i class="fas fa-camera"></i> Upload Parcel Photos</label>
                                            <input type="file" id="parcelPhotos" name="parcelPhotos[]" accept="image/*" multiple style="display:none;">
                                            <div id="uploadZone" class="upload-zone" style="cursor:pointer;">
                                                <span class="upload-icon"><i class="fas fa-cloud-upload-alt"></i></span>
                                                <p id="uploadZoneText" style="cursor:pointer;">Click here or drag and drop images</p>
                                                <small>Supported formats: JPG, PNG, GIF</small>
                                            </div>
    <!-- Camera and upload logic is now handled in parcel_registration.js only -->
                                        <button type="button" id="cameraBtn" class="btn btn-primary" style="margin-top: 1rem;">
                                            <i class="fas fa-camera"></i> Take Photo
                                        </button>
                                        <div id="photoPreview" class="photo-preview"></div>
                            </div>
                            
                            <div class="submit-area">
                                <!-- Form Actions -->
                                <div class="form-actions">
                                    <button type="button" class="btn-secondary" onclick="window.history.back()">
                                        <i class="fas fa-arrow-left"></i> Cancel
                                    </button>
                                    <button type="submit" class="btn-primary" id="submitBtn">
                                        <i class="fas fa-paper-plane"></i> Register Parcel
                                    </button>
                                </div>
                                
                                <div class="submit-info">
                                    <p><i class="fas fa-info-circle"></i> Please review all information before submitting.</p>
                                    <p><i class="fas fa-shield-alt"></i> Your parcel will be assigned a tracking number upon registration.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </main>

    </div>

    <!-- Success Modal -->
    <div id="successModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header success">
                <h2><i class="fas fa-check-circle"></i> Parcel Registered Successfully!</h2>
            </div>
            <div class="modal-body">
                <p>Your parcel has been registered successfully.</p>
                <p><strong>Tracking Number:</strong> <span id="modalTrackingNumber"></span></p>
                <div class="modal-actions">
                    <button type="button" class="btn-primary" onclick="closeModal()">Continue</button>
                    <button type="button" class="btn-secondary" onclick="printLabel()">Print Label</button>
                </div>
            </div>
        </div>
    </div>
<script src="../assets/js/sidebar-toggle.js"></script>
<script src="../assets/js/notifications.js"></script>
</body>
</html>
