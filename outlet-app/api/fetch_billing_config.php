<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ERROR | E_PARSE);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Method not allowed. Use GET."]);
    exit;
}

try {
    require_once __DIR__ . '/../includes/supabase-helper.php';

    if (!class_exists('SupabaseHelper')) {
        echo json_encode([
            'success' => false, 
            'error' => 'SupabaseHelper class not found. Please check if supabase-helper.php exists and contains the class.'
        ]);
        exit;
    }

    $companyId = $_GET['company_id'] ?? null;

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
    $result = $supabase->get('billing_configs', $query);
    $billingConfigs = $result ?? [];

    if (empty($billingConfigs)) {
        $defaultConfig = [
            'base_rate' => 1000,
            'rate_per_kg' => 200,
            'volumetric_divisor' => 5000,
            'currency' => 'ZMW',
            'additional_rules' => [
                'delivery_options' => [
                    'standard' => ['multiplier' => 1.0, 'base_fee' => 1000],
                    'express' => ['multiplier' => 2.5, 'base_fee' => 2500],
                    'sameday' => ['multiplier' => 5.0, 'base_fee' => 5000]
                ],
                'insurance_rate' => 0.02,
                'min_fee' => 500
            ]
        ];
        
        echo json_encode([
            "success" => true,
            "config" => $defaultConfig,
            "is_default" => true,
            "message" => "Using default billing configuration"
        ]);
        exit;
    }

    $config = $billingConfigs[0];
    
    $additionalRules = [];
    if (!empty($config['additional_rules'])) {
        if (is_string($config['additional_rules'])) {
            $additionalRules = json_decode($config['additional_rules'], true) ?? [];
        } else {
            $additionalRules = $config['additional_rules'];
        }
    }

    $billingConfig = [
        'base_rate' => (float)($config['base_rate'] ?? 1000),
        'rate_per_kg' => (float)($config['rate_per_kg'] ?? 200),
        'volumetric_divisor' => (int)($config['volumetric_divisor'] ?? 5000),
        'currency' => $config['currency'] ?? 'ZMW',
        'additional_rules' => array_merge([
            'delivery_options' => [
                'standard' => ['multiplier' => 1.0, 'base_fee' => 1000],
                'express' => ['multiplier' => 2.5, 'base_fee' => 2500],
                'sameday' => ['multiplier' => 5.0, 'base_fee' => 5000]
            ],
            'insurance_rate' => 0.02,
            'min_fee' => 500
        ], $additionalRules)
    ];

    echo json_encode([
        "success" => true,
        "config" => $billingConfig,
        "is_default" => false
    ]);

} catch (Exception $e) {
    error_log("Error fetching billing config: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Failed to fetch billing configuration: " . $e->getMessage()
    ]);
}
?>
