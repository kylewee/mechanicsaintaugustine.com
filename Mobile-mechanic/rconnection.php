<?php
/**
 * Rating Database Connection
 * Uses centralized config for database credentials
 */

require_once __DIR__ . '/config.php';

// Connect to rating database
$db = new mysqli(RATING_DB_HOST, RATING_DB_USERNAME, RATING_DB_PASSWORD, RATING_DB_NAME);

if ($db->connect_errno) {
    error_log("Rating database connection failed: " . $db->connect_error);
    die("Connection failed. Please try again later.");
}

// Set charset to prevent encoding issues
$db->set_charset("utf8mb4");
?>