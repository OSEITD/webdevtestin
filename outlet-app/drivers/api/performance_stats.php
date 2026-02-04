<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
require_once __DIR__ . '/../../includes/OutletAwareSupabaseHelper.php';
try {
    $driverId = $_SESSION['user_id'];
    $companyId = $_SESSION['company_id'];
    $supabase = new OutletAwareSupabaseHelper();

    $today = date('Y-m-d');
    $weekStart = date('Y-m-d', strtotime('monday this week'));
    $weekEnd = date('Y-m-d', strtotime('sunday this week'));

    // Fast path: use pre-aggregated driver_qps table to compute trips/parcels quickly
    $tripsTodayCount = 0;
    $tripsWeekCount = 0;
    $parcelsDeliveredCount = 0;
    $parcelsReturnedCount = 0;

    try {
        $qps = $supabase->get('driver_qps',
            "driver_id=eq.{$driverId}&company_id=eq.{$companyId}&date=gte.{$weekStart}&date=lte.{$today}",
            'date,trips_completed,parcels_handled'
        );

        if (is_array($qps) && !empty($qps)) {
            foreach ($qps as $row) {
                $rowDate = $row['date'];
                $tripsWeekCount += intval($row['trips_completed'] ?? 0);
                $parcelsDeliveredCount += intval($row['parcels_handled'] ?? 0);
                if ($rowDate === $today) {
                    $tripsTodayCount += intval($row['trips_completed'] ?? 0);
                }
            }
        } else {
            // Fallback to detailed queries when aggregated data is not available
            $tripsToday = $supabase->get('trips',
                "driver_id=eq.{$driverId}&company_id=eq.{$companyId}&trip_status=eq.completed&updated_at=gte.{$today}T00:00:00&updated_at=lte.{$today}T23:59:59",
                'id'
            );
            $tripsTodayCount = count($tripsToday);

            $tripsWeek = $supabase->get('trips',
                "driver_id=eq.{$driverId}&company_id=eq.{$companyId}&trip_status=eq.completed&updated_at=gte.{$weekStart}T00:00:00&updated_at=lte.{$weekEnd}T23:59:59",
                'id'
            );
            $tripsWeekCount = count($tripsWeek);

            $parcelsDelivered = $supabase->get('parcels',
                "driver_id=eq.{$driverId}&company_id=eq.{$companyId}&status=eq.delivered&updated_at=gte.{$weekStart}T00:00:00",
                'id'
            );
            $parcelsDeliveredCount = count($parcelsDelivered);
        }

        // Parcels returned still requires detailed query (rare), keep as fallback
        $parcelsReturned = $supabase->get('parcels',
            "driver_id=eq.{$driverId}&company_id=eq.{$companyId}&status=eq.returned&updated_at=gte.{$weekStart}T00:00:00",
            'id'
        );
        $parcelsReturnedCount = count($parcelsReturned);

    } catch (Exception $e) {
        error_log('Performance stats (qps) fallback error: ' . $e->getMessage());
        // On any error, default to zero and continue
    }

    echo json_encode([
        'success' => true,
        'stats' => [
            'trips_today' => $tripsTodayCount,
            'trips_week' => $tripsWeekCount,
            'parcels_delivered' => $parcelsDeliveredCount,
            'parcels_returned' => $parcelsReturnedCount
        ]
    ]);

} catch (Exception $e) {
    error_log("Performance stats error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch performance statistics: ' . $e->getMessage(),
        'stats' => [
            'trips_today' => 0,
            'trips_week' => 0,
            'parcels_delivered' => 0,
            'parcels_returned' => 0
        ]
    ]);
}
