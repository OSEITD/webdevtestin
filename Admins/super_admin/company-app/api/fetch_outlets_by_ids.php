<?php
// Simple endpoint: accepts GET `ids` as comma-separated list of outlet ids
// Returns JSON: { success: true, outlets: [ { id, outlet_name, address, ... }, ... ] }
header('Content-Type: application/json');
require_once __DIR__ . '/supabase-client.php';

try {
    $idsParam = $_GET['ids'] ?? '';
    if (empty($idsParam)) {
        echo json_encode(['success' => true, 'outlets' => []]);
        exit;
    }

    // Normalize ids
    $ids = array_values(array_filter(array_map('trim', explode(',', $idsParam))));
    if (count($ids) === 0) {
        echo json_encode(['success' => true, 'outlets' => []]);
        exit;
    }

    $client = new SupabaseClient();

    // Build PostgREST style filter: id=in.(id1,id2,...) -- assume ids are URL-safe
    $inList = implode(',', $ids);
    $query = "outlets?id=in.($inList)&select=id,outlet_name,address,latitude,longitude";

    $resp = $client->getRecord($query);

    // Normalize possible response shapes
    $outlets = [];
    if (is_array($resp)) {
        $outlets = $resp;
    } elseif (is_object($resp) && isset($resp->data) && is_array($resp->data)) {
        $outlets = $resp->data;
    }

    echo json_encode(['success' => true, 'outlets' => $outlets]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
