<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
require_once '../../includes/ResponseCache.php';
$cache = new ResponseCache();
$action = $_GET['action'] ?? 'clear';
switch ($action) {
    case 'clear':
        
        $cleared = $cache->clear();
        echo json_encode([
            'success' => true,
            'action' => 'clear_all',
            'files_cleared' => $cleared,
            'message' => "Cleared $cleared cache files"
        ]);
        break;
        
    case 'cleanup':
        
        $maxAge = $_GET['max_age'] ?? 3600;
        $cleaned = $cache->cleanup($maxAge);
        echo json_encode([
            'success' => true,
            'action' => 'cleanup',
            'files_removed' => $cleaned,
            'max_age_seconds' => $maxAge,
            'message' => "Removed $cleaned old cache files"
        ]);
        break;
        
    case 'driver':
        
        $driverId = $_GET['driver_id'] ?? null;
        if (!$driverId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'driver_id required']);
            exit;
        }
        
        $companyId = $_GET['company_id'] ?? $_SESSION['company_id'] ?? null;
        if (!$companyId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'company_id required']);
            exit;
        }
        
        
        $dashboardKey = "driver_dashboard_{$driverId}_{$companyId}";
        $tripKey = "active_trip_{$driverId}_{$companyId}";
        
        $cache->delete($dashboardKey);
        $cache->delete($tripKey);
        
        echo json_encode([
            'success' => true,
            'action' => 'clear_driver',
            'driver_id' => $driverId,
            'company_id' => $companyId,
            'message' => "Cleared cache for driver $driverId"
        ]);
        break;
        
    case 'stats':
        
        $cacheDir = __DIR__ . '/../../cache/api';
        $files = glob($cacheDir . '/*.cache');
        $totalSize = 0;
        $oldestFile = null;
        $newestFile = null;
        
        foreach ($files as $file) {
            $size = filesize($file);
            $mtime = filemtime($file);
            $totalSize += $size;
            
            if ($oldestFile === null || $mtime < $oldestFile['time']) {
                $oldestFile = ['file' => basename($file), 'time' => $mtime];
            }
            if ($newestFile === null || $mtime > $newestFile['time']) {
                $newestFile = ['file' => basename($file), 'time' => $mtime];
            }
        }
        
        echo json_encode([
            'success' => true,
            'cache_files' => count($files),
            'total_size_kb' => round($totalSize / 1024, 2),
            'oldest_file' => $oldestFile ? [
                'name' => $oldestFile['file'],
                'age_seconds' => time() - $oldestFile['time'],
                'age_minutes' => round((time() - $oldestFile['time']) / 60, 1)
            ] : null,
            'newest_file' => $newestFile ? [
                'name' => $newestFile['file'],
                'age_seconds' => time() - $newestFile['time'],
                'age_minutes' => round((time() - $newestFile['time']) / 60, 1)
            ] : null
        ]);
        break;
        
    default:
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid action',
            'valid_actions' => ['clear', 'cleanup', 'driver', 'stats']
        ]);
}
