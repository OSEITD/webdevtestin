<?php
header('Content-Type: application/json');
require_once __DIR__ . '/supabase-client.php';
require_once __DIR__ . '/error-handler.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['id']) || !isset($_SESSION['access_token'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Not authenticated'
    ]);
    exit;
}

// Accept either parcel numeric `id` or `track_number` as a lookup key
if (!isset($_GET['id']) && !isset($_GET['track_number'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Parcel id or track_number is required'
    ]);
    exit;
}

try {
    $supabase = new SupabaseClient();

    // Build the Supabase URL and filters depending on provided param
    $apiEndpoint = $supabase->getUrl() . '/rest/v1/parcels';
    $filters = [];
    if (isset($_GET['id'])) {
        $filters[] = 'id=eq.' . urlencode($_GET['id']);
    }
    if (isset($_GET['track_number'])) {
        $filters[] = 'track_number=eq.' . urlencode($_GET['track_number']);
    }
    // Always restrict by company session id if available
    if (isset($_SESSION['company_id']) || isset($_SESSION['id'])) {
        $filters[] = 'company_id=eq.' . urlencode($_SESSION['company_id'] ?? $_SESSION['id']);
    }

    $apiEndpoint .= '?' . implode('&', $filters) . '&select=*';

    error_log("Fetching parcel details from: " . $apiEndpoint);

    // Initialize cURL
    $ch = curl_init($apiEndpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . $supabase->getKey(),
        'Authorization: Bearer ' . $_SESSION['access_token']
    ]);

    // Execute the request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // If token expired / unauthorized, try to refresh once if a refresh token exists
    if ($httpCode === 401) {
        error_log("Supabase returned 401 for parcel details. Attempting token refresh if available.");
        $refreshToken = $_SESSION['refresh_token'] ?? null;
        if ($refreshToken) {
            try {
                $refreshResult = $supabase->refreshToken($refreshToken);
                if (is_array($refreshResult) && isset($refreshResult['access_token'])) {
                    $_SESSION['access_token'] = $refreshResult['access_token'];
                    // retry the original request with the new token
                    $ch = curl_init($apiEndpoint);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'apikey: ' . $supabase->getKey(),
                        'Authorization: Bearer ' . $_SESSION['access_token']
                    ]);
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                }
            } catch (Exception $refreshEx) {
                error_log("Token refresh failed: " . $refreshEx->getMessage());
            }
        }
    }

    if ($httpCode !== 200) {
        error_log("Error response: " . $response);
        throw new Exception("API returned status code: " . $httpCode, $httpCode);
    }

    $parcels = json_decode($response, true);
    if ($parcels === null && json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Failed to parse JSON response");
    }

    if (empty($parcels)) {
        throw new Exception("Parcel not found", 404);
    }

    echo json_encode([
        'success' => true,
        'data' => $parcels[0]  // Return the first (and should be only) result
    ]);

} catch (Exception $e) {
    ErrorHandler::handleException($e, 'fetch_parcel_details.php');
}