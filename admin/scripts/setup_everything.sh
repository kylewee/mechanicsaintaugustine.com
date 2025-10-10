#!/usr/bin/env bash
set -euo pipefail

# This script replays the end-to-end setup we performed for mechanicsaintaugustine.com.
# It is idempotent and safe to run multiple times; existing files are backed up with timestamps.

PROJECT_ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
API_DIR="$PROJECT_ROOT/../api"
SMS_DIR="$API_DIR/sms"
INCOMING_PHP="$SMS_DIR/incoming.php"
STATUS_PHP="$SMS_DIR/status_callback.php"
INCOMING_LOG="$SMS_DIR/sms_incoming.log"
STATUS_LOG="$SMS_DIR/sms_status.log"
ENV_FILE="$API_DIR/.env.local.php"

log() {
  printf "[setup] %s\n" "$1"
}

backup_file() {
  local target="$1"
  if [[ -f "$target" ]]; then
    local stamp
    stamp=$(date +%Y%m%d-%H%M%S)
    cp "$target" "${target}.${stamp}.bak"
    log "Backed up $(basename "$target") -> ${target}.${stamp}.bak"
  fi
}

log "Creating SMS API directory"
mkdir -p "$SMS_DIR"
chmod 775 "$SMS_DIR"

log "Backing up existing webhook handlers if they exist"
backup_file "$INCOMING_PHP"
backup_file "$STATUS_PHP"

log "Writing incoming SMS handler"
cat <<'PHP' > "$INCOMING_PHP"
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
PHP

log "Writing delivery status callback handler"
cat <<'PHP' > "$STATUS_PHP"
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
PHP

log "Creating log files"
touch "$INCOMING_LOG" "$STATUS_LOG"
chmod 664 "$INCOMING_LOG" "$STATUS_LOG"

log "Running PHP syntax check"
php -l "$INCOMING_PHP" >/dev/null
php -l "$STATUS_PHP" >/dev/null

if [[ -f "$ENV_FILE" ]]; then
  TWILIO_ACCOUNT_SID=$(php -r "require '$ENV_FILE'; echo defined('TWILIO_ACCOUNT_SID') ? TWILIO_ACCOUNT_SID : '';" || true)
  TWILIO_AUTH_TOKEN=$(php -r "require '$ENV_FILE'; echo defined('TWILIO_AUTH_TOKEN') ? TWILIO_AUTH_TOKEN : '';" || true)
  TWILIO_SMS_FROM=$(php -r "require '$ENV_FILE'; echo defined('TWILIO_SMS_FROM') ? TWILIO_SMS_FROM : '';" || true)
  log "Found Twilio credentials in .env.local.php"
  cat <<EOF

Optional: to send a quick test SMS using the credentials above, run:
  curl -X POST https://api.twilio.com/2010-04-01/Accounts/${TWILIO_ACCOUNT_SID}/Messages.json \\
    --data-urlencode "To=${TWILIO_SMS_FROM}" \\
    --data-urlencode "From=${TWILIO_SMS_FROM}" \\
    --data-urlencode "MessagingServiceSid=MG436fdf28d58037bd14cc9a2e4f0b25e1" \\
    --data-urlencode "Body=Mechanic St Augustine test message" \\
    -u "${TWILIO_ACCOUNT_SID}:${TWILIO_AUTH_TOKEN}"
EOF
else
  log "Twilio credential file (.env.local.php) not found; skipped test curl instructions"
fi

cat <<'EOF'

All done!
Next steps:
  1. In Twilio Console → Messaging Services → Sole Proprietor A2P, set the incoming webhook to https://mechanicstaugustine.com/api/sms/incoming.php (POST).
  2. Set the delivery status callback to https://mechanicstaugustine.com/api/sms/status_callback.php.
  3. Watch the logs with:
       tail -f /home/kylewee/mechanicsaintaugustine.com/site/api/sms/sms_incoming.log
       tail -f /home/kylewee/mechanicsaintaugustine.com/site/api/sms/sms_status.log
EOF
