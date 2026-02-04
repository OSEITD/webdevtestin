<?php
/**
 * Backfill driver_qps table with historical performance data
 * This script populates the driver_qps aggregated table from trips and parcel_list data
 * 
 * Usage: Run via browser as manager
 * URL: http://outlet.localhost/drivers/api/backfill_driver_qps.php
 */

require_once '../../includes/OutletAwareSupabaseHelper.php';
require_once '../../config.php';

header('Content-Type: text/html; charset=utf-8');
session_start();

// Security: Only allow managers or admins to run this
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['manager', 'admin'])) {
    http_response_code(403);
    die('<h1>Error: Unauthorized</h1><p>This tool can only be run by managers or admins.</p>');
}

$company_id = $_SESSION['company_id'] ?? null;

?>
<!DOCTYPE html>
<html>
<head>
    <title>Driver QPS Backfill</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1200px; margin: 50px auto; padding: 20px; }
        .log { background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0; max-height: 500px; overflow-y: auto; }
        .log pre { margin: 5px 0; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        .summary { background: #e3f2fd; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .progress { background: #ddd; height: 30px; border-radius: 5px; margin: 10px 0; }
        .progress-bar { background: #4caf50; height: 100%; border-radius: 5px; transition: width 0.3s; }
    </style>
</head>
<body>
    <h1>🔄 Driver Performance Data Backfill</h1>
    <p>This tool will populate the <code>driver_qps</code> table with historical performance data from completed trips.</p>
    
    <div class="log" id="log">
        <pre class="info">[Starting backfill process...]</pre>
    </div>
    
    <div class="progress">
        <div class="progress-bar" id="progress" style="width: 0%"></div>
    </div>
    
    <div class="summary" id="summary" style="display: none;"></div>
    
    <script>
    function log(message, type = 'info') {
        const logDiv = document.getElementById('log');
        const pre = document.createElement('pre');
        pre.className = type;
        pre.textContent = `[${new Date().toLocaleTimeString()}] ${message}`;
        logDiv.appendChild(pre);
        logDiv.scrollTop = logDiv.scrollHeight;
    }
    
    function updateProgress(percent) {
        document.getElementById('progress').style.width = percent + '%';
    }
    
    function showSummary(html) {
        const summaryDiv = document.getElementById('summary');
        summaryDiv.innerHTML = html;
        summaryDiv.style.display = 'block';
    }
    </script>
</body>
</html>
<?php
flush();

try {
    $supabase = new OutletAwareSupabaseHelper();
    
    echo "<script>log('Fetching completed trips...', 'info');</script>";
    flush();
    
    $completed_trips = $supabase->get('trips', [
        'status' => 'eq.completed',
        'completed_at' => 'not.is.null',
        'select' => 'id,driver_id,company_id,completed_at',
        'order' => 'completed_at.desc',
        'limit' => 2000
    ]);
    
    if (!$completed_trips || count($completed_trips) === 0) {
        echo "<script>log('No completed trips found', 'error'); updateProgress(100);</script>";
        flush();
        exit;
    }
    
    $total_trips = count($completed_trips);
    echo "<script>log('Found {$total_trips} completed trips', 'success');</script>";
    flush();
    
    // Group by driver + date
    $aggregated = [];
    $processed = 0;
    
    foreach ($completed_trips as $trip) {
        $driver_id = $trip['driver_id'];
        if (!$driver_id) {
            $processed++;
            continue;
        }
        
        $date = date('Y-m-d', strtotime($trip['completed_at']));
        $company_id_trip = $trip['company_id'];
        $key = $driver_id . '|' . $date;
        
        if (!isset($aggregated[$key])) {
            $aggregated[$key] = [
                'driver_id' => $driver_id,
                'company_id' => $company_id_trip,
                'date' => $date,
                'trips_completed' => 0,
                'parcels_handled' => 0
            ];
        }
        
        $aggregated[$key]['trips_completed']++;
        
        // Get parcels for this trip
        try {
            $parcels = $supabase->get('parcel_list', [
                'trip_id' => 'eq.' . $trip['id'],
                'status' => 'in.(delivered,failed_delivery)',
                'select' => 'id'
            ]);
            
            if ($parcels) {
                $aggregated[$key]['parcels_handled'] += count($parcels);
            }
        } catch (Exception $e) {
            // Skip parcel count if error
        }
        
        $processed++;
        if ($processed % 50 === 0) {
            $percent = round(($processed / $total_trips) * 50); // First 50% for processing
            echo "<script>updateProgress($percent); log('Processed {$processed}/{$total_trips} trips', 'info');</script>";
            flush();
        }
    }
    
    $total_records = count($aggregated);
    echo "<script>log('Aggregated {$total_records} unique driver-date records', 'success'); updateProgress(50);</script>";
    flush();
    
    // Upsert into driver_qps
    $inserted = 0;
    $updated = 0;
    $errors = 0;
    $idx = 0;
    
    foreach ($aggregated as $record) {
        try {
            // Check if record exists
            $existing = $supabase->get('driver_qps', [
                'driver_id' => 'eq.' . $record['driver_id'],
                'date' => 'eq.' . $record['date']
            ]);
            
            if ($existing && count($existing) > 0) {
                // Update existing
                $result = $supabase->patch('driver_qps', [
                    'trips_completed' => $record['trips_completed'],
                    'parcels_handled' => $record['parcels_handled']
                ], [
                    'driver_id' => 'eq.' . $record['driver_id'],
                    'date' => 'eq.' . $record['date']
                ]);
                if ($result !== false) {
                    $updated++;
                } else {
                    $errors++;
                }
            } else {
                // Insert new
                $result = $supabase->post('driver_qps', $record);
                if ($result) {
                    $inserted++;
                } else {
                    $errors++;
                }
            }
        } catch (Exception $e) {
            error_log("Error upserting driver_qps: " . $e->getMessage());
            $errors++;
        }
        
        $idx++;
        if ($idx % 10 === 0) {
            $percent = 50 + round(($idx / $total_records) * 50); // Second 50% for upserting
            echo "<script>updateProgress($percent); log('Upserted {$idx}/{$total_records} records', 'info');</script>";
            flush();
        }
    }
    
    echo "<script>updateProgress(100);</script>";
    echo "<script>log('✅ Backfill complete!', 'success');</script>";
    
    $summaryHtml = "<h3>📊 Backfill Summary</h3>";
    $summaryHtml .= "<p><strong>Completed Trips Processed:</strong> {$total_trips}</p>";
    $summaryHtml .= "<p><strong>Driver-Date Records:</strong> {$total_records}</p>";
    $summaryHtml .= "<p><strong>Inserted:</strong> {$inserted}</p>";
    $summaryHtml .= "<p><strong>Updated:</strong> {$updated}</p>";
    $summaryHtml .= "<p><strong>Errors:</strong> {$errors}</p>";
    
    echo "<script>showSummary(" . json_encode($summaryHtml) . ");</script>";
    flush();
    
} catch (Exception $e) {
    error_log("Backfill error: " . $e->getMessage());
    echo "<script>log('❌ Error: " . addslashes($e->getMessage()) . "', 'error');</script>";
    flush();
}
?>
