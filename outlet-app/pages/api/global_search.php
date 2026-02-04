<?php

while (ob_get_level()) {
    ob_end_clean();
}

header('HTTP/1.1 301 Moved Permanently');
header('Location: ../../api/global_search.php');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'API moved',
        'redirect' => '../../api/global_search.php',
        'message' => 'Global search API has moved to /outlet-app/api/global_search.php'
    ]);
}

exit();
?>