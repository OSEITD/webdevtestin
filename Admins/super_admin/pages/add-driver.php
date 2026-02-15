<?php
// Include necessary files and check authentication
require_once __DIR__ . '/../api/supabase-client.php';

// Fetch list of companies from Supabase
try {
    $response = callSupabase('companies?select=id,company_name');
    $companies = [];
    if ($response && is_array($response)) {
        $companies = $response;
    } else {
        $companies = $response->data;
    }
} catch (Exception $e) {
    $companies = [];
    // Log error or display message if needed
    // error_log($e->getMessage());
}

    $pageTitle = 'Admin - Add-Driver';
require_once '../includes/header.php';
?>

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
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            font-size: 1rem;
            transition: border-color 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: #3b82f6;
            outline: none;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .submit-btn {
            background-color:  #2E0D2A;
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
            background-color: #2E0D2A;
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
    <div class="mobile-dashboard">
        <!-- Main Content -->
        <main class="main-content">
            <div class="content-header">
                <a href="users.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i>
                    Back to Users
                </a>
                <h1>Add New Driver</h1>
            </div>

            <div class="form-container">
                <form id="addDriverForm" onsubmit="return handleSubmit(event)">
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
                            <label for="driver_name">Driver Name</label>
                            <input type="text" id="driver_name" name="driver_name" required placeholder="Enter full name">
                        </div>

                        <div class="grid-2">
                            <div class="form-group">
                                <label for="driver_email">Email</label>
                                <input type="email" id="driver_email" name="driver_email" required>
                            </div>

                            <div class="form-group">
                                <label for="driver_phone">Phone Number</label>
                                <input type="tel" id="driver_phone" name="driver_phone" required placeholder="+XX XXXX XXXX">
                            </div>
                        </div>

                        <div class="grid-2">
                            <div class="form-group">
                                <label for="password">Password</label>
                                <input type="password" id="password" name="password" required>
                            </div>

                            <div class="form-group">
                                <label for="confirm_password">Confirm Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" required>
                            </div>
                        </div>
                    </div>
                   
                    <button type="submit" class="submit-btn" id="submitBtn">Create Driver Account</button>
                </form>
            </div>
        </main>
    </div>

    <script>

        function handleSubmit(e) {
            e.preventDefault();
            
            const form = $('#addDriverForm');
            const submitBtn = $('#submitBtn');
            const password = $('#password').val();
            const confirmPassword = $('#confirm_password').val();

            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            const email = $('#driver_email').val();
            if (!emailRegex.test(email)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Email',
                    text: 'Please enter a valid email address!'
                });
                return false;
            }

            // Phone number validation
            const phoneRegex = /^\+?[\d\s-]{8,}$/;
            const phone = $('#driver_phone').val();
            if (!phoneRegex.test(phone)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Phone Number',
                    text: 'Please enter a valid phone number!'
                });
                return false;
            }

            // Password match validation
            if (password !== confirmPassword) {
                Swal.fire({
                    icon: 'error',
                    title: 'Password Mismatch',
                    text: 'Passwords do not match!'
                });
                return false;
            }

            // Company validation
            if (!$('#company_id').val()) {
                Swal.fire({
                    icon: 'error',
                    title: 'Company Required',
                    text: 'Please select a company!'
                });
                return false;
            }

            // Show loading state
            submitBtn.prop('disabled', true).text('Creating Account...');

            // Create FormData object to handle file uploads
            const formData = new FormData(form[0]);
            
            // Submit form using jQuery AJAX
            $.ajax({
                url: '../api/add_driver.php',
                type: 'POST',
                data: formData,
                headers: {
                    'X-CSRF-TOKEN': $('#csrf_token').val()
                },
                processData: false,  // Don't process the data
                contentType: false,  // Don't set content type (browser will set it with boundary)
                dataType: 'json',
                success: function(result) {
                    console.log('Success:', result);
                    if (result.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: 'Driver account created successfully!',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            form[0].reset();
                            window.location.href = 'users.php';
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: result.message || 'Failed to create driver account'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'An error occurred while creating the account.'
                    });
                },
                complete: function() {
                    // Reset button state
                    submitBtn.prop('disabled', false).text('Create Driver Account');
                }
            });

            return false;
        }

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
