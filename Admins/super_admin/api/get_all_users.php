<?php
header('Content-Type: application/json');
require_once __DIR__ . '/supabase-client.php';
require_once __DIR__ . '/init.php';

try {
    // Allow only GET
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit;
    }

    // Pagination params
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(100, max(10, (int)($_GET['per_page'] ?? 25)));
    $offset = ($page - 1) * $perPage;
    $search = isset($_GET['q']) ? trim($_GET['q']) : '';

    $supabase = new SupabaseClient();

    // Build select - include company name via foreign key join if companies table exists
    $select = 'id,email,full_name,first_name,last_name,phone,role,company_id,avatar_url,created_at,last_sign_in_at,metadata,is_active';

    // Build base endpoint
    $endpoint = "profiles?select={$select}&order=created_at.desc&limit={$perPage}&offset={$offset}";

    if ($search !== '') {
        // Use ilike for case-insensitive partial match on full_name or email
        $q = rawurlencode('%' . $search . '%');
        // PostgREST expects OR clause encoded; use simple approach appending or=
        // Note: ensure server PostgREST supports this encoding
        $endpoint .= "&or=(full_name.ilike.{$q},email.ilike.{$q})";
    }

    $res = $supabase->get($endpoint);

    // Normalize response: SupabaseClient->get may return object with ->data
    $rows = [];
    if (is_object($res) && isset($res->data) && is_array($res->data)) {
        $rows = $res->data;
    } elseif (is_array($res)) {
        $rows = $res;
    }

    // Get total count (using exact count header instead of downloading records)
    try {
        $countEndpoint = 'profiles';
        if ($search !== '') {
            $q = rawurlencode('%' . $search . '%');
            $countEndpoint .= "?or=(full_name.ilike.{$q},email.ilike.{$q})";
        }
        $total = $supabase->getExactCount($countEndpoint);
    } catch (Exception $e) {
        // ignore count errors
        $total = count($rows);
    }

    echo json_encode([
        'success' => true,
        'data' => $rows,
        'meta' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
