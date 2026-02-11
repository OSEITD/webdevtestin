<?php
// Disable error display in responses
ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

require_once __DIR__ . '/supabase-client.php';
require_once __DIR__ . '/error-handler.php';

class DashboardChartAPI {
    private $supabase;

    public function __construct() {
        $this->supabase = new SupabaseClient();
    }

    public function handleRequest() {
        try {
            // session cookie params
            $isLocalhost = in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1']);
            $cookieParams = ['lifetime'=>0,'path'=>'/','httponly'=>true,'samesite'=>'Lax'];
            if (!$isLocalhost) { $cookieParams['secure'] = true; $cookieParams['domain'] = '.' . $_SERVER['HTTP_HOST']; }
            session_set_cookie_params($cookieParams);
            if (session_status() === PHP_SESSION_NONE) session_start();

            if ($_SERVER['REQUEST_METHOD'] !== 'GET') throw new Exception('Method not allowed', 405);
            if (!isset($_SESSION['id'])) throw new Exception('Company ID not found in session', 401);

            $companyId = $_SESSION['id'];
            $accessToken = $_SESSION['access_token'] ?? null;
            $refreshToken = $_SESSION['refresh_token'] ?? null;

            $supabase = $this->supabase;

            // refresh helper (same as other endpoints)
            $refreshFn = function($refreshToken) use ($supabase) {
                $refreshUrl = $supabase->getUrl() . '/auth/v1/token?grant_type=refresh_token';
                $headers = ['apikey: ' . $supabase->getKey(), 'Content-Type: application/json'];
                $ch = curl_init($refreshUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['refresh_token'=>$refreshToken]));
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                // Gate SSL verification by APP_ENV: enforce in production, allow bypass in development
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, (getenv('APP_ENV') ?: 'production') === 'production');
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, (getenv('APP_ENV') ?: 'production') === 'production' ? 2 : 0);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($httpCode === 200) {
                    $result = json_decode($response, true);
                    if (isset($result['access_token'])) { $_SESSION['access_token'] = $result['access_token']; return $result['access_token']; }
                }
                return null;
            };

            // ensure we have an access token by attempting refresh if needed
            if ($refreshToken && !$accessToken) {
                $new = $refreshFn($refreshToken);
                if ($new) $accessToken = $new;
            }

            // Prepare 30-day date labels
            $days = 30;
            $labels = [];
            for ($i = $days-1; $i >= 0; $i--) {
                $labels[] = date('Y-m-d', strtotime("-{$i} days"));
            }

            // Read optional filters from query string
            $outletFilter = isset($_GET['outlet']) ? trim($_GET['outlet']) : null;
            $serviceFilter = isset($_GET['service']) ? trim($_GET['service']) : null;

            // Function to fetch parcels (attempt with token then fallback to service role)
            $fetchParcels = function() use ($companyId, &$accessToken, $refreshFn, $refreshToken, $outletFilter, $serviceFilter) {
                try {
                    // call supabase client
                    $filters = ['date' => date('Y-m-d', strtotime('-29 days'))];
                    if (!empty($outletFilter)) $filters['outlet_id'] = $outletFilter;
                    if (!empty($serviceFilter)) $filters['service_type'] = $serviceFilter;
                    return (new SupabaseClient())->getParcels($companyId, $accessToken, $filters);
                } catch (Exception $e) {
                    // attempt refresh
                    if ($refreshToken) {
                        $new = $refreshFn($refreshToken);
                        if ($new) {
                            $accessToken = $new;
                            $filters = ['date' => date('Y-m-d', strtotime('-29 days'))];
                            if (!empty($outletFilter)) $filters['outlet_id'] = $outletFilter;
                            if (!empty($serviceFilter)) $filters['service_type'] = $serviceFilter;
                            return (new SupabaseClient())->getParcels($companyId, $accessToken, $filters);
                        }
                    }
                    // service role fallback
                    $svc = new SupabaseClient();
                    $query = "parcels?company_id=eq.{$companyId}&select=created_at,delivery_fee,fee,amount,price";
                    if (!empty($outletFilter)) {
                        // Use origin_outlet_id since that's the correct field in parcels table
                        $query .= "&or=(origin_outlet_id.eq.{$outletFilter},outlet_id.eq.{$outletFilter})";
                    }
                    if (!empty($serviceFilter)) $query .= "&service_type=eq.{$serviceFilter}";
                    $res = $svc->getRecord($query, true);
                    if (is_object($res) && isset($res->data)) return $res->data;
                    return [];
                }
            };

            $parcels = $fetchParcels();
            if (!is_array($parcels)) $parcels = [];

            // Initialize counts and revenue maps per date
            $counts = array_fill_keys($labels, 0);
            $revenueMap = array_fill_keys($labels, 0.0);

            foreach ($parcels as $p) {
                $created = isset($p['created_at']) ? substr($p['created_at'],0,10) : null;
                if ($created && isset($counts[$created])) {
                    $counts[$created]++;

                    // Prefer exact delivery fee columns when available
                    $fee = null;
                    foreach (['delivery_fee','fee','amount','price'] as $col) {
                        if (isset($p[$col]) && $p[$col] !== null && $p[$col] !== '') {
                            // cast to float safely
                            $fee = floatval($p[$col]);
                            break;
                        }
                    }

                    // Add known fee to revenue; missing fees are treated as 0 (actual revenue)
                    if ($fee !== null) {
                        $revenueMap[$created] += $fee;
                    }
                }
            }

            $deliveries = array_values($counts);
            $revenue = array_values($revenueMap);

            echo json_encode(['success'=>true,'data'=>['labels'=>$labels,'deliveries'=>$deliveries,'revenue'=>$revenue]]);

        } catch (Exception $e) {
            ErrorHandler::handleException($e, 'fetch_dashboard_chart.php');
        }
    }
}

$api = new DashboardChartAPI();
$api->handleRequest();
