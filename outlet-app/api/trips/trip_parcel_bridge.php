<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET");
header("Access-Control-Allow-Headers: Content-Type");

session_start();
require_once '../../includes/trip_parcel_bridge.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$bridge = new TripParcelBridge();

try {
    switch ($method) {
        case 'GET':
            
            echo json_encode([
                'success' => true,
                'context' => $bridge->getContext(),
                'active_workflow' => $bridge->hasActiveWorkflow(),
                'suggestions' => $bridge->getWorkflowSuggestions(),
                'navigation_urls' => $bridge->getNavigationUrls()
            ]);
            break;
            
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            $action = $input['action'] ?? '';
            
            switch ($action) {
                case 'set_active_trip':
                    $bridge->setActiveTrip(
                        $input['trip_id'] ?? null,
                        $input['trip_details'] ?? []
                    );
                    echo json_encode([
                        'success' => true,
                        'message' => 'Active trip set successfully',
                        'redirect_suggestion' => 'parcel_registration.php'
                    ]);
                    break;
                    
                case 'add_pending_parcel':
                    $bridge->addPendingParcel(
                        $input['parcel_id'] ?? null,
                        $input['parcel_details'] ?? []
                    );
                    echo json_encode([
                        'success' => true,
                        'message' => 'Parcel added to pending list',
                        'redirect_suggestion' => 'trip_wizard.php'
                    ]);
                    break;
                    
                case 'clear_pending_parcels':
                    $bridge->clearPendingParcels();
                    echo json_encode([
                        'success' => true,
                        'message' => 'Pending parcels cleared'
                    ]);
                    break;
                    
                case 'reset_bridge':
                    $bridge->reset();
                    echo json_encode([
                        'success' => true,
                        'message' => 'Bridge state reset'
                    ]);
                    break;
                    
                case 'set_workflow_mode':
                    $bridge->setWorkflowMode($input['mode'] ?? null);
                    echo json_encode([
                        'success' => true,
                        'message' => 'Workflow mode updated'
                    ]);
                    break;
                    
                default:
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid action']);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    error_log("Bridge API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
