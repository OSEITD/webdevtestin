<?php
require_once __DIR__ . '/../../auth/session-check.php';
$page_title = 'Settings';
require_once '../includes/header.php';
?>
<body class="bg-gray-100 min-h-screen">

    <div class="mobile-dashboard">

        <!-- Main Settings Content -->
        <main class="main-content">
            <div class="content-header">
                <h1>Settings</h1>
                <p class="subtitle">Manage your app preferences and account</p>
            </div>

            <!-- Settings Tabs -->
            <div class="settings-tabs">
                <button class="tab-btn active" data-tab="profile">Profile</button>
                <button class="tab-btn" data-tab="general">General</button>
            </div>

            <!-- Profile Settings -->
            <div class="settings-tab-content active" id="profile">
                <div class="settings-card">
                    <h3><i class="fas fa-user"></i> Your Profile</h3>
                    <div class="form-group">
                        <label>Name</label>
                        <input id="profileName" type="text" placeholder="Full name" value="<?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input id="profileEmail" type="email" placeholder="name@example.com" value="<?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input id="profilePhone" type="text" placeholder="e.g. +12025550123" value="<?php echo htmlspecialchars($_SESSION['user_phone'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Address Line 1</label>
                        <input id="profileAddress" type="text" placeholder="Address line 1">
                    </div>
                    <div class="grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label>City</label>
                            <input id="profileCity" type="text" placeholder="City">
                        </div>
                        <div class="form-group">
                            <label>State / Province</label>
                            <input id="profileState" type="text" placeholder="State">
                        </div>
                    </div>
                    <div class="grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label>Postal Code</label>
                            <input id="profilePostalCode" type="text" placeholder="Postal Code">
                        </div>
                        <div class="form-group">
                            <label>Country</label>
                            <input id="profileCountry" type="text" placeholder="Country">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Avatar</label>
                        <input id="profileAvatar" type="file" accept="image/*">
                        <div id="avatarPreview" style="margin-top:8px;">
                            <?php if (!empty($_SESSION['user_avatar'])): ?>
                                <img src="<?php echo htmlspecialchars($_SESSION['user_avatar']); ?>" alt="avatar" style="height:72px;border-radius:8px;">
                            <?php endif; ?>
                        </div>
                    </div>
                    <button id="saveProfileBtn" class="save-btn">Save Profile</button>
                    <div id="profileMsg" style="margin-top:8px;color:#666;font-size:0.95rem"></div>
                </div>

                <div class="settings-card">
                    <h3><i class="fas fa-lock"></i> Password</h3>
                    <div class="form-group">
                        <label>Current Password</label>
                        <input id="currentPassword" type="password" placeholder="Enter current password">
                    </div>
                    <div class="form-group">
                        <label>New Password</label>
                        <input id="newPassword" type="password" placeholder="Enter new password">
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input id="confirmNewPassword" type="password" placeholder="Confirm new password">
                    </div>
                    <button id="updatePasswordBtn" class="save-btn">Update Password</button>
                    <div id="passwordMsg" style="margin-top:8px;color:#666;font-size:0.95rem"></div>
                </div>
            </div>

            <!-- General Settings -->
            <div class="settings-tab-content" id="general">
                <div class="settings-card">
                    <h3><i class="fas fa-user-cog"></i> Company</h3>
                    <div class="form-group">
                        <label>Company Name</label>
                        <input id="companyName" type="text" value="WebDev Technologies" placeholder="Enter company name">
                    </div>
                    <div class="form-group">
                        <label>Address Line 1</label>
                        <input id="companyAddress" type="text" placeholder="Address line 1">
                    </div>
                    <div class="grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label>City</label>
                            <input id="companyCity" type="text" placeholder="City">
                        </div>
                        <div class="form-group">
                            <label>State / Province</label>
                            <input id="companyState" type="text" placeholder="State">
                        </div>
                    </div>
                    <div class="grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label>Postal Code</label>
                            <input id="companyPostalCode" type="text" placeholder="Postal Code">
                        </div>
                        <div class="form-group">
                            <label>Country</label>
                            <input id="companyCountry" type="text" placeholder="Country">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Currency</label>
                        <select id="companyCurrency">
                            <option value="USD" selected>USD - US Dollar</option>
                            <option value="EUR">EUR - Euro</option>
                            <option value="GBP">GBP - British Pound</option>
                            <option value="ZMW">ZMW - Zambian Kwacha</option>
                        </select>
                    </div>
                    <button id="saveCompanyBtn" class="save-btn">Save Changes</button>
                </div>

                <div class="settings-card">
                    <h3><i class="fas fa-bell"></i> Alerts</h3>
                    <div class="toggle-group">
                        <label>Delivery Updates</label>
                        <label class="toggle-switch">
                            <input id="notifDeliveryUpdates" type="checkbox" checked>
                            <span class="slider"></span>
                        </label>
                    </div>
                    <div class="toggle-group">
                        <label>New Outlets</label>
                        <label class="toggle-switch">
                            <input id="notifNewOutlets" type="checkbox" checked>
                            <span class="slider"></span>
                        </label>
                    </div>
                    <div class="toggle-group">
                        <label>Urgent Issues</label>
                        <label class="toggle-switch">
                            <input id="notifUrgentIssues" type="checkbox" checked>
                            <span class="slider"></span>
                        </label>
                    </div>
                    <button id="saveNotificationsBtn" class="save-btn">Save Notifications</button>
                </div>
            </div>

            <!-- Keyboard Shortcuts Settings -->
        </main>
    </div>

    <!-- Scripts -->
    <script src="../assets/js/company-scripts.js"></script>
    <script>
        // Tab switching logic
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.settings-tab-content').forEach(c => c.classList.remove('active'));
                
                btn.classList.add('active');
                const tabId = btn.getAttribute('data-tab');
                document.getElementById(tabId).classList.add('active');
            });
        });
    </script>
    <script>
        // Profile form handlers
        const avatarInput = document.getElementById('profileAvatar');
        const avatarPreview = document.getElementById('avatarPreview');
        if (avatarInput) {
            avatarInput.addEventListener('change', function () {
                const f = this.files && this.files[0];
                if (!f) return; 
                const reader = new FileReader();
                reader.onload = function (e) {
                    avatarPreview.innerHTML = `<img src="${e.target.result}" alt="avatar" style="height:72px;border-radius:8px;">`;
                };
                reader.readAsDataURL(f);
            });
        }

        document.getElementById('saveProfileBtn').addEventListener('click', async function () {
            const btn = this; btn.disabled = true; btn.textContent = 'Saving...';
            const name = document.getElementById('profileName').value.trim();
            const email = document.getElementById('profileEmail').value.trim();
            const phone = document.getElementById('profilePhone').value.trim();
            const address = document.getElementById('profileAddress').value.trim();
            const city = document.getElementById('profileCity').value.trim();
            const state = document.getElementById('profileState').value.trim();
            const postal_code = document.getElementById('profilePostalCode').value.trim();
            const country = document.getElementById('profileCountry').value.trim();
            // Support multiple password input IDs: profileNewPassword (legacy) or newPassword (security tab)
            const pwdEl = document.getElementById('profileNewPassword') || document.getElementById('newPassword');
            const password = pwdEl ? pwdEl.value : '';
            const file = avatarInput && avatarInput.files && avatarInput.files[0];
            const msgEl = document.getElementById('profileMsg');

            try {
                let resp;
                if (file) {
                    const fd = new FormData();
                    fd.append('name', name);
                    fd.append('email', email);
                    fd.append('phone', phone);
                    if (password) fd.append('password', password);
                    fd.append('address', address);
                    fd.append('city', city);
                    fd.append('state', state);
                    fd.append('postal_code', postal_code);
                    fd.append('country', country);
                    fd.append('avatar', file);
                    const r = await fetch('../api/update_user_profile.php', { method: 'POST', credentials: 'same-origin', body: fd });
                    resp = await r.json().catch(() => ({ success: false, error: 'Invalid response' }));
                } else {
                    const r = await fetch('../api/update_user_profile.php', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ name, email, phone, password, address, city, state, postal_code, country }) });
                    const text = await r.text();
                    try { resp = JSON.parse(text); } catch (e) { resp = { success: false, error: text }; }
                }

                if (resp && resp.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Profile Updated!',
                        text: 'Your profile has been updated successfully.',
                        confirmButtonColor: '#2e0d2a',
                        timer: 2500,
                        timerProgressBar: true
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: resp?.error || 'Failed to update profile',
                        confirmButtonColor: '#2e0d2a'
                    });
                }
            } catch (err) {
                Swal.fire({
                    icon: 'error',
                    title: 'Connection Error',
                    text: err.message || 'Network error',
                    confirmButtonColor: '#2e0d2a'
                });
            }
            btn.textContent = 'Save Profile'; btn.disabled = false;
        });
    </script>
    <script>
        // Fetch existing profile and company settings and populate the form
        (async function populateSettings(){
            try {
                const r = await fetch('../api/fetch_settings.php', { credentials: 'same-origin' });
                if (!r.ok) throw new Error('Failed to fetch');
                const json = await r.json();
                if (!json.success) return;
                const profile = json.data.profile || {};
                const company = json.data.company || {};

                // Profile
                if (profile) {
                    if (profile.full_name) document.getElementById('profileName').value = profile.full_name;
                    if (profile.email) document.getElementById('profileEmail').value = profile.email;
                    if (profile.phone) document.getElementById('profilePhone').value = profile.phone;
                    if (profile.address) document.getElementById('profileAddress').value = profile.address;
                    if (profile.city) document.getElementById('profileCity').value = profile.city;
                    if (profile.state) document.getElementById('profileState').value = profile.state;
                    if (profile.postal_code) document.getElementById('profilePostalCode').value = profile.postal_code;
                    if (profile.country) document.getElementById('profileCountry').value = profile.country;
                    if (profile.avatar_url) {
                        const avatarPreview = document.getElementById('avatarPreview');
                        avatarPreview.innerHTML = `<img src="${profile.avatar_url}" alt="avatar" style="height:72px;border-radius:8px;">`;
                    }
                }

                // Company
                if (company) {
                    if (company.company_name) document.getElementById('companyName').value = company.company_name;
                    if (company.address) document.getElementById('companyAddress').value = company.address;
                    if (company.city) document.getElementById('companyCity').value = company.city;
                    if (company.state) document.getElementById('companyState').value = company.state;
                    if (company.postal_code) document.getElementById('companyPostalCode').value = company.postal_code;
                    if (company.country) document.getElementById('companyCountry').value = company.country;
                    if (company.currency) document.getElementById('companyCurrency').value = company.currency;
                    // notifications stored as json
                    if (company.notifications) {
                        const n = company.notifications;
                        if (typeof n === 'string') {
                            try { n = JSON.parse(n); } catch (e) { /* keep as string */ }
                        }
                        if (n && typeof n === 'object') {
                            document.getElementById('notifDeliveryUpdates').checked = !!n.delivery;
                            document.getElementById('notifNewOutlets').checked = !!n.outlets;
                            document.getElementById('notifUrgentIssues').checked = !!n.urgent;
                        }
                    }
                }
            } catch (err) {
                console.debug('populateSettings error', err);
            }
        })();
    </script>
    <script>
        // Settings save handlers
        async function postJson(url, body) {
            const res = await fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
            const text = await res.text();
            try { return JSON.parse(text); } catch (e) { return { success: false, error: text }; }
        }

        document.getElementById('saveCompanyBtn').addEventListener('click', async function () {
            const btn = this; btn.disabled = true; btn.textContent = 'Saving...';
            const name = document.getElementById('companyName').value.trim();
            const currency = document.getElementById('companyCurrency').value;
            const address = document.getElementById('companyAddress').value.trim();
            const city = document.getElementById('companyCity').value.trim();
            const state = document.getElementById('companyState').value.trim();
            const postal_code = document.getElementById('companyPostalCode').value.trim();
            const country = document.getElementById('companyCountry').value.trim();
            
            const resp = await postJson('../api/update_company_settings.php', { company_name: name, currency, address, city, state, postal_code, country });
            if (resp && resp.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Settings Saved!',
                    text: 'Company settings updated successfully.',
                    confirmButtonColor: '#2e0d2a',
                    timer: 2500,
                    timerProgressBar: true
                });
                btn.textContent = 'Save Changes'; btn.disabled = false;
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to save settings: ' + (resp?.error || 'Unknown'),
                    confirmButtonColor: '#2e0d2a'
                });
                btn.textContent = 'Save Changes'; btn.disabled = false;
            }
        });

        document.getElementById('saveNotificationsBtn').addEventListener('click', async function () {
            const btn = this; btn.disabled = true; btn.textContent = 'Saving...';
            const delivery = document.getElementById('notifDeliveryUpdates').checked;
            const outlets = document.getElementById('notifNewOutlets').checked;
            const urgent = document.getElementById('notifUrgentIssues').checked;
            // For now, we persist notifications into company settings as JSON in a notifications field
            const resp = await postJson('../api/update_company_settings.php', { notifications: { delivery, outlets, urgent } });
            if (resp && resp.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Notifications Saved!',
                    text: 'Notification preferences updated.',
                    confirmButtonColor: '#2e0d2a',
                    timer: 2500,
                    timerProgressBar: true
                });
                btn.textContent = 'Save Notifications'; btn.disabled = false;
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to save notifications: ' + (resp?.error || 'Unknown'),
                    confirmButtonColor: '#2e0d2a'
                });
                btn.textContent = 'Save Notifications'; btn.disabled = false;
            }
        });

        document.getElementById('updatePasswordBtn').addEventListener('click', async function () {
            const btn = this; btn.disabled = true; btn.textContent = 'Updating...';
            const current = document.getElementById('currentPassword').value;
            const nw = document.getElementById('newPassword').value;
            const confirm = document.getElementById('confirmNewPassword').value;
            const resp = await postJson('../api/update_password.php', { current, new: nw, confirm });
            const msgEl = document.getElementById('passwordMsg');
            if (resp && resp.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Password Updated!',
                    text: 'Your password has been changed successfully.',
                    confirmButtonColor: '#2e0d2a',
                    timer: 2500,
                    timerProgressBar: true
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: resp?.error || 'Failed to update password',
                    confirmButtonColor: '#2e0d2a'
                });
            }
            btn.textContent = 'Update Password'; btn.disabled = false;
        });
    </script>
</body>
</html>