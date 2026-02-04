<?php
header('Content-Type: application/json');
$supabaseUrl = 'https://xerpchdsykqafrsxbqef.supabase.co/rest/v1/drivers';
$apiKey = '';
$bearer = '';
$opts = [
	'http' => [
		'method' => 'GET',
		'header' => "apikey: $apiKey\r\nAuthorization: Bearer $bearer\r\nContent-Type: application/json\r\n"
	]
];
$context = stream_context_create($opts);
$result = file_get_contents($supabaseUrl . '?status=eq.available', false, $context);
if ($result === false) {
	echo json_encode(['success' => false, 'error' => 'Failed to fetch drivers']);
	exit;
}
$drivers = json_decode($result, true);
if (!is_array($drivers)) {
	echo json_encode(['success' => false, 'error' => 'Invalid response from Supabase']);
	exit;
}
echo json_encode(['success' => true, 'drivers' => $drivers]);
