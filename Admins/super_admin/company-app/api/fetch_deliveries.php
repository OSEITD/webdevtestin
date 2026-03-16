<?php
require_once __DIR__ . '/supabase-client.php';
require_once __DIR__ . '/input-validation.php';
require_once __DIR__ . '/session-helper.php';
require_once __DIR__ . '/error-handler.php';

class FetchDeliveriesAPI {
    private $supabase;

    public function __construct() {
        $this->supabase = new SupabaseClient();
    }

    public function handleRequest() {
        header('Content-Type: application/json');

        try {
            // Initialize secure session with Secure, HttpOnly, SameSite flags
            SessionHelper::initializeSecureSession();
            
            // Require authentication
            SessionHelper::requireAuth();

            // Build filters but force status=delivered
            // Accept both origin_outlet_id (preferred) and legacy outlet_id
            // Validate all user inputs before building filters
            $filters = [
                'status' => 'delivered',
                'search' => InputValidator::validateSearch($_GET['search'] ?? ''),
                'date' => $this->getDateFilter(InputValidator::validateDateRange($_GET['dateRange'] ?? '')),
                'driver_id' => InputValidator::validateUUID($_GET['driver_id'] ?? '') ?: null,
                // support origin_outlet_id param (client sends this), fallback to outlet_id for legacy callers
                'origin_outlet_id' => InputValidator::validateUUID($_GET['origin_outlet_id'] ?? '') ?: 
                                      InputValidator::validateUUID($_GET['outlet_id'] ?? '') ?: null
            ];

            // Ask supabase for only the fields we need to avoid undefined key warnings
            $response = $this->supabase->getParcels(
                SessionHelper::getCompanyId(),
                SessionHelper::getAccessToken(),
                $filters
            );

            if (!is_array($response)) {
                throw new Exception('Invalid response from database');
            }

            // Map to a minimal, safe shape
            $data = array_map(function($row) {
                return [
                    'id' => $row['id'] ?? null,
                    'track_number' => $row['track_number'] ?? null,
                    'sender' => $row['sender_name'] ?? ($row['sender'] ?? null),
                    'receiver' => $row['receiver_name'] ?? ($row['receiver'] ?? null),
                    'status' => $row['status'] ?? null
                ];
            }, $response);

            echo json_encode(['success' => true, 'data' => $data]);

        } catch (Exception $e) {
            ErrorHandler::handleException($e, 'fetch_deliveries.php');
        }
    }

    private function getDateFilter($dateRange) {
        if (!$dateRange) return null;

        switch ($dateRange) {
            case 'today':
                return date('Y-m-d');
            case 'last7days':
                return date('Y-m-d', strtotime('-7 days'));
            case 'thismonth':
                return date('Y-m-01');
            default:
                return null;
        }
    }
}

$api = new FetchDeliveriesAPI();
$api->handleRequest();
