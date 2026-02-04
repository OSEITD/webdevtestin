<?php
// =================================================
// Bootstrap & Error Handling
// =================================================
ob_start();
ini_set('display_errors', 0);
ini_set('html_errors', 0);
error_reporting(E_ALL);

// Ensure we're sending clean JSON response
if (ob_get_length()) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
header_remove('X-Powered-By');

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/supabase-client.php'; // Fix relative path

// Force JSON responses

function sendJsonResponse($success, $data) {
    if (ob_get_length()) ob_end_clean(); // clear any stray HTML
    
    $response = [
        'success' => $success,
        'data'    => $data
    ];
    
    // Ensure JSON encoding doesn't fail silently
    $json = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        error_log('JSON encoding error: ' . json_last_error_msg());
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode([
            'success' => false,
            'data' => [
                'error' => 'Failed to encode response',
                'details' => json_last_error_msg()
            ]
        ]);
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo $json;
    }
    exit;
}

// Handle fatal errors gracefully
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("Fatal Error: " . print_r($error, true));
        if (ob_get_length()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'data'    => [
                'error'   => 'Fatal server error',
                'details' => $error['message']
            ]
        ]);
        exit;
    }
});

// Error handler (non-fatal)
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error [$errno]: $errstr in $errfile on line $errline");
    if (ob_get_length()) {
        ob_end_clean();
    }
    sendJsonResponse(false, [
        'error'   => 'Internal server error',
        'details' => $errstr
    ]);
});

// =================================================
// PDF Helper
// =================================================
function generatePDF($title, $content, $companyData = null) {
    try {
        // If content is an array, convert to professional HTML format
        if (is_array($content)) {
            $content = formatProfessionalReport($title, $content, $companyData);
        }

        // mPDF is required for PDF generation in super_admin
        if (class_exists('Mpdf\\Mpdf')) {
            $mpdf = new \Mpdf\Mpdf([
                'tempDir' => sys_get_temp_dir(),
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 10,
                'margin_bottom' => 10,
                'default_font' => 'Arial'
            ]);
            $mpdf->SetTitle($title);
            $mpdf->SetAuthor('SwiftShip Admin');
            
            $mpdf->WriteHTML($content);
            // return binary string (S = return as string)
            return $mpdf->Output('', 'S');
        }

        // If we reach here, mPDF is not available — fail loudly with guidance
        throw new Exception('mPDF library is not installed. Please run "composer require mpdf/mpdf" in the project root.');
    } catch (Exception $e) {
        error_log("PDF Generation Error: " . $e->getMessage());
        throw new Exception("Failed to generate PDF report: " . $e->getMessage());
    }
}

// Format report in professional style matching the provided template
function formatProfessionalReport($title, $data, $companyData = null) {
    $html = '<style>';
    $html .= 'body { font-family: Arial, sans-serif; color: #333; line-height: 1.4; }';
    $html .= 'h1 { font-size: 28px; color: #333; margin: 10px 0; font-weight: bold; }';
    $html .= 'h2 { font-size: 16px; color: #333; margin: 15px 0 10px 0; font-weight: bold; border-bottom: 2px solid #333; padding-bottom: 5px; }';
    $html .= '.header { margin-bottom: 15px; }';
    $html .= '.header-info { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 11px; color: #555; }';
    $html .= '.stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px; }';
    $html .= '.stat-box { background: #f5f5f5; padding: 12px; border-radius: 4px; }';
    $html .= '.stat-label { font-size: 11px; color: #666; font-weight: bold; }';
    $html .= '.stat-value { font-size: 16px; color: #333; font-weight: bold; margin-top: 5px; }';
    $html .= 'table { width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 11px; }';
    $html .= 'thead { background: #f5f5f5; }';
    $html .= 'th { padding: 8px; text-align: left; font-weight: bold; color: #333; border-bottom: 1px solid #ddd; }';
    $html .= 'td { padding: 8px; border-bottom: 1px solid #eee; }';
    $html .= 'tr:nth-child(even) { background: #fafafa; }';
    $html .= '.section { margin-bottom: 20px; page-break-inside: avoid; }';
    $html .= '.page-break { page-break-before: always; }';
    $html .= '.footer { font-size: 9px; color: #999; margin-top: 20px; text-align: center; }';
    $html .= '</style>';

    // Header section
    $html .= '<div class="header">';
    $html .= '<h1>' . htmlspecialchars($title) . '</h1>';
    
    if ($companyData && isset($companyData['company_name'])) {
        $html .= '<div class="header-info">';
        $html .= '<span><strong>Company:</strong> ' . htmlspecialchars($companyData['company_name']) . '</span>';
        $html .= '<span><strong>Generated:</strong> ' . date('Y-m-d H:i:s') . '</span>';
        if (isset($companyData['generated_by'])) {
            $html .= '<span><strong>Generated by:</strong> ' . htmlspecialchars($companyData['generated_by']) . '</span>';
        }
        $html .= '</div>';
    } else {
        $html .= '<div class="header-info" style="justify-content: flex-end;">';
        $html .= '<span><strong>Generated:</strong> ' . date('Y-m-d H:i:s') . '</span>';
        $html .= '</div>';
    }
    
    // Date range if available
    if (isset($data['statistics']['period'])) {
        $period = $data['statistics']['period'];
        $html .= '<div class="header-info">';
        $html .= '<span><strong>Start Date:</strong> ' . htmlspecialchars($period['start']) . '</span>';
        $html .= '<span><strong>End Date:</strong> ' . htmlspecialchars($period['end']) . '</span>';
        $html .= '</div>';
    }
    
    $html .= '</div>';

    // Statistics as grid boxes
    if (isset($data['statistics'])) {
        $html .= '<div class="section">';
        $html .= '<h2>Summary</h2>';
        $html .= '<div class="stats-grid">';
        
        foreach ($data['statistics'] as $key => $value) {
            if ($key === 'period' || is_array($value)) {
                continue;
            }
            $label = str_replace('_', ' ', ucwords(str_replace('_', ' ', $key)));
            $html .= '<div class="stat-box">';
            $html .= '<div class="stat-label">' . htmlspecialchars($label) . '</div>';
            $html .= '<div class="stat-value">' . htmlspecialchars((string)$value) . '</div>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        $html .= '</div>';
    }

    // Data tables
    if (isset($data['data']) && is_array($data['data']) && !empty($data['data'])) {
        $html .= '<div class="section">';
        $html .= '<h2>Detailed Records</h2>';
        $html .= formatDataAsTableHTML($data['data']);
        $html .= '</div>';
    }

    $html .= '<div class="footer">This is an automated report generated by SwiftShip Admin System</div>';
    
    return $html;
}

// Format data as HTML table
function formatDataAsTableHTML($records) {
    if (empty($records) || !is_array($records)) {
        return '<p>No records to display.</p>';
    }

    $firstRecord = reset($records);
    if (!is_array($firstRecord)) {
        return '<p>Unable to format records as table.</p>';
    }

    $headers = array_keys($firstRecord);
    
    // Limit headers for better readability
    $headers = array_slice($headers, 0, 8);

    $html = '<table>';
    
    // Table header
    $html .= '<thead><tr>';
    foreach ($headers as $header) {
        $label = str_replace('_', ' ', ucwords(str_replace('_', ' ', $header)));
        $html .= '<th>' . htmlspecialchars($label) . '</th>';
    }
    $html .= '</tr></thead>';

    // Table body
    $html .= '<tbody>';
    $rowCount = 0;
    foreach ($records as $record) {
        if ($rowCount >= 50) {
            break;
        }
        
        $html .= '<tr>';
        foreach ($headers as $header) {
            $value = $record[$header] ?? '';
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value);
            }
            $value = (string)$value;
            if (strlen($value) > 40) {
                $value = substr($value, 0, 37) . '...';
            }
            $html .= '<td>' . htmlspecialchars($value) . '</td>';
        }
        $html .= '</tr>';
        $rowCount++;
    }
    $html .= '</tbody>';
    $html .= '</table>';

    if (count($records) > 50) {
        $html .= '<p style="font-size:10px; color:#999;">Showing 50 of ' . count($records) . ' records</p>';
    }

    return $html;
}

// Helper function to format data as HTML (legacy, kept for compatibility)
function formatDataAsHTML($data) {
    return formatProfessionalReport('Report', $data);
}

// =================================================
// Main Logic
// =================================================
try {
    // Start session before any output
    session_start();

    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJsonResponse(false, ['error' => 'Invalid request method']);
    }

    // Check authentication
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
        sendJsonResponse(false, ['error' => 'Unauthorized access']);
    }

    // Read input (support form POST and JSON body)
    $rawBody = file_get_contents('php://input');
    $body = $_POST ?: ([]);
    if (empty($body) && !empty($rawBody)) {
        $decoded = json_decode($rawBody, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $body = $decoded;
        }
    }

    $reportType = $body['report_type'] ?? $body['reportType'] ?? null;
    $startDate  = $body['start_date'] ?? $body['startDate'] ?? null;
    $endDate    = $body['end_date'] ?? $body['endDate'] ?? null;

    if (!$reportType || !$startDate || !$endDate) {
        sendJsonResponse(false, ['error' => 'Missing required parameters']);
    }

    // Call the right report generator
    switch ($reportType) {
        case 'delivery':
            $reportData = generateDeliveryReport($startDate, $endDate);
            break;
        case 'revenue':
            $reportData = generateRevenueReport($startDate, $endDate);
            break;
        case 'user':
            $reportData = generateUserReport($startDate, $endDate);
            break;
        case 'outlet':
            $reportData = generateOutletReport($startDate, $endDate);
            break;
        default:
            sendJsonResponse(false, ['error' => 'Invalid report type']);
    }

    // Make PDF
    $companyData = [
        'company_name' => 'SwiftShip',
        'generated_by' => $_SESSION['email'] ?? $_SESSION['user_id'] ?? 'System'
    ];
    $pdfBinary = generatePDF(ucfirst($reportType) . ' Report', $reportData, $companyData);

    // Send PDF directly to browser without storing
    $fileName = "report_{$reportType}_" . date('Ymd_His') . '.pdf';
    
    if (ob_get_length()) ob_end_clean();
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . strlen($pdfBinary));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo $pdfBinary;
    exit;

} catch (Exception $e) {
    error_log("Report Generation Exception: " . $e->getMessage());

    // Ensure logs directory exists before attempting to write
    $logsDir = __DIR__ . '/../logs';
    if (!is_dir($logsDir)) {
        @mkdir($logsDir, 0777, true);
        @chmod($logsDir, 0777);
    }

    $logFile = $logsDir . '/report_error.log';
    // Suppress warnings here; if writing fails we'll still return JSON error to client
    @file_put_contents($logFile, date('c') . ' - ' . $e->getMessage() . PHP_EOL, FILE_APPEND);

    sendJsonResponse(false, [
        'error' => 'Failed to generate report',
        'details' => $e->getMessage()
    ]);
}

// =================================================
// Report Generation Functions
// =================================================

/**
 * Generates a delivery report for the specified date range
 * @param string $startDate Start date in ISO format
 * @param string $endDate End date in ISO format
 * @return array Report data including statistics and delivery details
 * @throws Exception if data fetch fails
 */
function generateDeliveryReport($startDate, $endDate) {
    try {
        $query = "parcels?select=*&created_at=gte.{$startDate}&created_at=lte.{$endDate}";
        $deliveries = callSupabase($query);

        if (!is_array($deliveries)) {
            throw new Exception('Failed to fetch delivery data');
        }

        // Calculate additional stats
        $completedDeliveries = array_filter($deliveries, function($delivery) {
            return isset($delivery['status']) && strtolower($delivery['status']) === 'delivered';
        });

        $stats = [
            'total_deliveries' => count($deliveries),
            'completed_deliveries' => count($completedDeliveries),
            'pending_deliveries' => count($deliveries) - count($completedDeliveries),
            'period' => [
                'start' => $startDate,
                'end' => $endDate
            ]
        ];

        return [
            'statistics' => $stats,
            'data' => $deliveries
        ];
    } catch (Exception $e) {
        error_log("Error generating delivery report: " . $e->getMessage());
        throw new Exception('Failed to generate delivery report: ' . $e->getMessage());
    }
}

function generateRevenueReport($startDate, $endDate) {
    try {
        // Fetch transactions from payment_transactions table with date range
        $query = "payment_transactions?select=*&created_at=gte.{$startDate}&created_at=lte.{$endDate}";
        $transactions = callSupabaseWithServiceKey($query, 'GET', []);

        if (!is_array($transactions)) {
            throw new Exception('Failed to fetch payment transaction data');
        }

        $total = 0;
        $transactionCount = 0;

        // Sum total_amount from all transactions
        foreach ($transactions as $transaction) {
            if (isset($transaction['total_amount']) && $transaction['total_amount'] !== null) {
                $total += floatval($transaction['total_amount']);
                $transactionCount++;
            }
        }

        // Remove Lenco-related fields from report display
        $filteredTransactions = array_map(function($t) {
            $filtered = $t;
            unset($filtered['lenco_tx_id']);
            unset($filtered['lenco_tx_ref']);
            unset($filtered['Lenco Tx Id']);
            unset($filtered['Lenco Tx Ref']);
            return $filtered;
        }, $transactions);

        $stats = [
            'total_revenue' => round($total, 2),
            'total_transactions' => count($transactions),
            'transactions_with_amount' => $transactionCount,
            'average_transaction' => $transactionCount > 0 ? round($total / $transactionCount, 2) : 0,
            'period' => [
                'start' => $startDate,
                'end' => $endDate
            ]
        ];

        return [
            'statistics' => $stats,
            'data' => $filteredTransactions
        ];
    } catch (Exception $e) {
        error_log("Error generating revenue report: " . $e->getMessage());
        throw new Exception('Failed to generate revenue report: ' . $e->getMessage());
    }
}

function generateUserReport($startDate, $endDate) {
    try {
        $query = "all_users?select=*&created_at=gte.{$startDate}&created_at=lte.{$endDate}";
        $users = callSupabase($query);

        if (!is_array($users)) {
            throw new Exception('Failed to fetch user data');
        }

        $activeUsers = array_filter($users, function($user) {
            return isset($user['status']) && strtolower($user['status']) === 'active';
        });

        $stats = [
            'total_users' => count($users),
            'active_users' => count($activeUsers),
            'inactive_users' => count($users) - count($activeUsers),
            'period' => [
                'start' => $startDate,
                'end' => $endDate
            ]
        ];

        return [
            'statistics' => $stats,
            'data' => $users
        ];
    } catch (Exception $e) {
        error_log("Error generating user report: " . $e->getMessage());
        throw new Exception('Failed to generate user report: ' . $e->getMessage());
    }
}

function generateOutletReport($startDate, $endDate) {
    try {
        $query = "outlets?select=*";
        $outlets = callSupabase($query);

        if (!is_array($outlets)) {
            throw new Exception('Failed to fetch outlet data');
        }

        $activeOutlets = array_filter($outlets, function($outlet) {
            return isset($outlet['status']) && strtolower($outlet['status']) === 'active';
        });

        // Get parcel counts for each outlet - optimized approach
        $parcelCounts = [];
        try {
            // Fetch all parcels with origin and destination outlet IDs in a single query
            $allParcelsQuery = "parcels?select=id,origin_outlet_id,destination_outlet_id";
            $allParcels = callSupabase($allParcelsQuery);
            
            // Initialize parcel counts for all outlets
            foreach ($outlets as $outlet) {
                $parcelCounts[$outlet['id']] = 0;
            }
            
            // Count parcels by outlet (both origin and destination)
            if (is_array($allParcels)) {
                foreach ($allParcels as $parcel) {
                    if (isset($parcel['origin_outlet_id']) && isset($parcelCounts[$parcel['origin_outlet_id']])) {
                        $parcelCounts[$parcel['origin_outlet_id']]++;
                    }
                    if (isset($parcel['destination_outlet_id']) && isset($parcelCounts[$parcel['destination_outlet_id']])) {
                        $parcelCounts[$parcel['destination_outlet_id']]++;
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Warning: Could not fetch parcel distribution: " . $e->getMessage());
            // Set all to 0 if parcel fetch fails
            foreach ($outlets as $outlet) {
                $parcelCounts[$outlet['id']] = 0;
            }
        }

        $stats = [
            'total_outlets' => count($outlets),
            'active_outlets' => count($activeOutlets),
            'inactive_outlets' => count($outlets) - count($activeOutlets),
            'parcel_distribution' => $parcelCounts,
            'period' => [
                'start' => $startDate,
                'end' => $endDate
            ]
        ];

        return [
            'statistics' => $stats,
            'data' => $outlets
        ];
    } catch (Exception $e) {
        error_log("Error generating outlet report: " . $e->getMessage());
        throw new Exception('Failed to generate outlet report: ' . $e->getMessage());
    }
}
