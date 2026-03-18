<?php
$page_title = 'Company - Wallet & Payouts';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/WalletManager.php';

$companyId = $_SESSION['company_id'] ?? $_SESSION['id'] ?? null;
if (!$companyId) {
    die("<div class='container mt-5'><h3>Unauthorized access. Please log in properly.</h3></div>");
}

$wallet = CompanyWalletManager::getWallet($companyId);
$availableBalance = $wallet ? floatval($wallet['available_balance']) : 0.00;
$pendingBalance = $wallet ? floatval($wallet['pending_balance']) : 0.00;
$totalEarned = $wallet ? floatval($wallet['total_earned']) : 0.00;

$payouts = CompanyWalletManager::getPayouts($companyId, 20);
$transactions = CompanyWalletManager::getTransactions($companyId, 30);

    //  payout method / saved payout details from the company record.
    $defaultPayoutMethod = 'bank_transfer';
    $defaultBankName = '';
    $defaultBankAccountNumber = '';
    $defaultBankAccountName = '';
    $defaultMobileNumber = '';

    try {
        $client = new SupabaseClient();
        $accessToken = $_SESSION['access_token'] ?? null;
        $companyRes = $accessToken ? $client->getCompany($companyId, $accessToken) : $client->get('companies?id=eq.' . $companyId);

       
        $companyRecord = null;
        if (is_array($companyRes) && isset($companyRes[0])) {
            $companyRecord = $companyRes[0];
        } elseif (is_object($companyRes) && isset($companyRes->data) && is_array($companyRes->data) && isset($companyRes->data[0])) {
            $companyRecord = $companyRes->data[0];
        }

        if (is_array($companyRecord)) {
            $defaultPayoutMethod = $companyRecord['payout_method'] ?? $defaultPayoutMethod;
            $defaultBankName = $companyRecord['bank_name'] ?? '';
            $defaultBankAccountNumber = $companyRecord['bank_account_number'] ?? '';
            $defaultBankAccountName = $companyRecord['bank_account_name'] ?? '';
            $defaultMobileNumber = $companyRecord['mobile_money_number'] ?? '';
        }
    } catch (Exception $e) {
       
        error_log('Unable to fetch company payout info: ' . $e->getMessage());
    }
    $currencyStr = $companyRecord['currency'] ?? ($_SESSION['company_currency'] ?? 'K');
   
    $_SESSION['company_currency'] = $currencyStr;
?>
<link rel="stylesheet" href="../assets/css/company.css">
<style>
    .wallet-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }
    
    .wallet-header h1 {
        font-size: 1.5rem;
        font-weight: 600;
        color: #2c3e50;
        margin: 0;
    }
    
    .request-payout-btn {
        background-color: #27ae60;
        color: #fff;
        border: none;
        padding: 0.6rem 1.2rem;
        border-radius: 8px;
        font-weight: 500;
        cursor: pointer;
        transition: background-color 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .request-payout-btn:hover {
        background-color: #219653;
    }
    
    .balances-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .balance-card {
        background: #fff;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    
    .balance-card .label {
        font-size: 0.95rem;
        color: #7f8c8d;
        margin-bottom: 0.5rem;
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: 0.5px;
    }
    
    .balance-card .amount {
        font-size: 2.2rem;
        font-weight: 700;
        color: #2c3e50;
        margin: 0;
    }
    
    .balance-card .amount.available {
        color: #27ae60;
    }
    
    .wallet-tabs {
        display: flex;
        gap: 1rem;
        border-bottom: 2px solid #e2e8f0;
        margin-bottom: 1.5rem;
    }
    
    .wallet-tab {
        padding: 0.8rem 1.5rem;
        font-weight: 600;
        color: #7f8c8d;
        cursor: pointer;
        border-bottom: 3px solid transparent;
        margin-bottom: -2px;
        transition: all 0.2s;
    }
    
    .wallet-tab.active {
        color: #3498db;
        border-bottom-color: #3498db;
    }
    
    .tab-content {
        display: none;
    }
    
    .tab-content.active {
        display: block;
    }
    
    .data-table-wrapper {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        overflow-x: auto;
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
    }
    
    .data-table th, .data-table td {
        padding: 1rem 1.2rem;
        border-bottom: 1px solid #f1f5f9;
        font-size: 0.95rem;
    }
    
    .data-table th {
        background-color: #f8fafc;
        color: #64748b;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.85rem;
        letter-spacing: 0.5px;
    }
    
    .data-table tbody tr:hover {
        background-color: #f8fafc;
    }
    
    .badge-status {
        padding: 0.35rem 0.75rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        display: inline-block;
        text-transform: capitalize;
    }
    
    .badge-pending { background-color: #fef08a; color: #b45309; }
    .badge-processing { background-color: #bfdbfe; color: #1d4ed8; }
    .badge-completed { background-color: #bbf7d0; color: #15803d; }
    .badge-failed { background-color: #fecaca; color: #b91c1c; }
    .badge-credit { background-color: #d1fae5; color: #166534; }
    .badge-debit { background-color: #fee2e2; color: #991b1b; }

    /* Modal Styling */
    .modal-overlay {
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.5);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        padding: 1rem;
    }
    
    .modal-content {
        background: #fff;
        border-radius: 12px;
        width: 100%;
        max-width: 450px;
        padding: 2rem;
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        position: relative;
    }
    
    .modal-content h3 {
        margin-top: 0;
        margin-bottom: 1.5rem;
        color: #2c3e50;
    }
    
    .form-group {
        margin-bottom: 1.2rem;
    }
    
    .form-group label {
        display: block;
        font-weight: 500;
        margin-bottom: 0.4rem;
        color: #34495e;
    }
    
    .form-group input, .form-group textarea {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font-family: inherit;
        font-size: 1rem;
    }
    
    .form-group input:focus, .form-group textarea:focus {
        outline: none;
        border-color: #3498db;
        box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
    }
    
    .modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 1rem;
        margin-top: 2rem;
    }
    
    .btn-cancel {
        background: #f1f5f9;
        color: #64748b;
        border: none;
        padding: 0.6rem 1.2rem;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 500;
        transition: 0.2s;
    }
    .btn-cancel:hover { background: #e2e8f0; }
    
    .btn-submit {
        background: #3498db;
        color: white;
        border: none;
        padding: 0.6rem 1.2rem;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 500;
        transition: 0.2s;
    }
    .btn-submit:hover { background: #2980b9; }

    .help-text {
        font-size: 0.85rem;
        color: #7f8c8d;
        margin-top: 0.2rem;
        display: block;
    }
</style>

<div class="mobile-dashboard">
    <main class="main-content">
        <div class="wallet-header">
            <h1>Wallet & Payouts</h1>
            <button class="request-payout-btn" onclick="openPayoutModal()">
                <i class="fas fa-hand-holding-usd"></i> Request Payout
            </button>
        </div>

        <!-- Balances Grid -->
        <div class="balances-grid">
            <div class="balance-card">
                <div class="label">Available Balance</div>
                <div class="amount available"><?php echo $currencyStr; ?><?php echo number_format($availableBalance, 2); ?></div>
            </div>
            <div class="balance-card">
                <div class="label">Pending Withdrawals</div>
                <div class="amount"><?php echo $currencyStr; ?><?php echo number_format($pendingBalance, 2); ?></div>
            </div>
            <div class="balance-card">
                <div class="label">Total Earned</div>
                <div class="amount"><?php echo $currencyStr; ?><?php echo number_format($totalEarned, 2); ?></div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="wallet-tabs">
            <div class="wallet-tab active" onclick="switchThemeTab(event, 'transactions')">Recent Transactions</div>
            <div class="wallet-tab" onclick="switchThemeTab(event, 'payouts')">Payout History</div>
        </div>

        <!-- Transactions Tab -->
        <div id="tab-transactions" class="tab-content active">
            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Amount</th>
                            <th>Type</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transactions)): ?>
                            <tr><td colspan="4" style="text-align: center; padding: 2rem;">No recent transactions found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($transactions as $tx): ?>
                                <tr>
                                    <td><?php echo date('M j, Y h:i A', strtotime($tx['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($tx['description']); ?>
                                        <div style="font-size: 0.8rem; color: #94a3b8;">
                                            <?php echo ucfirst(htmlspecialchars($tx['type'] ?? '')); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                            $amt = $tx['amount'] ?? 0;
                                            $decimals = ($tx['transaction_type'] ?? '') === 'commission_debit' ? 3 : 2;
                                            $formatted = $currencyStr . number_format(abs($amt), $decimals);
                                        ?>
                                        <?php if ($amt > 0): ?>
                                            <span style="color: #16a085; font-weight: 600;">+ <?php echo $formatted; ?></span>
                                        <?php else: ?>
                                            <span style="color: #e74c3c; font-weight: 600;">- <?php echo $formatted; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge-status badge-<?php echo strtolower($tx['transaction_type'] ?? 'unknown'); ?>">
                                            <?php echo htmlspecialchars($tx['transaction_type'] ?? 'unknown'); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Payouts Tab -->
        <div id="tab-payouts" class="tab-content">
            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Bank Details</th>
                            <th>Status</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payouts)): ?>
                            <tr><td colspan="5" style="text-align: center; padding: 2rem;">No payout history found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($payouts as $p): ?>
                                <tr>
                                    <td><?php echo date('M j, Y h:i A', strtotime($p['created_at'])); ?></td>
                                    <td style="font-weight: 600;"><?php echo $currencyStr . number_format($p['amount'], 2); ?></td>
                                    <td><?php echo nl2br(htmlspecialchars($p['notes'] ?? '')); ?></td>
                                    <td>
                                        <span class="badge-status badge-<?php echo strtolower($p['status'] ?? 'unknown'); ?>">
                                            <?php echo htmlspecialchars($p['status'] ?? 'unknown'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($p['admin_notes'] ?? '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- Modal for Requesting Payout -->
<div id="payoutModal" class="modal-overlay">
    <div class="modal-content">
        <h3>Request Payout</h3>
        
        <div class="form-group">
            <label>Available to Withdraw</label>
            <input type="text" value="<?php echo $currencyStr . number_format($availableBalance, 2); ?>" readonly disabled style="background:#f8fafc; color:#64748b; font-weight:bold;">
        </div>

        <div class="form-group">
            <label for="payoutAmount">Amount (<?php echo $currencyStr; ?>)</label>
            <input type="number" id="payoutAmount" min="100" max="<?php echo $availableBalance; ?>" step="0.01" placeholder="Enter amount..." required>
        </div>

        <div class="form-group">
            <label for="payoutMethod">Payment Destination</label>
            <select id="payoutMethod" class="form-control">
                <option value="bank_transfer"<?php echo $defaultPayoutMethod === 'bank_transfer' ? ' selected' : ''; ?>>Bank Transfer</option>
                <option value="mobile_money"<?php echo $defaultPayoutMethod === 'mobile_money' ? ' selected' : ''; ?>>Mobile Money</option>
            </select>
            <span class="help-text">Choose where the payout should be sent.</span>
        </div>

        <div id="bankFields" style="display: none;">
            <div class="form-group">
                <label for="bankName">Bank Name</label>
                <input id="bankName" class="form-control" type="text" value="<?php echo htmlspecialchars($defaultBankName); ?>" placeholder="e.g. Access Bank" />
            </div>
            <div class="form-group">
                <label for="bankAccountNumber">Account Number</label>
                <input id="bankAccountNumber" class="form-control" type="text" value="<?php echo htmlspecialchars($defaultBankAccountNumber); ?>" placeholder="e.g. 1234567890" />
            </div>
            <div class="form-group">
                <label for="bankAccountName">Account Name</label>
                <input id="bankAccountName" class="form-control" type="text" value="<?php echo htmlspecialchars($defaultBankAccountName); ?>" placeholder="e.g. John Doe" />
            </div>
        </div>

        <div id="mobileFields" style="display: none;">
            <div class="form-group">
                <label for="mobileNumber">Mobile Money Number</label>
                <input id="mobileNumber" class="form-control" type="text" value="<?php echo htmlspecialchars($defaultMobileNumber); ?>" placeholder="e.g. 0971234567" />
                <span class="help-text">Enter the mobile money number where you want the payout.</span>
            </div>
        </div>

        <div class="modal-actions">
            <button class="btn-cancel" onclick="closePayoutModal()">Cancel</button>
            <button class="btn-submit" id="btnSubmitPayout" onclick="submitPayoutRequest()">Submit Request</button>
        </div>
    </div>
</div>

<script>
    // Tab Switching
    function switchThemeTab(event, tabId) {
        document.querySelectorAll('.wallet-tab').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
        
        const clickedTab = event.currentTarget || event.target;
        clickedTab.classList.add('active');
        document.getElementById('tab-' + tabId).classList.add('active');
    }

    // Modal Handling
    const payoutModal = document.getElementById('payoutModal');
    
    function openPayoutModal() {
        <?php if ($availableBalance <= 0): ?>
            Swal.fire({
                title: 'Insufficient Balance',
                text: 'You do not have any available balance to withdraw.',
                icon: 'warning',
                confirmButtonColor: '#3498db'
            });
            return;
        <?php endif; ?>
        payoutModal.style.display = 'flex';
        updatePayoutFieldsVisibility();
    }

    function closePayoutModal() {
        payoutModal.style.display = 'none';
        document.getElementById('payoutAmount').value = '';
        document.getElementById('bankName').value = '';
        document.getElementById('bankAccountNumber').value = '';
        document.getElementById('bankAccountName').value = '';
        document.getElementById('mobileNumber').value = '';
    }

    //  Payout submition using API
    function updatePayoutFieldsVisibility() {
        const method = document.getElementById('payoutMethod').value;
        const bankFields = document.getElementById('bankFields');
        const mobileFields = document.getElementById('mobileFields');

        if (method === 'mobile_money') {
            bankFields.style.display = 'none';
            mobileFields.style.display = 'block';
        } else {
            bankFields.style.display = 'block';
            mobileFields.style.display = 'none';
        }
    }

    document.getElementById('payoutMethod').addEventListener('change', updatePayoutFieldsVisibility);

    function submitPayoutRequest() {
        const amount = document.getElementById('payoutAmount').value;
        const method = document.getElementById('payoutMethod').value;
        const maxAmount = <?php echo $availableBalance; ?>;
        
        if (!amount || amount <= 0) {
            Swal.fire('Error', 'Please enter a valid amount.', 'error');
            return;
        }
        
        if (parseFloat(amount) > maxAmount) {
            Swal.fire('Error', 'Amount exceeds available balance.', 'error');
            return;
        }

        const payload = {
            amount: parseFloat(amount),
            payout_method: method
        };

        if (method === 'bank_transfer') {
            payload.bank_name = document.getElementById('bankName').value.trim();
            payload.bank_account_number = document.getElementById('bankAccountNumber').value.trim();
            payload.bank_account_name = document.getElementById('bankAccountName').value.trim();

            if (!payload.bank_name || !payload.bank_account_number || !payload.bank_account_name) {
                Swal.fire('Error', 'Please enter complete bank account details.', 'error');
                return;
            }
        } else {
            payload.mobile_number = document.getElementById('mobileNumber').value.trim();
            if (!payload.mobile_number) {
                Swal.fire('Error', 'Please enter a mobile money number.', 'error');
                return;
            }
        }

        const btnSubmit = document.getElementById('btnSubmitPayout');
        btnSubmit.disabled = true;
        btnSubmit.innerText = 'Submitting...';

        fetch('../api/request_payout.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                Swal.fire('Success', data.message, 'success').then(() => {
                    window.location.reload();
                });
            } else {
                Swal.fire('Failed', data.message, 'error');
                btnSubmit.disabled = false;
                btnSubmit.innerText = 'Submit Request';
            }
        })
        .catch(err => {
            console.error(err);
            Swal.fire('Error', 'A server error occurred while processing the request.', 'error');
            btnSubmit.disabled = false;
            btnSubmit.innerText = 'Submit Request';
        });
    }

    // Initialize form state
    document.addEventListener('DOMContentLoaded', () => {
        updatePayoutFieldsVisibility();
    });

    // Sidebar toggling (same behavior across company-app pages)
    (function() {
        const menuBtn = document.getElementById('menuBtn');
        const closeMenu = document.getElementById('closeMenu');
        const sidebar = document.getElementById('sidebar');
        const menuOverlay = document.getElementById('menuOverlay');

        function toggleMenu() {
            if (!sidebar || !menuOverlay) return;
            sidebar.classList.toggle('show');
            menuOverlay.classList.toggle('show');
            document.body.style.overflow = sidebar.classList.contains('show') ? 'hidden' : '';
        }

        if (menuBtn) {
            menuBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                toggleMenu();
            });
        }

        if (closeMenu) closeMenu.addEventListener('click', toggleMenu);
        if (menuOverlay) menuOverlay.addEventListener('click', toggleMenu);

        document.querySelectorAll('.menu-items a').forEach(item => {
            item.addEventListener('click', toggleMenu);
        });
    })();
</script>

<?php
// We don't necessarily have a footer, some files use closing body.
// If header.php doesn't close body and html, we can do it here.
?>
    </body>
</html>
