<?php
// Detailed company reports stats endpoint
ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// During local development, enable display of errors to help debugging
$isLocalhostDisplay = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1']);
if ($isLocalhostDisplay) {
    ini_set('display_errors', '1');
}

require_once __DIR__ . '/supabase-client.php';
require_once __DIR__ . '/curl-helper.php';
require_once __DIR__ . '/error-handler.php';

class CompanyReportsStatsAPI {
    private $supabase;

    public function __construct() {
        $this->supabase = new SupabaseClient();
    }

    public function handleRequest() {
        try {
            // Initialize secure session via session-helper
            require_once __DIR__ . '/session-helper.php';
            SessionHelper::initializeSecureSession();

            if ($_SERVER['REQUEST_METHOD'] !== 'GET') throw new Exception('Method not allowed', 405);
            if (!isset($_SESSION['id'])) throw new Exception('Company ID not found in session', 401);

            $companyId = $_SESSION['id'];
            $accessToken = $_SESSION['access_token'] ?? null;
            $refreshToken = $_SESSION['refresh_token'] ?? null;
            if (!$companyId) throw new Exception('Not authenticated', 401);

            // Read filters from query string
            $timePeriod = $_GET['timePeriod'] ?? ($_GET['timeperiod'] ?? 'week');
            $startDate = $_GET['startDate'] ?? null;
            $endDate = $_GET['endDate'] ?? null;
            $outletFilter = $_GET['outletFilter'] ?? ($_GET['outlet'] ?? null);
            $driverFilter = $_GET['driverFilter'] ?? ($_GET['driver'] ?? null);

            $filters = [];
            if ($startDate) $filters['start_date'] = $startDate;
            if ($endDate) $filters['end_date'] = $endDate;
            if ($outletFilter && $outletFilter !== 'all') $filters['outlet_id'] = $outletFilter;
            if ($driverFilter) $filters['driver_id'] = $driverFilter;

            // token refresh helper
            $supabase = $this->supabase;
            $refreshFn = function($refreshToken) use ($supabase) {
                $refreshUrl = $supabase->getUrl() . '/auth/v1/token?grant_type=refresh_token';
                $headers = ['apikey: ' . $supabase->getKey(), 'Content-Type: application/json'];
                $payload = json_encode(['refresh_token' => $refreshToken]);
                try {
                    $response = CurlHelper::post($refreshUrl, $payload, $headers);
                    $result = json_decode($response, true);
                    if (is_array($result) && isset($result['access_token'])) { $_SESSION['access_token'] = $result['access_token']; return $result['access_token']; }
                } catch (Exception $ex) {
                    error_log('fetch_company_reports_stats.php: token refresh via CurlHelper failed: ' . $ex->getMessage());
                }
                return null;
            };

            // attempt wrapper to retry once on 401/JWT expired
            $attemptSupabaseCall = function($callable) use (&$accessToken, $refreshFn, $refreshToken) {
                try { return $callable($accessToken); }
                catch (Exception $e) {
                    $msg = $e->getMessage();
                    if (stripos($msg, '401') !== false || stripos($msg, 'jwt expired') !== false || stripos($msg, 'JWT expired') !== false) {
                        if ($refreshToken) {
                            $newAccess = $refreshFn($refreshToken);
                            if ($newAccess) { $accessToken = $newAccess; return $callable($accessToken); }
                        }
                        throw new Exception('Session expired. Please log in again.', 401);
                    }
                    throw $e;
                }
            };

            // Fetch core records (try authenticated then service-role fallback)
            try {
                $outlets = [];
                try {
                    $outlets = $attemptSupabaseCall(function($token) use ($companyId) { return $this->supabase->getCompanyOutlets($companyId, $token); });
                } catch (Exception $e) { error_log("Outlets fetch failed: " . $e->getMessage()); }

                $drivers = [];
                try {
                    $drivers = $attemptSupabaseCall(function($token) use ($companyId) { return $this->supabase->getCompanyDrivers($companyId, $token); });
                } catch (Exception $e) { error_log("Drivers fetch failed: " . $e->getMessage()); }

                $deliveries = [];
                try {
                    $deliveries = $attemptSupabaseCall(function($token) use ($companyId) { return $this->supabase->getDeliveries($companyId, $token, []); });
                } catch (Exception $e) { 
                    error_log("Deliveries fetch failed (probably missing table): " . $e->getMessage());
                    // Try fallback to parcels if deliveries table is missing
                     try {
                        $deliveries = $attemptSupabaseCall(function($token) use ($companyId) {
                             $pData = $this->supabase->getParcels($companyId, $token, []); 
                             // Map parcels to delivery structure if needed, or just use as is if compatible
                             return $pData;
                        });
                    } catch (Exception $e2) { error_log("Deliveries fallback failed: " . $e2->getMessage()); }
                }

                $parcels = [];
                try {
                    $parcels = $attemptSupabaseCall(function($token) use ($companyId) { return $this->supabase->getParcels($companyId, $token, []); });
                } catch (Exception $e) { error_log("Parcels fetch failed: " . $e->getMessage()); }

                // Fetch company revenue directly from companies table
                $companyRevenue = 0.0;
                try {
                    $companyData = $attemptSupabaseCall(function($token) use ($companyId) {
                        return $this->supabase->getWithToken("companies?id=eq.{$companyId}&select=revenue", $token);
                    });
                    if (is_array($companyData) && isset($companyData[0]['revenue'])) {
                        $companyRevenue = floatval($companyData[0]['revenue']);
                    } elseif (is_object($companyData) && isset($companyData->data) && isset($companyData->data[0]['revenue'])) {
                        $companyRevenue = floatval($companyData->data[0]['revenue']);
                    }
                } catch (Exception $e) {
                    error_log("Company revenue fetch failed: " . $e->getMessage());
                }
            } catch (Exception $e) {
                file_put_contents(__DIR__ . '/debug_revenue_fatal.log', "Fatal error in main fetch block: " . $e->getMessage());
                // Fallback logic for really catastrophic failures (e.g. auth completely broken)
                // ... ensure we have variables defined ...
                // fallback to service-role getRecord to ensure we can still build stats
                try {
                        $outletsResult = $this->supabase->getRecord("outlets?company_id=eq.{$companyId}&select=*");
                        $driversResult = $this->supabase->getRecord("drivers?company_id=eq.{$companyId}&select=*");
                        // Try to fetch deliveries table; if the project uses 'parcels' instead, fallback to parcels for delivery-like data
                        try {
                            $deliveriesResult = $this->supabase->getRecord("deliveries?company_id=eq.{$companyId}&select=created_at,delivered_at,driver_id,outlet_id,status", true);
                        } catch (Exception $x) {
                            error_log('Reports API - deliveries table fetch failed, falling back to parcels for delivery-like data: ' . $x->getMessage());
                            // parcels table uses origin_outlet_id / destination_outlet_id instead of outlet_id
                            $deliveriesResult = $this->supabase->getRecord("parcels?company_id=eq.{$companyId}&select=created_at,delivered_at,driver_id,origin_outlet_id,status", true);
                        }
                        // Fetch payment_transactions for revenue (fallback)
                        try {
                             $paymentTransactions = $attemptSupabaseCall(function($token) use ($companyId) {
                                return $this->supabase->getWithToken("payment_transactions?company_id=eq.{$companyId}&select=net_amount,status", $token);
                            });
                        } catch (Exception $ptx) {
                            error_log('Reports API - payment_transactions fetch failed: ' . $ptx->getMessage());
                            $paymentTransactions = [];
                        }
                        try {
                            $parcelsResult = $this->supabase->getRecord("parcels?company_id=eq.{$companyId}&select=delivery_fee,declared_value,status,created_at,delivered_at,origin_outlet_id,outlet_id", true);
                        } catch (Exception $pe) {
                            error_log('Reports API - parcels select with outlet_id failed, retrying without outlet_id: ' . $pe->getMessage());
                            try {
                                $parcelsResult = $this->supabase->getRecord("parcels?company_id=eq.{$companyId}&select=delivery_fee,declared_value,status,created_at,delivered_at,origin_outlet_id", true);
                            } catch (Exception $pe2) {
                                error_log('Reports API - parcels select without outlet_id also failed, falling back to minimal select: ' . $pe2->getMessage());
                                // final fallback: minimal fields (no outlet ids)
                                $parcelsResult = $this->supabase->getRecord("parcels?company_id=eq.{$companyId}&select=delivery_fee,declared_value,status,created_at,delivered_at", true);
                            }
                        }

                        $outlets = is_object($outletsResult) && isset($outletsResult->data) ? $outletsResult->data : ($outletsResult ?? []);
                        $drivers = is_object($driversResult) && isset($driversResult->data) ? $driversResult->data : ($driversResult ?? []);
                        $deliveries = is_object($deliveriesResult) && isset($deliveriesResult->data) ? $deliveriesResult->data : ($deliveriesResult ?? []);
                        $parcels = is_object($parcelsResult) && isset($parcelsResult->data) ? $parcelsResult->data : ($parcelsResult ?? []);
                } catch (Exception $e2) {
                    throw new Exception('Failed to fetch data for reports: ' . $e2->getMessage());
                }
            }

            // Normalize arrays
            $outletsArr = is_array($outlets) ? $outlets : (is_object($outlets) && isset($outlets->data) ? $outlets->data : []);
            $driversArr = is_array($drivers) ? $drivers : (is_object($drivers) && isset($drivers->data) ? $drivers->data : []);
            $deliveriesArr = is_array($deliveries) ? $deliveries : (is_object($deliveries) && isset($deliveries->data) ? $deliveries->data : []);
            $parcelsArr = is_array($parcels) ? $parcels : (is_object($parcels) && isset($parcels->data) ? $parcels->data : []);

            // Counts
            $active_outlets = count($outletsArr);
            $active_drivers = count($driversArr);
            
            // Total deliveries: count parcels with status = 'Delivered'
            $total_deliveries = 0;
            $deliveredParcelIds = [];
            foreach ($parcelsArr as $p) {
                $status = isset($p['status']) ? strtolower($p['status']) : '';
                if ($status === 'delivered') {
                    $total_deliveries++;
                    $parcelId = $p['id'] ?? ($p->id ?? null);
                    if ($parcelId) $deliveredParcelIds[(string)$parcelId] = true;
                }
            }

            // In-progress deliveries (status not delivered/cancelled)
            $in_progress = 0;
            foreach ($deliveriesArr as $d) {
                $status = isset($d['status']) ? strtolower($d['status']) : '';
                if ($status !== 'delivered' && $status !== 'cancelled' && $status !== '') $in_progress++;
            }

            // Total revenue: use company revenue from companies table
            $total_revenue = $companyRevenue ?? 0.0;

            // Average delivery time (in minutes) computed from deliveries delivered_at - created_at
            $totalMinutes = 0.0; $deliveredCount = 0;
            foreach ($deliveriesArr as $d) {
                $created = $d['created_at'] ?? ($d['createdAt'] ?? null);
                $delivered = $d['delivered_at'] ?? ($d['deliveredAt'] ?? null);
                if ($created && $delivered) {
                    $t1 = strtotime($created); $t2 = strtotime($delivered);
                    if ($t1 && $t2 && $t2 > $t1) { $totalMinutes += (($t2 - $t1) / 60.0); $deliveredCount++; }
                }
            }
            $avg_delivery_time = $deliveredCount > 0 ? round($totalMinutes / $deliveredCount, 2) : null;

            // Top outlets by delivery count (simple PHP aggregation)
            $outletCounts = [];
            foreach ($deliveriesArr as $d) {
                // support multiple possible key names from different deployments: origin_outlet_id, originOutletId, outlet_id, outletId
                $oid = $d['origin_outlet_id'] ?? ($d['originOutletId'] ?? ($d['outlet_id'] ?? ($d['outletId'] ?? null)));
                if ($oid) { $k = (string)$oid; if (!isset($outletCounts[$k])) $outletCounts[$k] = 0; $outletCounts[$k]++; }
            }
            arsort($outletCounts);
            $top_outlets = [];
            $i = 0;
            foreach ($outletCounts as $oid => $cnt) {
                if ($i++ >= 5) break;
                // try to get outlet name
                $name = null;
                foreach ($outletsArr as $o) { if ((string)($o['id'] ?? $o->id ?? '') === (string)$oid) { $name = $o['name'] ?? $o['outlet_name'] ?? $o->outlet_name ?? $o['name'] ?? null; break; } }
                $top_outlets[] = ['origin_outlet_id' => $oid, 'name' => $name ?: $oid, 'deliveries' => $cnt];
            }

                    // Compute revenue per outlet from parcels using ONLY delivery_fee/deliveryFee
                    $revenueByOutlet = [];
                    $revenueKeys = ['delivery_fee', 'deliveryFee'];
                    foreach ($parcelsArr as $p) {
                        // determine outlet id for the parcel (origin preferred)
                        $poid = $p['origin_outlet_id'] ?? ($p['originOutletId'] ?? ($p['outlet_id'] ?? ($p['outletId'] ?? null)));
                        if (empty($poid)) continue;
                        $val = null;
                        foreach ($revenueKeys as $k) {
                            if (is_array($p) && array_key_exists($k, $p) && $p[$k] !== null && $p[$k] !== '') { $val = $p[$k]; break; }
                            if (is_object($p) && property_exists($p, $k) && $p->$k !== null && $p->$k !== '') { $val = $p->$k; break; }
                        }
                        if (is_numeric($val)) {
                            $k = (string)$poid;
                            if (!isset($revenueByOutlet[$k])) $revenueByOutlet[$k] = 0.0;
                            $revenueByOutlet[$k] += floatval($val);
                        }
                    }

                    // Attach revenue to top_outlets entries
                    foreach ($top_outlets as $idx => $to) {
                        $key = (string)($to['origin_outlet_id'] ?? $to['id'] ?? '');
                        $top_outlets[$idx]['revenue'] = isset($revenueByOutlet[$key]) ? round($revenueByOutlet[$key], 2) : 0.0;
                    }

                    // Build revenue breakdown grouped by service type (delivery_option)
                    $serviceAgg = [];
                    foreach ($parcelsArr as $p) {
                        // support multiple field names for the service type
                        $svc = null;
                        if (is_array($p)) {
                            $svc = $p['delivery_option'] ?? $p['deliveryOption'] ?? $p['service_type'] ?? $p['serviceType'] ?? null;
                        } elseif (is_object($p)) {
                            if (property_exists($p, 'delivery_option')) $svc = $p->delivery_option;
                            elseif (property_exists($p, 'deliveryOption')) $svc = $p->deliveryOption;
                            elseif (property_exists($p, 'service_type')) $svc = $p->service_type;
                            elseif (property_exists($p, 'serviceType')) $svc = $p->serviceType;
                        }
                        $svc = $svc ?: 'Unknown';

                        // revenue for this parcel (only delivery_fee/deliveryFee)
                        $val = null;
                        foreach ($revenueKeys as $k) {
                            if (is_array($p) && array_key_exists($k, $p) && $p[$k] !== null && $p[$k] !== '') { $val = $p[$k]; break; }
                            if (is_object($p) && property_exists($p, $k) && $p->$k !== null && $p->$k !== '') { $val = $p->$k; break; }
                        }

                        if (!isset($serviceAgg[$svc])) $serviceAgg[$svc] = ['deliveries' => 0, 'revenue' => 0.0];
                        $serviceAgg[$svc]['deliveries']++;
                        if (is_numeric($val)) $serviceAgg[$svc]['revenue'] += floatval($val);
                    }

                    $revenue_breakdown = [];
                    foreach ($serviceAgg as $svc => $info) {
                        $avg_price = $info['deliveries'] > 0 ? round($info['revenue'] / $info['deliveries'], 2) : null;
                        $percent = $total_revenue > 0 ? round(($info['revenue'] / $total_revenue) * 100, 1) : null;
                        $revenue_breakdown[] = [
                            'service_type' => $svc,
                            'deliveries' => $info['deliveries'],
                            'revenue' => round($info['revenue'], 2),
                            'avg_price' => $avg_price,
                            'percent' => $percent
                        ];
                    }

                    // sort by revenue desc
                    usort($revenue_breakdown, function($a, $b) { return ($b['revenue'] <=> $a['revenue']); });

            // Top drivers by delivery count
            $driverCounts = [];
            foreach ($deliveriesArr as $d) {
                $did = $d['driver_id'] ?? ($d['driverId'] ?? null);
                if ($did) { $k = (string)$did; if (!isset($driverCounts[$k])) $driverCounts[$k] = 0; $driverCounts[$k]++; }
            }
            arsort($driverCounts);
            $top_drivers = [];
            $i = 0;
            foreach ($driverCounts as $did => $cnt) {
                if ($i++ >= 5) break;
                $name = null;
                foreach ($driversArr as $dr) { if ((string)($dr['id'] ?? $dr->id ?? '') === (string)$did) { $name = $dr['driver_name'] ?? $dr['name'] ?? $dr->full_name ?? null; break; } }
                $top_drivers[] = ['driver_id' => $did, 'name' => $name ?: $did, 'deliveries' => $cnt];
            }
            // Ensure we always provide a revenue_breakdown (compute if not yet built)
            if (!isset($revenue_breakdown)) {
                $serviceAgg = [];
                foreach ($parcelsArr as $p) {
                    $svc = null;
                    if (is_array($p)) {
                        $svc = $p['delivery_option'] ?? $p['deliveryOption'] ?? $p['service_type'] ?? $p['serviceType'] ?? null;
                    } elseif (is_object($p)) {
                        if (property_exists($p, 'delivery_option')) $svc = $p->delivery_option;
                        elseif (property_exists($p, 'deliveryOption')) $svc = $p->deliveryOption;
                        elseif (property_exists($p, 'service_type')) $svc = $p->service_type;
                        elseif (property_exists($p, 'serviceType')) $svc = $p->serviceType;
                    }
                    $svc = $svc ?: 'Unknown';
                    $val = null;
                    foreach ($revenueKeys as $k) {
                        if (is_array($p) && array_key_exists($k, $p) && $p[$k] !== null && $p[$k] !== '') { $val = $p[$k]; break; }
                        if (is_object($p) && property_exists($p, $k) && $p->$k !== null && $p->$k !== '') { $val = $p->$k; break; }
                    }
                    if (!isset($serviceAgg[$svc])) $serviceAgg[$svc] = ['deliveries' => 0, 'revenue' => 0.0];
                    $serviceAgg[$svc]['deliveries']++;
                    if (is_numeric($val)) $serviceAgg[$svc]['revenue'] += floatval($val);
                }
                $revenue_breakdown = [];
                foreach ($serviceAgg as $svc => $info) {
                    $avg_price = $info['deliveries'] > 0 ? round($info['revenue'] / $info['deliveries'], 2) : null;
                    $percent = $total_revenue > 0 ? round(($info['revenue'] / $total_revenue) * 100, 1) : null;
                    $revenue_breakdown[] = [
                        'service_type' => $svc,
                        'deliveries' => $info['deliveries'],
                        'revenue' => round($info['revenue'], 2),
                        'avg_price' => $avg_price,
                        'percent' => $percent
                    ];
                }
                usort($revenue_breakdown, function($a, $b) { return ($b['revenue'] <=> $a['revenue']); });
            }

            $data = [
                'active_outlets' => $active_outlets,
                'active_drivers' => $active_drivers,
                'total_deliveries' => $total_deliveries,
                'deliveries_in_progress' => $in_progress,
                'total_revenue' => round($total_revenue, 2),
                'avg_delivery_time' => $avg_delivery_time,
                'top_outlets' => $top_outlets,
                'top_drivers' => $top_drivers,
                'revenue_breakdown' => $revenue_breakdown ?? []
            ];

            // Attach currency (prefer session value, otherwise try to fetch the company record)
            $currency = $_SESSION['company_currency'] ?? null;
            if (empty($currency) && isset($_SESSION['id'])) {
                try {
                    $companyRec = $this->supabase->getCompany($_SESSION['id'], $_SESSION['access_token'] ?? null);
                    if (is_array($companyRec) && isset($companyRec[0]['currency'])) $currency = $companyRec[0]['currency'];
                    elseif (is_object($companyRec) && isset($companyRec->data) && is_array($companyRec->data) && isset($companyRec->data[0]['currency'])) $currency = $companyRec->data[0]['currency'];
                    if (!empty($currency)) $_SESSION['company_currency'] = $currency;
                } catch (Exception $e) { /* ignore */ }
            }
            $data['currency'] = $currency ?? 'USD';

            echo json_encode(['success' => true, 'data' => $data]);

        } catch (Exception $e) {
            ErrorHandler::handleException($e, 'fetch_company_reports_stats.php');
        }
    }
}

$api = new CompanyReportsStatsAPI();
$api->handleRequest();
