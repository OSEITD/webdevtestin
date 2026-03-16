<?php
/**
 * Currency Settings API Endpoint
 * ==============================
 * Handles currency configuration updates for both system-wide (Admin) 
 * and per-company (Manager) overrides.
 * 
 * Endpoints:
 *   GET  /api/currency/settings     - Get current currency settings
 *   POST /api/currency/update       - Update currency (admin/manager)
 *   GET  /api/currency/company/:id  - Get company's effective currency
 *   POST /api/currency/audit-log    - Get audit history (admin only)
 */

session_start();
require_once __DIR__ . '/../api/supabase-client.php';

header('Content-Type: application/json');

// ============================================================================
// PREVENT DIRECT ACCESS - API ONLY
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ============================================================================
// ROUTE DISPATCHER
// ============================================================================

$request_method = $_SERVER['REQUEST_METHOD'];
$request_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Extract action from path or query
$action = $_GET['action'] ?? 'settings';

try {
    switch ($action) {
        case 'settings':
            if ($request_method === 'GET') {
                handleGetSettings();
            } else {
                throw new Exception('Method not allowed', 405);
            }
            break;

        case 'update':
            if ($request_method === 'POST') {
                handleUpdateCurrency();
            } else {
                throw new Exception('Method not allowed', 405);
            }
            break;

        case 'company':
            if ($request_method === 'GET') {
                handleGetCompanyCurrency();
            } else {
                throw new Exception('Method not allowed', 405);
            }
            break;

        case 'audit-log':
            if ($request_method === 'GET') {
                handleGetAuditLog();
            } else {
                throw new Exception('Method not allowed', 405);
            }
            break;

        default:
            throw new Exception('Unknown action', 400);
    }
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}


// ============================================================================
// HANDLER FUNCTIONS
// ============================================================================

/**
 * GET /api/currency/settings?action=settings
 * Return current global currency and configuration
 */
function handleGetSettings() {
    global $supabase;

    if (!isset($_SESSION['id'])) {
        throw new Exception('Unauthorized', 401);
    }

    try {
        // Fetch settings
        $settings = callSupabase('system_settings?select=global_currency,currency_config', 'GET');

        if (empty($settings)) {
            throw new Exception('Settings not found', 404);
        }

        $setting = $settings[0];

        // Parse currency config if it's JSON string
        $config = $setting['currency_config'];
        if (is_string($config)) {
            $config = json_decode($config, true);
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => [
                'global_currency' => $setting['global_currency'] ?? 'USD',
                'currency_config' => $config ?? getDefaultCurrencyConfig(),
                'timestamp' => date('c')
            ]
        ]);
    } catch (Exception $e) {
        throw new Exception('Failed to fetch settings: ' . $e->getMessage(), 500);
    }
}

/**
 * POST /api/currency/update?action=update
 * Update currency (requires admin or company manager role)
 * 
 * Request body:
 * {
 *   "type": "global" | "company",
 *   "currency_code": "USD",
 *   "company_id": "uuid" (required if type=company),
 *   "reason": "Quarterly review" (optional for audit trail)
 * }
 */
function handleUpdateCurrency() {
    global $supabase;

    if (!isset($_SESSION['id'])) {
        throw new Exception('Unauthorized', 401);
    }

    // Parse request body
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        throw new Exception('Invalid JSON body', 400);
    }

    $type = $input['type'] ?? null;
    $currencyCode = $input['currency_code'] ?? null;
    $companyId = $input['company_id'] ?? null;
    $reason = $input['reason'] ?? 'Manual update';

    // Validate currency code format (ISO 4217)
    if (!preg_match('/^[A-Z]{3}$/', $currencyCode)) {
        throw new Exception('Invalid currency code. Must be ISO 4217 format (e.g., USD, EUR, ZMW)', 400);
    }

    try {
        if ($type === 'global') {
            // Update system-wide currency (Admin only)
            updateGlobalCurrency($currencyCode, $reason);
        } elseif ($type === 'company') {
            // Update company-specific currency (Manager or Admin)
            if (!$companyId) {
                throw new Exception('company_id required for company currency update', 400);
            }
            updateCompanyCurrency($companyId, $currencyCode, $reason);
        } else {
            throw new Exception('Invalid type. Must be "global" or "company"', 400);
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => ucfirst($type) . ' currency updated to ' . $currencyCode,
            'timestamp' => date('c')
        ]);
    } catch (Exception $e) {
        throw new Exception('Currency update failed: ' . $e->getMessage(), 500);
    }
}

/**
 * Update global system currency (Admin only)
 * @private
 */
function updateGlobalCurrency($currencyCode, $reason) {
    // Check admin role
    if (($_SESSION['role'] ?? null) !== 'super_admin') {
        throw new Exception('Only admins can update system currency', 403);
    }

    // Fetch current currency for audit trail
    $settings = callSupabase('system_settings?select=global_currency', 'GET');
    $oldCurrency = $settings[0]['global_currency'] ?? 'USD';

    // Update settings
    $result = callSupabase(
        'system_settings',
        'PATCH',
        [
            'global_currency' => $currencyCode
        ]
    );

    if (empty($result)) {
        throw new Exception('Failed to update settings', 500);
    }

    // Log to audit trail
    logCurrencyChange(
        'system_currency',
        null,
        $oldCurrency,
        $currencyCode,
        $reason
    );
}

/**
 * Update company-specific currency (Manager or Admin)
 * @private
 */
function updateCompanyCurrency($companyId, $currencyCode, $reason) {
    $userId = $_SESSION['id'];
    $userRole = $_SESSION['role'] ?? null;

    // Check authorization
    $isAdmin = ($userRole === 'super_admin');
    $isManager = isCompanyManager($companyId, $userId);

    if (!$isAdmin && !$isManager) {
        throw new Exception('You do not have permission to update this company\'s currency', 403);
    }

    // Fetch current currency for audit trail
    $company = callSupabase("companies?id=eq.{$companyId}&select=currency", 'GET');
    if (empty($company)) {
        throw new Exception('Company not found', 404);
    }

    $oldCurrency = $company[0]['currency'] ?? $company[0]['global_currency'] ?? 'USD';

    // Update company
    $result = callSupabase(
        "companies?id=eq.{$companyId}",
        'PATCH',
        [
            'currency' => $currencyCode
        ]
    );

    if (empty($result)) {
        throw new Exception('Failed to update company currency', 500);
    }

    // Log to audit trail
    logCurrencyChange(
        'company_currency',
        $companyId,
        $oldCurrency,
        $currencyCode,
        $reason
    );
}

/**
 * GET /api/currency/company/:id?action=company
 * Get a company's effective currency (global override included)
 */
function handleGetCompanyCurrency() {
    $companyId = $_GET['company_id'] ?? null;

    if (!$companyId) {
        throw new Exception('company_id parameter required', 400);
    }

    try {
        $company = callSupabase("companies?id=eq.{$companyId}&select=id,currency", 'GET');

        if (empty($company)) {
            throw new Exception('Company not found', 404);
        }

        // Get effective currency (company override or global)
        $settings = callSupabase('system_settings?select=global_currency', 'GET');
        $globalCurrency = $settings[0]['global_currency'] ?? 'USD';
        $effectiveCurrency = $company[0]['currency'] ?? $globalCurrency;

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => [
                'company_id' => $companyId,
                'company_override' => $company[0]['currency'] ?? null,
                'global_currency' => $globalCurrency,
                'effective_currency' => $effectiveCurrency
            ]
        ]);
    } catch (Exception $e) {
        throw new Exception('Failed to fetch company currency: ' . $e->getMessage(), 500);
    }
}

/**
 * GET /api/currency/audit-log?action=audit-log&limit=50
 * Get currency change audit log (Admin only)
 */
function handleGetAuditLog() {
    if (($_SESSION['role'] ?? null) !== 'super_admin') {
        throw new Exception('Only admins can view audit logs', 403);
    }

    $limit = min((int)($_GET['limit'] ?? 50), 500);
    $offset = (int)($_GET['offset'] ?? 0);

    try {
        $query = "currency_audit_log?select=*&order=changed_at.desc&limit={$limit}&offset={$offset}";
        $logs = callSupabase($query, 'GET');

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $logs,
            'count' => count($logs),
            'limit' => $limit,
            'offset' => $offset
        ]);
    } catch (Exception $e) {
        throw new Exception('Failed to fetch audit logs: ' . $e->getMessage(), 500);
    }
}


// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Check if user is manager of a company
 * @private
 */
function isCompanyManager($companyId, $userId) {
    try {
        // Check if user is company manager using correct table name
        $managers = callSupabase(
            "company_managers?company_id=eq.{$companyId}&user_id=eq.{$userId}&select=id",
            'GET'
        );
        if (!empty($managers)) return true;
        
        // Also check companies table for owner
        $company = callSupabase(
            "companies?id=eq.{$companyId}&select=id",
            'GET'
        );
        return !empty($company);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Log currency change to audit trail
 * @private
 */
function logCurrencyChange($entityType, $entityId, $oldValue, $newValue, $reason) {
    try {
        // Set context for trigger
        $userId = $_SESSION['id'];

        $insertData = [
            'changed_by' => $userId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'reason' => $reason,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
        ];

        callSupabase('currency_audit_log', 'POST', $insertData);
    } catch (Exception $e) {
        // Log error but don't fail the request - audit is secondary
        error_log('Failed to log currency change: ' . $e->getMessage());
    }
}

/**
 * Get default currency configuration
 * @private
 */
function getDefaultCurrencyConfig() {
    return [
        'USD' => ['minor_units' => 2, 'symbol' => '$', 'name' => 'US Dollar'],
        'EUR' => ['minor_units' => 2, 'symbol' => '€', 'name' => 'Euro'],
        'GBP' => ['minor_units' => 2, 'symbol' => '£', 'name' => 'British Pound'],
        'ZMW' => ['minor_units' => 2, 'symbol' => 'ZK', 'name' => 'Zambian Kwacha'],
        'NGN' => ['minor_units' => 2, 'symbol' => '₦', 'name' => 'Nigerian Naira'],
        'ZAR' => ['minor_units' => 2, 'symbol' => 'R', 'name' => 'South African Rand'],
        'KES' => ['minor_units' => 2, 'symbol' => 'Ksh', 'name' => 'Kenyan Shilling'],
        'UGX' => ['minor_units' => 0, 'symbol' => 'Ush', 'name' => 'Ugandan Shilling'],
        'JPY' => ['minor_units' => 0, 'symbol' => '¥', 'name' => 'Japanese Yen']
    ];
}
