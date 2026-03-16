<?php
// Simple server-side address normalization using OpenStreetMap Nominatim
// Accepts POST or GET 'address' parameter and returns JSON with parsed fields.

header('Content-Type: application/json');

$address = trim($_POST['address'] ?? $_GET['address'] ?? '');
if ($address === '') {
    echo json_encode(['success' => false, 'message' => 'No address provided']);
    exit;
}

$query = http_build_query([
    'q' => $address,
    'format' => 'json',
    'addressdetails' => 1,
    'limit' => 1,
    'accept-language' => 'en'
]);

$url = 'https://nominatim.openstreetmap.org/search?' . $query;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 8);
curl_setopt($ch, CURLOPT_USERAGENT, 'WDParcelSendReceiverPWA/1.0 (+https://example.com)');

$resp = curl_exec($ch);
if ($resp === false) {
    echo json_encode(['success' => false, 'message' => 'Failed to contact Nominatim']);
    exit;
}

$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code < 200 || $code >= 300) {
    echo json_encode(['success' => false, 'message' => 'Nominatim returned HTTP ' . $code]);
    exit;
}

$data = json_decode($resp, true);
if (!is_array($data) || count($data) === 0) {
    echo json_encode(['success' => false, 'message' => 'No results from Nominatim']);
    exit;
}

$best = $data[0];
$addr = $best['address'] ?? [];

$result = [
    'success' => true,
    'display_name' => $best['display_name'] ?? '',
    'address_line1' => '',
    'city' => '',
    'state' => '',
    'postal_code' => '',
    'country' => ''
];

// Build address_line1: house_number + road or pedestrian or footway
$parts = [];
if (!empty($addr['house_number'])) $parts[] = $addr['house_number'];
if (!empty($addr['road'])) $parts[] = $addr['road'];
elseif (!empty($addr['pedestrian'])) $parts[] = $addr['pedestrian'];
elseif (!empty($addr['footway'])) $parts[] = $addr['footway'];
elseif (!empty($addr['path'])) $parts[] = $addr['path'];
elseif (!empty($addr['residential'])) $parts[] = $addr['residential'];

if ($parts) $result['address_line1'] = implode(' ', $parts);
elseif (!empty($addr['building'])) $result['address_line1'] = $addr['building'];
elseif (!empty($addr['address29'])) $result['address_line1'] = $addr['address29'] ?? '';

// City detection
if (!empty($addr['city'])) $result['city'] = $addr['city'];
elseif (!empty($addr['town'])) $result['city'] = $addr['town'];
elseif (!empty($addr['village'])) $result['city'] = $addr['village'];
elseif (!empty($addr['hamlet'])) $result['city'] = $addr['hamlet'];

// State / province
if (!empty($addr['state'])) $result['state'] = $addr['state'];
elseif (!empty($addr['county'])) $result['state'] = $addr['county'];

// Postal code
if (!empty($addr['postcode'])) $result['postal_code'] = $addr['postcode'];

// Country
if (!empty($addr['country'])) $result['country'] = $addr['country'];

echo json_encode($result);

?>