<?php
header('Content-Type: application/json');
require_once '../includes/auth_guard.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    if (empty($input['full_name']) || empty($input['nrc'])) {
        throw new Exception('Full name and NRC are required');
    }
    
    $nrc = trim($input['nrc']);
    $full_name = trim($input['full_name']);
    $phone = !empty($input['phone']) ? trim($input['phone']) : null;
    $email = !empty($input['email']) ? trim($input['email']) : null;
    
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }
    
    require_once '../includes/MultiTenantSupabaseHelper.php';
    $companyId = $_SESSION['company_id'] ?? null;
    $supabase = new MultiTenantSupabaseHelper($companyId);
    
    $existing = null;
    if ($email) {
        $existingByNRC = $supabase->get('global_customers', "nrc=eq.$nrc", 'id,full_name,nrc,phone,email');
        $existingByEmail = $supabase->get('global_customers', "email=eq.$email", 'id,full_name,nrc,phone,email');
        
        $existing = !empty($existingByNRC) ? $existingByNRC[0] : (!empty($existingByEmail) ? $existingByEmail[0] : null);
    } else {
        $existingByNRC = $supabase->get('global_customers', "nrc=eq.$nrc", 'id,full_name,nrc,phone,email');
        $existing = !empty($existingByNRC) ? $existingByNRC[0] : null;
    }
    
    if ($existing) {
        $needsUpdate = false;
        $updateData = [];
        
        if ($phone && $phone !== $existing['phone']) {
            $updateData['phone'] = $phone;
            $needsUpdate = true;
        }
        
        if ($email && $email !== $existing['email']) {
            $updateData['email'] = $email;
            $needsUpdate = true;
        }
        
        if ($full_name !== $existing['full_name']) {
            $updateData['full_name'] = $full_name;
            $needsUpdate = true;
        }
        
        if ($needsUpdate) {
            $updateResult = $supabase->put("global_customers?id=eq.{$existing['id']}", $updateData);
            
            echo json_encode([
                'success' => true,
                'message' => 'Customer updated successfully',
                'customer_id' => $existing['id'],
                'action' => 'updated'
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'message' => 'Customer already exists',
                'customer_id' => $existing['id'],
                'action' => 'existing'
            ]);
        }
    } else {
        $customerData = [
            'full_name' => $full_name,
            'nrc' => $nrc,
            'phone' => $phone,
            'email' => $email
        ];
        
        $result = $supabase->postGlobal('global_customers', $customerData);
        
        if ($result && is_array($result) && !empty($result)) {
            $newCustomerId = $result[0]['id'] ?? null;
            
            echo json_encode([
                'success' => true,
                'message' => 'Customer saved successfully',
                'customer_id' => $newCustomerId,
                'action' => 'created'
            ]);
        } else {
            throw new Exception('Failed to save customer - no response from database');
        }
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
