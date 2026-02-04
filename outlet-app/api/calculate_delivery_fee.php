<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ERROR | E_PARSE);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Method not allowed. Use POST."]);
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
    $weight = (float)($input['weight'] ?? 0);
    $deliveryOption = $input['delivery_option'] ?? '';
    $parcelValue = (float)($input['parcel_value'] ?? 0);
    $insuranceAmount = (float)($input['insurance_amount'] ?? 0);
    
    $length = (float)($input['length'] ?? 0);
    $width = (float)($input['width'] ?? 0);
    $height = (float)($input['height'] ?? 0);

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
    $billingConfigs = $supabase->get('billing_configs', $query);

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

    $config = $defaultConfig;
    
    if (!empty($billingConfigs)) {
        $configData = $billingConfigs[0];
        
        $additionalRules = [];
        if (!empty($configData['additional_rules'])) {
            if (is_string($configData['additional_rules'])) {
                $additionalRules = json_decode($configData['additional_rules'], true) ?? [];
            } else {
                $additionalRules = $configData['additional_rules'];
            }
        }

        $config = [
            'base_rate' => (float)($configData['base_rate'] ?? $defaultConfig['base_rate']),
            'rate_per_kg' => (float)($configData['rate_per_kg'] ?? $defaultConfig['rate_per_kg']),
            'volumetric_divisor' => (int)($configData['volumetric_divisor'] ?? $defaultConfig['volumetric_divisor']),
            'currency' => $configData['currency'] ?? $defaultConfig['currency'],
            'additional_rules' => array_merge($defaultConfig['additional_rules'], $additionalRules)
        ];
    }

    $deliveryFee = calculateDeliveryFee($config, $weight, $deliveryOption, $parcelValue, $insuranceAmount, $length, $width, $height);

    echo json_encode([
        "success" => true,
        "fee" => $deliveryFee['total'],
        "breakdown" => $deliveryFee['breakdown'],
        "currency" => $config['currency'],
        "config_used" => empty($billingConfigs) ? 'default' : 'company'
    ]);

} catch (Exception $e) {
    error_log("Error calculating delivery fee: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Failed to calculate delivery fee: " . $e->getMessage()
    ]);
}

function calculateDeliveryFee($config, $weight, $deliveryOption, $parcelValue, $insuranceAmount, $length = 0, $width = 0, $height = 0) {
    $breakdown = [
        'base_fee' => 0,
        'weight_fee' => 0,
        'volumetric_fee' => 0,
        'insurance_fee' => 0,
        'delivery_option_fee' => 0,
        'minimum_applied' => false
    ];

    $deliveryOptions = $config['additional_rules']['delivery_options'] ?? [];
    if (isset($deliveryOptions[$deliveryOption])) {
        $optionConfig = $deliveryOptions[$deliveryOption];
        $breakdown['base_fee'] = (float)($optionConfig['base_fee'] ?? $config['base_rate']);
        $breakdown['delivery_option_fee'] = $breakdown['base_fee'];
    } else {
        $breakdown['base_fee'] = $config['base_rate'];
        $breakdown['delivery_option_fee'] = $breakdown['base_fee'];
    }

    $breakdown['weight_fee'] = $weight * $config['rate_per_kg'];

    if ($length > 0 && $width > 0 && $height > 0) {
        $volumetricWeight = ($length * $width * $height) / $config['volumetric_divisor'];
        $chargeableWeight = max($weight, $volumetricWeight);
        $breakdown['volumetric_fee'] = ($chargeableWeight - $weight) * $config['rate_per_kg'];
        $breakdown['weight_fee'] = $chargeableWeight * $config['rate_per_kg'];
    }

    if ($insuranceAmount > 0) {
        $insuranceRate = $config['additional_rules']['insurance_rate'] ?? 0.02;
        $breakdown['insurance_fee'] = max($parcelValue, $insuranceAmount) * $insuranceRate;
    }

    $total = $breakdown['base_fee'] + $breakdown['weight_fee'] + $breakdown['volumetric_fee'] + $breakdown['insurance_fee'];

    $minFee = $config['additional_rules']['min_fee'] ?? 0;
    if ($minFee > 0 && $total < $minFee) {
        $breakdown['minimum_applied'] = true;
        $total = $minFee;
    }

    return [
        'total' => round($total, 2),
        'breakdown' => $breakdown
    ];
}
?>
