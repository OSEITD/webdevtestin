<?php
session_start();


// Check if user is logged in and has correct role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: login.php');
    exit;
}

require_once dirname(__DIR__) . '/api/supabase-client.php';

// Fetch companies for the dropdown
try {
    $companies = callSupabase('companies?select=id,company_name&status=eq.active');
    if (!is_array($companies)) {
        $companies = [];
    }
} catch (Exception $e) {
    error_log('Error fetching companies: ' . $e->getMessage());
    $companies = [];
}

$pageTitle = 'Admin - Add Outlet';
require_once __DIR__ . '/../includes/header.php';
?>

    <div class="mobile-dashboard">
        <!-- Main Content Area for Add New Outlet -->
        <main class="main-content">
            <div class="content-header">
                <h1>Add New Outlet</h1>
            </div>

            <div class="form-container">
                <h2>Outlet Details</h2>
                <form id="addOutletForm" novalidate>
                    <input type="hidden" id="csrf_token" value="<?php echo CSRFHelper::getToken(); ?>">
                    <div class="form-group">
                        <label for="outletName">Outlet Name <span class="required">*</span></label>
                        <input type="text" id="outletName" name="outletName" class="form-input-field" placeholder="e.g., Downtown Branch" required>
                    </div>
                    <div class="form-group">
                        <label for="company">Associated Company <span class="required">*</span></label>
                        <div class="select-wrapper">
                            <select id="company" name="company" class="form-input-field" required>
                                <option value="">Select a Company</option>
                                <?php if (!empty($companies)): ?>
                                    <?php foreach ($companies as $company): ?>
                                        <option value="<?php echo htmlspecialchars($company['id']); ?>">
                                            <?php echo htmlspecialchars($company['company_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="" disabled>No companies available</option>
                                <?php endif; ?>
                            </select>
                            <div class="select-arrow"></div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="contactPerson">Contact Person <span class="required">*</span></label>
                        <input type="text" id="contactPerson" name="contactPerson" class="form-input-field" placeholder="e.g., John Smith" required>
                    </div>
                    <div class="form-group">
                        <label for="contactEmail">Contact Email <span class="required">*</span></label>
                        <input type="email" id="contactEmail" name="contactEmail" class="form-input-field" placeholder="e.g., john.smith@outlet.com" required>
                    </div>
                    <div class="form-group">
                        <label for="contactPhone">Contact Phone</label>
                        <input type="tel" id="contactPhone" name="contactPhone" class="form-input-field">
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
                        <label for="password">Password <span class="required">*</span></label>
                        <input type="password" id="password" name="password" class="form-input-field" placeholder="8-16 characters" minlength="8" maxlength="16" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-input-field" placeholder="Re-enter password" minlength="8" maxlength="16" required>
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="form-select-field">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn cancel-btn" id="cancelBtn">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn save-btn" id="saveOutletBtn">
                            <i class="fas fa-plus-circle"></i> Add Outlet
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <!-- Form Validator -->
    <script src="../assets/js/form-validator.js?v=2"></script>
    <script>
        const validator = new FormValidator('addOutletForm', {
            outletName:       { required: true, minLength: 2, maxLength: 100 },
            company:          { required: true },
            contactPerson:    { required: true, minLength: 2, maxLength: 100 },
            contactEmail:     { required: true, email: true },
            contactPhone:     { phone: true },
            address_line1:    { required: true, minLength: 3 },
            city:             { minLength: 2 },
            country:          { required: true },
            password:         { required: true, password: true, minLength: 8, maxLength: 16 },
            confirm_password: { required: true, password: true, match: 'password' }
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

            try {
                let fullPhone = '';
                const phoneVal = document.getElementById('contactPhone').value.replace(/\s/g, '');
                if (phoneVal) {
                    const selectedCountry = validator._phoneCountrySelections['contactPhone'] || 'ZM';
                    const country = COUNTRY_CODES.find(c => c.code === selectedCountry);
                    fullPhone = country ? country.dial + phoneVal : '+260' + phoneVal;
                }

                const response = await fetch('../api/add_outlet.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.getElementById('csrf_token').value
                    },
                    body: JSON.stringify({
                        outlet_name: document.getElementById('outletName').value.trim(),
                        company_id: document.getElementById('company').value.trim(),
                        address: document.getElementById('address_line1').value.trim(),
                        city: document.getElementById('city').value.trim(),
                        state: document.getElementById('state').value.trim(),
                        postal_code: document.getElementById('postal_code').value.trim(),
                        country: document.getElementById('country').value,
                        contact_person: document.getElementById('contactPerson').value.trim(),
                        contact_phone: fullPhone,
                        contact_email: document.getElementById('contactEmail').value.trim(),
                        password: document.getElementById('password').value,
                        confirm_password: document.getElementById('confirm_password').value,
                        status: document.getElementById('status').value
                    })
                });
                const result = await response.json();

                if (result.success && result.outlet && Array.isArray(result.outlet)) {
                    Swal.fire({
                        icon: 'success', title: 'Success!',
                        text: 'Outlet created successfully!',
                        confirmButtonColor: '#2e0d2a',
                        timer: 2500,
                        timerProgressBar: true
                    }).then(() => { window.location.href = 'outlets.php'; });
                } else {
                    if (result.errors) validator.applyServerErrors(result.errors);
                    throw new Error(result.error || 'Failed to create outlet');
                }
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'Error', text: error.message, confirmButtonColor: '#2e0d2a' });
            } finally {
                saveBtn.disabled = false;
            }
        });

        document.getElementById('cancelBtn').addEventListener('click', function() {
            window.history.back();
        });
    </script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
