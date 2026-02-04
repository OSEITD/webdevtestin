<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Method not allowed. Use GET."]);
    exit;
}

require_once __DIR__ . '/../includes/supabase-helper.php';

if (!class_exists('SupabaseHelper')) {
    echo json_encode([
        'success' => false, 
        'error' => 'SupabaseHelper class not found. Please check if supabase-helper.php exists and contains the class.'
    ]);
    exit;
}

$companyId = $_GET['company_id'] ?? '';
$phone = $_GET['phone'] ?? '';
$email = $_GET['email'] ?? '';

if (empty($companyId) || (empty($phone) && empty($email))) {
    echo json_encode([
        'success' => false, 
        'error' => 'Missing required parameters: company_id and (phone or email)'
    ]);
    exit;
}

try {
    $supabase = new SupabaseHelper();
    
    $filters = ["company_id=eq.$companyId"];
    
    if (!empty($phone)) {
        $filters[] = "phone=eq." . urlencode($phone);
    }
    
    if (!empty($email)) {
        $filters[] = "email=ilike." . urlencode(strtolower($email));
    }
    
    $query = implode('&', $filters);
    $response = $supabase->get('customers', $query);
    
    if ($response === null) {
        $result = [];
    } else {
        $result = $response['data'] ?? $response ?? [];
    }
    
    if (!empty($result)) {
        echo json_encode([
            'success' => true, 
            'customer' => $result[0]
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'customer' => null,
            'message' => 'No customer found'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
}
?>
