<?php
session_start();
require_once '../api/supabase-client.php';

// Check if user is logged in and has super_admin role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: login.php');
    exit;
}

// Get current settings
try {
    $settings = callSupabase('system_settings?id=eq.1');
    $currentSettings = is_array($settings) && !empty($settings) ? $settings[0] : [];
} catch (Exception $e) {
    error_log('Error fetching settings: ' . $e->getMessage());
    $currentSettings = [];
}

$pageTitle = 'Admin - settings';
require_once '../includes/header.php';
?>

    <div class="mobile-dashboard">
        <style>
            :root {
                --primary-color: #2E0D2A;
                --primary-light: #4A1C40;
                --secondary-color: #FF6B6B;
                --accent-color: #4CAF50;
                --text-color: #333;
                --text-light: #777;
                --bg-color: #f8f9fa;
                --card-bg: #fff;
                --border-color: #e0e0e0;
                --sidebarBg: #2E0D2A;
                --sidebarText: #e0e0e0;
                --sidebarHover: #4A1C40;
                --sidebarActive: #6A2A62;
            }
        </style>

        <style>
            .settings-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .settings-header {
            margin-bottom: 30px;
        }

        .settings-header h2 {
            color: var(--primary-color);
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .settings-subtitle {
            color: var(--text-light);
            margin-top: 5px;
        }

        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .settings-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .card-header {
            background: var(--primary-color);
            color: white;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header h3 {
            margin: 0;
            font-size: 18px;
        }

        .card-body {
            padding: 20px;
        }

        .info-group {
            margin-bottom: 15px;
        }

        .info-group label {
            display: block;
            margin-bottom: 5px;
            color: var(--text-color);
            font-weight: 500;
        }

        .form-input-field {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            font-size: 14px;
        }

        .toggle-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding: 10px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .toggle-label {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-color);
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 24px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: var(--accent-color);
        }

        input:checked + .slider:before {
            transform: translateX(26px);
        }

        .dropdown {
            position: relative;
        }

        .dropdown-toggle {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            background: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            z-index: 1000;
            max-height: 200px;
            overflow-y: auto;
        }

        .dropdown.active .dropdown-menu {
            display: block;
        }

        .dropdown-menu a {
            padding: 8px 12px;
            display: block;
            color: var(--text-color);
            text-decoration: none;
        }

        .dropdown-menu a:hover {
            background: var(--bg-color);
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .save-btn {
            background: var(--accent-color);
            color: white;
        }

        .save-btn:hover {
            background: #3d8b40;
        }

        .cancel-btn {
            background: #f44336;
            color: white;
        }

        .cancel-btn:hover {
            background: #d32f2f;
        }

        .action-btn {
            width: 100%;
            padding: 10px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 15px;
            transition: background 0.3s;
        }

        .action-btn:hover {
            background: var(--primary-light);
        }

        @media (max-width: 768px) {
            .settings-grid {
                grid-template-columns: 1fr;
            }
            
            .settings-content {
                padding: 10px;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
    <script>
        // Initialize sidebar functionality when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Get all required elements
            const menuBtn = document.getElementById('menuBtn');
            const closeBtn = document.getElementById('closeMenu');
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('menuOverlay');
            
            // Log elements for debugging
            console.log('Menu button:', menuBtn);
            console.log('Close button:', closeBtn);
            console.log('Sidebar:', sidebar);
            console.log('Overlay:', overlay);

            // Function to show sidebar
            function showSidebar() {
                console.log('Showing sidebar');
                requestAnimationFrame(() => {
                    overlay.style.visibility = 'visible';
                    overlay.style.opacity = '1';
                    sidebar.classList.add('show');
                    document.body.style.overflow = 'hidden';
                });
            }

            // Function to hide sidebar
            function hideSidebar() {
                console.log('Hiding sidebar');
                overlay.style.opacity = '0';
                overlay.style.visibility = 'hidden';
                sidebar.classList.remove('show');
                document.body.style.overflow = '';
            }

            // Menu button click event
            if (menuBtn) {
                menuBtn.addEventListener('click', function(e) {
                    console.log('Menu button clicked');
                    e.preventDefault();
                    e.stopPropagation();
                    showSidebar();
                });
            }

            // Close button click event
            if (closeBtn) {
                closeBtn.addEventListener('click', function(e) {
                    console.log('Close button clicked');
                    e.preventDefault();
                    hideSidebar();
                });
            }

            // Overlay click event
            if (overlay) {
                overlay.addEventListener('click', function(e) {
                    console.log('Overlay clicked');
                    e.preventDefault();
                    hideSidebar();
                });
            }

            // Close sidebar when clicking outside
            document.addEventListener('click', function(e) {
                if (sidebar && sidebar.classList.contains('show') &&
                    !sidebar.contains(e.target) &&
                    !menuBtn.contains(e.target)) {
                    console.log('Outside click - hiding sidebar');
                    hideSidebar();
                }
            });

            // Initialize sidebar in hidden state
            console.log('Initializing sidebar');
            hideSidebar();
        });
    </script>
    </script>
</head>


    <!-- Mobile Dashboard Container -->
    <div class="mobile-dashboard">
        <!-- Main Content Area for Admin Settings -->
        <main class="main-content">
            <div class="settings-content">
                <div class="settings-header">
                    <h2><i class="fas fa-cog"></i> Admin Settings</h2>
                    <p class="settings-subtitle">Manage system-wide configurations and preferences</p>
                </div>

                <form class="settings-form" id="settingsForm">
                    <div class="settings-grid">
                        <!-- General Settings Card -->
                        <div class="settings-card">
                            <div class="card-header">
                                <i class="fas fa-globe"></i>
                                <h3>General Settings</h3>
                            </div>
                            <div class="card-body">
                                <div class="info-group">
                                    <label for="platformName">Platform Name</label>
                                    <input type="text" id="platformName" name="platformName" class="form-input-field" 
                                           value="<?php echo htmlspecialchars($currentSettings['platform_name'] ?? 'WebDev Tech'); ?>" 
                                           placeholder="Enter platform name">
                                </div>
                                <div class="info-group">
                                    <label for="defaultCurrency">Default Currency</label>
                                    <div class="dropdown" id="defaultCurrencyDropdown">
                                        <button type="button" class="dropdown-toggle">
                                            <span id="selectedDefaultCurrency"><?php echo htmlspecialchars($currentSettings['default_currency'] ?? 'ZMW - Zambian Kwacha'); ?></span>
                                            <i class="fas fa-chevron-down"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a href="#" data-value="USD - US Dollar">USD - US Dollar</a>
                                            <a href="#" data-value="ZMW - Zambian Kwacha">ZMW - Zambian Kwacha</a>
                                            <a href="#" data-value="EUR - Euro">EUR - Euro</a>
                                            <a href="#" data-value="GBP - British Pound">GBP - British Pound</a>
                                          
                                        </div>
                                    </div>
                                    <input type="hidden" name="defaultCurrency" id="defaultCurrencyInput" 
                                           value="<?php echo htmlspecialchars($currentSettings['default_currency'] ?? 'ZMW - Zambian Kwacha'); ?>">
                                </div>
                                <div class="info-group">
                                    <label for="timezone">Timezone</label>
                                    <input type="text" id="timezone" name="timezone" class="form-input-field" 
                                           value="<?php echo htmlspecialchars($currentSettings['timezone'] ?? 'Africa/Lusaka'); ?>" 
                                           placeholder="e.g., Africa/Lusaka">
                                </div>
                            </div>
                        </div>

                        <!-- User Management Settings Card -->
                        <div class="settings-card">
                            <div class="card-header">
                                <i class="fas fa-users-cog"></i>
                                <h3>User Management</h3>
                            </div>
                            <div class="card-body">
                                <div class="toggle-row">
                                    <div class="toggle-label">
                                        <i class="fas fa-user-plus"></i>
                                        <span>Allow New User Registrations</span>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" id="allowRegistrations" checked>
                                        <span class="slider round"></span>
                                    </label>
                                </div>

                                <div class="toggle-row">
                                    <div class="toggle-label">
                                        <i class="fas fa-unlock-alt"></i>
                                        <span>Enable Two-Factor Authentication</span>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" id="enable2FA">
                                        <span class="slider round"></span>
                                    </label>
                                </div>

                                <div class="dropdown-group">
                                    <label>Default User Role</label>
                                    <div class="dropdown" id="defaultUserRoleDropdown">
                                        <button type ="button" class="dropdown-toggle">
                                            <span id="selectedDefaultUserRole">Customer</span>
                                            <i class="fas fa-chevron-down"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a href="#" data-value="Super Admin">Super Admin</a>
                                            <a href="#" data-value="Driver">Driver</a>
                                            <a href="#" data-value="Customer">Customer</a>
                                            <a href="#" data-value="Outlet Manager">Outlet Manager</a>
                                            <a href="#" data-value="Company Manager">Company Manager</a>
                                        </div>
                                    </div>
                                    <input type="hidden" name="defaultUserRole" id="defaultUserRoleInput" value="Customer">
                                </div>
                            </div>
                        </div>

                        <!-- Notifications & Alerts Card -->
                        <div class="settings-card notifications-card">
                            <div class="card-header">
                                <i class="fas fa-bell"></i>
                                <h3>System Notifications</h3>
                            </div>
                            <div class="card-body">
                                <div class="toggle-row">
                                    <div class="toggle-label">
                                        <i class="fas fa-envelope"></i>
                                        <span>Email Notifications</span>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" id="emailNotif" checked>
                                        <span class="slider round"></span>
                                    </label>
                                </div>

                                <div class="toggle-row">
                                    <div class="toggle-label">
                                        <i class="fas fa-sms"></i>
                                        <span>SMS Alerts</span>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" id="smsAlerts">
                                        <span class="slider round"></span>
                                    </label>
                                </div>

                                <div class="toggle-row">
                                    <div class="toggle-label">
                                        <i class="fas fa-exclamation-circle"></i>
                                        <span>Critical System Alerts</span>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" id="criticalAlerts" checked>
                                        <span class="slider round"></span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Integrations Card -->
                        <div class="settings-card security-card">
                            <div class="card-header">
                                <i class="fas fa-plug"></i>
                                <h3>Integrations</h3>
                            </div>
                            <div class="card-body">
                                <div class="info-group">
                                    <label for="paymentGateway">Payment Gateway</label>
                                    <input type="text" id="paymentGateway" name="paymentGateway" class="form-input-field" value="Stripe (Configured)" placeholder="e.g., Stripe, PayPal">
                                </div>
                                <div class="info-group">
                                    <label for="smsProvider">SMS Provider</label>
                                    <input type="text" id="smsProvider" name="smsProvider" class="form-input-field" value="Twilio (Configured)" placeholder="e.g., Twilio, Nexmo">
                                </div>
                                <button type="button" class="action-btn full-width" id="manageApiKeysBtn">
                                    <i class="fas fa-cogs"></i> Manage API Keys
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
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
        // Function to load settings from the server
        async function loadSettings() {
            try {
                const response = await fetch('../api/manage_settings.php');
                const data = await response.json();
                
                if (!response.ok) {
                    throw new Error(data.message || 'Failed to fetch settings');
                }
                
                if (data.success && data.settings) {
                    populateForm(data.settings);
                } else {
                    throw new Error(data.message || 'Invalid response format');
                }
            } catch (error) {
                console.error('Error loading settings:', error);
                showMessageBox('Failed to load settings: ' + error.message);
                
                // If unauthorized, redirect to login
                if (error.message === 'Unauthorized access') {
                    window.location.href = '../auth/login.php';
                }
            }
        }

        // Function to populate form with settings
        function populateForm(settings) {
            document.getElementById('platformName').value = settings.platform_name || '';
            document.getElementById('selectedDefaultCurrency').textContent = settings.default_currency || 'ZMW - Zambian Kwacha';
            document.getElementById('defaultCurrencyInput').value = settings.default_currency || 'ZMW - Zambian Kwacha';
            document.getElementById('timezone').value = settings.timezone || '';
            document.getElementById('allowRegistrations').checked = settings.allow_registrations || false;
            document.getElementById('enable2FA').checked = settings.enable_2fa || false;
            document.getElementById('selectedDefaultUserRole').textContent = settings.default_user_role || 'Customer';
            document.getElementById('defaultUserRoleInput').value = settings.default_user_role || 'Customer';
            document.getElementById('emailNotif').checked = settings.email_notifications || false;
            document.getElementById('smsAlerts').checked = settings.sms_alerts || false;
            document.getElementById('criticalAlerts').checked = settings.critical_alerts || false;
            document.getElementById('paymentGateway').value = settings.payment_gateway || '';
            document.getElementById('smsProvider').value = settings.sms_provider || '';
        }

        // Function to save settings
        async function saveSettings(formData) {
            try {
                const response = await fetch('../api/manage_settings.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        platformName: formData.get('platformName'),
                        defaultCurrency: formData.get('defaultCurrency'),
                        timezone: formData.get('timezone'),
                        allowRegistrations: document.getElementById('allowRegistrations').checked,
                        enable2FA: document.getElementById('enable2FA').checked,
                        defaultUserRole: document.getElementById('defaultUserRoleInput').value,
                        emailNotifications: document.getElementById('emailNotif').checked,
                        smsAlerts: document.getElementById('smsAlerts').checked,
                        criticalAlerts: document.getElementById('criticalAlerts').checked,
                        paymentGateway: document.getElementById('paymentGateway').value,
                        smsProvider: document.getElementById('smsProvider').value
                    })
                });

                if (!response.ok) throw new Error('Failed to save settings');
                
                const data = await response.json();
                if (data.success) {
                    showMessageBox('Settings saved successfully!');
                } else {
                    throw new Error(data.message || 'Failed to save settings');
                }
            } catch (error) {
                console.error('Error saving settings:', error);
                showMessageBox('Failed to save settings: ' + error.message);
            }
        }

        // Load settings when page loads
        document.addEventListener('DOMContentLoaded', loadSettings);

        

        // Dropdown functionality
        document.querySelectorAll('.dropdown').forEach(dropdown => {
            const toggle = dropdown.querySelector('.dropdown-toggle');
            const menu = dropdown.querySelector('.dropdown-menu');
            const selectedSpan = toggle.querySelector('span');

            toggle.addEventListener('click', function(e) {
                e.stopPropagation(); // Prevent document click from closing immediately
                dropdown.classList.toggle('active');
            });

            menu.querySelectorAll('a').forEach(item => {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    selectedSpan.textContent = this.dataset.value;
                    dropdown.classList.remove('active');
                    // Update the correct hidden input for each dropdown
                    if (dropdown.id === 'defaultCurrencyDropdown') {
                        document.getElementById('defaultCurrencyInput').value = this.dataset.value;
                    }
                    if (dropdown.id === 'defaultUserRoleDropdown') {
                        document.getElementById('defaultUserRoleInput').value = this.dataset.value;
                    }
                    showMessageBox(`Selected: ${this.dataset.value}`);
                });
            });
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            document.querySelectorAll('.dropdown').forEach(dropdown => {
                if (!dropdown.contains(e.target)) {
                    dropdown.classList.remove('active');
                }
            });
        });

        // Toggle Switch functionality
        document.querySelectorAll('.toggle-switch input[type="checkbox"]').forEach(toggle => {
            toggle.addEventListener('change', function() {
                const labelText = this.closest('.toggle-row').querySelector('.toggle-label span').textContent;
                const status = this.checked ? 'Enabled' : 'Disabled';
                showMessageBox(`${labelText} ${status}!`);
            });
        });

        // Action Buttons functionality
        document.getElementById('manageApiKeysBtn').addEventListener('click', () => {
            showMessageBox('Manage API Keys button clicked! (Functionality not implemented)');
        });

        document.getElementById('cancelSettingsBtn').addEventListener('click', () => {
            showMessageBox('Settings changes cancelled!');
            // In a real app, you might reset the form or navigate back
        });

        document.getElementById('settingsForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            await saveSettings(formData);
        });

        // Function to display a custom message box instead of alert()
        function showMessageBox(message) {
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
            overlay.id = 'messageBoxOverlay';

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
            closeButton.style.cssText = `
                background-color: #3b82f6; /* Blue-600 */
                color: white;
                padding: 0.75rem 1.5rem;
                border-radius: 0.375rem;
                font-weight: bold;
                cursor: pointer;
                transition: background-color 0.2s;
            `;
            closeButton.onmouseover = () => closeButton.style.backgroundColor = '#2563eb';
            closeButton.onmouseout = () => closeButton.style.backgroundColor = '#3b82f6';
            closeButton.addEventListener('click', () => {
                document.body.removeChild(overlay);
            });

            messageBox.appendChild(messageParagraph);
            messageBox.appendChild(closeButton);
            overlay.appendChild(messageBox);
            document.body.appendChild(overlay);
        }
    </script>
    <script src="../assets/js/admin-scripts.js" defer></script>
</body>
</html>
