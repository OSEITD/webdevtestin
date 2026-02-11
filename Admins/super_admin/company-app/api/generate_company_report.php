<?php
// Generate company report as PDF (uses mPDF when available)
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Cache-Control: no-cache, must-revalidate');

require_once __DIR__ . '/supabase-client.php';

// Start session with same cookie params as other endpoints
$isLocalhost = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1']);
$cookieParams = [ 'lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax' ];
if (!$isLocalhost) { $cookieParams['secure'] = true; $cookieParams['domain'] = '.' . $_SERVER['HTTP_HOST']; }
session_set_cookie_params($cookieParams);
if (session_status() === PHP_SESSION_NONE) session_start();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Method not allowed', 405);
    if (!isset($_SESSION['id'])) throw new Exception('Not authenticated', 401);

    $companyId = $_SESSION['id'];
    $accessToken = $_SESSION['access_token'] ?? null;

    // read filters from POST
    $body = $_POST ?: json_decode(file_get_contents('php://input'), true) ?: [];
    $timePeriod = $body['timePeriod'] ?? 'week';
    $startDate = $body['startDate'] ?? null;
    $endDate = $body['endDate'] ?? null;
    $outletFilter = $body['outletFilter'] ?? null;

    // Build filters for SupabaseClient
    // If start/end not provided, derive them from timePeriod
    $filters = [];
    // Helper to compute start/end dates for common periods
    $computeRange = function($period) {
        $now = new DateTimeImmutable('now');
        $tz = $now->getTimezone();
        switch (strtolower($period)) {
            case 'today':
                $start = $now->setTime(0,0,0);
                $end = $now->setTime(23,59,59);
                break;
            case 'week':
                // ISO week: Monday as first day
                $start = $now->modify('monday this week')->setTime(0,0,0);
                $end = $start->modify('+6 days')->setTime(23,59,59);
                break;
            case 'month':
                $start = $now->modify('first day of this month')->setTime(0,0,0);
                $end = $now->modify('last day of this month')->setTime(23,59,59);
                break;
            case 'quarter':
                $m = (int)$now->format('n');
                $q = intval(floor(($m - 1) / 3));
                $startMonth = $q * 3 + 1;
                $start = (new DateTimeImmutable("{$now->format('Y')}-" . str_pad($startMonth,2,'0',STR_PAD_LEFT) . '-01'))->setTime(0,0,0);
                $end = $start->modify('+2 months')->modify('last day of')->setTime(23,59,59);
                break;
            case 'year':
                $start = new DateTimeImmutable($now->format('Y') . '-01-01'); $start = $start->setTime(0,0,0);
                $end = new DateTimeImmutable($now->format('Y') . '-12-31'); $end = $end->setTime(23,59,59);
                break;
            default:
                return [null, null];
        }
        return [$start->format('Y-m-d'), $end->format('Y-m-d')];
    };

    if (empty($startDate) && empty($endDate) && !empty($timePeriod)) {
        [$sd, $ed] = $computeRange($timePeriod);
        if ($sd) $startDate = $sd;
        if ($ed) $endDate = $ed;
    }

    if ($startDate) $filters['start_date'] = $startDate;
    if ($endDate) $filters['end_date'] = $endDate;
    if ($outletFilter && $outletFilter !== 'all') $filters['outlet_id'] = $outletFilter;

    // Accept reportType for title/conditional rendering (defaults to Delivery Performance)
    $reportType = $body['reportType'] ?? 'delivery';

    $supabase = new SupabaseClient();

    // Try to reuse logic - fetch outlets, drivers, deliveries, parcels, and payment_transactions
    $outlets = [];
    $drivers = [];
    $deliveries = [];
    $parcels = [];
    $paymentTransactions = [];

    try {
        $outlets = $supabase->getCompanyOutlets($companyId, $accessToken);
        $drivers = $supabase->getCompanyDrivers($companyId, $accessToken);
        // Deliveries expect an 'outlet_id' filter; parcels use 'origin_outlet_id' in this schema.
        error_log('generate_company_report: delivery filters: ' . print_r($filters, true));
        $deliveries = $supabase->getDeliveries($companyId, $accessToken, $filters);

        // Prepare parcel-specific filters: map outlet_id -> origin_outlet_id to avoid referencing parcels.outlet_id
        $parcelFilters = $filters;
        if (!empty($parcelFilters['outlet_id'])) {
            $parcelFilters['origin_outlet_id'] = $parcelFilters['outlet_id'];
            unset($parcelFilters['outlet_id']);
        }
        error_log('generate_company_report: parcelFilters before getParcels: ' . print_r($parcelFilters, true));
        $parcels = $supabase->getParcels($companyId, $accessToken, $parcelFilters);

        // Fetch company revenue directly from companies table
        $companyRevenue = 0.0;
        try {
            $companyData = $supabase->getWithToken("companies?id=eq.{$companyId}&select=revenue", $accessToken);
            if (is_array($companyData) && isset($companyData[0]['revenue'])) {
                $companyRevenue = floatval($companyData[0]['revenue']);
            } elseif (is_object($companyData) && isset($companyData->data) && isset($companyData->data[0]['revenue'])) {
                $companyRevenue = floatval($companyData->data[0]['revenue']);
            }
        } catch (Exception $e) {
            error_log("Company revenue fetch failed in PDF generator: " . $e->getMessage());
        }
    } catch (Exception $e) {
        // fallback to getRecord style queries (service role via client)
        try {
            // If an outlet filter is provided, limit outlets to that one; drivers remain company-wide
            if (!empty($outletFilter) && $outletFilter !== 'all') {
                $outRec = $supabase->getRecord("outlets?company_id=eq.{$companyId}&id=eq.{$outletFilter}&select=*");
            } else {
                $outRec = $supabase->getRecord("outlets?company_id=eq.{$companyId}&select=*");
            }
            $drvRec = $supabase->getRecord("drivers?company_id=eq.{$companyId}&select=*");

            // Prepare date range query for fallback endpoints (use created_at range)
            $dateQuery = '';
            if (!empty($startDate)) {
                $dateQuery .= '&created_at=gte.' . rawurlencode($startDate . 'T00:00:00');
            }
            if (!empty($endDate)) {
                $dateQuery .= '&created_at=lte.' . rawurlencode($endDate . 'T23:59:59');
            }

            // Try to fetch deliveries; if the table doesn't exist, fall back to parcels for delivery-like data
                try {
                    // If an outlet filter is set, ask for deliveries where either origin_outlet_id or outlet_id matches
                    if (!empty($outletFilter) && $outletFilter !== 'all') {
                        // Build endpoint using explicit encoding for IDs but keep PostgREST commas/parentheses literal
                        $cid = rawurlencode($companyId);
                        $oid = rawurlencode($outletFilter);
                        $endpointDeliveries = "deliveries?company_id=eq.{$cid}&or=(origin_outlet_id.eq.{$oid},outlet_id.eq.{$oid})&select=created_at,delivered_at,driver_id,outlet_id,status" . $dateQuery;
                        error_log('generate_company_report: delivery fallback endpoint: ' . $endpointDeliveries);
                        $delRec = $supabase->getRecord($endpointDeliveries, true);
                    } else {
                        $cid = rawurlencode($companyId);
                        $endpointDeliveries = "deliveries?company_id=eq.{$cid}&select=created_at,delivered_at,driver_id,outlet_id,status" . $dateQuery;
                        error_log('generate_company_report: delivery fallback endpoint: ' . $endpointDeliveries);
                        $delRec = $supabase->getRecord($endpointDeliveries, true);
                    }
                    $deliveries = is_object($delRec) && isset($delRec->data) ? $delRec->data : ($delRec ?? []);
                } catch (Exception $delEx) {
                // deliveries table may not exist in some deployments — use parcels as the source of delivery-like records
                error_log('Report generator: deliveries fetch failed, falling back to parcels: ' . $delEx->getMessage());
                // When falling back to parcels, also respect the outlet filter if provided
                if (!empty($outletFilter) && $outletFilter !== 'all') {
                    $cid = rawurlencode($companyId);
                    $oid = rawurlencode($outletFilter);
                    $endpointParAsDel = "parcels?company_id=eq.{$cid}&or=(origin_outlet_id.eq.{$oid},outlet_id.eq.{$oid})&select=created_at,delivered_at,driver_id,origin_outlet_id,status" . $dateQuery;
                    error_log('generate_company_report: parcels-as-deliveries fallback endpoint: ' . $endpointParAsDel);
                    $parAsDel = $supabase->getRecord($endpointParAsDel, true);
                } else {
                    $cid = rawurlencode($companyId);
                    $endpointParAsDel = "parcels?company_id=eq.{$cid}&select=created_at,delivered_at,driver_id,origin_outlet_id,status" . $dateQuery;
                    error_log('generate_company_report: parcels-as-deliveries fallback endpoint: ' . $endpointParAsDel);
                    $parAsDel = $supabase->getRecord($endpointParAsDel, true);
                }
                $deliveries = is_object($parAsDel) && isset($parAsDel->data) ? $parAsDel->data : ($parAsDel ?? []);
            }

            // Fetch parcels (for revenue etc.)
            // Fetch parcels; if outletFilter is present, limit parcels to that outlet where possible
            if (!empty($outletFilter) && $outletFilter !== 'all') {
                $cid = rawurlencode($companyId);
                $oid = rawurlencode($outletFilter);
                $endpointParRec = "parcels?company_id=eq.{$cid}&or=(origin_outlet_id.eq.{$oid},outlet_id.eq.{$oid})&select=id,delivery_fee,status,created_at,delivered_at" . $dateQuery;
                error_log('generate_company_report: parcels revenue endpoint: ' . $endpointParRec);
                $parRec = $supabase->getRecord($endpointParRec, true);
            } else {
                $cid = rawurlencode($companyId);
                $endpointParRec = "parcels?company_id=eq.{$cid}&select=id,delivery_fee,status,created_at,delivered_at" . $dateQuery;
                error_log('generate_company_report: parcels revenue endpoint: ' . $endpointParRec);
                $parRec = $supabase->getRecord($endpointParRec, true);
            }

            $outlets = is_object($outRec) && isset($outRec->data) ? $outRec->data : ($outRec ?? []);
            $drivers = is_object($drvRec) && isset($drvRec->data) ? $drvRec->data : ($drvRec ?? []);
            $parcels = is_object($parRec) && isset($parRec->data) ? $parRec->data : ($parRec ?? []);
            // $deliveries already set above

            // Fetch company revenue (already fetched above, but ensure it's available in fallback)
            if (!isset($companyRevenue)) {
                $companyRevenue = 0.0;
                try {
                    $cid = rawurlencode($companyId);
                    $cRec = $supabase->getWithToken("companies?id=eq.{$cid}&select=revenue", $accessToken);
                    if (is_array($cRec) && isset($cRec[0]['revenue'])) {
                        $companyRevenue = floatval($cRec[0]['revenue']);
                    } elseif (is_object($cRec) && isset($cRec->data) && isset($cRec->data[0]['revenue'])) {
                        $companyRevenue = floatval($cRec->data[0]['revenue']);
                    }
                } catch (Exception $crEx) {
                    error_log('Report generator: company revenue fetch failed: ' . $crEx->getMessage());
                }
            }
        } catch (Exception $e2) {
            throw new Exception('Failed to fetch data for report: ' . $e2->getMessage());
        }
    }

    // Normalize arrays
    $outletsArr = is_array($outlets) ? $outlets : (is_object($outlets) && isset($outlets->data) ? $outlets->data : []);
    $driversArr = is_array($drivers) ? $drivers : (is_object($drivers) && isset($drivers->data) ? $drivers->data : []);
    $deliveriesArr = is_array($deliveries) ? $deliveries : (is_object($deliveries) && isset($deliveries->data) ? $deliveries->data : []);
    $parcelsArr = is_array($parcels) ? $parcels : (is_object($parcels) && isset($parcels->data) ? $parcels->data : []);

    // Build summary stats (similar to fetch_company_reports_stats)
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

    // Total revenue: use company revenue from companies table
    $total_revenue = $companyRevenue ?? 0.0;

    $totalMinutes = 0.0; $deliveredCount = 0;
    foreach ($deliveriesArr as $d) {
        $created = $d['created_at'] ?? $d['createdAt'] ?? null;
        $delivered = $d['delivered_at'] ?? $d['deliveredAt'] ?? null;
        if ($created && $delivered) {
            $t1 = strtotime($created); $t2 = strtotime($delivered);
            if ($t1 && $t2 && $t2 > $t1) { $totalMinutes += (($t2 - $t1) / 60.0); $deliveredCount++; }
        }
    }
    $avg_delivery_time = $deliveredCount > 0 ? round($totalMinutes / $deliveredCount, 2) : null;

    // Build a simple top outlets table
    $outletCounts = [];
    foreach ($deliveriesArr as $d) {
        $oid = $d['origin_outlet_id'] ?? $d['outlet_id'] ?? $d['outletId'] ?? null;
        if ($oid) { $k = (string)$oid; if (!isset($outletCounts[$k])) $outletCounts[$k] = 0; $outletCounts[$k]++; }
    }
    arsort($outletCounts);
    $top_outlets = [];
    $i = 0;
    foreach ($outletCounts as $oid => $cnt) {
        if ($i++ >= 10) break;
        $name = null;
        foreach ($outletsArr as $o) { if ((string)($o['id'] ?? $o->id ?? '') === (string)$oid) { $name = $o['name'] ?? $o['outlet_name'] ?? $o['outletName'] ?? null; break; } }
        $top_outlets[] = ['id' => $oid, 'name' => $name ?: $oid, 'deliveries' => $cnt];
    }

    // Compose HTML for PDF
    $logoPath = __DIR__ . '/../../assets/img/Logo.png';
    $logoUrl = null;
    if (file_exists($logoPath)) {
        // Convert image to base64 data URI for mPDF compatibility
        $imageData = base64_encode(file_get_contents($logoPath));
        $logoUrl = 'data:image/png;base64,' . $imageData;
    }

    $html = '<!doctype html><html><head><meta charset="utf-8"><style>';
    $html .= 'body{font-family:Arial,Helvetica,sans-serif;color:#111;margin:24px} .header{display:flex;align-items:center;gap:12px} .logo{height:60px} h1{font-size:20px;margin:0} .summary{display:flex;gap:12px;margin-top:12px} .card{background:#f7f7f7;padding:12px;border-radius:6px;flex:1} table{width:100%;border-collapse:collapse;margin-top:12px} th,td{border:1px solid #e1e1e1;padding:8px;text-align:left} th{background:#fafafa}';
    $html .= '</style></head><body>';
    $titleLabel = ucfirst(str_replace('_', ' ', $reportType)) . ' Report';

    // Format start/end date labels for header
    $startLabel = $startDate ? (DateTimeImmutable::createFromFormat('Y-m-d', $startDate) ? DateTimeImmutable::createFromFormat('Y-m-d', $startDate)->format('M j, Y') : htmlspecialchars($startDate)) : '—';
    $endLabel = $endDate ? (DateTimeImmutable::createFromFormat('Y-m-d', $endDate) ? DateTimeImmutable::createFromFormat('Y-m-d', $endDate)->format('M j, Y') : htmlspecialchars($endDate)) : '—';

    // Resolve company name and contact person (prefer DB fields company_name and contact_person)
    $companyName = 'Company';
    $generatedBy = 'System';
    try {
        $companyData = null;
        if ($accessToken && method_exists($supabase, 'getCompany')) {
            $companyData = $supabase->getCompany($companyId, $accessToken);
        } else {
            // select company_name and contact_person explicitly
            $companyData = $supabase->getRecord("companies?id=eq.{$companyId}&select=company_name,contact_person", true);
        }

        // companyData might be an array, object with data, or single object
        if (is_array($companyData) && isset($companyData[0])) {
            $rec = $companyData[0];
            if (!empty($rec['company_name'])) $companyName = $rec['company_name'];
            if (!empty($rec['contact_person'])) $generatedBy = $rec['contact_person'];
        } elseif (is_object($companyData) && isset($companyData->data) && isset($companyData->data[0])) {
            $rec = $companyData->data[0];
            if (is_array($rec)) {
                if (!empty($rec['company_name'])) $companyName = $rec['company_name'];
                if (!empty($rec['contact_person'])) $generatedBy = $rec['contact_person'];
            } elseif (is_object($rec)) {
                if (!empty($rec->company_name)) $companyName = $rec->company_name;
                if (!empty($rec->contact_person)) $generatedBy = $rec->contact_person;
            }
        } elseif (is_object($companyData)) {
            if (!empty($companyData->company_name)) $companyName = $companyData->company_name;
            if (!empty($companyData->contact_person)) $generatedBy = $companyData->contact_person;
        }
    } catch (Exception $cx) {
        // ignore and fallback to session
    }

    // Fallbacks: session fields
    if (empty($companyName) && !empty($_SESSION['company_name'])) $companyName = $_SESSION['company_name'];
    if (empty($generatedBy) || $generatedBy === 'System') {
        if (!empty($_SESSION['contact_person'])) $generatedBy = $_SESSION['contact_person'];
        elseif (!empty($_SESSION['full_name'])) $generatedBy = $_SESSION['full_name'];
        elseif (!empty($_SESSION['name'])) $generatedBy = $_SESSION['name'];
        elseif (!empty($_SESSION['email'])) $generatedBy = $_SESSION['email'];
    }

    // Render different layouts depending on reportType
    // Determine company currency (session preferred, otherwise from company record)
    $companyCurrency = $_SESSION['company_currency'] ?? null;
    if (empty($companyCurrency)) {
        // try to extract from $rec or $companyData
        if (isset($rec) && !empty($rec['currency'])) $companyCurrency = $rec['currency'];
        elseif (is_object($companyData) && isset($companyData->data) && isset($companyData->data[0]) && isset($companyData->data[0]['currency'])) $companyCurrency = $companyData->data[0]['currency'];
        elseif (is_object($companyData) && isset($companyData->currency)) $companyCurrency = $companyData->currency;
    }

    // Helper: format currency values using Intl if available and currency is ISO code, otherwise prefix symbol
    function format_currency($amount, $currency = null) {
        if ($amount === null || $amount === '') return '—';
        $num = (float)$amount;
        // If currency is an ISO 3-letter code and Intl is available
        if (!empty($currency) && preg_match('/^[A-Z]{3}$/', $currency) && class_exists('NumberFormatter')) {
            try {
                $fmt = new NumberFormatter( locale_get_default() ?: 'en_US', NumberFormatter::CURRENCY );
                $val = $fmt->formatCurrency($num, $currency);
                if ($val !== false) return $val;
            } catch (Exception $e) {
                // fallback below
            }
        }
        // If currency looks like a symbol (e.g. '$', '₦', '€'), prefix it
        if (!empty($currency) && mb_strlen($currency) <= 4) {
            return $currency . number_format($num, 2);
        }
        // Default: prefix with '$' and format
        return ($currency ?: '$') . number_format($num, 2);
    }
    $html .= '<div class="header">';
    if ($logoUrl) $html .= "<img class=\"logo\" src=\"{$logoUrl}\" alt=\"Logo\">";
    $html .= '<div><h1>' . htmlspecialchars($titleLabel) . '</h1>';
    $html .= '<div style="font-size:0.95em;color:#333;margin-top:6px"><strong>Company:</strong> ' . htmlspecialchars($companyName) . '</div>';
    $html .= '<div>Generated: ' . date('Y-m-d H:i:s') . ' &nbsp;&nbsp; <strong>Generated by:</strong> ' . htmlspecialchars($generatedBy) . '</div>';
    $html .= '<div style="font-size:0.95em;color:#555;margin-top:6px">';
    $html .= '<strong>Start Date:</strong> ' . $startLabel . ' &nbsp;&nbsp; <strong>End Date:</strong> ' . $endLabel;
    $html .= '</div></div></div>';

    // Default layout: delivery / overall
    if (in_array(strtolower($reportType), ['', 'delivery', 'overall', 'summary'])) {
    $html .= '<div class="summary">';
    $html .= '<div class="card"><strong>Total Deliveries</strong><div style="font-size:18px">' . number_format($total_deliveries) . '</div></div>';
    $html .= '<div class="card"><strong>Total Revenue</strong><div style="font-size:18px">' . format_currency($total_revenue, $companyCurrency) . '</div></div>';
    $html .= '<div class="card"><strong>Avg Delivery Time</strong><div style="font-size:18px">' . ($avg_delivery_time !== null ? ($avg_delivery_time . ' min') : '—') . '</div></div>';
    $html .= '<div class="card"><strong>Active Outlets</strong><div style="font-size:18px">' . number_format($active_outlets) . '</div></div>';
    $html .= '</div>';

        $html .= '<h2 style="margin-top:18px">Top Outlets</h2>';
        $html .= '<table><thead><tr><th>Outlet</th><th>Deliveries</th></tr></thead><tbody>';
        if (empty($top_outlets)) {
            $html .= '<tr><td colspan="2">No data</td></tr>';
        } else {
            foreach ($top_outlets as $o) {
                $html .= '<tr><td>' . htmlspecialchars($o['name']) . '</td><td>' . number_format($o['deliveries']) . '</td></tr>';
            }
        }
        $html .= '</tbody></table>';

        // Top Drivers table (by number of deliveries)
        $driverCountsForTop = [];
        foreach ($deliveriesArr as $d2) {
            $did = $d2['driver_id'] ?? $d2['driverId'] ?? null;
            if ($did) { $k = (string)$did; if (!isset($driverCountsForTop[$k])) $driverCountsForTop[$k] = 0; $driverCountsForTop[$k]++; }
        }
        arsort($driverCountsForTop);
        $top_drivers = [];
        $di = 0;
        foreach ($driverCountsForTop as $did => $cnt) {
            if ($di++ >= 10) break;
            // resolve name from driversArr
            $dname = $did;
            foreach ($driversArr as $dr2) {
                $dr2Id = is_array($dr2) ? ($dr2['id'] ?? null) : (is_object($dr2) ? ($dr2->id ?? null) : null);
                if ((string)$dr2Id === (string)$did) {
                    if (is_array($dr2)) {
                        $dname = $dr2['full_name'] ?? $dr2['driver_name'] ?? $dr2['name'] ?? $dr2['email'] ?? $dname;
                    } elseif (is_object($dr2)) {
                        $dname = $dr2->full_name ?? $dr2->driver_name ?? $dr2->name ?? $dr2->email ?? $dname;
                    }
                    break;
                }
            }
            $top_drivers[] = ['id' => $did, 'name' => $dname ?: $did, 'deliveries' => $cnt];
        }

        $html .= '<h2 style="margin-top:18px">Top Drivers</h2>';
        $html .= '<table><thead><tr><th>Driver</th><th>Deliveries</th></tr></thead><tbody>';
        if (empty($top_drivers)) {
            $html .= '<tr><td colspan="2">No data</td></tr>';
        } else {
            foreach ($top_drivers as $td) {
                $html .= '<tr><td>' . htmlspecialchars($td['name']) . '</td><td>' . number_format($td['deliveries']) . '</td></tr>';
            }
        }
        $html .= '</tbody></table>';

        // Deliveries sample
        $html .= '<h2 style="margin-top:18px">Deliveries</h2>';
        $html .= '<table><thead><tr><th>Created</th><th>Delivered</th><th>Driver</th><th>Status</th></tr></thead><tbody>';
        $sampleCount = 0;
        foreach ($deliveriesArr as $d) {
            if ($sampleCount++ >= 20) break;
            $created = htmlspecialchars($d['created_at'] ?? $d['createdAt'] ?? '');
            $delivered = htmlspecialchars($d['delivered_at'] ?? $d['deliveredAt'] ?? '');
            // Resolve driver name from drivers array when possible
            $driverIdVal = $d['driver_id'] ?? $d['driverId'] ?? null;
            $driverName = $driverIdVal ?: '';
            if ($driverIdVal) {
                foreach ($driversArr as $dr) {
                    // support both array and object records
                    $drId = is_array($dr) ? ($dr['id'] ?? null) : (is_object($dr) ? ($dr->id ?? null) : null);
                    if ((string)$drId === (string)$driverIdVal) {
                        if (is_array($dr)) {
                            if (!empty($dr['driver_name'])) $driverName = $dr['driver_name'];
                            elseif (!empty($dr['name'])) $driverName = $dr['name'];
                            elseif (!empty($dr['email'])) $driverName = $dr['email'];
                        } elseif (is_object($dr)) {
                            if (!empty($dr->driver_name)) $driverName = $dr->driver_name;
                            elseif (!empty($dr->name)) $driverName = $dr->name;
                            elseif (!empty($dr->email)) $driverName = $dr->email;
                        }
                        break;
                    }
                }
            }
            $driver = htmlspecialchars($driverName);
            $status = htmlspecialchars($d['status'] ?? '');
            $html .= "<tr><td>{$created}</td><td>{$delivered}</td><td>{$driver}</td><td>{$status}</td></tr>";
        }
        if ($sampleCount === 0) $html .= '<tr><td colspan="4">No deliveries</td></tr>';
        $html .= '</tbody></table>';

    } elseif (strtolower($reportType) === 'revenue') {
        // Revenue-focused layout: revenue by outlet and summary
    $html .= '<div class="summary">';
    $html .= '<div class="card"><strong>Total Revenue</strong><div style="font-size:20px">' . format_currency($total_revenue, $companyCurrency) . '</div></div>';
    $html .= '<div class="card"><strong>Total Parcels</strong><div style="font-size:20px">' . number_format(count($parcelsArr)) . '</div></div>';
    $avgParcelValue = $parcelsArr ? ($total_revenue / max(1, count($parcelsArr))) : 0.0;
    $html .= '<div class="card"><strong>Avg Parcel Value</strong><div style="font-size:18px">' . format_currency($avgParcelValue, $companyCurrency) . '</div></div>';
    $html .= '</div>';

        // Revenue by outlet (approx via top_outlets + revenue split not exact unless parcel has outlet)
        $html .= '<h2 style="margin-top:18px">Top Outlets (by deliveries)</h2>';
        $html .= '<table><thead><tr><th>Outlet</th><th>Deliveries</th></tr></thead><tbody>';
        if (empty($top_outlets)) {
            $html .= '<tr><td colspan="2">No data</td></tr>';
        } else {
            foreach ($top_outlets as $o) {
                $html .= '<tr><td>' . htmlspecialchars($o['name']) . '</td><td>' . number_format($o['deliveries']) . '</td></tr>';
            }
        }
        $html .= '</tbody></table>';

    } elseif (strtolower($reportType) === 'outlet') {
        // Outlet centric layout
        $html .= '<h2 style="margin-top:18px">Outlets Overview</h2>';
        $html .= '<table><thead><tr><th>Outlet</th><th>Deliveries</th><th>Active</th></tr></thead><tbody>';
        if (empty($outletsArr)) {
            $html .= '<tr><td colspan="3">No outlets</td></tr>';
        } else {
            foreach ($outletsArr as $o) {
                $oid = $o['id'] ?? $o->id ?? '';
                $name = $o['company_name'] ?? $o['companyName'] ?? $o['name'] ?? $o['outlet_name'] ?? $o['outletName'] ?? '';
                $cnt = $outletCounts[(string)$oid] ?? 0;
                $html .= '<tr><td>' . htmlspecialchars($name) . '</td><td>' . number_format($cnt) . '</td><td>' . (empty($o['disabled']) ? 'Yes' : 'No') . '</td></tr>';
            }
        }
        $html .= '</tbody></table>';

    } elseif (strtolower($reportType) === 'driver') {
        // Driver performance layout
        // Build driver counts
        $driverCounts = [];
        foreach ($deliveriesArr as $d) {
            $did = $d['driver_id'] ?? $d['driverId'] ?? null;
            if ($did) { $k = (string)$did; if (!isset($driverCounts[$k])) $driverCounts[$k] = 0; $driverCounts[$k]++; }
        }
        arsort($driverCounts);
        $html .= '<h2 style="margin-top:18px">Driver Performance</h2>';
        $html .= '<table><thead><tr><th>Driver</th><th>Deliveries</th></tr></thead><tbody>';
        if (empty($driverCounts)) {
            $html .= '<tr><td colspan="2">No driver activity</td></tr>';
        } else {
            $dI = 0;
            foreach ($driverCounts as $did => $cnt) {
                if ($dI++ >= 50) break;
                $name = $did;
                foreach ($driversArr as $dr) { if ((string)($dr['id'] ?? $dr->id ?? '') === (string)$did) { $name = $dr['driver_name'] ?? $dr['name'] ?? $dr['email'] ?? $name; break; } }
                $html .= '<tr><td>' . htmlspecialchars($name) . '</td><td>' . number_format($cnt) . '</td></tr>';
            }
        }
        $html .= '</tbody></table>';

    } else {
        // Unknown type: fall back to default overall layout
        $html .= '<div class="summary">';
        $html .= '<div class="card"><strong>Total Deliveries</strong><div style="font-size:18px">' . number_format($total_deliveries) . '</div></div>';
        $html .= '<div class="card"><strong>Total Revenue</strong><div style="font-size:18px">' . format_currency($total_revenue, $companyCurrency) . '</div></div>';
        $html .= '</div>';
    }

    $html .= '</body></html>';

    // Try to use mPDF if available via Composer autoload
    $composerAutoload = __DIR__ . '/../../vendor/autoload.php';
    if (file_exists($composerAutoload)) require_once $composerAutoload;

    if (class_exists('\Mpdf\Mpdf')) {
        /** @noinspection PhpUndefinedClassInspection */
        /** @var \Mpdf\Mpdf $mpdf */
        $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir()]);
        // support local image paths by converting absolute path to data URI if necessary
        $mpdf->WriteHTML($html);
        $filename = 'company-report-' . date('Ymd_His') . '.pdf';
        // Output as download
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        // Use legacy destination string 'I' (inline) to avoid referencing a non-existent class constant
        $mpdf->Output($filename, 'I');
        exit;
    }

    // Fallback: return printable HTML view
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
    exit;

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

?>
