<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();

// Debug the session state
if (!isset($_SESSION['user_id'])) {
    // If no user is logged in, redirect to login once
    if (!isset($_SESSION['login_redirect'])) {
        $_SESSION['login_redirect'] = true;
        header('Location: ../auth/login.php');
        exit();
    } else {
        // Clear the redirect flag and show error
        unset($_SESSION['login_redirect']);
        die('Session error: Not logged in. Please <a href="../auth/login.php">login</a> and try again.');
    }
}

// Include session check without redirect loop
require_once __DIR__ . '/../../auth/session-check.php';
include '../includes/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trip Creation Test</title>

    <!-- Poppins Font -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Main CSS -->
    <link rel="stylesheet" href="../assets/css/company.css">

     <style>
       body {
            font-family: 'Poppins', sans-serif;
            background: white;
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }

        .trip-wizard {
            max-width: 1200px;
            margin: 20px auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .wizard-header {
            background: white;
            color: #2E0D2A;
            padding: 30px;
            text-align: center;
            border-bottom: 1px solid #dee2e6;
        }

        .wizard-header h1 {
            margin: 0 0 10px 0;
            font-size: 2rem;
            font-weight: 700;
            color: #2E0D2A;
        }

        .wizard-header p {
            margin: 0;
            opacity: 0.7;
            font-size: 1.1rem;
            color: #666;
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

            .trip-wizard {
                margin: 10px;
            }

            .step-content {
                padding: 20px;
            }
        }
    </style>
</head>    

<body>
    <div class="mobile-dashboard">
    
        <main class="main-content">
            <div class="content-container">
                <div class="trip-wizard">
                    <!-- Wizard Header -->
                    <div class="wizard-header">
                        <h1><i class="fas fa-route"></i> Trip Creation</h1>
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

    </div>

    <script>
  // Global variables
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

        // Set these when user selects origin/destination
        let routeOutlets = {
            origin: null,
            destination: null
        };

        let availableParcels = []; 
        
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Trip wizard initialized');
            // Run initial data load and keep the promise so we can wait for selects/options to be ready
            const initPromise = loadInitialData();

            // If opened with ?edit=ID, fetch trip data and populate the form after initial data finishes loading
            const params = new URLSearchParams(window.location.search);
            const editId = params.get('edit');
            if (editId) {
                console.log('Edit mode detected for trip id:', editId);
                initPromise.then(() => {
                    return fetch(`../api/get_trip.php?id=${encodeURIComponent(editId)}`, { credentials: 'include' });
                }).then(r => r.json())
                .then(data => {
                    if (data && data.success && data.trip) {
                        populateTripForEdit(data.trip);
                    } else {
                        console.error('Failed to load trip for edit', data);
                    }
                }).catch(err => console.error('Error fetching trip for edit:', err));
            }

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

        // Populate the form and internal tripData from a fetched trip object
        function populateTripForEdit(trip) {
            try {
                console.log('Populating trip data for edit:', trip);
                // Normalize parcels from the fetched trip object
                // Server may return parcels in several shapes: trip.parcels, trip.parcel_list, or nested parcel objects.
                availableParcels = [];
                if (Array.isArray(trip.parcels) && trip.parcels.length) {
                    // trip.parcels may be an array of parcel objects or parcel_list rows with nested parcel
                    availableParcels = trip.parcels.map(p => (p.parcel ? p.parcel : p));
                } else if (Array.isArray(trip.parcel_list) && trip.parcel_list.length) {
                    availableParcels = trip.parcel_list.map(pl => (pl.parcel ? pl.parcel : pl));
                } else if (Array.isArray(trip.available_parcels) && trip.available_parcels.length) {
                    availableParcels = trip.available_parcels;
                } else {
                    // No parcels embedded in trip response; leave availableParcels empty for now.
                    availableParcels = [];
                }

                // Also populate selected parcels if the trip contains assigned parcel ids
                tripData.selectedParcels = [];
                if (Array.isArray(trip.parcels) && trip.parcels.length) {
                    // If parcel objects include id fields, use them
                    tripData.selectedParcels = trip.parcels.map(p => (p.parcel ? (p.parcel.id || p.parcel.parcel_id || p.parcel_id) : (p.id || p.parcel_id))).filter(Boolean);
                } else if (Array.isArray(trip.parcel_list) && trip.parcel_list.length) {
                    tripData.selectedParcels = trip.parcel_list.map(pl => (pl.parcel ? (pl.parcel.id || pl.parcel.parcel_id || pl.parcel_id) : (pl.parcel_id || pl.id))).filter(Boolean);
                } else if (Array.isArray(trip.selected_parcels) && trip.selected_parcels.length) {
                    tripData.selectedParcels = trip.selected_parcels.map(s => s.parcel_id || s.id).filter(Boolean);
                }

                // The edit flow doesn't provide a single `parcelId` variable to this function.
                // Some older logic checks `parcelId` — declare it locally as null to avoid
                // a ReferenceError while preserving the existing conditional branches.
                let parcelId = null;

                // === Client-side route filtering ===
                // Keep only parcels that are currently at the origin outlet. Among those,
                // prioritize parcels whose destination matches the trip destination or any intermediate stop.
                if (!parcelId) {
                    // routeOutlets in this script is an object { origin, destination }
                    // Use helper to get a stable array of outlet ids for the route.
                    const routeOutletIds = getRouteOutletIds();
                    const originId = routeOutletIds.length ? routeOutletIds[0] : null;
                    const routeSet = new Set(routeOutletIds.filter(Boolean));

                    // helper to robustly read parcel's current outlet id
                    const getCurrentOutlet = (p) => {
                        return p.current_outlet_id || (p.current_outlet && (p.current_outlet.id || p.current_outlet)) || p.origin_outlet_id || p.origin_outlet || p.current_location || null;
                    };

                    // helper to robustly read parcel's destination outlet id
                    const getDestinationOutlet = (p) => {
                        return p.destination_outlet_id || (p.destination_outlet && (p.destination_outlet.id || p.destination_outlet)) || p.dest_outlet_id || p.dest_outlet || p.destination || null;
                    };

                    // Filter parcels that are physically at the origin outlet
                    let atOrigin = availableParcels.filter(p => {
                        const cur = getCurrentOutlet(p);
                        return cur && String(cur) === String(originId);
                    });

                    // If none found at origin, broaden to parcels whose current outlet is any of route outlets
                    if (atOrigin.length === 0) {
                        atOrigin = availableParcels.filter(p => {
                            const cur = getCurrentOutlet(p);
                            return cur && routeSet.has(String(cur));
                        });
                    }

                    // Among atOrigin, prefer those whose destination is within the route (stops/destination)
                    const matchesDest = [];
                    const others = [];
                    atOrigin.forEach(p => {
                        const dest = getDestinationOutlet(p);
                        if (dest && routeSet.has(String(dest))) matchesDest.push(p);
                        else others.push(p);
                    });

                    // Final ordered list: matching-destination first, then other at-origin parcels
                    const filteredParcels = matchesDest.concat(others);

                    // Replace availableParcels with filtered result
                    availableParcels = filteredParcels;
                }

                // ✅ Render filtered results
                displayParcels(availableParcels);
                updateParcelStats(availableParcels);

                if (!parcelId) {
                    // Only show route message if filtering by outlets
                    const routeOutletNames = [];
                    // Use the array of route ids to map to option text
                    const routeOutletIds = getRouteOutletIds();
                    const selects = [
                        document.getElementById('originOutlet'),
                        document.getElementById('destinationOutlet'),
                        document.getElementById('intermediateOutlet')
                    ];

                    routeOutletIds.forEach(outletId => {
                        if (!outletId) return;
                        let outletName = outletId; // fallback to raw ID
                        for (let select of selects) {
                            if (select) {
                                for (let option of select.options) {
                                    if (String(option.value) === String(outletId)) {
                                        outletName = option.text;
                                        break;
                                    }
                                }
                            }
                        }
                        routeOutletNames.push(outletName);
                    });

                    showMessage(`${availableParcels.length} parcel(s) loaded for route: ${routeOutletNames.join(' → ')}`, 'success');
                } else {
                    showMessage(`${availableParcels.length} parcel(s) loaded by ID lookup`, 'success');
                }

                // If the trip includes an assigned driver, ensure the option exists and select it.
                const driverId = (trip.driver && (Array.isArray(trip.driver) ? (trip.driver[0] && trip.driver[0].id) : trip.driver.id)) || trip.driver_id || trip.driverId || null;
                if (driverId) {
                    const driverName = (trip.driver && Array.isArray(trip.driver) && trip.driver[0])
                        ? (trip.driver[0].driver_name || trip.driver[0].name || `Driver ${driverId}`)
                        : (trip.driver && (trip.driver.driver_name || trip.driver.name)) || `Driver ${driverId}`;
                    ensureOptionExists('driverId', driverId, driverName);
                    setSelectValueWithRetry('driverId', driverId);
                }

                // Populate vehicle, origin, destination and stops into the form and tripData
                // Vehicle
                const vehicleId = trip.vehicle_id || (trip.vehicle && (Array.isArray(trip.vehicle) ? (trip.vehicle[0] && (trip.vehicle[0].id || trip.vehicle[0].vehicle_id)) : (trip.vehicle.id || trip.vehicle.vehicle_id))) || trip.vehicleId || null;
                if (vehicleId) {
                    ensureOptionExists('vehicleId', vehicleId, `Vehicle ${vehicleId}`);
                    setSelectValueWithRetry('vehicleId', vehicleId);
                    tripData.vehicle = vehicleId;
                }

                // Origin & Destination
                const originId = trip.origin_outlet || trip.origin || trip.origin_outlet_id || (trip.originOutlet && (trip.originOutlet.id || trip.originOutlet)) || null;
                const destinationId = trip.destination_outlet || trip.destination || trip.destination_outlet_id || (trip.destinationOutlet && (trip.destinationOutlet.id || trip.destinationOutlet)) || null;
                if (originId) {
                    ensureOptionExists('originOutlet', originId, `Outlet ${originId}`);
                    setSelectValueWithRetry('originOutlet', originId);
                    tripData.originOutlet = originId;
                }
                if (destinationId) {
                    ensureOptionExists('destinationOutlet', destinationId, `Outlet ${destinationId}`);
                    setSelectValueWithRetry('destinationOutlet', destinationId);
                    tripData.destinationOutlet = destinationId;
                }

                // Stops (intermediate)
                tripData.stops = [];
                if (Array.isArray(trip.stops) && trip.stops.length) {
                    tripData.stops = trip.stops.map(s => ({ id: s.id || s.outlet_id || s.outletId || s, name: s.name || s.outlet_name || s.label || (s.id || s.outlet_id || s) }));
                } else if (Array.isArray(trip.intermediate_stops) && trip.intermediate_stops.length) {
                    tripData.stops = trip.intermediate_stops.map(s => ({ id: s.id || s.outlet_id || s, name: s.name || s.outlet_name || (s.id || s.outlet_id || s) }));
                }

                // Departure time
                tripData.departureTime = trip.departure_time || trip.departureTime || trip.scheduled_departure || null;
                const depInput = document.getElementById('departureTime');
                if (depInput && tripData.departureTime) depInput.value = toDatetimeLocal(tripData.departureTime);

                const depEl = document.getElementById('departureTime');
                if (depEl && tripData.departureTime) depEl.value = toDatetimeLocal(tripData.departureTime);

                // Re-render stops and parcel lists
                updateStopsDisplay();
                // Render selected parcels (may render minimal info if availableParcels not loaded yet)
                displaySelectedParcels();

                // Update submit button label to 'Update'
                const submitBtn = document.getElementById('submitBtn');
                if (submitBtn) {
                    submitBtn.style.display = 'inline-block';
                    submitBtn.innerHTML = '<i class="fas fa-check"></i> Update Trip';
                }

            } catch (e) {
                console.error('Error populating trip for edit:', e);
            }
        }

        // Ensure an option exists in a select; append a fallback option if it's missing.
        function ensureOptionExists(selectId, value, textFallback) {
            const sel = document.getElementById(selectId);
            if (!sel || value == null) return;
            const valStr = String(value);
            const exists = Array.from(sel.options).some(o => String(o.value) === valStr);
            if (!exists) {
                const opt = document.createElement('option');
                opt.value = valStr;
                opt.text = textFallback || `Assigned: ${valStr}`;
                // Mark it so we can style or remove later if desired
                opt.dataset.addedForEdit = '1';
                // It's safer to append at the end so user can still change selection
                sel.appendChild(opt);
                console.log(`ensureOptionExists: appended fallback option for ${selectId} => ${valStr}`);
            }
        }

        // Try to set a select's value; if the option isn't present yet retry a few times.
        function setSelectValueWithRetry(selectId, value, attempts = 6, delay = 150) {
            const trySet = (remaining) => {
                const sel = document.getElementById(selectId);
                if (!sel) return;
                const valStr = String(value);
                const hasOption = Array.from(sel.options).some(o => String(o.value) === valStr);
                if (hasOption) {
                    sel.value = valStr;
                    // trigger change handlers if any
                    sel.dispatchEvent(new Event('change', { bubbles: true }));
                } else if (remaining > 0) {
                    setTimeout(() => trySet(remaining - 1), delay);
                } else {
                    console.warn(`setSelectValueWithRetry: option ${value} not found for ${selectId}`);
                }
            };
            trySet(attempts);
        }

        // Convert an ISO datetime (possibly with timezone) to the format accepted by <input type="datetime-local">
        function toDatetimeLocal(isoString) {
            if (!isoString) return '';
            // Create a Date from the string (handles timezone offsets)
            const d = new Date(isoString);
            if (isNaN(d.getTime())) return '';
            const pad = (n) => String(n).padStart(2, '0');
            const yyyy = d.getFullYear();
            const mm = pad(d.getMonth() + 1);
            const dd = pad(d.getDate());
            const hh = pad(d.getHours());
            const min = pad(d.getMinutes());
            const sec = pad(d.getSeconds());
            // Return with seconds (datetime-local accepts optional seconds)
            return `${yyyy}-${mm}-${dd}T${hh}:${min}:${sec}`;
        }

        // Render the selected parcels area from tripData.selectedParcels
        function displaySelectedParcels() {
            const listEl = document.getElementById('selectedParcelsList');
            const countEl = document.getElementById('selectedCount');
            const section = document.getElementById('selectedParcelsSection');
            if (!listEl || !countEl || !section) return;
            listEl.innerHTML = '';
            const selected = Array.isArray(tripData.selectedParcels) ? tripData.selectedParcels : [];
            countEl.textContent = selected.length;
            if (selected.length === 0) {
                section.style.display = 'none';
                return;
            }
            section.style.display = 'block';
            selected.forEach(pid => {
                const parcel = availableParcels.find(p => p.id === pid) || null;
                const track = parcel ? (parcel.track_number || parcel.id) : pid;
                const receiver = parcel ? (parcel.receiver_name || '') : '';
                const html = `
                    <div style="display:flex; justify-content:space-between; align-items:center; padding:8px; background:#fff; border-radius:6px; margin-bottom:6px; border:1px solid #eef2f7;">
                        <div><strong>${track}</strong><div style="font-size:12px;color:#666">${receiver}</div></div>
                        <div style="font-size:12px;color:#666">${parcel ? (parcel.weight_display || '') : ''}</div>
                    </div>
                `;
                listEl.insertAdjacentHTML('beforeend', html);
            });
        }

        // Loading vehicles and outlets
async function loadInitialData() {
    try {
        showMessage('Loading data...', 'info');
        
        // Loading vehicles
        const vehicleResponse = await fetch('../api/fetch_vehicles.php', {
            credentials: 'same-origin'
        });
        if (vehicleResponse.ok) {
            const vehicleData = await vehicleResponse.json();
            console.log('Vehicle data received:', vehicleData);
            
            // Handle different possible response formats
            let vehiclesToUse = null;
            
            if (Array.isArray(vehicleData)) {
                vehiclesToUse = vehicleData;
            } else if (vehicleData.success && Array.isArray(vehicleData.vehicles)) {
                vehiclesToUse = vehicleData.vehicles;
            } else if (vehicleData.success && Array.isArray(vehicleData.data)) {
                vehiclesToUse = vehicleData.data;
            }
            
            if (vehiclesToUse) {
                console.log('Processing vehicles:', vehiclesToUse);
                populateVehicleOptions(vehiclesToUse);
            } else if (vehicleData.error) {
                console.error('Failed to load vehicles:', vehicleData.error);
                showMessage('Failed to load vehicles: ' + vehicleData.error, 'error');
            } else {
                console.error('Unexpected vehicle response format:', vehicleData);
                showMessage('Failed to load vehicles: Unexpected response format', 'error');
            }
        } else {
            const errorData = await vehicleResponse.json().catch(() => ({}));
            console.error('Vehicle API error:', vehicleResponse.status, errorData);
            showMessage(`Failed to load vehicles: HTTP ${vehicleResponse.status}`, 'error');
        }

        // Loading outlets
        const outletResponse = await fetch('../api/fetch_company_outlets.php', {
            credentials: 'same-origin'
        });
        if (outletResponse.ok) {
            const outletData = await outletResponse.json();
            console.log('Outlet data received:', outletData);
            if (outletData.success && outletData.data) {
                populateOutletOptions(outletData.data);
            } else if (outletData.error) {
                console.error('Failed to load outlets:', outletData.error);
                showMessage('Failed to load outlets: ' + outletData.error, 'error');
            } else {
                console.error('Unexpected outlet response format:', outletData);
                showMessage('Failed to load outlets: Unexpected response format', 'error');
            }
        } else {
            const errorData = await outletResponse.json().catch(() => ({}));
            console.error('Outlet API error:', outletResponse.status, errorData);
            showMessage(`Failed to load outlets: HTTP ${outletResponse.status}`, 'error');
        }

        // Loading drivers
        const driverResponse = await fetch('../api/fetch_drivers.php', {
            credentials: 'same-origin'
        });
        if (driverResponse.ok) {
            const driverData = await driverResponse.json();
            if (driverData.success && driverData.data) {
                populateDriverOptions(driverData.data);
            } else if (driverData.error) {
                console.error('Failed to load drivers:', driverData.error);
                showMessage('Failed to load drivers: ' + driverData.error, 'error');
            } else if (Array.isArray(driverData)) {
                // Fallback for direct array response
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

        // Set current outlet as origin
        const currentOutletId = document.getElementById('currentOutletId').value;
        if (currentOutletId) {
            const originSelect = document.getElementById('originOutlet');
            if (originSelect) {
                originSelect.value = currentOutletId;
                handleOriginChange();
            }
        }

        // Initialize datetime input after loading data
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
                
                
                const defaultDateTime = new Date(now.getTime() + (60 * 60000)); // 1 hour from now
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
            if (!select) {
                console.error('Vehicle select element not found');
                return;
            }
            
            select.innerHTML = '<option value="">Select a vehicle</option>';
            console.log('Populating vehicles:', vehicles);
            
            if (Array.isArray(vehicles)) {
                vehicles.forEach(vehicle => {
                    if (vehicle && vehicle.id) {
                        const option = document.createElement('option');
                        option.value = vehicle.id;
                        
                        // Build vehicle display info from all possible property names
                        const vehicleInfo = [
                            vehicle.vehicle_name || vehicle.name,
                            vehicle.vehicle_number || vehicle.registration_number || vehicle.reg_number,
                            vehicle.vehicle_type || vehicle.type
                        ].filter(Boolean).join(' - ');
                        
                        if (vehicleInfo.trim()) {
                            option.textContent = vehicleInfo;
                        } else {
                            // Fallback if no name properties are found
                            option.textContent = `Vehicle ${vehicle.id}`;
                        }
                        
                        // Check availability using multiple possible property names
                        const isAvailable = 
                            vehicle.status === 'available' || 
                            vehicle.is_available || 
                            (!vehicle.status && !vehicle.is_available);  // Default to available if no status info
                        
                        if (!isAvailable) {
                            option.disabled = true;
                            option.textContent += ' (Not Available)';
                        }
                        
                        select.appendChild(option);
                    }
                });
            }
            
            // Log the final state of the select element
            console.log('Vehicle select options:', select.innerHTML);
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
                        option.textContent = `${driverName} (${driverPhone})`;
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

            // Origin
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

            // Intermediate stops
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

            // Destination
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
            updateRouteInfo(); // Update route info for parcel filtering
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
            
            // Build route outlet names
            const routeNames = [];
            
            // Add origin
            if (originSelect?.value) {
                const originText = originSelect.options[originSelect.selectedIndex]?.text || 'Origin';
                routeNames.push(originText);
            }
            
            // Add intermediate stops
            tripData.stops.forEach(stop => {
                routeNames.push(stop.name);
            });
            
            // Add destination
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

            // Checking if already added
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
            
            // Auto-refreshing parcels if we're on step 3
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

            // Update buttons
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
                        
                        // Updating trip data
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

            // Calculating parcel statistics
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

            // Showing selected parcels details if any
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
                // detect if this page was opened for editing an existing trip (create-trip.php?edit=ID)
                const urlParamsForEdit = new URLSearchParams(window.location.search);
                const editTripId = urlParamsForEdit.get('edit');
                const isEditing = !!editTripId;
                submitBtn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${isEditing ? 'Updating' : 'Creating'} Trip...`;

                showMessage(`${isEditing ? 'Updating' : 'Creating'} trip...`, 'info');
                
                // trip data for API
                // Note: post stops as rows to `trip_stops` table (use outlet_id & outlet_stop_order)
                // and send selected parcels as objects with parcel_id so server can create parcel_list rows.
                // read auth context from hidden inputs in case JS global not set
                const hiddenCompanyId = document.getElementById('companyId') ? document.getElementById('companyId').value : '';
                const hiddenManagerId = document.getElementById('managerId') ? document.getElementById('managerId').value : '';

                const tripPayload = {
                    vehicle_id: document.getElementById('vehicleId').value,
                    driver_id: document.getElementById('driverId').value,
                    origin_outlet: document.getElementById('originOutlet').value,
                    destination_outlet: document.getElementById('destinationOutlet').value,
                    departure_time: document.getElementById('departureTime').value,
                    // Map frontend stops to DB trip_stops columns
                    route_stops: tripData.stops.map(stop => ({
                        outlet_id: stop.id,
                        outlet_stop_order: stop.order
                    })),
                    // Ensure parcels are objects with parcel_id for server-side insertion
                    selected_parcels: tripData.selectedParcels.map(pid => ({ parcel_id: pid }))
                };
                // if editing, include the trip id so the API knows to update
                if (isEditing) {
                    tripPayload.id = editTripId;
                }
                
                console.log('Submitting trip with payload:', tripPayload);
                
                const apiEndpoint = isEditing ? '../api/update_trip.php' : '../api/create_trip.php';
                const response = await fetch(apiEndpoint, {
                    method: 'POST',
                    credentials: 'include',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(tripPayload)
                });
                
                console.log('Trip creation response status:', response.status);
                
                if (response.ok) {
                    const result = await response.json();
                    console.log('Trip creation result:', result);
                    
                    if (result.success) {
                        showMessage(`${isEditing ? 'Trip updated' : 'Trip created'} successfully! Trip ID: ${result.trip_id}`, 'success');
                        
                        // Reuse the same success display component
                        displayTripCreationSuccess(result);
                        
                        // Previously we auto-redirected to trips; now we disconnect and provide explicit actions
                        // The summary includes buttons for 'View Trip' and 'Go to Trips'
                        
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
                            <p style="font-size: 1.2rem; font-weight: bold; color: #155724;">
                                ${(() => {
                                    const serverCount = result.trip_stops_created;
                                    if (serverCount && serverCount > 0) return serverCount;
                                    let local = 0;
                                    if (typeof tripData !== 'undefined') {
                                        local = (tripData.stops ? tripData.stops.length : 0) + 2;
                                        if (document.getElementById('originOutlet').value === document.getElementById('destinationOutlet').value) {
                                            local = (tripData.stops ? tripData.stops.length : 0) + 1;
                                        }
                                    }
                                    return local;
                                })()}
                            </p>
                        </div>
                        <div style="background: white; padding: 20px; border-radius: 10px; border: 2px solid #28a745;">
                            <h4 style="color: #28a745; margin-bottom: 10px;"><i class="fas fa-box"></i> Parcels Assigned</h4>
                            <p style="font-size: 1.2rem; font-weight: bold; color: #155724;">
                                ${result.parcels_assigned > 0 ? result.parcels_assigned : (typeof tripData !== 'undefined' ? tripData.selectedParcels.length : 0)}
                            </p>
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
                    
                    <div style="margin-top: 30px; display:flex; gap:12px; justify-content:center;">
                        <button id="goToTripsBtn" class="btn btn-info" style="margin-right: 8px;">
                            <i class="fas fa-list"></i> Go to Trips
                        </button>
                        <button id="createAnotherTripBtn" class="btn btn-secondary">
                            <i class="fas fa-plus"></i> Create Another Trip
                        </button>
                    </div>
                </div>
            `;
            
            summaryContainer.innerHTML = successHtml;
            // Wire up actions
            const viewBtn = document.getElementById('viewCreatedTripBtn');
            const goTripsBtn = document.getElementById('goToTripsBtn');
            const createAnotherBtn = document.getElementById('createAnotherTripBtn');
            if (viewBtn) viewBtn.addEventListener('click', () => { window.location.href = `view_trip.php?id=${result.trip_id}`; });
            if (goTripsBtn) goTripsBtn.addEventListener('click', () => { window.location.href = 'trips.php'; });
            if (createAnotherBtn) createAnotherBtn.addEventListener('click', () => { window.location.reload(); });
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

        // Helper function to get route outlet IDs for filtering
        function getRouteOutletIds() {
            const routeOutlets = [];
            
            //  origin outlet
            const originSelect = document.getElementById('originOutlet');
            if (originSelect && originSelect.value) {
                routeOutlets.push(originSelect.value);
            }
            
            //  intermediate stops
            if (tripData.stops && tripData.stops.length > 0) {
                tripData.stops.forEach(stop => {
                    if (stop.id && !routeOutlets.includes(stop.id)) {
                        routeOutlets.push(stop.id);
                    }
                });
            }
            
            //  destination outlet
            const destSelect = document.getElementById('destinationOutlet');
            if (destSelect && destSelect.value) {
                if (!routeOutlets.includes(destSelect.value)) {
                    routeOutlets.push(destSelect.value);
                }
            }
            
            console.log('Route outlets for filtering:', routeOutlets);
            return routeOutlets;
        }

function displayParcels(parcels) {
    const container = document.getElementById('parcelsContainer');
    if (!container) return;

    if (!parcels || parcels.length === 0) {
        container.innerHTML = `
            <div style="text-align: center; padding: 20px; color: #6c757d; background: #f8f9fa; border-radius: 8px; border: 2px dashed #dee2e6;">
                <i class="fas fa-inbox" style="font-size: 24px; margin-bottom: 10px;"></i>
                <p>No parcels found for this route.</p>
            </div>
        `;
        return;
    }

    // Render list with selectable controls
    container.innerHTML = parcels.map(parcel => {
        const id = parcel.id || parcel.parcel_id || parcel.track_number || '';
        const isSelected = Array.isArray(tripData.selectedParcels) && tripData.selectedParcels.some(p => String(p) === String(id));
        const cardClass = isSelected ? 'parcel-card selected' : 'parcel-card';
        const btnClass = isSelected ? 'parcel-select-btn deselect' : 'parcel-select-btn select';
        const btnLabel = isSelected ? 'Remove' : 'Add';

        return `
        <div id="parcel-card-${id}" class="${cardClass}" style="border:1px solid #ddd; border-radius:8px; padding:12px; margin-bottom:8px; background:#fff; display:flex; justify-content:space-between; align-items:center;">
            <div style="flex:1;">
                <p style="margin:0 0 6px 0;"><strong>Tracking #:</strong> ${parcel.track_number || id}</p>
                <p style="margin:0 0 6px 0;"><strong>Receiver:</strong> ${parcel.receiver_name || '-'}</p>
                <p style="margin:0 0 6px 0;"><strong>Sender:</strong> ${parcel.sender_name || '-'}</p>
                <p style="margin:0; font-size:0.9rem; color:#555;"><strong>Status:</strong> ${parcel.status || ''}</p>
            </div>
            <div style="margin-left:12px;">
                <button id="parcel-btn-${id}" class="${btnClass}" onclick="toggleParcelSelection('${id}')" style="min-width:90px;">${btnLabel}</button>
            </div>
        </div>`;
    }).join('');
}

// Toggle parcel selection: add or remove parcel id from tripData.selectedParcels
function toggleParcelSelection(parcelId) {
    if (!parcelId) return;
    parcelId = String(parcelId);
    if (!Array.isArray(tripData.selectedParcels)) tripData.selectedParcels = [];

    const idx = tripData.selectedParcels.findIndex(p => String(p) === parcelId);
    const cardEl = document.getElementById('parcel-card-' + parcelId);
    const btnEl = document.getElementById('parcel-btn-' + parcelId);

    if (idx === -1) {
        // add
        tripData.selectedParcels.push(parcelId);
        if (cardEl) cardEl.classList.add('selected');
        if (btnEl) { btnEl.classList.remove('select'); btnEl.classList.add('deselect'); btnEl.textContent = 'Remove'; }
    } else {
        // remove
        tripData.selectedParcels.splice(idx, 1);
        if (cardEl) cardEl.classList.remove('selected');
        if (btnEl) { btnEl.classList.remove('deselect'); btnEl.classList.add('select'); btnEl.textContent = 'Add'; }
    }

    // Update selected parcels UI and stats
    displaySelectedParcels();
    updateParcelStats(availableParcels || []);
}

function updateParcelStats(parcels) {
    const statsDiv = document.getElementById('parcelStats');
    if (!statsDiv) return;

    const total = parcels.length;
    const pending = parcels.filter(p => p.status === 'pending').length;
    const delivered = parcels.filter(p => p.status === 'delivered').length;

    statsDiv.innerHTML = `
        <div style="display:flex; gap:20px; font-size:14px; color:#333;">
            <span><strong>Total:</strong> ${total}</span>
            <span><strong>Pending:</strong> ${pending}</span>
            <span><strong>Delivered:</strong> ${delivered}</span>
        </div>
    `;
}


// Parcel Management Functions
async function loadAvailableParcels(parcelId = null) {
    const container = document.getElementById('parcelsContainer');
    const loadBtn = document.getElementById('loadParcelsBtn');
    const statsDiv = document.getElementById('parcelStats');

    if (!container || !loadBtn) return;

    loadBtn.disabled = true;
    loadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';

    container.innerHTML = '<div class="loading-spinner"></div><p style="text-align: center; color: #666;">Fetching available parcels...</p>';

    try {
        // ✅ Build query params
        const params = new URLSearchParams();

        if (parcelId) {
            // --- Case 1: fetch by parcel ID ---
            params.append("id", parcelId);
        } else {
            // --- Case 2: fetch by origin/destination ---
            const routeOutlets = getRouteOutletIds();

            if (routeOutlets.length === 0) {
                container.innerHTML = `
                    <div style="text-align: center; padding: 20px; color: #6c757d; background: #f8f9fa; border-radius: 8px; border: 2px dashed #dee2e6;">
                        <i class="fas fa-info-circle" style="font-size: 24px; margin-bottom: 10px;"></i>
                        <p>Please select origin and destination outlets first to load matching parcels.</p>
                    </div>
                `;
                loadBtn.disabled = false;
                loadBtn.innerHTML = '<i class="fas fa-refresh"></i> Load Parcels';
                return;
            }

            params.append("origin", routeOutlets[0]); // first = origin
            if (routeOutlets.length > 1) {
                params.append("destination", routeOutlets[routeOutlets.length - 1]); // last = destination
            }
        }

        // ✅ Fetch parcels
        const response = await fetch(`../api/fetch_parcels.php?${params.toString()}`, {
            method: "GET",
            headers: { "Accept": "application/json" }
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

            // ✅ Normalize response
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

            // ✅ Render results
            displayParcels(availableParcels);
            updateParcelStats(availableParcels);

            if (!parcelId) {
                // Only show route message if filtering by outlets
                const routeOutletNames = [];
                // routeOutlets here is the array returned earlier by getRouteOutletIds()
                const selects = [
                    document.getElementById('originOutlet'),
                    document.getElementById('destinationOutlet'),
                    document.getElementById('intermediateOutlet')
                ];

                const routeOutletIds = getRouteOutletIds();

                routeOutletIds.forEach(outletId => {
                    if (!outletId) return;
                    let outletName = outletId; // fallback to raw ID
                    for (let select of selects) {
                        if (select) {
                            for (let option of select.options) {
                                if (String(option.value) === String(outletId)) {
                                    outletName = option.text;
                                    break;
                                }
                            }
                        }
                    }
                    routeOutletNames.push(outletName);
                });


                showMessage(`${availableParcels.length} parcels loaded for route: ${routeOutletNames.join(' → ')}`, 'success');
            } else {
                showMessage(`${availableParcels.length} parcel(s) loaded by ID lookup`, 'success');
            }

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
        container.innerHTML = `
            <div style="text-align: center; padding: 20px; color: #dc3545; background: #f8d7da; border-radius: 8px; border: 2px solid #f5c6cb;">
                <i class="fas fa-exclamation-triangle" style="font-size: 24px; margin-bottom: 10px;"></i>
                <p>Error loading parcels. Please try again.</p>
            </div>
        `;
        showMessage('Error loading parcels: ' + error.message, 'error');
    }

    loadBtn.disabled = false;
    loadBtn.innerHTML = '<i class="fas fa-refresh"></i> Refresh Parcels';
}


        // Notification system
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
    <script src="../assets/js/company-scripts.js"></script>
</body>
</html>