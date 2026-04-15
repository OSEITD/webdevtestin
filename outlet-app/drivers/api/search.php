<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../includes/session_manager.php';
initSession();
require_once __DIR__ . '/../../includes/OutletAwareSupabaseHelper.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'driver') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$driverId = $_SESSION['user_id'];
$companyId = $_SESSION['company_id'] ?? null;

if (!$companyId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing company context']);
    exit;
}

$query = trim($_GET['q'] ?? '');
$type = $_GET['type'] ?? 'all';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$limit = max(1, min($limit, 20));

if ($query === '' || strlen($query) < 2) {
    echo json_encode([
        'success' => true,
        'query' => $query,
        'total' => 0,
        'results' => [
            'parcels' => [],
            'trips' => [],
            'notifications' => []
        ]
    ]);
    exit;
}

$supabase = new OutletAwareSupabaseHelper();
$results = [
    'parcels' => [],
    'trips' => [],
    'notifications' => []
];

try {
    $encodedQuery = urlencode($query);

    if ($type === 'all' || $type === 'parcels') {
        $parcelFilters = 'company_id=eq.' . urlencode($companyId) .
            '&driver_id=eq.' . urlencode($driverId) .
            '&or=(' .
                'track_number.ilike.*' . $encodedQuery . '*,' .
                'sender_name.ilike.*' . $encodedQuery . '*,' .
                'receiver_name.ilike.*' . $encodedQuery . '*,' .
                'sender_phone.ilike.*' . $encodedQuery . '*,' .
                'receiver_phone.ilike.*' . $encodedQuery . '*,' .
                'receiver_address.ilike.*' . $encodedQuery . '*'
            . ')' .
            '&order=created_at.desc' .
            '&limit=' . $limit;

        $parcelResults = $supabase->get(
            'parcels',
            $parcelFilters,
            'id,track_number,sender_name,receiver_name,status,receiver_address,created_at'
        );

        foreach ($parcelResults as $parcel) {
            $trackNumber = $parcel['track_number'] ?? '';
            $results['parcels'][] = [
                'id' => $parcel['id'] ?? null,
                'type' => 'parcel',
                'title' => 'Parcel #' . ($trackNumber ?: 'Unknown'),
                'subtitle' => buildParcelSubtitle($parcel),
                'status' => $parcel['status'] ?? 'unknown',
                'date' => $parcel['created_at'] ?? null,
                'url' => 'pages/deliveries.php?search=' . urlencode($trackNumber ?: $query),
                'icon' => 'fa-box'
            ];
        }
    }

    if ($type === 'all' || $type === 'trips') {
        $normalized = preg_replace('/[^a-f0-9]/i', '', $query);
        $tripSearchParts = [];
        if (strlen($normalized) >= 4) {
            $tripSearchParts[] = 'id.ilike.*' . urlencode($normalized) . '*';
        }
        $tripSearchParts[] = 'trip_status.ilike.*' . $encodedQuery . '*';

        $tripFilters = 'driver_id=eq.' . urlencode($driverId) .
            '&or=(' . implode(',', $tripSearchParts) . ')' .
            '&order=created_at.desc' .
            '&limit=' . $limit;

        $tripResults = $supabase->get(
            'trips',
            $tripFilters,
            'id,trip_status,trip_date,departure_time,created_at'
        );

        foreach ($tripResults as $trip) {
            $tripId = $trip['id'] ?? '';
            $shortId = $tripId ? strtoupper(substr($tripId, 0, 8)) : 'UNKNOWN';
            $results['trips'][] = [
                'id' => $tripId,
                'type' => 'trip',
                'title' => 'Trip #' . $shortId,
                'subtitle' => buildTripSubtitle($trip),
                'status' => $trip['trip_status'] ?? 'unknown',
                'date' => $trip['created_at'] ?? null,
                'url' => 'dashboard.php?trip_id=' . urlencode($tripId),
                'icon' => 'fa-route'
            ];
        }
    }

    if ($type === 'all' || $type === 'notifications') {
        $notifFilters = 'recipient_id=eq.' . urlencode($driverId) .
            '&or=(' .
                'title.ilike.*' . $encodedQuery . '*,' .
                'message.ilike.*' . $encodedQuery . '*'
            . ')' .
            '&order=created_at.desc' .
            '&limit=' . $limit;

        $notifResults = $supabase->get(
            'notifications',
            $notifFilters,
            'id,title,message,status,created_at'
        );

        foreach ($notifResults as $notification) {
            $results['notifications'][] = [
                'id' => $notification['id'] ?? null,
                'type' => 'notification',
                'title' => $notification['title'] ?? 'Notification',
                'subtitle' => excerpt($notification['message'] ?? '', 90),
                'status' => $notification['status'] ?? 'unread',
                'date' => $notification['created_at'] ?? null,
                'url' => 'pages/notifications.php?highlight=' . urlencode($notification['id'] ?? ''),
                'icon' => 'fa-bell'
            ];
        }
    }

    $total = count($results['parcels']) + count($results['trips']) + count($results['notifications']);

    echo json_encode([
        'success' => true,
        'query' => $query,
        'total' => $total,
        'results' => $results
    ]);
} catch (Exception $e) {
    error_log('Driver search error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Search failed'
    ]);
}

function buildParcelSubtitle(array $parcel) {
    $sender = trim($parcel['sender_name'] ?? '');
    $receiver = trim($parcel['receiver_name'] ?? '');

    if ($sender !== '' && $receiver !== '') {
        return $sender . ' -> ' . $receiver;
    }

    if ($receiver !== '') {
        return 'Receiver: ' . $receiver;
    }

    if ($sender !== '') {
        return 'Sender: ' . $sender;
    }

    return '';
}

function buildTripSubtitle(array $trip) {
    $status = trim($trip['trip_status'] ?? '');
    $statusLabel = $status !== '' ? formatStatusLabel($status) : 'Unknown';
    $dateSource = $trip['trip_date'] ?? ($trip['departure_time'] ?? ($trip['created_at'] ?? null));
    $dateLabel = $dateSource ? formatDateLabel($dateSource) : '';

    if ($dateLabel !== '') {
        return 'Status: ' . $statusLabel . ' - ' . $dateLabel;
    }

    return 'Status: ' . $statusLabel;
}

function formatStatusLabel($status) {
    $label = str_replace('_', ' ', strtolower($status));
    return ucwords($label);
}

function formatDateLabel($dateString) {
    $timestamp = strtotime($dateString);
    if (!$timestamp) {
        return '';
    }
    return date('M j, Y', $timestamp);
}

function excerpt($text, $limit) {
    $text = trim((string)$text);
    if ($text === '') {
        return '';
    }
    if (strlen($text) <= $limit) {
        return $text;
    }
    return substr($text, 0, $limit - 3) . '...';
}
