<?php
/**
 * Test Call Simulation
 * Simulates a complete phone call workflow including:
 * 1. Incoming call webhook
 * 2. Recording transcription
 * 3. AI extraction of customer data
 * 4. Quote intake API submission
 * 5. CRM lead creation
 */

declare(strict_types=1);

echo "=== MECHANIC SAINT AUGUSTINE - CALL SIMULATION TEST ===\n\n";

// Load environment configuration
$envFile = __DIR__ . '/api/.env.local.php';
if (is_file($envFile)) {
    require $envFile;
}

// Sample transcript from a customer call
$sampleTranscript = "Hi, my name is John Smith, and I'm calling because my 2018 Honda Accord won't start.
I'm located at 123 Main Street in Jacksonville. My phone number is 904-555-1234.
The engine just makes a clicking sound when I turn the key. I think it might be the battery or the starter.
Can you come take a look?";

echo "1. SIMULATING INCOMING CALL\n";
echo str_repeat("-", 50) . "\n";
echo "From: +19045551234\n";
echo "To: Twilio Number\n";
echo "Status: Call connected, recording started\n";
echo "Recording: Dual channel, transcription enabled\n\n";

echo "2. TRANSCRIPT RECEIVED\n";
echo str_repeat("-", 50) . "\n";
echo "Transcript:\n";
echo wordwrap($sampleTranscript, 70) . "\n\n";

echo "3. EXTRACTING CUSTOMER DATA (Pattern Matching Fallback)\n";
echo str_repeat("-", 50) . "\n";

// Simulate the extract_customer_data_patterns function
function extract_customer_info($transcript) {
    $data = [];

    // Extract name
    if (preg_match('/my name is ([A-Z][a-z]+)\s+([A-Z][a-z]+)/i', $transcript, $m)) {
        $data['first_name'] = ucfirst(strtolower($m[1]));
        $data['last_name'] = ucfirst(strtolower($m[2]));
        $data['name'] = $data['first_name'] . ' ' . $data['last_name'];
    }

    // Extract phone
    if (preg_match('/(\d{3})[-.\s]?(\d{3})[-.\s]?(\d{4})/', $transcript, $m)) {
        $data['phone'] = $m[1] . $m[2] . $m[3];
    }

    // Extract address
    if (preg_match('/(?:located at|at)\s+([^.]+?)\s+in\s+(\w+)/i', $transcript, $m)) {
        $data['address'] = trim($m[1]) . ', ' . trim($m[2]);
    }

    // Extract year
    if (preg_match('/\b(19\d{2}|20[0-2]\d)\s+([A-Z][a-z]+)\s+([A-Z][a-z]+)/i', $transcript, $m)) {
        $data['year'] = $m[1];
        $data['make'] = ucfirst(strtolower($m[2]));
        $data['model'] = ucfirst(strtolower($m[3]));
    }

    // Extract notes/problem description
    if (preg_match('/won\'t start.*?(?:I think|Can you)/is', $transcript, $m)) {
        $data['notes'] = trim(str_replace(["\n", "  "], [" ", " "], $m[0]));
    }

    return $data;
}

$extractedData = extract_customer_info($sampleTranscript);

foreach ($extractedData as $key => $value) {
    echo sprintf("%-15s: %s\n", ucwords(str_replace('_', ' ', $key)), $value);
}
echo "\n";

echo "4. PREPARING QUOTE INTAKE API REQUEST\n";
echo str_repeat("-", 50) . "\n";
echo "Endpoint: /api/quote_intake.php\n";
echo "Method: POST\n";
echo "Content-Type: application/json\n\n";

$quotePayload = [
    'first_name' => $extractedData['first_name'] ?? '',
    'last_name' => $extractedData['last_name'] ?? '',
    'phone' => $extractedData['phone'] ?? '',
    'email' => '',  // Not mentioned in call
    'address' => $extractedData['address'] ?? '',
    'year' => $extractedData['year'] ?? '',
    'make' => $extractedData['make'] ?? '',
    'model' => $extractedData['model'] ?? '',
    'notes' => $extractedData['notes'] ?? '',
    'source' => 'phone_call',
    'call_sid' => 'TEST_' . uniqid(),
];

echo "Payload:\n";
echo json_encode($quotePayload, JSON_PRETTY_PRINT) . "\n\n";

echo "5. CRM LEAD CREATION\n";
echo str_repeat("-", 50) . "\n";
echo "Entity ID: " . (defined('CRM_LEADS_ENTITY_ID') ? CRM_LEADS_ENTITY_ID : 'Not configured') . "\n";
echo "Table: app_entity_" . (defined('CRM_LEADS_ENTITY_ID') ? CRM_LEADS_ENTITY_ID : 'XX') . "\n\n";

echo "Field Mapping:\n";
if (defined('CRM_FIELD_MAP') && is_array(CRM_FIELD_MAP)) {
    foreach (CRM_FIELD_MAP as $field => $fieldId) {
        if ($fieldId > 0 && isset($quotePayload[$field]) && $quotePayload[$field]) {
            echo sprintf("  field_%d => %s: %s\n",
                $fieldId,
                $field,
                is_string($quotePayload[$field]) ? substr($quotePayload[$field], 0, 40) : $quotePayload[$field]
            );
        }
    }
} else {
    echo "  [Field map not configured]\n";
}

echo "\n6. EXPECTED CRM RECORD\n";
echo str_repeat("-", 50) . "\n";
echo "Created By: User ID " . (defined('CRM_CREATED_BY_USER_ID') ? CRM_CREATED_BY_USER_ID : 1) . "\n";
echo "Date Added: " . date('Y-m-d H:i:s') . "\n";
echo "Status: New Lead\n";
echo "Source: Phone Call (Automated Transcription)\n\n";

echo "Lead Details:\n";
echo "  Customer: {$quotePayload['first_name']} {$quotePayload['last_name']}\n";
echo "  Phone: " . (isset($quotePayload['phone']) ?
    preg_replace('/(\d{3})(\d{3})(\d{4})/', '($1) $2-$3', $quotePayload['phone']) : 'N/A') . "\n";
echo "  Vehicle: {$quotePayload['year']} {$quotePayload['make']} {$quotePayload['model']}\n";
echo "  Location: {$quotePayload['address']}\n";
echo "  Problem: " . substr($quotePayload['notes'], 0, 60) . "...\n\n";

echo "7. NEXT STEPS (AUTOMATED WORKFLOW)\n";
echo str_repeat("-", 50) . "\n";
echo "✓ Lead created in CRM\n";
echo "✓ Notification email sent to: " . implode(', ', QUOTE_NOTIFICATION_EMAILS ?? ['admin']) . "\n";
echo "✓ Recording saved with transcript\n";
echo "✓ Customer receives confirmation SMS (if configured)\n";
echo "✓ Mechanic can review lead in CRM dashboard\n\n";

echo "8. TESTING CONNECTION TO QUOTE INTAKE API\n";
echo str_repeat("-", 50) . "\n";

// Try to make actual API call to test endpoint
$testUrl = 'http://localhost:8080/api/quote_intake.php';
echo "Testing endpoint: $testUrl\n\n";

$ch = curl_init($testUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($quotePayload),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT => 5
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "Connection Error: $error\n";
    echo "(This is expected if web server is not running)\n\n";
} else {
    echo "HTTP Status: $httpCode\n";
    if ($response) {
        echo "Response:\n";
        $decoded = json_decode($response, true);
        if ($decoded) {
            echo json_encode($decoded, JSON_PRETTY_PRINT) . "\n";
        } else {
            echo $response . "\n";
        }
    }
    echo "\n";
}

echo "=== TEST SIMULATION COMPLETE ===\n\n";

echo "To make a real test with Twilio:\n";
echo "1. Set up Twilio account with phone number\n";
echo "2. Configure webhook URL: https://yourdomain.com/voice/incoming.php\n";
echo "3. Set environment variables in api/.env.local.php\n";
echo "4. Make a call to your Twilio number\n";
echo "5. System will automatically:\n";
echo "   - Forward call to mechanic\n";
echo "   - Record and transcribe conversation\n";
echo "   - Extract customer data using AI\n";
echo "   - Create lead in CRM\n";
echo "   - Send notifications\n\n";
