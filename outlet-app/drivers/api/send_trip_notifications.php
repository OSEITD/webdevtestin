<?php
require_once '../../includes/OutletAwareSupabaseHelper.php';
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
$trip_id = $_POST['trip_id'] ?? $_GET['trip_id'] ?? '';
$driver_id = $_SESSION['user_id'];
$company_id = $_SESSION['company_id'];
if (!$trip_id) {
    echo json_encode(['success' => false, 'error' => 'Missing trip_id']);
    exit;
}
$supabase = new OutletAwareSupabaseHelper();
try {
    $parcelList = $supabase->get('parcel_list',
        'trip_id=eq.' . urlencode($trip_id) . '&select=parcel_id'
    );
    if (empty($parcelList)) {
        echo json_encode([
            'success' => true,
            'message' => 'No parcels found for this trip',
            'notifications_sent' => 0
        ]);
        exit;
    }
    $parcelIds = array_column($parcelList, 'parcel_id');
    $parcelIdsStr = implode(',', array_map('urlencode', $parcelIds));
    $parcels = $supabase->get('parcels',
        'id=in.(' . $parcelIdsStr . ')&select=id,track_number,sender_name,sender_email,sender_phone,receiver_name,receiver_phone,global_sender_id,global_receiver_id,destination_outlet_id'
    );
    $driver = $supabase->get('drivers', 'id=eq.' . urlencode($driver_id));
    $driverName = $driver[0]['driver_name'] ?? 'Driver';
    $trip = $supabase->get('trips', 'id=eq.' . urlencode($trip_id));
    $tripInfo = $trip[0] ?? [];
    $notificationsSent = 0;
    $recipients = [];
    foreach ($parcels as $parcel) {
        $trackNumber = $parcel['track_number'];
        $notificationData = [
            'parcel_id' => $parcel['id'],
            'track_number' => $trackNumber,
            'trip_id' => $trip_id,
            'driver_name' => $driverName,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        if (!empty($parcel['sender_phone']) || !empty($parcel['sender_email'])) {
            $senderNotification = [
                'company_id' => $company_id,
                'recipient_id' => $parcel['global_sender_id'] ?? null,
                'sender_id' => $driver_id,
                'title' => '📦 Your Parcel is On the Way',
                'message' => "Good news! Your parcel (#{$trackNumber}) is now in transit with {$driverName}. Track your delivery in real-time.",
                'notification_type' => 'parcel_status_change',
                'parcel_id' => $parcel['id'],
                'priority' => 'high',
                'status' => 'unread',
                'data' => json_encode($notificationData),
                'created_at' => date('Y-m-d H:i:s')
            ];
            $supabase->insert('notifications', $senderNotification);
            if ($parcel['global_sender_id']) {
                sendPushNotification(
                    $parcel['global_sender_id'],
                    'Your Parcel is On the Way',
                    "Parcel #{$trackNumber} is now in transit with {$driverName}",
                    $notificationData,
                    $supabase
                );
            }
            $notificationsSent++;
            $recipients[] = [
                'type' => 'sender',
                'name' => $parcel['sender_name'],
                'track_number' => $trackNumber
            ];
        }
        if (!empty($parcel['receiver_phone'])) {
            $receiverNotification = [
                'company_id' => $company_id,
                'recipient_id' => $parcel['global_receiver_id'] ?? null,
                'sender_id' => $driver_id,
                'title' => '📦 Package Coming Your Way',
                'message' => "Your parcel (#{$trackNumber}) is on the way! Expected delivery soon. Track it in real-time.",
                'notification_type' => 'parcel_status_change',
                'parcel_id' => $parcel['id'],
                'priority' => 'high',
                'status' => 'unread',
                'data' => json_encode($notificationData),
                'created_at' => date('Y-m-d H:i:s')
            ];
            $supabase->insert('notifications', $receiverNotification);
            if ($parcel['global_receiver_id']) {
                sendPushNotification(
                    $parcel['global_receiver_id'],
                    'Package Coming Your Way',
                    "Parcel #{$trackNumber} is on the way. Track your delivery!",
                    $notificationData,
                    $supabase
                );
            }
            $notificationsSent++;
            $recipients[] = [
                'type' => 'receiver',
                'name' => $parcel['receiver_name'],
                'track_number' => $trackNumber
            ];
        }
    }
    $supabase->insert('notification_logs', [
        'user_id' => $driver_id,
        'title' => 'Trip Started Notifications',
        'body' => "Sent {$notificationsSent} notifications for trip {$trip_id}",
        'data' => json_encode([
            'trip_id' => $trip_id,
            'parcels_count' => count($parcels),
            'notifications_sent' => $notificationsSent,
            'recipients' => $recipients
        ]),
        'status' => 'sent',
        'sent_at' => date('Y-m-d H:i:s'),
        'created_at' => date('Y-m-d H:i:s')
    ]);
    echo json_encode([
        'success' => true,
        'message' => "Successfully sent {$notificationsSent} notifications",
        'notifications_sent' => $notificationsSent,
        'parcels_count' => count($parcels),
        'recipients' => $recipients
    ]);
} catch (Exception $e) {
    error_log("Error sending trip notifications: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
function sendPushNotification($userId, $title, $body, $data, $supabase) {
    try {
        $subscriptions = $supabase->get('push_subscriptions',
            'user_id=eq.' . urlencode($userId) . '&is_active=eq.true'
        );
        if (empty($subscriptions)) {
            return false;
        }
        foreach ($subscriptions as $subscription) {
            $payload = json_encode([
                'title' => $title,
                'body' => $body,
                'icon' => '/img/logo.png',
                'badge' => '/img/badge.png',
                'data' => $data,
                'timestamp' => time()
            ]);
            $supabase->insert('notification_logs', [
                'user_id' => $userId,
                'title' => $title,
                'body' => $body,
                'data' => json_encode($data),
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
        return true;
    } catch (Exception $e) {
        error_log("Error sending push notification: " . $e->getMessage());
        return false;
    }
}
