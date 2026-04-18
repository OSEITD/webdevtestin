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
require_once __DIR__ . '/../includes/header.php';
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
                <form id="addDriverForm" novalidate>
                    <input type="hidden" id="csrf_token" value="<?php echo CSRFHelper::getToken(); ?>">
                    <!-- Company Selection -->
                    <div class="form-section">
                        <h2>Company Information</h2>
                        <div class="form-group">
                            <label for="company_id">Select Company <span class="required">*</span></label>
                            <select id="company_id" name="company_id" class="form-input-field" required>
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
                            <label for="driver_name">Driver Name <span class="required">*</span></label>
                            <input type="text" id="driver_name" name="driver_name" class="form-input-field" required placeholder="Enter full name">
                        </div>

                        <div class="grid-2">
                            <div class="form-group">
                                <label for="driver_email">Email <span class="required">*</span></label>
                                <input type="email" id="driver_email" name="driver_email" class="form-input-field" required>
                            </div>

                            <div class="form-group">
                                <label for="driver_phone">Phone Number <span class="required">*</span></label>
                                <input type="tel" id="driver_phone" name="driver_phone" class="form-input-field" required>
                            </div>
                        </div>

                        <div class="grid-2">
                            <div class="form-group">
                                <label for="password">Password <span class="required">*</span></label>
                                <input type="password" id="password" name="password" class="form-input-field" placeholder="8-16 characters" minlength="8" maxlength="16" required>
                            </div>

                            <div class="form-group">
                                <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                                <input type="password" id="confirm_password" name="confirm_password" class="form-input-field" placeholder="Re-enter password" minlength="8" maxlength="16" required>
                            </div>
                        </div>
                    </div>
                   
                    <button type="submit" class="submit-btn" id="submitBtn">Create Driver Account</button>
                </form>
            </div>
        </main>
    </div>

    <!-- Form Validator -->
    <script src="../assets/js/form-validator.js?v=2"></script>
    <script>
        const validator = new FormValidator('addDriverForm', {
            company_id:       { required: true },
            driver_name:      { required: true, minLength: 2, maxLength: 100 },
            driver_email:     { required: true, email: true },
            driver_phone:     { required: true, phone: true },
            password:         { required: true, password: true, minLength: 8, maxLength: 16 },
            confirm_password: { required: true, password: true, match: 'password' }
        });

        document.getElementById('addDriverForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            if (!validator.validateAll()) return;

            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Creating Account...';

            try {
                const phoneDigits = document.getElementById('driver_phone').value.replace(/\s/g, '');
                const selectedCountry = validator._phoneCountrySelections['driver_phone'] || 'ZM';
                const country = COUNTRY_CODES.find(c => c.code === selectedCountry);
                const fullPhone = country ? country.dial + phoneDigits : '+260' + phoneDigits;

                const formData = new FormData();
                formData.append('company_id', document.getElementById('company_id').value);
                formData.append('driver_name', document.getElementById('driver_name').value);
                formData.append('driver_email', document.getElementById('driver_email').value);
                formData.append('driver_phone', fullPhone);
                formData.append('password', document.getElementById('password').value);
                formData.append('confirm_password', document.getElementById('confirm_password').value);

                const response = await fetch('../api/add_driver.php', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.getElementById('csrf_token').value },
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Driver Created!',
                        text: 'Driver account created successfully!',
                        confirmButtonColor: '#2e0d2a',
                        timer: 2500,
                        timerProgressBar: true
                    }).then(() => { window.location.href = 'users.php'; });
                } else {
                    if (result.errors) validator.applyServerErrors(result.errors);
                    Swal.fire({ icon: 'error', title: 'Error', text: result.message || 'Failed to create driver account', confirmButtonColor: '#2e0d2a' });
                }
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'Connection Error', text: 'Could not connect to the server.', confirmButtonColor: '#2e0d2a' });
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Create Driver Account';
            }
        });
    </script>
  
<?php include __DIR__ . '/../includes/footer.php'; ?>
