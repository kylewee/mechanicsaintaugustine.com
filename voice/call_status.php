<?php
declare(strict_types=1);

// Call system status checker
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Load voice system config from API layer
$config_file = __DIR__ . '/../api/.env.local.php';
if (file_exists($config_file)) {
    require_once $config_file;
}

$log_file = __DIR__ . '/voice.log';
$recordings_dir = __DIR__ . '/recordings';

$status = [
    'timestamp' => date('c'),
    'repo_source' => 'https://github.com/kylewee/idk.git',
    'system_health' => [],
    'voice_log' => [
        'path' => $log_file,
        'exists' => file_exists($log_file),
        'writable' => file_exists($log_file) && is_writable($log_file),
        'size' => file_exists($log_file) ? filesize($log_file) : 0,
        'recent_entries' => []
    ],
    'recordings_dir' => [
        'path' => $recordings_dir,
        'exists' => is_dir($recordings_dir),
        'writable' => is_dir($recordings_dir) && is_writable($recordings_dir),
        'files' => []
    ],
    'twilio_config' => [
        'phone_configured' => defined('TWILIO_PHONE_NUMBER'),
        'forward_configured' => defined('FORWARD_TO_NUMBER'),
        'transcribe_enabled' => defined('TWILIO_TRANSCRIBE_ENABLED') ? TWILIO_TRANSCRIBE_ENABLED : false,
        'webhook_urls' => [
            'incoming' => 'https://mechanicstaugustine.com/voice/incoming.php',
            'recording_callback' => 'https://mechanicstaugustine.com/voice/recording_callback.php'
        ]
    ]
];

// Check configuration status
if (!file_exists($config_file)) {
    $status['system_health'][] = 'ERROR: Configuration file missing at ' . $config_file;
} else {
    $status['system_health'][] = 'Configuration loaded successfully';
}

// Ensure voice.log exists and is writable
if (!$status['voice_log']['exists']) {
    $status['system_health'][] = 'Creating voice.log file';
    if (touch($log_file) && chmod($log_file, 0644)) {
        $status['voice_log']['exists'] = true;
        $status['voice_log']['writable'] = true;
        $status['system_health'][] = 'voice.log created successfully';
    } else {
        $status['system_health'][] = 'ERROR: Cannot create voice.log - check permissions';
    }
} else {
    $status['system_health'][] = 'voice.log exists';
    if (!$status['voice_log']['writable']) {
        $status['system_health'][] = 'ERROR: voice.log is not writable';
    }
}

// Parse recent log entries (JSON format per architecture)
if ($status['voice_log']['exists'] && $status['voice_log']['size'] > 0) {
    $content = file_get_contents($log_file);
    if (!empty(trim($content))) {
        $lines = array_filter(explode("\n", trim($content)));
        $status['voice_log']['total_lines'] = count($lines);
        
        foreach (array_slice($lines, -5) as $line) {
            $entry = json_decode($line, true);
            if ($entry) {
                $status['voice_log']['recent_entries'][] = [
                    'timestamp' => $entry['timestamp'] ?? 'unknown',
                    'type' => $entry['type'] ?? 'unknown',
                    'call_sid' => substr($entry['call_sid'] ?? '', 0, 12) . '...',
                    'from' => $entry['from'] ?? 'unknown',
                    'has_recording' => !empty($entry['recording_sid']),
                    'has_transcript' => !empty($entry['transcript']),
                    'crm_lead_created' => isset($entry['crm_result'])
                ];
            }
        }
    }
}

// Check recordings directory structure
if (!$status['recordings_dir']['exists']) {
    $status['system_health'][] = 'Creating recordings directory';
    if (mkdir($recordings_dir, 0755, true)) {
        // Create .htaccess for direct access to recordings
        file_put_contents($recordings_dir . '/.htaccess', "Options -Indexes\nRequire all granted\n");
        $status['recordings_dir']['exists'] = true;
        $status['recordings_dir']['writable'] = true;
        $status['system_health'][] = 'recordings directory created';
    } else {
        $status['system_health'][] = 'ERROR: Cannot create recordings directory';
    }
} else {
    // List local recording files
    $files = scandir($recordings_dir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'mp3') {
            $status['recordings_dir']['files'][] = [
                'filename' => $file,
                'size_bytes' => filesize($recordings_dir . '/' . $file),
                'created' => date('c', filemtime($recordings_dir . '/' . $file))
            ];
        }
    }
}

echo json_encode($status, JSON_PRETTY_PRINT);
?>
