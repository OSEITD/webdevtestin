<?php
// Include necessary files and check authentication
require_once __DIR__ . '/../api/supabase-client.php';

$pageTitle = 'Admin - [Page Name]';
require_once '../includes/header.php';
?>

    <div class="mobile-dashboard">
        <!-- Main Content Area -->
        <main class="main-content">
            <style>
        .form-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            margin: 2rem auto;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #374151;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            font-size: 1rem;
            transition: border-color 0.2s;
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #3b82f6;
            outline: none;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .submit-btn {
            background-color: #3b82f6;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.375rem;
            font-weight: 500;
            cursor: pointer;
            width: 100%;
            transition: background-color 0.2s;
        }

        .submit-btn:hover {
            background-color: #2563eb;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #6b7280;
            text-decoration: none;
            margin-bottom: 1rem;
        }

        .back-btn:hover {
            color: #374151;
        }

        .error-message {
            color: #dc2626;
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }

        .form-section {
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-section h2 {
            color: #374151;
            font-size: 1.25rem;
            margin-bottom: 1rem;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        @media (max-width: 640px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }
        }
    </style>

        <!-- Top Header Bar - REMOVED (now in header.php) -->
        <!-- Sidebar - REMOVED (now in header.php) -->

        <div class="content-header">
                <a href="users.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i>
                    Back to Users
                </a>
                <h1>Add New Customer</h1>
            </div>

            <div class="form-container">
                <form id="addCustomerForm" method="POST" action="../api/add_customer.php">
                    <input type="hidden" id="csrf_token" value="<?php echo CSRFHelper::getToken(); ?>">
                      <!-- Company Selection -->
                    <div class="form-section">
                        <h2>Company Information</h2>
                        <div class="form-group">
                            <label for="company_id">Select Company</label>
                            <select id="company_id" name="company_id" required>
                                <option value="">Select Company</option>
                                <?php foreach ($companies as $company): ?>
                                    <option value="<?php echo htmlspecialchars($company['id']); ?>">
                                        <?php echo htmlspecialchars($company['company_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <!-- Personal Information -->
                    <div class="form-section">
                        <h2>Personal Information</h2>
                           <div class="form-group">
                            <label for="customer_name">Customer Name</label>
                            <input type="text" id="customer_name" name="customer_name" required placeholder="Enter full name">
                        </div>

                        <div class="grid-2">
                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" id="email" name="email" required>
                            </div>

                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" id="phone" name="phone" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" required>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                    </div>

                    <!-- Primary Address -->
                    <div class="form-section">
                        <div class="form-group">
                            <label for="street_address">Address</label>
                            <input type="text" id="address" name="address" required>
                        </div>
                    </div>

                    <button type="submit" class="submit-btn">Create Customer Account</button>
                </form>
            </div>
        </main>
    </div>

    <script>
        // Form validation
        const form = document.getElementById('addCustomerForm');
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');

        form.addEventListener('submit', (e) => {
            // Password match validation
            if (password.value !== confirmPassword.value) {
                e.preventDefault();
                alert('Passwords do not match!');
                return;
            }

            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            const email = document.getElementById('email').value;

            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address!');
                return;
            }

            // Phone number validation
            const phoneRegex = /^\+?[\d\s-]{8,}$/;
            const phone = document.getElementById('phone').value;

            if (!phoneRegex.test(phone)) {
                e.preventDefault();
                alert('Please enter a valid phone number!');
                return;
            }

            // Postal code validation
            const postalCode = document.getElementById('postal_code').value;
            if (!/^\d{5}(-\d{4})?$/.test(postalCode)) {
                e.preventDefault();
                alert('Please enter a valid postal code!');
                return;
            }
        });

        
    </script>
   <script src="../assets/js/admin-scripts.js" defer></script>
</body>
</html>
