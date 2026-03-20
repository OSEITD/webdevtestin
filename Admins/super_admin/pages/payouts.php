<?php
session_start();
require_once __DIR__ . '/../api/supabase-client.php';
require_once __DIR__ . '/../includes/WalletManager.php';

// Check if user is logged in
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: ../auth/login.php');
    exit;
}

$pageTitle = 'Admin - Payout Approvals';

// Fetch all payouts with company names (joined via PostgREST relationship)
$payouts = [];
try {
    // Use Supabase relationship query to include the company name in a single request
    $payoutsResponse = callSupabaseWithServiceKey('company_payouts?select=*,companies(company_name)&order=requested_at.desc', 'GET');
    if (is_array($payoutsResponse)) {
        foreach ($payoutsResponse as &$p) {
            $p['company_name'] = $p['companies']['company_name'] ?? 'Unknown Company';
        }
        $payouts = $payoutsResponse;
    }
} catch (Exception $e) {
    error_log("Error fetching payouts: " . $e->getMessage());
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mobile-dashboard">
    <main class="main-content">
        <div class="content-header">
            <h1>Payout Requests</h1>
            <!-- Optional: Export button -->
            <button class="btn btn-outline" onclick="window.print()"><i class="fas fa-print"></i> Print Records</button>
        </div>

        <div class="card">
            <div class="card-header">
                <h2>All Transactions</h2>
            </div>

            <div class="filter-bar" style="display:flex; gap:10px; align-items:center; margin-bottom:15px;">
                <label for="payoutFilterInput" style="font-weight:600; margin:0;">Filter:</label>
                <input id="payoutFilterInput" type="text" placeholder="Search company, amount, method, status..." style="flex:1; padding:8px 10px; border:1px solid #ccc; border-radius:4px;" />
            </div>
            
            <div class="tabs-container">
                <div class="tabs">
                    <button class="tab-btn active" data-status="all">All</button>
                    <button class="tab-btn" data-status="pending">Pending</button>
                    <button class="tab-btn" data-status="approved">Approved</button>
                    <button class="tab-btn" data-status="processing">Processing</button>
                    <button class="tab-btn" data-status="completed">Completed</button>
                    <button class="tab-btn" data-status="failed">Failed / Cancelled</button>
                </div>
            </div>

            <div class="table-scrollable overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Requested At</th>
                            <th>Company</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payouts)): ?>
                            <tr>
                                <td colspan="6" style="text-align:center;">No payout requests found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($payouts as $payout): ?>
                                <tr class="payout-row" data-status="<?php echo htmlspecialchars($payout['status']); ?>">
                                    <td><?php echo date('M d, Y H:i', strtotime($payout['requested_at'])); ?></td>
                                    <td><strong><?php echo htmlspecialchars($payout['company_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars(($payout['currency'] ?? 'K') . ' ' . number_format($payout['amount'], 2)); ?></td>
                                    <td>
                                        <span class="badge-pill badge-info"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $payout['payout_method']))); ?></span>
                                    </td>
                                    <td>
                                        <?php 
                                            $stClass = 'badge-secondary';
                                            if ($payout['status'] === 'completed') $stClass = 'badge-success';
                                            elseif ($payout['status'] === 'pending') $stClass = 'badge-warning';
                                            elseif ($payout['status'] === 'failed' || $payout['status'] === 'cancelled') $stClass = 'badge-danger';
                                            elseif ($payout['status'] === 'approved') $stClass = 'badge-primary';
                                        ?>
                                        <span class="badge-pill <?php echo $stClass; ?>">
                                            <?php echo ucfirst(htmlspecialchars($payout['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline view-details-btn" 
                                            data-id="<?php echo $payout['id']; ?>"
                                            data-company="<?php echo htmlspecialchars($payout['company_id']); ?>"
                                            data-companyname="<?php echo htmlspecialchars($payout['company_name']); ?>"
                                            data-amount="<?php echo htmlspecialchars($payout['amount']); ?>"
                                            data-currency="<?php echo htmlspecialchars($payout['currency'] ?? 'K'); ?>"
                                            data-method="<?php echo htmlspecialchars($payout['payout_method']); ?>"
                                            data-status="<?php echo htmlspecialchars($payout['status']); ?>"
                                            data-notes="<?php echo htmlspecialchars($payout['notes'] ?? ''); ?>"
                                            data-failure_reason="<?php echo htmlspecialchars($payout['failure_reason'] ?? ''); ?>"
                                            data-details="<?php 
                                            // Handle various payment details formats
                                            $detailsStr = '';
                                            if (!empty($payout['bank_name'])) $detailsStr .= "Bank: {$payout['bank_name']} \n";
                                            if (!empty($payout['account_name'])) $detailsStr .= "Name: {$payout['account_name']} \n";
                                            if (!empty($payout['account_number'])) $detailsStr .= "Acc: {$payout['account_number']} \n";
                                            if (!empty($payout['mobile_number'])) $detailsStr .= "Mobile: {$payout['mobile_number']} \n";
                                            echo htmlspecialchars($detailsStr);
                                            ?>">
                                            View & Process
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- Modal for Payout Processing -->
<div id="payoutModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <span class="close-btn" id="closeModal">&times;</span>
        <h2>Process Payout</h2>
        
        <div class="mb-4">
            <h3 id="modalCompanyName" style="margin-top: 10px;"></h3>
            <p style="font-size: 1.5rem; font-weight: bold; margin: 10px 0;" id="modalAmount"></p>
            
            <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <strong>Payment Destination:</strong> <span id="modalMethod"></span><br>
                <pre id="modalDetails" style="margin-top:10px; font-family: inherit; font-size:0.9rem;"></pre>
            </div>
            
            <div class="form-group">
                <label>Notes</label>
                <p style="margin-top:8px; font-size:0.9rem; color:#555;">
                    Payouts use the Lenco gateway and must be <strong>at least K5</strong>. Any attempt to send less will fail.
                </p>
            </div>

            <form id="processPayoutForm">
                <input type="hidden" id="payoutId" name="payout_id">
                <input type="hidden" id="companyId" name="company_id">
                <input type="hidden" id="csrf_token" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                
                <div class="form-group">
                    <label>Action to Take</label>
                    <select id="payoutAction" name="status" class="form-control" required>
                        <option value="">Select Action...</option>
                        <option value="approved">Approve (Mark as Approved)</option>
                        <option value="processing">Processing (Funds being sent)</option>
                        <option value="completed">Complete (Send payout)</option>
                        <option value="failed">Fail (Return funds to available balance)</option>
                        <option value="cancelled">Cancel (Return funds)</option>
                    </select>
                </div>
                
                <div class="form-group" style="margin-top: 15px">
                    <label>External Reference (if completed)</label>
                    <input type="text" id="payoutRef" name="reference" class="form-control" placeholder="e.g. Bank Txd ID">
                </div>

                <div class="form-group" style="margin-top: 15px">
                    <label>Admin Notes</label>
                    <textarea id="payoutNotes" name="notes" class="form-control" rows="2" placeholder="Visible internally logs"></textarea>
                </div>

                <div id="failureReasonField" class="form-group" style="margin-top: 15px; display: none;">
                    <label>Failure Reason</label>
                    <textarea id="failureReason" name="failure_reason" class="form-control" rows="2" placeholder="Reason for failure (optional)"></textarea>
                </div>

                <div class="form-actions" style="margin-top: 20px;">
                    <button type="button" class="btn btn-outline" id="cancelProcess">Close</button>
                    <button type="submit" class="btn btn-primary" id="saveProcess">Save Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Basic enhancements for badges/tabs to match standard admin */
.tabs-container { margin-bottom: 20px; border-bottom: 1px solid #ddd;}
.tab-btn { background: none; border: none; padding: 10px 20px; cursor: pointer; font-size:1rem; border-bottom: 2px solid transparent;}
.tab-btn.active { border-bottom: 2px solid var(--primary-color); font-weight: bold; color: var(--primary-color);}
.badge-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    position: relative;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.85em;
    font-weight: 500;
}

/* Allow longer method names to wrap if needed */
.data-table td span {
    white-space: normal;
    word-break: break-word;
}

.badge-secondary { background: #eee; color: #333; }
.badge-warning { background: #ffeeba; color: #856404; }
.badge-success { background: #d4edda; color: #155724; }
.badge-danger { background: #f8d7da; color: #721c24; }
.badge-primary { background: #cce5ff; color: #004085; }
.badge-info { background: #d1ecf1; color: #0c5460; }
.table-scrollable { max-height: 70vh; overflow-y: auto; overflow-x: auto; }
.overflow-x-auto { overflow-x: auto; }
.form-group { margin-bottom: 15px; }
.form-control { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; }
.form-actions { display: flex; gap: 10px; justify-content: flex-end; }

/* Center modal in viewport */
.modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.45);
    align-items: center;
    justify-content: center;
    padding: 1rem;
    z-index: 1000;
}

.modal-content {
    width: 100%;
    max-width: 520px;
    max-height: 90vh;
    overflow: auto;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    position: relative;
}

.close-btn {
    position: absolute;
    top: 10px;
    right: 12px;
    font-size: 1.5rem;
    color: #444;
    cursor: pointer;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Tab switching logic
    const tabs = document.querySelectorAll('.tab-btn');
    const rows = document.querySelectorAll('.payout-row');
    const filterInput = document.getElementById('payoutFilterInput');

    const filterRows = () => {
        const targetStatus = document.querySelector('.tab-btn.active')?.getAttribute('data-status') || 'all';
        const searchText = (filterInput?.value || '').trim().toLowerCase();

        rows.forEach(row => {
            const status = row.getAttribute('data-status');
            const rowText = row.textContent.toLowerCase();

            const statusMatch = (targetStatus === 'all' || status === targetStatus);
            const textMatch = !searchText || rowText.includes(searchText);

            row.style.display = (statusMatch && textMatch) ? '' : 'none';
        });
    };

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            filterRows();
        });
    });

    if (filterInput) {
        filterInput.addEventListener('input', filterRows);
    }

    filterRows();

    // Modal Logic
    const modal = document.getElementById('payoutModal');
    const closeBtn = document.getElementById('closeModal');
    const cancelBtn = document.getElementById('cancelProcess');
    
    document.querySelectorAll('.view-details-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('payoutId').value = this.getAttribute('data-id');
            document.getElementById('companyId').value = this.getAttribute('data-company');
            document.getElementById('modalCompanyName').textContent = this.getAttribute('data-companyname');
            document.getElementById('modalAmount').textContent = this.getAttribute('data-currency') + ' ' + Number(this.getAttribute('data-amount')).toLocaleString(undefined, {minimumFractionDigits: 2});
            document.getElementById('modalMethod').textContent = this.getAttribute('data-method').replace('_', ' ').toUpperCase();
            document.getElementById('modalDetails').textContent = this.getAttribute('data-details') || 'No additional details provided.';
            
            // Set current status
            const currStatus = this.getAttribute('data-status');
            const selectAction = document.getElementById('payoutAction');
            const saveBtn = document.getElementById('saveProcess');

            // if completed or failed, disable the form mostly.
            if(currStatus === 'completed' || currStatus === 'failed' || currStatus === 'cancelled') {
                selectAction.value = currStatus;
                selectAction.disabled = true;
                saveBtn.disabled = true;
            } else {
                selectAction.value = '';
                selectAction.disabled = false;
                saveBtn.disabled = false;
            }

            // Ensure the form reflects the current state (e.g. show failure reason when needed)
            updateSaveButtonText();

            document.getElementById('payoutNotes').value = this.getAttribute('data-notes');
            document.getElementById('failureReason').value = this.getAttribute('data-failure_reason') || '';
            document.getElementById('payoutRef').value = '';

            // No payout preview lookup is performed; the payout will be executed when marked completed.
            modal.style.display = 'flex';
        });
    });

    const closeModal = () => { modal.style.display = 'none'; };
    closeBtn.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);

    // Form Submission
    const saveBtn = document.getElementById('saveProcess');
    const payoutActionSelect = document.getElementById('payoutAction');

    const updateSaveButtonText = () => {
        if (!saveBtn || !payoutActionSelect) return;
        const status = payoutActionSelect.value;

        // Show failure reason input only when relevant
        const failureField = document.getElementById('failureReasonField');
        if (failureField) {
            failureField.style.display = (status === 'failed' || status === 'cancelled') ? 'block' : 'none';
        }

        if (status === 'completed') {
            saveBtn.textContent = 'Confirm & Execute Payout';
        } else {
            saveBtn.textContent = 'Save Update';
        }
    };

    if (payoutActionSelect) {
        payoutActionSelect.addEventListener('change', updateSaveButtonText);
        updateSaveButtonText();
    }

    document.getElementById('processPayoutForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const status = payoutActionSelect?.value;
        if (!status) {
            alert('Please select an action before saving.');
            return;
        }

        if (!confirm('Are you sure you want to apply this status update?')) return;
        
        saveBtn.disabled = true;
        saveBtn.textContent = 'Processing...';

        const formData = new FormData(this);
        const data = Object.fromEntries(formData.entries());

        fetch('../api/process_payout.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': data.csrf_token
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            if (result.status === 'success') {
                alert('Payout updated successfully');
                location.reload();
            } else {
                alert(result.message || 'Error occurred updating the payout');
                saveBtn.disabled = false;
                updateSaveButtonText();
            }
        })
        .catch(err => {
            console.error('Error:', err);
            alert('An unexpected error occurred.');
            saveBtn.disabled = false;
            updateSaveButtonText();
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>