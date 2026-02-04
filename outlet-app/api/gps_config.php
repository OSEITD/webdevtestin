<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../includes/LocationService.php';

try {
    $action = $_GET['action'] ?? 'config';
    
    switch ($action) {
        case 'config':
            
            $config = LocationService::getGPSConfiguration();
            echo json_encode([
                'success' => true,
                'config' => $config,
                'message' => 'GPS configuration loaded for Zambian operations'
            ]);
            break;
            
        case 'validate_location':
            $latitude = floatval($_GET['latitude'] ?? 0);
            $longitude = floatval($_GET['longitude'] ?? 0);
            
            $isValid = LocationService::validateZambianCoordinates($latitude, $longitude);
            $nearestCity = $isValid ? LocationService::findNearestCity($latitude, $longitude) : null;
            
            echo json_encode([
                'success' => true,
                'valid' => $isValid,
                'coordinates' => LocationService::formatCoordinates($latitude, $longitude),
                'nearest_city' => $nearestCity,
                'country' => $isValid ? 'Zambia' : 'Outside Zambia'
            ]);
            break;
            
        case 'get_cities':
            echo json_encode([
                'success' => true,
                'cities' => LocationService::getZambianCities(),
                'default_city' => LocationService::getDefaultLocation()
            ]);
            break;
            
        case 'calculate_distance':
            $lat1 = floatval($_GET['lat1'] ?? 0);
            $lon1 = floatval($_GET['lon1'] ?? 0);
            $lat2 = floatval($_GET['lat2'] ?? 0);
            $lon2 = floatval($_GET['lon2'] ?? 0);
            $unit = $_GET['unit'] ?? 'km';
            
            $distance = LocationService::calculateDistance($lat1, $lon1, $lat2, $lon2, $unit);
            
            echo json_encode([
                'success' => true,
                'distance' => round($distance, 2),
                'unit' => $unit,
                'formatted' => round($distance, 2) . ' ' . $unit
            ]);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'error' => 'Invalid action. Available actions: config, validate_location, get_cities, calculate_distance'
            ]);
    }
    
} catch (Exception $e) {
    error_log("GPS Configuration API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}
?>