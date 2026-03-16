<?php
/**
 * Currency Helper Functions
 * Handles currency formatting, conversion, and management
 * Uses system_settings table for global currency and company overrides
 */

// Get the global/company currency
function getEffectiveCurrency($companyId = null) {
    try {
        $currency = null;
        
        // If company ID provided, check for company-level override
        if ($companyId) {
            try {
                $companies = callSupabase("companies?id=eq.{$companyId}&select=currency");
                if (!empty($companies) && !empty($companies[0]['currency'])) {
                    $currency = $companies[0]['currency'];
                    error_log("getEffectiveCurrency: Using company currency: " . $currency);
                    return $currency; // Return early if found
                }
            } catch (Exception $e) {
                error_log("Error fetching company currency: " . $e->getMessage());
            }
        }
        
        // If no company override, get global setting
        try {
            $settings = callSupabaseWithServiceKey('system_settings?select=global_currency&limit=1', 'GET');
            if (!empty($settings) && !empty($settings[0]['global_currency'])) {
                $globalCurrency = $settings[0]['global_currency'];
                error_log("getEffectiveCurrency: Using global currency: " . $globalCurrency);
                return $globalCurrency;
            } else {
                error_log("getEffectiveCurrency: No global currency found in system_settings, using fallback");
            }
        } catch (Exception $e) {
            error_log("Error fetching global currency: " . $e->getMessage());
        }
        
        // Final fallback to USD if everything fails
        error_log("getEffectiveCurrency: Falling back to USD");
        return 'USD';
    } catch (Exception $e) {
        error_log("getEffectiveCurrency error: " . $e->getMessage());
        return 'USD'; // fallback on error
    }
}

// Get currency decimal places
function getCurrencyDecimals($currencyCode = null) {
    $decimals = [
        'USD' => 2, 'EUR' => 2, 'GBP' => 2, 'JPY' => 0,
        'CHF' => 2, 'CAD' => 2, 'AUD' => 2, 'CNY' => 2,
        'INR' => 2, 'ZMW' => 2, 'ZAR' => 2, 'NGN' => 2,
        'KES' => 2, 'UGX' => 0, 'GHS' => 2, 'EGP' => 2
    ];
    
    if (!$currencyCode) $currencyCode = 'USD';
    return $decimals[strtoupper($currencyCode)] ?? 2;
}

// Format minor units to display currency
// If amount is 2550 and decimals is 2, displays 25.50
// If isMinorUnits is false, displays amount as-is (for values already in display format)
function formatCurrency($minorUnits, $currencyCode = null, $companyId = null, $isMinorUnits = false) {
    if ($minorUnits === null || $minorUnits === '') {
        return '—';
    }
    
    // Get currency if not provided
    // If currencyCode is explicitly empty/null, get effective currency (company override or global)
    if (!$currencyCode) {
        $currencyCode = getEffectiveCurrency($companyId);
        error_log("formatCurrency: No currency provided, resolved to: " . $currencyCode);
    }
    
    // Get decimal places
    $decimals = getCurrencyDecimals($currencyCode);
    
    // Convert from minor units to display value (only if needed)
    if ($isMinorUnits) {
        $amount = (int)$minorUnits / pow(10, $decimals);
    } else {
        // Already in display format, just use as-is
        $amount = floatval($minorUnits);
    }
    
    // Try to format with NumberFormatter if available
    if (class_exists('NumberFormatter')) {
        try {
            $fmt = new NumberFormatter(locale_get_default() ?: 'en_US', NumberFormatter::CURRENCY);
            $result = $fmt->formatCurrency($amount, $currencyCode);
            if ($result !== false) {
                error_log("formatCurrency: Using NumberFormatter with currency: " . $currencyCode);
                return $result;
            }
        } catch (Exception $e) {
            error_log("formatCurrency: NumberFormatter error: " . $e->getMessage());
            // fallback below
        }
    }
    
    // Fallback: manual formatting
    $formatted = number_format($amount, $decimals);
    
    // Add currency symbol
    $symbols = [
        'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'JPY' => '¥',
        'CHF' => 'Fr', 'CAD' => 'C$', 'AUD' => 'A$', 'CNY' => '¥',
        'INR' => '₹', 'ZMW' => 'K', 'ZAR' => 'R', 'NGN' => '₦',
        'KES' => 'KSh', 'UGX' => 'USh', 'GHS' => '₵', 'EGP' => '£'
    ];
    
    $symbol = $symbols[$currencyCode] ?? $currencyCode;
    error_log("formatCurrency: Returning formatted value with symbol: " . $symbol . ", currency: " . $currencyCode);
    return $symbol . $formatted;
}

// Parse display value to minor units
// If user enters 25.50 and currency is USD (2 decimals), returns 2550
function parseToMinorUnits($displayValue, $currencyCode = null) {
    if (!$displayValue || $displayValue === '') {
        return 0;
    }
    
    if (!$currencyCode) {
        $currencyCode = 'USD';
    }
    
    $decimals = getCurrencyDecimals($currencyCode);
    
    // Remove any currency symbols and whitespace
    $clean = preg_replace('/[^\d.,\-]/', '', $displayValue);
    
    // Remove formatting characters
    $clean = str_replace(',', '', $clean);
    
    // Convert to float
    $amount = (float)$clean;
    
    // Convert to minor units
    return (int)round($amount * pow(10, $decimals));
}

// Add data attributes for JavaScript currency formatting
function getCurrencyDataAttributes($minorUnits, $currencyCode = null, $companyId = null) {
    if (!$currencyCode) {
        $currencyCode = getEffectiveCurrency($companyId);
    }
    
    // Escape for HTML attribute
    $escapedCurrency = htmlspecialchars($currencyCode, ENT_QUOTES, 'UTF-8');
    
    return "data-currency-value=\"{$minorUnits}\" data-currency=\"{$escapedCurrency}\"";
}

// Get the current global currency from system_settings
function getGlobalCurrency() {
    try {
        $settings = callSupabase('system_settings?select=global_currency&limit=1');
        if (!empty($settings) && !empty($settings[0]['global_currency'])) {
            return $settings[0]['global_currency'];
        }
        return 'USD'; // Default fallback
    } catch (Exception $e) {
        error_log("getGlobalCurrency error: " . $e->getMessage());
        return 'USD';
    }
}

// Set the global currency in system_settings
function setGlobalCurrency($currencyCode) {
    try {
        if (!$currencyCode) {
            return false;
        }
        
        // Ensure currency code is uppercase and valid
        $currencyCode = strtoupper($currencyCode);
        
        // Check if record exists
        $existing = callSupabase('system_settings?select=id&limit=1');
        
        if (!empty($existing)) {
            // Update existing record
            global $supabaseUrl, $supabaseKey;
            $client = new SupabaseClient($supabaseUrl, $supabaseKey);
            $result = $client->patch('system_settings', json_encode(['global_currency' => $currencyCode]));
            error_log("Updated global currency to: " . $currencyCode);
            return true;
        }
        
        return false;
    } catch (Exception $e) {
        error_log("setGlobalCurrency error: " . $e->getMessage());
        return false;
    }
}
