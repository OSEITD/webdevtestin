<?php
// Centralized API initialization
require_once __DIR__ . '/init.php';

// Verify authentication and role
ErrorHandler::requireAuth('search.php');
ErrorHandler::requireMethod('GET', 'search.php');

if ($_SESSION['role'] !== 'super_admin') {
    ErrorHandler::logError("Unauthorized access attempt (wrong role: {$_SESSION['role']})", 'search.php');
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'You do not have permission to access this resource.']);
    exit;
}

require_once 'supabase-client.php';
require_once 'pages-data.php';

// Get search query from request
$query = $_GET['q'] ?? '';
if (empty($query)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request. Please check your input and try again.']);
    exit;
}

try {
    $results = [
        'companies' => [],
        'users' => [],
        'parcels' => [],
        'pages' => []
    ];

    // Search companies
    $companies = callSupabase("companies?select=id,company_name,status&company_name=ilike.*" . urlencode($query) . "*&limit=5");
    if (is_array($companies)) {
        $results['companies'] = array_map(function($company) {
            return [
                'id' => $company['id'],
                'name' => $company['company_name'],
                'status' => $company['status'],
                'type' => 'company'
            ];
        }, $companies);
    }

    // Search users
    $users = callSupabase("all_users?select=id,name,contact_email,role&or=(name.ilike.*" . urlencode($query) . "*,contact_email.ilike.*" . urlencode($query) . "*)&limit=5");
    if (is_array($users)) {
        $results['users'] = array_map(function($user) {
            return [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['contact_email'],
                'role' => $user['role'],
                'type' => 'user'
            ];
        }, $users);
    }

    // Search parcels
    $parcels = callSupabase("parcels?select=id,track_number,status&track_number=ilike.*" . urlencode($query) . "*&limit=5");
    if (is_array($parcels)) {
        $results['parcels'] = array_map(function($parcel) {
            return [
                'id' => $parcel['id'],
                'tracking' => $parcel['track_number'],
                'status' => $parcel['status'],
                'type' => 'parcel'
            ];
        }, $parcels);
    }

    // Search system pages and functionalities
    $query_lower = strtolower($query);
    $system_pages = getSystemPages();
    $matching_pages = array_filter($system_pages, function($page) use ($query_lower) {
        return strpos(strtolower($page['title']), $query_lower) !== false ||
               strpos(strtolower($page['description']), $query_lower) !== false;
    });

    if (!empty($matching_pages)) {
        $results['pages'] = array_values($matching_pages);
    }

    echo json_encode([
        'success' => true,
        'results' => $results
    ]);

} catch (Exception $e) {
    ErrorHandler::handleException($e, 'search.php');
}