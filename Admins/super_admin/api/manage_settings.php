<?php
// Centralized API initialization
require_once __DIR__ . '/init.php';

// Verify authentication
ErrorHandler::requireAuth('manage_settings.php');

require_once __DIR__ . '/supabase-client.php';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Handle saving settings
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            throw new Exception('Invalid input data - empty request body');
        }

        error_log('manage_settings POST received: ' . json_encode($input));

        $settings = [
            'platform_name' => $input['platformName'] ?? 'WebDev Tech',
            'global_currency' => substr(trim($input['defaultCurrency'] ?? 'ZMW'), 0, 3),
            'timezone' => $input['timezone'] ?? 'Africa/Lusaka',
            'allow_registrations' => (bool)($input['allowRegistrations'] ?? true),
            'enable_2fa' => (bool)($input['enable2FA'] ?? false),
            'default_user_role' => $input['defaultUserRole'] ?? 'Admin',
            'email_notifications' => (bool)($input['emailNotifications'] ?? true),
            'sms_alerts' => (bool)($input['smsAlerts'] ?? false),
            'critical_alerts' => (bool)($input['criticalAlerts'] ?? true),
            'payment_gateway' => $input['paymentGateway'] ?? 'Stripe',
            'sms_provider' => $input['smsProvider'] ?? 'Twilio',
            'updated_at' => date('Y-m-d H:i:s')
        ];

        error_log('Settings to save: ' . json_encode($settings));

        // Fetch the existing system_settings record using service key
        $existingSettings = callSupabaseWithServiceKey('system_settings?limit=1', 'GET');
        error_log('Existing settings query result: ' . json_encode($existingSettings));
        
        if (!is_array($existingSettings) || empty($existingSettings)) {
            // If no record exists, create one with POST
            error_log('Creating new settings record...');
            try {
                $createResult = callSupabaseWithServiceKey('system_settings', 'POST', $settings);
                error_log('Create result type: ' . gettype($createResult));
                error_log('Create result: ' . json_encode($createResult));
            } catch (Exception $e) {
                error_log('Create failed with exception: ' . $e->getMessage());
                throw new Exception('Failed to create settings: ' . $e->getMessage());
            }
        } else {
            // Update existing record with PATCH
            $settingsId = $existingSettings[0]['id'];
            error_log('Updating existing settings record ID: ' . $settingsId);
            try {
                $result = callSupabaseWithServiceKey("system_settings?id=eq.{$settingsId}", 'PATCH', $settings);
                error_log('Update result type: ' . gettype($result));
                error_log('Update result: ' . json_encode($result));
            } catch (Exception $e) {
                error_log('Update failed with exception: ' . $e->getMessage());
                throw new Exception('Failed to update settings: ' . $e->getMessage());
            }
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Settings updated successfully'
        ]);
        exit;

    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Handle retrieving settings
        $settings = callSupabaseWithServiceKey('system_settings?limit=1', 'GET');
        
        if (!is_array($settings) || empty($settings)) {
            // Return default settings if none exist
            $settings = [[
                'platform_name' => 'WebDev Tech',
                'global_currency' => 'ZMW',
                'timezone' => 'Africa/Lusaka'
            ]];
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'settings' => $settings[0]
        ]);
        exit;
    } else {
        throw new Exception('Invalid request method: ' . $_SERVER['REQUEST_METHOD']);
    }

} catch (Exception $e) {
    http_response_code(500);
    $errorMsg = $e->getMessage();
    error_log('manage_settings.php FATAL ERROR: ' . $errorMsg);
    error_log('Stack trace: ' . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'error' => $errorMsg,
        'type' => get_class($e)
    ]);
    exit;
}
