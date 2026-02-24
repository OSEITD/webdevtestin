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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trip Creation Wizard</title>

    <!-- Poppins Font -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../css/outlet-dashboard.css">
    
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: white;
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }

        .trip-wizard {
            max-width: 1400px; /* Extended to match main-content width */
            margin: 20px auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .wizard-header {
            background: linear-gradient(135deg, #2E0D2A 0%, #4A1C40 100%);
            color: white;
            padding: 30px;
            text-align: center;
            border-bottom: none;
        }

        .wizard-header h1 {
            margin: 0 0 10px 0;
            font-size: 2rem;
            font-weight: 700;
            color: white;
        }

        .wizard-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 1.1rem;
            color: white;
        }

        .wizard-steps {
            display: flex;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }

        .wizard-step {
            flex: 1;
            padding: 20px 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #f8f9fa;
            border: 2px solid transparent;
        }

        .wizard-step:hover {
            background: #e9ecef;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .wizard-step.active {
            background: #4A1C40;
            color: white;
            border-color: #2E0D2A;
        }

        .wizard-step.active:hover {
            background: #2E0D2A;
            transform: translateY(-2px);
        }

        .wizard-step.completed {
            background: #28a745;
            color: white;
            border-color: #1e7e34;
        }

        .wizard-step.completed:hover {
            background: #1e7e34;
            transform: translateY(-2px);
        }

        .wizard-step i {
            display: block;
            font-size: 20px;
            margin-bottom: 8px;
        }

        .step-content {
            display: none;
            padding: 30px;
        }

        .step-content.active {
            display: block;
        }

        .form-section {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid #e9ecef;
        }

        .form-section h3 {
            color: #2E0D2A;
            margin-bottom: 20px;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
            box-sizing: border-box;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #4A1C40;
            box-shadow: 0 0 0 3px rgba(74, 28, 64, 0.1);
        }

        input[type="datetime-local"] {
            position: relative !important;
            z-index: 10 !important;
            cursor: pointer;
        }

        input[type="datetime-local"]::-webkit-calendar-picker-indicator {
            cursor: pointer;
            position: relative;
            z-index: 20;
            opacity: 1;
        }

        input[type="datetime-local"]::-webkit-inner-spin-button {
            z-index: 20;
        }

        .required {
            color: #e74c3c;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: #4A1C40;
            color: white;
        }

        .btn-primary:hover {
            background: #2E0D2A;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .wizard-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 25px 30px;
            background: #f8f9fa;
            border-top: 1px solid #dee2e6;
        }

        .info-box {
            background: #e7f3ff;
            border: 2px solid #4A1C40;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .info-box h4 {
            color: #2E0D2A;
            margin-bottom: 10px;
        }

        .info-box ul {
            margin: 10px 0;
            padding-left: 20px;
            color: #333;
        }

        .stops-container {
            border: 3px dashed #4A1C40;
            border-radius: 12px;
            padding: 25px;
            background: white;
        }

        /* Wider content container and responsive table support */
        .content-container {
            width: 100%;
            max-width: calc(100% - 40px);
            margin: 20px auto;
            box-sizing: border-box;
        }

        .content-container .table-wrapper {
            overflow-x: auto;
        }

        .content-container table {
            width: 100%;
            border-collapse: collapse;
            min-width: 700px;
        }

        .content-container th, .content-container td {
            padding: 12px 15px;
            text-align: left;
            vertical-align: middle;
        }

        @media (max-width: 768px) {
            .content-container table, .content-container thead, .content-container tbody, .content-container th, .content-container td, .content-container tr {
                display: block;
            }
            .content-container thead tr {
                position: absolute;
                top: -9999px;
                left: -9999px;
            }
            .content-container tr {
                margin: 0 0 12px 0;
                border-radius: 8px;
                background: #fff;
                box-shadow: 0 1px 4px rgba(0,0,0,0.05);
                padding: 10px;
            }
            .content-container td {
                border: none;
                position: relative;
                padding-left: 50%;
                white-space: normal;
            }
            .content-container td::before {
                position: absolute;
                left: 12px;
                top: 12px;
                width: 45%;
                padding-right: 10px;
                white-space: nowrap;
                font-weight: 600;
                color: #4a5568;
                content: attr(data-label);
            }
        }

        .stop-item {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 5px solid #ddd;
        }

        .stop-item.origin {
            border-left-color: #28a745;
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
        }

        .stop-item.destination {
            border-left-color: #dc3545;
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
        }

        .stop-item.intermediate {
            border-left-color: #ffc107;
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1001;
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            max-width: 400px;
            animation: slideInRight 0.3s ease;
        }

        .notification.success { background: #28a745; }
        .notification.error { background: #dc3545; }
        .notification.warning { background: #ffc107; color: #333; }
        .notification.info { background: #17a2b8; }

        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        .parcel-item {
            background: white;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .parcel-item:hover {
            border-color: #4A1C40;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .parcel-item.selected {
            border-color: #28a745;
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
        }

        .parcel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .parcel-track {
            font-weight: 600;
            color: #2E0D2A;
            font-size: 1.1rem;
        }

        .parcel-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .parcel-status.pending {
            background: #fff3cd;
            color: #856404;
        }

        .parcel-status.ready_for_dispatch {
            background: #d4edda;
            color: #155724;
        }

        .parcel-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .parcel-detail {
            display: flex;
            flex-direction: column;
        }

        .parcel-detail-label {
            font-size: 0.8rem;
            color: #666;
            margin-bottom: 4px;
        }

        .parcel-detail-value {
            font-weight: 500;
            color: #333;
        }

        .parcel-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .parcel-select-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .parcel-select-btn.select {
            background: #28a745;
            color: white;
        }

        .parcel-select-btn.deselect {
            background: #dc3545;
            color: white;
        }

        .selected-parcels-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 15px;
        }

        .selected-parcel-item {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 8px;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .loading-spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #4A1C40;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @media (max-width: 768px) {
            .wizard-steps {
                flex-direction: column;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .wizard-actions {
                flex-direction: column;
                gap: 10px;
            }

            .main-content {
                padding-left: 0.5rem;
                padding-right: 0.5rem;
                max-width: 100%;
            }

            .content-container {
                margin: 10px 0;
                padding: 0;
                box-shadow: none;
                border-radius: 0;
            }

            .trip-wizard {
                margin: 0;
                border-radius: 0;
                box-shadow: none;
            }

            .wizard-header {
                padding: 20px 15px;
            }

            .wizard-header h1 {
                font-size: 1.5rem;
            }

            .wizard-header p {
                font-size: 0.9rem;
            }

            .step-content {
                padding: 15px;
            }

            .wizard-step {
                padding: 15px 10px;
            }
        }
    </style>
</head>

<body>
    <div class="mobile-dashboard">
        <?php include '../includes/navbar.php'; ?>
        <?php include '../includes/sidebar.php'; ?>

        
        <main class="main-content">
            <div class="content-container">
                <div class="trip-wizard">
                    <!-- Wizard Header -->
                    <div class="wizard-header">
                        <h1><i class="fas fa-route"></i> Trip Creation Wizard</h1>
                        <p>Complete multi-stop trip planning with parcel assignments</p>
                    </div>

                    <!-- Wizard Steps Navigation -->
                    <div class="wizard-steps">
                        <div class="wizard-step active" data-step="1" onclick="goToStep(1)">
                            <i class="fas fa-truck"></i>
                            <div>Step 1: Trip Details</div>
                        </div>
                        <div class="wizard-step" data-step="2" onclick="goToStep(2)">
                            <i class="fas fa-map-marker-alt"></i>
                            <div>Step 2: Route Planning</div>
                        </div>
                        <div class="wizard-step" data-step="3" onclick="goToStep(3)">
                            <i class="fas fa-box"></i>
                            <div>Step 3: Add Parcels</div>
                        </div>
                        <div class="wizard-step" data-step="4" onclick="goToStep(4)">
                            <i class="fas fa-check-circle"></i>
                            <div>Step 4: Review & Submit</div>
                        </div>
                    </div>

                    <!-- Step 1: Trip Details -->
                    <div class="step-content active" id="step1">
                        <form id="tripForm">
                            <input type="hidden" id="companyId" value="<?php echo htmlspecialchars($_SESSION['company_id'] ?? ''); ?>">
                            <input type="hidden" id="managerId" value="<?php echo htmlspecialchars($_SESSION['user_id'] ?? ''); ?>">
                            <input type="hidden" id="currentOutletId" value="<?php echo htmlspecialchars($_SESSION['outlet_id'] ?? ''); ?>">

                            <div class="form-section">
                                <h3><i class="fas fa-truck"></i> Vehicle & Driver Selection</h3>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="vehicleId">Select Vehicle <span class="required">*</span></label>
                                        <select id="vehicleId" name="vehicleId" required>
                                            <option value="">Loading vehicles...</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="driverId">Select Driver <span class="required">*</span></label>
                                        <select id="driverId" name="driverId" required>
                                            <option value="">Loading drivers...</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="departureTime">Planned Departure Time <span class="required">*</span></label>
                                        <input type="datetime-local" id="departureTime" name="departureTime" required
                                               min=""
                                               style="position: relative; z-index: 1;">
                                        <small class="text-muted">Select the planned departure date and time</small>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h3><i class="fas fa-building"></i> Origin & Destination</h3>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="originOutlet">Origin Outlet <span class="required">*</span></label>
                                        <select id="originOutlet" name="originOutlet" required>
                                            <option value="">Loading outlets...</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="destinationOutlet">Destination Outlet <span class="required">*</span></label>
                                        <select id="destinationOutlet" name="destinationOutlet" required>
                                            <option value="">Select destination outlet</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Step 2: Route Planning -->
                    <div class="step-content" id="step2">
                        <div class="form-section">
                            <h3><i class="fas fa-map-marker-alt"></i> Trip Stops Management</h3>
                            <p>Manage the route stops for this trip. You can add intermediate stops between origin and destination.</p>
                            
                            <div class="stops-container">
                                <div id="stopsDisplay">
                                    <p style="color: #6c757d; text-align: center;">Select origin and destination to see route</p>
                                </div>
                                
                                <div class="form-group" style="margin-top: 20px;">
                                    <label for="intermediateOutlet">Add Intermediate Stop</label>
                                    <div style="display: flex; gap: 10px; align-items: flex-end;">
                                        <select id="intermediateOutlet" style="flex: 1;">
                                            <option value="">Select intermediate outlet</option>
                                        </select>
                                        <button type="button" class="btn btn-primary" onclick="addIntermediateStop()">
                                            <i class="fas fa-plus"></i> Add Stop
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Add Parcels -->
                    <div class="step-content" id="step3">
                        <div class="form-section">
                            <h3><i class="fas fa-box"></i> Parcel Assignment (Optional)</h3>
                            <div class="info-box">
                                <h4><i class="fas fa-lightbulb"></i> Smart Parcel Filtering</h4>
                                <ul>
                                    <li><strong>Empty Trips Allowed:</strong> You can create trips without parcels - they act as empty containers</li>
                                    <li><strong>Route-based Filtering:</strong> Only parcels with destinations matching your selected route are shown</li>
                                    <li><strong>Available Parcels:</strong> Unassigned parcels with status "pending" or "ready for dispatch"</li>
                                    <li><strong>Automatic Updates:</strong> Parcel list refreshes when you change origin, destination, or stops</li>
                                    <li><strong>Trip Assignment:</strong> Selected parcels will be automatically assigned to appropriate trip stops</li>
                                    <li><strong>Status Tracking:</strong> Assigned parcels become part of the trip manifest for tracking</li>
                                    <li><strong>Later Assignment:</strong> Parcels can be assigned to existing trips after creation</li>
                                </ul>
                                <div id="routeInfo" style="margin-top: 15px; padding: 10px; background: rgba(74, 28, 64, 0.1); border-radius: 6px; font-size: 0.9rem;">
                                    <strong><i class="fas fa-route"></i> Current Route Outlets:</strong>
                                    <span id="routeOutletsList">Select origin and destination first</span>
                                </div>
                            </div>
                            
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                                <button type="button" class="btn btn-primary" onclick="loadAvailableParcels()" id="loadParcelsBtn">
                                    <i class="fas fa-refresh"></i> Load Available Parcels
                                </button>
                                <div id="parcelStats" style="color: #666; font-size: 0.9rem;">
                                    <!-- Parcel statistics  -->
                                </div>
                            </div>
                            
                            <div id="parcelsContainer">
                                <div id="parcelLoadingState" style="text-align: center; padding: 40px; color: #666;">
                                    <i class="fas fa-box fa-2x" style="margin-bottom: 10px; opacity: 0.5;"></i>
                                    <p>Click "Load Available Parcels" to see parcels ready for assignment</p>
                                </div>
                            </div>

                            <!-- Selected Parcels Summary -->
                            <div id="selectedParcelsSection" style="display: none;">
                                <h4 style="margin-top: 30px; margin-bottom: 15px; color: #2E0D2A;">
                                    <i class="fas fa-check-circle"></i> Selected Parcels (<span id="selectedCount">0</span>)
                                </h4>
                                <div id="selectedParcelsList" class="selected-parcels-list">
                                    <!-- Selected parcels  -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 4: Review & Submit -->
                    <div class="step-content" id="step4">
                        <div class="form-section">
                            <h3><i class="fas fa-clipboard-check"></i> Trip Summary</h3>
                            <div id="tripSummary">
                                <p style="color: #6c757d; text-align: center;">Complete previous steps to see summary</p>
                            </div>
                        </div>
                    </div>

                    <!-- Wizard Actions -->
                    <div class="wizard-actions">
                        <button type="button" class="btn btn-secondary" id="prevBtn" onclick="changeStep(-1)" disabled>
                            <i class="fas fa-arrow-left"></i> Previous
                        </button>
                        <button type="button" class="btn btn-primary" id="nextBtn" onclick="changeStep(1)">
                            Next <i class="fas fa-arrow-right"></i>
                        </button>
                        <button type="button" class="btn btn-success" id="submitBtn" onclick="submitTrip()" style="display: none;">
                            <i class="fas fa-check"></i> Create Trip
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        
        let currentStep = 1;
        const maxSteps = 4;
        let tripData = {
            vehicle: null,
            originOutlet: null,
            destinationOutlet: null,
            stops: [],
            parcels: [],
            departureTime: null,
            selectedParcels: [] 
        };
        let availableParcels = []; 
        
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Trip wizard initialized');
            loadInitialData();
            updateStepDisplay();
            
            
            setTimeout(() => {
                setupDateTimeInput();
            }, 500);
            
            
            document.addEventListener('click', function(e) {
                if (e.target.closest('.remove-stop-btn')) {
                    const button = e.target.closest('.remove-stop-btn');
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const index = parseInt(button.getAttribute('data-stop-index'));
                    console.log('Remove button clicked for stop index:', index);
                    
                    if (!isNaN(index)) {
                        removeIntermediateStop(index);
                    } else {
                        console.error('Invalid stop index from button:', button.getAttribute('data-stop-index'));
                    }
                }
            });
        });

        function setupDateTimeInput() {
            const departureTimeInput = document.getElementById('departureTime');
            if (departureTimeInput) {
                console.log('Setting up datetime input');
                
                
                departureTimeInput.addEventListener('click', function(e) {
                    console.log('Datetime input clicked');
                    this.focus();
                    this.click();
                });
                
                
                departureTimeInput.addEventListener('focus', function(e) {
                    console.log('Datetime input focused');
                   
                    if (this.showPicker && typeof this.showPicker === 'function') {
                        try {
                            this.showPicker();
                        } catch (err) {
                            console.log('showPicker not available or failed');
                        }
                    }
                });
                
                
                departureTimeInput.removeAttribute('readonly');
                departureTimeInput.removeAttribute('disabled');
                
                console.log('Datetime input setup complete');
            } else {
                console.error('Datetime input not found');
            }
        }

        
        async function loadInitialData() {
            try {
                showMessage('Loading data...', 'info');
                
                const vehicleResponse = await fetch('../api/fetch_vehicles.php', {
                    credentials: 'same-origin'
                });
                if (vehicleResponse.ok) {
                    const vehicleData = await vehicleResponse.json();
                    if (vehicleData.success && vehicleData.vehicles) {
                        populateVehicleOptions(vehicleData.vehicles);
                    } else if (vehicleData.error) {
                        console.error('Failed to load vehicles:', vehicleData.error);
                        showMessage('Failed to load vehicles: ' + vehicleData.error, 'error');
                    } else if (Array.isArray(vehicleData)) {
                        
                        populateVehicleOptions(vehicleData);
                    } else {
                        console.error('Unexpected vehicle response format:', vehicleData);
                        showMessage('Failed to load vehicles: Unexpected response format', 'error');
                    }
                } else {
                    const errorData = await vehicleResponse.json().catch(() => ({}));
                    console.error('Vehicle API error:', vehicleResponse.status, errorData);
                    showMessage(`Failed to load vehicles: HTTP ${vehicleResponse.status}`, 'error');
                }

                
                const outletResponse = await fetch('../api/outlets/fetch_company_outlets.php', {
                    credentials: 'same-origin'
                });
                if (outletResponse.ok) {
                    const outletData = await outletResponse.json();
                    if (outletData.success && outletData.outlets) {
                        populateOutletOptions(outletData.outlets);
                    } else {
                        console.error('Failed to load outlets:', outletData.error || 'Unknown error');
                        showMessage('Failed to load outlets: ' + (outletData.error || 'Unknown error'), 'error');
                    }
                } else {
                    const errorData = await outletResponse.json().catch(() => ({}));
                    console.error('Outlets API error:', outletResponse.status, errorData);
                    showMessage(`Failed to load outlets: HTTP ${outletResponse.status}`, 'error');
                }

                
                const driverResponse = await fetch('../api/drivers/fetch_drivers.php', {
                    credentials: 'same-origin'
                });
                if (driverResponse.ok) {
                    const driverData = await driverResponse.json();
                    if (driverData.success && driverData.drivers) {
                        populateDriverOptions(driverData.drivers);
                    } else if (driverData.error) {
                        console.error('Failed to load drivers:', driverData.error);
                        showMessage('Failed to load drivers: ' + driverData.error, 'error');
                    } else if (Array.isArray(driverData)) {
                        
                        populateDriverOptions(driverData);
                    } else {
                        console.error('Unexpected driver response format:', driverData);
                        showMessage('Failed to load drivers: Unexpected response format', 'error');
                    }
                } else {
                    const errorData = await driverResponse.json().catch(() => ({}));
                    console.error('Driver API error:', driverResponse.status, errorData);
                    showMessage(`Failed to load drivers: HTTP ${driverResponse.status}`, 'error');
                }

                
                const currentOutletId = document.getElementById('currentOutletId').value;
                if (currentOutletId) {
                    const originSelect = document.getElementById('originOutlet');
                    if (originSelect) {
                        originSelect.value = currentOutletId;
                        handleOriginChange();
                    }
                }

               
                initializeDateTimeInput();

                showMessage('Data loaded successfully', 'success');
            } catch (error) {
                console.error('Error loading data:', error);
                showMessage('Error loading data: ' + error.message, 'error');
            }
        }

        function initializeDateTimeInput() {
            const departureTimeInput = document.getElementById('departureTime');
            if (departureTimeInput) {
                console.log('Initializing datetime input');
                
                const testInput = document.createElement('input');
                testInput.type = 'datetime-local';
                const isSupported = testInput.type === 'datetime-local';
                
                console.log('datetime-local supported:', isSupported);
                
                const now = new Date();
                const minDateTime = new Date(now.getTime() + (30 * 60000)); 
                departureTimeInput.min = minDateTime.toISOString().slice(0, 16);
                
                
                const defaultDateTime = new Date(now.getTime() + (60 * 60000)); 
                departureTimeInput.value = defaultDateTime.toISOString().slice(0, 16);
                
              
                departureTimeInput.addEventListener('change', function() {
                    const selectedTime = new Date(this.value);
                    const minTime = new Date(this.min);
                    
                    if (selectedTime < minTime) {
                        showMessage('Departure time must be at least 30 minutes from now', 'error');
                        this.value = minTime.toISOString().slice(0, 16);
                    }
                });
                
                departureTimeInput.style.position = 'relative';
                departureTimeInput.style.zIndex = '10';
                
                
                if (!isSupported) {
                    console.log('Adding fallback for unsupported datetime-local');
                    departureTimeInput.type = 'text';
                    departureTimeInput.placeholder = 'YYYY-MM-DD HH:MM';
                    departureTimeInput.pattern = '\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}';
                }
                
                console.log('Datetime input initialization complete');
            }
        }

        function populateVehicleOptions(vehicles) {
            const select = document.getElementById('vehicleId');
            if (!select) return;
            
            select.innerHTML = '<option value="">Select a vehicle</option>';
            
            if (Array.isArray(vehicles)) {
                vehicles.forEach(vehicle => {
                    if (vehicle && vehicle.status) {
                        const option = document.createElement('option');
                        option.value = vehicle.id;
                        
                        const vehicleName = vehicle.name || 'Vehicle';
                        const plateNumber = vehicle.plate_number || 'No Plate';
                        option.textContent = `${vehicleName} (${plateNumber})`;
                        select.appendChild(option);
                    }
                });
            }
        }

        function populateDriverOptions(drivers) {
            const select = document.getElementById('driverId');
            if (!select) return;
            
            select.innerHTML = '<option value="">Select a driver</option>';
            
            if (Array.isArray(drivers)) {
                drivers.forEach(driver => {
                    if (driver && driver.status === 'available') {
                        const option = document.createElement('option');
                        option.value = driver.id;
                        
                        const driverName = driver.driver_name || 'Driver';
                        const driverPhone = driver.driver_phone || 'No Phone';
                        const driverEmail = driver.driver_email ? ` - ${driver.driver_email.substring(0, 20)}...` : '';
                        const driverId = driver.id ? ` [${driver.id.substring(0, 8)}...]` : '';
                        option.textContent = `${driverName} (${driverPhone})${driverEmail}${driverId}`;
                        select.appendChild(option);
                    }
                });
            }
        }

        function populateOutletOptions(outlets) {
            const originSelect = document.getElementById('originOutlet');
            const destSelect = document.getElementById('destinationOutlet');
            const intermediateSelect = document.getElementById('intermediateOutlet');

            [originSelect, destSelect, intermediateSelect].forEach(select => {
                if (select) {
                    select.innerHTML = '<option value="">Select outlet</option>';
                }
            });

            if (Array.isArray(outlets)) {
                outlets.forEach(outlet => {
                    if (outlet && (outlet.name || outlet.outlet_name)) {
                        [originSelect, destSelect, intermediateSelect].forEach(select => {
                            if (select) {
                                const option = document.createElement('option');
                                option.value = outlet.id;
                               
                                option.textContent = outlet.name || outlet.outlet_name;
                                option.dataset.location = outlet.location || outlet.address || '';
                                select.appendChild(option);
                            }
                        });
                    }
                });
            }

            
            if (originSelect) {
                originSelect.addEventListener('change', handleOriginChange);
            }
            if (destSelect) {
                destSelect.addEventListener('change', handleDestinationChange);
            }
        }

        function handleDestinationChange() {
            updateStopsDisplay();
            
            
            if (currentStep === 3) {
                const originSelect = document.getElementById('originOutlet');
                const destSelect = document.getElementById('destinationOutlet');
                const originId = originSelect?.value;
                const destId = destSelect?.value;
                
                if (originId && destId) {
                    console.log('Destination changed - auto-refreshing parcels');
                    setTimeout(() => loadAvailableParcels(), 100); 
                }
            }
        }

        function handleOriginChange() {
            const originSelect = document.getElementById('originOutlet');
            const destSelect = document.getElementById('destinationOutlet');
            const intermediateSelect = document.getElementById('intermediateOutlet');
            
            if (!originSelect) return;
            
            const originId = originSelect.value;
            
           
            [destSelect, intermediateSelect].forEach(select => {
                if (select) {
                    Array.from(select.options).forEach(option => {
                        option.disabled = option.value === originId;
                    });
                }
            });

            updateStopsDisplay();
            
            
            if (currentStep === 3) {
                const destId = destSelect?.value;
                if (originId && destId) {
                    console.log('Route changed - auto-refreshing parcels');
                    setTimeout(() => loadAvailableParcels(), 100); 
                }
            }
        }

        function updateStopsDisplay() {
            console.log('updateStopsDisplay called, current stops:', tripData.stops);
            
            const originSelect = document.getElementById('originOutlet');
            const destSelect = document.getElementById('destinationOutlet');
            const stopsDisplay = document.getElementById('stopsDisplay');

            if (!stopsDisplay) {
                console.error('stopsDisplay element not found');
                return;
            }

            const originId = originSelect?.value;
            const destId = destSelect?.value;

            if (!originId && !destId) {
                stopsDisplay.innerHTML = '<p style="color: #6c757d; text-align: center;">Select origin and destination to see route</p>';
                return;
            }

            let html = '';

            
            if (originId && originSelect) {
                const originText = originSelect.options[originSelect.selectedIndex]?.text || 'Origin';
                html += `
                    <div class="stop-item origin">
                        <div>
                            <strong><i class="fas fa-play"></i> Origin: ${originText}</strong>
                            <small style="display: block; color: #666;">Stop 1</small>
                        </div>
                    </div>
                `;
            }

            
            tripData.stops.forEach((stop, index) => {
                html += `
                    <div class="stop-item intermediate">
                        <div>
                            <strong><i class="fas fa-map-marker"></i> ${stop.name}</strong>
                            <small style="display: block; color: #666;">Stop ${index + 2}</small>
                        </div>
                        <button type="button" 
                                class="btn btn-danger remove-stop-btn" 
                                data-stop-index="${index}"
                                onclick="event.stopPropagation(); removeIntermediateStop(${index})" 
                                style="padding: 5px 10px; cursor: pointer;">
                            <i class="fas fa-trash"></i> Remove
                        </button>
                    </div>
                `;
            });

            
            if (destId && destSelect) {
                const destText = destSelect.options[destSelect.selectedIndex]?.text || 'Destination';
                html += `
                    <div class="stop-item destination">
                        <div>
                            <strong><i class="fas fa-flag-checkered"></i> Destination: ${destText}</strong>
                            <small style="display: block; color: #666;">Stop ${tripData.stops.length + 2}</small>
                        </div>
                    </div>
                `;
            }

            stopsDisplay.innerHTML = html;
            updateRouteInfo(); 
        }

        function updateRouteInfo() {
            const routeInfo = document.getElementById('routeOutletsList');
            if (!routeInfo) return;
            
            const routeOutlets = getRouteOutletIds();
            const originSelect = document.getElementById('originOutlet');
            const destSelect = document.getElementById('destinationOutlet');
            
            if (routeOutlets.length === 0) {
                routeInfo.textContent = 'Select origin and destination first';
                routeInfo.style.color = '#6c757d';
                return;
            }
            
            
            const routeNames = [];
            
            
            if (originSelect?.value) {
                const originText = originSelect.options[originSelect.selectedIndex]?.text || 'Origin';
                routeNames.push(originText);
            }
            
            
            tripData.stops.forEach(stop => {
                routeNames.push(stop.name);
            });
            
            
            if (destSelect?.value && destSelect.value !== originSelect?.value) {
                const destText = destSelect.options[destSelect.selectedIndex]?.text || 'Destination';
                routeNames.push(destText);
            }
            
            routeInfo.innerHTML = routeNames.join(' <i class="fas fa-arrow-right" style="margin: 0 5px; color: #4A1C40;"></i> ');
            routeInfo.style.color = '#2E0D2A';
        }

        function addIntermediateStop() {
            const select = document.getElementById('intermediateOutlet');
            if (!select || !select.value) {
                showMessage('Please select an outlet for the intermediate stop', 'warning');
                return;
            }

            const selectedId = select.value;
            const selectedText = select.options[select.selectedIndex].text;

            
            if (tripData.stops.some(stop => stop.id === selectedId)) {
                showMessage('This outlet is already added as a stop', 'warning');
                return;
            }

            tripData.stops.push({
                id: selectedId,
                name: selectedText,
                order: tripData.stops.length + 2
            });

            select.value = '';
            updateStopsDisplay();
            showMessage('Intermediate stop added successfully', 'success');
            
            
            if (currentStep === 3) {
                console.log('Intermediate stop added - auto-refreshing parcels');
                setTimeout(() => loadAvailableParcels(), 100);
            }
        }

        function removeIntermediateStop(index) {
            console.log('Attempting to remove stop at index:', index);
            console.log('Current stops before removal:', tripData.stops);
            
            if (index < 0 || index >= tripData.stops.length) {
                console.error('Invalid stop index:', index);
                showMessage('Error: Invalid stop index', 'error');
                return;
            }
            
            try {
                tripData.stops.splice(index, 1);
                console.log('Stops after removal:', tripData.stops);
                updateStopsDisplay();
                showMessage('Intermediate stop removed', 'info');
                
                
                if (currentStep === 3) {
                    console.log('Intermediate stop removed - auto-refreshing parcels');
                    setTimeout(() => loadAvailableParcels(), 100);
                }
            } catch (error) {
                console.error('Error removing stop:', error);
                showMessage('Error removing stop: ' + error.message, 'error');
            }
        }

       
        function changeStep(direction) {
            const newStep = currentStep + direction;
            
            if (newStep < 1 || newStep > maxSteps) return;
            
            if (direction === 1 && !validateCurrentStep()) {
                return;
            }

            currentStep = newStep;
            updateStepDisplay();
        }

      
        function goToStep(stepNumber) {
            console.log('Attempting to go to step:', stepNumber, 'Current step:', currentStep);
            
            if (stepNumber < 1 || stepNumber > maxSteps) {
                console.log('Invalid step number');
                return;
            }
            
            if (stepNumber > currentStep) {
                console.log('Going forward, validating current step');
                if (!validateCurrentStep()) {
                    console.log('Validation failed');
                    return;
                }
            }
            
           
            console.log('Setting current step to:', stepNumber);
            currentStep = stepNumber;
            updateStepDisplay();
        }

        function updateStepDisplay() {
            console.log('Updating step display for step:', currentStep);
            
            document.querySelectorAll('.wizard-step').forEach((step, index) => {
                const stepNum = index + 1;
                step.classList.remove('active', 'completed');
                
                if (stepNum === currentStep) {
                    step.classList.add('active');
                    console.log('Set step', stepNum, 'as active');
                } else if (stepNum < currentStep) {
                    step.classList.add('completed');
                    console.log('Set step', stepNum, 'as completed');
                }
            });

            
            document.querySelectorAll('.step-content').forEach((content, index) => {
                const stepNum = index + 1;
                const isActive = stepNum === currentStep;
                content.classList.toggle('active', isActive);
                console.log('Step content', stepNum, isActive ? 'shown' : 'hidden');
            });

            
            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');
            const submitBtn = document.getElementById('submitBtn');

            if (prevBtn) prevBtn.disabled = currentStep === 1;
            if (nextBtn) nextBtn.style.display = currentStep === maxSteps ? 'none' : 'inline-flex';
            if (submitBtn) {
                submitBtn.style.display = currentStep === maxSteps ? 'inline-flex' : 'none';
                
                if (currentStep === maxSteps) {
                    updateSubmitButtonState();
                }
            }

            
            if (currentStep === 3) {
                const originSelect = document.getElementById('originOutlet');
                const destSelect = document.getElementById('destinationOutlet');
                
                if (originSelect?.value && destSelect?.value) {
                    console.log('Entering step 3 with complete route - loading parcels');
                    setTimeout(() => loadAvailableParcels(), 200);
                }
            } else if (currentStep === 4) {
                updateTripSummary();
                updateSubmitButtonState();
            }
        }

        function validateCurrentStep() {
            console.log('Validating step:', currentStep);
            
            switch (currentStep) {
                case 1:
                    const vehicleId = document.getElementById('vehicleId')?.value;
                    const originId = document.getElementById('originOutlet')?.value;
                    const destinationId = document.getElementById('destinationOutlet')?.value;

                    console.log('Step 1 values:', { vehicleId, originId, destinationId });

                    
                    if (!vehicleId && !originId && !destinationId) {
                        
                        showMessage('Complete the form for full validation', 'info');
                        return true;
                    }

                    if (vehicleId && originId && destinationId) {
                        if (originId === destinationId) {
                            showMessage('Origin and destination cannot be the same', 'error');
                            return false;
                        }
                        
                        
                        tripData.vehicle = vehicleId;
                        tripData.originOutlet = originId;
                        tripData.destinationOutlet = destinationId;
                        tripData.departureTime = document.getElementById('departureTime')?.value;
                    }
                    
                    return true;

                case 2:
                    return true; 

                case 3:
                    return true;

                default:
                    return true;
            }
        }

        function updateTripSummary() {
            const summaryContainer = document.getElementById('tripSummary');
            if (!summaryContainer) return;

            const vehicleSelect = document.getElementById('vehicleId');
            const originSelect = document.getElementById('originOutlet');
            const destSelect = document.getElementById('destinationOutlet');

            const vehicleText = vehicleSelect?.options[vehicleSelect.selectedIndex]?.text || 'No vehicle selected';
            const originText = originSelect?.options[originSelect.selectedIndex]?.text || 'No origin selected';
            const destText = destSelect?.options[destSelect.selectedIndex]?.text || 'No destination selected';

            
            const selectedParcelCount = tripData.selectedParcels.length;
            const totalWeight = tripData.selectedParcels.reduce((sum, parcelId) => {
                const parcel = availableParcels.find(p => p.id === parcelId);
                return sum + (parseFloat(parcel?.parcel_weight || 0));
            }, 0);
            
            const totalValue = tripData.selectedParcels.reduce((sum, parcelId) => {
                const parcel = availableParcels.find(p => p.id === parcelId);
                return sum + (parseFloat(parcel?.declared_value || 0));
            }, 0);

            let html = `
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center;">
                        <h4><i class="fas fa-truck"></i> Vehicle</h4>
                        <p>${vehicleText}</p>
                    </div>
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center;">
                        <h4><i class="fas fa-play"></i> Origin</h4>
                        <p>${originText}</p>
                    </div>
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center;">
                        <h4><i class="fas fa-flag-checkered"></i> Destination</h4>
                        <p>${destText}</p>
                    </div>
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center;">
                        <h4><i class="fas fa-map-marker"></i> Stops</h4>
                        <p>${tripData.stops.length} intermediate</p>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
                    <div style="background: #e7f3ff; padding: 20px; border-radius: 8px; text-align: center; border: 2px solid #4A1C40;">
                        <h4><i class="fas fa-box"></i> Parcels</h4>
                        <p style="font-size: 1.5rem; font-weight: bold; color: #2E0D2A;">${selectedParcelCount}</p>
                    </div>
                    <div style="background: #e7f3ff; padding: 20px; border-radius: 8px; text-align: center; border: 2px solid #4A1C40;">
                        <h4><i class="fas fa-weight"></i> Total Weight</h4>
                        <p style="font-size: 1.5rem; font-weight: bold; color: #2E0D2A;">${totalWeight.toFixed(2)} kg</p>
                    </div>
                    <div style="background: #e7f3ff; padding: 20px; border-radius: 8px; text-align: center; border: 2px solid #4A1C40;">
                        <h4><i class="fas fa-dollar-sign"></i> Total Value</h4>
                        <p style="font-size: 1.5rem; font-weight: bold; color: #2E0D2A;">ZMW ${totalValue.toFixed(2)}</p>
                    </div>
                </div>
            `;

            
            if (selectedParcelCount > 0) {
                html += `
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-top: 20px;">
                        <h4 style="color: #2E0D2A; margin-bottom: 15px;">
                            <i class="fas fa-list"></i> Parcel Manifest
                        </h4>
                        <div style="max-height: 300px; overflow-y: auto;">
                `;
                
                tripData.selectedParcels.forEach((parcelId, index) => {
                    const parcel = availableParcels.find(p => p.id === parcelId);
                    if (parcel) {
                        html += `
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px; background: white; margin-bottom: 8px; border-radius: 6px; border-left: 4px solid #4A1C40;">
                                <div>
                                    <strong>${parcel.track_number}</strong>
                                    <small style="display: block; color: #666;">${parcel.receiver_name} → ${parcel.destination_outlet?.outlet_name || 'N/A'}</small>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-weight: 500;">${parcel.weight_display}</div>
                                    <small style="color: #666;">ZMW ${parseFloat(parcel.declared_value || 0).toFixed(2)}</small>
                                </div>
                            </div>
                        `;
                    }
                });
                
                html += `
                        </div>
                    </div>
                `;
            }

            summaryContainer.innerHTML = html;
            
            
            updateSubmitButtonState();
        }

        async function submitTrip() {
            if (!confirm('Create this trip? This action cannot be undone.')) {
                return;
            }
            
        
            if (!validateTripData()) {
                return;
            }
            
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.innerHTML;
            
            try {
                
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Trip...';
                
                showMessage('Creating trip...', 'info');
                
                
                const departureDateTime = document.getElementById('departureTime').value;
                const tripDate = departureDateTime ? departureDateTime.split('T')[0] : '';
                const tripPayload = {
                    vehicle_id: document.getElementById('vehicleId').value,
                    driver_id: document.getElementById('driverId').value,
                    origin_outlet: document.getElementById('originOutlet').value,
                    destination_outlet: document.getElementById('destinationOutlet').value,
                    departure_time: departureDateTime,
                    trip_date: tripDate,
                    route_stops: tripData.stops.map(stop => ({
                        id: stop.id,
                        name: stop.name,
                        order: stop.order
                    })),
                    selected_parcels: tripData.selectedParcels.map(id => String(id)) 
                };
                
                console.log('Submitting trip with payload:', tripPayload);
                console.log('Selected parcels details:', tripData.selectedParcels.map(id => {
                    const parcel = availableParcels.find(p => p.id === id);
                    return parcel ? { id: parcel.id, track_number: parcel.track_number, type: typeof parcel.id } : { id: id, found: false, type: typeof id };
                }));
                
                const response = await fetch('../api/trips/create_trip.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(tripPayload)
                });
                
                console.log('Trip creation response status:', response.status);
                
                if (response.ok) {
                    const result = await response.json();
                    console.log('Trip creation result:', result);
                    console.log('Debug info from API:', result.debug_info);
                    
                    if (result.success) {
                        showMessage(`Trip created successfully! Trip ID: ${result.trip_id}`, 'success');
                        console.log(`Parcels assigned: ${result.parcels_assigned} out of ${result.selected_parcels_count} selected`);
                        displayTripCreationSuccess(result);
                        
                        
                    } else {
                        throw new Error(result.error || 'Failed to create trip');
                    }
                } else {
                    const errorResult = await response.json().catch(() => ({}));
                    throw new Error(errorResult.error || `Server error: ${response.status}`);
                }
                
            } catch (error) {
                console.error('Error creating trip:', error);
                showMessage('Error creating trip: ' + error.message, 'error');
            } finally {
                
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        }
        
        function validateTripData(showMessages = true) {
            const vehicleId = document.getElementById('vehicleId').value;
            const originOutlet = document.getElementById('originOutlet').value;
            const destOutlet = document.getElementById('destinationOutlet').value;
            const departureTime = document.getElementById('departureTime').value;
            
            if (!vehicleId) {
                if (showMessages) {
                    showMessage('Please select a vehicle for the trip', 'error');
                    goToStep(1);
                }
                return false;
            }
            
            if (!originOutlet || !destOutlet) {
                if (showMessages) {
                    showMessage('Please select both origin and destination outlets', 'error');
                    goToStep(2);
                }
                return false;
            }
            
            if (!departureTime) {
                if (showMessages) {
                    showMessage('Please set a departure time for the trip', 'error');
                    goToStep(1);
                }
                return false;
            }
            
            
            const now = new Date();
            const selectedTime = new Date(departureTime);
            if (selectedTime <= now) {
                if (showMessages) {
                    showMessage('Departure time must be in the future', 'error');
                    goToStep(1);
                }
                return false;
            }
            
            return true;
        }
        
        function displayTripCreationSuccess(result) {
            const summaryContainer = document.getElementById('tripSummary');
            if (!summaryContainer) return;
            
            const successHtml = `
                <div style="background: linear-gradient(135deg, #d4edda, #c3e6cb); padding: 30px; border-radius: 15px; text-align: center; border: 3px solid #28a745;">
                    <div style="font-size: 3rem; color: #28a745; margin-bottom: 20px;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h2 style="color: #155724; margin-bottom: 20px;">Trip Created Successfully!</h2>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 30px 0;">
                        <div style="background: white; padding: 20px; border-radius: 10px; border: 2px solid #28a745;">
                            <h4 style="color: #28a745; margin-bottom: 10px;"><i class="fas fa-route"></i> Trip ID</h4>
                            <p style="font-size: 1.2rem; font-weight: bold; color: #155724;">${result.trip_id}</p>
                        </div>
                        <div style="background: white; padding: 20px; border-radius: 10px; border: 2px solid #28a745;">
                            <h4 style="color: #28a745; margin-bottom: 10px;"><i class="fas fa-map-marker-alt"></i> Stops Created</h4>
                            <p style="font-size: 1.2rem; font-weight: bold; color: #155724;">${result.trip_stops_created || 0}</p>
                        </div>
                        <div style="background: white; padding: 20px; border-radius: 10px; border: 2px solid #28a745;">
                            <h4 style="color: #28a745; margin-bottom: 10px;"><i class="fas fa-box"></i> Parcels Assigned</h4>
                            <p style="font-size: 1.2rem; font-weight: bold; color: #155724;">${result.parcels_assigned || 0}</p>
                        </div>
                    </div>
                    
                    <div style="margin-top: 25px;">
                        <p style="font-size: 1.1rem; color: #155724; margin-bottom: 15px;">
                            <strong>Status:</strong> Scheduled and ready for dispatch
                        </p>
                        <p style="color: #666; font-style: italic;">
                            You can now manage this trip from the dashboard or assign a driver.
                        </p>
                    </div>
                    
                    <div style="margin-top: 30px;">
                        <button onclick="window.location.href='outlet_dashboard.php'" class="btn btn-primary" style="margin-right: 15px;">
                            <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                        </button>
                        <button onclick="window.location.reload()" class="btn btn-secondary">
                            <i class="fas fa-plus"></i> Create Another Trip
                        </button>
                    </div>
                </div>
            `;
            
            summaryContainer.innerHTML = successHtml;
        }
        
        function updateSubmitButtonState() {
            const submitBtn = document.getElementById('submitBtn');
            if (!submitBtn) return;
            
            const isFormComplete = validateTripData(false); 
            const hasMinimumData = 
                document.getElementById('vehicleId')?.value &&
                document.getElementById('originOutlet')?.value &&
                document.getElementById('destinationOutlet')?.value &&
                document.getElementById('departureTime')?.value;
            
            if (isFormComplete) {
                submitBtn.disabled = false;
                submitBtn.classList.remove('btn-secondary');
                submitBtn.classList.add('btn-success');
                submitBtn.innerHTML = '<i class="fas fa-check"></i> Create Trip';
                submitBtn.title = 'All requirements met - ready to create trip';
            } else if (hasMinimumData) {
                submitBtn.disabled = false;
                submitBtn.classList.remove('btn-success');
                submitBtn.classList.add('btn-warning');
                submitBtn.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Create Trip (Validation Required)';
                submitBtn.title = 'Some validation issues - click to see details';
            } else {
                submitBtn.disabled = true;
                submitBtn.classList.remove('btn-success', 'btn-warning');
                submitBtn.classList.add('btn-secondary');
                submitBtn.innerHTML = '<i class="fas fa-times"></i> Incomplete Form';
                submitBtn.title = 'Please complete all required fields';
            }
        }

        
        function getRouteOutletIds() {
            const routeOutlets = [];
            
            
            const originSelect = document.getElementById('originOutlet');
            if (originSelect && originSelect.value) {
                routeOutlets.push(originSelect.value);
            }
            
            
            if (tripData.stops && tripData.stops.length > 0) {
                tripData.stops.forEach(stop => {
                    if (stop.id && !routeOutlets.includes(stop.id)) {
                        routeOutlets.push(stop.id);
                    }
                });
            }
            
            
            const destSelect = document.getElementById('destinationOutlet');
            if (destSelect && destSelect.value) {
                if (!routeOutlets.includes(destSelect.value)) {
                    routeOutlets.push(destSelect.value);
                }
            }
            
            console.log('Route outlets for filtering:', routeOutlets);
            return routeOutlets;
        }

        
        async function loadAvailableParcels() {
            const container = document.getElementById('parcelsContainer');
            const loadBtn = document.getElementById('loadParcelsBtn');
            const statsDiv = document.getElementById('parcelStats');
            
            if (!container || !loadBtn) return;
            
            loadBtn.disabled = true;
            loadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
            
            container.innerHTML = '<div class="loading-spinner"></div><p style="text-align: center; color: #666;">Fetching available parcels...</p>';
            
            try {
                
                const routeOutlets = getRouteOutletIds();
                
                if (routeOutlets.length === 0) {
                    container.innerHTML = '<div style="text-align: center; padding: 20px; color: #6c757d; background: #f8f9fa; border-radius: 8px; border: 2px dashed #dee2e6;"><i class="fas fa-info-circle" style="font-size: 24px; margin-bottom: 10px;"></i><p>Please select origin and destination outlets first to load matching parcels.</p></div>';
                    loadBtn.disabled = false;
                    loadBtn.innerHTML = '<i class="fas fa-refresh"></i> Load Parcels';
                    return;
                }
                
                
                const response = await fetch('../api/trips/fetch_available_parcels.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        outlet_filter: routeOutlets,
                        status_filter: ['pending', 'ready_for_dispatch']
                    })
                });
                
                console.log('Response status:', response.status);
                
                if (response.ok) {
                    const responseText = await response.text();
                    console.log('Raw response:', responseText.substring(0, 500) + (responseText.length > 500 ? '...' : ''));
                    
                    let responseData;
                    try {
                        responseData = JSON.parse(responseText);
                    } catch (jsonError) {
                        console.error('JSON Parse Error:', jsonError);
                        console.error('Response that failed to parse:', responseText);
                        throw new Error('Invalid JSON response from server. The server may have returned HTML or PHP errors.');
                    }
                    
                    console.log('Filtered parcels response:', responseData);
                    
                    
                    if (Array.isArray(responseData)) {
                        
                        availableParcels = responseData;
                    } else if (responseData.success && Array.isArray(responseData.parcels)) {
                        
                        availableParcels = responseData.parcels;
                    } else if (responseData.success && Array.isArray(responseData.data)) {
                        
                        availableParcels = responseData.data;
                    } else if (responseData.error) {
                        
                        throw new Error(responseData.error);
                    } else {
                        
                        console.error('Unexpected response format:', responseData);
                        throw new Error('Unexpected response format');
                    }
                    
                    displayParcels(availableParcels);
                    updateParcelStats(availableParcels);
                    
                    const routeOutletNames = routeOutlets.map(outletId => {
                        const originSelect = document.getElementById('originOutlet');
                        const destSelect = document.getElementById('destinationOutlet');
                        const intermediateSelect = document.getElementById('intermediateOutlet');
                        
                        
                        for (let select of [originSelect, destSelect, intermediateSelect]) {
                            if (select) {
                                for (let option of select.options) {
                                    if (option.value === outletId) {
                                        return option.text;
                                    }
                                }
                            }
                        }
                        return outletId;
                    });
                    
                    showMessage(`${availableParcels.length} parcels loaded for route: ${routeOutletNames.join(' → ')}`, 'success');
                } else {
                   
                    const errorText = await response.text();
                    console.error('API Error Response:', errorText);
                    
                    let errorData;
                    try {
                        errorData = JSON.parse(errorText);
                    } catch (parseError) {
                        console.error('Error response is not valid JSON:', parseError);
                        throw new Error(`Server returned non-JSON error response (HTTP ${response.status}). Check server logs.`);
                    }
                    
                    if (response.status === 401) {
                        throw new Error('Session expired. Please refresh the page and login again.');
                    } else if (errorData.error) {
                        throw new Error(errorData.error);
                    } else {
                        throw new Error(`Server error: ${response.status}`);
                    }
                }
            } catch (error) {
                console.error('Error loading parcels:', error);
                container.innerHTML = '<div style="text-align: center; padding: 20px; color: #dc3545; background: #f8d7da; border-radius: 8px; border: 2px solid #f5c6cb;"><i class="fas fa-exclamation-triangle" style="font-size: 24px; margin-bottom: 10px;"></i><p>Error loading parcels. Please try again.</p></div>';
                showMessage('Error loading parcels: ' + error.message, 'error');
            }
            
            loadBtn.disabled = false;
            loadBtn.innerHTML = '<i class="fas fa-refresh"></i> Refresh Parcels';
        }

        function updateParcelStats(parcels) {
            const statsDiv = document.getElementById('parcelStats');
            if (statsDiv && Array.isArray(parcels)) {
                const totalWeight = parcels.reduce((sum, p) => sum + (parseFloat(p.parcel_weight) || 0), 0);
                const totalValue = parcels.reduce((sum, p) => sum + (parseFloat(p.declared_value) || 0), 0);
                statsDiv.innerHTML = `
                    <div style="display: flex; justify-content: space-around; padding: 15px; background: #e7f3ff; border-radius: 8px; margin-bottom: 20px;">
                        <div style="text-align: center;">
                            <strong style="color: #2E0D2A; font-size: 18px;">${parcels.length}</strong>
                            <br><small style="color: #666;">Available Parcels</small>
                        </div>
                        <div style="text-align: center;">
                            <strong style="color: #2E0D2A; font-size: 18px;">${totalWeight.toFixed(2)} kg</strong>
                            <br><small style="color: #666;">Total Weight</small>
                        </div>
                        <div style="text-align: center;">
                            <strong style="color: #2E0D2A; font-size: 18px;">ZMW ${totalValue.toFixed(2)}</strong>
                            <br><small style="color: #666;">Total Value</small>
                        </div>
                    </div>
                `;
            }
        }

        function displayParcels(parcels) {
            const container = document.getElementById('parcelsContainer');
            if (!container) return;
            
            
            if (!Array.isArray(parcels)) {
                console.error('Expected parcels array, got:', parcels);
                container.innerHTML = '<p style="text-align: center; color: #dc3545;">Error: Invalid parcels data format.</p>';
                return;
            }
            
            if (parcels.length === 0) {
                container.innerHTML = `
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <i class="fas fa-inbox fa-2x" style="margin-bottom: 10px; opacity: 0.5;"></i>
                        <p>No available parcels found for assignment</p>
                        <small>Parcels must be unassigned and ready for dispatch</small>
                    </div>
                `;
                return;
            }
            
            let html = '';
            parcels.forEach(parcel => {
                const isSelected = tripData.selectedParcels.includes(parcel.id);
                html += `
                    <div class="parcel-item ${isSelected ? 'selected' : ''}" onclick="toggleParcelSelection('${parcel.id}')">
                        <div class="parcel-header">
                            <div class="parcel-track"># ${parcel.track_number}</div>
                            <div class="parcel-status ${parcel.status}">${parcel.status.replace('_', ' ')}</div>
                        </div>
                        
                        <div class="parcel-details">
                            <div class="parcel-detail">
                                <div class="parcel-detail-label">Sender</div>
                                <div class="parcel-detail-value">${parcel.sender_name || 'N/A'}</div>
                            </div>
                            <div class="parcel-detail">
                                <div class="parcel-detail-label">Receiver</div>
                                <div class="parcel-detail-value">${parcel.receiver_name || 'N/A'}</div>
                            </div>
                            <div class="parcel-detail">
                                <div class="parcel-detail-label">Weight</div>
                                <div class="parcel-detail-value">${parcel.weight_display}</div>
                            </div>
                            <div class="parcel-detail">
                                <div class="parcel-detail-label">Origin</div>
                                <div class="parcel-detail-value">${parcel.origin_outlet_name || 'N/A'}</div>
                            </div>
                            <div class="parcel-detail">
                                <div class="parcel-detail-label">Destination</div>
                                <div class="parcel-detail-value">${parcel.destination_outlet_name || 'N/A'}</div>
                            </div>
                            <div class="parcel-detail">
                                <div class="parcel-detail-label">Value</div>
                                <div class="parcel-detail-value">${parcel.value_display || 'ZMW 0.00'}</div>
                            </div>
                            <div class="parcel-detail">
                                <div class="parcel-detail-label">Delivery Fee</div>
                                <div class="parcel-detail-value">${parcel.fee_display}</div>
                            </div>
                        </div>
                        
                        <div class="parcel-actions">
                            <small style="color: #666;">
                                Created: ${new Date(parcel.created_at).toLocaleDateString()}
                            </small>
                            <button class="parcel-select-btn ${isSelected ? 'deselect' : 'select'}" 
                                    onclick="event.stopPropagation(); toggleParcelSelection('${parcel.id}')">
                                ${isSelected ? 'Remove' : 'Select'}
                            </button>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }

        function toggleParcelSelection(parcelId) {
            console.log('Toggling parcel selection for ID:', parcelId, 'Type:', typeof parcelId);
            console.log('Current selectedParcels:', tripData.selectedParcels);
            
            const index = tripData.selectedParcels.indexOf(parcelId);
            
            if (index === -1) {
                
                tripData.selectedParcels.push(parcelId);
                console.log('Added parcel to selection. New array:', tripData.selectedParcels);
                showMessage('Parcel added to selection', 'success');
            } else {
                
                tripData.selectedParcels.splice(index, 1);
                console.log('Removed parcel from selection. New array:', tripData.selectedParcels);
                showMessage('Parcel removed from selection', 'info');
            }
            
            
            displayParcels(availableParcels);
            updateSelectedParcelsDisplay();
        }

        function updateSelectedParcelsDisplay() {
            const section = document.getElementById('selectedParcelsSection');
            const countSpan = document.getElementById('selectedCount');
            const listDiv = document.getElementById('selectedParcelsList');
            
            if (!section || !countSpan || !listDiv) return;
            
            if (tripData.selectedParcels.length === 0) {
                section.style.display = 'none';
                return;
            }
            
            section.style.display = 'block';
            countSpan.textContent = tripData.selectedParcels.length;
            
            let html = '';
            tripData.selectedParcels.forEach(parcelId => {
                const parcel = availableParcels.find(p => p.id === parcelId);
                if (parcel) {
                    html += `
                        <div class="selected-parcel-item">
                            <div>
                                <strong>${parcel.track_number}</strong><br>
                                <small>${parcel.receiver_name} | ${parcel.weight_display}</small>
                            </div>
                            <button class="btn btn-danger" onclick="toggleParcelSelection('${parcelId}')" style="padding: 5px 10px;">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    `;
                }
            });
            
            listDiv.innerHTML = html;
        }

        
        function showMessage(message, type = 'info') {
            
            document.querySelectorAll('.notification').forEach(n => n.remove());

            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.innerHTML = `
                <span>${message}</span>
                <button onclick="this.parentElement.remove()" style="background: none; border: none; color: inherit; font-size: 18px; cursor: pointer; margin-left: 10px;">×</button>
            `;

            document.body.appendChild(notification);

            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 4000);
        }

        console.log('Trip creation wizard loaded successfully');
    </script>
    <script src="../assets/js/sidebar-toggle.js"></script>
    
    <?php include __DIR__ . '/../includes/pwa_install_button.php'; ?>
    <script src="../js/pwa-install.js"></script>
</body>
</html>
