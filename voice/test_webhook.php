<?php
// Test webhook simulation for voice system debugging
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Load configuration
$config_file = __DIR__ . '/../api/.env.local.php';
if (file_exists($config_file)) {
    require_once $config_file;
}

$log_file = __DIR__ . '/voice.log';

// Ensure log file exists
if (!file_exists($log_file)) {
    touch($log_file);
    chmod($log_file, 0644);
}

// Generate test call data following voice system JSON format
$test_call = [
    'timestamp' => date('c'),
    'type' => 'test_incoming_call',
    'call_sid' => 'CA_TEST_' . uniqid(),
    'from' => '+19041234567', 
    'to' => defined('TWILIO_PHONE_NUMBER') ? TWILIO_PHONE_NUMBER : '+19999999999',
    'direction' => 'inbound',
    'call_status' => 'completed',
    'source' => 'test_webhook_simulation'
];

// Write to voice.log using structured JSON logging pattern
$log_result = file_put_contents($log_file, json_encode($test_call) . "\n", FILE_APPEND | LOCK_EX);

// Generate test recording completion event
$test_recording = [
    'timestamp' => date('c'),
    'type' => 'recording_completed', 
    'call_sid' => $test_call['call_sid'],
    'recording_sid' => 'RE_TEST_' . uniqid(),
    'from' => $test_call['from'],
    'duration' => '30',
    'recording_url' => 'https://api.twilio.com/test-recording.mp3',
    'transcript' => 'Hello, I need help with my 2018 Honda Accord. The transmission is slipping.',
    'extracted_data' => [
        'first_name' => 'Test',
        'last_name' => 'Customer',
        'phone' => '+19041234567',
        'year' => '2018',
        'make' => 'Honda',
        'model' => 'Accord', 
        'notes' => 'Transmission slipping issue'
    ],
    'source' => 'test_webhook_simulation'
];

// Add recording event to log
$recording_log_result = file_put_contents($log_file, json_encode($test_recording) . "\n", FILE_APPEND | LOCK_EX);

$response = [
    'status' => ($log_result !== false && $recording_log_result !== false) ? 'success' : 'error',
    'message' => 'Test call and recording events logged',
    'log_file' => $log_file,
    'log_writable' => is_writable($log_file),
    'events_created' => [
        'incoming_call' => $log_result !== false,
        'recording_completed' => $recording_log_result !== false
    ],
    'test_data' => [
        'call' => $test_call,
        'recording' => $test_recording
    ]
];

echo json_encode($response, JSON_PRETTY_PRINT);
?>
