<?php
/**
 * Database Configuration
 * Loads database credentials from environment variables or uses defaults for local development
 */

// Main database configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USERNAME', getenv('DB_USERNAME') ?: 'root');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'mm');

// Rating database configuration (if different from main database)
define('RATING_DB_HOST', getenv('RATING_DB_HOST') ?: DB_HOST);
define('RATING_DB_USERNAME', getenv('RATING_DB_USERNAME') ?: DB_USERNAME);
define('RATING_DB_PASSWORD', getenv('RATING_DB_PASSWORD') ?: DB_PASSWORD);
define('RATING_DB_NAME', getenv('RATING_DB_NAME') ?: 'rating');
?>
