<?php

ob_start();

require_once '../../includes/auth_guard.php';

ob_end_clean();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['company_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$company_id = $_SESSION['company_id'];
$outlet_id = $_SESSION['outlet_id'] ?? null;

$method = $_SERVER['REQUEST_METHOD'];
$outlet_filter = null;
$status_filter = ['pending', 'ready_for_dispatch', 'at_outlet'];

if ($method === 'POST') {
    
    $input = isset($GLOBALS['test_input']) ? $GLOBALS['test_input'] : file_get_contents('php://input');
    $postData = json_decode($input, true);
    
    if (json_last_error() === JSON_ERROR_NONE && is_array($postData)) {
        if (isset($postData['outlet_filter']) && is_array($postData['outlet_filter'])) {
            $outlet_filter = array_filter($postData['outlet_filter']); 
        }
        if (isset($postData['status_filter']) && is_array($postData['status_filter'])) {
            $status_filter = $postData['status_filter'];
        }
    }
}

try {
    
    $supabaseUrl = 'https://xerpchdsykqafrsxbqef.supabase.co';
    $supabaseKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ';
    
    
    $query = "parcels?select=*&company_id=eq.$company_id";
    
    
    if (!empty($status_filter)) {
        $statusList = implode(',', $status_filter);
        $query .= "&status=in.($statusList)";
    }
    
    
    error_log("Fetch parcels - Company: $company_id, Method: $method, Outlet Filter: " . json_encode($outlet_filter));
    
    
    if (!empty($outlet_filter) && is_array($outlet_filter)) {
        
        $validOutlets = array_filter($outlet_filter, function($outlet) {
            return !empty($outlet) && is_string($outlet) && strlen($outlet) > 10;
        });
        
        if (!empty($validOutlets)) {
            $outletList = implode(',', $validOutlets);
            $query .= "&destination_outlet_id=in.($outletList)";
        } else {
            
            echo json_encode([
                'success' => true,
                'data' => [],
                'count' => 0,
                'message' => 'No valid outlets in filter',
                'filter_applied' => [
                    'outlet_filter' => $outlet_filter,
                    'status_filter' => $status_filter
                ]
            ]);
            exit();
        }
    } else if ($outlet_id) {
        
        $query .= "&origin_outlet_id=eq.$outlet_id";
    }
    
    
    $query .= "&order=created_at.desc";
    
    error_log("Final Supabase query: $query");
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "$supabaseUrl/rest/v1/$query");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey",
        "Content-Type: application/json"
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        throw new Exception("CURL Error: $curlError");
    }
    
    if ($httpCode === 200) {
        $parcels = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON response: " . json_last_error_msg());
        }
        
        if (!is_array($parcels)) {
            throw new Exception("Expected array response, got: " . gettype($parcels));
        }
        
        
        $parcelIds = array_column($parcels, 'id');
        if (!empty($parcelIds)) {
            
            $assignedParcelsQuery = "parcel_list?select=parcel_id,status&parcel_id=in.(" . implode(',', $parcelIds) . ")&company_id=eq.$company_id&status=not.eq.cancelled";
            
            error_log("Checking for assigned parcels: $assignedParcelsQuery");
            
            $ch2 = curl_init();
            curl_setopt($ch2, CURLOPT_URL, "$supabaseUrl/rest/v1/$assignedParcelsQuery");
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch2, CURLOPT_HTTPHEADER, [
                "apikey: $supabaseKey",
                "Authorization: Bearer $supabaseKey",
                "Content-Type: application/json"
            ]);
            
            $assignedResponse = curl_exec($ch2);
            $assignedHttpCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            curl_close($ch2);
            
            if ($assignedHttpCode === 200) {
                $assignedParcels = json_decode($assignedResponse, true);
                if (is_array($assignedParcels)) {
                    $assignedParcelIds = array_column($assignedParcels, 'parcel_id');
                    
                    error_log("Found " . count($assignedParcelIds) . " parcels already assigned to trips: " . json_encode($assignedParcelIds));
                    
                    
                    $parcels = array_filter($parcels, function($parcel) use ($assignedParcelIds) {
                        return !in_array($parcel['id'], $assignedParcelIds);
                    });
                    
                    
                    $parcels = array_values($parcels);
                    
                    error_log("After filtering: " . count($parcels) . " parcels available");
                } else {
                    error_log("Failed to decode assigned parcels response");
                }
            } else {
                error_log("Failed to fetch assigned parcels. HTTP Code: $assignedHttpCode, Response: $assignedResponse");
            }
        }
        
        
        $outletCache = [];
        if (!empty($parcels)) {
            
            $outletIds = [];
            foreach ($parcels as $parcel) {
                if (!empty($parcel['origin_outlet_id'])) {
                    $outletIds[] = $parcel['origin_outlet_id'];
                }
                if (!empty($parcel['destination_outlet_id'])) {
                    $outletIds[] = $parcel['destination_outlet_id'];
                }
            }
            
            $outletIds = array_unique($outletIds);
            
            if (!empty($outletIds)) {
                $outletQuery = "outlets?company_id=eq.$company_id&id=in.(" . implode(',', $outletIds) . ")&select=id,outlet_name";
                
                $ch3 = curl_init();
                curl_setopt($ch3, CURLOPT_URL, "$supabaseUrl/rest/v1/$outletQuery");
                curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch3, CURLOPT_HTTPHEADER, [
                    "apikey: $supabaseKey",
                    "Authorization: Bearer $supabaseKey",
                    "Content-Type: application/json"
                ]);
                
                $outletResponse = curl_exec($ch3);
                $outletHttpCode = curl_getinfo($ch3, CURLINFO_HTTP_CODE);
                curl_close($ch3);
                
                if ($outletHttpCode === 200) {
                    $outlets = json_decode($outletResponse, true);
                    if (is_array($outlets)) {
                        foreach ($outlets as $outlet) {
                            $outletCache[$outlet['id']] = $outlet['outlet_name'];
                        }
                    }
                }
            }
        }
        
        
        foreach ($parcels as &$parcel) {
            
            $parcel['origin_outlet_name'] = 'Unknown Origin';
            if (!empty($parcel['origin_outlet_id']) && isset($outletCache[$parcel['origin_outlet_id']])) {
                $parcel['origin_outlet_name'] = $outletCache[$parcel['origin_outlet_id']];
            }
            
            $parcel['destination_outlet_name'] = 'Unknown Destination';  
            if (!empty($parcel['destination_outlet_id']) && isset($outletCache[$parcel['destination_outlet_id']])) {
                $parcel['destination_outlet_name'] = $outletCache[$parcel['destination_outlet_id']];
            }
            
            
            if (!empty($parcel['parcel_length']) && !empty($parcel['parcel_width']) && !empty($parcel['parcel_height'])) {
                $parcel['volume'] = $parcel['parcel_length'] * $parcel['parcel_width'] * $parcel['parcel_height'];
            } else {
                $parcel['volume'] = 0;
            }
            
            
            $parcel['weight_display'] = !empty($parcel['parcel_weight']) ? number_format($parcel['parcel_weight'], 2) . ' kg' : 'N/A';
            
            
            $parcel['fee_display'] = !empty($parcel['delivery_fee']) ? 'ZMW ' . number_format($parcel['delivery_fee'], 2) : 'Free';
            
            
            if (empty($parcel['track_number'])) {
                $parcel['track_number'] = 'PKG-' . $parcel['id'];
            }
            
            
            $parcel['value_display'] = !empty($parcel['declared_value']) ? 'ZMW ' . number_format($parcel['declared_value'], 2) : 'ZMW 0.00';
        }
        
        
        if ($method === 'POST') {
            echo json_encode([
                'success' => true,
                'data' => $parcels,
                'count' => count($parcels),
                'filter_applied' => [
                    'outlet_filter' => $outlet_filter,
                    'status_filter' => $status_filter
                ]
            ]);
        } else {
            
            echo json_encode($parcels);
        }
    } else {
        error_log("Supabase API Error: HTTP $httpCode - $response");
        http_response_code($httpCode);
        echo json_encode(['error' => 'Failed to fetch parcels', 'http_code' => $httpCode, 'details' => $response]);
    }
    
} catch (Exception $e) {
    error_log("fetch_available_parcels.php error: " . $e->getMessage());
    error_log("Company ID: $company_id, Outlet ID: " . ($outlet_id ?? 'none'));
    error_log("Outlet Filter: " . ($outlet_filter ? json_encode($outlet_filter) : 'none'));
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error', 
        'message' => $e->getMessage(), 
        'debug' => [
            'company_id' => $company_id,
            'outlet_id' => $outlet_id,
            'user_id' => $_SESSION['user_id'] ?? 'not set',
            'method' => $method,
            'outlet_filter' => $outlet_filter
        ]
    ]);
}
?>
