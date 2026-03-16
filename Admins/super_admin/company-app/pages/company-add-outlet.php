<?php
    $page_title = 'Company - Add Outlet';
    include __DIR__ . '/../includes/header.php';
?>

<body class="bg-gray-100 min-h-screen">

    <div class="mobile-dashboard">
                    
        <!-- Main Content Area for Add New Outlet -->
        <main class="main-content">
            <div class="content-header">
                <h1>Add New Outlet</h1>
            </div>

            <div class="form-card">
                <p class="text-gray-600 mb-6">Provide the necessary details for the new outlet.</p>
                <form id="addOutletForm" novalidate>
                    <div class="form-group">
                        <label for="outletName">Outlet Name <span class="required">*</span></label>
                        <input type="text" id="outletName" name="outletName" class="form-input-field" placeholder="Enter outlet name" required>
                    </div>
                    <div class="form-group">
                        <input type="hidden" id="address" name="address" value="">
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
                        <label for="contactPerson">Contact Person <span class="required">*</span></label>
                        <input type="text" id="contactPerson" name="contactPerson" class="form-input-field" placeholder="Enter contact person's name" required>
                    </div>
                    <div class="form-group">
                        <label for="contact_email">Contact Email <span class="required">*</span></label>
                        <input type="email" id="contact_email" name="contact_email" class="form-input-field" placeholder="Enter Email" required>
                    </div>
                     <div class="form-group">
                        <label for="contact_phone">Contact Phone <span class="required">*</span></label>
                        <input type="tel" id="contact_phone" name="contact_phone" class="form-input-field" placeholder="Enter phone number" required>
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
                        <label for="status">Status</label>
                        <select id="status" name="status" class="form-input-field" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="maintenance">Under Maintenance</option>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="action-btn secondary" onclick="history.back()">
                            <i class="fas fa-arrow-left"></i> Back
                        </button>
                        <button type="submit" class="action-btn" id="saveOutletBtn">Save</button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <!-- Scripts -->
    <script src="../assets/js/company-scripts.js"></script>
    <script src="../../assets/js/form-validator.js?v=2"></script>
    <script>
        const validator = new FormValidator('addOutletForm', {
            outletName:      { required: true, minLength: 2, maxLength: 100 },
            address_line1:   { required: true, minLength: 5 },
            contactPerson:   { required: true, minLength: 2, maxLength: 100 },
            contact_email:   { required: true, email: true },
            contact_phone:   { required: true, phone: true },
            password:        { required: true, password: true, minLength: 8, maxLength: 16 },
            confirmPassword: { required: true, password: true, match: 'password' },
            country:         { required: true },
            status:          { required: true }
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

        function buildHiddenAddress() {
            const parts = [];
            const a1 = document.getElementById('address_line1').value.trim(); if (a1) parts.push(a1);
            const city = document.getElementById('city').value.trim(); if (city) parts.push(city);
            const state = document.getElementById('state').value.trim(); if (state) parts.push(state);
            const postal = document.getElementById('postal_code').value.trim(); if (postal) parts.push(postal);
            const country = document.getElementById('country').value; if (country) parts.push(country);
            document.getElementById('address').value = parts.join(', ');
        }

        document.getElementById('addOutletForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            if (!validator.validateAll()) return;

            buildHiddenAddress();

            const saveBtn = document.getElementById('saveOutletBtn');
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';

            // Build phone with country code
            let fullPhone = '';
            const phoneVal = document.getElementById('contact_phone').value.replace(/\s/g, '');
            if (phoneVal) {
                const selectedCountry = validator._phoneCountrySelections['contact_phone'] || 'ZM';
                const country = COUNTRY_CODES.find(c => c.code === selectedCountry);
                fullPhone = country ? country.dial + phoneVal : '+260' + phoneVal;
            }

            const formData = {
                outletName: document.getElementById('outletName').value.trim(),
                address: document.getElementById('address_line1').value.trim(),
                city: document.getElementById('city').value.trim(),
                state: document.getElementById('state').value.trim(),
                postal_code: document.getElementById('postal_code').value.trim(),
                country: document.getElementById('country').value,
                contactPerson: document.getElementById('contactPerson').value.trim(),
                contact_email: document.getElementById('contact_email').value.trim(),
                contact_phone: fullPhone,
                password: document.getElementById('password').value,
                status: document.getElementById('status').value
            };

            try {
                const response = await fetch('../api/add_outlet.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify(formData)
                });
                const result = await response.json();

                if (result.success === true) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Outlet Created!',
                        text: 'The outlet has been created successfully.',
                        confirmButtonColor: '#2e0d2a',
                        timer: 2500,
                        timerProgressBar: true
                    }).then(() => {
                        window.location.href = 'outlets.php';
                    });
                    return;
                }
                if (result.errors) validator.applyServerErrors(result.errors);

                let errorMessage = result.error || 'Failed to create outlet';
                if (errorMessage.includes('already exists')) {
                    errorMessage = 'An outlet with this name already exists. Please use a different name.';
                }
                throw new Error(errorMessage);
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
