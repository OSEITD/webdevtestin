<?php
// Centralized API initialization
require_once __DIR__ . '/init.php';

// Verify authentication
ErrorHandler::requireAuth('update_outlet.php');
ErrorHandler::requireMethod('POST', 'update_outlet.php');

require_once __DIR__ . '/supabase-client.php';

try {
    // Check if we're receiving JSON
    $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        $_POST = json_decode(file_get_contents('php://input'), true) ?? [];
    }

    // Step 1: Collect and validate form data
    $outletData = [
        'id' => trim($_POST['id'] ?? ''),
        'outlet_name' => trim($_POST['outlet_name'] ?? ''),
        'company_id' => trim($_POST['company_id'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'city' => trim($_POST['city'] ?? ''),
        'state' => trim($_POST['state'] ?? ''),
        'postal_code' => trim($_POST['postal_code'] ?? ''),
        'country' => trim($_POST['country'] ?? ''),
        'contact_person' => trim($_POST['contact_person'] ?? ''),
        'contact_phone' => trim($_POST['contact_phone'] ?? ''),
        'contact_email' => trim($_POST['contact_email'] ?? ''),
        'status' => trim($_POST['status'] ?? 'active'),
    ];

    // Verify outlet ID is provided
    if (empty($outletData['id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Outlet ID is required',
            'errors' => ['id' => 'Outlet ID is required']
        ]);
        exit;
    }

    // Step 2: Validate all fields with detailed error messages
    $validationErrors = [];

    // Outlet name: required, 2-100 chars
    if (empty($outletData['outlet_name'])) {
        $validationErrors['outlet_name'] = 'Outlet Name is required';
    } elseif (strlen($outletData['outlet_name']) < 2 || strlen($outletData['outlet_name']) > 100) {
        $validationErrors['outlet_name'] = 'Outlet Name must be 2-100 characters';
    }

    // Company ID: required, valid UUID format
    if (empty($outletData['company_id'])) {
        $validationErrors['company_id'] = 'Associated Company is required';
    } elseif (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $outletData['company_id'])) {
        $validationErrors['company_id'] = 'Invalid company ID format';
    }

    // Contact person: required, 2-100 chars
    if (empty($outletData['contact_person'])) {
        $validationErrors['contact_person'] = 'Contact Person is required';
    } elseif (strlen($outletData['contact_person']) < 2 || strlen($outletData['contact_person']) > 100) {
        $validationErrors['contact_person'] = 'Contact Person must be 2-100 characters';
    }

    // Email: required, valid format
    if (empty($outletData['contact_email'])) {
        $validationErrors['contact_email'] = 'Contact Email is required';
    } elseif (!filter_var($outletData['contact_email'], FILTER_VALIDATE_EMAIL)) {
        $validationErrors['contact_email'] = 'Please enter a valid email address';
    }

    // Phone: optional but must be valid if provided, international format +X followed by 7-15 digits
    if (!empty($outletData['contact_phone'])) {
        $cleanPhone = preg_replace('/[\s\-\(\)]/', '', $outletData['contact_phone']);
        if (!preg_match('/^\+\d{7,15}$/', $cleanPhone)) {
            $validationErrors['contact_phone'] = 'Please enter a valid phone number with country code (e.g., +260 XXX XXX XXX)';
        } else {
            // Store the cleaned phone number
            $outletData['contact_phone'] = $cleanPhone;
        }
    }

    // Address: required, 5-500 chars
    if (empty($outletData['address'])) {
        $validationErrors['address'] = 'Address is required';
    } elseif (strlen($outletData['address']) < 5 || strlen($outletData['address']) > 500) {
        $validationErrors['address'] = 'Address must be 5-500 characters';
    }

    // Status: must be one of active, inactive
    if (!in_array($outletData['status'], ['active', 'inactive'])) {
        $validationErrors['status'] = 'Invalid status value';
    }

    // If any validation errors, return them all at once
    if (!empty($validationErrors)) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'error' => 'Validation failed',
            'errors' => $validationErrors
        ]);
        exit;
    }

    // Step 3: Verify company exists (user has access check)
    $company = callSupabaseWithServiceKey(
        'companies?id=eq.' . urlencode($outletData['company_id']) . '&select=id,status',
        'GET'
    );
    if (empty($company)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Company not found',
            'errors' => ['company_id' => 'The selected company does not exist.']
        ]);
        exit;
    }

    // Step 4: Check for duplicate outlet name within same company (excluding current outlet)
    $existingOutlet = callSupabaseWithServiceKey(
        'outlets?outlet_name=eq.' . urlencode($outletData['outlet_name']) . '&company_id=eq.' . urlencode($outletData['company_id']) . '&id=neq.' . urlencode($outletData['id']) . '&select=id',
        'GET'
    );
    if (!empty($existingOutlet)) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error' => 'Another outlet with this name already exists in this company',
            'errors' => ['outlet_name' => 'An outlet with this name already exists in this company.']
        ]);
        exit;
    }

    // Step 5: Check for duplicate contact phone within same company (excluding current outlet)
    if (!empty($outletData['contact_phone'])) {
        $existingPhone = callSupabaseWithServiceKey(
            'outlets?contact_phone=eq.' . urlencode($outletData['contact_phone']) . '&company_id=eq.' . urlencode($outletData['company_id']) . '&id=neq.' . urlencode($outletData['id']) . '&select=id',
            'GET'
        );
        if (!empty($existingPhone)) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'error' => 'Another outlet with this phone number already exists',
                'errors' => ['contact_phone' => 'This phone number is already registered to another outlet in this company.']
            ]);
            exit;
        }
    }

    // Step 6: Prepare update data (exclude id field from update payload)
    $updateData = [
        'outlet_name' => $outletData['outlet_name'],
        'company_id' => $outletData['company_id'],
        'address' => $outletData['address'],
        'city' => $outletData['city'],
        'state' => $outletData['state'],
        'postal_code' => $outletData['postal_code'],
        'country' => $outletData['country'],
        'contact_person' => $outletData['contact_person'],
        'contact_phone' => $outletData['contact_phone'],
        'contact_email' => $outletData['contact_email'],
        'status' => $outletData['status'],
        'updated_at' => date('Y-m-d H:i:s')
    ];

    // Step 7: Update outlet in Supabase
    $result = callSupabaseWithServiceKey("outlets?id=eq.{$outletData['id']}", 'PATCH', $updateData);

    // Success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Outlet updated successfully',
        'outlet_id' => $outletData['id']
    ]);

} catch (Exception $e) {
    error_log('Error in update_outlet.php: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
