<?php
session_start();
require_once '../api/supabase-client.php';

// Check if user is logged in and has super_admin role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: login.php');
    exit;
}

$pageTitle = 'Admin - Add New User';
require_once '../includes/header.php';
?>

    <div class="mobile-dashboard">
        <!-- Main Content Area for Add New User -->
        <main class="main-content">
            <div class="content-header">
                <h1>Add New User</h1>
            </div>

            <div class="form-container">
                <h2>User Account Details</h2>
                <form id="addUserForm">
                    <div class="form-group">
                        <label for="fullName">Full Name</label>
                        <input type="text" id="fullName" class="form-input-field" placeholder="e.g., John Doe" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" class="form-input-field" placeholder="e.g., john.doe@example.com" required>
                    </div>
                    <div class="form-group password-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" class="form-input-field" placeholder="Enter password" required>
                        <span class="toggle-password" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                    <div class="form-group password-group">
                        <label for="confirmPassword">Confirm Password</label>
                        <input type="password" id="confirmPassword" class="form-input-field" placeholder="Confirm password" required>
                        <span class="toggle-password" id="toggleConfirmPassword">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                    <div class="form-group">
                        <label for="role">User Role</label>
                        <select id="role" class="form-select-field" required>
                            <option value="">Select a Role</option>
                            <option value="Admin">Admin</option>
                            <option value="Company Manager">Company Manager</option>
                            <option value="Outlet Manager">Outlet Manager</option>
                            <option value="Driver">Driver</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="associatedEntity">Associated Company/Outlet (Optional)</label>
                        <input type="text" id="associatedEntity" class="form-input-field" placeholder="e.g., Swift Logistics Inc. / Downtown Branch">
                        <small class="text-light">Specify if the user belongs to a specific company or outlet.</small>
                    </div>
                    <div class="form-group">
                        <label for="status">Account Status</label>
                        <select id="status" class="form-select-field">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="pending">Pending Approval</option>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn cancel-btn" id="cancelBtn">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn save-btn" id="saveUserBtn">
                            <i class="fas fa-user-plus"></i> Add User
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Menu toggle functionality
            const menuButton = document.querySelector('.menu-toggle');
            const sidebar = document.querySelector('.sidebar');
            const menuOverlay = document.getElementById('menuOverlay');

            if (menuButton && sidebar && menuOverlay) {
                menuButton.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                    menuOverlay.classList.toggle('active');
                });

                menuOverlay.addEventListener('click', function() {
                    sidebar.classList.remove('active');
                    menuOverlay.classList.remove('active');
                });
            }
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

        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordField = document.getElementById('password');
            const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordField.setAttribute('type', type);
            this.querySelector('i').classList.toggle('fa-eye');
            this.querySelector('i').classList.toggle('fa-eye-slash');
        });

        document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
            const confirmPasswordField = document.getElementById('confirmPassword');
            const type = confirmPasswordField.getAttribute('type') === 'password' ? 'text' : 'password';
            confirmPasswordField.setAttribute('type', type);
            this.querySelector('i').classList.toggle('fa-eye');
            this.querySelector('i').classList.toggle('fa-eye-slash');
        });

        // Form submission handler
        document.getElementById('addUserForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const fullName = document.getElementById('fullName').value;
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const role = document.getElementById('role').value;
            const associatedEntity = document.getElementById('associatedEntity').value;
            const status = document.getElementById('status').value;

            if (password !== confirmPassword) {
                showMessageBox('Passwords do not match. Please try again.');
                return;
            }

            try {
                // Show loading message
                showMessageBox('Adding user...');

                const response = await fetch('../api/add_user.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        full_name: fullName,
                        email: email,
                        password: password,
                        role: role,
                        associated_entity: associatedEntity || null,
                        status: status || 'active'
                    })
                });

                const result = await response.json();
                
                if (result.success) {
                    showMessageBox('User added successfully!');
                    this.reset();
                    // Redirect to users list after 2 seconds
                    setTimeout(() => {
                        window.location.href = 'users.php';
                    }, 2000);
                } else {
                    throw new Error(result.error);
                }
            } catch (error) {
                showMessageBox('Error adding user: ' + error.message);
            }
        });

        // Cancel button functionality
        document.getElementById('cancelBtn').addEventListener('click', function() {
            showMessageBox('User addition cancelled.');
            // Optionally, redirect back to the users list page
            // window.location.href = 'admin-users.html';
        });
    </script>
    <script src="../assets/js/admin-scripts.js" defer></script>
</body>
</html>
