<?php
// Centralized API initialization
require_once __DIR__ . '/init.php';

// Verify authentication
ErrorHandler::requireAuth('manage_settings.php');

require_once 'supabase-client.php';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Handle saving settings
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            throw new Exception('Invalid input data');
        }

        $settings = [
            'platform_name' => $input['platformName'] ?? 'WebDev Tech',
            'default_currency' => $input['defaultCurrency'] ?? 'ZMW',
            'timezone' => $input['timezone'] ?? 'Africa/Lusaka',
            'allow_registrations' => $input['allowRegistrations'] ?? true,
            'enable_2fa' => $input['enable2FA'] ?? false,
            'default_user_role' => $input['defaultUserRole'] ?? 'Super Admin',
            'email_notifications' => $input['emailNotifications'] ?? true,
            'sms_alerts' => $input['smsAlerts'] ?? false,
            'critical_alerts' => $input['criticalAlerts'] ?? true,
            'payment_gateway' => $input['paymentGateway'] ?? 'Stripe',
            // 'commission_percent' => isset($input['commissionPercent']) ? $input['commissionPercent'] : 0,
            'sms_provider' => $input['smsProvider'] ?? 'Twilio',
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // Update settings in Supabase
        $result = callSupabaseWithServiceKey('system_settings?id=eq.1', 'PATCH', $settings);
        if ($result === false) {
            throw new Exception('Failed to update settings');
        }

        echo json_encode([
            'success' => true,
            'message' => 'Settings updated successfully'
        ]);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Handle retrieving settings
        $settings = callSupabase('system_settings?id=eq.1');
        
        if (!is_array($settings) || empty($settings)) {
            throw new Exception('Failed to retrieve settings');
        }

        echo json_encode([
            'success' => true,
            'settings' => $settings[0]
        ]);
    } else {
        ErrorHandler::requireMethod('GET', 'manage_settings.php');
    }

} catch (Exception $e) {
    ErrorHandler::handleException($e, 'manage_settings.php');
}
