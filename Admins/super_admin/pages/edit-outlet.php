<?php
session_start();


if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: login.php');
    exit;
}

require_once dirname(__DIR__) . '/api/supabase-client.php';

$outlet_id = $_GET['id'] ?? null;
if (!$outlet_id) {
    header('Location: outlets.php');
    exit;
}

// Fetch outlet data
$outlet = callSupabaseWithServiceKey("outlets?id=eq.{$outlet_id}", 'GET');
if (empty($outlet)) {
    die('Outlet not found.');
}
$outlet = $outlet[0];

// Fetch companies for the dropdown
$companies = callSupabaseWithServiceKey('companies', 'GET');

$pageTitle = 'Admin - Edit-Outlet';
require_once '../includes/header.php';
?>

    <div class="mobile-dashboard">
        <main class="main-content">
            <div class="content-header">
                <h1>Edit Outlet</h1>
            </div>

            <div class="form-container">
                <h2>Outlet Details</h2>
                <form id="editOutletForm" novalidate>
                    <input type="hidden" id="csrf_token" value="<?php echo CSRFHelper::getToken(); ?>">
                    <input type="hidden" id="outletId" value="<?php echo htmlspecialchars($outlet['id']); ?>">
                    <div class="form-group">
                        <label for="outletName">Outlet Name <span class="required">*</span></label>
                        <input type="text" id="outletName" name="outletName" class="form-input-field" placeholder="e.g., Downtown Branch" required value="<?php echo htmlspecialchars($outlet['outlet_name']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="company">Associated Company <span class="required">*</span></label>
                        <select id="company" name="company" class="form-input-field" required>
                            <option value="">Select a Company</option>
                            <?php foreach ($companies as $company): ?>
                                <option value="<?php echo htmlspecialchars($company['id']); ?>" <?php echo ($outlet['company_id'] == $company['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($company['company_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="contactPerson">Contact Person <span class="required">*</span></label>
                        <input type="text" id="contactPerson" name="contactPerson" class="form-input-field" placeholder="e.g., John Smith" required value="<?php echo htmlspecialchars($outlet['contact_person']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="contactEmail">Contact Email <span class="required">*</span></label>
                        <input type="email" id="contactEmail" name="contactEmail" class="form-input-field" placeholder="e.g., john.smith@outlet.com" required value="<?php echo htmlspecialchars($outlet['contact_email']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="contactPhone">Contact Phone</label>
                        <input type="tel" id="contactPhone" name="contactPhone" class="form-input-field" value="<?php echo htmlspecialchars(substr($outlet['contact_phone'] ?? '', -9)); ?>">
                    </div>
                    <div class="form-group">
                        <input type="hidden" id="address" name="address" value="<?php echo htmlspecialchars($outlet['address'] ?? ''); ?>">
                        <label for="address_line1">Address Line 1 <span class="required">*</span></label>
                        <input type="text" id="address_line1" name="address_line1" class="form-input-field" placeholder="Street number and street name" required value="<?php echo htmlspecialchars($outlet['address'] ?? ''); ?>">
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label for="city">City / Town</label>
                            <input type="text" id="city" name="city" class="form-input-field" value="<?php echo htmlspecialchars($outlet['city'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="state">State / Province</label>
                            <input type="text" id="state" name="state" class="form-input-field" value="<?php echo htmlspecialchars($outlet['state'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label for="postal_code">Postal Code</label>
                            <input type="text" id="postal_code" name="postal_code" class="form-input-field" value="<?php echo htmlspecialchars($outlet['postal_code'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="country">Country</label>
                            <select id="country" name="country" class="form-input-field" data-selected="<?php echo htmlspecialchars($outlet['country'] ?? ''); ?>">
                                <option value="">Select Country</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="form-input-field">
                            <option value="active" <?php echo ($outlet['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($outlet['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                            <option value="maintenance" <?php echo ($outlet['status'] == 'maintenance') ? 'selected' : ''; ?>>Maintenance</option>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn cancel-btn" id="cancelBtn">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn save-btn" id="saveChangesBtn">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <!-- Form Validator -->
    <script src="../assets/js/form-validator.js?v=2"></script>
    <script>
        const validator = new FormValidator('editOutletForm', {
            outletName:    { required: true, minLength: 2, maxLength: 100 },
            company:       { required: true },
            contactPerson: { required: true, minLength: 2, maxLength: 100 },
            contactEmail:  { required: true, email: true },
            contactPhone:  { phone: true },
            address_line1: { required: true, minLength: 5, maxLength: 500 },
            country:       { required: true },
            status:        { required: true }
        });

        (function populateCountries() {
            if (typeof COUNTRY_CODES === 'undefined') return;
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
        })();

        async function parseStoredAddress() {
            const raw = (document.getElementById('address').value || '').trim();
            if (!raw) return;
            try {
                const resp = await fetch('../api/normalize_address.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ address: raw })
                });
                const data = await resp.json();
                if (data && data.success) {
                    if (data.address_line1) document.getElementById('address_line1').value = data.address_line1;
                    if (data.city) document.getElementById('city').value = data.city;
                    if (data.state) document.getElementById('state').value = data.state;
                    if (data.postal_code) document.getElementById('postal_code').value = data.postal_code;
                    if (data.country) {
                        const sel = document.getElementById('country');
                        for (let i=0;i<sel.options.length;i++) {
                            if (sel.options[i].textContent.toLowerCase() === data.country.toLowerCase()) {
                                sel.selectedIndex = i; break;
                            }
                        }
                    }
                    return;
                }
            } catch (e) {
                // fall through to heuristic fallback
            }

            // Fallback heuristic
            const tokens = raw.split(',').map(t => t.trim()).filter(Boolean);
            if (tokens.length > 0) document.getElementById('address_line1').value = tokens[0] || document.getElementById('address_line1').value;
            if (tokens.length > 1) document.getElementById('city').value = tokens[1] || '';
            if (tokens.length > 2) {
                const t2 = tokens[2];
                if (/^[0-9A-Z \-]{3,10}$/i.test(t2) && tokens.length === 3) {
                    document.getElementById('postal_code').value = t2;
                } else {
                    document.getElementById('state').value = t2;
                }
            }
            if (tokens.length > 3) document.getElementById('postal_code').value = tokens[3] || document.getElementById('postal_code').value;
            const last = raw.split(',').map(t=>t.trim()).filter(Boolean).pop();
            const sel = document.getElementById('country');
            for (let i=0;i<sel.options.length;i++) {
                if (sel.options[i].textContent.toLowerCase() === (last||'').toLowerCase()) {
                    sel.selectedIndex = i; break;
                }
            }
        }

        // parse on load
        document.addEventListener('DOMContentLoaded', parseStoredAddress);

        function buildHiddenAddress() {
            const parts = [];
            const a1 = document.getElementById('address_line1').value.trim(); if (a1) parts.push(a1);
            const city = document.getElementById('city').value.trim(); if (city) parts.push(city);
            const state = document.getElementById('state').value.trim(); if (state) parts.push(state);
            const postal = document.getElementById('postal_code').value.trim(); if (postal) parts.push(postal);
            const country = document.getElementById('country').value; if (country) parts.push(country);
            document.getElementById('address').value = parts.join(', ');
        }

        document.getElementById('editOutletForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            if (!validator.validateAll()) return;

            buildHiddenAddress();

            const saveBtn = document.getElementById('saveChangesBtn');
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';
            const outletId = document.getElementById('outletId').value;

            try {
                let fullPhone = '';
                const phoneVal = document.getElementById('contactPhone').value.replace(/\s/g, '');
                if (phoneVal) {
                    const selectedCountry = validator._phoneCountrySelections['contactPhone'] || 'ZM';
                    const country = COUNTRY_CODES.find(c => c.code === selectedCountry);
                    fullPhone = country ? country.dial + phoneVal : '+260' + phoneVal;
                }

                const response = await fetch('../api/update_outlet.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.getElementById('csrf_token').value
                    },
                    body: JSON.stringify({
                        id: outletId,
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
                        status: document.getElementById('status').value
                    })
                });
                const result = await response.json();
                if (result.success) {
                    Swal.fire({
                        icon: 'success', title: 'Success!',
                        text: 'Outlet updated successfully!',
                        confirmButtonColor: '#2e0d2a',
                        timer: 2500,
                        timerProgressBar: true
                    }).then(() => {
                        window.location.href = `view-outlet.php?id=${encodeURIComponent(outletId)}`;
                    });
                } else {
                    if (result.errors) validator.applyServerErrors(result.errors);
                    Swal.fire({ icon: 'error', title: 'Error', text: result.error || 'Failed to update outlet', confirmButtonColor: '#2e0d2a' });
                }
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'Connection Error', text: error.message, confirmButtonColor: '#2e0d2a' });
            } finally {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save Changes';
            }
        });

        document.getElementById('cancelBtn').addEventListener('click', function() {
            window.history.back();
        });
    </script>
</body>
</html>
