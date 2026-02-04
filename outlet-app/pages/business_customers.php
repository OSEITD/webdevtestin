<?php
require_once '../includes/auth_guard.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$current_user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Customers - <?php echo htmlspecialchars($current_user['company_name']); ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../css/outlet-dashboard.css">
    <style>
        .customers-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .add-customer-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            cursor: pointer;
            font-weight: 600;
            transition: transform 0.2s;
        }

        .add-customer-btn:hover {
            transform: translateY(-2px);
        }

        .customers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .customer-card {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #667eea;
        }

        .customer-name {
            font-weight: 600;
            font-size: 1.1rem;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }

        .customer-info {
            color: #6b7280;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }

        .customer-stats {
            display: flex;
            justify-content: space-between;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e5e7eb;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-weight: 600;
            color: #1f2937;
        }

        .stat-label {
            font-size: 0.8rem;
            color: #6b7280;
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-active {
            background-color: #d1fae5;
            color: #065f46;
        }

        .status-inactive {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }

        .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 2rem;
            border-radius: 0.75rem;
            width: 90%;
            max-width: 500px;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.25rem;
            font-weight: 500;
            color: #374151;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            font-size: 0.875rem;
        }

        .form-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 1.5rem;
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.375rem;
            cursor: pointer;
        }

        .btn-primary {
            background: #667eea;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.375rem;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="mobile-dashboard">
        <?php include '../includes/navbar.php'; ?>
        <?php include '../includes/sidebar.php'; ?>

        <div class="menu-overlay" id="menuOverlay"></div>

        <main class="main-content">
            <div class="dashboard-content">
                <div class="customers-header">
                    <h1>Business Customers</h1>
                    <button class="add-customer-btn" onclick="openAddCustomerModal()">
                        <i class="fas fa-plus"></i> Add New Customer
                    </button>
                </div>

                <div class="customers-grid" id="customersGrid">
                </div>
            </div>
        </main>
    </div>

    <div id="addCustomerModal" class="modal">
        <div class="modal-content">
            <h2>Add New Business Customer</h2>
            <form id="addCustomerForm">
                <div class="form-group">
                    <label class="form-label" for="businessName">Business Name *</label>
                    <input type="text" id="businessName" name="business_name" class="form-input" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="contactPerson">Contact Person *</label>
                    <input type="text" id="contactPerson" name="contact_person" class="form-input" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="phone">Phone *</label>
                    <input type="tel" id="phone" name="phone" class="form-input" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-input">
                </div>

                <div class="form-group">
                    <label class="form-label" for="address">Address *</label>
                    <textarea id="address" name="address" class="form-input" rows="3" required></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label" for="businessType">Business Type</label>
                    <select id="businessType" name="business_type" class="form-input">
                        <option value="">Select Business Type</option>
                        <option value="retail">Retail</option>
                        <option value="wholesale">Wholesale</option>
                        <option value="manufacturing">Manufacturing</option>
                        <option value="services">Services</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="creditLimit">Credit Limit</label>
                    <input type="number" id="creditLimit" name="credit_limit" class="form-input" step="0.01" min="0" value="0">
                </div>

                <div class="form-group">
                    <label class="form-label" for="paymentTerms">Payment Terms</label>
                    <select id="paymentTerms" name="payment_terms" class="form-input">
                        <option value="prepaid">Prepaid</option>
                        <option value="net15">Net 15</option>
                        <option value="net30">Net 30</option>
                        <option value="cod">COD</option>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeAddCustomerModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Add Customer</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        async function fetchCustomers() {
            try {
                const response = await fetch('./api/business_customers.php');
                const data = await response.json();

                if (data.success) {
                    displayCustomers(data.customers);
                }
            } catch (error) {
                console.error('Error fetching customers:', error);
            }
        }

        function displayCustomers(customers) {
            const grid = document.getElementById('customersGrid');
            grid.innerHTML = '';

            customers.forEach(customer => {
                const card = document.createElement('div');
                card.className = 'customer-card';

                const statusClass = customer.status === 'active' ? 'status-active' : 'status-inactive';
                const balanceStatus = customer.balance_status === 'overlimit' ? '⚠️ Over Limit' : '✅ Within Limit';

                card.innerHTML = `
                    <div class="customer-name">${customer.business_name}</div>
                    <div class="customer-info"><i class="fas fa-user"></i> ${customer.contact_person}</div>
                    <div class="customer-info"><i class="fas fa-phone"></i> ${customer.phone}</div>
                    <div class="customer-info"><i class="fas fa-envelope"></i> ${customer.email || 'N/A'}</div>
                    <div class="customer-info"><i class="fas fa-building"></i> ${customer.business_type || 'N/A'}</div>
                    <div class="customer-info">
                        <span class="status-badge ${statusClass}">${customer.status}</span>
                        <span style="margin-left: 0.5rem; font-size: 0.8rem;">${balanceStatus}</span>
                    </div>

                    <div class="customer-stats">
                        <div class="stat-item">
                            <div class="stat-value">${customer.total_parcels || 0}</div>
                            <div class="stat-label">Total Parcels</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">₵${customer.current_balance.toFixed(2)}</div>
                            <div class="stat-label">Balance</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">₵${customer.credit_limit.toFixed(2)}</div>
                            <div class="stat-label">Credit Limit</div>
                        </div>
                    </div>
                `;

                grid.appendChild(card);
            });
        }

        function openAddCustomerModal() {
            document.getElementById('addCustomerModal').style.display = 'block';
        }

        function closeAddCustomerModal() {
            document.getElementById('addCustomerModal').style.display = 'none';
            document.getElementById('addCustomerForm').reset();
        }

        document.getElementById('addCustomerForm').addEventListener('submit', async (e) => {
            e.preventDefault();

            const formData = new FormData(e.target);
            const customerData = Object.fromEntries(formData.entries());

            try {
                const response = await fetch('./api/business_customers.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(customerData)
                });

                const result = await response.json();

                if (result.success) {
                    closeAddCustomerModal();
                    fetchCustomers();
                    alert('Customer added successfully!');
                } else {
                    alert('Error adding customer: ' + (result.error || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error adding customer');
            }
        });

        document.getElementById('addCustomerModal').addEventListener('click', (e) => {
            if (e.target.id === 'addCustomerModal') {
                closeAddCustomerModal();
            }
        });

        fetchCustomers();
    </script>
</body>
</html>
