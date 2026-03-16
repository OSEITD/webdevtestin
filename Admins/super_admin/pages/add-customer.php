<?php
// Include necessary files and check authentication
require_once __DIR__ . '/../api/supabase-client.php';

$pageTitle = 'Admin - [Page Name]';
require_once '../includes/header.php';
?>

    <div class="mobile-dashboard">
        <!-- Main Content Area -->
        <main class="main-content">
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
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            font-size: 1rem;
            transition: border-color 0.2s;
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #3b82f6;
            outline: none;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .submit-btn {
            background-color: #3b82f6;
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
            background-color: #2563eb;
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

        .error-message {
            color: #dc2626;
            font-size: 0.875rem;
            margin-top: 0.5rem;
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
    </style>

        <!-- Top Header Bar - REMOVED (now in header.php) -->
        <!-- Sidebar - REMOVED (now in header.php) -->

        <div class="content-header">
                <a href="users.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i>
                    Back to Users
                </a>
                <h1>Add New Customer</h1>
            </div>

            <div class="form-container">
                <form id="addCustomerForm" novalidate>
                    <input type="hidden" id="csrf_token" value="<?php echo CSRFHelper::getToken(); ?>">
                      <!-- Company Selection -->
                    <div class="form-section">
                        <h2>Company Information</h2>
                        <div class="form-group">
                            <label for="company_id">Select Company <span class="required">*</span></label>
                            <select id="company_id" name="company_id" class="form-input-field" required>
                                <option value="">Select Company</option>
                                <?php foreach ($companies as $company): ?>
                                    <option value="<?php echo htmlspecialchars($company['id']); ?>">
                                        <?php echo htmlspecialchars($company['company_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <!-- Personal Information -->
                    <div class="form-section">
                        <h2>Personal Information</h2>
                           <div class="form-group">
                            <label for="customer_name">Customer Name <span class="required">*</span></label>
                            <input type="text" id="customer_name" name="customer_name" class="form-input-field" required placeholder="Enter full name">
                        </div>

                        <div class="grid-2">
                            <div class="form-group">
                                <label for="email">Email Address <span class="required">*</span></label>
                                <input type="email" id="email" name="email" class="form-input-field" required>
                            </div>

                            <div class="form-group">
                                <label for="phone">Phone Number <span class="required">*</span></label>
                                <input type="tel" id="phone" name="phone" class="form-input-field" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="password">Password <span class="required">*</span></label>
                            <input type="password" id="password" name="password" class="form-input-field" placeholder="8-16 characters" minlength="8" maxlength="16" required>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-input-field" placeholder="Re-enter password" minlength="8" maxlength="16" required>
                        </div>
                    </div>

                    <!-- Primary Address -->
                    <div class="form-section">
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

                    <button type="submit" class="submit-btn" id="submitBtn">Create Customer Account</button>
                </form>
            </div>
        </main>
    </div>

    <!-- Form Validator -->
    <script src="../assets/js/form-validator.js?v=2"></script>
    <script>
        const validator = new FormValidator('addCustomerForm', {
            company_id:       { required: true },
            customer_name:    { required: true, minLength: 2, maxLength: 100 },
            email:            { required: true, email: true },
            phone:            { required: true, phone: true },
            password:         { required: true, password: true, minLength: 8, maxLength: 16 },
            confirm_password: { required: true, password: true, match: 'password' },
            address_line1:    { required: true, minLength: 3 },
            city:             { minLength: 2 },
            country:          { required: true }
        });

        // Populate country select if COUNTRY_CODES is available
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

        document.getElementById('addCustomerForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            if (!validator.validateAll()) return;

            // assemble address into the hidden `address` field
            buildHiddenAddress();

            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Creating Account...';

            try {
                const phoneDigits = document.getElementById('phone').value.replace(/\s/g, '');
                const selectedCountry = validator._phoneCountrySelections['phone'] || 'ZM';
                const country = COUNTRY_CODES.find(c => c.code === selectedCountry);
                const fullPhone = country ? country.dial + phoneDigits : '+260' + phoneDigits;

                const response = await fetch('../api/add_customer.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-TOKEN': document.getElementById('csrf_token').value
                    },
                    body: new URLSearchParams({
                        company_id: document.getElementById('company_id').value,
                        customer_name: document.getElementById('customer_name').value,
                        email: document.getElementById('email').value,
                        phone: fullPhone,
                        password: document.getElementById('password').value,
                        confirm_password: document.getElementById('confirm_password').value,
                        address: document.getElementById('address').value
                    })
                });
                const result = await response.json();

                if (result.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Customer Created!',
                        text: 'Customer account created successfully!',
                        confirmButtonColor: '#2e0d2a',
                        timer: 2500,
                        timerProgressBar: true
                    }).then(() => { window.location.href = 'users.php'; });
                } else {
                    if (result.errors) validator.applyServerErrors(result.errors);
                    Swal.fire({ icon: 'error', title: 'Error', text: result.message || 'Failed to create customer account', confirmButtonColor: '#2e0d2a' });
                }
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'Connection Error', text: 'Could not connect to the server.', confirmButtonColor: '#2e0d2a' });
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Create Customer Account';
            }
        });
    </script>
</body>
</html>
