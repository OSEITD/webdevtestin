<?php
// Include necessary files and check authentication
require_once __DIR__ . '/../api/supabase-client.php';

$pageTitle = 'Admin - Add Admin ';
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

        /* Alert styles */
        .alert {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            max-width: 400px;
            z-index: 1000;
            animation: slideIn 0.3s ease-out;
        }

        .alert-success {
            background-color: #34d399;
            color: white;
            border-left: 4px solid #059669;
        }

        .alert-error {
            background-color: #f87171;
            color: white;
            border-left: 4px solid #dc2626;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .submit-btn:disabled {
            background-color: #9ca3af;
            cursor: not-allowed;
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
                <h1>Add New Admin</h1>
            </div>

            <div class="form-container">
                <form id="addAdminForm" onsubmit="return handleSubmit(event)">
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" name="name" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" required>
                    </div>

                    <div class="form-group">
                        <label for="phone">Contact</label>
                        <input type="tel" id="phone" name="phone" required>
                    </div>

                    <div class="form-group">
                        <label for="address">Address</label>
                        <input type="text" id="address" name="address" required>
                    </div>

                     <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required>
                    </div>

                     <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>

                    <button type="submit" class="submit-btn" id="submitBtn">Create Admin Account</button>
                </form>
            </div>
        </main>
    </div>

    <script>
       

        function handleSubmit(e) {
            e.preventDefault();
            
            const form = $('#addAdminForm');
            const submitBtn = $('#submitBtn');
            const password = $('#password').val();
            const confirmPassword = $('#confirm_password').val();

            // First, check if passwords match
            if (password !== confirmPassword) {
                Swal.fire({
                    icon: 'error',
                    title: 'Password Mismatch',
                    text: 'Passwords do not match!'
                });
                return false;
            }

            // Show loading state
            submitBtn.prop('disabled', true).text('Creating Account...');

            // Submit form using jQuery AJAX
            $.ajax({
                url: '../api/add_admin.php',
                type: 'POST',
                data: form.serialize(),
                dataType: 'json',
                success: function(result) {
                    console.log('Success:', result);
                    if (result.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: 'Admin account created successfully!',
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
                            text: result.message || 'Failed to create admin account'
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
                    submitBtn.prop('disabled', false).text('Create Admin Account');
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
        })();
    </script>
</body>
</html>
