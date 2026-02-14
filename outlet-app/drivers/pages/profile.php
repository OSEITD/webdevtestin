<?php
session_start();

ob_start();
require_once __DIR__ . '/../../../vendor/autoload.php';
ob_end_clean();

require_once __DIR__ . '/../../includes/OutletAwareSupabaseHelper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    header('Location: ../login.php');
    exit;
}

$driverId = $_SESSION['user_id'];
$supabase = new OutletAwareSupabaseHelper();

$profileData = null;
$driverData = null;
$companyData = null;
$errorMessage = '';
$successMessage = '';

try {
    
    $profileResponse = $supabase->get('profiles', "id=eq.{$driverId}");
    if (!empty($profileResponse)) {
        $profileData = $profileResponse[0];
        
        
        if (!empty($profileData['company_id'])) {
            $companyResponse = $supabase->get('companies', "id=eq.{$profileData['company_id']}", 'id,company_name');
            if (!empty($companyResponse)) {
                $companyData = $companyResponse[0];
            }
        }
    }
    
    
    $driverResponse = $supabase->get('drivers', "id=eq.{$driverId}");
    if (!empty($driverResponse)) {
        $driverData = $driverResponse[0];
    }
    
    
    $tripsResponse = $supabase->get('trips', "driver_id=eq.{$driverId}", 'id,trip_status,created_at');
    $totalTrips = count($tripsResponse);
    $completedTrips = count(array_filter($tripsResponse, function($trip) {
        return $trip['trip_status'] === 'completed';
    }));
    
    
    $qpsResponse = $supabase->get('driver_qps', "driver_id=eq.{$driverId}&order=date.desc&limit=30");
    $totalParcels = 0;
    foreach ($qpsResponse as $qps) {
        $totalParcels += $qps['parcels_handled'] ?? 0;
    }
    
} catch (Exception $e) {
    $errorMessage = "Error loading profile: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'update_profile') {
            $updateData = [
                'full_name' => trim($_POST['full_name']),
                'phone' => trim($_POST['phone']),
                'email' => trim($_POST['email']),
                'language' => $_POST['language'] ?? 'en',
                'notifications_enabled' => isset($_POST['notifications_enabled']) ? true : false,
                'notify_parcel' => isset($_POST['notify_parcel']) ? true : false,
                'notify_dispatch' => isset($_POST['notify_dispatch']) ? true : false,
                'notify_urgent' => isset($_POST['notify_urgent']) ? true : false,
            ];
            
            $supabase->update('profiles', $updateData, "id=eq.{$driverId}");
            
            
            $driverUpdateData = [
                'driver_name' => trim($_POST['full_name']),
                'driver_email' => trim($_POST['email']),
                'driver_phone' => trim($_POST['phone']),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $supabase->update('drivers', $driverUpdateData, "id=eq.{$driverId}");
            
            $successMessage = "Profile updated successfully!";
            
            
            $profileResponse = $supabase->get('profiles', "id=eq.{$driverId}");
            if (!empty($profileResponse)) {
                $profileData = $profileResponse[0];
            }
            
            $driverResponse = $supabase->get('drivers', "id=eq.{$driverId}");
            if (!empty($driverResponse)) {
                $driverData = $driverResponse[0];
            }
        }
        
        if ($_POST['action'] === 'change_password') {
            
            $successMessage = "Password change request submitted. Please check your email.";
        }
        
    } catch (Exception $e) {
        $errorMessage = "Error updating profile: " . $e->getMessage();
    }
}

$pageTitle = "Driver Profile"
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Driver Dashboard</title>
    
    <!-- Stylesheets -->
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
        
        .profile-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .profile-header {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 20px;
            padding: 10px 20px;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .back-button:hover {
            background: #f0f4ff;
            transform: translateX(-5px);
        }
        
        .profile-header-content {
            display: flex;
            align-items: center;
            gap: 30px;
            flex-wrap: wrap;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: white;
            font-weight: bold;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .profile-info {
            flex: 1;
            min-width: 250px;
        }
        
        .profile-name {
            font-size: 28px;
            font-weight: bold;
            color: #2d3748;
            margin-bottom: 10px;
        }
        
        .profile-role {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .profile-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .stat-card {
            background: #f7fafc;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            color: #718096;
        }
        
        .profile-content {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
        }
        
        @media (min-width: 768px) {
            .profile-content {
                grid-template-columns: 2fr 1fr;
            }
        }
        
        .profile-section {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .section-title {
            font-size: 20px;
            font-weight: bold;
            color: #2d3748;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title i {
            color: #667eea;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-group input[readonly] {
            background: #f7fafc;
            color: #718096;
            cursor: not-allowed;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 0;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: #667eea;
        }
        
        .checkbox-group label {
            margin: 0;
            cursor: pointer;
            font-weight: normal;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
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
        
        .btn-full {
            width: 100%;
            justify-content: center;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }
        
        .alert-success {
            background: #c6f6d5;
            color: #22543d;
            border: 2px solid #9ae6b4;
        }
        
        .alert-error {
            background: #fed7d7;
            color: #742a2a;
            border: 2px solid #fc8181;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: capitalize;
        }
        
        .status-available {
            background: #c6f6d5;
            color: #22543d;
        }
        
        .status-unavailable {
            background: #fed7d7;
            color: #742a2a;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            color: #718096;
            font-size: 14px;
        }
        
        .info-value {
            color: #2d3748;
            font-weight: 600;
            font-size: 14px;
        }
        
        .notification-settings {
            background: #f7fafc;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }
        
        .notification-settings h4 {
            color: #2d3748;
            margin-bottom: 15px;
            font-size: 16px;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .profile-container > * {
            animation: slideIn 0.5s ease-out;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            color: #718096;
        }
        
        .loading i {
            font-size: 48px;
            color: #667eea;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 1000;
            animation: fadeIn 0.3s ease;
        }
        
        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 20px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-title {
            font-size: 24px;
            font-weight: bold;
            color: #2d3748;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            color: #718096;
            cursor: pointer;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.2s ease;
        }
        
        .modal-close:hover {
            background: #f7fafc;
            color: #2d3748;
        }
        
        .password-input-group {
            position: relative;
            margin-bottom: 20px;
        }
        
        .password-input-group input {
            padding-right: 45px;
        }
        
        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #718096;
            cursor: pointer;
            padding: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .toggle-password:hover {
            color: #667eea;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="profile-container">
        <a href="../index.php" class="back-button">
            <i class="fas fa-arrow-left"></i>
            Back to Dashboard
        </a>
        
        <?php if ($errorMessage): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($successMessage): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($successMessage); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($profileData && $driverData): ?>
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-header-content">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($profileData['full_name'] ?? 'D', 0, 1)); ?>
                    </div>
                    <div class="profile-info">
                        <h1 class="profile-name"><?php echo htmlspecialchars($profileData['full_name'] ?? 'Driver'); ?></h1>
                        <div class="profile-role">
                            <i class="fas fa-id-badge"></i>
                            <?php echo ucfirst($profileData['role'] ?? 'driver'); ?>
                        </div>
                        <?php if ($companyData): ?>
                            <div style="color: #718096; margin-top: 5px;">
                                <i class="fas fa-building"></i>
                                <?php echo htmlspecialchars($companyData['company_name']); ?>
                            </div>
                        <?php endif; ?>
                        <div class="status-badge status-<?php echo $driverData['status'] ?? 'available'; ?>" style="margin-top: 10px;">
                            <i class="fas fa-circle"></i>
                            <?php echo ucfirst($driverData['status'] ?? 'Available'); ?>
                        </div>
                    </div>
                </div>
                
                <div class="profile-stats">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $totalTrips; ?></div>
                        <div class="stat-label">Total Trips</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $completedTrips; ?></div>
                        <div class="stat-label">Completed</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $totalParcels; ?></div>
                        <div class="stat-label">Parcels Delivered</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $completedTrips > 0 ? round(($completedTrips / $totalTrips) * 100) : 0; ?>%</div>
                        <div class="stat-label">Success Rate</div>
                    </div>
                </div>
            </div>
            
            <div class="profile-content">
                <!-- Main Profile Form -->
                <div class="profile-section">
                    <h2 class="section-title">
                        <i class="fas fa-user-edit"></i>
                        Edit Profile
                    </h2>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="form-group">
                            <label for="full_name">Full Name</label>
                            <input type="text" id="full_name" name="full_name" 
                                   value="<?php echo htmlspecialchars($profileData['full_name'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($profileData['email'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($profileData['phone'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="language">Preferred Language</label>
                            <select id="language" name="language">
                                <option value="en" <?php echo ($profileData['language'] ?? 'en') === 'en' ? 'selected' : ''; ?>>English</option>
                                <option value="fr" <?php echo ($profileData['language'] ?? '') === 'fr' ? 'selected' : ''; ?>>French</option>
                                <option value="es" <?php echo ($profileData['language'] ?? '') === 'es' ? 'selected' : ''; ?>>Spanish</option>
                            </select>
                        </div>
                        
                        <div class="notification-settings">
                            <h4><i class="fas fa-bell"></i> Notification Preferences</h4>
                            
                            <div class="checkbox-group">
                                <input type="checkbox" id="notifications_enabled" name="notifications_enabled" 
                                       <?php echo ($profileData['notifications_enabled'] ?? true) ? 'checked' : ''; ?>>
                                <label for="notifications_enabled">Enable all notifications</label>
                            </div>
                            
                            <div class="checkbox-group">
                                <input type="checkbox" id="notify_parcel" name="notify_parcel" 
                                       <?php echo ($profileData['notify_parcel'] ?? true) ? 'checked' : ''; ?>>
                                <label for="notify_parcel">Parcel updates</label>
                            </div>
                            
                            <div class="checkbox-group">
                                <input type="checkbox" id="notify_dispatch" name="notify_dispatch" 
                                       <?php echo ($profileData['notify_dispatch'] ?? true) ? 'checked' : ''; ?>>
                                <label for="notify_dispatch">Dispatch assignments</label>
                            </div>
                            
                            <div class="checkbox-group">
                                <input type="checkbox" id="notify_urgent" name="notify_urgent" 
                                       <?php echo ($profileData['notify_urgent'] ?? true) ? 'checked' : ''; ?>>
                                <label for="notify_urgent">Urgent deliveries</label>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-full" style="margin-top: 20px;">
                            <i class="fas fa-save"></i>
                            Save Changes
                        </button>
                    </form>
                </div>
                
                <!-- Sidebar Info -->
                <div>
                    <!-- Account Information -->
                    <div class="profile-section" style="margin-bottom: 20px;">
                        <h2 class="section-title">
                            <i class="fas fa-info-circle"></i>
                            Account Information
                        </h2>
                        
                        <div class="info-row">
                            <span class="info-label">Driver ID</span>
                            <span class="info-value"><?php echo substr($driverId, 0, 8); ?>...</span>
                        </div>
                        
                        <div class="info-row">
                            <span class="info-label">License Number</span>
                            <span class="info-value"><?php echo htmlspecialchars($driverData['license_number'] ?? 'N/A'); ?></span>
                        </div>
                        
                        <div class="info-row">
                            <span class="info-label">Member Since</span>
                            <span class="info-value">
                                <?php 
                                    $createdAt = $driverData['created_at'] ?? '';
                                    echo $createdAt ? date('M d, Y', strtotime($createdAt)) : 'N/A';
                                ?>
                            </span>
                        </div>
                        
                        <div class="info-row">
                            <span class="info-label">Last Updated</span>
                            <span class="info-value">
                                <?php 
                                    $updatedAt = $driverData['updated_at'] ?? '';
                                    echo $updatedAt ? date('M d, Y', strtotime($updatedAt)) : 'N/A';
                                ?>
                            </span>
                        </div>
                        
                        <div class="info-row">
                            <span class="info-label">Current Location</span>
                            <span class="info-value"><?php echo htmlspecialchars($driverData['current_location'] ?? 'Not tracking'); ?></span>
                        </div>
                    </div>
                    
                    <!-- Security Section -->
                    <div class="profile-section">
                        <h2 class="section-title">
                            <i class="fas fa-lock"></i>
                            Security
                        </h2>
                        
                        <p style="color: #718096; font-size: 14px; margin-bottom: 15px;">
                            Update your password to keep your account secure.
                        </p>
                        
                        <button type="button" class="btn btn-secondary btn-full" onclick="openPasswordModal()">
                            <i class="fas fa-key"></i>
                            Change Password
                        </button>
                        
                        <div style="margin-top: 20px;">
                            <div class="info-row">
                                <span class="info-label">Last Password Change</span>
                                <span class="info-value">
                                    <?php 
                                        $pwdUpdated = $profileData['password_last_updated'] ?? null;
                                        echo $pwdUpdated ? date('M d, Y', strtotime($pwdUpdated)) : 'Never';
                                    ?>
                                </span>
                            </div>
                            
                            <div class="info-row">
                                <span class="info-label">Session Timeout</span>
                                <span class="info-value"><?php echo $profileData['session_timeout'] ?? 30; ?> minutes</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="profile-section loading">
                <i class="fas fa-spinner"></i>
                <p>Loading profile data...</p>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.animation = 'slideOut 0.5s ease-out';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
        
        
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideOut {
                from {
                    opacity: 1;
                    transform: translateY(0);
                }
                to {
                    opacity: 0;
                    transform: translateY(-10px);
                }
            }
        `;
        document.head.appendChild(style);
        
        
        const masterToggle = document.getElementById('notifications_enabled');
        const notificationCheckboxes = ['notify_parcel', 'notify_dispatch', 'notify_urgent'];
        
        if (masterToggle) {
            masterToggle.addEventListener('change', function() {
                notificationCheckboxes.forEach(id => {
                    const checkbox = document.getElementById(id);
                    if (checkbox) {
                        checkbox.disabled = !this.checked;
                        if (!this.checked) {
                            checkbox.checked = false;
                        }
                    }
                });
            });
            
            
            if (!masterToggle.checked) {
                notificationCheckboxes.forEach(id => {
                    const checkbox = document.getElementById(id);
                    if (checkbox) checkbox.disabled = true;
                });
            }
        }
    </script>
    
    <!-- Password Change Modal -->
    <div id="passwordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Change Password</h3>
                <button class="modal-close" onclick="closePasswordModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="passwordChangeForm" onsubmit="handlePasswordChange(event)">
                <div class="form-group password-input-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" required>
                    <button type="button" class="toggle-password" onclick="togglePasswordVisibility('current_password')">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                
                <div class="form-group password-input-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" required minlength="8">
                    <button type="button" class="toggle-password" onclick="togglePasswordVisibility('new_password')">
                        <i class="fas fa-eye"></i>
                    </button>
                    <small style="color: #718096; font-size: 12px; display: block; margin-top: 5px;">
                        Must be at least 8 characters long
                    </small>
                </div>
                
                <div class="form-group password-input-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                    <button type="button" class="toggle-password" onclick="togglePasswordVisibility('confirm_password')">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                
                <div id="passwordError" style="display: none; color: #e53e3e; margin-bottom: 15px; padding: 10px; background: #fed7d7; border-radius: 8px; font-size: 14px;">
                </div>
                
                <button type="submit" class="btn btn-primary btn-full" id="submitPasswordBtn">
                    <i class="fas fa-save"></i>
                    Update Password
                </button>
            </form>
        </div>
    </div>
    
    <script>
        function openPasswordModal() {
            document.getElementById('passwordModal').classList.add('active');
            document.getElementById('passwordChangeForm').reset();
            document.getElementById('passwordError').style.display = 'none';
        }
        
        function closePasswordModal() {
            document.getElementById('passwordModal').classList.remove('active');
        }
        
        function togglePasswordVisibility(inputId) {
            const input = document.getElementById(inputId);
            const button = input.nextElementSibling;
            const icon = button.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        async function handlePasswordChange(event) {
            event.preventDefault();
            
            const currentPassword = document.getElementById('current_password').value;
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const errorDiv = document.getElementById('passwordError');
            const submitBtn = document.getElementById('submitPasswordBtn');
            
            
            if (newPassword !== confirmPassword) {
                errorDiv.textContent = 'New passwords do not match!';
                errorDiv.style.display = 'block';
                return;
            }
            
            if (newPassword.length < 8) {
                errorDiv.textContent = 'Password must be at least 8 characters long!';
                errorDiv.style.display = 'block';
                return;
            }
            
            if (newPassword === currentPassword) {
                errorDiv.textContent = 'New password must be different from current password!';
                errorDiv.style.display = 'block';
                return;
            }
            
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
            errorDiv.style.display = 'none';
            
            try {
                const response = await fetch('../api/change_password.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        current_password: currentPassword,
                        new_password: newPassword
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    
                    const successAlert = document.createElement('div');
                    successAlert.className = 'alert alert-success';
                    successAlert.innerHTML = '<i class="fas fa-check-circle"></i> Password updated successfully!';
                    document.querySelector('.profile-container').insertBefore(successAlert, document.querySelector('.profile-container').firstChild);
                    
                    
                    closePasswordModal();
                    
                    
                    setTimeout(() => {
                        successAlert.style.animation = 'slideOut 0.5s ease-out';
                        setTimeout(() => successAlert.remove(), 500);
                    }, 5000);
                    
                    
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    errorDiv.textContent = result.error || 'Failed to update password. Please try again.';
                    errorDiv.style.display = 'block';
                }
            } catch (error) {
                console.error('Error changing password:', error);
                errorDiv.textContent = 'An error occurred. Please try again later.';
                errorDiv.style.display = 'block';
            } finally {
                
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-save"></i> Update Password';
            }
        }
        
        
        document.getElementById('passwordModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closePasswordModal();
            }
        });
        
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && document.getElementById('passwordModal').classList.contains('active')) {
                closePasswordModal();
            }
        });
    </script>
    
    <?php include __DIR__ . '/../../includes/pwa_install_button.php'; ?>
    <script src="../../js/pwa-install.js"></script>
</body>
</html>
