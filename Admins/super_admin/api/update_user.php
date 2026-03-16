<?php
require_once __DIR__ . '/supabase-client.php';
require_once __DIR__ . '/init.php';

// Verify authentication
ErrorHandler::requireAuth('update_user.php');

// Verify method
ErrorHandler::requireMethod('POST', 'update_user.php');

// CSRF token is already validated via init.php

try {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid request data']);
        exit;
    }

    $userId = $data['id'] ?? '';
    $fullName = trim($data['full_name'] ?? '');
    $email = trim($data['email'] ?? '');
    $phone = trim($data['phone'] ?? '');
    $role = $data['role'] ?? '';
    $companyId = $data['company_id'] ?? null;
    $outletId = $data['outlet_id'] ?? null;
    $status = $data['status'] ?? 'active';

    // Validation
    if (empty($userId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'User ID is required']);
        exit;
    }
    if (empty($fullName) || empty($email)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Full name and email are required']);
        exit;
    }

    // Fetch current profile to compare
    $existing = callSupabaseWithServiceKey("profiles?id=eq.{$userId}&select=email,phone", 'GET');
    if (empty($existing)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }
    $currentProfile = $existing[0];

    // Check for duplicate email (exclude current user)
    if (strtolower($email) !== strtolower($currentProfile['email'] ?? '')) {
        $dupEmail = callSupabaseWithServiceKey("profiles?email=eq." . urlencode($email) . "&id=neq.{$userId}&select=id", 'GET');
        if (!empty($dupEmail)) {
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => 'Email already in use by another user', 'errors' => ['email' => 'This email is already taken']]);
            exit;
        }
    }

    // Check for duplicate phone (exclude current user)
    if (!empty($phone) && $phone !== ($currentProfile['phone'] ?? '')) {
        $dupPhone = callSupabaseWithServiceKey("profiles?phone=eq." . urlencode($phone) . "&id=neq.{$userId}&select=id", 'GET');
        if (!empty($dupPhone)) {
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => 'Phone number already in use by another user', 'errors' => ['phone' => 'This phone is already taken']]);
            exit;
        }
    }

    // Extract address fields
    $address = trim($data['address'] ?? '');
    $city = trim($data['city'] ?? '');
    $state = trim($data['state'] ?? '');
    $postalCode = trim($data['postal_code'] ?? '');
    $country = trim($data['country'] ?? '');

    // Build update data
    $profileData = [
        'full_name' => $fullName,
        'email'     => $email,
        'phone'     => $phone,
        'role'      => $role,
        'address'   => $address,
        'city'      => $city,
        'state'     => $state,
        'postal_code' => $postalCode,
        'country'   => $country,
        'company_id' => $companyId ?: null,
        'outlet_id'  => $outletId ?: null,
        'updated_at' => date('c')
    ];

    // Update profile
    $result = callSupabaseWithServiceKey("profiles?id=eq.{$userId}", 'PATCH', $profileData);
    error_log("Profile update result: " . print_r($result, true));

    // Update Supabase auth email if it changed
    if (strtolower($email) !== strtolower($currentProfile['email'] ?? '')) {
        try {
            global $supabaseUrl, $supabaseServiceKey;
            $authUrl = $supabaseUrl . "/auth/v1/admin/users/{$userId}";
            $ch = curl_init($authUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['email' => $email]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "apikey: {$supabaseServiceKey}",
                "Authorization: Bearer {$supabaseServiceKey}",
                "Content-Type: application/json"
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, (getenv('APP_ENV') ?: 'production') === 'production');
            $authResult = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            error_log("Auth email update - HTTP: {$httpCode}, Response: {$authResult}");
        } catch (Exception $e) {
            error_log("Failed to update auth email: " . $e->getMessage());
            // Non-fatal: profile is already updated
        }
    }

    echo json_encode(['success' => true, 'message' => 'User updated successfully']);

} catch (Exception $e) {
    error_log("update_user.php error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
