<?php
    $page_title = 'Company - Add Outlet';
    include __DIR__ . '/../includes/header.php';
?>

<body class="bg-gray-100 min-h-screen">

    <div class="mobile-dashboard">
                    
        <!-- Main Content Area for Add New Outlet -->
        <main class="main-content">
            <div class="content-header">
                <h1>Add New Outlet</h1>
            </div>

            <div class="form-card">
                <p class="text-gray-600 mb-6">Provide the necessary details for the new outlet.</p>
                <form id="addOutletForm">
                    <div id="formError" class="error-message" style="display: none; color: red; margin-bottom: 1em;"></div>
                    <div class="form-group">
                        <label for="outletName">Outlet Name</label>
                        <input type="text" id="outletName" name="outletName" placeholder="Enter outlet name" required>
                    </div>
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" placeholder="Enter address" rows="3" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="contactPerson">Contact Person</label>
                        <input type="text" id="contactPerson" name="contactPerson" placeholder="Enter contact person's name" required>
                    </div>
                    <div class="form-group">
                        <label for="contact_email">Contact Email</label>
                        <input type="email" id="contact_email" name="contact_email" placeholder="Enter Email" required>
                    </div>
                     <div class="form-group">
                        <label for="contact_phone">Contact Phone</label>
                        <input type="tel" id="contact_phone" name="contact_phone" placeholder="Enter phone number" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Enter Password</label>
                        <input type="password" id="password" name="password" placeholder="Enter password" required minlength="8">
                        <small class="form-text text-muted">Password must be at least 8 characters long</small>
                    </div>

                    <div class="form-group">
                        <label for="confirmPassword">Confirm Password</label>
                        <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirm the password" required>
                        <small class="form-text text-muted">Re-enter the password to confirm</small>
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="maintenance">Under Maintenance</option>
                        </select>
                    </div>

                    <div class="form-actions">

                    <button class="action-btn secondary"  onclick="history.back()">
                        <i class="fas fa-arrow-left" ></i> Back </button>
              
                     <button type="submit" class="action-btn">Save</button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <!-- Link to the external JavaScript files -->
      <script src="../assets/js/company-scripts.js"></script>
    <script src="../assets/js/add-outlet.js"></script>
    <script>
        // Wait for DOM to be loaded
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Initializing form handling...');
            
            // Get the form
            const form = document.getElementById('addOutletForm');
            if (!form) {
                console.error('Add outlet form not found!');
                return;
            }
            
            // Attach submit handler
            form.addEventListener('submit', handleAddOutlet);
            console.log('Form handler attached successfully');
            
            // Verify all form elements are present
            const formElements = [
                'outletName',
                'address',
                'contactPerson',
                'contact_email',
                'contact_phone',
                'password',
                'confirmPassword',
                'status',
                'formError'
            ];
            
            const missingElements = formElements.filter(id => {
                const element = document.getElementById(id);
                if (!element) {
                    console.error(`Element with id '${id}' not found`);
                    return true;
                }
                return false;
            });
            
            if (missingElements.length > 0) {
                console.error('Missing form elements:', missingElements);
                const errorDiv = document.getElementById('formError');
                if (errorDiv) {
                    errorDiv.textContent = 'Form initialization error. Please contact support.';
                    errorDiv.style.display = 'block';
                }
            } else {
                console.log('All form elements verified successfully');
            }
        });
    </script>
</body>