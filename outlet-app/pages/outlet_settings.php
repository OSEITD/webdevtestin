<?php
require_once '../includes/auth_guard.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$current_user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Outlet Settings</title>

    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../css/outlet-dashboard.css">
    <link rel="stylesheet" href="../css/settings.css">

    <style>
        .outlet-status-indicator {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 15px;
            padding: 12px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            border-left: 4px solid #10b981;
        }

        .outlet-status-indicator i {
            color: #10b981;
            font-size: 1.2rem;
        }

        .outlet-status-indicator span {
            color: #fff;
            font-weight: 500;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .status-badge.active {
            background-color: #d1fae5;
            color: #065f46;
        }

        .status-badge.inactive {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .form-input-field:invalid {
            border-color: #ef4444;
        }

        .form-input-field:valid {
            border-color: #10b981;
        }

        .validation-message {
            font-size: 0.875rem;
            margin-top: 4px;
            display: none;
        }

        .validation-message.error {
            color: #ef4444;
            display: block;
        }

        .validation-message.success {
            color: #10b981;
            display: block;
        }
    </style>

</head>
<body class="bg-gray-100 min-h-screen">

    <div class="mobile-dashboard">

        <?php include '../includes/navbar.php'; ?>
        <?php include '../includes/sidebar.php'; ?>

        <div class="menu-overlay" id="menuOverlay"></div>

        <main class="main-content">
            <div class="settings-content">
                <div class="settings-header">
                    <h2><i class="fas fa-cog"></i> Outlet Settings</h2>
                    <p class="settings-subtitle">Manage your outlet's profile and preferences</p>
                    <div class="outlet-status-indicator" id="outletStatusIndicator">
                        <i class="fas fa-store"></i>
                        <span id="currentOutletName">Loading...</span>
                        <span class="status-badge active" id="outletStatusBadge">Active</span>
                    </div>
                </div>

                <form class="settings-form" id="outletSettingsForm">
                    <div class="settings-grid">
                        <div class="settings-card">
                            <div class="card-header">
                                <i class="fas fa-store"></i>
                                <h3>Outlet Information</h3>
                            </div>
                            <div class="card-body">
                                <div class="info-group">
                                    <label for="outletName">Outlet Name *</label>
                                    <input type="text" id="outletName" name="outletName" class="form-input-field" placeholder="Enter outlet's full name" required>
                                    <div class="validation-message" id="outletNameMessage"></div>
                                </div>
                                <div class="info-group">
                                    <label for="outletAddress">Outlet Address *</label>
                                    <input type="text" id="outletAddress" name="outletAddress" class="form-input-field" placeholder="Enter outlet's address" required>
                                    <div class="validation-message" id="outletAddressMessage"></div>
                                </div>
                                <div class="info-group">
                                    <label for="contactPerson">Contact Person</label>
                                    <input type="text" id="contactPerson" name="contactPerson" class="form-input-field" placeholder="Enter contact person name">
                                    <div class="validation-message" id="contactPersonMessage"></div>
                                </div>
                                <div class="info-group">
                                    <label for="contactEmail">Contact Email *</label>
                                    <input type="email" id="contactEmail" name="contactEmail" class="form-input-field" placeholder="Enter contact email" required>
                                    <div class="validation-message" id="contactEmailMessage"></div>
                                </div>
                                <div class="info-group">
                                    <label for="contactNumber">Contact Number *</label>
                                    <input type="tel" id="contactNumber" name="contactNumber" class="form-input-field" placeholder="Enter contact number" required>
                                    <div class="validation-message" id="contactNumberMessage"></div>
                                </div>
                            </div>
                        </div>

                        <div class="settings-card">
                            <div class="card-header">
                                <i class="fas fa-clock"></i>
                                <h3>Operating Hours</h3>
                            </div>
                            <div class="card-body">
                                <div class="info-group">
                                    <label for="openingTime">Opening Time</label>
                                    <input type="time" id="openingTime" name="openingTime" class="form-input-field" value="09:00">
                                </div>
                                <div class="info-group">
                                    <label for="closingTime">Closing Time</label>
                                    <input type="time" id="closingTime" name="closingTime" class="form-input-field" value="17:00">
                                </div>
                                <div class="info-group">
                                    <label>Days of Operation</label>
                                    <div class="dropdown" id="daysOfOperationDropdown">
                                        <button class="dropdown-toggle">
                                            <span id="selectedDaysOfOperation">Monday - Friday</span>
                                            <i class="fas fa-chevron-down"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a href="#" data-value="Monday - Friday">Monday - Friday</a>
                                            <a href="#" data-value="Monday - Saturday">Monday - Saturday</a>
                                            <a href="#" data-value="7 Days a Week">7 Days a Week</a>
                                            <a href="#" data-value="Custom">Custom</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="settings-card">
                            <div class="card-header">
                                <i class="fas fa-map-marker-alt"></i>
                                <h3>Location</h3>
                            </div>
                            <div class="card-body">
                                <div class="info-group">
                                    <label for="latitude">Latitude</label>
                                    <input type="number" id="latitude" name="latitude" class="form-input-field" placeholder="Enter latitude" step="0.000001">
                                </div>
                                <div class="info-group">
                                    <label for="longitude">Longitude</label>
                                    <input type="number" id="longitude" name="longitude" class="form-input-field" placeholder="Enter longitude" step="0.000001">
                                </div>
                                <button type="button" class="action-btn full-width" id="getCurrentLocation">
                                    <i class="fas fa-location-arrow"></i> Get Current Location
                                </button>
                            </div>
                        </div>

                        <div class="settings-card notifications-card">
                            <div class="card-header">
                                <i class="fas fa-bell"></i>
                                <h3>Notification Settings</h3>
                            </div>
                            <div class="card-body">
                                <div class="toggle-row">
                                    <div class="toggle-label">
                                        <i class="fas fa-mobile-alt"></i>
                                        <span>New Parcel Notifications</span>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" id="newParcelNotif" checked>
                                        <span class="slider round"></span>
                                    </label>
                                </div>

                                <div class="toggle-row">
                                    <div class="toggle-label">
                                        <i class="fas fa-truck-loading"></i>
                                        <span>Dispatch Reminders</span>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" id="dispatchReminders" checked>
                                        <span class="slider round"></span>
                                    </label>
                                </div>

                                <div class="toggle-row">
                                    <div class="toggle-label">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <span>Urgent Alerts</span>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" id="urgentAlerts" checked>
                                        <span class="slider round"></span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="settings-card security-card">
                            <div class="card-header">
                                <i class="fas fa-shield-alt"></i>
                                <h3>Security</h3>
                            </div>
                            <div class="card-body">
                                <button type="button" class="action-btn full-width" id="changePasswordBtn">
                                    <i class="fas fa-key"></i> Change Password
                                </button>

                                <button type="button" class="action-btn full-width" id="manageUsersBtn">
                                    <i class="fas fa-users-cog"></i> Manage Users
                                </button>

                                <div class="dropdown-group">
                                    <label>Session Timeout</label>
                                    <div class="dropdown" id="sessionTimeoutDropdown">
                                        <button class="dropdown-toggle">
                                            <span id="selectedSessionTimeout">30 Minutes</span>
                                            <i class="fas fa-chevron-down"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a href="#" data-value="15 Minutes">15 Minutes</a>
                                            <a href="#" data-value="30 Minutes">30 Minutes</a>
                                            <a href="#" data-value="1 Hour">1 Hour</a>
                                            <a href="#" data-value="Never">Never</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn cancel-btn" id="cancelSettingsBtn">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn save-btn" id="saveSettingsBtn">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        const menuBtn = document.getElementById('menuBtn');
        const closeMenu = document.getElementById('closeMenu');
        const sidebar = document.getElementById('sidebar');
        const menuOverlay = document.getElementById('menuOverlay');

        const outletNameInput = document.getElementById('outletName');
        const outletAddressInput = document.getElementById('outletAddress');
        const contactPersonInput = document.getElementById('contactPerson');
        const contactEmailInput = document.getElementById('contactEmail');
        const contactNumberInput = document.getElementById('contactNumber');
        const latitudeInput = document.getElementById('latitude');
        const longitudeInput = document.getElementById('longitude');
        const saveSettingsBtn = document.getElementById('saveSettingsBtn');
        const cancelSettingsBtn = document.getElementById('cancelSettingsBtn');

        function toggleMenu() {
            sidebar.classList.toggle('show');
            menuOverlay.classList.toggle('show');
            document.body.style.overflow = sidebar.classList.contains('show') ? 'hidden' : '';
        }

        menuBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleMenu();
        });

        closeMenu.addEventListener('click', toggleMenu);
        menuOverlay.addEventListener('click', toggleMenu);

        document.querySelectorAll('.menu-items a').forEach(item => {
            item.addEventListener('click', toggleMenu);
        });

        function setupRealTimeValidation() {
            outletNameInput.addEventListener('blur', () => validateField(outletNameInput, 'outletNameMessage', 'Outlet name is required'));
            outletAddressInput.addEventListener('blur', () => validateField(outletAddressInput, 'outletAddressMessage', 'Address is required'));
            contactEmailInput.addEventListener('blur', () => validateEmailField(contactEmailInput, 'contactEmailMessage'));
            contactNumberInput.addEventListener('blur', () => validatePhoneField(contactNumberInput, 'contactNumberMessage'));
        }

        function validateField(field, messageId, errorMessage) {
            const messageElement = document.getElementById(messageId);
            if (!field.value.trim()) {
                showValidationMessage(messageElement, errorMessage, 'error');
                return false;
            } else {
                showValidationMessage(messageElement, 'Valid', 'success');
                return true;
            }
        }

        function validateEmailField(field, messageId) {
            const messageElement = document.getElementById(messageId);
            if (!field.value.trim()) {
                showValidationMessage(messageElement, 'Email is required', 'error');
                return false;
            } else if (!isValidEmail(field.value.trim())) {
                showValidationMessage(messageElement, 'Please enter a valid email address', 'error');
                return false;
            } else {
                showValidationMessage(messageElement, 'Valid email', 'success');
                return true;
            }
        }

        function validatePhoneField(field, messageId) {
            const messageElement = document.getElementById(messageId);
            if (!field.value.trim()) {
                showValidationMessage(messageElement, 'Phone number is required', 'error');
                return false;
            } else if (!isValidPhoneNumber(field.value.trim())) {
                showValidationMessage(messageElement, 'Please enter a valid phone number (9-15 digits)', 'error');
                return false;
            } else {
                showValidationMessage(messageElement, 'Valid phone number', 'success');
                return true;
            }
        }

        function showValidationMessage(element, message, type) {
            element.textContent = message;
            element.className = `validation-message ${type}`;
        }

        async function loadOutletDetails() {
            try {
                const response = await fetch('../api/outlets/fetch_outlet_details.php');
                const data = await response.json();

                if (data.success) {
                    const outlet = data.data;
                    outletNameInput.value = outlet.outlet_name || '';
                    outletAddressInput.value = outlet.address || '';
                    contactPersonInput.value = outlet.contact_person || '';
                    contactEmailInput.value = outlet.contact_email || '';
                    contactNumberInput.value = outlet.contact_phone || '';
                    latitudeInput.value = outlet.latitude || '';
                    longitudeInput.value = outlet.longitude || '';

                    document.getElementById('currentOutletName').textContent = outlet.outlet_name || 'Outlet';
                    const statusBadge = document.getElementById('outletStatusBadge');
                    const status = outlet.status || 'active';
                    statusBadge.textContent = status.charAt(0).toUpperCase() + status.slice(1);
                    statusBadge.className = `status-badge ${status}`;
                } else {
                    showMessageBox('Error loading outlet details: ' + data.error, 'error');
                }
            } catch (error) {
                showMessageBox('Error loading outlet details: ' + error.message, 'error');
            }
        }

        async function loadOutletHours() {
            try {
                const response = await fetch('../api/outlets/fetch_outlet_hours.php');
                const data = await response.json();

                if (data.success) {
                    const hours = data.data;
                    document.getElementById('openingTime').value = hours.opening_time || '09:00';
                    document.getElementById('closingTime').value = hours.closing_time || '17:00';
                    document.getElementById('selectedDaysOfOperation').textContent = hours.days_of_operation || 'Monday - Friday';
                }
            } catch (error) {
                console.log('Error loading outlet hours: ' + error.message);
            }
        }

        async function loadNotificationSettings() {
            try {
                const response = await fetch('../api/notifications/fetch_notification_settings.php');
                const data = await response.json();

                if (data.success) {
                    const settings = data.data;
                    document.getElementById('newParcelNotif').checked = settings.notify_parcel !== false;
                    document.getElementById('dispatchReminders').checked = settings.notify_dispatch !== false;
                    document.getElementById('urgentAlerts').checked = settings.notify_urgent !== false;

                    const timeoutValue = settings.session_timeout || 30;
                    let timeoutText = '30 Minutes';
                    if (timeoutValue === 15) timeoutText = '15 Minutes';
                    else if (timeoutValue === 60) timeoutText = '1 Hour';
                    else if (timeoutValue === 0) timeoutText = 'Never';

                    document.getElementById('selectedSessionTimeout').textContent = timeoutText;
                }
            } catch (error) {
                console.log('Error loading notification settings: ' + error.message);
            }
        }

        function validateForm() {
            const errors = [];

            if (!outletNameInput.value.trim()) {
                errors.push('Outlet name is required');
            }

            if (!outletAddressInput.value.trim()) {
                errors.push('Address is required');
            }

            if (!contactEmailInput.value.trim()) {
                errors.push('Contact email is required');
            } else if (!isValidEmail(contactEmailInput.value.trim())) {
                errors.push('Please enter a valid email address');
            }

            if (!contactNumberInput.value.trim()) {
                errors.push('Contact number is required');
            } else if (!isValidPhoneNumber(contactNumberInput.value.trim())) {
                errors.push('Please enter a valid phone number (numbers only, 9-15 digits)');
            }

            const openingTime = document.getElementById('openingTime').value;
            const closingTime = document.getElementById('closingTime').value;

            if (openingTime && closingTime && openingTime >= closingTime) {
                errors.push('Opening time must be before closing time');
            }

            return errors;
        }

        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        function isValidPhoneNumber(phone) {
            const cleanPhone = phone.replace(/\D/g, '');
            return cleanPhone.length >= 9 && cleanPhone.length <= 15;
        }

        async function saveOutletSettings() {
            const errors = validateForm();
            if (errors.length > 0) {
                showMessageBox('Please fix the following errors:\n' + errors.join('\n'), 'error');
                return;
            }

            const formData = {
                outlet_name: outletNameInput.value.trim(),
                address: outletAddressInput.value.trim(),
                contact_person: contactPersonInput.value.trim(),
                contact_email: contactEmailInput.value.trim(),
                contact_phone: contactNumberInput.value.trim(),
                latitude: latitudeInput.value ? parseFloat(latitudeInput.value) : null,
                longitude: longitudeInput.value ? parseFloat(longitudeInput.value) : null,
                status: 'active'
            };

            try {
                const response = await fetch('../api/update_outlet_settings.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(formData)
                });

                const result = await response.json();

                if (result.success) {
                    await saveOperatingHours();
                    await saveNotificationSettings();

                    showMessageBox('Settings saved successfully!', 'success');
                    loadOutletDetails();
                    loadOutletHours();
                    loadNotificationSettings();
                } else {
                    showMessageBox('Error saving settings: ' + result.error, 'error');
                }
            } catch (error) {
                showMessageBox('Error saving settings: ' + error.message, 'error');
            }
        }

        async function saveOperatingHours() {
            const openingTime = document.getElementById('openingTime').value;
            const closingTime = document.getElementById('closingTime').value;
            const daysOfOperation = document.getElementById('selectedDaysOfOperation').textContent;

            const hoursData = {
                opening_time: openingTime,
                closing_time: closingTime,
                days_of_operation: daysOfOperation
            };

            try {
                const response = await fetch('../api/update_outlet_hours.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(hoursData)
                });

                const result = await response.json();
                if (!result.success) {
                    console.log('Error saving hours:', result.error);
                }
            } catch (error) {
                console.log('Error saving hours:', error.message);
            }
        }

        async function saveNotificationSettings() {
            const notificationData = {
                notify_parcel: document.getElementById('newParcelNotif').checked,
                notify_dispatch: document.getElementById('dispatchReminders').checked,
                notify_urgent: document.getElementById('urgentAlerts').checked,
                notifications_enabled: true,
                session_timeout: getSessionTimeoutValue()
            };

            try {
                const response = await fetch('../api/update_notification_settings.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(notificationData)
                });

                const result = await response.json();
                if (!result.success) {
                    console.log('Error saving notification settings:', result.error);
                }
            } catch (error) {
                console.log('Error saving notification settings:', error.message);
            }
        }

        function getSessionTimeoutValue() {
            const timeoutText = document.getElementById('selectedSessionTimeout').textContent;
            switch(timeoutText) {
                case '15 Minutes': return 15;
                case '30 Minutes': return 30;
                case '1 Hour': return 60;
                case 'Never': return 0;
                default: return 30;
            }
        }

        document.getElementById('getCurrentLocation').addEventListener('click', function() {
            if (navigator.geolocation) {
                showMessageBox('Getting your current location...', 'info');
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        latitudeInput.value = position.coords.latitude.toFixed(6);
                        longitudeInput.value = position.coords.longitude.toFixed(6);
                        showMessageBox('Location captured successfully!', 'success');
                    },
                    function(error) {
                        showMessageBox('Error getting location: ' + error.message, 'error');
                    }
                );
            } else {
                showMessageBox('Geolocation is not supported by this browser.', 'error');
            }
        });

        document.querySelectorAll('.dropdown').forEach(dropdown => {
            const toggle = dropdown.querySelector('.dropdown-toggle');
            const menu = dropdown.querySelector('.dropdown-menu');
            const selectedSpan = toggle.querySelector('span');

            toggle.addEventListener('click', function(e) {
                e.stopPropagation();
                dropdown.classList.toggle('active');
            });

            menu.querySelectorAll('a').forEach(item => {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    selectedSpan.textContent = this.dataset.value;
                    dropdown.classList.remove('active');
                });
            });
        });

        document.addEventListener('click', function(e) {
            document.querySelectorAll('.dropdown').forEach(dropdown => {
                if (!dropdown.contains(e.target)) {
                    dropdown.classList.remove('active');
                }
            });
        });

        document.querySelectorAll('.toggle-switch input[type="checkbox"]').forEach(toggle => {
            toggle.addEventListener('change', function() {
                const labelText = this.closest('.toggle-row').querySelector('.toggle-label span').textContent;
                const status = this.checked ? 'Enabled' : 'Disabled';
                console.log(`${labelText} ${status}`);
            });
        });

        document.getElementById('changePasswordBtn').addEventListener('click', () => {
            showPasswordChangeModal();
        });

        function showPasswordChangeModal() {
            const overlay = document.createElement('div');
            overlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.5);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 1000;
            `;

            const modal = document.createElement('div');
            modal.style.cssText = `
                background-color: white;
                padding: 2rem;
                border-radius: 0.5rem;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                max-width: 90%;
                width: 400px;
            `;

            modal.innerHTML = `
                <h3 style="margin: 0 0 1.5rem 0; color: #374151;">Change Password</h3>
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; color: #6b7280;">Current Password</label>
                    <input type="password" id="currentPassword" style="width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.375rem; font-size: 1rem;" placeholder="Enter current password">
                </div>
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; color: #6b7280;">New Password</label>
                    <input type="password" id="newPassword" style="width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.375rem; font-size: 1rem;" placeholder="Enter new password">
                    <small style="color: #6b7280; font-size: 0.875rem;">Must be at least 8 characters with letters and numbers</small>
                </div>
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; margin-bottom: 0.5rem; color: #6b7280;">Confirm New Password</label>
                    <input type="password" id="confirmPassword" style="width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.375rem; font-size: 1rem;" placeholder="Confirm new password">
                </div>
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button id="cancelPasswordBtn" style="padding: 0.75rem 1.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem; background: white; color: #374151; cursor: pointer;">Cancel</button>
                    <button id="savePasswordBtn" style="padding: 0.75rem 1.5rem; border: none; border-radius: 0.375rem; background: #3b82f6; color: white; cursor: pointer;">Change Password</button>
                </div>
            `;

            overlay.appendChild(modal);
            document.body.appendChild(overlay);

            document.getElementById('cancelPasswordBtn').addEventListener('click', () => {
                document.body.removeChild(overlay);
            });

            document.getElementById('savePasswordBtn').addEventListener('click', async () => {
                const currentPassword = document.getElementById('currentPassword').value;
                const newPassword = document.getElementById('newPassword').value;
                const confirmPassword = document.getElementById('confirmPassword').value;

                if (!currentPassword || !newPassword || !confirmPassword) {
                    alert('Please fill in all password fields');
                    return;
                }

                if (newPassword !== confirmPassword) {
                    alert('New password and confirmation do not match');
                    return;
                }

                if (newPassword.length < 8 || !/^(?=.*[A-Za-z])(?=.*\d)/.test(newPassword)) {
                    alert('Password must be at least 8 characters long and contain both letters and numbers');
                    return;
                }

                try {
                    const response = await fetch('../api/change_password.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            current_password: currentPassword,
                            new_password: newPassword,
                            confirm_password: confirmPassword
                        })
                    });

                    const result = await response.json();

                    if (result.success) {
                        document.body.removeChild(overlay);
                        showMessageBox(result.message, 'success');
                    } else {
                        alert('Error: ' + result.error);
                    }
                } catch (error) {
                    alert('Error changing password: ' + error.message);
                }
            });

            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    document.body.removeChild(overlay);
                }
            });
        }

        document.getElementById('manageUsersBtn').addEventListener('click', () => {
            showMessageBox('User management functionality will be implemented soon!', 'info');
        });

        document.getElementById('cancelSettingsBtn').addEventListener('click', () => {
            if (confirm('Are you sure you want to cancel? Unsaved changes will be lost.')) {
                loadOutletDetails();
                loadOutletHours();
                loadNotificationSettings();
            }
        });

        document.getElementById('outletSettingsForm').addEventListener('submit', (e) => {
            e.preventDefault();
            saveOutletSettings();
        });

        function showMessageBox(message, type = 'info') {
            const overlay = document.createElement('div');
            overlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.5);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 1000;
            `;

            const messageBox = document.createElement('div');
            messageBox.style.cssText = `
                background-color: white;
                padding: 2rem;
                border-radius: 0.5rem;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                text-align: center;
                max-width: 90%;
                width: 400px;
            `;

            const messageParagraph = document.createElement('p');
            messageParagraph.textContent = message;
            messageParagraph.style.cssText = `
                font-size: 1.25rem;
                margin-bottom: 1.5rem;
                color: #333;
            `;

            const closeButton = document.createElement('button');
            closeButton.textContent = 'OK';

            let buttonColor;
            switch(type) {
                case 'error':
                    buttonColor = '#ef4444';
                    break;
                case 'success':
                    buttonColor = '#10b981';
                    break;
                case 'info':
                default:
                    buttonColor = '#3b82f6';
                    break;
            }

            closeButton.style.cssText = `
                background-color: ${buttonColor};
                color: white;
                padding: 0.75rem 1.5rem;
                border-radius: 0.375rem;
                font-weight: bold;
                cursor: pointer;
                transition: background-color 0.2s;
            `;
            closeButton.onmouseover = () => closeButton.style.backgroundColor = type === 'error' ? '#dc2626' :
                                                                 type === 'success' ? '#059669' : '#2563eb';
            closeButton.onmouseout = () => closeButton.style.backgroundColor = buttonColor;
            closeButton.addEventListener('click', () => {
                document.body.removeChild(overlay);
            });

            messageBox.appendChild(messageParagraph);
            messageBox.appendChild(closeButton);
            overlay.appendChild(messageBox);
            document.body.appendChild(overlay);
        }

        document.addEventListener('DOMContentLoaded', function() {
            loadOutletDetails();
            loadOutletHours();
            loadNotificationSettings();
            setupRealTimeValidation();
        });
    </script>
    
    <?php include __DIR__ . '/../includes/pwa_install_button.php'; ?>
    <script src="../js/pwa-install.js"></script>
</body>
</html>
