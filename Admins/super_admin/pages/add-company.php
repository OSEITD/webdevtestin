<?php
session_start();
require_once '../api/supabase-client.php';

// Check if user is logged in and has super_admin role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: login.php');
    exit;
}

$pageTitle = 'Admin - Add-company';
require_once '../includes/header.php';
?>

    <div class="mobile-dashboard">
        <!-- Main Content Area for Add New Company -->
        <main class="main-content">
            <div class="content-header">
                <h1>Add New Company</h1>
            </div>

            <div class="form-container">
                <h2>Company Details</h2>
                <form id="addCompanyForm">
                    <div class="form-group">
                        <label for="name">Company Name</label>

                        <input type="text" id="company_name" name="company_name" class="form-input-field" placeholder="e.g., Swift Logistics Inc." required>

                    <div class="form-group">
                        <label for="subdomain">Subdomain</label>
                        <input type="text" id="subdomain" name="subdomain" class="form-input-field" placeholder="e.g., swiftlogistics" required>
                        <small class="help-text">Will be auto-generated if left empty</small>
                    </div>
                    <div class="form-group">
                        <label for="contact_person">Contact Person</label>
                        <input type="text" id="contact_person" name="contact_person" class="form-input-field" placeholder="e.g., Jane Doe" required>
                    </div>
                    <div class="form-group">
                        <label for="contact_email">Contact Email</label>
                        <input type="email" id="contact_email" name="contact_email" class="form-input-field" placeholder="e.g., jane.doe@company.com" required>
                    </div>
                    <div class="form-group">
                        <label for="contact_phone">Contact Phone</label>
                        <input type="tel" id="contact_phone" name="contact_phone" class="form-input-field" placeholder="e.g., +1234567890">
                    </div>
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" class="form-textarea-field" placeholder="Full company address"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" class="form-input-field" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-input-field" required>
                    </div>
                    <div class="form-group">
                        <label for="revenue">Initial Revenue</label>
                        <input type="number" id="revenue" name="revenue" class="form-input-field" placeholder="0.00" step="0.01" value="0">
                    </div>
                    <div class="form-group">
                        <label for="commission">Commission (%)</label>
                        <input type="number" id="commission" name="commission" class="form-input-field" placeholder="0.00" step="0.01" min="0" max="100" value="0">
                        <small class="help-text">Commission percentage for this company</small>
                    </div>
                    <div class="form-group">
                        <label for="progress">Progress (%)</label>
                        <input type="number" id="progress" name="progress" class="form-input-field" placeholder="0" min="0" max="100" value="0">
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
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

    <script>
        // Function to show message box
        function showMessageBox(message, isError = false) {
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

            const text = document.createElement('p');
            text.textContent = message;
            text.style.cssText = `
                margin-bottom: 1.5rem;
                color: ${isError ? 'red' : '#333'};
            `;

            const button = document.createElement('button');
            button.textContent = 'OK';
            button.style.cssText = `
                background-color: #3b82f6;
                color: white;
                padding: 0.5rem 2rem;
                border-radius: 0.25rem;
                border: none;
                cursor: pointer;
            `;
            button.onclick = () => document.body.removeChild(overlay);

            messageBox.appendChild(text);
            messageBox.appendChild(button);
            overlay.appendChild(messageBox);
            document.body.appendChild(overlay);
        }

        // Handle form submission
        document.getElementById('addCompanyForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            // Gather form data
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            // Validate passwords match
            if (password !== confirmPassword) {
                showMessageBox("Passwords do not match!", true);
                return;
            }

            const formData = {
                company_name: document.getElementById('company_name').value,
                subdomain: document.getElementById('subdomain').value,
                contact_person: document.getElementById('contact_person').value,
                contact_email: document.getElementById('contact_email').value,
                contact_phone: document.getElementById('contact_phone').value,
                address: document.getElementById('address').value,
                password: password,
                confirm_password: confirmPassword,
                revenue: parseFloat(document.getElementById('revenue').value) || 0,
                commission: parseFloat(document.getElementById('commission').value) || 0,
                progress: parseInt(document.getElementById('progress').value) || 0,
                status: document.getElementById('status').value
            };

            try {
                const response = await fetch('../api/add_company.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(formData)
                });

                const result = await response.json();
                
                if (result.success) {
                    showMessageBox('Company added successfully!');
                    // Redirect to companies list after 2 seconds
                    setTimeout(() => {
                        window.location.href = 'companies.php';
                    }, 2000);
                } else {
                    throw new Error(result.error || 'Failed to add company');
                }
            } catch (error) {
                showMessageBox(error.message, true);
            }
        });

        // Auto-generate subdomain from company name
        document.getElementById('company_name').addEventListener('input', function() {
            const subdomainInput = document.getElementById('subdomain');
            if (!subdomainInput.value) {
                subdomainInput.value = this.value
                    .toLowerCase()
                    .replace(/[^a-z0-9]/g, '');
            }
        });

        // Cancel button functionality
        document.getElementById('cancelBtn').addEventListener('click', () => {
            if (confirm('Are you sure you want to cancel? Any unsaved changes will be lost.')) {
                window.location.href = 'companies.php';
            }
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
