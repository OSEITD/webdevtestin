<?php
// Require centralized init to configure error reporting, session and output buffering
require_once __DIR__ . '/init.php';

// Calculate the base URL for the admin section - use relative path for portability
$adminBaseUrl = '..';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="admin-base-url" content="<?php echo $adminBaseUrl; ?>">
    
    <!-- Poppins Font -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- SweetAlert2 for popups -->
     <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Admin styles -->
    <link rel="stylesheet" href="<?php echo $adminBaseUrl; ?>/assets/css/admin-dashboard.css">
    <link rel="stylesheet" href="<?php echo $adminBaseUrl; ?>/assets/css/dashboard-improvements.css">
    <link rel="stylesheet" href="<?php echo $adminBaseUrl; ?>/assets/css/view-details.css">
    
    <!-- Core scripts -->
    <script src="<?php echo $adminBaseUrl; ?>/assets/js/admin-scripts.js" defer></script>
    <script src="<?php echo $adminBaseUrl; ?>/assets/js/search.js" defer></script>
    
    <title><?php echo $pageTitle ?? 'Admin Dashboard'; ?></title>
</head>