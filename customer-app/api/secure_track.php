<?php
ob_start();
error_reporting(E_ERROR | E_PARSE | E_CORE_ERROR);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    http_response_code(200);
    exit();
}
try {
    $autoloadPath = __DIR__ . '/../../vendor/autoload.php';
    if (!file_exists($autoloadPath)) {
        throw new Exception("Composer autoload file not found at: $autoloadPath");
    }
    require_once $autoloadPath;
    $supabasePath = __DIR__ . '/../includes/supabase.php';
    if (!file_exists($supabasePath)) {
        throw new Exception("Supabase configuration file not found at: $supabasePath");
    }
    require_once $supabasePath;
    $trackerPath = __DIR__ . '/../includes/SecureParcelTracker.php';
    if (!file_exists($trackerPath)) {
        throw new Exception("SecureParcelTracker class not found at: $trackerPath");
    }
    require_once $trackerPath;
    $unwantedOutput = ob_get_contents();
    if (!empty($unwantedOutput)) {
        error_log("Unexpected output during include: " . $unwantedOutput);
    }
    ob_clean();
} catch (Exception $e) {
    error_log("Exception during dependency loading: " . $e->getMessage());
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load required dependencies: ' . $e->getMessage()
    ]);
    exit();
} catch (Error $e) {
    error_log("Fatal error during dependency loading: " . $e->getMessage());
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Fatal error loading dependencies: ' . $e->getMessage()
    ]);
    exit();
}
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_samesite' => 'Strict'
]);
$rateLimitKey = 'tracking_attempts_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$maxAttemptsPerHour = 20;
if (!isset($_SESSION[$rateLimitKey])) {
    $_SESSION[$rateLimitKey] = ['count' => 0, 'reset_time' => time() + 3600];
} elseif ($_SESSION[$rateLimitKey]['reset_time'] < time()) {
    $_SESSION[$rateLimitKey] = ['count' => 0, 'reset_time' => time() + 3600];
}
$_SESSION[$rateLimitKey]['count']++;
if ($_SESSION[$rateLimitKey]['count'] > $maxAttemptsPerHour) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'error' => 'Too many tracking attempts. Please try again later.',
        'retry_after' => $_SESSION[$rateLimitKey]['reset_time'] - time()
    ]);
    exit();
}
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (!$input) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid request data'
    ]);
    exit();
}
$action = $input['action'] ?? 'track_parcel';
$tracker = new SecureParcelTracker();
try {
    switch ($action) {
        case 'track_parcel':
        default:
            ob_end_clean();
            $result = handleTrackParcel($tracker, $input);
            echo json_encode($result);
            break;
        case 'get_tracking_history':
            ob_end_clean();
            echo json_encode(handleTrackingHistory($tracker, $input));
            break;
        case 'verify_company_access':
            ob_end_clean();
            echo json_encode(handleCompanyAccess($tracker, $input));
            break;
    }
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
function handleTrackParcel($tracker, $input) {
    $required = ['track_number', 'phone_number', 'nrc'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            return [
                'success' => false,
                'error' => "Field '$field' is required"
            ];
        }
    }
    $companySubdomain = $input['company_subdomain'] ?? null;
    $result = $tracker->verifyAndTrackParcel(
        $input['track_number'],
        $input['phone_number'],
        $input['nrc'],
        $companySubdomain
    );
    if ($result['success']) {
        $companyName = null;
        if (isset($result['data']['company_info']['company_name'])) {
            $companyName = $result['data']['company_info']['company_name'];
        }
        $_SESSION['verified_tracking_data'] = [
            'parcel' => $result['data'],
            'customer_role' => $result['customer_role'],
            'company' => $companyName,
            'verified_at' => time(),
            'track_number' => $input['track_number']
        ];
        $result['redirect_url'] = '../track_details.php';
    }
    return $result;
}
function handleTrackingHistory($tracker, $input) {
    $required = ['parcel_id', 'phone_number', 'nrc'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            return [
                'success' => false,
                'error' => "Field '$field' is required"
            ];
        }
    }
    return $tracker->getTrackingHistory(
        $input['parcel_id'],
        $input['phone_number'],
        $input['nrc']
    );
}
function handleCompanyAccess($tracker, $input) {
    if (empty($input['company_subdomain'])) {
        return [
            'success' => false,
            'error' => 'Company subdomain is required'
        ];
    }
    try {
        $companies = $tracker->callSupabase("companies?subdomain=eq.{$input['company_subdomain']}&status=eq.active&select=id,company_name,subdomain");
        if (empty($companies)) {
            return [
                'success' => false,
                'error' => 'Company not found or inactive'
            ];
        }
        return [
            'success' => true,
            'company' => [
                'name' => $companies[0]['company_name'],
                'subdomain' => $companies[0]['subdomain']
            ]
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Unable to verify company access'
        ];
    }
}
?>