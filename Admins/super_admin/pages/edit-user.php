<?php
session_start();
require_once __DIR__ . '/../api/supabase-client.php';

// Check if user is logged in and has super_admin role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: login.php');
    exit;
}

$userId = $_GET['id'] ?? null;
$user = null;

if ($userId) {
    try {
        $profiles = callSupabaseWithServiceKey('profiles?id=eq.' . urlencode($userId) . '&select=*', 'GET');
        if (!empty($profiles) && is_array($profiles)) {
            $user = $profiles[0];
        }
    } catch (Exception $e) {
        error_log('Error fetching user for edit: ' . $e->getMessage());
    }
}

if (!$user) {
    header('Location: users.php');
    exit;
}

// Fetch companies for dropdown
try {
    $companies = callSupabaseWithServiceKey('companies?select=id,company_name&order=company_name.asc', 'GET');
    if (!is_array($companies)) $companies = [];
} catch (Exception $e) {
    $companies = [];
}

$addressLine1 = $user['address'] ?? '';
$city = $user['city'] ?? '';
$state = $user['state'] ?? '';
$postalCode = $user['postal_code'] ?? '';
$country = $user['country'] ?? '';

// Extract phone digits (strip country code for display)
$phone = $user['phone'] ?? '';
$phoneCountry = 'ZM'; // Default country

// Extract country code from phone if it exists
$countryCodeMap = [
    '+260' => 'ZM',
    '+1' => 'US',
    '+44' => 'UK',
    '+27' => 'ZA',
    '+255' => 'TZ',
    '+254' => 'KE',
    '+234' => 'NG',
    '+233' => 'GH',
    '+91' => 'IN',
    '+61' => 'AU'
];

foreach ($countryCodeMap as $dial => $code) {
    if (strpos($phone, $dial) === 0) {
        $phoneCountry = $code;
        break;
    }
}

$pageTitle = 'Admin - Edit User';
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
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: #374151; }
        .form-group input, .form-group select {
            width: 100%; padding: 0.75rem; border: 1px solid #d1d5db;
            border-radius: 0.375rem; font-size: 1rem; transition: border-color 0.2s;
        }
        .form-group input:focus, .form-group select:focus {
            border-color: #2E0D2A; outline: none; box-shadow: 0 0 0 3px rgba(46, 13, 42, 0.1);
        }
        .submit-btn {
            background-color: #2E0D2A; color: white; padding: 0.75rem 1.5rem;
            border: none; border-radius: 0.375rem; font-weight: 500;
            cursor: pointer; width: 100%; transition: background-color 0.2s;
        }
        .submit-btn:hover { background-color: #4a1545; }
        .submit-btn:disabled { background-color: #9ca3af; cursor: not-allowed; }
        .back-btn {
            display: inline-flex; align-items: center; gap: 0.5rem;
            color: #6b7280; text-decoration: none; margin-bottom: 1rem;
        }
        .back-btn:hover { color: #374151; }
        .form-section { border-bottom: 1px solid #e5e7eb; padding-bottom: 1.5rem; margin-bottom: 1.5rem; }
        .form-section h2 { color: #374151; font-size: 1.25rem; margin-bottom: 1rem; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        @media (max-width: 640px) { .grid-2 { grid-template-columns: 1fr; } }
        .conditional-field { display: none; }
        .conditional-field.visible { display: block; }
        .status-group { display: flex; gap: 1rem; margin-top: 0.5rem; }
        .status-option { display: flex; align-items: center; gap: 0.4rem; cursor: pointer; }
        .status-option input[type="radio"] { accent-color: #2E0D2A; }
    </style>

    <div class="mobile-dashboard">
        <main class="main-content">
            <div class="content-header">
                <a href="view-user.php?id=<?php echo urlencode($user['id']); ?>" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to User
                </a>
                <h1>Edit User</h1>
            </div>

            <div class="form-container">
                <form id="editUserForm" novalidate>
                    <input type="hidden" id="csrf_token" value="<?php echo CSRFHelper::getToken(); ?>">
                    <input type="hidden" id="user_id" value="<?php echo htmlspecialchars($user['id']); ?>">

                    <!-- Role -->
                    <div class="form-section">
                        <h2>Role</h2>
                        <div class="grid-2">
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label for="role">User Role <span class="required">*</span></label>
                                <select id="role" name="role" class="form-input-field" required>
                                    <option value="">Select a Role</option>
                                    <!-- Populated dynamically from DB -->
                                </select>
                            </div>
                        </div>

                        <!-- Company dropdown -->
                        <div class="form-group conditional-field" id="companyFieldWrapper">
                            <label for="company_id">Company <span class="required">*</span></label>
                            <select id="company_id" name="company_id" class="form-input-field">
                                <option value="">Select Company</option>
                                <?php foreach ($companies as $company): ?>
                                    <option value="<?php echo htmlspecialchars($company['id']); ?>"
                                        <?php echo ($user['company_id'] ?? '') === $company['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($company['company_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Outlet dropdown -->
                        <div class="form-group conditional-field" id="outletFieldWrapper">
                            <label for="outlet_id">Outlet <span class="required">*</span></label>
                            <select id="outlet_id" name="outlet_id" class="form-input-field">
                                <option value="">Select a company first</option>
                            </select>
                        </div>
                    </div>

                    <!-- Personal Information -->
                    <div class="form-section">
                        <h2>Personal Information</h2>
                        <div class="form-group">
                            <label for="name">Full Name <span class="required">*</span></label>
                            <input type="text" id="name" name="name" class="form-input-field"
                                   value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" required>
                        </div>
                        <div class="grid-2">
                            <div class="form-group">
                                <label for="email">Email Address <span class="required">*</span></label>
                                <input type="email" id="email" name="email" class="form-input-field"
                                       value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone</label>
                                    <input type="tel" id="phone" name="phone" class="form-input-field"
                                           value="<?php echo htmlspecialchars(substr($phone, -9)); ?>" 
                                           placeholder="Enter phone number">
                            </div>
                        </div>
                    </div>

                    <!-- Address -->
                    <div class="form-section" style="border-bottom: none; padding-bottom: 0;">
                        <h2>Address</h2>
                        <div class="form-group">
                            <label for="address_line1">Address Line 1</label>
                            <input type="text" id="address_line1" name="address_line1" class="form-input-field"
                                   value="<?php echo htmlspecialchars($addressLine1); ?>">
                        </div>
                        <div class="grid-2">
                            <div class="form-group">
                                <label for="city">City / Town</label>
                                <input type="text" id="city" name="city" class="form-input-field"
                                       value="<?php echo htmlspecialchars($city); ?>">
                            </div>
                            <div class="form-group">
                                <label for="state">State / Province</label>
                                <input type="text" id="state" name="state" class="form-input-field"
                                       value="<?php echo htmlspecialchars($state); ?>">
                            </div>
                        </div>
                        <div class="grid-2">
                            <div class="form-group">
                                <label for="postal_code">Postal Code</label>
                                <input type="text" id="postal_code" name="postal_code" class="form-input-field"
                                       value="<?php echo htmlspecialchars($postalCode); ?>">
                            </div>
                            <div class="form-group">
                                <label for="country">Country</label>
                                <select id="country" name="country" class="form-input-field" data-selected="<?php echo htmlspecialchars($country); ?>">
                                    <option value="">Select Country</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- User Suspension Section -->
                    <div class="form-section" style="border-bottom: none; padding-bottom: 0;">
                        <h2>Account Status</h2>
                        <div class="form-group">
                            <label>Current Status</label>
                            <div style="padding: 0.75rem; background-color: #f3f4f6; border-radius: 0.375rem; font-weight: 500;">
                                <span id="statusDisplay" style="text-transform: capitalize;">
                                    <?php echo htmlspecialchars($user['status'] ?? 'Active'); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div id="suspensionInfo" style="display: none; background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 1rem; border-radius: 0.375rem; margin-bottom: 1rem;">
                            <p style="margin: 0 0 0.5rem 0; font-weight: 500;">Suspension Details:</p>
                            <p style="margin: 0.25rem 0; color: #666;">
                                <strong>Suspended At:</strong> <span id="suspendedAtDisplay">-</span>
                            </p>
                            <p style="margin: 0.25rem 0; color: #666;">
                                <strong>Suspended By:</strong> <span id="suspendedByDisplay">-</span>
                            </p>
                            <p style="margin: 0.25rem 0; color: #666;">
                                <strong>Reason:</strong> <span id="suspensionReasonDisplay">-</span>
                            </p>
                        </div>

                        <div style="display: flex; gap: 1rem;">
                            <button type="button" id="suspendBtn" class="submit-btn" style="background-color: #dc2626;">Suspend User</button>
                            <button type="button" id="unsuspendBtn" class="submit-btn" style="background-color: #16a34a; display: none;">Unsuspend User</button>
                        </div>
                    </div>

                    <button type="submit" class="submit-btn" id="submitBtn">Save Changes</button>
                </form>
            </div>
        </main>
    </div>

    <script src="../assets/js/form-validator.js?v=2"></script>
    <script>
    // Instantiate FormValidator so the phone country dropdown is tracked
    const validator = new FormValidator('editUserForm', {
        name:         { required: true, minLength: 2, maxLength: 100 },
        email:        { required: true, email: true },
        phone:        { phone: true },
        address_line1:{ minLength: 3 }
    });

    document.addEventListener('DOMContentLoaded', function() {
        // Populate country dropdown
        if (typeof COUNTRY_CODES !== 'undefined') {
            const sel = document.getElementById('country');
            const selectedCountry = sel.getAttribute('data-selected') || '';
            COUNTRY_CODES.forEach(c => {
                const opt = document.createElement('option');
                const val = c.name || c.code || c;
                opt.value = val;
                opt.textContent = val;
                if (val.toLowerCase() === selectedCountry.toLowerCase()) {
                    opt.selected = true;
                }
                sel.appendChild(opt);
            });
        }
        const roleSelect = document.getElementById('role');
        const companyFieldWrapper = document.getElementById('companyFieldWrapper');
        const outletFieldWrapper = document.getElementById('outletFieldWrapper');
        const outletSelect = document.getElementById('outlet_id');
        const companyRoles = ['driver', 'company_manager', 'company_admin', 'outlet_manager'];
        const outletRoles = ['outlet_manager'];
        const currentRole = '<?php echo addslashes($user['role'] ?? ''); ?>';
        const currentCompanyId = '<?php echo addslashes($user['company_id'] ?? ''); ?>';
        const currentOutletId = '<?php echo addslashes($user['outlet_id'] ?? ''); ?>';

        // Load roles from DB
        fetch('../api/get_roles.php')
            .then(r => r.json())
            .then(data => {
                if (Array.isArray(data)) {
                    data.forEach(role => {
                        const opt = document.createElement('option');
                        opt.value = role.name;
                        opt.textContent = role.name.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
                        if (role.name === currentRole) opt.selected = true;
                        roleSelect.appendChild(opt);
                    });
                    // Trigger initial visibility
                    updateConditionalFields(currentRole);
                }
            })
            .catch(() => {
                ['super_admin', 'company_admin', 'outlet_manager', 'driver'].forEach(r => {
                    const opt = document.createElement('option');
                    opt.value = r;
                    opt.textContent = r.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
                    if (r === currentRole) opt.selected = true;
                    roleSelect.appendChild(opt);
                });
                updateConditionalFields(currentRole);
            });

        function updateConditionalFields(role) {
            const r = role.toLowerCase();
            if (companyRoles.includes(r)) {
                companyFieldWrapper.classList.add('visible');
            } else {
                companyFieldWrapper.classList.remove('visible');
            }
            if (outletRoles.includes(r)) {
                outletFieldWrapper.classList.add('visible');
                // Load outlets if company is set
                if (currentCompanyId) loadOutlets(currentCompanyId, currentOutletId);
            } else {
                outletFieldWrapper.classList.remove('visible');
            }
        }

        roleSelect.addEventListener('change', function() {
            updateConditionalFields(this.value);
            // Reset outlet when role changes
            outletSelect.innerHTML = '<option value="">Select a company first</option>';
        });

        // Load outlets when company changes
        document.getElementById('company_id').addEventListener('change', function() {
            const selectedRole = roleSelect.value.toLowerCase();
            if (outletRoles.includes(selectedRole)) {
                loadOutlets(this.value);
            }
        });

        async function loadOutlets(companyId, preselectId) {
            outletSelect.innerHTML = '<option value="">Loading outlets...</option>';
            if (!companyId) {
                outletSelect.innerHTML = '<option value="">Select a company first</option>';
                return;
            }
            try {
                const res = await fetch('../api/get_outlets_by_company.php?company_id=' + encodeURIComponent(companyId));
                const json = await res.json();
                outletSelect.innerHTML = '<option value="">Select Outlet</option>';
                if (json.success && Array.isArray(json.data)) {
                    if (json.data.length === 0) {
                        outletSelect.innerHTML = '<option value="">No outlets found</option>';
                    } else {
                        json.data.forEach(o => {
                            const opt = document.createElement('option');
                            opt.value = o.id;
                            opt.textContent = o.outlet_name;
                            if (preselectId && o.id === preselectId) opt.selected = true;
                            outletSelect.appendChild(opt);
                        });
                    }
                }
            } catch (err) {
                outletSelect.innerHTML = '<option value="">Failed to load outlets</option>';
            }
        }

        // Build address string
        function buildAddress() {
            const parts = [];
            const a1 = document.getElementById('address_line1').value.trim(); if (a1) parts.push(a1);
            const city = document.getElementById('city').value.trim(); if (city) parts.push(city);
            const state = document.getElementById('state').value.trim(); if (state) parts.push(state);
            const postal = document.getElementById('postal_code').value.trim(); if (postal) parts.push(postal);
            const country = document.getElementById('country').value.trim(); if (country) parts.push(country);
            return parts.join(', ');
        }

        // Form submission
        document.getElementById('editUserForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            // Run field-level validation first (checks phone is correct local digits, email format, etc.)
            if (!validator.validateAll()) return;

            const selectedRole = roleSelect.value.toLowerCase();

            // Validate company for roles that need it
            if (companyRoles.includes(selectedRole) && !document.getElementById('company_id').value) {
                Swal.fire({ icon: 'warning', title: 'Company Required', text: 'Please select a company for this role.', confirmButtonColor: '#2e0d2a' });
                return;
            }
            // Validate outlet for outlet_manager
            if (outletRoles.includes(selectedRole) && !document.getElementById('outlet_id').value) {
                Swal.fire({ icon: 'warning', title: 'Outlet Required', text: 'Please select an outlet for this role.', confirmButtonColor: '#2e0d2a' });
                return;
            }

            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving...';

            try {
                // Build full phone number with country code
                let fullPhone = '';
                const phoneDigits = document.getElementById('phone').value.replace(/\s/g, '');
                if (phoneDigits) {
                    const selectedCountryCode = validator._phoneCountrySelections['phone'] || 'ZM';
                    const country = COUNTRY_CODES.find(c => c.code === selectedCountryCode);
                    fullPhone = country ? country.dial + phoneDigits : '+260' + phoneDigits;
                }

                const response = await fetch('../api/update_user.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.getElementById('csrf_token').value
                    },
                    body: JSON.stringify({
                        id: document.getElementById('user_id').value,
                        full_name: document.getElementById('name').value,
                        email: document.getElementById('email').value,
                        phone: fullPhone || null,
                        role: roleSelect.value,
                        company_id: document.getElementById('company_id').value || null,
                        outlet_id: document.getElementById('outlet_id').value || null,
                        address: document.getElementById('address_line1').value.trim(),
                        city: document.getElementById('city').value.trim(),
                        state: document.getElementById('state').value.trim(),
                        postal_code: document.getElementById('postal_code').value.trim(),
                        country: document.getElementById('country').value,
                        status: document.querySelector('input[name="status"]:checked')?.value || 'active'
                    })
                });
                const result = await response.json();

                if (result.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Updated!',
                        text: 'User profile updated successfully.',
                        confirmButtonColor: '#2e0d2a',
                        timer: 2000,
                        timerProgressBar: true
                    }).then(() => {
                        window.location.href = 'view-user.php?id=' + encodeURIComponent(document.getElementById('user_id').value);
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: result.error || 'Failed to update user', confirmButtonColor: '#2e0d2a' });
                }
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'Connection Error', text: 'Could not connect to the server.', confirmButtonColor: '#2e0d2a' });
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Save Changes';
            }
        });
    });
    </script>

    <!-- Suspension Modal -->
    <div id="suspensionModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0, 0, 0, 0.5); z-index: 1000; justify-content: center; align-items: center;">
        <div style="background: white; border-radius: 0.5rem; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2); max-width: 500px; width: 90%; padding: 2rem;">
            <h2 style="margin-top: 0; margin-bottom: 1rem; color: #1f2937;">Suspend User</h2>
            <p style="color: #666; margin-bottom: 1.5rem;">Enter a reason for suspending this user. The user will be notified via email.</p>
            
            <form id="suspensionForm" style="display: flex; flex-direction: column; gap: 1rem;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="suspensionReason">Suspension Reason <span class="required">*</span></label>
                    <textarea id="suspensionReason" name="suspension_reason" class="form-input-field" 
                              style="min-height: 120px; font-family: inherit; resize: vertical;" 
                              placeholder="Enter the reason for suspension..." required></textarea>
                </div>

                <div style="display: flex; gap: 1rem;">
                    <button type="button" id="cancelSuspensionBtn" class="submit-btn" style="background-color: #6b7280; cursor: pointer;">Cancel</button>
                    <button type="submit" class="submit-btn" style="background-color: #dc2626; cursor: pointer;">Confirm Suspension</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    // Suspension management
    document.addEventListener('DOMContentLoaded', function() {
        const userId = document.getElementById('user_id').value;
        const userStatus = "<?php echo htmlspecialchars($user['status'] ?? 'Active'); ?>";
        const suspendedAt = "<?php echo htmlspecialchars($user['suspended_at'] ?? ''); ?>";
        const suspendedBy = "<?php echo htmlspecialchars($user['suspended_by'] ?? ''); ?>";
        const suspensionReason = "<?php echo htmlspecialchars($user['suspension_reason'] ?? ''); ?>";

        const suspendBtn = document.getElementById('suspendBtn');
        const unsuspendBtn = document.getElementById('unsuspendBtn');
        const suspensionModal = document.getElementById('suspensionModal');
        const suspensionForm = document.getElementById('suspensionForm');
        const cancelSuspensionBtn = document.getElementById('cancelSuspensionBtn');
        const suspensionInfo = document.getElementById('suspensionInfo');
        const statusDisplay = document.getElementById('statusDisplay');

        // Update UI based on user status
        function updateSuspensionUI() {
            if (userStatus === 'Suspended') {
                suspendBtn.style.display = 'none';
                unsuspendBtn.style.display = 'block';
                suspensionInfo.style.display = 'block';

                // Fetch suspension details from database
                fetch('../api/get_user_suspension_details.php?user_id=' + encodeURIComponent(userId))
                    .then(r => r.json())
                    .then(data => {
                        if (data.success && data.suspension) {
                            const susp = data.suspension;
                            document.getElementById('suspendedAtDisplay').textContent = new Date(susp.suspended_at).toLocaleString();
                            document.getElementById('suspendedByDisplay').textContent = susp.suspended_by_name || susp.suspended_by_email || 'Unknown Admin';
                            document.getElementById('suspensionReasonDisplay').textContent = susp.suspension_reason || 'No reason provided';
                        }
                    })
                    .catch(err => console.error('Error fetching suspension details:', err));
            } else {
                suspendBtn.style.display = 'block';
                unsuspendBtn.style.display = 'none';
                suspensionInfo.style.display = 'none';
            }
        }

        // Initialize UI on page load
        updateSuspensionUI();

        // Suspend button click handler
        suspendBtn.addEventListener('click', function() {
            document.getElementById('suspensionReason').value = '';
            suspensionModal.style.display = 'flex';
        });

        // Unsuspend button click handler
        unsuspendBtn.addEventListener('click', async function() {
            const confirmed = await Swal.fire({
                icon: 'warning',
                title: 'Unsuspend User?',
                text: 'This will reactivate the user account and they will be able to log in again.',
                showCancelButton: true,
                confirmButtonColor: '#16a34a',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, Unsuspend',
                cancelButtonText: 'Cancel'
            });

            if (confirmed.isConfirmed) {
                try {
                    const response = await fetch('../api/unsuspend_user.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            user_id: userId,
                            unsuspension_reason: 'Unsuspended by admin'
                        })
                    });

                    const result = await response.json();

                    if (result.success) {
                        await Swal.fire({
                            icon: 'success',
                            title: 'Unsuspended!',
                            text: 'User account has been reactivated.',
                            confirmButtonColor: '#2e0d2a'
                        });
                        location.reload();
                    } else {
                        await Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: result.error || 'Failed to unsuspend user',
                            confirmButtonColor: '#2e0d2a'
                        });
                    }
                } catch (error) {
                    await Swal.fire({
                        icon: 'error',
                        title: 'Connection Error',
                        text: error.message,
                        confirmButtonColor: '#2e0d2a'
                    });
                }
            }
        });

        // Cancel button in modal
        cancelSuspensionBtn.addEventListener('click', function() {
            suspensionModal.style.display = 'none';
        });

        // Close modal when clicking outside
        suspensionModal.addEventListener('click', function(e) {
            if (e.target === suspensionModal) {
                suspensionModal.style.display = 'none';
            }
        });

        // Form submission for suspension
        suspensionForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const reason = document.getElementById('suspensionReason').value.trim();
            if (!reason) {
                await Swal.fire({
                    icon: 'warning',
                    title: 'Required Field',
                    text: 'Please enter a suspension reason.',
                    confirmButtonColor: '#2e0d2a'
                });
                return;
            }

            try {
                const response = await fetch('../api/suspend_user.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        user_id: userId,
                        suspension_reason: reason
                    })
                });

                const result = await response.json();

                if (result.success) {
                    suspensionModal.style.display = 'none';
                    await Swal.fire({
                        icon: 'success',
                        title: 'Suspended!',
                        text: 'User account has been suspended. All active sessions have been terminated.',
                        confirmButtonColor: '#2e0d2a'
                    });
                    location.reload();
                } else {
                    await Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: result.error || 'Failed to suspend user',
                        confirmButtonColor: '#2e0d2a'
                    });
                }
            } catch (error) {
                await Swal.fire({
                    icon: 'error',
                    title: 'Connection Error',
                    text: error.message,
                    confirmButtonColor: '#2e0d2a'
                });
            }
        });
    });
    </script>
