<?php
/**
 * Input validation helpers to prevent injection and enforce data type safety.
 * All user inputs should be validated before use in queries or output.
 */

class InputValidator {
    /**
     * Validate a UUID v4 format (standard format used in Supabase)
     * Returns the UUID if valid, null otherwise.
     * 
     * @param mixed $value
     * @return string|null
     */
    public static function validateUUID($value) {
        if (!is_string($value)) {
            return null;
        }
        // UUID v4 format: 8-4-4-4-12 hex digits
        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
        if (preg_match($pattern, $value)) {
            return strtolower($value);
        }
        return null;
    }

    /**
     * Validate a date string in YYYY-MM-DD format.
     * Returns the date if valid, null otherwise.
     * 
     * @param mixed $value
     * @return string|null
     */
    public static function validateDate($value) {
        if (!is_string($value)) {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            // Check if date is actually valid (e.g., 2025-02-30 is invalid)
            $parts = explode('-', $value);
            if (checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0])) {
                return $value;
            }
        }
        return null;
    }

    /**
     * Validate a date range filter (enum-like values)
     * Allowed: 'today', 'last7days', 'thismonth', or empty string
     * Returns the value if valid, empty string otherwise.
     * 
     * @param mixed $value
     * @return string
     */
    public static function validateDateRange($value) {
        if (!is_string($value)) {
            return '';
        }
        $allowed = ['today', 'last7days', 'thismonth', ''];
        return in_array($value, $allowed, true) ? $value : '';
    }

    /**
     * Validate a search string (limit length and characters to prevent abuse)
     * Returns sanitized search string or empty string if invalid.
     * 
     * @param mixed $value
     * @return string
     */
    public static function validateSearch($value) {
        if (!is_string($value)) {
            return '';
        }
        // Limit to 255 chars and alphanumeric + common punctuation
        $value = substr(trim($value), 0, 255);
        // Allow letters, numbers, spaces, hyphens, underscores, @, dots (for emails/tracking)
        if (preg_match('/^[a-zA-Z0-9\s\-_@.]*$/', $value)) {
            return $value;
        }
        return '';
    }

    /**
     * Validate a status enum value
     * Allowed: 'pending', 'in-transit', 'delivered', 'failed', 'returned', or empty string
     * 
     * @param mixed $value
     * @return string
     */
    public static function validateStatus($value) {
        if (!is_string($value)) {
            return '';
        }
        $allowed = ['pending', 'in-transit', 'delivered', 'failed', 'returned', ''];
        return in_array($value, $allowed, true) ? $value : '';
    }

    /**
     * Validate an array of UUIDs (for batch lookups)
     * Returns array of validated UUIDs (invalid ones filtered out).
     * 
     * @param array $values
     * @return array
     */
    public static function validateUUIDArray($values) {
        if (!is_array($values)) {
            return [];
        }
        $validated = [];
        foreach ($values as $value) {
            $uuid = self::validateUUID($value);
            if ($uuid !== null) {
                $validated[] = $uuid;
            }
        }
        return $validated;
    }
}
?>
