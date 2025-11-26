<?php
/**
 * MySQLi Database Connection
 * Uses centralized config for database credentials
 */

require_once __DIR__ . '/config.php';

// Create connection
$conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die("Connection failed. Please try again later.");
}

// Set charset to prevent encoding issues
$conn->set_charset("utf8mb4");
?>