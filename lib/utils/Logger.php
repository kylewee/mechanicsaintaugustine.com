<?php
/**
 * Application Logger
 *
 * Provides structured logging for production monitoring
 */

class Logger {
    const LEVEL_DEBUG = 'DEBUG';
    const LEVEL_INFO = 'INFO';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_ERROR = 'ERROR';
    const LEVEL_CRITICAL = 'CRITICAL';

    private static $log_dir = null;
    private static $min_level = self::LEVEL_INFO;

    /**
     * Initialize logger
     *
     * @param string $log_dir Directory for log files
     * @param string $min_level Minimum log level
     */
    public static function init($log_dir = null, $min_level = self::LEVEL_INFO) {
        if ($log_dir === null) {
            $log_dir = __DIR__ . '/../../logs';
        }

        self::$log_dir = $log_dir;
        self::$min_level = $min_level;

        // Create log directory if it doesn't exist
        if (!is_dir(self::$log_dir)) {
            mkdir(self::$log_dir, 0755, true);
        }
    }

    /**
     * Log a message
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context data
     * @param string $category Log category/channel
     */
    private static function log($level, $message, $context = [], $category = 'app') {
        if (self::$log_dir === null) {
            self::init();
        }

        // Check if this level should be logged
        $levels = [
            self::LEVEL_DEBUG => 0,
            self::LEVEL_INFO => 1,
            self::LEVEL_WARNING => 2,
            self::LEVEL_ERROR => 3,
            self::LEVEL_CRITICAL => 4
        ];

        if ($levels[$level] < $levels[self::$min_level]) {
            return; // Skip logging this level
        }

        // Format log entry
        $timestamp = date('Y-m-d H:i:s');
        $context_str = !empty($context) ? json_encode($context) : '';

        $log_entry = sprintf(
            "[%s] [%s] [%s] %s %s\n",
            $timestamp,
            $level,
            $category,
            $message,
            $context_str
        );

        // Determine log file
        $log_file = self::$log_dir . '/' . $category . '-' . date('Y-m-d') . '.log';

        // Write to log file
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);

        // For critical errors, also log to PHP error log
        if ($level === self::LEVEL_CRITICAL || $level === self::LEVEL_ERROR) {
            error_log($message . ($context_str ? ' Context: ' . $context_str : ''));
        }
    }

    /**
     * Log debug message
     */
    public static function debug($message, $context = [], $category = 'app') {
        self::log(self::LEVEL_DEBUG, $message, $context, $category);
    }

    /**
     * Log info message
     */
    public static function info($message, $context = [], $category = 'app') {
        self::log(self::LEVEL_INFO, $message, $context, $category);
    }

    /**
     * Log warning message
     */
    public static function warning($message, $context = [], $category = 'app') {
        self::log(self::LEVEL_WARNING, $message, $context, $category);
    }

    /**
     * Log error message
     */
    public static function error($message, $context = [], $category = 'app') {
        self::log(self::LEVEL_ERROR, $message, $context, $category);
    }

    /**
     * Log critical message
     */
    public static function critical($message, $context = [], $category = 'app') {
        self::log(self::LEVEL_CRITICAL, $message, $context, $category);
    }

    /**
     * Log HTTP request
     */
    public static function logRequest($endpoint, $method = 'POST', $data = []) {
        $context = [
            'method' => $method,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'data_keys' => array_keys($data)
        ];

        self::info("Request to $endpoint", $context, 'requests');
    }

    /**
     * Log database query (for debugging)
     */
    public static function logQuery($sql, $params = [], $execution_time = null) {
        $context = [
            'sql' => $sql,
            'params' => $params
        ];

        if ($execution_time !== null) {
            $context['execution_time_ms'] = round($execution_time * 1000, 2);
        }

        self::debug("Database query executed", $context, 'database');
    }

    /**
     * Log API call (external services like Twilio, OpenAI)
     */
    public static function logApiCall($service, $endpoint, $success, $response_time = null, $error = null) {
        $context = [
            'service' => $service,
            'endpoint' => $endpoint,
            'success' => $success,
            'response_time_ms' => $response_time ? round($response_time * 1000, 2) : null
        ];

        if ($error) {
            $context['error'] = $error;
            self::error("API call failed", $context, 'api');
        } else {
            self::info("API call successful", $context, 'api');
        }
    }

    /**
     * Clean up old log files
     *
     * @param int $days_to_keep Number of days to keep logs
     */
    public static function cleanup($days_to_keep = 30) {
        if (self::$log_dir === null) {
            self::init();
        }

        $cutoff_time = time() - ($days_to_keep * 24 * 60 * 60);
        $files = glob(self::$log_dir . '/*.log');

        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoff_time) {
                unlink($file);
            }
        }
    }
}
