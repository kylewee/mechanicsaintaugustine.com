<?php
/**
 * Simple PSR-4 compatible autoloader for lib/ classes
 */

spl_autoload_register(function ($class) {
    // Base directory for the namespace prefix
    $base_dir = __DIR__ . '/';

    // Map of class prefixes to directories
    $prefixes = [
        'Database\\' => 'database/',
        'Services\\' => 'services/',
        'Utils\\' => 'utils/',
    ];

    // Check each prefix
    foreach ($prefixes as $prefix => $dir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) === 0) {
            $relative_class = substr($class, $len);
            $file = $base_dir . $dir . str_replace('\\', '/', $relative_class) . '.php';
            if (file_exists($file)) {
                require $file;
                return;
            }
        }
    }

    // Try direct class name without namespace (for simple classes like Database)
    $file = $base_dir . 'database/' . $class . '.php';
    if (file_exists($file)) {
        require $file;
        return;
    }

    $file = $base_dir . 'services/' . $class . '.php';
    if (file_exists($file)) {
        require $file;
        return;
    }

    $file = $base_dir . 'utils/' . $class . '.php';
    if (file_exists($file)) {
        require $file;
        return;
    }
});
