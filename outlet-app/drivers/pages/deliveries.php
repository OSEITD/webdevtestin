<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    header('Location: ../../login.php');
    exit();
}

$driverId = $_SESSION['user_id'];
$companyId = $_SESSION['company_id'] ?? null;

require_once __DIR__ . '/../../includes/OutletAwareSupabaseHelper.php';
$supabase = new OutletAwareSupabaseHelper();

$completedTrips = [];
$errorMessage = '';

try {
    
    $trips = $supabase->get('trips', "driver_id=eq.{$driverId}&trip_status=eq.completed&order=created_at.desc", '*');
    
    
    foreach ($trips as &$trip) {
        
        if (!empty($trip['origin_outlet_id'])) {
            $origin = $supabase->get('outlets', "id=eq.{$trip['origin_outlet_id']}", 'outlet_name,address');
            $trip['origin_name'] = $origin[0]['outlet_name'] ?? 'Unknown';
            $trip['origin_address'] = $origin[0]['address'] ?? '';
        }
        
        
        if (!empty($trip['destination_outlet_id'])) {
            $destination = $supabase->get('outlets', "id=eq.{$trip['destination_outlet_id']}", 'outlet_name,address');
            $trip['destination_name'] = $destination[0]['outlet_name'] ?? 'Unknown';
            $trip['destination_address'] = $destination[0]['address'] ?? '';
        }
        
        
        $stops = $supabase->get('trip_stops', "trip_id=eq.{$trip['id']}&order=stop_order.asc", '*');
        $trip['stops_count'] = count($stops);
        
        
        $parcels = $supabase->get('parcel_list', "trip_id=eq.{$trip['id']}", 'id');
        $trip['parcels_count'] = count($parcels);
        
        
        if (!empty($trip['vehicle_id'])) {
            $vehicle = $supabase->get('vehicle', "id=eq.{$trip['vehicle_id']}", 'name,plate_number');
            $trip['vehicle_name'] = $vehicle[0]['name'] ?? 'Unknown';
            $trip['vehicle_plate'] = $vehicle[0]['plate_number'] ?? '';
        }
    }
    
    $completedTrips = $trips;
    
} catch (Exception $e) {
    $errorMessage = "Error loading deliveries: " . $e->getMessage();
    error_log($errorMessage);
}

$pageTitle = "Delivery History";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Driver Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            padding-top: 80px;
        }
        
        .deliveries-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .page-header {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .page-title {
            font-size: 32px;
            font-weight: bold;
            color: #2d3748;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .page-title i {
            color: #667eea;
        }
        
        .page-subtitle {
            color: #718096;
            font-size: 16px;
        }
        
        .stats-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .stat-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 36px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .deliveries-list {
            display: grid;
            gap: 20px;
        }
        
        .delivery-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .delivery-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }
        
        .delivery-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .trip-id {
            font-size: 18px;
            font-weight: bold;
            color: #2d3748;
        }
        
        .trip-date {
            color: #718096;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            background: #c6f6d5;
            color: #22543d;
        }
        
        .delivery-route {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            padding: 20px;
            background: #f7fafc;
            border-radius: 15px;
        }
        
        .route-point {
            flex: 1;
        }
        
        .route-label {
            font-size: 12px;
            color: #718096;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .route-name {
            font-size: 16px;
            color: #2d3748;
            font-weight: 600;
            margin-bottom: 3px;
        }
        
        .route-address {
            font-size: 13px;
            color: #a0aec0;
        }
        
        .route-arrow {
            font-size: 24px;
            color: #667eea;
        }
        
        .delivery-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            padding-top: 15px;
            border-top: 2px solid #e2e8f0;
        }
        
        .detail-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .detail-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        
        .icon-blue {
            background: #e6f2ff;
            color: #3182ce;
        }
        
        .icon-green {
            background: #c6f6d5;
            color: #22543d;
        }
        
        .icon-purple {
            background: #faf5ff;
            color: #805ad5;
        }
        
        .detail-content {
            flex: 1;
        }
        
        .detail-label {
            font-size: 12px;
            color: #718096;
            margin-bottom: 2px;
        }
        
        .detail-value {
            font-size: 15px;
            color: #2d3748;
            font-weight: 600;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #e2e8f0;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
        }
        
        .btn-secondary:hover {
            background: #cbd5e0;
        }
        
        .empty-state {
            background: white;
            border-radius: 20px;
            padding: 60px 30px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .empty-icon {
            font-size: 80px;
            color: #cbd5e0;
            margin-bottom: 20px;
        }
        
        .empty-title {
            font-size: 24px;
            font-weight: bold;
            color: #2d3748;
            margin-bottom: 10px;
        }
        
        .empty-text {
            color: #718096;
            font-size: 16px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-error {
            background: #fed7d7;
            color: #742a2a;
            border: 2px solid #fc8181;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .delivery-card {
            animation: slideIn 0.5s ease-out;
        }
        
        .filter-bar {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }
        
        .filter-bar input,
        .filter-bar select {
            padding: 10px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            flex: 1;
            min-width: 200px;
        }
        
        .filter-bar input:focus,
        .filter-bar select:focus {
            outline: none;
            border-color: #667eea;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="deliveries-container">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-truck-loading"></i>
                Delivery History
            </h1>
            <p class="page-subtitle">View all your completed deliveries and trip details</p>
            
            <div class="stats-summary">
                <div class="stat-box">
                    <div class="stat-value"><?php echo count($completedTrips); ?></div>
                    <div class="stat-label">Total Deliveries</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value">
                        <?php echo array_sum(array_column($completedTrips, 'parcels_count')); ?>
                    </div>
                    <div class="stat-label">Parcels Delivered</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value">
                        <?php echo array_sum(array_column($completedTrips, 'stops_count')); ?>
                    </div>
                    <div class="stat-label">Total Stops</div>
                </div>
            </div>
        </div>
        
        <?php if ($errorMessage): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php endif; ?>
        
        <div class="filter-bar">
            <input type="text" id="searchInput" placeholder="Search by trip ID or destination..." onkeyup="filterDeliveries()">
            <input type="date" id="dateFilter" onchange="filterDeliveries()">
            <select id="sortBy" onchange="sortDeliveries()">
                <option value="date-desc">Newest First</option>
                <option value="date-asc">Oldest First</option>
                <option value="parcels-desc">Most Parcels</option>
            </select>
        </div>
        
        <div class="deliveries-list" id="deliveriesList">
            <?php if (empty($completedTrips)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-box-open"></i>
                    </div>
                    <h2 class="empty-title">No Deliveries Yet</h2>
                    <p class="empty-text">Your completed deliveries will appear here</p>
                </div>
            <?php else: ?>
                <?php foreach ($completedTrips as $trip): ?>
                    <div class="delivery-card" data-trip-id="<?php echo htmlspecialchars($trip['id']); ?>" 
                         data-date="<?php echo htmlspecialchars($trip['created_at']); ?>"
                         data-parcels="<?php echo $trip['parcels_count']; ?>">
                        <div class="delivery-header">
                            <div>
                                <div class="trip-id">
                                    Trip #<?php echo substr($trip['id'], 0, 8); ?>
                                </div>
                                <div class="trip-date">
                                    <i class="fas fa-calendar"></i>
                                    <?php echo date('M d, Y g:i A', strtotime($trip['created_at'])); ?>
                                </div>
                            </div>
                            <span class="status-badge">
                                <i class="fas fa-check-circle"></i>
                                Completed
                            </span>
                        </div>
                        
                        <div class="delivery-route">
                            <div class="route-point">
                                <div class="route-label">From</div>
                                <div class="route-name"><?php echo htmlspecialchars($trip['origin_name'] ?? 'N/A'); ?></div>
                                <div class="route-address"><?php echo htmlspecialchars($trip['origin_address'] ?? ''); ?></div>
                            </div>
                            <div class="route-arrow">
                                <i class="fas fa-arrow-right"></i>
                            </div>
                            <div class="route-point">
                                <div class="route-label">To</div>
                                <div class="route-name"><?php echo htmlspecialchars($trip['destination_name'] ?? 'N/A'); ?></div>
                                <div class="route-address"><?php echo htmlspecialchars($trip['destination_address'] ?? ''); ?></div>
                            </div>
                        </div>
                        
                        <div class="delivery-details">
                            <div class="detail-item">
                                <div class="detail-icon icon-blue">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <div class="detail-content">
                                    <div class="detail-label">Stops</div>
                                    <div class="detail-value"><?php echo $trip['stops_count']; ?></div>
                                </div>
                            </div>
                            
                            <div class="detail-item">
                                <div class="detail-icon icon-green">
                                    <i class="fas fa-box"></i>
                                </div>
                                <div class="detail-content">
                                    <div class="detail-label">Parcels</div>
                                    <div class="detail-value"><?php echo $trip['parcels_count']; ?></div>
                                </div>
                            </div>
                            
                            <div class="detail-item">
                                <div class="detail-icon icon-purple">
                                    <i class="fas fa-car"></i>
                                </div>
                                <div class="detail-content">
                                    <div class="detail-label">Vehicle</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($trip['vehicle_plate'] ?? 'N/A'); ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="action-buttons">
                            <a href="delivery-details.php?trip_id=<?php echo urlencode($trip['id']); ?>" class="btn btn-primary">
                                <i class="fas fa-eye"></i>
                                View Details
                            </a>
                            <button class="btn btn-secondary" onclick="generateReport('<?php echo $trip['id']; ?>')">
                                <i class="fas fa-file-pdf"></i>
                                Generate Report
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        
        function filterDeliveries() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const dateFilter = document.getElementById('dateFilter').value;
            const cards = document.querySelectorAll('.delivery-card');
            
            cards.forEach(card => {
                const tripId = card.getAttribute('data-trip-id');
                const tripDate = card.getAttribute('data-date').split('T')[0];
                const text = card.textContent.toLowerCase();
                
                const matchesSearch = text.includes(searchTerm);
                const matchesDate = !dateFilter || tripDate === dateFilter;
                
                card.style.display = (matchesSearch && matchesDate) ? 'block' : 'none';
            });
        }
        
        function sortDeliveries() {
            const sortBy = document.getElementById('sortBy').value;
            const container = document.getElementById('deliveriesList');
            const cards = Array.from(container.querySelectorAll('.delivery-card'));
            
            cards.sort((a, b) => {
                if (sortBy === 'date-desc') {
                    return new Date(b.getAttribute('data-date')) - new Date(a.getAttribute('data-date'));
                } else if (sortBy === 'date-asc') {
                    return new Date(a.getAttribute('data-date')) - new Date(b.getAttribute('data-date'));
                } else if (sortBy === 'parcels-desc') {
                    return parseInt(b.getAttribute('data-parcels')) - parseInt(a.getAttribute('data-parcels'));
                }
            });
            
            cards.forEach(card => container.appendChild(card));
        }
        
        function generateReport(tripId) {
            alert('Generating PDF report for trip: ' + tripId);
            
        }
    </script>
</body>
</html>

        <div class="route-summary" id="routeSummary">
            <div class="summary-stats">
                <div class="stat-item">
                    <span class="stat-number" id="totalDeliveries">0</span>
                    <span class="stat-label">Total</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number" id="pendingDeliveries">0</span>
                    <span class="stat-label">Pending</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number" id="completedDeliveries">0</span>
                    <span class="stat-label">Completed</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number" id="estimatedTime">0h</span>
                    <span class="stat-label">Est. Time</span>
                </div>
            </div>
        </div>

        <div class="deliveries-container">
            <div class="deliveries-list" id="deliveriesList">
                <div class="loading-placeholder">
                    <div class="loading-spinner"></div>
                    <p>Loading deliveries...</p>
                </div>
            </div>
        </div>

        <div class="modal" id="deliveryModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Delivery Details</h3>
                    <button class="close-btn" onclick="closeModal('deliveryModal')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body" id="deliveryModalBody">
                </div>
            </div>
        </div>

        <div class="modal" id="confirmDeliveryModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Confirm Delivery</h3>
                    <button class="close-btn" onclick="closeModal('confirmDeliveryModal')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="deliveryConfirmForm">
                        <div class="form-group">
                            <label>Recipient Name <span class="required">*</span></label>
                            <input type="text" id="recipientName" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Delivery Photo</label>
                            <div class="photo-upload">
                                <input type="file" id="deliveryPhoto" accept="image/*" capture="environment">
                                <div class="photo-preview" id="photoPreview"></div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Signature</label>
                            <div class="signature-pad">
                                <canvas id="signatureCanvas" width="300" height="150"></canvas>
                                <button type="button" class="clear-signature" onclick="clearSignature()">
                                    <i class="fas fa-eraser"></i> Clear
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Delivery Notes</label>
                            <textarea id="deliveryNotes" rows="3" placeholder="Optional delivery notes..."></textarea>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" onclick="closeModal('confirmDeliveryModal')">
                                Cancel
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-check"></i> Confirm Delivery
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="floating-actions">
            <button class="fab scan-btn" onclick="openScanner()" title="Scan QR Code">
                <i class="fas fa-qrcode"></i>
            </button>
        </div>

        <nav class="bottom-nav">
            <a href="../dashboard.php" class="nav-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="pickups.php" class="nav-item">
                <i class="fas fa-box"></i>
                <span>Pickups</span>
            </a>
            <a href="deliveries.php" class="nav-item active">
                <i class="fas fa-truck"></i>
                <span>Deliveries</span>
            </a>
            <a href="scanner.php" class="nav-item">
                <i class="fas fa-qrcode"></i>
                <span>Scanner</span>
            </a>
            <a href="profile.php" class="nav-item">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
        </nav>
    </div>

    <script>
        class DeliveriesManager {
            constructor() {
                this.currentFilter = 'all';
                this.sortOrder = 'priority'; 
                this.deliveriesData = [];
                this.currentDelivery = null;
                this.signaturePad = null;
                this.init();
            }

            init() {
                this.setupEventListeners();
                this.setupSignaturePad();
                this.loadDeliveries();
                
                setInterval(() => this.loadDeliveries(), 30000);
            }

            setupEventListeners() {
                document.querySelectorAll('.filter-tab').forEach(tab => {
                    tab.addEventListener('click', (e) => {
                        this.setActiveFilter(e.target.dataset.status);
                    });
                });

                document.getElementById('deliveryConfirmForm').addEventListener('submit', (e) => {
                    e.preventDefault();
                    this.submitDeliveryConfirmation();
                });

                document.getElementById('deliveryPhoto').addEventListener('change', (e) => {
                    this.handlePhotoUpload(e);
                });

                document.addEventListener('click', (e) => {
                    if (e.target.closest('.delivery-card')) {
                        const parcelId = e.target.closest('.delivery-card').dataset.parcelId;
                        this.showDeliveryDetails(parcelId);
                    }
                });
            }

            setupSignaturePad() {
                const canvas = document.getElementById('signatureCanvas');
                const ctx = canvas.getContext('2d');
                
                let isDrawing = false;
                let lastX = 0;
                let lastY = 0;

                canvas.addEventListener('mousedown', (e) => {
                    isDrawing = true;
                    [lastX, lastY] = [e.offsetX, e.offsetY];
                });

                canvas.addEventListener('mousemove', (e) => {
                    if (!isDrawing) return;
                    ctx.beginPath();
                    ctx.moveTo(lastX, lastY);
                    ctx.lineTo(e.offsetX, e.offsetY);
                    ctx.stroke();
                    [lastX, lastY] = [e.offsetX, e.offsetY];
                });

                canvas.addEventListener('mouseup', () => isDrawing = false);
                canvas.addEventListener('mouseout', () => isDrawing = false);

                canvas.addEventListener('touchstart', (e) => {
                    e.preventDefault();
                    const rect = canvas.getBoundingClientRect();
                    const touch = e.touches[0];
                    isDrawing = true;
                    lastX = touch.clientX - rect.left;
                    lastY = touch.clientY - rect.top;
                });

                canvas.addEventListener('touchmove', (e) => {
                    e.preventDefault();
                    if (!isDrawing) return;
                    const rect = canvas.getBoundingClientRect();
                    const touch = e.touches[0];
                    const currentX = touch.clientX - rect.left;
                    const currentY = touch.clientY - rect.top;
                    
                    ctx.beginPath();
                    ctx.moveTo(lastX, lastY);
                    ctx.lineTo(currentX, currentY);
                    ctx.stroke();
                    lastX = currentX;
                    lastY = currentY;
                });

                canvas.addEventListener('touchend', () => isDrawing = false);

                ctx.strokeStyle = '#000';
                ctx.lineWidth = 2;
                ctx.lineCap = 'round';
                ctx.lineJoin = 'round';
            }

            async loadDeliveries() {
                try {
                    const response = await fetch('../api/driver-deliveries.php');
                    const data = await response.json();

                    if (data.success) {
                        this.deliveriesData = data.data;
                        this.renderDeliveries();
                        this.updateRouteSummary();
                    } else {
                        this.showError(data.error || 'Failed to load deliveries');
                    }
                } catch (error) {
                    console.error('Error loading deliveries:', error);
                    this.showError('Connection error. Please check your internet connection.');
                }
            }

            renderDeliveries() {
                const container = document.getElementById('deliveriesList');
                let filteredDeliveries = this.filterDeliveries();
                
                filteredDeliveries = this.sortDeliveries(filteredDeliveries);

                if (filteredDeliveries.length === 0) {
                    container.innerHTML = this.getEmptyState();
                    return;
                }

                const deliveriesHTML = filteredDeliveries.map((delivery, index) => 
                    this.createDeliveryCard(delivery, index + 1)
                ).join('');
                container.innerHTML = deliveriesHTML;
            }

            filterDeliveries() {
                if (this.currentFilter === 'all') {
                    return this.deliveriesData.filter(d => ['in_transit', 'out_for_delivery', 'delivered'].includes(d.status));
                }
                return this.deliveriesData.filter(delivery => delivery.status === this.currentFilter);
            }

            sortDeliveries(deliveries) {
                return deliveries.sort((a, b) => {
                    switch (this.sortOrder) {
                        case 'priority':
                            const priorityOrder = { 'high': 3, 'medium': 2, 'low': 1 };
                            return (priorityOrder[b.priority] || 1) - (priorityOrder[a.priority] || 1);
                        case 'distance':
                            return 0;
                        case 'time':
                            return new Date(a.created_at) - new Date(b.created_at);
                        default:
                            return 0;
                    }
                });
            }

            createDeliveryCard(delivery, sequence) {
                const statusColors = {
                    'in_transit': 'blue',
                    'out_for_delivery': 'orange',
                    'delivered': 'green',
                    'failed_delivery': 'red'
                };

                const priorityIcons = {
                    'high': 'fas fa-exclamation-triangle',
                    'medium': 'fas fa-circle',
                    'low': 'fas fa-minus'
                };

                return `
                    <div class="delivery-card" data-parcel-id="${delivery.parcel_id}">
                        <div class="delivery-sequence">${sequence}</div>
                        
                        <div class="delivery-header">
                            <div class="delivery-id">
                                <strong>#${delivery.tracking_number}</strong>
                                <span class="priority-badge priority-${delivery.priority}">
                                    <i class="${priorityIcons[delivery.priority] || 'fas fa-circle'}"></i>
                                    ${delivery.priority || 'Normal'}
                                </span>
                            </div>
                            <div class="delivery-status status-${delivery.status}">
                                ${delivery.status.replace('_', ' ').toUpperCase()}
                            </div>
                        </div>
                        
                        <div class="delivery-details">
                            <div class="delivery-destination">
                                <i class="fas fa-map-marker-alt"></i>
                                <div>
                                    <div class="recipient-name">${delivery.recipient_name}</div>
                                    <div class="delivery-address">${delivery.delivery_address}</div>
                                </div>
                            </div>
                            
                            <div class="delivery-info">
                                <div class="info-item">
                                    <i class="fas fa-box"></i>
                                    <span>${delivery.description || 'Package'}</span>
                                </div>
                                <div class="info-item">
                                    <i class="fas fa-phone"></i>
                                    <span>${delivery.recipient_phone}</span>
                                </div>
                                <div class="info-item">
                                    <i class="fas fa-clock"></i>
                                    <span>Est. ${this.calculateETA(delivery)} mins</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="delivery-actions">
                            ${delivery.status === 'out_for_delivery' ? `
                                <button class="btn-action btn-primary" onclick="confirmDelivery('${delivery.parcel_id}')">
                                    <i class="fas fa-check"></i> Deliver
                                </button>
                            ` : delivery.status === 'in_transit' ? `
                                <button class="btn-action btn-primary" onclick="markOutForDelivery('${delivery.parcel_id}')">
                                    <i class="fas fa-truck"></i> Out for Delivery
                                </button>
                            ` : ''}
                            <button class="btn-action btn-secondary" onclick="getDirections('${delivery.delivery_latitude}', '${delivery.delivery_longitude}')">
                                <i class="fas fa-route"></i> Directions
                            </button>
                            <button class="btn-action btn-outline" onclick="callRecipient('${delivery.recipient_phone}')">
                                <i class="fas fa-phone"></i> Call
                            </button>
                        </div>
                    </div>
                `;
            }

            setActiveFilter(status) {
                this.currentFilter = status;
                
                document.querySelectorAll('.filter-tab').forEach(tab => {
                    tab.classList.remove('active');
                    if (tab.dataset.status === status) {
                        tab.classList.add('active');
                    }
                });
                
                this.renderDeliveries();
                this.updateRouteSummary();
            }

            updateRouteSummary() {
                const filteredDeliveries = this.filterDeliveries();
                const total = filteredDeliveries.length;
                const completed = filteredDeliveries.filter(d => d.status === 'delivered').length;
                const pending = total - completed;
                const estimatedTime = this.calculateTotalTime(filteredDeliveries);

                document.getElementById('totalDeliveries').textContent = total;
                document.getElementById('pendingDeliveries').textContent = pending;
                document.getElementById('completedDeliveries').textContent = completed;
                document.getElementById('estimatedTime').textContent = `${Math.round(estimatedTime / 60)}h`;
            }

            calculateETA(delivery) {
                return Math.floor(Math.random() * 30) + 10;
            }

            calculateTotalTime(deliveries) {
                return deliveries.length * 15;
            }

            async showDeliveryDetails(parcelId) {
                const delivery = this.deliveriesData.find(d => d.parcel_id === parcelId);
                if (!delivery) return;

                const modalBody = document.getElementById('deliveryModalBody');
                modalBody.innerHTML = `
                    <div class="delivery-detail">
                        <div class="detail-section">
                            <h4>Delivery Information</h4>
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <label>Tracking Number:</label>
                                    <span>${delivery.tracking_number}</span>
                                </div>
                                <div class="detail-item">
                                    <label>Status:</label>
                                    <span class="status-badge status-${delivery.status}">${delivery.status}</span>
                                </div>
                                <div class="detail-item">
                                    <label>Priority:</label>
                                    <span>${delivery.priority || 'Normal'}</span>
                                </div>
                                <div class="detail-item">
                                    <label>Weight:</label>
                                    <span>${delivery.weight || 'N/A'} kg</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="detail-section">
                            <h4>Recipient Details</h4>
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <label>Name:</label>
                                    <span>${delivery.recipient_name}</span>
                                </div>
                                <div class="detail-item">
                                    <label>Phone:</label>
                                    <span><a href="tel:${delivery.recipient_phone}">${delivery.recipient_phone}</a></span>
                                </div>
                                <div class="detail-item">
                                    <label>Address:</label>
                                    <span>${delivery.delivery_address}</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="detail-section">
                            <h4>Package Details</h4>
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <label>Description:</label>
                                    <span>${delivery.description || 'Package'}</span>
                                </div>
                                <div class="detail-item">
                                    <label>Weight:</label>
                                    <span>${delivery.weight || 'N/A'} kg</span>
                                </div>
                                <div class="detail-item">
                                    <label>Special Instructions:</label>
                                    <span>${delivery.special_instructions || 'None'}</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="detail-actions">
                            ${delivery.status === 'out_for_delivery' ? `
                                <button class="btn btn-primary" onclick="confirmDelivery('${delivery.parcel_id}')">
                                    <i class="fas fa-check"></i> Confirm Delivery
                                </button>
                            ` : delivery.status === 'in_transit' ? `
                                <button class="btn btn-primary" onclick="markOutForDelivery('${delivery.parcel_id}')">
                                    <i class="fas fa-truck"></i> Out for Delivery
                                </button>
                            ` : ''}
                            <button class="btn btn-secondary" onclick="getDirections('${delivery.delivery_latitude}', '${delivery.delivery_longitude}')">
                                <i class="fas fa-route"></i> Get Directions
                            </button>
                            <button class="btn btn-outline" onclick="callRecipient('${delivery.recipient_phone}')">
                                <i class="fas fa-phone"></i> Call Recipient
                            </button>
                        </div>
                    </div>
                `;

                document.getElementById('deliveryModal').classList.add('active');
            }

            showError(message) {
                const container = document.getElementById('deliveriesList');
                container.innerHTML = `
                    <div class="error-state">
                        <i class="fas fa-exclamation-triangle"></i>
                        <h3>Error Loading Deliveries</h3>
                        <p>${message}</p>
                        <button class="btn btn-primary" onclick="refreshDeliveries()">
                            <i class="fas fa-sync-alt"></i> Retry
                        </button>
                    </div>
                `;
            }

            getEmptyState() {
                return `
                    <div class="empty-state">
                        <i class="fas fa-truck"></i>
                        <h3>No Deliveries</h3>
                        <p>No deliveries found for the selected filter.</p>
                        <button class="btn btn-primary" onclick="refreshDeliveries()">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                `;
            }

            handlePhotoUpload(event) {
                const file = event.target.files[0];
                if (!file) return;

                const reader = new FileReader();
                reader.onload = (e) => {
                    const preview = document.getElementById('photoPreview');
                    preview.innerHTML = `<img src="${e.target.result}" alt="Delivery photo">`;
                };
                reader.readAsDataURL(file);
            }

            async submitDeliveryConfirmation() {
                const recipientName = document.getElementById('recipientName').value;
                const notes = document.getElementById('deliveryNotes').value;
                const photoInput = document.getElementById('deliveryPhoto');
                
                if (!recipientName) {
                    showNotification('Recipient name is required', 'error');
                    return;
                }

                try {
                    let photoBase64 = null;
                    if (photoInput.files[0]) {
                        photoBase64 = await this.fileToBase64(photoInput.files[0]);
                    }

                    const canvas = document.getElementById('signatureCanvas');
                    const signatureBase64 = canvas.toDataURL();

                    const location = await this.getCurrentLocation();

                    const response = await fetch('../api/update-delivery-status.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            parcel_id: this.currentDelivery,
                            status: 'delivered',
                            recipient_name: recipientName,
                            notes: notes,
                            photo: photoBase64,
                            signature: signatureBase64,
                            latitude: location?.latitude,
                            longitude: location?.longitude
                        })
                    });

                    const data = await response.json();
                    
                    if (data.success) {
                        showNotification('Delivery confirmed successfully!', 'success');
                        this.loadDeliveries();
                        closeModal('confirmDeliveryModal');
                        closeModal('deliveryModal');
                        this.resetDeliveryForm();
                    } else {
                        showNotification(data.error || 'Failed to confirm delivery', 'error');
                    }
                } catch (error) {
                    console.error('Error confirming delivery:', error);
                    showNotification('Failed to confirm delivery. Please try again.', 'error');
                }
            }

            fileToBase64(file) {
                return new Promise((resolve, reject) => {
                    const reader = new FileReader();
                    reader.readAsDataURL(file);
                    reader.onload = () => resolve(reader.result);
                    reader.onerror = error => reject(error);
                });
            }

            getCurrentLocation() {
                return new Promise((resolve) => {
                    if (!navigator.geolocation) {
                        resolve(null);
                        return;
                    }

                    navigator.geolocation.getCurrentPosition(
                        (position) => resolve({
                            latitude: position.coords.latitude,
                            longitude: position.coords.longitude
                        }),
                        () => resolve(null)
                    );
                });
            }

            resetDeliveryForm() {
                document.getElementById('deliveryConfirmForm').reset();
                document.getElementById('photoPreview').innerHTML = '';
                this.clearSignature();
            }

            clearSignature() {
                const canvas = document.getElementById('signatureCanvas');
                const ctx = canvas.getContext('2d');
                ctx.clearRect(0, 0, canvas.width, canvas.height);
            }
        }

        const deliveriesManager = new DeliveriesManager();

        function refreshDeliveries() {
            deliveriesManager.loadDeliveries();
        }

        function toggleSort() {
            const sortOptions = ['priority', 'distance', 'time'];
            const currentIndex = sortOptions.indexOf(deliveriesManager.sortOrder);
            deliveriesManager.sortOrder = sortOptions[(currentIndex + 1) % sortOptions.length];
            deliveriesManager.renderDeliveries();
        }

        function optimizeRoute() {
            showNotification('Route optimization coming soon!', 'info');
        }

        function showMapView() {
            showNotification('Map view coming soon!', 'info');
        }

        async function markOutForDelivery(parcelId) {
            if (!confirm('Mark this parcel as out for delivery?')) return;
            
            try {
                const response = await fetch('../api/update-delivery-status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        parcel_id: parcelId,
                        status: 'out_for_delivery',
                        notes: 'Out for delivery'
                    })
                });

                const data = await response.json();
                
                if (data.success) {
                    showNotification('Parcel marked as out for delivery!', 'success');
                    refreshDeliveries();
                    closeModal('deliveryModal');
                } else {
                    showNotification(data.error || 'Failed to update status', 'error');
                }
            } catch (error) {
                console.error('Error updating delivery status:', error);
                showNotification('Connection error. Please try again.', 'error');
            }
        }

        function confirmDelivery(parcelId) {
            deliveriesManager.currentDelivery = parcelId;
            closeModal('deliveryModal');
            document.getElementById('confirmDeliveryModal').classList.add('active');
        }

        function clearSignature() {
            deliveriesManager.clearSignature();
        }

        function getDirections(lat, lng) {
            if (lat && lng) {
                const url = `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`;
                window.open(url, '_blank');
            } else {
                showNotification('Location coordinates not available', 'warning');
            }
        }

        function callRecipient(phone) {
            if (phone) {
                window.location.href = `tel:${phone}`;
            }
        }

        function openScanner() {
            window.location.href = 'scanner.php';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'times' : 'info'}"></i>
                <span>${message}</span>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.classList.add('show');
            }, 100);
            
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }
    </script>
    
    <?php include __DIR__ . '/../../includes/pwa_install_button.php'; ?>
    <script src="../../js/pwa-install.js"></script>
</body>
</html>