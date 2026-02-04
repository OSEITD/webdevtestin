<?php
// Disable error display in responses (prevent HTML warnings from breaking JSON)
ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

require_once __DIR__ . '/supabase-client.php';
require_once __DIR__ . '/error-handler.php';

class DashboardStatsAPI {
    private $supabase;

    public function __construct() {
        $this->supabase = new SupabaseClient();
    }

    public function handleRequest() {
        try {
            // Set session cookie params similar to other endpoints
            $isLocalhost = in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1']);
            $cookieParams = [
                'lifetime' => 0,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax'
            ];
            if (!$isLocalhost) {
                $cookieParams['secure'] = true;
                $cookieParams['domain'] = '.' . $_SERVER['HTTP_HOST'];
            }
            session_set_cookie_params($cookieParams);
            if (session_status() === PHP_SESSION_NONE) session_start();

            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                throw new Exception('Method not allowed', 405);
            }

            if (!isset($_SESSION['id'])) {
                throw new Exception('Company ID not found in session', 401);
            }

            $companyId = $_SESSION['id'];
            $accessToken = $_SESSION['access_token'] ?? null;
            $refreshToken = $_SESSION['refresh_token'] ?? null;

            if (!$companyId) {
                throw new Exception('Not authenticated', 401);
            }

            // Helper to refresh access token using refresh token
            $supabase = $this->supabase;
            $refreshFn = function($refreshToken) use ($supabase) {
                $refreshUrl = $supabase->getUrl() . '/auth/v1/token?grant_type=refresh_token';
                $headers = [
                    'apikey: ' . $supabase->getKey(),
                    'Content-Type: application/json'
                ];

                $ch = curl_init($refreshUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['refresh_token' => $refreshToken]));
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                // Gate SSL verification by APP_ENV: enforce in production, allow bypass in development
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, (getenv('APP_ENV') ?: 'production') === 'production');
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, (getenv('APP_ENV') ?: 'production') === 'production' ? 2 : 0);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode === 200) {
                    $result = json_decode($response, true);
                    if (isset($result['access_token'])) {
                        $_SESSION['access_token'] = $result['access_token'];
                        return $result['access_token'];
                    }
                }
                return null;
            };

            // Try to refresh token if we have a refresh token
            if ($refreshToken && !$accessToken) {
                $newAccess = $refreshFn($refreshToken);
                if ($newAccess) {
                    $accessToken = $newAccess;
                }
            }

            // If access token exists but may be expired, attempt refresh then proceed
            if ($refreshToken && $accessToken) {
                $newAccess = $refreshFn($refreshToken);
                if ($newAccess) {
                    $accessToken = $newAccess;
                }
            }

            if (!$accessToken) {
                throw new Exception('Access token not found or expired', 401);
            }

            // Helper to attempt a Supabase call and retry once after refreshing token on 401/JWT expired
            $attemptSupabaseCall = function($callable) use (&$accessToken, $refreshFn, $refreshToken, $companyId) {
                try {
                    return $callable($accessToken);
                } catch (Exception $e) {
                    $msg = $e->getMessage();
                    error_log("Supabase call failed: " . $msg);
                    // If it's a 401 or mentions JWT expired, try refreshing once
                    if (stripos($msg, '401') !== false || stripos($msg, 'jwt expired') !== false || stripos($msg, 'JWT expired') !== false) {
                        if ($refreshToken) {
                            error_log('Attempting token refresh due to 401/JWT expired');
                            $newAccess = $refreshFn($refreshToken);
                            if ($newAccess) {
                                $accessToken = $newAccess;
                                // Retry the call once with new access token
                                try {
                                    return $callable($accessToken);
                                } catch (Exception $e2) {
                                    error_log('Retry after refresh failed: ' . $e2->getMessage());
                                    throw new Exception('Session expired. Please log in again.', 401);
                                }
                            }
                        }
                        throw new Exception('Session expired. Please log in again.', 401);
                    }
                    throw $e;
                }
            };

            // Fetch outlets and drivers (with retry logic).
            // If user's token is expired and refresh fails, fall back to service-role queries.
            try {
                $outlets = $attemptSupabaseCall(function($token) use ($companyId) {
                    return $this->supabase->getCompanyOutlets($companyId, $token);
                });
                $drivers = $attemptSupabaseCall(function($token) use ($companyId) {
                    return $this->supabase->getCompanyDrivers($companyId, $token);
                });

                // Fetch parcels (deliveries) for the company
                $parcels = $attemptSupabaseCall(function($token) use ($companyId) {
                    return $this->supabase->getParcels($companyId, $token, []);
                });
            } catch (Exception $e) {
                // If the error indicates session expiry, attempt service-role fallback
                $msg = $e->getMessage();
                error_log('Authenticated fetch failed for dashboard stats: ' . $msg);
                // Use service role (if available) through getRecord with useServiceRole=true
                try {
                    // Fetch only minimal fields to reduce payload
                    $outletsResult = $this->supabase->getRecord("outlets?company_id=eq.{$companyId}&select=id", true);
                    $driversResult = $this->supabase->getRecord("drivers?company_id=eq.{$companyId}&select=id", true);
                    $parcelsResult = $this->supabase->getRecord("parcels?company_id=eq.{$companyId}&select=status", true);

                    $outlets = is_object($outletsResult) && isset($outletsResult->data) ? $outletsResult->data : ($outletsResult ?? []);
                    $drivers = is_object($driversResult) && isset($driversResult->data) ? $driversResult->data : ($driversResult ?? []);
                    $parcels = is_object($parcelsResult) && isset($parcelsResult->data) ? $parcelsResult->data : ($parcelsResult ?? []);
                } catch (Exception $e2) {
                    error_log('Service role fallback failed: ' . $e2->getMessage());
                    throw new Exception('Session expired. Please log in again.', 401);
                }
            }

            // Ensure arrays
            $outletsCount = is_array($outlets) ? count($outlets) : 0;
            $driversCount = is_array($drivers) ? count($drivers) : 0;
            $totalDeliveries = is_array($parcels) ? count($parcels) : 0;

            // Deliveries in progress: statuses that are not 'delivered' or 'cancelled'
            $inProgress = 0;
            if (is_array($parcels)) {
                foreach ($parcels as $p) {
                    $status = isset($p['status']) ? strtolower($p['status']) : '';
                    if ($status !== 'delivered' && $status !== 'cancelled' && $status !== '') {
                        $inProgress++;
                    }
                }
            }

            $data = [
                'active_outlets' => $outletsCount,
                'active_drivers' => $driversCount,
                'total_deliveries' => $totalDeliveries,
                'deliveries_in_progress' => $inProgress
            ];

            echo json_encode(['success' => true, 'data' => $data]);

        } catch (Exception $e) {
            ErrorHandler::handleException($e, 'fetch_dashboard_stats.php');
        }
    }
}

$api = new DashboardStatsAPI();
$api->handleRequest();
