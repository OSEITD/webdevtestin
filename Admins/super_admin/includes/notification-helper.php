<?php
class AdminNotificationHelper {
    
    /**
     * Create a user created notification
     */
    public static function createUserCreatedNotification($userData, $recipientId = null) {
        $title = "New User Created";
        $createdBy = $userData['created_by'] ?? 'system';
        $message = "User {$userData['full_name']} ({$userData['email']}) has been created by {$createdBy}";
        
        return self::createNotification([
            'recipient_id' => $recipientId,
            'title' => $title,
            'message' => $message,
            'notification_type' => 'user_created',
            'user_id' => $userData['id'],
            'priority' => 'medium',
            'data' => json_encode([
                'user_email' => $userData['email'],
                'user_name' => $userData['full_name'],
                'user_role' => $userData['role']
            ])
        ]);
    }
    
    /**
     * Create a company created notification
     */
    public static function createCompanyCreatedNotification($companyData, $recipientId = null) {
        $title = "New Company Created";
        $message = "Company '{$companyData['company_name']}' has been created";
        
        return self::createNotification([
            'recipient_id' => $recipientId,
            'title' => $title,
            'message' => $message,
            'notification_type' => 'company_created',
            'company_id' => $companyData['id'],
            'priority' => 'medium',
            'data' => json_encode([
                'company_name' => $companyData['company_name'],
                'company_email' => $companyData['company_email'] ?? 'N/A'
            ])
        ]);
    }
    
    /**
     * Create a company status change notification
     */
    public static function createCompanyStatusNotification($companyData, $oldStatus, $newStatus, $recipientId = null) {
        $title = "Company Status Changed";
        $message = "Company '{$companyData['company_name']}' status changed from '{$oldStatus}' to '{$newStatus}'";
        
        $priority = 'medium';
        if ($newStatus === 'suspended' || $newStatus === 'deactivated') {
            $priority = 'high';
        }
        
        return self::createNotification([
            'recipient_id' => $recipientId,
            'title' => $title,
            'message' => $message,
            'notification_type' => 'company_status_change',
            'company_id' => $companyData['id'],
            'priority' => $priority,
            'data' => json_encode([
                'company_name' => $companyData['company_name'],
                'old_status' => $oldStatus,
                'new_status' => $newStatus
            ])
        ]);
    }
    
    /**
     * Create an outlet created notification
     */
    public static function createOutletCreatedNotification($outletData, $recipientId = null) {
        $title = "New Outlet Created";
        $companyName = $outletData['company_name'] ?? 'Unknown';
        $message = "Outlet '{$outletData['outlet_name']}' has been created for company '{$companyName}'";
        
        return self::createNotification([
            'recipient_id' => $recipientId,
            'title' => $title,
            'message' => $message,
            'notification_type' => 'outlet_created',
            'outlet_id' => $outletData['id'],
            'company_id' => $outletData['company_id'],
            'priority' => 'medium',
            'data' => json_encode([
                'outlet_name' => $outletData['outlet_name'],
                'outlet_address' => $outletData['address'] ?? 'N/A'
            ])
        ]);
    }
    
    /**
     * Create a system alert notification
     */
    public static function createSystemAlertNotification($alertTitle, $alertMessage, $severity = 'medium', $recipientId = null) {
        return self::createNotification([
            'recipient_id' => $recipientId,
            'title' => $alertTitle,
            'message' => $alertMessage,
            'notification_type' => 'system_alert',
            'priority' => $severity,
            'data' => json_encode([
                'alert_type' => 'system',
                'timestamp' => date('Y-m-d H:i:s')
            ])
        ]);
    }
    
    /**
     * Create a report notification
     */
    public static function createReportNotification($reportData, $recipientId = null) {
        $title = "Report Generated";
        $message = "Report '{$reportData['name']}' has been generated and is ready for download";
        
        return self::createNotification([
            'recipient_id' => $recipientId,
            'title' => $title,
            'message' => $message,
            'notification_type' => 'report_ready',
            'priority' => 'medium',
            'data' => json_encode([
                'report_name' => $reportData['name'],
                'report_type' => $reportData['type'] ?? 'custom',
                'generated_at' => $reportData['created_at'] ?? date('Y-m-d H:i:s')
            ])
        ]);
    }
    
    /**
     * Create a role created notification
     */
    public static function createRoleCreatedNotification($roleData, $recipientId = null) {
        $title = "New Role Created";
        $message = "Role '{$roleData['name']}' has been created in the system";
        
        return self::createNotification([
            'recipient_id' => $recipientId,
            'title' => $title,
            'message' => $message,
            'notification_type' => 'role_created',
            'priority' => 'low',
            'data' => json_encode([
                'role_name' => $roleData['name'],
                'role_description' => $roleData['description'] ?? ''
            ])
        ]);
    }
    
    /**
     * Generic notification creation
     */
    private static function createNotification($data) {
        $notification = [
            'title' => $data['title'] ?? 'Notification',
            'message' => $data['message'] ?? '',
            'notification_type' => $data['notification_type'] ?? 'system',
            'priority' => $data['priority'] ?? 'medium',
            'status' => 'unread',
            'is_read' => false,
            'created_at' => date('c'),
            'data' => $data['data'] ?? json_encode([])
        ];
        
        // Add optional fields
        if (isset($data['recipient_id'])) {
            $notification['recipient_id'] = $data['recipient_id'];
        }
        if (isset($data['user_id'])) {
            $notification['user_id'] = $data['user_id'];
        }
        if (isset($data['company_id'])) {
            $notification['company_id'] = $data['company_id'];
        }
        if (isset($data['outlet_id'])) {
            $notification['outlet_id'] = $data['outlet_id'];
        }
        
        return $notification;
    }
}

/**
 * Helper functions for notification dates
 */
if (!function_exists('formatNotificationDate')) {
    function formatNotificationDate($date) {
        return date('M d, Y H:i', strtotime($date));
    }
}

if (!function_exists('timeAgo')) {
    function timeAgo($date) {
        $time = strtotime($date);
        $diff = time() - $time;
        
        if ($diff < 60) return $diff . 's ago';
        if ($diff < 3600) return floor($diff / 60) . 'm ago';
        if ($diff < 86400) return floor($diff / 3600) . 'h ago';
        if ($diff < 604800) return floor($diff / 86400) . 'd ago';
        
        return date('M d', $time);
    }
}
