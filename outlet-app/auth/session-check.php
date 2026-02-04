<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    
    header("Location: ../login.php");
    exit();
}

$allowed_roles = ['outlet_manager', 'outlet_admin', 'driver'];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
    
    header("Location: ../login.php");
    exit();
}

if (isset($_SESSION['access_token'])) {
    
    
}

return true;
?>
