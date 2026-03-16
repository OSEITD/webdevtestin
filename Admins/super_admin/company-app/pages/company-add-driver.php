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
                <form id="addDriverForm" novalidate>
                    <div class="form-group">
                        <label for="driverName">Driver Name <span class="required">*</span></label>
                        <input type="text" id="driverName" name="driverName" class="form-input-field" placeholder="Enter driver's full name" required>
                    </div>
                    <div class="form-group">
                        <label for="address_line1">Address Line 1 <span class="required">*</span></label>
                        <input type="text" id="address_line1" name="address_line1" class="form-input-field" placeholder="Street number and street name" required>
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label for="city">City / Town</label>
                            <input type="text" id="city" name="city" class="form-input-field">
                        </div>
                        <div class="form-group">
                            <label for="state">State / Province</label>
                            <input type="text" id="state" name="state" class="form-input-field">
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label for="postal_code">Postal Code</label>
                            <input type="text" id="postal_code" name="postal_code" class="form-input-field">
                        </div>
                        <div class="form-group">
                            <label for="country">Country</label>
                            <select id="country" name="country" class="form-input-field">
                                <option value="">Select Country</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="driver_phone">Phone <span class="required">*</span></label>
                        <input type="tel" id="driver_phone" name="driver_phone" class="form-input-field" placeholder="Enter contact number" required>
                    </div>
                    <div class="form-group">
                        <label for="driver_email">Email <span class="required">*</span></label>
                        <input type="email" id="driver_email" name="driver_email" class="form-input-field" placeholder="Enter email" required>
                    </div>
                    <div class="form-group">
                        <label for="license_number">License <span class="required">*</span></label>
                        <input type="text" id="license_number" name="license_number" class="form-input-field" placeholder="Enter license number" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password <span class="required">*</span></label>
                        <input type="password" id="password" name="password" class="form-input-field" placeholder="8-16 characters" required minlength="8" maxlength="16">
                    </div>
                    <div class="form-group">
                        <label for="confirmPassword">Confirm Password <span class="required">*</span></label>
                        <input type="password" id="confirmPassword" name="confirmPassword" class="form-input-field" placeholder="Confirm the password" required minlength="8" maxlength="16">
                    </div>
                    <div class="form-group">
                        <label for="employmentStatus">Employment Status <span class="required">*</span></label>
                        <select id="employmentStatus" name="employmentStatus" class="form-input-field" required>
                            <option value="">Select employment status</option>
                            <option value="available">Available</option>
                            <option value="assigned">Assigned</option>
                            <option value="out_for_delivery">Out for Delivery</option>
                            <option value="unavailable">Unavailable</option>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="action-btn secondary" onclick="history.back()">
                            <i class="fas fa-arrow-left"></i> Back
                        </button>
                        <button type="submit" class="action-btn" id="saveDriverBtn">Save</button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <!-- Scripts -->
    <script src="../assets/js/company-scripts.js"></script>
    <script src="../../assets/js/form-validator.js?v=2"></script>
    <script>
        const validator = new FormValidator('addDriverForm', {
            driverName:       { required: true, minLength: 2, maxLength: 100 },
            driver_phone:     { required: true, phone: true },
            driver_email:     { required: true, email: true },
            license_number:   { required: true, minLength: 2 },
            password:         { required: true, password: true, minLength: 8, maxLength: 16 },
            confirmPassword:  { required: true, password: true, match: 'password' },
            employmentStatus: { required: true },
            address_line1:    { required: true, minLength: 5 },
            country:          { required: true }
        });

        (function populateCountries() {
            if (typeof COUNTRY_CODES === 'undefined') return;
            const sel = document.getElementById('country');
            COUNTRY_CODES.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.name || c.code || c;
                opt.textContent = c.name || c.code || c;
                sel.appendChild(opt);
            });
        })();

        document.getElementById('addDriverForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            if (!validator.validateAll()) return;

            const saveBtn = document.getElementById('saveDriverBtn');
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';

            // Build phone with country code
            let fullPhone = '';
            const phoneVal = document.getElementById('driver_phone').value.replace(/\s/g, '');
            if (phoneVal) {
                const selectedCountry = validator._phoneCountrySelections['driver_phone'] || 'ZM';
                const country = COUNTRY_CODES.find(c => c.code === selectedCountry);
                fullPhone = country ? country.dial + phoneVal : '+260' + phoneVal;
            }

            const formData = {
                driverName: document.getElementById('driverName').value.trim(),
                driver_phone: fullPhone,
                driver_email: document.getElementById('driver_email').value.trim(),
                license_number: document.getElementById('license_number').value.trim(),
                password: document.getElementById('password').value,
                status: document.getElementById('employmentStatus').value,
                address: document.getElementById('address_line1').value.trim(),
                city: document.getElementById('city').value.trim(),
                state: document.getElementById('state').value.trim(),
                postal_code: document.getElementById('postal_code').value.trim(),
                country: document.getElementById('country').value
            };

            try {
                const response = await fetch('../api/add_driver.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify(formData)
                });
                const result = await response.json();

                if (result.success === true) {
                    const creds = result.data.driver;
                    Swal.fire({
                        icon: 'success',
                        title: 'Driver Added!',
                        html: `<p>Login credentials for <strong>${creds.name}</strong>:</p><p>Email: <strong>${creds.email}</strong></p><p>Temporary Password: <strong>${creds.temp_password}</strong></p><p style="color:#666;margin-top:10px;">Please ask them to change their password upon first login.</p>`,
                        confirmButtonColor: '#2e0d2a'
                    }).then(() => {
                        window.location.href = 'drivers.php';
                    });
                    return;
                }
                if (result.errors) validator.applyServerErrors(result.errors);
                throw new Error(result.error || 'Failed to add driver');
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message || 'An unexpected error occurred.',
                    confirmButtonColor: '#2e0d2a'
                });
            } finally {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save';
            }
        });
    </script>
</body>
</html>
