<?php

error_reporting(0);
ini_set('display_errors', 0);

ob_start();

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

ob_clean();
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

$outlet_id = $_GET['outlet_id'] ?? null;

if (!$outlet_id) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Missing required parameter: outlet_id"]);
    exit;
}

$SUPABASE_URL = "https://xerpchdsykqafrsxbqef.supabase.co";
$SUPABASE_API_KEY = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ";

if (is_numeric($outlet_id)) {
    
    $query = "parcels?select=track_number,status,updated_at,receiver_name,sender_name&order=updated_at.desc&limit=25";
} else {
    
    

$query = "parcels?or=(origin_outlet_id.eq.$outlet_id,destination_outlet_id.eq.$outlet_id)&select=track_number,status,updated_at,created_at,receiver_name,sender_name,parcel_weight,delivery_option&order=updated_at.desc.nullslast,created_at.desc&limit=25";
}

$ch = curl_init("$SUPABASE_URL/rest/v1/$query");

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "apikey: $SUPABASE_API_KEY",
        "Authorization: Bearer $SUPABASE_API_KEY",
        "Content-Type: application/json"
    ],
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);

try {
    if ($http_code >= 200 && $http_code < 300) {
        $parcels = json_decode($response, true);
        $history = [];

        if (is_array($parcels) && !empty($parcels)) {
            foreach ($parcels as $parcel) {
                
                
                $updatedAt = $parcel["updated_at"];
                $createdAt = $parcel["created_at"];
                $isStatusChange = !empty($updatedAt) && $updatedAt !== $createdAt;
                
                
                $activityTimestamp = $updatedAt ?: $createdAt;
                
                
                $action = 'scan';
                $status_change = '';
                $actionDescription = '';
                
                switch ($parcel["status"]) {
                    case 'pending':
                        if ($isStatusChange) {
                            $action = 'status-update';
                            $status_change = 'Status changed to Pending';
                            $actionDescription = 'Parcel status updated to pending';
                        } else {
                            $action = 'registered';
                            $status_change = 'Parcel registered as pending';
                            $actionDescription = 'New parcel registered';
                        }
                        break;
                    case 'in_transit':
                        $action = 'check-out';
                        $status_change = 'Checked out for transit';
                        $actionDescription = 'Parcel checked out and in transit';
                        break;
                    case 'delivered':
                        $action = 'mark-delivered';
                        $status_change = 'Marked as delivered';
                        $actionDescription = 'Parcel successfully delivered';
                        break;
                    case 'ready_for_dispatch':
                        $action = 'check-in';
                        $status_change = 'Checked in, ready for dispatch';
                        $actionDescription = 'Parcel checked in at outlet';
                        break;
                    case 'assigned':
                        $action = 'assignment';
                        $status_change = 'Assigned to driver/trip';
                        $actionDescription = 'Parcel assigned for delivery';
                        break;
                    case 'at_outlet':
                        $action = 'arrival';
                        $status_change = 'Arrived at outlet';
                        $actionDescription = 'Parcel arrived at outlet';
                        break;
                    default:
                        $action = $isStatusChange ? 'status-update' : 'registered';
                        $statusFormatted = ucfirst(str_replace('_', ' ', $parcel["status"] ?? 'unknown'));
                        if ($isStatusChange) {
                            $status_change = "Status changed to {$statusFormatted}";
                            $actionDescription = "Parcel status updated to {$statusFormatted}";
                        } else {
                            $status_change = "Registered as {$statusFormatted}";
                            $actionDescription = "New parcel registered with {$statusFormatted} status";
                        }
                }

                
                $isRecent = false;
                if ($activityTimestamp) {
                    $timeAgo = time() - strtotime($activityTimestamp);
                    $isRecent = $timeAgo < 7200; 
                }

                $history[] = [
                    "track_number" => $parcel["track_number"] ?? 'Unknown',
                    "action" => $action,
                    "status_change" => $status_change,
                    "action_description" => $actionDescription,
                    "timestamp" => $activityTimestamp,
                    "is_recent" => $isRecent,
                    "staff_name" => "Outlet Staff", 
                    "customer_info" => $parcel["receiver_name"] ?? $parcel["sender_name"] ?? "Unknown",
                    "weight" => $parcel["parcel_weight"] ?? null,
                    "delivery_option" => $parcel["delivery_option"] ?? null,
                    "is_status_change" => $isStatusChange,
                    "created_at" => $createdAt,
                    "updated_at" => $updatedAt
                ];
            }
            echo json_encode($history);
        } else {
            
            echo json_encode([]);
        }
    } else {
        echo json_encode([
            "success" => false,
            "error" => "Failed to fetch scan history",
            "details" => "HTTP $http_code"
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "error" => "Server error: " . $e->getMessage()
    ]);
}

curl_close($ch);

exit;
?>
