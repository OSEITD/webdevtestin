<?php
session_start();


if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: login.php');
    exit;
}

require_once dirname(__DIR__) . '/api/supabase-client.php';

$outlet_id = $_GET['id'] ?? null;
if (!$outlet_id) {
    header('Location: outlets.php');
    exit;
}

// Fetch outlet data
$outlet = callSupabaseWithServiceKey("outlets?id=eq.{$outlet_id}", 'GET');
if (empty($outlet)) {
    die('Outlet not found.');
}
$outlet = $outlet[0];

// Fetch companies for the dropdown
$companies = callSupabaseWithServiceKey('companies', 'GET');

$pageTitle = 'Admin - Edit-Outlet';
require_once '../includes/header.php';
?>

    <div class="mobile-dashboard">
        <main class="main-content">
            <div class="content-header">
                <h1>Edit Outlet</h1>
            </div>

            <div class="form-container">
                <h2>Outlet Details</h2>
                <form id="editOutletForm">
                    <input type="hidden" id="csrf_token" value="<?php echo CSRFHelper::getToken(); ?>">
                    <input type="hidden" id="outletId" value="<?php echo htmlspecialchars($outlet['id']); ?>">
                    <div class="form-group">
                        <label for="outletName">Outlet Name</label>
                        <input type="text" id="outletName" class="form-input-field" placeholder="e.g., Downtown Branch" required value="<?php echo htmlspecialchars($outlet['outlet_name']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="company">Associated Company</label>
                        <select id="company" class="form-select-field" required>
                            <option value="">Select a Company</option>
                            <?php foreach ($companies as $company): ?>
                                <option value="<?php echo htmlspecialchars($company['id']); ?>" <?php echo ($outlet['company_id'] == $company['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($company['company_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="contactPerson">Contact Person</label>
                        <input type="text" id="contactPerson" class="form-input-field" placeholder="e.g., John Smith" required value="<?php echo htmlspecialchars($outlet['contact_person']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="contactEmail">Contact Email</label>
                        <input type="email" id="contactEmail" class="form-input-field" placeholder="e.g., john.smith@outlet.com" required value="<?php echo htmlspecialchars($outlet['contact_email']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="contactPhone">Contact Phone</label>
                        <input type="tel" id="contactPhone" class="form-input-field" placeholder="e.g., +1234567890" value="<?php echo htmlspecialchars($outlet['contact_phone']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" class="form-textarea-field" placeholder="Full outlet address" required><?php echo htmlspecialchars($outlet['address']); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" class="form-select-field">
                            <option value="active" <?php echo ($outlet['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($outlet['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn cancel-btn" id="cancelBtn">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn save-btn" id="saveChangesBtn">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        document.getElementById('editOutletForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const outletId = document.getElementById('outletId').value;
            const outletName = document.getElementById('outletName').value;
            const company = document.getElementById('company').value;
            const contactPerson = document.getElementById('contactPerson').value;
            const contactEmail = document.getElementById('contactEmail').value;
            const contactPhone = document.getElementById('contactPhone').value;
            const address = document.getElementById('address').value;
            const status = document.getElementById('status').value;

            try {
                const response = await fetch('../api/update_outlet.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.getElementById('csrf_token').value
                    },
                    body: JSON.stringify({
                        id: outletId,
                        outlet_name: outletName.trim(),
                        company_id: company.trim(),
                        address: address.trim(),
                        contact_person: contactPerson.trim(),
                        contact_phone: contactPhone ? contactPhone.trim() : '',
                        contact_email: contactEmail.trim(),
                        status: status
                    })
                });
                const result = await response.json();
                if (result.success) {
                    window.location.href = `view-outlet.php?id=${encodeURIComponent(outletId)}`;
                } else {
                    throw new Error(result.error);
                }
            } catch (error) {
                alert('Error updating outlet: ' + error.message);
            }
        });

        document.getElementById('cancelBtn').addEventListener('click', function() {
            window.history.back();
        });
    </script>
</body>
</html>
