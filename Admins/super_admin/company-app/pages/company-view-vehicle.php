<?php
require_once __DIR__ . '/../../auth/session-check.php';
require_once __DIR__ . '/../api/supabase-client.php';

$vehicleId = $_GET['id'] ?? null;
$vehicle = null;

if ($vehicleId) {
    try {
        $supabase = new SupabaseClient();
        $supabaseUrl = $supabase->getUrl();
        $supabaseKey = $supabase->getKey();
        
        $apiEndpoint = $supabaseUrl . '/rest/v1/vehicle?id=eq.' . urlencode($vehicleId) . '&select=*';
        
        $ch = curl_init($apiEndpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey"
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $vehicles = json_decode($response, true);
            if (!empty($vehicles) && is_array($vehicles)) {
                $vehicle = $vehicles[0];
            }
        }
    } catch (Exception $e) {
        error_log('Error fetching vehicle: ' . $e->getMessage());
    }
}

$page_title = 'Company - View Vehicle';        
include __DIR__ . '/../includes/header.php';        
?>

<body class="bg-gray-100 min-h-screen">
    <style>
        .vehicle-profile-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            max-width: 700px;
            margin: 2rem auto;
            overflow: hidden;
        }
        .profile-header {
            background: linear-gradient(135deg, #2E0D2A, #4a1545);
            color: white;
            padding: 2rem;
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        .profile-avatar {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            flex-shrink: 0;
        }
        .profile-header-info h2 {
            margin: 0 0 0.25rem 0;
            font-size: 1.5rem;
        }
        .profile-header-info p {
            margin: 0;
            opacity: 0.8;
            font-size: 0.95rem;
        }
        .profile-body {
            padding: 2rem;
        }
        .detail-section {
            margin-bottom: 1.5rem;
        }
        .detail-section h3 {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #9ca3af;
            margin: 0 0 0.75rem 0;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #f3f4f6;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.6rem 0;
        }
        .detail-row + .detail-row {
            border-top: 1px solid #f9fafb;
        }
        .detail-label {
            font-weight: 500;
            color: #6b7280;
            font-size: 0.9rem;
        }
        .detail-value {
            color: #1f2937;
            font-weight: 500;
            text-align: right;
            max-width: 60%;
            word-break: break-word;
        }
        .status-pill {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .status-available { background: #d1fae5; color: #065f46; }
        .status-assigned { background: #dbeafe; color: #1e40af; }
        .status-out-for-delivery { background: #fed7aa; color: #92400e; }
        .status-unavailable { background: #e5e7eb; color: #374151; }
        .status-maintenance { background: #fee2e2; color: #991b1b; }
        .profile-actions {
            display: flex;
            gap: 0.75rem;
            padding: 1.5rem 2rem;
            border-top: 1px solid #f3f4f6;
            flex-wrap: wrap;
        }
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.65rem 1.25rem;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.9rem;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-back {
            background: #f3f4f6;
            color: #4b5563;
        }
        .btn-back:hover { background: #e5e7eb; }
        .btn-edit {
            background: #2E0D2A;
            color: white;
        }
        .btn-edit:hover { background: #4a1545; }
        .btn-delete {
            background: #fee2e2;
            color: #991b1b;
            margin-left: auto;
        }
        .btn-delete:hover { background: #fecaca; }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #6b7280;
            text-decoration: none;
            margin-bottom: 0.5rem;
        }
        .back-link:hover { color: #374151; }
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #6b7280;
        }
        .empty-state i { font-size: 3rem; margin-bottom: 1rem; color: #d1d5db; }
        @media (max-width: 640px) {
            .profile-header { flex-direction: column; text-align: center; }
            .detail-row { flex-direction: column; align-items: flex-start; gap: 0.25rem; }
            .detail-value { text-align: left; max-width: 100%; }
            .profile-actions { flex-direction: column; }
        }
    </style>

    <div class="mobile-dashboard">
        <main class="main-content">
            <div class="content-header">
                <a href="company-vehicles.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Back to Vehicles
                </a>
                <h1>Vehicle Details</h1>
            </div>

            <?php if ($vehicle): ?>
                <?php
                    $vehicleName = $vehicle['name'] ?? 'Unknown';
                    $plateNumber = $vehicle['plate_number'] ?? 'N/A';
                    $status = strtolower($vehicle['status'] ?? 'available');
                    $statusClass = 'status-available';
                    if ($status === 'assigned') $statusClass = 'status-assigned';
                    if ($status === 'out_for_delivery' || $status === 'out-for-delivery') $statusClass = 'status-out-for-delivery';
                    if ($status === 'unavailable') $statusClass = 'status-unavailable';
                    if ($status === 'maintenance') $statusClass = 'status-maintenance';

                    $createdAt = isset($vehicle['created_at']) ? date('d M Y, H:i', strtotime($vehicle['created_at'])) : 'N/A';
                    $updatedAt = isset($vehicle['updated_at']) ? date('d M Y, H:i', strtotime($vehicle['updated_at'])) : 'N/A';
                ?>

                <div class="vehicle-profile-card">
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <i class="fas fa-truck"></i>
                        </div>
                        <div class="profile-header-info">
                            <h2><?php echo htmlspecialchars($vehicleName); ?></h2>
                            <p><?php echo htmlspecialchars($plateNumber); ?></p>
                        </div>
                    </div>

                    <div class="profile-body">
                        <!-- Vehicle Info -->
                        <div class="detail-section">
                            <h3>Vehicle Information</h3>
                            <div class="detail-row">
                                <span class="detail-label">Vehicle Name</span>
                                <span class="detail-value"><?php echo htmlspecialchars($vehicle['name'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Plate Number</span>
                                <span class="detail-value"><?php echo htmlspecialchars($vehicle['plate_number'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Status</span>
                                <span class="detail-value"><span class="status-pill <?php echo $statusClass; ?>"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $status))); ?></span></span>
                            </div>
                        </div>

                        <!-- Specifications -->
                        <?php if (!empty($vehicle['make']) || !empty($vehicle['model']) || !empty($vehicle['year'])): ?>
                        <div class="detail-section">
                            <h3>Specifications</h3>
                            <?php if (!empty($vehicle['make'])): ?>
                            <div class="detail-row">
                                <span class="detail-label">Make</span>
                                <span class="detail-value"><?php echo htmlspecialchars($vehicle['make']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($vehicle['model'])): ?>
                            <div class="detail-row">
                                <span class="detail-label">Model</span>
                                <span class="detail-value"><?php echo htmlspecialchars($vehicle['model']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($vehicle['year'])): ?>
                            <div class="detail-row">
                                <span class="detail-label">Year</span>
                                <span class="detail-value"><?php echo htmlspecialchars($vehicle['year']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Additional Details -->
                        <?php if (!empty($vehicle['vin']) || !empty($vehicle['color'])): ?>
                        <div class="detail-section">
                            <h3>Additional Details</h3>
                            <?php if (!empty($vehicle['vin'])): ?>
                            <div class="detail-row">
                                <span class="detail-label">VIN</span>
                                <span class="detail-value" style="font-size:0.8rem; color:#9ca3af;"><?php echo htmlspecialchars($vehicle['vin']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($vehicle['color'])): ?>
                            <div class="detail-row">
                                <span class="detail-label">Color</span>
                                <span class="detail-value"><?php echo htmlspecialchars($vehicle['color']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Vehicle ID -->
                        <div class="detail-section">
                            <h3>System Information</h3>
                            <div class="detail-row">
                                <span class="detail-label">Vehicle ID</span>
                                <span class="detail-value" style="font-size:0.8rem; color:#9ca3af;"><?php echo htmlspecialchars($vehicle['id'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Created</span>
                                <span class="detail-value"><?php echo htmlspecialchars($createdAt); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Last Updated</span>
                                <span class="detail-value"><?php echo htmlspecialchars($updatedAt); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="profile-actions">
                        <a href="company-vehicles.php" class="btn-action btn-back">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                        <a href="company-edit-vehicle.php?id=<?php echo urlencode($vehicle['id']); ?>" class="btn-action btn-edit">
                            <i class="fas fa-pen"></i> Edit Vehicle
                        </a>
                        <button class="btn-action btn-delete" id="deleteVehicleBtn">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </div>

            <?php else: ?>
                <div class="vehicle-profile-card">
                    <div class="empty-state">
                        <i class="fas fa-car-side"></i>
                        <p>Vehicle not found or no ID provided.</p>
                        <a href="company-vehicles.php" class="btn-action btn-back" style="display:inline-flex; margin-top:1rem;">
                            <i class="fas fa-arrow-left"></i> Back to Vehicles
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Include JavaScript for sidebar functionality -->
    <script src="../assets/js/company-scripts.js"></script>

    <script>
    <?php if ($vehicle): ?>
    document.addEventListener('DOMContentLoaded', () => {
        const deleteBtn = document.getElementById('deleteVehicleBtn');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', () => {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Delete Vehicle?',
                        html: 'Are you sure you want to delete <strong><?php echo htmlspecialchars($vehicleName); ?></strong>?<br><small>This action cannot be undone.</small>',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#991b1b',
                        cancelButtonColor: '#6b7280',
                        confirmButtonText: 'Yes, delete',
                        cancelButtonText: 'Cancel'
                    }).then(async (result) => {
                        if (result.isConfirmed) {
                            try {
                                const response = await fetch('../api/delete_vehicle.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json'
                                    },
                                    body: JSON.stringify({
                                        id: '<?php echo $vehicle['id']; ?>'
                                    })
                                });
                                const data = await response.json();

                                if (data.success) {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Deleted!',
                                        text: 'Vehicle has been deleted.',
                                        confirmButtonColor: '#2e0d2a',
                                        timer: 2000,
                                        timerProgressBar: true
                                    }).then(() => {
                                        window.location.href = 'company-vehicles.php';
                                    });
                                } else {
                                    Swal.fire({ icon: 'error', title: 'Error', text: data.message || data.error || 'Failed to delete vehicle', confirmButtonColor: '#2e0d2a' });
                                }
                            } catch (err) {
                                Swal.fire({ icon: 'error', title: 'Error', text: 'Could not connect to the server.', confirmButtonColor: '#2e0d2a' });
                            }
                        }
                    });
                } else {
                    if (confirm('Are you sure you want to delete this vehicle?')) {
                        fetch('../api/delete_vehicle.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ id: '<?php echo $vehicle['id']; ?>' })
                        }).then(r => r.json()).then(data => {
                            if (data.success) {
                                alert('Vehicle deleted successfully');
                                window.location.href = 'company-vehicles.php';
                            } else {
                                alert('Error: ' + (data.error || 'Failed to delete'));
                            }
                        }).catch(err => alert('Error: ' + err.message));
                    }
                }
            });
        }
    });
    <?php endif; ?>
    </script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
