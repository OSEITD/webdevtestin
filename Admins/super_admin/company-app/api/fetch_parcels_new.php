<?php
require_once __DIR__ . '/supabase-client.php';

class DeliveriesAPI {
    private $supabase;
    
    public function __construct() {
        $this->supabase = new SupabaseClient();
    }
    
    public function handleRequest() {
        // Enable error reporting for debugging
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        
        // Start output buffering to prevent header issues
        ob_start();
        
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET');
        
        try {
            // Start session if not already started
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            error_log("Session data: " . print_r($_SESSION, true));
            
            // Verify authentication
            if (!isset($_SESSION['id']) || !isset($_SESSION['access_token'])) {
                throw new Exception('Not authenticated - ID: ' . (isset($_SESSION['id']) ? 'yes' : 'no') . 
                    ', Token: ' . (isset($_SESSION['access_token']) ? 'yes' : 'no'), 401);
            }
            
            // Get query parameters
            $filters = $this->getFilters();
            
            // Get deliveries from Supabase
            $response = $this->supabase->getDeliveries(
                $_SESSION['id'],
                $_SESSION['access_token'],
                $filters
            );
            
            if (!is_array($response)) {
                throw new Exception('Invalid response from database');
            }
            
            // Format response
            $parcels = $this->formatDeliveries($response);
            
            echo json_encode([
                'success' => true,
                'data' => $parcels
            ]);
            
        } catch (Exception $e) {
            // Log the error
            error_log("Error in fetch_parcels_new.php: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            
            // Clear any previous output
            if (ob_get_length()) ob_clean();
            
            $statusCode = $e->getCode() ?: 500;
            http_response_code($statusCode);
            
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'debug_info' => [
                    'code' => $statusCode,
                    'file' => basename($e->getFile()),
                    'line' => $e->getLine()
                ]
            ]);
        } finally {
            // End output buffering
            if (ob_get_length()) ob_end_flush();
        }
    }
    
    private function getFilters() {
        return [
            'status' => $_GET['status'] ?? null,
            'search' => $_GET['search'] ?? null,
            'date' => $this->getDateFilter($_GET['dateRange'] ?? null),
            'driver_id' => $_GET['driver_id'] ?? null,
            'outlet_id' => $_GET['outlet_id'] ?? null
        ];
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
    
    private function formatDeliveries($deliveries) {
        return array_map(function($delivery) {
            return [
                'id' => $delivery['id'],
                'tracking_number' => $delivery['tracking_number'],
                'sender_name' => $delivery['sender_name'],
                'receiver_name' => $delivery['receiver_name'],
                'origin_address' => $delivery['origin_address'],
                'destination_address' => $delivery['destination_address'],
                'status' => $delivery['status'],
                'weight' => $delivery['weight'] ?? null,
                'description' => $delivery['description'] ?? '',
                'created_at' => $delivery['created_at'],
                'estimated_delivery_date' => $delivery['estimated_delivery_date'] ?? null,
                'driver_name' => $delivery['driver_name'] ?? 'Not Assigned'
            ];
        }, $deliveries);
    }
}

// Execute the API
$api = new DeliveriesAPI();
$api->handleRequest();