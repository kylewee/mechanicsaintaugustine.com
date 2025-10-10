<?php
// Generic status callback endpoint: accepts POST payloads, logs them, and optionally emails a summary.
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Callback-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    echo json_encode(['status' => 'ok']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$envFile = __DIR__ . '/../api/.env.local.php';
if (is_file($envFile)) {
    require $envFile;
}

$sharedToken = defined('STATUS_CALLBACK_TOKEN') ? trim((string)STATUS_CALLBACK_TOKEN) : '';
$providedToken = '';
if (isset($_SERVER['HTTP_X_CALLBACK_TOKEN'])) {
    $providedToken = (string)$_SERVER['HTTP_X_CALLBACK_TOKEN'];
} elseif (isset($_GET['token'])) {
    $providedToken = (string)$_GET['token'];
}

if ($sharedToken !== '') {
    if (!is_string($providedToken) || $providedToken === '' || !hash_equals($sharedToken, $providedToken)) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}

$rawBody = file_get_contents('php://input');
$payload = null;
if ($rawBody !== false && $rawBody !== '') {
    $decoded = json_decode($rawBody, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $payload = $decoded;
    } else {
        parse_str($rawBody, $formData);
        $payload = $formData ?: ['raw' => $rawBody];
    }
}

if ($payload === null) {
    $payload = [];
}

$receivedAt = date('c');
$logEntry = [
    'timestamp' => $receivedAt,
    'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
    'payload' => $payload,
    'raw' => $rawBody,
];

$logPath = __DIR__ . '/../api/status_callback.log';
@file_put_contents($logPath, json_encode($logEntry, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);

$emailResult = ['status' => 'skipped'];
if (defined('STATUS_CALLBACK_EMAILS') && STATUS_CALLBACK_EMAILS) {
    $configured = STATUS_CALLBACK_EMAILS;
    if (is_string($configured)) {
        $configured = array_map('trim', explode(',', $configured));
    }
    if (!is_array($configured)) {
        $configured = [(string)$configured];
    }

    $recipients = [];
    foreach ($configured as $email) {
        $email = trim((string)$email);
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $recipients[] = $email;
        }
    }

    if ($recipients && function_exists('mail')) {
        $subject = 'Status callback received at ' . $receivedAt;
        $lines = [];
        $lines[] = 'A status callback was received.';
        $lines[] = 'Timestamp: ' . $receivedAt;
        if (isset($logEntry['remote_addr'])) {
            $lines[] = 'Remote IP: ' . $logEntry['remote_addr'];
        }
        if (isset($logEntry['user_agent'])) {
            $lines[] = 'User Agent: ' . $logEntry['user_agent'];
        }
        $lines[] = '';
        $lines[] = 'Payload:';
        $lines[] = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $body = implode("\n", $lines);

        $headers = "Content-Type: text/plain; charset=UTF-8";
        if (defined('STATUS_CALLBACK_EMAIL_FROM') && STATUS_CALLBACK_EMAIL_FROM && filter_var(STATUS_CALLBACK_EMAIL_FROM, FILTER_VALIDATE_EMAIL)) {
            $headers .= "\r\nFrom: " . STATUS_CALLBACK_EMAIL_FROM;
        }

        $sent = [];
        $failed = [];
        foreach ($recipients as $recipient) {
            $result = mail($recipient, $subject, $body, $headers);
            if ($result) {
                $sent[] = $recipient;
            } else {
                $failed[] = $recipient;
            }
        }

        if (!$failed) {
            $emailResult = ['status' => 'sent', 'sent' => $sent];
        } elseif (!$sent) {
            $emailResult = ['status' => 'error', 'failed' => $failed];
        } else {
            $emailResult = ['status' => 'partial', 'sent' => $sent, 'failed' => $failed];
        }
    } elseif (!$recipients) {
        $emailResult = ['status' => 'skipped', 'reason' => 'no_valid_recipients'];
    } else {
        $emailResult = ['status' => 'error', 'reason' => 'mail_function_missing'];
    }
}

echo json_encode([
    'status' => 'ok',
    'received_at' => $receivedAt,
    'email' => $emailResult,
]);

