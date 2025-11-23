<?php
/**
 * Health Check Endpoint
 *
 * Provides system health status for monitoring and load balancers
 */

header('Content-Type: application/json');

// Initialize
require_once __DIR__ . '/lib/autoload.php';

// Load configuration
if (file_exists(__DIR__ . '/Mobile-mechanic/config.php')) {
    require_once __DIR__ . '/Mobile-mechanic/config.php';
}
if (file_exists(__DIR__ . '/api/.env.local.php')) {
    require_once __DIR__ . '/api/.env.local.php';
}

$health = [
    'status' => 'healthy',
    'timestamp' => date('c'),
    'checks' => []
];

$has_errors = false;

// Check 1: Database connectivity (main)
try {
    $db = Database::getInstance('main');
    $db->query('SELECT 1');
    $health['checks']['database_main'] = [
        'status' => 'healthy',
        'message' => 'Connected'
    ];
} catch (Exception $e) {
    $has_errors = true;
    $health['checks']['database_main'] = [
        'status' => 'unhealthy',
        'message' => 'Connection failed'
    ];
}

// Check 2: Database connectivity (rating)
try {
    $db = Database::getInstance('rating');
    $db->query('SELECT 1');
    $health['checks']['database_rating'] = [
        'status' => 'healthy',
        'message' => 'Connected'
    ];
} catch (Exception $e) {
    $has_errors = true;
    $health['checks']['database_rating'] = [
        'status' => 'unhealthy',
        'message' => 'Connection failed'
    ];
}

// Check 3: CRM database connectivity
try {
    $db = Database::getInstance('crm');
    $db->query('SELECT 1');
    $health['checks']['database_crm'] = [
        'status' => 'healthy',
        'message' => 'Connected'
    ];
} catch (Exception $e) {
    // CRM might not be set up, treat as warning
    $health['checks']['database_crm'] = [
        'status' => 'warning',
        'message' => 'Not configured or connection failed'
    ];
}

// Check 4: Required environment variables
$required_env = [
    'TWILIO_ACCOUNT_SID',
    'TWILIO_AUTH_TOKEN',
    'OPENAI_API_KEY'
];

$missing_env = [];
foreach ($required_env as $var) {
    if (empty(getenv($var))) {
        $missing_env[] = $var;
    }
}

if (empty($missing_env)) {
    $health['checks']['environment'] = [
        'status' => 'healthy',
        'message' => 'All required variables set'
    ];
} else {
    $has_errors = true;
    $health['checks']['environment'] = [
        'status' => 'unhealthy',
        'message' => 'Missing variables: ' . implode(', ', $missing_env)
    ];
}

// Check 5: File system permissions
$writable_dirs = [
    __DIR__ . '/logs',
    __DIR__ . '/api',
    __DIR__ . '/quote',
    __DIR__ . '/voice'
];

$permission_issues = [];
foreach ($writable_dirs as $dir) {
    if (is_dir($dir) && !is_writable($dir)) {
        $permission_issues[] = $dir;
    }
}

if (empty($permission_issues)) {
    $health['checks']['file_permissions'] = [
        'status' => 'healthy',
        'message' => 'All directories writable'
    ];
} else {
    $has_errors = true;
    $health['checks']['file_permissions'] = [
        'status' => 'unhealthy',
        'message' => 'Not writable: ' . implode(', ', $permission_issues)
    ];
}

// Check 6: PHP configuration
$php_checks = [
    'version' => version_compare(PHP_VERSION, '7.4.0', '>='),
    'pdo' => extension_loaded('pdo'),
    'pdo_mysql' => extension_loaded('pdo_mysql'),
    'curl' => extension_loaded('curl'),
    'json' => extension_loaded('json'),
    'mbstring' => extension_loaded('mbstring')
];

$php_issues = [];
foreach ($php_checks as $check => $passed) {
    if (!$passed) {
        $php_issues[] = $check;
    }
}

if (empty($php_issues)) {
    $health['checks']['php'] = [
        'status' => 'healthy',
        'message' => 'PHP ' . PHP_VERSION . ' - All extensions loaded',
        'version' => PHP_VERSION
    ];
} else {
    $has_errors = true;
    $health['checks']['php'] = [
        'status' => 'unhealthy',
        'message' => 'Issues: ' . implode(', ', $php_issues),
        'version' => PHP_VERSION
    ];
}

// Set overall status
if ($has_errors) {
    $health['status'] = 'unhealthy';
    http_response_code(503); // Service Unavailable
} else {
    $health['status'] = 'healthy';
    http_response_code(200);
}

// Output health check results
echo json_encode($health, JSON_PRETTY_PRINT);
