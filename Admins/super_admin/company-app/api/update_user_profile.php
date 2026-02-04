<?php
session_start();

header('Content-Type: application/json');

// Lightweight method guard
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Accept either `user_id` (profile user id) or `id` (some pages set different keys)
if (!isset($_SESSION['user_id']) && !isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized - no active session']);
    exit;
}

$userId = $_SESSION['user_id'] ?? $_SESSION['id'];

// Load shared Supabase client (uses configured project key/service role key)
require_once __DIR__ . '/supabase-client.php';
$supabase = new SupabaseClient();

// Parse incoming data: prefer multipart/form-data ($_POST + $_FILES) or JSON body
$data = [];
if (!empty($_POST)) {
    // When FormData is used (file upload path) the fields arrive in $_POST
    $data = $_POST;
} else {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) $data = $decoded;
}

// If no fields at all, return an error
if (empty($data) && empty($_FILES)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No updatable fields provided']);
    exit;
}

// Map client fields to DB column names
$mapping = [
    'name' => 'full_name',
    'email' => 'email',
    'phone' => 'phone',
    // we intentionally do not map 'password' here; password changes should go through the dedicated auth flow
];

$update = [];
foreach ($mapping as $in => $out) {
    if (isset($data[$in]) && $data[$in] !== '') {
        // Cast phone to string to preserve leading zeros
        $val = $data[$in];
        if ($out === 'phone') $val = (string)$val;
        $update[$out] = $val;
    }
}

// For now we do not support avatar binary storage here; if a file is included, return a helpful message
if (!empty($_FILES) && count($_FILES) > 0) {
    // TODO: implement file storage (local or Supabase storage) and set avatar_url
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Avatar uploads are not supported by this endpoint yet']);
    exit;
}

if (empty($update)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No valid updatable fields provided']);
    exit;
}

try {
    // Use Supabase client's put() which selects an appropriate auth key (service role if available)
    $result = $supabase->put("profiles?id=eq.$userId", $update);

    // put() may return true (no body) or parsed response array. Normalize success response
    // Update session values to reflect the change
    if (isset($update['full_name'])) $_SESSION['user_name'] = $update['full_name'];
    if (isset($update['email'])) $_SESSION['user_email'] = $update['email'];
    if (isset($update['phone'])) $_SESSION['user_phone'] = $update['phone'];

    echo json_encode(['success' => true, 'data' => $result]);
} catch (Exception $e) {
    error_log('update_user_profile error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to update profile', 'details' => $e->getMessage()]);
}
?>