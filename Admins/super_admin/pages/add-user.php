<?php
session_start();
require_once __DIR__ . '/../api/supabase-client.php';

// Check if user is logged in and has super_admin role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: login.php');
    exit;
}

// Fetch companies for the company dropdown (needed for driver / company-manager roles)
try {
    $companies = callSupabaseWithServiceKey('companies?select=id,company_name&order=company_name.asc', 'GET');
    if (!is_array($companies)) $companies = [];
} catch (Exception $e) {
    $companies = [];
}

$pageTitle = 'Admin - Add New User';
require_once __DIR__ . '/../includes/header.php';
?>

    <style>
        .form-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            margin: 2rem auto;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #374151;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            font-size: 1rem;
            transition: border-color 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: #2E0D2A;
            outline: none;
            box-shadow: 0 0 0 3px rgba(46, 13, 42, 0.1);
        }

        .submit-btn {
            background-color: #2E0D2A;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.375rem;
            font-weight: 500;
            cursor: pointer;
            width: 100%;
            transition: background-color 0.2s;
        }

        .submit-btn:hover {
            background-color: #4a1545;
        }

        .submit-btn:disabled {
            background-color: #9ca3af;
            cursor: not-allowed;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #6b7280;
            text-decoration: none;
            margin-bottom: 1rem;
        }

        .back-btn:hover {
            color: #374151;
        }

        .form-section {
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-section h2 {
            color: #374151;
            font-size: 1.25rem;
            margin-bottom: 1rem;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        @media (max-width: 640px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }
        }

        /* Conditional section visibility */
        .conditional-field {
            display: none;
        }
        .conditional-field.visible {
            display: block;
        }
    </style>
    <div class="mobile-dashboard">
        <!-- Main Content -->
        <main class="main-content">
            <div class="content-header">
                <a href="users.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i>
                    Back to Users
                </a>
                <h1>Add New User</h1>
            </div>

            <div class="form-container">
                <form id="addUserForm" novalidate>
                    <input type="hidden" id="csrf_token" value="<?php echo CSRFHelper::getToken(); ?>">

                    <!-- Role Selection -->
                    <div class="form-section">
                        <h2>Role</h2>
                        <div class="form-group">
                            <label for="role">User Role <span class="required">*</span></label>
                            <select id="role" name="role" class="form-input-field" required>
                                <option value="">Select a Role</option>
                                <!-- Populated dynamically from DB -->
                            </select>
                        </div>

                        <!-- Company dropdown – shown only for roles that need it -->
                        <div class="form-group conditional-field" id="companyFieldWrapper">
                            <label for="company_id">Company <span class="required">*</span></label>
                            <select id="company_id" name="company_id" class="form-input-field">
                                <option value="">Select Company</option>
                                <?php foreach ($companies as $company): ?>
                                    <option value="<?php echo htmlspecialchars($company['id']); ?>">
                                        <?php echo htmlspecialchars($company['company_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Outlet dropdown – shown only for outlet_manager -->
                        <div class="form-group conditional-field" id="outletFieldWrapper">
                            <label for="outlet_id">Outlet <span class="required">*</span></label>
                            <select id="outlet_id" name="outlet_id" class="form-input-field">
                                <option value="">Select a company first</option>
                            </select>
                        </div>

                        <!-- License Number – shown only for driver role -->
                        <div class="form-group conditional-field" id="licenseFieldWrapper">
                            <label for="license_number">Driver License Number <span class="required">*</span></label>
                            <input type="text" id="license_number" name="license_number" class="form-input-field" placeholder="Enter driver license number">
                        </div>
                    </div>

                    <!-- Personal Information -->
                    <div class="form-section">
                        <h2>Personal Information</h2>
                        <div class="form-group">
                            <label for="name">Full Name <span class="required">*</span></label>
                            <input type="text" id="name" name="name" class="form-input-field" placeholder="Enter full name" required>
                        </div>

                        <div class="grid-2">
                            <div class="form-group">
                                <label for="email">Email Address <span class="required">*</span></label>
                                <input type="email" id="email" name="email" class="form-input-field" placeholder="e.g., user@example.com" required>
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone <span class="required">*</span></label>
                                <input type="tel" id="phone" name="phone" class="form-input-field" required>
                            </div>
                        </div>
                    </div>

                    <!-- Address -->
                    <div class="form-section">
                        <h2>Address</h2>
                        <input type="hidden" id="address" name="address" value="">
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
                    </div>

                    <!-- Password -->
                    <div class="form-section" style="border-bottom: none; padding-bottom: 0;">
                        <h2>Security</h2>
                        <div class="grid-2">
                            <div class="form-group">
                                <label for="password">Password <span class="required">*</span></label>
                                <input type="password" id="password" name="password" class="form-input-field" placeholder="8-16 characters" minlength="8" maxlength="16" required>
                            </div>
                            <div class="form-group">
                                <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                                <input type="password" id="confirm_password" name="confirm_password" class="form-input-field" placeholder="Re-enter password" minlength="8" maxlength="16" required>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="submit-btn" id="submitBtn">Create User Account</button>
                </form>
            </div>
        </main>
    </div>

    <!-- Form Validator -->
    <script src="../assets/js/form-validator.js?v=2"></script>
    <script>
        const validator = new FormValidator('addUserForm', {
            role:             { required: true },
            name:             { required: true, minLength: 2, maxLength: 100 },
            email:            { required: true, email: true },
            phone:            { required: true, phone: true },
            address_line1:    { required: true, minLength: 3 },
            password:         { required: true, password: true, minLength: 8, maxLength: 16 },
            confirm_password: { required: true, password: true, match: 'password' },
            license_number:   { minLength: 3, maxLength: 50 }
        });

        // Populate country dropdown
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

        // Populate role dropdown dynamically from the database
        (function loadRoles() {
            const roleSelect = document.getElementById('role');
            fetch('../api/get_roles.php')
                .then(r => r.json())
                .then(data => {
                    if (Array.isArray(data)) {
                        data.forEach(role => {
                            const opt = document.createElement('option');
                            opt.value = role.name;
                            opt.textContent = role.name.charAt(0).toUpperCase() + role.name.slice(1);
                            roleSelect.appendChild(opt);
                        });
                    }
                })
                .catch(err => {
                    console.error('Failed to load roles', err);
                    // Fallback hardcoded roles
                    ['super_admin', 'driver', 'customer'].forEach(r => {
                        const opt = document.createElement('option');
                        opt.value = r;
                        opt.textContent = r.charAt(0).toUpperCase() + r.slice(1);
                        roleSelect.appendChild(opt);
                    });
                });
        })();

        // Roles that require a company selection
        const companyRoles = ['driver', 'company_manager', 'company_admin', 'outlet_manager'];
        const outletRoles = ['outlet_manager'];
        const driverRoles = ['driver'];
        const companyFieldWrapper = document.getElementById('companyFieldWrapper');
        const outletFieldWrapper = document.getElementById('outletFieldWrapper');
        const licenseFieldWrapper = document.getElementById('licenseFieldWrapper');
        const outletSelect = document.getElementById('outlet_id');
        const roleSelect = document.getElementById('role');

        roleSelect.addEventListener('change', function() {
            const selectedRole = this.value.toLowerCase();
            // Show/hide company
            if (companyRoles.includes(selectedRole)) {
                companyFieldWrapper.classList.add('visible');
            } else {
                companyFieldWrapper.classList.remove('visible');
                document.getElementById('company_id').value = '';
            }
            // Show/hide outlet
            if (outletRoles.includes(selectedRole)) {
                outletFieldWrapper.classList.add('visible');
            } else {
                outletFieldWrapper.classList.remove('visible');
                outletSelect.innerHTML = '<option value="">Select a company first</option>';
            }
            // Show/hide license number
            if (driverRoles.includes(selectedRole)) {
                licenseFieldWrapper.classList.add('visible');
            } else {
                licenseFieldWrapper.classList.remove('visible');
                document.getElementById('license_number').value = '';
            }
        });

        // Fetch outlets when a company is selected (only relevant for outlet_manager)
        document.getElementById('company_id').addEventListener('change', async function() {
            const companyId = this.value;
            const selectedRole = roleSelect.value.toLowerCase();

            // Reset outlet dropdown
            outletSelect.innerHTML = '<option value="">Loading outlets...</option>';

            if (!companyId || !outletRoles.includes(selectedRole)) {
                outletSelect.innerHTML = '<option value="">Select a company first</option>';
                return;
            }

            try {
                const res = await fetch('../api/get_outlets_by_company.php?company_id=' + encodeURIComponent(companyId));
                const json = await res.json();

                outletSelect.innerHTML = '<option value="">Select Outlet</option>';
                if (json.success && Array.isArray(json.data)) {
                    if (json.data.length === 0) {
                        outletSelect.innerHTML = '<option value="">No outlets found for this company</option>';
                    } else {
                        json.data.forEach(o => {
                            const opt = document.createElement('option');
                            opt.value = o.id;
                            opt.textContent = o.outlet_name;
                            outletSelect.appendChild(opt);
                        });
                    }
                }
            } catch (err) {
                console.error('Failed to load outlets:', err);
                outletSelect.innerHTML = '<option value="">Failed to load outlets</option>';
            }
        });

        // Fetch and populate outlet address when outlet is selected
        outletSelect.addEventListener('change', async function() {
            const outletId = this.value;
            const selectedRole = roleSelect.value.toLowerCase();

            // Only populate address for outlet_manager role
            if (!outletRoles.includes(selectedRole) || !outletId) {
                return;
            }

            try {
                const res = await fetch('../api/get_outlet_details.php?outlet_id=' + encodeURIComponent(outletId));
                const json = await res.json();

                if (json.success && json.data) {
                    // Populate address field from outlet data
                    // The outlet has a single 'address' field, so we'll put it in address_line1
                    document.getElementById('address_line1').value = json.data.address || '';
                    // Clear other address fields as they're not applicable for outlet addresses
                    document.getElementById('city').value = '';
                    document.getElementById('state').value = '';
                    document.getElementById('postal_code').value = '';
                    document.getElementById('country').value = '';
                }
            } catch (err) {
                console.error('Failed to load outlet details:', err);
            }
        });

        // Build hidden combined address
        function buildHiddenAddress() {
            const parts = [];
            const a1 = document.getElementById('address_line1').value.trim(); if (a1) parts.push(a1);
            const city = document.getElementById('city').value.trim(); if (city) parts.push(city);
            const state = document.getElementById('state').value.trim(); if (state) parts.push(state);
            const postal = document.getElementById('postal_code').value.trim(); if (postal) parts.push(postal);
            const country = document.getElementById('country').value; if (country) parts.push(country);
            document.getElementById('address').value = parts.join(', ');
        }

        // Form submission
        document.getElementById('addUserForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            if (!validator.validateAll()) return;

            // Validate company is selected for roles that require it
            const selectedRole = document.getElementById('role').value.toLowerCase();
            if (companyRoles.includes(selectedRole) && !document.getElementById('company_id').value) {
                Swal.fire({ icon: 'warning', title: 'Company Required', text: 'Please select a company for this role.', confirmButtonColor: '#2e0d2a' });
                return;
            }

            // Validate outlet is selected for outlet_manager
            if (outletRoles.includes(selectedRole) && !document.getElementById('outlet_id').value) {
                Swal.fire({ icon: 'warning', title: 'Outlet Required', text: 'Please select an outlet for this role.', confirmButtonColor: '#2e0d2a' });
                return;
            }

            buildHiddenAddress();

            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Creating Account...';

            try {
                // Build phone with country code
                const phoneDigits = document.getElementById('phone').value.replace(/\s/g, '');
                const selectedCountry = validator._phoneCountrySelections['phone'] || 'ZM';
                const countryData = (typeof COUNTRY_CODES !== 'undefined') ? COUNTRY_CODES.find(c => c.code === selectedCountry) : null;
                const fullPhone = countryData ? countryData.dial + phoneDigits : '+260' + phoneDigits;

                    const payload = {
                        full_name: document.getElementById('name').value,
                        email: document.getElementById('email').value,
                        phone: fullPhone,
                        address: document.getElementById('address_line1').value.trim(),
                        city: document.getElementById('city').value.trim(),
                        state: document.getElementById('state').value.trim(),
                        postal_code: document.getElementById('postal_code').value.trim(),
                        country: document.getElementById('country').value,
                        role: document.getElementById('role').value,
                        company_id: document.getElementById('company_id').value || null,
                        outlet_id: document.getElementById('outlet_id').value || null,
                        password: document.getElementById('password').value,
                        confirm_password: document.getElementById('confirm_password').value,
                        status: 'active'
                    };

                // Add license_number if role is driver
                if (document.getElementById('role').value.toLowerCase() === 'driver') {
                    payload.license_number = document.getElementById('license_number').value;
                }

                const response = await fetch('../api/add_user.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.getElementById('csrf_token').value
                    },
                    body: JSON.stringify(payload)
                });
                const result = await response.json();

                if (result.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'User Created!',
                        text: 'User account created successfully!',
                        confirmButtonColor: '#2e0d2a',
                        timer: 2500,
                        timerProgressBar: true
                    }).then(() => {
                        window.location.href = 'users.php';
                    });
                } else {
                    if (result.errors) validator.applyServerErrors(result.errors);
                    Swal.fire({ icon: 'error', title: 'Error', text: result.error || 'Failed to create user account', confirmButtonColor: '#2e0d2a' });
                }
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'Connection Error', text: 'Could not connect to the server.', confirmButtonColor: '#2e0d2a' });
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Create User Account';
            }
        });
    </script>
</body>
</html>
