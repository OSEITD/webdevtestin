/**
 * Generate user report from all_users table using status column
 */
function generateUserReport($startDate, $endDate) {
    error_log("Starting user report generation - Start Date: $startDate, End Date: $endDate");
    
    $customHeaders = [
        'Range: 0-99999',  // Request all records
        'Range-Unit: items',
        'Prefer: resolution=merge-duplicates'
    ];
    
    $users = callSupabaseWithServiceKey('all_users', 'GET', null, $customHeaders);
    if (!$users) {
        error_log("No users found in all_users table");
        return array();
    }
    
    error_log("Found " . count($users) . " users in the database");

    $stats = array(
        'total_users' => 0,
        'active_users' => 0,
        'new_users' => 0,
        'by_role' => array(),
        'by_status' => array(),
        'by_company' => array()
    );

    foreach ($users as $user) {
        // Safely handle created_at date
        $createdAt = null;
        if (!empty($user['created_at'])) {
            $createdAt = strtotime($user['created_at']);
        }
        
        // Count total users
        $stats['total_users']++;

        // Count by status from the status column
        if (isset($user['status'])) {
            $status = strtolower($user['status']);
            if (!isset($stats['by_status'][$status])) {
                $stats['by_status'][$status] = 0;
            }
            $stats['by_status'][$status]++;

            // Count active users based on status being 'active'
            if ($status === 'active') {
                $stats['active_users']++;
            }
        } else {
            // Default status if not set
            if (!isset($stats['by_status']['unknown'])) {
                $stats['by_status']['unknown'] = 0;
            }
            $stats['by_status']['unknown']++;
        }

        // Count new users in selected date range
        if ($createdAt && strtotime($startDate) && strtotime($endDate)) {
            if ($createdAt >= strtotime($startDate) && $createdAt <= strtotime($endDate)) {
                $stats['new_users']++;
            }
        }

        // Group by role
        $role = isset($user['role']) ? strtolower($user['role']) : 'unknown';
        if (!isset($stats['by_role'][$role])) {
            $stats['by_role'][$role] = 0;
        }
        $stats['by_role'][$role]++;

        // Track by company if available
        if (isset($user['company_id']) && isset($user['company_name'])) {
            $companyName = $user['company_name'];
            if (!isset($stats['by_company'][$companyName])) {
                $stats['by_company'][$companyName] = 0;
            }
            $stats['by_company'][$companyName]++;
        }
    }

    // Calculate percentages and additional metrics
    if ($stats['total_users'] > 0) {
        $stats['active_rate'] = round(($stats['active_users'] / $stats['total_users']) * 100, 2);
        $stats['new_user_rate'] = round(($stats['new_users'] / $stats['total_users']) * 100, 2);
        
        // Calculate role distribution percentages
        $stats['role_distribution'] = array();
        foreach ($stats['by_role'] as $role => $count) {
            $stats['role_distribution'][$role] = round(($count / $stats['total_users']) * 100, 2);
        }
    }

    // Sort company stats by number of users
    if (!empty($stats['by_company'])) {
        arsort($stats['by_company']);
        // Keep only top 10 companies
        $stats['by_company'] = array_slice($stats['by_company'], 0, 10, true);
    }

    return $stats;
}