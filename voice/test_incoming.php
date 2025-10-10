<?php
// Test incoming call simulation
header('Content-Type: text/xml');

// Log test call
$test_entry = [
    'timestamp' => date('c'),
    'type' => 'test_call',
    'call_sid' => 'TEST_' . uniqid(),
    'from' => '+19041234567',
    'message' => 'Test call simulation'
];

file_put_contents(__DIR__ . '/voice.log', json_encode($test_entry) . "\n", FILE_APPEND);

$xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Response>
    <Say voice="alice">This is a test of the Mechanic Saint Augustine voice system.</Say>
    <Record timeout="10" transcribe="true" recordingStatusCallback="https://mechanicstaugustine.com/voice/recording_callback.php"/>
</Response>
XML;

echo $xml;
