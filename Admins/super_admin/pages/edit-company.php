<?php
session_start();
require_once '../api/supabase-client.php';

// Check if user is logged in and has super_admin role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: login.php');
    exit;
}

// Get company ID from URL parameter
$companyId = isset($_GET['id']) ? $_GET['id'] : null;

if (!$companyId) {
    header('Location: companies.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $updateData = [
            'company_name' => $_POST['name'],
            'contact_person' => $_POST['contact_person'],
            'contact_email' => $_POST['contact_email'],
            'contact_phone' => $_POST['contact_phone'],
            'address' => $_POST['address'],
            'status' => $_POST['status'],
            'subdomain' => $_POST['subdomain'],
            'commission' => floatval($_POST['commission'] ?? 0)
        ];

        // Update company in Supabase (include query in endpoint to provide WHERE clause)
        $result = callSupabaseWithServiceKey("companies?id=eq.{$companyId}", 'PATCH', $updateData);
        
        if ($result) {
            header("Location: view-company.php?id={$companyId}&success=1");
            exit;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

try {
    // Fetch company details from Supabase
    $company = callSupabase("companies?id=eq.{$companyId}&select=*");
    
    if (!$company || empty($company)) {
        throw new Exception('Company not found');
    }
    
    // Get the first company since we're querying by ID
    $company = $company[0];
    
} catch (Exception $e) {
    header('Location: companies.php?error=' . urlencode($e->getMessage()));
    exit;
}

$pageTitle = 'Admin - Edit-Company';
require_once '../includes/header.php';
?>

    <div class="mobile-dashboard">
        <!-- Main Content Area -->
        <main class="main-content">
            <div class="content-header">
                <h1>Edit Company</h1>
            </div>

            <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <div class="edit-form-container">
                <form method="POST" class="edit-form" id="editCompanyForm">
                    <div class="form-group">
                        <label for="name">Company Name*</label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($company['company_name'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="subdomain">Subdomain*</label>
                        <input type="text" id="subdomain" name="subdomain" value="<?php echo htmlspecialchars($company['subdomain'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="contact_person">Contact Person*</label>
                        <input type="text" id="contact_person" name="contact_person" value="<?php echo htmlspecialchars($company['contact_person'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Contact Email*</label>
                        <input type="email" id="contact_email" name="contact_email" value="<?php echo htmlspecialchars($company['contact_email'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="contact_phone" name="contact_phone" value="<?php echo htmlspecialchars($company['contact_phone'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" rows="3"><?php echo htmlspecialchars($company['address'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="commission">Commission (%)</label>
                        <input type="number" id="commission" name="commission" value="<?php echo htmlspecialchars($company['commission'] ?? 0); ?>" step="0.01" min="0" max="100">
                        <small class="help-text">Commission percentage for this company</small>
                    </div>

                    <div class="form-group">
                        <label for="status">Status*</label>
                        <select id="status" name="status" required>
                            <option value="active" <?php echo (($company['status'] ?? '') === 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="suspended" <?php echo (($company['status'] ?? '') === 'suspended') ? 'selected' : ''; ?>>Suspended</option>
                            <option value="inactive" <?php echo (($company['status'] ?? '') === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>


                    <div class="button-group">
                        <button type="button" class="secondary-btn" onclick="window.location.href='view-company.php?id=<?php echo urlencode($companyId); ?>'">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="primary-btn">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        // Form validation
        document.getElementById('editCompanyForm').addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            const email = document.getElementById('contact_email').value.trim();
            const subdomain = document.getElementById('subdomain').value.trim();
            
            if (!name || !email || !subdomain) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return;
            }

            // Email validation
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailPattern.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address.');
                return;
            }

            // Subdomain validation (letters, numbers, and hyphens only)
            const subdomainPattern = /^[a-z0-9-]+$/;
            if (!subdomainPattern.test(subdomain)) {
                e.preventDefault();
                alert('Subdomain can only contain lowercase letters, numbers, and hyphens.');
                return;
            }
        });

        // Add unsaved changes warning
        let formChanged = false;
        const form = document.getElementById('editCompanyForm');
        const formInputs = form.querySelectorAll('input, textarea, select');

        formInputs.forEach(input => {
            input.addEventListener('change', () => {
                formChanged = true;
            });
        });

        window.addEventListener('beforeunload', (e) => {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        // Clear the form changed flag when submitting
        form.addEventListener('submit', () => {
            formChanged = false;
        });
    </script>

    <style>
        .edit-form-container {
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin: 20px;
        }

        .edit-form {
            display: grid;
            gap: 20px;
            max-width: 800px;
            margin: 0 auto;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-weight: 500;
            color: #374151;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 10px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .button-group {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .primary-btn,
        .secondary-btn {
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .primary-btn {
            background-color: #2563eb;
            color: white;
            border: none;
        }

        .secondary-btn {
            background-color: #f3f4f6;
            color: #374151;
            border: 1px solid #d1d5db;
        }

        .primary-btn:hover {
            background-color: #1d4ed8;
        }

        .secondary-btn:hover {
            background-color: #e5e7eb;
        }

        .alert {
            padding: 12px;
            border-radius: 6px;
            margin: 20px;
        }

        .alert-error {
            background-color: #fee2e2;
            border: 1px solid #ef4444;
            color: #b91c1c;
        }
    </style>
    <script>
        // Menu handled by admin-scripts.js
    </script>
</body>
</html>
