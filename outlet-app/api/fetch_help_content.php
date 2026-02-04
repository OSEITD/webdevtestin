<?php
session_start();
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PATCH, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized: Please log in"]);
    exit;
}

$company_id = $_SESSION['company_id'] ?? null;
$user_id = $_SESSION['user_id'] ?? null;

if (!$company_id) {
    error_log("No company_id in session for user: " . $user_id);
}

error_log("Session data - User ID: " . $user_id . ", Company ID: " . ($company_id ?? 'null'));

$supabaseUrl = "https://xerpchdsykqafrsxbqef.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ";

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $section_type = isset($_GET['section_type']) ? $_GET['section_type'] : null;
    
    try {
        $help_content = [];
        
        if ($company_id) {
            $query_params = [];
            $query_params[] = "company_id=eq.$company_id";
            
            if ($section_type && in_array($section_type, ['faq', 'contact', 'guide'])) {
                $query_params[] = "section_type=eq.$section_type";
            }
            
            $query_params[] = "order=created_at.desc";
            $query_params[] = "select=*";
            
            $query_string = implode('&', $query_params);
            
            $ch = curl_init("$supabaseUrl/rest/v1/help?$query_string");
            
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    "apikey: $supabaseKey",
                    "Authorization: Bearer $supabaseKey",
                    "Content-Type: application/json"
                ],
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code >= 200 && $http_code < 300) {
                $help_content = json_decode($response, true);
                error_log("Company-specific content found: " . count($help_content) . " items");
            } else {
                error_log("Failed to fetch company-specific content: HTTP $http_code");
            }
        }
        
        if (empty($help_content)) {
            $default_query_params = [];
            $default_query_params[] = "company_id=is.null";
            
            if ($section_type && in_array($section_type, ['faq', 'contact', 'guide'])) {
                $default_query_params[] = "section_type=eq.$section_type";
            }
            
            $default_query_params[] = "order=created_at.desc";
            $default_query_params[] = "select=*";
            
            $default_query_string = implode('&', $default_query_params);
            
            $ch = curl_init("$supabaseUrl/rest/v1/help?$default_query_string");
            
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    "apikey: $supabaseKey",
                    "Authorization: Bearer $supabaseKey",
                    "Content-Type: application/json"
                ],
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code >= 200 && $http_code < 300) {
                $help_content = json_decode($response, true);
                error_log("Default content found: " . count($help_content) . " items");
            } else {
                error_log("Failed to fetch default content: HTTP $http_code");
                $help_content = [];
            }
        }
        
        foreach ($help_content as &$item) {
            if (is_string($item['content'])) {
                $decoded = json_decode($item['content'], true);
                if ($decoded !== null) {
                    $item['content'] = $decoded;
                }
            }
        }
        
        echo json_encode([
            'success' => true,
            'data' => $help_content,
            'count' => count($help_content),
            'company_id' => $company_id,
            'user_id' => $user_id,
            'filters' => [
                'section_type' => $section_type,
                'source' => $company_id ? 'company-specific' : 'default'
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Error fetching help content: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'error' => 'Failed to fetch help content', 
            'details' => $e->getMessage()
        ]);
    }
    
} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['title'], $input['section_type'], $input['content'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit;
    }
    
    $title = $input['title'];
    $section_type = $input['section_type'];
    $content = $input['content'];
    
    if (!in_array($section_type, ['faq', 'contact', 'guide'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid section type']);
        exit;
    }
    
    try {
        $data = [
            'title' => $title,
            'section_type' => $section_type,
            'content' => is_array($content) ? json_encode($content) : $content,
            'company_id' => $company_id
        ];
        
        $ch = curl_init("$supabaseUrl/rest/v1/help");
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                "apikey: $supabaseKey",
                "Authorization: Bearer $supabaseKey",
                "Content-Type: application/json",
                "Prefer: return=representation"
            ],
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code >= 200 && $http_code < 300) {
            $result = json_decode($response, true);
            if (!empty($result)) {
                
                if (is_string($result[0]['content'])) {
                    $decoded = json_decode($result[0]['content'], true);
                    if ($decoded !== null) {
                        $result[0]['content'] = $decoded;
                    }
                }
                
                echo json_encode([
                    'success' => true,
                    'data' => $result[0]
                ]);
            } else {
                throw new Exception("No data returned from database");
            }
        } else {
            throw new Exception("Database error: HTTP $http_code");
        }
        
    } catch (Exception $e) {
        error_log("Error creating help content: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to create help content', 'details' => $e->getMessage()]);
    }
    
} elseif ($method === 'PATCH') {
    
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $_GET['id'] ?? null;
    
    if (!$id || !isset($input['title'], $input['section_type'], $input['content'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit;
    }
    
    $title = $input['title'];
    $section_type = $input['section_type'];
    $content = $input['content'];
    
    if (!in_array($section_type, ['faq', 'contact', 'guide'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid section type']);
        exit;
    }
    
    try {
        $data = [
            'title' => $title,
            'section_type' => $section_type,
            'content' => is_array($content) ? json_encode($content) : $content,
            'updated_at' => date('c')
        ];
        
        
        $query_params = [];
        $query_params[] = "id=eq.$id";
        if ($company_id) {
            $query_params[] = "company_id=eq.$company_id";
        }
        $query_string = implode('&', $query_params);
        
        $ch = curl_init("$supabaseUrl/rest/v1/help?$query_string");
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => "PATCH",
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                "apikey: $supabaseKey",
                "Authorization: Bearer $supabaseKey",
                "Content-Type: application/json",
                "Prefer: return=representation"
            ],
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code >= 200 && $http_code < 300) {
            $result = json_decode($response, true);
            if (!empty($result)) {
                
                if (is_string($result[0]['content'])) {
                    $decoded = json_decode($result[0]['content'], true);
                    if ($decoded !== null) {
                        $result[0]['content'] = $decoded;
                    }
                }
                
                echo json_encode([
                    'success' => true,
                    'data' => $result[0]
                ]);
            } else {
                throw new Exception("No data returned from database");
            }
        } else {
            throw new Exception("Database error: HTTP $http_code");
        }
        
    } catch (Exception $e) {
        error_log("Error updating help content: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to update help content', 'details' => $e->getMessage()]);
    }
    
} elseif ($method === 'DELETE') {
    
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing ID parameter']);
        exit;
    }
    
    try {
        
        $query_params = [];
        $query_params[] = "id=eq.$id";
        if ($company_id) {
            $query_params[] = "company_id=eq.$company_id";
        }
        $query_string = implode('&', $query_params);
        
        $ch = curl_init("$supabaseUrl/rest/v1/help?$query_string");
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => "DELETE",
            CURLOPT_HTTPHEADER => [
                "apikey: $supabaseKey",
                "Authorization: Bearer $supabaseKey",
                "Content-Type: application/json"
            ],
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code >= 200 && $http_code < 300) {
            echo json_encode([
                'success' => true,
                'message' => 'Help content deleted successfully'
            ]);
        } else {
            throw new Exception("Database error: HTTP $http_code");
        }
        
    } catch (Exception $e) {
        error_log("Error deleting help content: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to delete help content', 'details' => $e->getMessage()]);
    }
    
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}
?>
