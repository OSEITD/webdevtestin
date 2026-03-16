<?php
// Centralized API initialization
require_once __DIR__ . '/init.php';

// Verify authentication
ErrorHandler::requireAuth('update_company.php');
ErrorHandler::requireMethod('POST', 'update_company.php');

require_once 'supabase-client.php';

try {
    // Check if we're receiving JSON
    $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        $_POST = json_decode(file_get_contents('php://input'), true) ?? [];
    }

    // Step 1: Collect and validate form data
    $companyData = [
        'id' => trim($_POST['id'] ?? ''),
        'company_name' => trim($_POST['company_name'] ?? ''),
        'subdomain' => trim($_POST['subdomain'] ?? ''),
        'contact_person' => trim($_POST['contact_person'] ?? ''),
        'contact_email' => trim($_POST['contact_email'] ?? ''),
        'contact_phone' => trim($_POST['contact_phone'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'city' => trim($_POST['city'] ?? ''),
        'state' => trim($_POST['state'] ?? ''),
        'postal_code' => trim($_POST['postal_code'] ?? ''),
        'country' => trim($_POST['country'] ?? ''),
        'status' => trim($_POST['status'] ?? 'active'),
    ];
    $commissionRate = floatval($_POST['commission_rate'] ?? 0);

    // Verify company ID is provided
    if (empty($companyData['id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Company ID is required',
            'errors' => ['id' => 'Company ID is required']
        ]);
        exit;
    }

    // Step 2: Validate all fields with detailed error messages
    $validationErrors = [];

    // Company name: required, 2-100 chars
    if (empty($companyData['company_name'])) {
        $validationErrors['company_name'] = 'Company Name is required';
    } elseif (strlen($companyData['company_name']) < 2 || strlen($companyData['company_name']) > 100) {
        $validationErrors['company_name'] = 'Company Name must be 2-100 characters';
    }

    // Subdomain: required, 3-50 chars, lowercase alphanumeric
    if (empty($companyData['subdomain'])) {
        $validationErrors['subdomain'] = 'Subdomain is required';
    } elseif (strlen($companyData['subdomain']) < 3 || strlen($companyData['subdomain']) > 50) {
        $validationErrors['subdomain'] = 'Subdomain must be 3-50 characters';
    } elseif (!preg_match('/^[a-z0-9]+$/', $companyData['subdomain'])) {
        $validationErrors['subdomain'] = 'Subdomain must contain only lowercase letters and numbers';
    }

    // Contact person: required, 2-100 chars
    if (empty($companyData['contact_person'])) {
        $validationErrors['contact_person'] = 'Contact Person is required';
    } elseif (strlen($companyData['contact_person']) < 2 || strlen($companyData['contact_person']) > 100) {
        $validationErrors['contact_person'] = 'Contact Person must be 2-100 characters';
    }

    // Email: required, valid format
    if (empty($companyData['contact_email'])) {
        $validationErrors['contact_email'] = 'Contact Email is required';
    } elseif (!filter_var($companyData['contact_email'], FILTER_VALIDATE_EMAIL)) {
        $validationErrors['contact_email'] = 'Please enter a valid email address';
    }

    // Phone: required, international format +X followed by 7-15 digits
    $cleanPhone = preg_replace('/[\s\-\(\)]/', '', $companyData['contact_phone']);
    if (empty($companyData['contact_phone'])) {
        $validationErrors['contact_phone'] = 'Contact Phone is required';
    } elseif (!preg_match('/^\+\d{7,15}$/', $cleanPhone)) {
        $validationErrors['contact_phone'] = 'Please enter a valid phone number with country code (e.g., +260 XXX XXX XXX)';
    } else {
        // Store the cleaned phone number
        $companyData['contact_phone'] = $cleanPhone;
    }

    // Address: required, 5-500 chars. We accept either full address or assembled split fields.
    if (empty($companyData['address'])) {
        $validationErrors['address'] = 'Address is required';
    } elseif (strlen($companyData['address']) < 5 || strlen($companyData['address']) > 500) {
        $validationErrors['address'] = 'Address must be 5-500 characters';
    }

    // Commission rate: 0-100
    if ($commissionRate < 0 || $commissionRate > 100) {
        $validationErrors['commission_rate'] = 'Commission rate must be between 0 and 100';
    }

    // Status: must be one of active, inactive, suspended
    if (!in_array($companyData['status'], ['active', 'inactive', 'suspended'])) {
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

    // Step 3: Check for duplicate subdomain (excluding current company)
    $existingSubdomain = callSupabaseWithServiceKey(
        'companies?subdomain=eq.' . urlencode($companyData['subdomain']) . '&id=neq.' . urlencode($companyData['id']) . '&select=id',
        'GET'
    );
    if (!empty($existingSubdomain)) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error' => 'This subdomain is already taken',
            'errors' => ['subdomain' => 'This subdomain is already taken. Please choose a different one.']
        ]);
        exit;
    }

    // Step 4: Check for duplicate company name (excluding current company)
    $existingName = callSupabaseWithServiceKey(
        'companies?company_name=eq.' . urlencode($companyData['company_name']) . '&id=neq.' . urlencode($companyData['id']) . '&select=id',
        'GET'
    );
    if (!empty($existingName)) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error' => 'A company with this name already exists',
            'errors' => ['company_name' => 'A company with this name already exists.']
        ]);
        exit;
    }

    // Step 5: Check for duplicate contact phone (excluding current company)
    $existingPhone = callSupabaseWithServiceKey(
        'companies?contact_phone=eq.' . urlencode($companyData['contact_phone']) . '&id=neq.' . urlencode($companyData['id']) . '&select=id',
        'GET'
    );
    if (!empty($existingPhone)) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error' => 'This phone number is already registered to another company',
            'errors' => ['contact_phone' => 'This phone number is already registered to another company.']
        ]);
        exit;
    }

    // Step 6: Prepare update data (exclude id field from update payload)
    $updateData = [
        'company_name' => $companyData['company_name'],
        'subdomain' => $companyData['subdomain'],
        'contact_person' => $companyData['contact_person'],
        'contact_email' => $companyData['contact_email'],
        'contact_phone' => $companyData['contact_phone'],
        'address' => $companyData['address'],
        'city' => $companyData['city'],
        'state' => $companyData['state'],
        'postal_code' => $companyData['postal_code'],
        'country' => $companyData['country'],
        'status' => $companyData['status'],
        'commission_rate' => $commissionRate,
        'updated_at' => date('Y-m-d H:i:s')
    ];

    // Step 7: Update company in Supabase
    $result = callSupabaseWithServiceKey("companies?id=eq.{$companyData['id']}", 'PATCH', $updateData);

    // Step 8: Cascade suspension/unsuspension to all company users
    $cascadeCount = 0;
    $adminId = $_SESSION['user_id'] ?? 'system';
    $currentTime = date('c');

    if ($companyData['status'] === 'suspended') {
        // Suspend all active users under this company
        $companyUsers = callSupabaseWithServiceKey(
            "profiles?company_id=eq.{$companyData['id']}&select=id,email,full_name,status",
            'GET'
        );

        if (!empty($companyUsers) && is_array($companyUsers)) {
            foreach ($companyUsers as $user) {
                $uid = $user['id'] ?? null;
                if (!$uid || $uid === $adminId) continue; // Don't suspend the admin performing the action

                // Skip if already suspended
                if (($user['status'] ?? '') === 'Suspended') continue;

                // Update user status to Suspended
                callSupabaseWithServiceKey(
                    "profiles?id=eq.{$uid}",
                    'PATCH',
                    [
                        'status' => 'Suspended',
                        'suspended_at' => $currentTime,
                        'suspended_by' => $adminId,
                        'suspension_reason' => 'Company suspended by administrator',
                        'updated_at' => $currentTime
                    ]
                );

                // Kill active sessions for this user
                $activeSessions = callSupabaseWithServiceKey(
                    "user_sessions?user_id=eq.{$uid}&logout_time=is.null",
                    'GET'
                );
                if (!empty($activeSessions) && is_array($activeSessions)) {
                    foreach ($activeSessions as $session) {
                        $sessionId = $session['id'] ?? null;
                        if ($sessionId) {
                            callSupabaseWithServiceKey(
                                "user_sessions?id=eq.{$sessionId}",
                                'PATCH',
                                ['logout_time' => $currentTime]
                            );
                        }
                    }
                }

                // Audit log
                try {
                    callSupabaseWithServiceKey(
                        'suspension_audit_log',
                        'POST',
                        [
                            'user_id' => $uid,
                            'suspended_by_id' => $adminId,
                            'action' => 'suspended',
                            'reason' => 'Company suspended by administrator',
                            'suspension_reason' => 'Company suspended by administrator',
                            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500)
                        ]
                    );
                } catch (Exception $e) {
                    error_log("Warning: Failed to create audit log for user {$uid}: " . $e->getMessage());
                }

                $cascadeCount++;
                error_log("Cascade-suspended user {$uid} due to company suspension");
            }
        }

        error_log("Company {$companyData['id']} suspended — {$cascadeCount} users cascade-suspended");

    } elseif ($companyData['status'] === 'active') {
        // Unsuspend users that were suspended due to company suspension
        $suspendedUsers = callSupabaseWithServiceKey(
            "profiles?company_id=eq.{$companyData['id']}&status=eq.Suspended&select=id,email,full_name,suspension_reason",
            'GET'
        );

        if (!empty($suspendedUsers) && is_array($suspendedUsers)) {
            foreach ($suspendedUsers as $user) {
                $uid = $user['id'] ?? null;
                if (!$uid) continue;

                // Only unsuspend if they were suspended specifically because of the company suspension
                $reason = $user['suspension_reason'] ?? '';
                if ($reason !== 'Company suspended by administrator') continue;

                // Reactivate user
                callSupabaseWithServiceKey(
                    "profiles?id=eq.{$uid}",
                    'PATCH',
                    [
                        'status' => 'Active',
                        'suspended_at' => null,
                        'suspended_by' => null,
                        'suspension_reason' => null,
                        'updated_at' => $currentTime
                    ]
                );

                // Audit log
                try {
                    callSupabaseWithServiceKey(
                        'suspension_audit_log',
                        'POST',
                        [
                            'user_id' => $uid,
                            'suspended_by_id' => $adminId,
                            'action' => 'unsuspended',
                            'reason' => 'Company reactivated by administrator',
                            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500)
                        ]
                    );
                } catch (Exception $e) {
                    error_log("Warning: Failed to create audit log for user {$uid}: " . $e->getMessage());
                }

                $cascadeCount++;
                error_log("Cascade-unsuspended user {$uid} due to company reactivation");
            }
        }

        error_log("Company {$companyData['id']} reactivated — {$cascadeCount} users cascade-unsuspended");
    }

    // Success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Company updated successfully',
        'company_id' => $companyData['id'],
        'users_affected' => $cascadeCount
    ]);

} catch (Exception $e) {
    error_log('Error in update_company.php: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
