<?php

require_once __DIR__ . '/../../includes/session_manager.php';
initSession();
require_once __DIR__ . '/../../includes/OutletAwareSupabaseHelper.php';
header('Content-Type: application/json');
if (!isset($_SESSION['user_id']) || !isset($_SESSION['company_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
$helper = new OutletAwareSupabaseHelper();
// Return both available and assigned drivers for trip assignment.
$filters = "deleted_at=is.null&status=in.(available,assigned)";
$select = "id,driver_name,driver_email,driver_phone,status";
$drivers = $helper->get('drivers', $filters, $select);
echo json_encode(['success' => true, 'drivers' => $drivers]);