<?php
// Shim front controller that forwards requests to the Admins/super_admin front controller.
// This avoids duplicating files and lets the centralized Admins code handle routing.

// Change working directory to the Admins copy
$adminsPath = __DIR__ . '/../Admins/super_admin';
if (!is_dir($adminsPath)) {
    header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error');
    echo "Admin front controller not found at: $adminsPath";
    exit;
}

chdir($adminsPath);

// Let the Admins front controller handle the request (it expects to run from its own folder)
require __DIR__ . '/../Admins/super_admin/index.php';
