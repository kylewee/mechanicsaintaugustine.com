<?php

//database_connection.php
// SECURITY: Use environment variables for database credentials

try {
    $db_host = getenv('MM_DB_HOST') ?: 'localhost';
    $db_name = getenv('MM_DB_NAME') ?: 'mm';
    $db_user = getenv('MM_DB_USER') ?: 'root';
    $db_pass = getenv('MM_DB_PASSWORD') ?: '';

    $dsn = "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4";
    $connect = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false, // Use real prepared statements
    ]);
} catch(PDOException $e) {
    // Don't expose connection details in production
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed. Please contact support.");
}

?>