<?php
/**
 * Phone Number Normalization Utility
 *
 * Consolidates phone normalization logic used across the application
 */

class PhoneNormalizer {
    /**
     * Normalize phone number to E.164 format (+1XXXXXXXXXX)
     *
     * @param string $phone Raw phone number
     * @return string|null Normalized phone number or null if invalid
     */
    public static function normalize($phone) {
        if (empty($phone)) {
            return null;
        }

        // Remove all non-numeric characters
        $digits = preg_replace('/[^0-9]/', '', $phone);

        // Handle different formats
        if (strlen($digits) === 10) {
            // Assume US number, add +1
            return '+1' . $digits;
        } elseif (strlen($digits) === 11 && substr($digits, 0, 1) === '1') {
            // Already has country code
            return '+' . $digits;
        } elseif (strlen($digits) > 10) {
            // Has country code, add +
            return '+' . $digits;
        }

        // Invalid length
        return null;
    }

    /**
     * Format phone number for display (XXX) XXX-XXXX
     *
     * @param string $phone Phone number (can be normalized or raw)
     * @return string Formatted phone number
     */
    public static function format($phone) {
        $digits = preg_replace('/[^0-9]/', '', $phone);

        // Get last 10 digits for US format
        if (strlen($digits) >= 10) {
            $last10 = substr($digits, -10);
            return sprintf(
                '(%s) %s-%s',
                substr($last10, 0, 3),
                substr($last10, 3, 3),
                substr($last10, 6, 4)
            );
        }

        return $phone; // Return as-is if can't format
    }

    /**
     * Validate phone number
     *
     * @param string $phone Phone number to validate
     * @return bool True if valid
     */
    public static function isValid($phone) {
        $normalized = self::normalize($phone);
        return $normalized !== null;
    }

    /**
     * Compare two phone numbers for equality
     *
     * @param string $phone1 First phone number
     * @param string $phone2 Second phone number
     * @return bool True if numbers are the same
     */
    public static function equals($phone1, $phone2) {
        $norm1 = self::normalize($phone1);
        $norm2 = self::normalize($phone2);
        return $norm1 !== null && $norm1 === $norm2;
    }
}
