<?php

header('Content-Type: application/json');

try {
    
    $vapidFile = dirname(__DIR__, 2) . '/vapid-keys.txt';
    
    if (!file_exists($vapidFile)) {
        throw new Exception('VAPID keys file not found: ' . $vapidFile);
    }
    
    $content = file_get_contents($vapidFile);
    preg_match('/PUBLIC KEY:\s*[\r\n]+([A-Za-z0-9_\-]+)/', $content, $matches);
    
    if (!$matches || !isset($matches[1])) {
        throw new Exception('Could not extract public key from vapid-keys.txt');
    }
    
    $masterPublicKey = trim($matches[1]);
    
    
    $filesToCheck = [
        'outlet-app/drivers/dashboard.php' => [
            'pattern' => '/VAPID_PUBLIC_KEY\s*=\s*[\'"]([^\'\"]+)[\'"]/',
            'type' => 'JavaScript constant'
        ],
        'outlet-app/drivers/test-push-notifications.html' => [
            'pattern' => '/VAPID_PUBLIC_KEY\s*=\s*[\'"]([^\'\"]+)[\'"]/',
            'type' => 'JavaScript constant'
        ],
        'outlet-app/includes/push_notification_service.php' => [
            'pattern' => '/[\'"]publicKey[\'\"]\s*=>\s*[\'"]([^\'\"]+)[\'"]/',
            'type' => 'PHP array value'
        ]
    ];
    
    $results = [
        'timestamp' => date('Y-m-d H:i:s'),
        'master_key' => $masterPublicKey,
        'validation_results' => [],
        'status' => 'healthy',
        'issues_found' => 0
    ];
    
    $baseDir = dirname(__DIR__, 2);
    
    foreach ($filesToCheck as $relativePath => $config) {
        $filePath = $baseDir . '/' . $relativePath;
        $fileResult = [
            'file' => $relativePath,
            'type' => $config['type'],
            'exists' => file_exists($filePath),
            'key_found' => null,
            'matches_master' => false,
            'status' => 'unknown'
        ];
        
        if ($fileResult['exists']) {
            $fileContent = file_get_contents($filePath);
            
            if (preg_match($config['pattern'], $fileContent, $matches)) {
                $fileResult['key_found'] = $matches[1];
                $fileResult['matches_master'] = ($matches[1] === $masterPublicKey);
                $fileResult['status'] = $fileResult['matches_master'] ? 'valid' : 'mismatch';
                
                if (!$fileResult['matches_master']) {
                    $results['issues_found']++;
                    $results['status'] = 'critical';
                }
            } else {
                $fileResult['status'] = 'not_found';
                $results['issues_found']++;
                $results['status'] = 'critical';
            }
        } else {
            $fileResult['status'] = 'missing_file';
            $results['issues_found']++;
            $results['status'] = 'critical';
        }
        
        $results['validation_results'][] = $fileResult;
    }
    
    
    $logFile = $baseDir . '/xampp/php/logs/php_error_log';
    if (file_exists($logFile)) {
        $logContent = file_get_contents($logFile);
        $recent403Count = substr_count($logContent, '403 Forbidden');
        $vapidErrorCount = substr_count($logContent, 'VAPID credentials');
        
        $results['log_analysis'] = [
            'recent_403_errors' => $recent403Count,
            'vapid_errors' => $vapidErrorCount,
            'log_file_checked' => $logFile
        ];
        
        if ($vapidErrorCount > 0) {
            $results['issues_found']++;
            $results['status'] = 'warning';
        }
    }
    
    
    $results['recommendations'] = [];
    
    if ($results['issues_found'] > 0) {
        $results['recommendations'][] = 'CRITICAL: VAPID key mismatches detected';
        $results['recommendations'][] = 'Update all files to use the master public key: ' . $masterPublicKey;
        $results['recommendations'][] = 'Force user re-subscription after fixing keys';
    }
    
    if ($results['status'] === 'healthy') {
        $results['recommendations'][] = 'All VAPID keys are synchronized correctly';
        $results['recommendations'][] = 'Monitor push notification success rates daily';
    }
    
    echo json_encode($results, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'VAPID validation failed',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
}
?>