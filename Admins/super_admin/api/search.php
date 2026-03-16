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
        'outlets' => [],
        'drivers' => [],
        'users' => [],
        'parcels' => [],
        'pages' => []
    ];
    $total_count = 0;

    // Search companies
    $companies = callSupabase("companies?select=id,company_name,status&company_name=ilike.*" . urlencode($query) . "*&limit=3");
    if (is_array($companies)) {
        $results['companies'] = array_map(function($company) {
            return [
                'title' => $company['company_name'],
                'subtitle' => 'Status: ' . $company['status'],
                'url' => 'view-company.php?id=' . $company['id'],
                'icon' => 'building',
                'status' => $company['status']
            ];
        }, $companies);
        $total_count += count($results['companies']);
    }

    // Search outlets
    $outlets = callSupabase("outlets?select=id,outlet_name,status,address&outlet_name=ilike.*" . urlencode($query) . "*&limit=3");
    if (is_array($outlets)) {
        $results['outlets'] = array_map(function($outlet) {
            return [
                'title' => $outlet['outlet_name'],
                'subtitle' => $outlet['address'] ?? 'No address',
                'url' => 'view-outlet.php?id=' . $outlet['id'],
                'icon' => 'store',
                'status' => $outlet['status']
            ];
        }, $outlets);
        $total_count += count($results['outlets']);
    }

    // Search drivers
    $drivers = callSupabase("all_users?select=id,name,contact_email,status&role=eq.driver&or=(name.ilike.*" . urlencode($query) . "*,contact_email.ilike.*" . urlencode($query) . "*)&limit=3");
    if (is_array($drivers)) {
        $results['drivers'] = array_map(function($driver) {
            return [
                'title' => $driver['name'],
                'subtitle' => $driver['contact_email'],
                'url' => 'view-driver.php?id=' . $driver['id'],
                'icon' => 'truck',
                'status' => $driver['status']
            ];
        }, $drivers);
        $total_count += count($results['drivers']);
    }

    // Search other users (excluding drivers)
    $users = callSupabase("all_users?select=id,name,contact_email,role,status&role=neq.driver&or=(name.ilike.*" . urlencode($query) . "*,contact_email.ilike.*" . urlencode($query) . "*)&limit=3");
    if (is_array($users)) {
        $results['users'] = array_map(function($user) {
            return [
                'title' => $user['name'],
                'subtitle' => $user['role'] . ' - ' . $user['contact_email'],
                'url' => 'view-user.php?id=' . $user['id'],
                'icon' => 'user',
                'status' => $user['status']
            ];
        }, $users);
        $total_count += count($results['users']);
    }

    // Search parcels
    $parcels = callSupabase("parcels?select=id,track_number,status&track_number=ilike.*" . urlencode($query) . "*&limit=3");
    if (is_array($parcels)) {
        $results['parcels'] = array_map(function($parcel) {
            return [
                'title' => $parcel['track_number'],
                'subtitle' => 'Status: ' . $parcel['status'],
                'url' => 'view-parcel.php?id=' . $parcel['id'],
                'icon' => 'box',
                'status' => $parcel['status']
            ];
        }, $parcels);
        $total_count += count($results['parcels']);
    }

    // Search system pages
    $query_lower = strtolower($query);
    $system_pages = getSystemPages();
    $matching_pages = array_filter($system_pages, function($page) use ($query_lower) {
        return strpos(strtolower($page['title']), $query_lower) !== false ||
               strpos(strtolower($page['description']), $query_lower) !== false;
    });

    if (!empty($matching_pages)) {
        $results['pages'] = array_values(array_map(function($page) {
             return [
                 'title' => $page['title'],
                 'subtitle' => $page['description'],
                 'url' => $page['url'],
                 'icon' => $page['icon'] ?? 'file-alt'
             ];
        }, $matching_pages));
        $total_count += count($results['pages']);
    }

    echo json_encode([
        'success' => true,
        'results' => $results,
        'total' => $total_count
    ]);

} catch (Exception $e) {
    ErrorHandler::handleException($e, 'search.php');
}