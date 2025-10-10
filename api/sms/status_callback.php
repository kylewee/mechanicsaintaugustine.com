<?php
declare(strict_types=1);

// Twilio delivery status callback handler
$envPath = dirname(__DIR__) . '/.env.local.php';
if (is_file($envPath)) {
    require $envPath;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (strcasecmp($method, 'POST') !== 0) {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(200);
    echo 'Twilio SMS status callback ready';
    return;
}

$payload = $_POST;

$entry = [
    'ts' => gmdate('c'),
    'event' => 'sms_status',
    'sid' => $payload['MessageSid'] ?? ($payload['SmsSid'] ?? null),
    'status' => $payload['MessageStatus'] ?? null,
    'accountSid' => $payload['AccountSid'] ?? null,
    'to' => $payload['To'] ?? null,
    'from' => $payload['From'] ?? null,
    'errorCode' => $payload['ErrorCode'] ?? null,
    'errorMessage' => $payload['ErrorMessage'] ?? null,
    'raw' => $payload,
];

$logFile = dirname(__DIR__) . '/sms_status.log';
$logLine = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($logLine !== false) {
    file_put_contents($logFile, $logLine . PHP_EOL, FILE_APPEND | LOCK_EX);
}

header('Content-Type: text/plain; charset=utf-8');
http_response_code(200);
echo 'OK';
