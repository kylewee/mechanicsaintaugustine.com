<?php
// Test webhook functionality
header('Content-Type: application/json');

// Simulate a recording callback
$test_data = [
    'timestamp' => date('c'),
    'type' => 'recording_completed',
    'call_sid' => 'CAtest123',
    'recording_sid' => 'REtest123',
    'from' => '+19041234567',
    'duration' => '45',
    'recording_url' => 'https://api.twilio.com/test-recording.mp3',
    'transcript' => 'Hello, I need help with my 2015 Honda Civic. The starter is not working.',
    'extracted_data' => [
        'first_name' => 'Test',
        'last_name' => 'Customer',
        'phone' => '+19041234567',
        'year' => '2015',
        'make' => 'Honda', 
        'model' => 'Civic',
        'notes' => 'Starter not working'
    ]
];

// Add to voice log
file_put_contents(__DIR__ . '/voice.log', json_encode($test_data) . "\n", FILE_APPEND);

echo json_encode(['status' => 'Test entry added to voice log', 'data' => $test_data], JSON_PRETTY_PRINT);
