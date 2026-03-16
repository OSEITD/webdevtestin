<?php
session_start();
require_once __DIR__ . '/../api/supabase-client.php';
require_once __DIR__ . '/../includes/currency-helper.php';

// Check if user is logged in and has super_admin role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: login.php');
    exit;
}

$pageTitle = 'Admin - Add Company';
require_once __DIR__ . '/../includes/header.php';
?>

    <div class="mobile-dashboard">
        <!-- Main Content Area for Add New Company -->
        <main class="main-content">
            <div class="content-header">
                <h1>Add New Company</h1>
            </div>

            <div class="form-container">
                <h2>Company Details</h2>
                <form id="addCompanyForm" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo CSRFHelper::getToken(); ?>">
                    
                    <div class="form-group">
                        <label for="company_name">Company Name <span class="required">*</span></label>
                        <input type="text" id="company_name" name="company_name" class="form-input-field" 
                               placeholder="e.g., Swift Logistics Inc." 
                               minlength="2" maxlength="100" required>
                    </div>

                    <div class="form-group">
                        <label for="subdomain">Subdomain <span class="required">*</span></label>
                        <input type="text" id="subdomain" name="subdomain" class="form-input-field" 
                               placeholder="e.g., swiftlogistics" 
                               minlength="3" maxlength="50" required>
                        <small class="help-text">Lowercase letters and numbers only. Auto-generated from company name.</small>
                    </div>

                    <div class="form-group">
                        <label for="contact_person">Contact Person <span class="required">*</span></label>
                        <input type="text" id="contact_person" name="contact_person" class="form-input-field" 
                               placeholder="e.g., Jane Doe" 
                               minlength="2" maxlength="100" required>
                    </div>

                    <div class="form-group">
                        <label for="contact_email">Contact Email <span class="required">*</span></label>
                        <input type="email" id="contact_email" name="contact_email" class="form-input-field" 
                               placeholder="e.g., jane.doe@company.com" required>
                    </div>

                    <div class="form-group">
                        <label for="contact_phone">Contact Phone <span class="required">*</span></label>
                        <input type="tel" id="contact_phone" name="contact_phone" class="form-input-field" 
                               placeholder="+260 XXX XXX XXX" required>
                    </div>

                    <div class="form-group">
                        <input type="hidden" id="address" name="address" value="">
                        <label for="address_line1">Address Line 1 <span class="required">*</span></label>
                        <input type="text" id="address_line1" name="address_line1" class="form-input-field" placeholder="Street number and street name" minlength="5" maxlength="500" required>
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
                        <label for="password">Password <span class="required">*</span></label>
                        <input type="password" id="password" name="password" class="form-input-field" 
                               placeholder="8-16 characters"
                               minlength="8" maxlength="16" required>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-input-field" 
                               placeholder="Re-enter password"
                               minlength="8" maxlength="16" required>
                    </div>

                    <div class="form-group">
                        <label for="revenue">Initial Revenue</label>
                        <input type="number" id="revenue" name="revenue" class="form-input-field" 
                               placeholder="0.00" step="0.01" value="0">
                    </div>

                    <div class="form-group">
                        <label for="commission_rate">Commission Rate (%)</label>
                        <input type="number" id="commission_rate" name="commission_rate" class="form-input-field" 
                               placeholder="0.00" step="0.01" min="0" max="100" value="0">
                        <small class="help-text">Commission rate percentage for this company</small>
                    </div>

                    <div class="form-group">
                        <label for="status">Status <span class="required">*</span></label>
                        <select id="status" name="status" class="form-select-field" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn cancel-btn" id="cancelBtn">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn save-btn" id="saveCompanyBtn">
                            <i class="fas fa-plus-circle"></i> Add Company
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <!-- Form Validator -->
    <script src="../assets/js/form-validator.js?v=2"></script>
    <script>
        // Initialize form validator with rules
        const validator = new FormValidator('addCompanyForm', {
            company_name: {
                required: true,
                minLength: 2,
                maxLength: 100
            },
            subdomain: {
                required: true,
                minLength: 3,
                maxLength: 50,
                subdomain: true
            },
            contact_person: {
                required: true,
                minLength: 2,
                maxLength: 100
            },
            contact_email: {
                required: true,
                email: true
            },
            contact_phone: {
                required: true,
                phone: true
            },
            address_line1: {
                required: true,
                minLength: 5,
                maxLength: 500
            },
            city: { minLength: 2 },
            country: { required: true },
            password: {
                required: true,
                password: true,
                minLength: 8,
                maxLength: 16
            },
            confirm_password: {
                required: true,
                password: true,
                match: 'password'
            },
            commission_rate: {
                custom: (value) => {
                    const num = parseFloat(value);
                    if (value !== '' && (isNaN(num) || num < 0 || num > 100)) {
                        return 'Commission rate must be between 0 and 100';
                    }
                    return null;
                }
            }
        });

        // Populate country select
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

        // Handle form submission
        document.getElementById('addCompanyForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            // Run full validation
            if (!validator.validateAll()) {
                return;
            }

            // build hidden address
            buildHiddenAddress();

            const submitBtn = document.getElementById('saveCompanyBtn');
            submitBtn.classList.add('is-loading');
            submitBtn.disabled = true;

            const formData = {
                company_name: document.getElementById('company_name').value.trim(),
                subdomain: document.getElementById('subdomain').value.trim(),
                contact_person: document.getElementById('contact_person').value.trim(),
                contact_email: document.getElementById('contact_email').value.trim(),
                contact_phone: (() => {
                    const phoneDigits = document.getElementById('contact_phone').value.replace(/\s/g, '');
                    const selectedCountry = validator._phoneCountrySelections['contact_phone'] || 'ZM';
                    const country = COUNTRY_CODES.find(c => c.code === selectedCountry);
                    return country ? country.dial + phoneDigits : '+260' + phoneDigits;
                })(),
                address: document.getElementById('address_line1').value.trim(),
                city: document.getElementById('city').value.trim(),
                state: document.getElementById('state').value.trim(),
                postal_code: document.getElementById('postal_code').value.trim(),
                country: document.getElementById('country').value,
                password: document.getElementById('password').value,
                confirm_password: document.getElementById('confirm_password').value,
                // Send revenue as entered by user
                revenue: parseFloat(document.getElementById('revenue').value) || 0,
                // Commission rate stored as decimal (e.g., 5.50 for 5.5%)
                commission_rate: parseFloat(document.getElementById('commission_rate').value) || 0,
                status: document.getElementById('status').value
            };

            try {
                const response = await fetch('../api/add_company.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('input[name="csrf_token"]').value
                    },
                    body: JSON.stringify(formData)
                });

                const result = await response.json();
                
                if (result.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Company Added!',
                        text: 'The company has been created successfully.',
                        confirmButtonColor: '#2e0d2a',
                        timer: 2500,
                        timerProgressBar: true
                    }).then(() => {
                        window.location.href = 'companies.php';
                    });
                } else {
                    // If server returned field-specific errors, apply them
                    if (result.errors && typeof result.errors === 'object') {
                        validator.applyServerErrors(result.errors);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: result.error || 'Failed to add company. Please try again.',
                            confirmButtonColor: '#2e0d2a'
                        });
                    }
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Connection Error',
                    text: 'Could not connect to the server. Please check your connection and try again.',
                    confirmButtonColor: '#2e0d2a'
                });
            } finally {
                submitBtn.classList.remove('is-loading');
                submitBtn.disabled = false;
            }
        });

        // Auto-generate subdomain from company name
        document.getElementById('company_name').addEventListener('input', function() {
            const subdomainInput = document.getElementById('subdomain');
            if (!subdomainInput.dataset.userEdited) {
                subdomainInput.value = this.value
                    .toLowerCase()
                    .replace(/[^a-z0-9]/g, '');
                // Trigger validation if subdomain was previously validated
                if (validator.errors['subdomain'] !== undefined) {
                    validator.validateField('subdomain');
                }
            }
        });

        // Mark subdomain as user-edited when manually changed
        document.getElementById('subdomain').addEventListener('input', function() {
            if (this.value !== document.getElementById('company_name').value.toLowerCase().replace(/[^a-z0-9]/g, '')) {
                this.dataset.userEdited = 'true';
            }
        });

        // Cancel button functionality
        document.getElementById('cancelBtn').addEventListener('click', () => {
            Swal.fire({
                title: 'Discard Changes?',
                text: 'Any unsaved changes will be lost.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e74c3c',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, discard',
                cancelButtonText: 'Keep editing'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'companies.php';
                }
            });
        });
    </script>
    <script>
        // Inline sidebar toggle fallback (guards against missing elements)
        (function(){
            const menuBtn = document.getElementById('menuBtn');
            const closeMenu = document.getElementById('closeMenu');
            const sidebar = document.getElementById('sidebar');
            const menuOverlay = document.getElementById('menuOverlay');

        })();
    </script>
</body>
</html>
