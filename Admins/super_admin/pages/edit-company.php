<?php
session_start();
require_once __DIR__ . '/../api/supabase-client.php';

// Check if user is logged in and has super_admin role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: login.php');
    exit;
}

// Get company ID from URL parameter
$companyId = isset($_GET['id']) ? $_GET['id'] : null;

if (!$companyId) {
    header('Location: companies.php');
    exit;
}

renderForm:
try {
    // Fetch company details from Supabase
    $company = callSupabase("companies?id=eq.{$companyId}&select=*");
    
    if (!$company || empty($company)) {
        throw new Exception('Company not found');
    }
    
    // Get the first company since we're querying by ID
    $company = $company[0];
    
} catch (Exception $e) {
    header('Location: companies.php?error=' . urlencode($e->getMessage()));
    exit;
}

// Include currency helper functions
require_once __DIR__ . '/../includes/currency-helper.php';

$pageTitle = 'Admin - Edit-Company';
require_once __DIR__ . '/../includes/header.php';
?>

    <div class="mobile-dashboard">
        <!-- Main Content Area -->
        <main class="main-content">
            <div class="content-header">
                <h1>Edit Company</h1>
            </div>

            <div class="edit-form-container">
                <form id="editCompanyForm" novalidate>
                    <input type="hidden" id="csrf_token" value="<?php echo CSRFHelper::getToken(); ?>">
                    <input type="hidden" id="company_id" value="<?php echo htmlspecialchars($companyId); ?>">
                    
                    <div class="form-group">
                        <label for="company_name">Company Name <span class="required">*</span></label>
                        <input type="text" id="company_name" name="company_name" class="form-input-field" 
                               placeholder="e.g., Swift Logistics Inc." 
                               minlength="2" maxlength="100" required
                               value="<?php echo htmlspecialchars($company['company_name'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="subdomain">Subdomain <span class="required">*</span></label>
                        <input type="text" id="subdomain" name="subdomain" class="form-input-field" 
                               placeholder="e.g., swiftlogistics" 
                               minlength="3" maxlength="50" required
                               value="<?php echo htmlspecialchars($company['subdomain'] ?? ''); ?>">
                        <small class="help-text">Lowercase letters and numbers only.</small>
                    </div>

                    <div class="form-group">
                        <label for="contact_person">Contact Person <span class="required">*</span></label>
                        <input type="text" id="contact_person" name="contact_person" class="form-input-field" 
                               placeholder="e.g., Jane Doe" 
                               minlength="2" maxlength="100" required
                               value="<?php echo htmlspecialchars($company['contact_person'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="contact_email">Contact Email <span class="required">*</span></label>
                        <input type="email" id="contact_email" name="contact_email" class="form-input-field" 
                               placeholder="e.g., jane.doe@company.com" required
                               value="<?php echo htmlspecialchars($company['contact_email'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="contact_phone">Contact Phone <span class="required">*</span></label>
                        <input type="tel" id="contact_phone" name="contact_phone" class="form-input-field" 
                               placeholder="+260 XXX XXX XXX" required
                               value="<?php echo htmlspecialchars(substr($company['contact_phone'] ?? '', -9)); ?>">
                    </div>

                    <div class="form-group">
                        <input type="hidden" id="address" name="address" value="<?php echo htmlspecialchars($company['address'] ?? ''); ?>">
                        <label for="address_line1">Address Line 1 <span class="required">*</span></label>
                        <input type="text" id="address_line1" name="address_line1" class="form-input-field" 
                               placeholder="Street number and street name" minlength="5" maxlength="500" required
                               value="<?php echo htmlspecialchars($company['address'] ?? ''); ?>">
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label for="city">City / Town</label>
                            <input type="text" id="city" name="city" class="form-input-field" value="<?php echo htmlspecialchars($company['city'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="state">State / Province</label>
                            <input type="text" id="state" name="state" class="form-input-field" value="<?php echo htmlspecialchars($company['state'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label for="postal_code">Postal Code</label>
                            <input type="text" id="postal_code" name="postal_code" class="form-input-field" value="<?php echo htmlspecialchars($company['postal_code'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="country">Country</label>
                            <select id="country" name="country" class="form-input-field" data-selected="<?php echo htmlspecialchars($company['country'] ?? ''); ?>">
                                <option value="">Select Country</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="commission_rate">Commission Rate (%)</label>
                        <input type="number" id="commission_rate" name="commission_rate" class="form-input-field" 
                               placeholder="0.00" step="0.01" min="0" max="100" 
                               value="<?php echo htmlspecialchars($company['commission_rate'] ?? 0); ?>">
                        <small class="help-text">Commission rate percentage for this company (0-100)</small>
                    </div>

                    <div class="form-group">
                        <label for="status">Status <span class="required">*</span></label>
                        <select id="status" name="status" class="form-select-field" required>
                            <option value="active" <?php echo (($company['status'] ?? '') === 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo (($company['status'] ?? '') === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                            <option value="suspended" <?php echo (($company['status'] ?? '') === 'suspended') ? 'selected' : ''; ?>>Suspended</option>
                        </select>
                    </div>

                    <div class="button-group">
                        <button type="button" class="secondary-btn" id="cancelBtn">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="primary-btn" id="saveChangesBtn">
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
        const validator = new FormValidator('editCompanyForm', {
            company_name:    { required: true, minLength: 2, maxLength: 100 },
            subdomain:       { required: true, minLength: 3, maxLength: 50, subdomain: true },
            contact_person:  { required: true, minLength: 2, maxLength: 100 },
            contact_email:   { required: true, email: true },
            contact_phone:   { required: true, phone: true },
            address_line1:   { required: true, minLength: 5, maxLength: 500 },
            city:            { minLength: 2 },
            country:         { required: true },
            status:          { required: true }
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

        function buildHiddenAddress() {
            const parts = [];
            const a1 = document.getElementById('address_line1').value.trim(); if (a1) parts.push(a1);
            const city = document.getElementById('city').value.trim(); if (city) parts.push(city);
            const state = document.getElementById('state') ? document.getElementById('state').value.trim() : '' ; if (state) parts.push(state);
            const postal = document.getElementById('postal_code') ? document.getElementById('postal_code').value.trim() : '' ; if (postal) parts.push(postal);
            const country = document.getElementById('country').value; if (country) parts.push(country);
            document.getElementById('address').value = parts.join(', ');
        }

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

            // Fallback heuristic if normalization fails
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

        document.getElementById('editCompanyForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            if (!validator.validateAll()) return;

            // build hidden address from split fields
            buildHiddenAddress();

            const saveBtn = document.getElementById('saveChangesBtn');
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';
            const companyId = document.getElementById('company_id').value;

            try {
                let fullPhone = '';
                const phoneVal = document.getElementById('contact_phone').value.replace(/\s/g, '');
                if (phoneVal) {
                    const selectedCountry = validator._phoneCountrySelections['contact_phone'] || 'ZM';
                    const country = COUNTRY_CODES.find(c => c.code === selectedCountry);
                    fullPhone = country ? country.dial + phoneVal : '+260' + phoneVal;
                }

                const response = await fetch('../api/update_company.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.getElementById('csrf_token').value
                    },
                    body: JSON.stringify({
                        id: companyId,
                        company_name: document.getElementById('company_name').value.trim(),
                        subdomain: document.getElementById('subdomain').value.trim().toLowerCase(),
                        contact_person: document.getElementById('contact_person').value.trim(),
                        contact_email: document.getElementById('contact_email').value.trim(),
                        contact_phone: fullPhone,
                        address: document.getElementById('address_line1').value.trim(),
                        city: document.getElementById('city').value.trim(),
                        state: document.getElementById('state') ? document.getElementById('state').value.trim() : '',
                        postal_code: document.getElementById('postal_code') ? document.getElementById('postal_code').value.trim() : '',
                        country: document.getElementById('country').value,
                        // Commission rate stored as decimal
                        commission_rate: parseFloat(document.getElementById('commission_rate').value) || 0,
                        status: document.getElementById('status').value
                    })
                });

                const result = await response.json();

                if (result.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: 'Company updated successfully!',
                        confirmButtonColor: '#2e0d2a',
                        timer: 2500,
                        timerProgressBar: true
                    }).then(() => {
                        window.location.href = `view-company.php?id=${encodeURIComponent(companyId)}`;
                    });
                } else {
                    if (result.errors) {
                        validator.applyServerErrors(result.errors);
                    }
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: result.error || 'Failed to update company',
                        confirmButtonColor: '#2e0d2a'
                    });
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Connection Error',
                    text: 'Could not connect to the server.',
                    confirmButtonColor: '#2e0d2a'
                });
            } finally {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save Changes';
            }
        });

        // parse stored address into fields on load
        document.addEventListener('DOMContentLoaded', function() {
            parseStoredAddress();
        });

        document.getElementById('cancelBtn').addEventListener('click', function() {
            window.history.back();
        });
    </script>

    <style>
        .edit-form-container {
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin: 20px;
        }

        .edit-form {
            display: grid;
            gap: 20px;
            max-width: 800px;
            margin: 0 auto;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-weight: 500;
            color: #374151;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 10px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .button-group {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .primary-btn,
        .secondary-btn {
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .primary-btn {
            background-color: #2E0D2A;
            color: white;
            border: none;
        }

        .secondary-btn {
            background-color: #f3f4f6;
            color: #374151;
            border: 1px solid #d1d5db;
        }

        .primary-btn:hover {
            background-color: #1d4ed8;
        }

        .secondary-btn:hover {
            background-color: #e5e7eb;
        }

        .alert {
            padding: 12px;
            border-radius: 6px;
            margin: 20px;
        }

        .alert-error {
            background-color: #fee2e2;
            border: 1px solid #ef4444;
            color: #b91c1c;
        }
    </style>
<?php include __DIR__ . '/../includes/footer.php'; ?>
