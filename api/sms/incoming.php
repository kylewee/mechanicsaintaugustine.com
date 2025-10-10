<?php
declare(strict_types=1);

// Twilio SMS inbound webhook handler
$envPath = dirname(__DIR__) . '/.env.local.php';
if (is_file($envPath)) {
    require $envPath;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (strcasecmp($method, 'POST') !== 0) {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(200);
    echo 'Twilio SMS webhook ready';
    return;
}

$payload = $_POST;

$entry = [
    'ts' => gmdate('c'),
    'event' => 'incoming_sms',
    'sid' => $payload['MessageSid'] ?? ($payload['SmsSid'] ?? null),
    'accountSid' => $payload['AccountSid'] ?? null,
    'from' => $payload['From'] ?? null,
    'to' => $payload['To'] ?? null,
    'body' => $payload['Body'] ?? null,
    'raw' => $payload,
];

$logFile = dirname(__DIR__) . '/sms_incoming.log';
$logLine = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($logLine !== false) {
    file_put_contents($logFile, $logLine . PHP_EOL, FILE_APPEND | LOCK_EX);
}

header('Content-Type: text/xml; charset=utf-8');
http_response_code(200);
echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<Response></Response>";
