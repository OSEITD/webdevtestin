<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ERROR | E_PARSE);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT'])) {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Method not allowed. Use POST or PUT."]);
    exit;
}

try {
    
    require_once __DIR__ . '/../includes/supabase-helper.php';

    if (!class_exists('SupabaseHelper')) {
        echo json_encode([
            'success' => false, 
            'error' => 'SupabaseHelper class not found.'
        ]);
        exit;
    }

    
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Invalid JSON input"
        ]);
        exit;
    }

    
    $companyId = $input['company_id'] ?? null;
    $baseRate = (float)($input['base_rate'] ?? 1000);
    $ratePerKg = (float)($input['rate_per_kg'] ?? 200);
    $volumetricDivisor = (int)($input['volumetric_divisor'] ?? 5000);
    $currency = $input['currency'] ?? 'ZMW';
    $additionalRules = $input['additional_rules'] ?? [
        'delivery_options' => [
            'standard' => ['multiplier' => 1.0, 'base_fee' => 1000],
            'express' => ['multiplier' => 2.5, 'base_fee' => 2500],
            'sameday' => ['multiplier' => 5.0, 'base_fee' => 5000]
        ],
        'insurance_rate' => 0.02,
        'min_fee' => 500
    ];

    if (!$companyId) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Company ID is required"
        ]);
        exit;
    }

    
    $supabase = new SupabaseHelper();

    
    $query = "company_id=eq.$companyId";
    $existingConfigs = $supabase->get('billing_configs', $query);

    $configData = [
        'company_id' => $companyId,
        'base_rate' => $baseRate,
        'rate_per_kg' => $ratePerKg,
        'volumetric_divisor' => $volumetricDivisor,
        'currency' => $currency,
        'additional_rules' => json_encode($additionalRules),
        'updated_at' => date('c') 
    ];

    if (empty($existingConfigs)) {
        
        $configData['created_at'] = date('c');
        $result = $supabase->post('billing_configs', $configData);
        $action = 'created';
    } else {
        
        $result = $supabase->put('billing_configs', $configData, $query);
        $action = 'updated';
    }

    if ($result === false) {
        throw new Exception("Failed to save billing configuration");
    }

    echo json_encode([
        "success" => true,
        "message" => "Billing configuration $action successfully",
        "config" => [
            'company_id' => $companyId,
            'base_rate' => $baseRate,
            'rate_per_kg' => $ratePerKg,
            'volumetric_divisor' => $volumetricDivisor,
            'currency' => $currency,
            'additional_rules' => $additionalRules
        ]
    ]);

} catch (Exception $e) {
    error_log("Error saving billing config: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Failed to save billing configuration: " . $e->getMessage()
    ]);
}
?>
