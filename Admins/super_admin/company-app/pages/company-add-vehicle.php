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
                <form id="addVehicleForm" novalidate>
                    <div class="form-group">
                        <label for="name">Name <span class="required">*</span></label>
                        <input type="text" id="name" name="name" class="form-input-field" placeholder="Enter vehicle name" required>
                    </div>
                    <div class="form-group">
                        <label for="plate_number">Plate Number <span class="required">*</span></label>
                        <input type="text" id="plate_number" name="plate_number" class="form-input-field" placeholder="Enter plate number" required>
                    </div>
                    <div class="form-group">
                        <label for="status">Vehicle Status <span class="required">*</span></label>
                        <select id="status" name="status" class="form-input-field" required>
                            <option value="">Select status</option>
                            <option value="available">Available</option>
                            <option value="assigned">Assigned</option>
                            <option value="unavailable">Unavailable</option>
                            <option value="out_for_delivery">Out for Delivery</option>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="action-btn secondary" onclick="history.back()">
                            <i class="fas fa-arrow-left"></i> Back
                        </button>
                        <button type="submit" class="action-btn" id="saveVehicleBtn">
                            <i class="fas fa-plus"></i> Add Vehicle
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <!-- Scripts -->
    <script src="../assets/js/company-scripts.js"></script>
    <script src="../../assets/js/form-validator.js?v=2"></script>
    <script>
        const validator = new FormValidator('addVehicleForm', {
            name:         { required: true, minLength: 2, maxLength: 100 },
            plate_number: { required: true, minLength: 2, maxLength: 20 },
            status:       { required: true }
        });

        document.getElementById('addVehicleForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            if (!validator.validateAll()) return;

            const saveBtn = document.getElementById('saveVehicleBtn');
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            const formData = {
                name: document.getElementById('name').value.trim(),
                plate_number: document.getElementById('plate_number').value.trim(),
                status: document.getElementById('status').value
            };

            try {
                const response = await fetch('../api/add_vehicle.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify(formData)
                });
                const result = await response.json();

                if (result.success === true) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Vehicle Added!',
                        text: 'The vehicle has been added successfully.',
                        confirmButtonColor: '#2e0d2a',
                        timer: 2500,
                        timerProgressBar: true
                    }).then(() => {
                        window.location.href = 'company-vehicles.php';
                    });
                    return;
                }
                if (result.errors) validator.applyServerErrors(result.errors);
                throw new Error(result.error || 'Failed to add vehicle');
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message || 'An unexpected error occurred.',
                    confirmButtonColor: '#2e0d2a'
                });
            } finally {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fas fa-plus"></i> Add Vehicle';
            }
        });
    </script>
</body>
</html>
