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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Parcel Pool - <?= htmlspecialchars($_SESSION['outlet_name'] ?? 'Outlet') ?></title>

    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <link rel="stylesheet" href="../css/outlet-dashboard.css">
    <link rel="stylesheet" href="../css/notifications.css">

    <style>
        .parcel-pool-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1.5rem;
        }

        .pool-header {
            background: linear-gradient(135deg, #2E0D2A 0%, #4A1C40 100%);
            color: white;
            padding: 2rem;
            border-radius: 1rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(46, 13, 42, 0.3);
        }

        .pool-header h1 {
            margin: 0 0 0.5rem 0;
            font-size: 2.2rem;
            font-weight: 700;
        }

        .pool-header .subtitle {
            opacity: 0.9;
            font-size: 1.1rem;
            margin: 0;
        }

        .filter-controls {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(46, 13, 42, 0.1);
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-label {
            font-weight: 600;
            color: #2E0D2A;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .filter-select, .filter-input {
            padding: 0.75rem;
            border: 1px solid rgba(46, 13, 42, 0.2);
            border-radius: 0.5rem;
            font-size: 0.95rem;
            transition: border-color 0.2s ease;
        }

        .filter-select:focus, .filter-input:focus {
            border-color: #4A1C40;
            outline: none;
            box-shadow: 0 0 0 3px rgba(74, 28, 64, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
            margin-top: 1rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #2E0D2A 0%, #4A1C40 100%);
            color: white;
        }

        .btn-secondary {
            background: #f8f9fa;
            color: #495057;
            border: 1px solid #dee2e6;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 0.75rem;
            border: 1px solid rgba(46, 13, 42, 0.1);
            text-align: center;
            transition: transform 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(46, 13, 42, 0.15);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.2rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #2E0D2A;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .parcels-table-container {
            background: white;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(46, 13, 42, 0.1);
        }

        .parcels-table {
            width: 100%;
            border-collapse: collapse;
        }

        .parcels-table thead th {
            background: linear-gradient(135deg, #2E0D2A 0%, #4A1C40 100%);
            color: white;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .parcels-table tbody tr {
            border-bottom: 1px solid #f8f9fa;
            transition: background-color 0.2s ease;
        }

        .parcels-table tbody tr:hover {
            background-color: rgba(46, 13, 42, 0.04);
        }

        .parcels-table td {
            padding: 1rem;
            vertical-align: middle;
        }

        .track-number {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            color: #2E0D2A;
            background: rgba(46, 13, 42, 0.05);
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
        }

        .status-badge {
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-at_outlet { background: #d4edda; color: #155724; }
        .status-assigned { background: #cce5ff; color: #004085; }
        .status-in_transit { background: #e2e3ff; color: #383d41; }
        .status-delivered { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .status-ready_for_dispatch { background: #e2e3ff; color: #383d41; }

        .priority-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .priority-urgent { background: #fee2e2; color: #b91c1c; }
        .priority-high { background: #fde68a; color: #b45309; }
        .priority-medium { background: #e0f2f1; color: #0f766e; }
        .priority-low { background: #ede9fe; color: #5b21b6; }

        .customer-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .customer-name {
            font-weight: 600;
            color: #2E0D2A;
        }

        .customer-phone {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .loading-state {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 3rem;
            color: #dee2e6;
            margin-bottom: 1rem;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            padding: 1.5rem;
            background: #f8f9fa;
        }

        .pagination button {
            padding: 0.5rem 0.75rem;
            border: 1px solid #dee2e6;
            background: white;
            color: #495057;
            border-radius: 0.25rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .pagination button:hover:not(:disabled) {
            background: #2E0D2A;
            color: white;
            border-color: #2E0D2A;
        }

        .pagination button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination .current-page {
            background: #2E0D2A;
            color: white;
            border-color: #2E0D2A;
        }

        @media (max-width: 768px) {
            .main-content {
                padding-left: 0.5rem;
                padding-right: 0.5rem;
                max-width: 100%;
            }

            .content-container {
                margin: 10px 0.5rem;
                padding: 20px 15px;
                border-radius: 8px;
            }

            .parcel-pool-container {
                padding: 1rem 0.5rem;
            }

            .filter-grid {
                grid-template-columns: 1fr;
            }

            .filter-actions {
                justify-content: stretch;
            }

            .filter-actions .btn {
                flex: 1;
                justify-content: center;
            }

            .parcels-table-container {
                overflow-x: auto;
            }

            .parcels-table {
                min-width: 800px;
            }
        }
    </style>
</head>
<body>
    <div class="mobile-dashboard">
        <?php include '../includes/navbar.php'; ?>
        <?php include '../includes/sidebar.php'; ?>

        <div class="menu-overlay" id="menuOverlay"></div>

        <main class="main-content">
            <div class="parcel-pool-container">
                <div class="pool-header">
                    <h1><i class="fas fa-swimming-pool"></i> Parcel Pool</h1>
                    <p class="subtitle">Manage all parcels associated with your outlet</p>
                </div>

                <div class="stats-grid" id="statsGrid">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(46, 13, 42, 0.1); color: #2E0D2A;">
                            <i class="fas fa-boxes"></i>
                        </div>
                        <div class="stat-value" id="totalParcels">-</div>
                        <div class="stat-label">Total Parcels</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(255, 193, 7, 0.1); color: #ffc107;">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-value" id="pendingParcels">-</div>
                        <div class="stat-label">Pending</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(40, 167, 69, 0.1); color: #28a745;">
                            <i class="fas fa-warehouse"></i>
                        </div>
                        <div class="stat-value" id="atOutletParcels">-</div>
                        <div class="stat-label">At Outlet</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(23, 162, 184, 0.1); color: #17a2b8;">
                            <i class="fas fa-truck"></i>
                        </div>
                        <div class="stat-value" id="inTransitParcels">-</div>
                        <div class="stat-label">In Transit</div>
                    </div>
                </div>

                <div class="filter-controls">
                    <form id="filterForm">
                        <div class="filter-grid">
                            <div class="filter-group">
                                <label class="filter-label">Status</label>
                                <select name="status" class="filter-select" id="statusFilter">
                                    <option value="">All Statuses</option>
                                    <option value="pending">Pending</option>
                                    <option value="at_outlet">At Outlet</option>
                                    <option value="assigned">Assigned</option>
                                    <option value="in_transit">In Transit</option>
                                    <option value="delivered">Delivered</option>
                                    <option value="cancelled">Cancelled</option>
                                    <option value="ready_for_dispatch">Ready for Dispatch</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label class="filter-label">Relation Type</label>
                                <select name="relation" class="filter-select" id="relationFilter">
                                    <option value="all">All Relations</option>
                                    <option value="origin">Origin (Outgoing)</option>
                                    <option value="destination">Destination (Incoming)</option>
                                    <option value="listed">Listed in Pool</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label class="filter-label">Date Range</label>
                                <select name="dateRange" class="filter-select" id="dateRangeFilter">
                                    <option value="">All Time</option>
                                    <option value="today">Today</option>
                                    <option value="yesterday">Yesterday</option>
                                    <option value="this_week">This Week</option>
                                    <option value="last_week">Last Week</option>
                                    <option value="this_month">This Month</option>
                                    <option value="last_month">Last Month</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label class="filter-label">Search</label>
                                <input type="text" name="search" class="filter-input" id="searchFilter" placeholder="Track number, sender, receiver...">
                            </div>
                        </div>
                        <div class="filter-actions">
                            <button type="button" class="btn btn-secondary" id="resetFilters">
                                <i class="fas fa-undo"></i> Reset
                            </button>
                            <button type="submit" class="btn btn-primary" id="applyFilters">
                                <i class="fas fa-filter"></i> Apply Filters
                            </button>
                        </div>
                    </form>
                </div>

                <div class="parcels-table-container">
                    <table class="parcels-table">
                        <thead>
                            <tr>
                                <th>Track Number</th>
                                <th>Status</th>
                                <th>Sender</th>
                                <th>Receiver</th>
                                <th>Relation</th>
                                <th>Weight</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="parcelsTableBody">
                            <tr>
                                <td colspan="8" class="loading-state">
                                    <i class="fas fa-spinner fa-spin"></i> Loading parcels...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="pagination" id="pagination" style="display: none;">
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Parcel Details Modal -->
    <div id="parcelDetailsModal" class="modal" style="display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4);">
        <div class="modal-content" style="background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 90%; max-width: 780px; border-radius: 8px; position: relative;">
            <span class="close" id="closeParcelModal" style="color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
            <h2>Parcel Details</h2>
            <p class="subtitle" id="modalTrackingNumber">Tracking Number: Loading...</p>
            <div id="modalParcelStatusDisplay" class="parcel-status-display status-pending">Loading...</div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                <div class="detail-section" style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h3 style="margin-top: 0; color: #2E0D2A; border-bottom: 2px solid #2E0D2A; padding-bottom: 0.5rem;">Recipient Information</h3>
                    <div class="detail-item" style="display: flex; align-items: center; margin-bottom: 1rem;">
                        <i class="fas fa-user" style="width: 20px; margin-right: 10px; color: #4A1C40;"></i>
                        <div>
                            <p class="label" style="margin: 0; font-weight: 600; color: #666;">Recipient Name</p>
                            <p class="value" id="modalRecipientName" style="margin: 0.25rem 0 0 0; font-weight: 500;">Loading...</p>
                        </div>
                    </div>
                    <div class="detail-item" style="display: flex; align-items: center; margin-bottom: 1rem;">
                        <i class="fas fa-map-marker-alt" style="width: 20px; margin-right: 10px; color: #4A1C40;"></i>
                        <div>
                            <p class="label" style="margin: 0; font-weight: 600; color: #666;">Delivery Address</p>
                            <p class="value" id="modalDeliveryAddress" style="margin: 0.25rem 0 0 0; font-weight: 500;">Loading...</p>
                        </div>
                    </div>
                    <div class="detail-item" style="display: flex; align-items: center; margin-bottom: 1rem;">
                        <i class="fas fa-phone" style="width: 20px; margin-right: 10px; color: #4A1C40;"></i>
                        <div>
                            <p class="label" style="margin: 0; font-weight: 600; color: #666;">Contact Number</p>
                            <p class="value" id="modalContactNumber" style="margin: 0.25rem 0 0 0; font-weight: 500;">Loading...</p>
                        </div>
                    </div>
                </div>

                <div class="detail-section" style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h3 style="margin-top: 0; color: #2E0D2A; border-bottom: 2px solid #2E0D2A; padding-bottom: 0.5rem;">Parcel Information</h3>
                    <div class="detail-item" style="display: flex; align-items: center; margin-bottom: 1rem;">
                        <i class="fas fa-weight-hanging" style="width: 20px; margin-right: 10px; color: #4A1C40;"></i>
                        <div>
                            <p class="label" style="margin: 0; font-weight: 600; color: #666;">Weight</p>
                            <p class="value" id="modalParcelWeight" style="margin: 0.25rem 0 0 0; font-weight: 500;">Loading...</p>
                        </div>
                    </div>
                    <div class="detail-item" style="display: flex; align-items: center; margin-bottom: 1rem;">
                        <i class="fas fa-dollar-sign" style="width: 20px; margin-right: 10px; color: #4A1C40;"></i>
                        <div>
                            <p class="label" style="margin: 0; font-weight: 600; color: #666;">Delivery Fee</p>
                            <p class="value" id="modalDeliveryFee" style="margin: 0.25rem 0 0 0; font-weight: 500;">Loading...</p>
                        </div>
                    </div>
                    <div class="detail-item" style="display: flex; align-items: center; margin-bottom: 1rem;">
                        <i class="fas fa-info-circle" style="width: 20px; margin-right: 10px; color: #4A1C40;"></i>
                        <div>
                            <p class="label" style="margin: 0; font-weight: 600; color: #666;">Special Instructions</p>
                            <p class="value" id="modalSpecialInstructions" style="margin: 0.25rem 0 0 0; font-weight: 500;">Loading...</p>
                        </div>
                    </div>
                </div>

                <div class="detail-section" style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h3 style="margin-top: 0; color: #2E0D2A; border-bottom: 2px solid #2E0D2A; padding-bottom: 0.5rem;">Sender Information</h3>
                    <div class="detail-item" style="display: flex; align-items: center; margin-bottom: 1rem;">
                        <i class="fas fa-user" style="width: 20px; margin-right: 10px; color: #4A1C40;"></i>
                        <div>
                            <p class="label" style="margin: 0; font-weight: 600; color: #666;">Sender Name</p>
                            <p class="value" id="modalSenderName" style="margin: 0.25rem 0 0 0; font-weight: 500;">Loading...</p>
                        </div>
                    </div>
                    <div class="detail-item" style="display: flex; align-items: center; margin-bottom: 1rem;">
                        <i class="fas fa-phone" style="width: 20px; margin-right: 10px; color: #4A1C40;"></i>
                        <div>
                            <p class="label" style="margin: 0; font-weight: 600; color: #666;">Sender Phone</p>
                            <p class="value" id="modalSenderPhone" style="margin: 0.25rem 0 0 0; font-weight: 500;">Loading...</p>
                        </div>
                    </div>
                </div>

                <div class="detail-section" style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h3 style="margin-top: 0; color: #2E0D2A; border-bottom: 2px solid #2E0D2A; padding-bottom: 0.5rem;">Route Information</h3>
                    <div class="detail-item" style="display: flex; align-items: center; margin-bottom: 1rem;">
                        <i class="fas fa-building" style="width: 20px; margin-right: 10px; color: #4A1C40;"></i>
                        <div>
                            <p class="label" style="margin: 0; font-weight: 600; color: #666;">Origin Outlet</p>
                            <p class="value" id="modalOriginOutlet" style="margin: 0.25rem 0 0 0; font-weight: 500;">Loading...</p>
                        </div>
                    </div>
                    <div class="detail-item" style="display: flex; align-items: center; margin-bottom: 1rem;">
                        <i class="fas fa-building" style="width: 20px; margin-right: 10px; color: #4A1C40;"></i>
                        <div>
                            <p class="label" style="margin: 0; font-weight: 600; color: #666;">Destination Outlet</p>
                            <p class="value" id="modalDestinationOutlet" style="margin: 0.25rem 0 0 0; font-weight: 500;">Loading...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentPage = 1;
        const pageSize = 20;
        let totalPages = 1;
        let currentFilters = {};

        const outletId = '<?= htmlspecialchars($_SESSION["outlet_id"] ?? "") ?>';
        const companyId = '<?= htmlspecialchars($_SESSION["company_id"] ?? "") ?>';

        document.addEventListener('DOMContentLoaded', function() {
            initializeParcelPool();
            setupEventListeners();
        });

        function initializeParcelPool() {
            loadParcels();
            loadStatistics();
        }

        function setupEventListeners() {
            document.getElementById('filterForm').addEventListener('submit', function(e) {
                e.preventDefault();
                currentPage = 1;
                loadParcels();
            });

            document.getElementById('resetFilters').addEventListener('click', function() {
                document.getElementById('filterForm').reset();
                currentFilters = {};
                currentPage = 1;
                loadParcels();
                loadStatistics();
            });

            let searchTimeout;
            document.getElementById('searchFilter').addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    currentPage = 1;
                    loadParcels();
                }, 500);
            });

            setInterval(() => {
                loadParcels();
                loadStatistics();
            }, 30000);
        }

        async function loadParcels() {
            try {
                showLoading();

                const formData = new FormData(document.getElementById('filterForm'));
                currentFilters = Object.fromEntries(formData.entries());

                const params = new URLSearchParams({
                    page: currentPage,
                    limit: pageSize,
                    outlet_id: outletId,
                    company_id: companyId,
                    ...currentFilters
                });

                const response = await fetch(`../api/parcels/fetch_parcel_pool.php?${params}`, {
                    credentials: 'same-origin'
                });

                const data = await response.json();

                if (data.success) {
                    renderParcelsTable(data.parcels);
                    renderPagination(data.pagination);
                } else {
                    showError(data.error || 'Failed to load parcels');
                }
            } catch (error) {
                console.error('Error loading parcels:', error);
                showError('Network error occurred while loading parcels');
            }
        }

        async function loadStatistics() {
            try {
                const response = await fetch(`../api/parcels/fetch_parcel_pool_stats.php?outlet_id=${outletId}&company_id=${companyId}`, {
                    credentials: 'same-origin'
                });

                const data = await response.json();

                if (data.success) {
                    document.getElementById('totalParcels').textContent = data.stats.total || 0;
                    document.getElementById('pendingParcels').textContent = data.stats.pending || 0;
                    document.getElementById('atOutletParcels').textContent = data.stats.at_outlet || 0;
                    document.getElementById('inTransitParcels').textContent = data.stats.in_transit || 0;
                }
            } catch (error) {
                console.error('Error loading statistics:', error);
            }
        }

        function renderParcelsTable(parcels) {
            const tbody = document.getElementById('parcelsTableBody');

            if (!parcels || parcels.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="empty-state">
                            <i class="fas fa-box-open"></i>
                            <div>No parcels found matching your criteria</div>
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = parcels.map(parcel => `
                <tr>
                    <td>
                        <span class="track-number">${escapeHtml(parcel.track_number)}</span>
                    </td>
                    <td>
                        <span class="status-badge status-${parcel.status || 'unknown'}">
                            ${formatStatus(parcel.status)}
                        </span>
                    </td>
                    <td>
                        <div class="customer-info">
                            <div class="customer-name">${escapeHtml(parcel.sender_name || 'Unknown')}</div>
                            ${parcel.sender_phone ? `<div class="customer-phone">${escapeHtml(parcel.sender_phone)}</div>` : ''}
                        </div>
                    </td>
                    <td>
                        <div class="customer-info">
                            <div class="customer-name">${escapeHtml(parcel.receiver_name || 'Unknown')}</div>
                            ${parcel.receiver_phone ? `<div class="customer-phone">${escapeHtml(parcel.receiver_phone)}</div>` : ''}
                        </div>
                    </td>
                    <td>
                        <span class="priority-badge priority-${getRelationType(parcel)}">
                            ${formatRelationType(parcel)}
                        </span>
                    </td>
                    <td>${parcel.parcel_weight || 0} kg</td>
                    <td>${formatDate(parcel.created_at)}</td>
                    <td>
                        <button class="btn btn-secondary" onclick="viewParcelDetails('${parcel.id}')" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                </tr>
            `).join('');
        }

        function renderPagination(pagination) {
            const paginationDiv = document.getElementById('pagination');

            if (!pagination || pagination.totalPages <= 1) {
                paginationDiv.style.display = 'none';
                return;
            }

            totalPages = pagination.totalPages;
            currentPage = pagination.currentPage;

            let paginationHTML = '';

            paginationHTML += `
                <button ${currentPage <= 1 ? 'disabled' : ''} onclick="changePage(${currentPage - 1})">
                    <i class="fas fa-chevron-left"></i>
                </button>
            `;

            const startPage = Math.max(1, currentPage - 2);
            const endPage = Math.min(totalPages, currentPage + 2);

            if (startPage > 1) {
                paginationHTML += `<button onclick="changePage(1)">1</button>`;
                if (startPage > 2) {
                    paginationHTML += `<span>...</span>`;
                }
            }

            for (let i = startPage; i <= endPage; i++) {
                paginationHTML += `
                    <button class="${i === currentPage ? 'current-page' : ''}" onclick="changePage(${i})">
                        ${i}
                    </button>
                `;
            }

            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    paginationHTML += `<span>...</span>`;
                }
                paginationHTML += `<button onclick="changePage(${totalPages})">${totalPages}</button>`;
            }

            paginationHTML += `
                <button ${currentPage >= totalPages ? 'disabled' : ''} onclick="changePage(${currentPage + 1})">
                    <i class="fas fa-chevron-right"></i>
                </button>
            `;

            paginationDiv.innerHTML = paginationHTML;
            paginationDiv.style.display = 'flex';
        }

        function changePage(page) {
            if (page >= 1 && page <= totalPages) {
                currentPage = page;
                loadParcels();
            }
        }

        function getRelationType(parcel) {
            if (parcel.origin_outlet_id === outletId && parcel.destination_outlet_id === outletId) {
                return 'medium';
            } else if (parcel.origin_outlet_id === outletId) {
                return 'high';
            } else if (parcel.destination_outlet_id === outletId) {
                return 'low';
            } else {
                return 'urgent';
            }
        }

        function formatRelationType(parcel) {
            if (parcel.origin_outlet_id === outletId && parcel.destination_outlet_id === outletId) {
                return 'Internal';
            } else if (parcel.origin_outlet_id === outletId) {
                return 'Outgoing';
            } else if (parcel.destination_outlet_id === outletId) {
                return 'Incoming';
            } else {
                return 'Listed';
            }
        }

        function formatStatus(status) {
            if (!status) return 'Unknown';
            return status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        }

        function formatDate(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric'
            });
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function showLoading() {
            document.getElementById('parcelsTableBody').innerHTML = `
                <tr>
                    <td colspan="8" class="loading-state">
                        <i class="fas fa-spinner fa-spin"></i> Loading parcels...
                    </td>
                </tr>
            `;
        }

        function showError(message) {
            document.getElementById('parcelsTableBody').innerHTML = `
                <tr>
                    <td colspan="8" class="empty-state">
                        <i class="fas fa-exclamation-triangle" style="color: #dc3545;"></i>
                        <div style="color: #dc3545;">${escapeHtml(message)}</div>
                    </td>
                </tr>
            `;
        }

        function viewParcelDetails(parcelId) {
            // Show modal
            const modal = document.getElementById('parcelDetailsModal');
            modal.style.display = 'block';

            // Fetch parcel details
            fetch(`../api/get_parcel_details.php?id=${parcelId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.parcel) {
                        const parcel = data.parcel;

                        // Update modal content
                        document.getElementById('modalTrackingNumber').textContent = `Tracking Number: ${parcel.track_number || 'N/A'}`;
                        document.getElementById('modalParcelStatusDisplay').textContent = parcel.status ? parcel.status.replace('_', ' ').toUpperCase() : 'UNKNOWN';
                        document.getElementById('modalParcelStatusDisplay').className = `parcel-status-display status-${parcel.status || 'pending'}`;

                        document.getElementById('modalRecipientName').textContent = parcel.receiver_name || 'N/A';
                        document.getElementById('modalDeliveryAddress').textContent = parcel.receiver_address || 'N/A';
                        document.getElementById('modalContactNumber').textContent = parcel.receiver_phone || 'N/A';

                        document.getElementById('modalParcelWeight').textContent = parcel.parcel_weight ? `${parcel.parcel_weight} kg` : 'N/A';
                        document.getElementById('modalDeliveryFee').textContent = parcel.delivery_fee ? `ZMW ${parcel.delivery_fee}` : 'N/A';
                        document.getElementById('modalSpecialInstructions').textContent = parcel.special_instructions || 'None';

                        document.getElementById('modalSenderName').textContent = parcel.sender_name || 'N/A';
                        document.getElementById('modalSenderPhone').textContent = parcel.sender_phone || 'N/A';

                        document.getElementById('modalOriginOutlet').textContent = parcel.origin_outlet_name || 'N/A';
                        document.getElementById('modalDestinationOutlet').textContent = parcel.destination_outlet_name || 'N/A';
                    } else {
                        alert('Failed to load parcel details');
                    }
                })
                .catch(error => {
                    console.error('Error fetching parcel details:', error);
                    alert('Error loading parcel details');
                });
        }

        // Close modal functionality
        document.getElementById('closeParcelModal').onclick = function() {
            document.getElementById('parcelDetailsModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('parcelDetailsModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }
    </script>

    <script src="../assets/js/sidebar-toggle.js"></script>
    <script src="../assets/js/notifications.js"></script>
    
    <?php include __DIR__ . '/../includes/pwa_install_button.php'; ?>
    <script src="../js/pwa-install.js"></script>
</body>
</html>