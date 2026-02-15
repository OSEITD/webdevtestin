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
require_once '../includes/header.php';
?>

    <div class="mobile-dashboard">
        <!-- Main Content Area for Add New Outlet -->
        <main class="main-content">
            <div class="content-header">
                <h1>Add New Outlet</h1>
            </div>

            <div class="form-container">
                <h2>Outlet Details</h2>
                <form id="addOutletForm">
                    <input type="hidden" id="csrf_token" value="<?php echo CSRFHelper::getToken(); ?>">
                    <div class="form-group">
                        <label for="outletName">Outlet Name</label>
                        <input type="text" id="outletName" class="form-input-field" placeholder="e.g., Downtown Branch" required>
                    </div>
                    <div class="form-group">
                        <label for="company">Associated Company</label>
                        <div class="select-wrapper">
                            <select id="company" name="company" class="form-control" required>
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
                        <label for="contactPerson">Contact Person</label>
                        <input type="text" id="contactPerson" class="form-input-field" placeholder="e.g., John Smith" required>
                    </div>
                    <div class="form-group">
                        <label for="contactEmail">Contact Email</label>
                        <input type="email" id="contactEmail" class="form-input-field" placeholder="e.g., john.smith@outlet.com" required>
                    </div>
                    <div class="form-group">
                        <label for="contactPhone">Contact Phone</label>
                        <input type="tel" id="contactPhone" class="form-input-field" placeholder="e.g., +1234567890">
                    </div>
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" class="form-textarea-field" placeholder="Full outlet address" required></textarea>
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
                        <label for="status">Status</label>
                        <select id="status" class="form-select-field">
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

    <script>
        

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


        // Form submission handler
        document.getElementById('addOutletForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const outletName = document.getElementById('outletName').value;
            const company = document.getElementById('company').value;
            const contactPerson = document.getElementById('contactPerson').value;
            const contactEmail = document.getElementById('contactEmail').value;
            const contactPhone = document.getElementById('contactPhone').value;
            const address = document.getElementById('address').value;
            const status = document.getElementById('status').value;
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            // Only use the real API call and redirect logic
            try {
                // Validate required fields
                if (!outletName || !company || !address || !contactPerson || !contactEmail || !password || !confirmPassword) {
                    throw new Error('Please fill in all required fields');
                }

                // Validate passwords match
                if (password !== confirmPassword) {
                    throw new Error('Passwords do not match');
                }

                // Validate email format
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(contactEmail)) {
                    throw new Error('Please enter a valid email address');
                }

                const response = await fetch('../api/add_outlet.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.getElementById('csrf_token').value
                    },
                    body: JSON.stringify({
                        outlet_name: outletName.trim(),
                        company_id: company.trim(),
                        address: address.trim(),
                        contact_person: contactPerson.trim(),
                        contact_phone: contactPhone ? contactPhone.trim() : '',
                        contact_email: contactEmail.trim(),
                        password: password,
                        confirm_password: confirmPassword,
                        status: status
                    })
                });
                const result = await response.json();
                console.log('API Response:', result); // Debug log
                
                if (result.success && result.outlet && Array.isArray(result.outlet)) {
                    // Use the ID from the response
                    const outletId = result.outlet[0].id;
                    if (outletId) {
                        showMessageBox('Outlet created successfully! Redirecting...');
                        setTimeout(() => {
                            window.location.href = `outlets.php`;
                        }, 2000);
                    } else {
                        showMessageBox('Outlet created successfully!');
                        this.reset();
                    }
                } else {
                    throw new Error(result.error || 'Failed to create outlet');
                }
            } catch (error) {
                showMessageBox('Error adding outlet: ' + error.message);
            }
        });

        // Cancel button functionality
        document.getElementById('cancelBtn').addEventListener('click', function() {
            showMessageBox('Outlet addition cancelled.');
            // Optionally, redirect back to the outlets list page
            // window.location.href = 'admin-outlets.html';
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
