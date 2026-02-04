<?php
require_once __DIR__ . '/supabase-client.php';

class DeliveriesAPI {
    private $supabase;
    
    public function __construct() {
        $this->supabase = new SupabaseClient();
    }
    
    public function handleRequest() {
        header('Content-Type: application/json');
        
        try {
            // Verify authentication
            session_start();
            if (!isset($_SESSION['id']) || !isset($_SESSION['access_token'])) {
                throw new Exception('Not authenticated', 401);
            }
            
            // Get query parameters
            $filters = $this->getFilters();
            
            // Get parcels from Supabase (use parcels table)
            $response = $this->supabase->getParcels(
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
            http_response_code($e->getCode() ?: 500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
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
            // Use defensive access to avoid PHP warnings when fields are missing
            return [
                'id' => $delivery['id'] ?? null,
                'tracking_number' => $delivery['tracking_number'] ?? null,
                'sender_name' => $delivery['sender_name'] ?? '',
                'receiver_name' => $delivery['receiver_name'] ?? '',
                'origin_outlet' => $delivery['origin_outlet'] ?? null,
                'destination_outlet' => $delivery['destination_outlet'] ?? null,
                'status' => $delivery['status'] ?? 'pending',
                'weight' => $delivery['weight'] ?? null,
                'description' => $delivery['description'] ?? '',
                'created_at' => $delivery['created_at'] ?? null,
                'estimated_delivery_date' => $delivery['estimated_delivery_date'] ?? null,
                'driver_name' => $delivery['driver_name'] ?? 'Not Assigned'
            ];
        }, $deliveries);
    }
}

// Execute the API
$api = new DeliveriesAPI();
$api->handleRequest();