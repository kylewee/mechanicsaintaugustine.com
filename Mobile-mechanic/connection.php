<?php
// Use environment variables for database credentials
// SECURITY: Never use root user with no password in production!
$servername = getenv('MM_DB_HOST') ?: 'localhost';
$username = getenv('MM_DB_USER') ?: 'root';
$password = getenv('MM_DB_PASSWORD') ?: '';
$dbname = getenv('MM_DB_NAME') ?: 'mm';

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    // Don't expose connection details in production
    error_log("Database connection failed: " . $conn->connect_error);
    die("Database connection failed. Please contact support.");
}

// Set charset to prevent SQL injection via character encoding
$conn->set_charset("utf8mb4");
?>