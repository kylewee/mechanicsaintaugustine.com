<?php
/**
 * Input Validation and Sanitization
 *
 * Provides secure input validation to prevent XSS, SQL injection, and other attacks
 */

class InputValidator {
    /**
     * Sanitize string input (remove HTML/PHP tags)
     *
     * @param string $input Raw input
     * @param bool $allow_html Whether to allow safe HTML tags
     * @return string Sanitized input
     */
    public static function sanitizeString($input, $allow_html = false) {
        if (empty($input)) {
            return '';
        }

        $input = trim($input);

        if ($allow_html) {
            // Allow only safe HTML tags
            $allowed_tags = '<p><br><strong><em><u><a><ul><ol><li>';
            return strip_tags($input, $allowed_tags);
        }

        return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Validate and sanitize email address
     *
     * @param string $email Email address
     * @return string|null Sanitized email or null if invalid
     */
    public static function sanitizeEmail($email) {
        if (empty($email)) {
            return null;
        }

        $email = trim(strtolower($email));
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);

        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }

        return null;
    }

    /**
     * Validate email address
     *
     * @param string $email Email to validate
     * @return bool True if valid
     */
    public static function isValidEmail($email) {
        return self::sanitizeEmail($email) !== null;
    }

    /**
     * Sanitize phone number
     *
     * @param string $phone Phone number
     * @return string|null Sanitized phone or null if invalid
     */
    public static function sanitizePhone($phone) {
        require_once __DIR__ . '/PhoneNormalizer.php';
        return PhoneNormalizer::normalize($phone);
    }

    /**
     * Sanitize integer input
     *
     * @param mixed $input Input value
     * @param int $min Minimum value (optional)
     * @param int $max Maximum value (optional)
     * @return int|null Sanitized integer or null if invalid
     */
    public static function sanitizeInt($input, $min = null, $max = null) {
        $value = filter_var($input, FILTER_VALIDATE_INT);

        if ($value === false) {
            return null;
        }

        if ($min !== null && $value < $min) {
            return null;
        }

        if ($max !== null && $value > $max) {
            return null;
        }

        return $value;
    }

    /**
     * Sanitize float input
     *
     * @param mixed $input Input value
     * @return float|null Sanitized float or null if invalid
     */
    public static function sanitizeFloat($input) {
        $value = filter_var($input, FILTER_VALIDATE_FLOAT);
        return $value !== false ? $value : null;
    }

    /**
     * Sanitize URL
     *
     * @param string $url URL to sanitize
     * @return string|null Sanitized URL or null if invalid
     */
    public static function sanitizeUrl($url) {
        if (empty($url)) {
            return null;
        }

        $url = filter_var($url, FILTER_SANITIZE_URL);

        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }

        return null;
    }

    /**
     * Validate required field
     *
     * @param mixed $value Value to check
     * @return bool True if not empty
     */
    public static function required($value) {
        if (is_string($value)) {
            return trim($value) !== '';
        }
        return !empty($value);
    }

    /**
     * Validate array of required fields in data
     *
     * @param array $data Data to validate
     * @param array $required_fields List of required field names
     * @return array Array of missing field names (empty if all present)
     */
    public static function validateRequired($data, $required_fields) {
        $missing = [];

        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || !self::required($data[$field])) {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    /**
     * Sanitize array input (sanitize each value)
     *
     * @param array $input Input array
     * @param callable $sanitizer Sanitization function
     * @return array Sanitized array
     */
    public static function sanitizeArray($input, $sanitizer = null) {
        if (!is_array($input)) {
            return [];
        }

        if ($sanitizer === null) {
            $sanitizer = [self::class, 'sanitizeString'];
        }

        return array_map($sanitizer, $input);
    }

    /**
     * Get sanitized POST data
     *
     * @param string $key POST key
     * @param mixed $default Default value if not set
     * @param string $type Data type (string, email, phone, int, float, url)
     * @return mixed Sanitized value
     */
    public static function post($key, $default = null, $type = 'string') {
        if (!isset($_POST[$key])) {
            return $default;
        }

        $value = $_POST[$key];

        switch ($type) {
            case 'email':
                return self::sanitizeEmail($value) ?? $default;
            case 'phone':
                return self::sanitizePhone($value) ?? $default;
            case 'int':
                return self::sanitizeInt($value) ?? $default;
            case 'float':
                return self::sanitizeFloat($value) ?? $default;
            case 'url':
                return self::sanitizeUrl($value) ?? $default;
            case 'string':
            default:
                return self::sanitizeString($value);
        }
    }

    /**
     * Get sanitized GET data
     *
     * @param string $key GET key
     * @param mixed $default Default value if not set
     * @param string $type Data type
     * @return mixed Sanitized value
     */
    public static function get($key, $default = null, $type = 'string') {
        if (!isset($_GET[$key])) {
            return $default;
        }

        $value = $_GET[$key];

        switch ($type) {
            case 'email':
                return self::sanitizeEmail($value) ?? $default;
            case 'phone':
                return self::sanitizePhone($value) ?? $default;
            case 'int':
                return self::sanitizeInt($value) ?? $default;
            case 'float':
                return self::sanitizeFloat($value) ?? $default;
            case 'url':
                return self::sanitizeUrl($value) ?? $default;
            case 'string':
            default:
                return self::sanitizeString($value);
        }
    }

    /**
     * Prevent CSRF attacks - validate token
     *
     * @param string $token Token to validate
     * @param string $session_key Session key where token is stored
     * @return bool True if valid
     */
    public static function validateCsrfToken($token, $session_key = 'csrf_token') {
        if (!isset($_SESSION[$session_key])) {
            return false;
        }

        return hash_equals($_SESSION[$session_key], $token);
    }

    /**
     * Generate CSRF token
     *
     * @param string $session_key Session key to store token
     * @return string Generated token
     */
    public static function generateCsrfToken($session_key = 'csrf_token') {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $token = bin2hex(random_bytes(32));
        $_SESSION[$session_key] = $token;

        return $token;
    }
}
