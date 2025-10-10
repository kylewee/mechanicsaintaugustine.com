<?php
// Quote intake endpoint: validates input, calls estimator, and (new) creates a CRM lead
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Load CRM config if available
$envFile = __DIR__ . '/../api/.env.local.php';
if (is_file($envFile)) {
    require $envFile;
}

/**
 * Convert the various opt-in values ("true", 1, "yes") into a boolean flag.
 */
function qi_to_bool($value): bool
{
    if (is_bool($value)) {
        return $value;
    }
    if (is_numeric($value)) {
        return (int)$value !== 0;
    }
    if (is_string($value)) {
        $normalized = strtolower(trim($value));
        return in_array($normalized, ['1', 'true', 'yes', 'y', 'on'], true);
    }
    return false;
}

/**
 * Normalize a phone number into E.164 for Twilio.
 */
function qi_normalize_phone($value): ?string
{
    if (!is_scalar($value)) {
        return null;
    }
    $raw = trim((string)$value);
    if ($raw === '') {
        return null;
    }
    if ($raw[0] === '+') {
        $digits = '+' . preg_replace('/[^0-9]/', '', substr($raw, 1));
        return strlen($digits) >= 10 ? $digits : null;
    }
    $digits = preg_replace('/\D+/', '', $raw);
    $len = strlen($digits);
    if ($len === 10) {
        return '+1' . $digits;
    }
    if ($len === 11 && $digits[0] === '1') {
        return '+' . $digits;
    }
    if ($len >= 10 && $len <= 15) {
        return '+' . $digits;
    }
    return null;
}

/**
 * Quick local pricing matrix mirroring the public quote widget.
 */
function qi_local_estimate(array $lead): ?array
{
    $repair = '';
    if (!empty($lead['repair'])) {
        $repair = strtolower(trim((string)$lead['repair']));
    } elseif (!empty($lead['service'])) {
        $repair = strtolower(trim((string)$lead['service']));
    }
    if ($repair === '') {
        return null;
    }
    $slug = preg_replace('/[^a-z0-9]+/', ' ', $repair);
    $slug = trim($slug);
    if ($slug === '') {
        return null;
    }
    $aliases = [
        'brake pads replacement' => 'brake pads',
        'alternator replacement' => 'alternator',
        'starter replacement' => 'starter',
        'timing belt replacement' => 'timing belt',
        'engine diagnostic' => 'check engine',
        'check engine light' => 'check engine',
        'battery' => 'battery replacement',
    ];
    if (isset($aliases[$slug])) {
        $slug = $aliases[$slug];
    }
    $priceMap = [
        'oil change' => 50,
        'brake pads' => 150,
        'battery replacement' => 120,
        'alternator' => 350,
        'starter' => 300,
        'timing belt' => 500,
        'ac recharge' => 180,
        'check engine' => 80,
        'heater hose replacement' => 220,
    ];
    if (!isset($priceMap[$slug])) {
        return null;
    }
    $base = (float)$priceMap[$slug];
    $multiplier = 1.0;
    $engine = isset($lead['engine']) ? strtolower((string)$lead['engine']) : '';
    if ($engine !== '' && strpos($engine, 'v8') !== false) {
        $multiplier = 1.2;
    }
    $year = isset($lead['year']) ? (int)$lead['year'] : 0;
    if ($year > 0 && $year < 2000) {
        $multiplier += 0.1;
    }
    $amount = round($base * $multiplier);
    return [
        'amount' => (float)$amount,
        'source' => 'local_matrix',
        'base_price' => $base,
        'multiplier' => $multiplier,
        'repair_key' => $slug,
    ];
}

/**
 * Extract a numeric candidate from the internal estimator payload.
 */
function qi_remote_estimate_candidate($estimate)
{
    if (!is_array($estimate)) {
        return null;
    }
    $candidates = [];
    $low = isset($estimate['total_low']) && is_numeric($estimate['total_low']) ? (float)$estimate['total_low'] : null;
    $high = isset($estimate['total_high']) && is_numeric($estimate['total_high']) ? (float)$estimate['total_high'] : null;
    if ($low !== null && $high !== null && $low > 0 && $high > 0) {
        $candidates[] = [
            'amount' => ($low + $high) / 2,
            'source' => 'remote_range_avg',
            'details' => ['low' => $low, 'high' => $high],
        ];
    }
    foreach (['total', 'estimate', 'price', 'amount', 'total_mid'] as $key) {
        if (isset($estimate[$key]) && is_numeric($estimate[$key])) {
            $candidates[] = [
                'amount' => (float)$estimate[$key],
                'source' => 'remote_' . $key,
            ];
        }
    }
    if (empty($candidates)) {
        foreach ($estimate as $key => $value) {
            if (is_numeric($value)) {
                $candidates[] = [
                    'amount' => (float)$value,
                    'source' => 'remote_' . $key,
                ];
                break;
            }
        }
    }
    if (empty($candidates)) {
        return null;
    }
    foreach ($candidates as $candidate) {
        if ($candidate['amount'] > 0) {
            $candidate['amount'] = round($candidate['amount']);
            return $candidate;
        }
    }
    $first = $candidates[0];
    $first['amount'] = round($first['amount']);
    return $first;
}

/**
 * Merge local and remote estimates for downstream logging + messaging.
 */
function qi_build_estimate_summary($estimate, array $lead): array
{
    $remote = qi_remote_estimate_candidate($estimate);
    $local = qi_local_estimate($lead);
    $summary = [
        'amount' => null,
        'source' => null,
        'candidates' => [],
    ];
    if ($remote) {
        $summary['candidates']['remote'] = $remote;
        if ($remote['amount'] > 0) {
            $summary['amount'] = $remote['amount'];
            $summary['source'] = $remote['source'];
        }
    }
    if ($local) {
        $summary['candidates']['local'] = $local;
        if ($summary['amount'] === null && $local['amount'] > 0) {
            $summary['amount'] = round((float)$local['amount']);
            $summary['source'] = $local['source'];
        }
    }
    if ($summary['amount'] !== null) {
        $summary['amount'] = (float)round($summary['amount']);
    }
    if (is_array($estimate)) {
        $summary['raw_remote'] = $estimate;
    }

    $risk = qi_vehicle_risk_multiplier($lead);
    $summary['risk_adjustment'] = $risk;
    if ($risk['multiplier'] !== 1.0) {
        foreach ($summary['candidates'] as $key => $candidate) {
            $baseAmount = isset($candidate['amount']) ? (float)$candidate['amount'] : null;
            if ($baseAmount === null) {
                continue;
            }
            $adjusted = round($baseAmount * $risk['multiplier']);
            $summary['candidates'][$key]['base_amount'] = $baseAmount;
            $summary['candidates'][$key]['amount'] = (float)$adjusted;
        }
        if ($summary['amount'] !== null) {
            $summary['base_amount'] = $summary['amount'];
            $summary['amount'] = (float)round($summary['amount'] * $risk['multiplier']);
            if (!empty($summary['source'])) {
                $summary['source'] .= ' + risk';
            }
        }
    }

    return $summary;
}

/**
 * Load cached summary derived from the SCANIA Component X dataset.
 */
function qi_component_x_summary_cache(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $file = __DIR__ . '/../data/scania_component_x_summary.json';
    if (is_file($file)) {
        $decoded = json_decode((string)file_get_contents($file), true);
        if (is_array($decoded)) {
            $cache = $decoded;
            return $cache;
        }
    }
    $cache = [];
    return $cache;
}

/**
 * Map a user-facing duty class to the anonymised Spec_0 category from the dataset.
 */
function qi_vehicle_class_to_category(string $vehicleClass): ?string
{
    $normalized = strtolower(trim($vehicleClass));
    $map = [
        'light'  => 'Cat2',
        'medium' => 'Cat1',
        'heavy'  => 'Cat0',
    ];
    return $map[$normalized] ?? null;
}

/**
 * Compute a risk multiplier based on the SCANIA Component X failure rates.
 */
function qi_vehicle_risk_multiplier(array $lead): array
{
    $summary = qi_component_x_summary_cache();
    if (!$summary) {
        return [
            'multiplier' => 1.0,
            'repair_rate' => 0.0,
            'category' => null,
            'source' => 'component_x_summary_unavailable',
        ];
    }

    $baseRate = isset($summary['training_repair_rate']) ? (float)$summary['training_repair_rate'] : 0.0;
    $category = null;
    $rate = $baseRate;

    if (!empty($lead['vehicle_class'])) {
        $mapped = qi_vehicle_class_to_category((string)$lead['vehicle_class']);
        if ($mapped && isset($summary['by_spec_0'][$mapped]['repair_rate'])) {
            $category = $mapped;
            $rate = (float)$summary['by_spec_0'][$mapped]['repair_rate'];
        }
    }

    $multiplier = 1.0;
    if ($rate > 0) {
        $multiplier += min($rate, 1.0);
    }

    return [
        'multiplier' => $multiplier,
        'repair_rate' => $rate,
        'category' => $category,
        'source' => $summary['dataset'] ?? 'component_x_summary',
    ];
}

/**
 * Forward sanitized payload to the Go platform API (best-effort and optional).
 */
function qi_forward_to_platform(array $payload): array
{
    if (!defined('PLATFORM_PUBLIC_QUOTE_ENDPOINT') || !PLATFORM_PUBLIC_QUOTE_ENDPOINT) {
        return ['status' => 'skipped', 'reason' => 'endpoint_not_configured'];
    }
    if (!function_exists('curl_init')) {
        return ['status' => 'skipped', 'reason' => 'curl_missing'];
    }

    $ch = curl_init(PLATFORM_PUBLIC_QUOTE_ENDPOINT);
    if (!$ch) {
        return ['status' => 'skipped', 'reason' => 'curl_init_failed'];
    }

    $body = json_encode($payload);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($body),
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 2,
        CURLOPT_CONNECTTIMEOUT => 1,
    ]);

    $response = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($errno !== 0) {
        return ['status' => 'error', 'reason' => 'curl', 'errno' => $errno, 'error' => $error];
    }

    if ($http < 200 || $http >= 300) {
        return ['status' => 'error', 'http' => $http, 'body' => $response];
    }

    $decoded = json_decode((string)$response, true);
    return ['status' => 'ok', 'http' => $http, 'response' => $decoded];
}

/**
 * Send a one-time SMS via Twilio when the lead opts in.
 */
function qi_send_sms_quote(array $lead, array $estimateSummary): array
{
    if (!function_exists('curl_init')) {
        return ['status' => 'error', 'reason' => 'curl_missing'];
    }
    if (!defined('TWILIO_ACCOUNT_SID') || !defined('TWILIO_AUTH_TOKEN')) {
        return ['status' => 'error', 'reason' => 'twilio_config_missing'];
    }
    $messagingServiceSid = defined('TWILIO_MESSAGING_SERVICE_SID') && TWILIO_MESSAGING_SERVICE_SID
        ? trim((string)TWILIO_MESSAGING_SERVICE_SID)
        : '';

    $fromConfig = defined('TWILIO_SMS_FROM') && TWILIO_SMS_FROM ? (string)TWILIO_SMS_FROM : '';
    if ($fromConfig === '' && defined('TWILIO_CALLER_ID') && TWILIO_CALLER_ID) {
        $fromConfig = (string)TWILIO_CALLER_ID;
    }
    $from = $fromConfig !== '' ? qi_normalize_phone($fromConfig) : null;

    if ($messagingServiceSid === '' && $from === null) {
        return ['status' => 'error', 'reason' => 'twilio_from_missing'];
    }
    $toRaw = $lead['phone'] ?? '';
    $to = qi_normalize_phone($toRaw);
    if ($to === null) {
        return ['status' => 'error', 'reason' => 'invalid_destination', 'detail' => $toRaw];
    }
    $name = isset($lead['name']) && trim((string)$lead['name']) !== '' ? trim((string)$lead['name']) : 'there';
    $repair = isset($lead['repair']) && trim((string)$lead['repair']) !== '' ? trim((string)$lead['repair']) : (isset($lead['service']) ? trim((string)$lead['service']) : 'your vehicle');
    $vehicleParts = [];
    foreach (['year', 'make', 'model'] as $key) {
        if (!empty($lead[$key])) {
            $vehicleParts[] = trim((string)$lead[$key]);
        }
    }
    $vehicle = implode(' ', $vehicleParts);
    if ($vehicle === '') {
        $vehicle = 'your vehicle';
    }
    $slotInfo = null;
    if (isset($lead['_preferred_slot']) && is_array($lead['_preferred_slot']) && isset($lead['_preferred_slot']['display'])) {
        $slotInfo = $lead['_preferred_slot']['display'];
    } elseif (isset($lead['preferred_slot'])) {
        $slot = qi_normalize_slot($lead['preferred_slot']);
        if ($slot && isset($slot['display'])) {
            $slotInfo = $slot['display'];
        }
    }
    $amount = isset($estimateSummary['amount']) && is_numeric($estimateSummary['amount']) ? (float)$estimateSummary['amount'] : null;
    if ($amount !== null && $amount > 0) {
        $priceText = '$' . number_format($amount, 0);
        $body = "Hi {$name}, thanks for contacting Mechanics Saint Augustine. Estimated price for {$repair} on {$vehicle} is {$priceText}.";
    } else {
        $body = "Hi {$name}, thanks for contacting Mechanics Saint Augustine. We received your request for {$repair} on {$vehicle}.";
    }
    if ($slotInfo) {
        $body .= " Preferred time: {$slotInfo}.";
    }
    $body .= ' Reply STOP to opt out.';

    $payloadFields = [
        'To' => $to,
        'Body' => $body,
    ];
    if ($messagingServiceSid !== '') {
        $payloadFields['MessagingServiceSid'] = $messagingServiceSid;
    } else {
        $payloadFields['From'] = $from;
    }

    $payload = http_build_query($payloadFields);

    $url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode((string)TWILIO_ACCOUNT_SID) . '/Messages.json';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => (string)TWILIO_ACCOUNT_SID . ':' . (string)TWILIO_AUTH_TOKEN,
    ]);

    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErrno = curl_errno($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return [
            'status' => 'error',
            'reason' => 'twilio_request_failed',
            'curl_errno' => $curlErrno,
            'curl_error' => $curlError,
        ];
    }

    $decoded = json_decode((string)$response, true);
    if ($httpCode >= 200 && $httpCode < 300) {
        $result = [
            'status' => 'sent',
            'sid' => isset($decoded['sid']) ? (string)$decoded['sid'] : null,
            'http' => $httpCode,
            'to' => $to,
            'body_preview' => substr($body, 0, 120),
        ];
        if ($messagingServiceSid !== '') {
            $result['messaging_service'] = $messagingServiceSid;
            if (isset($decoded['from'])) {
                $result['from'] = (string)$decoded['from'];
            }
        } else {
            $result['from'] = $from;
        }
        return $result;
    }

    return [
        'status' => 'error',
        'reason' => 'twilio_http_' . $httpCode,
        'http' => $httpCode,
        'twilio_response' => $decoded ?: (string)$response,
        'curl_errno' => $curlErrno,
        'curl_error' => $curlError,
    ];
}

/**
 * Notify the shop owner about a new lead via SMS.
 */
function qi_notify_owner_sms(array $payload): array
{
    if (!function_exists('curl_init')) {
        return ['status' => 'error', 'reason' => 'curl_missing'];
    }
    if (!defined('TWILIO_ACCOUNT_SID') || !defined('TWILIO_AUTH_TOKEN')) {
        return ['status' => 'skipped', 'reason' => 'twilio_config_missing'];
    }
    if (!defined('TWILIO_FORWARD_TO') || !TWILIO_FORWARD_TO) {
        return ['status' => 'skipped', 'reason' => 'owner_number_missing'];
    }
    $to = qi_normalize_phone(TWILIO_FORWARD_TO);
    if ($to === null) {
        return ['status' => 'skipped', 'reason' => 'owner_number_invalid', 'raw' => TWILIO_FORWARD_TO];
    }

    $fromConfig = defined('TWILIO_SMS_FROM') && TWILIO_SMS_FROM ? (string)TWILIO_SMS_FROM : '';
    if ($fromConfig === '' && defined('TWILIO_CALLER_ID') && TWILIO_CALLER_ID) {
        $fromConfig = (string)TWILIO_CALLER_ID;
    }
    $from = $fromConfig !== '' ? qi_normalize_phone($fromConfig) : null;
    $messagingServiceSid = defined('TWILIO_MESSAGING_SERVICE_SID') && TWILIO_MESSAGING_SERVICE_SID
        ? trim((string)TWILIO_MESSAGING_SERVICE_SID)
        : '';
    if ($messagingServiceSid === '' && $from === null) {
        return ['status' => 'skipped', 'reason' => 'twilio_from_missing'];
    }

    $lead = $payload['lead'] ?? [];
    $estimate = $payload['estimate'] ?? [];

    $name = trim((string)($lead['name'] ?? 'New lead'));
    $phone = trim((string)($lead['phone'] ?? ''));
    $repair = trim((string)($lead['repair'] ?? ''));
    $location = trim((string)($lead['service_location'] ?? ''));
    $preferred = null;
    if (isset($lead['preferred_slot']) && is_array($lead['preferred_slot']) && isset($lead['preferred_slot']['display'])) {
        $preferred = $lead['preferred_slot']['display'];
    } elseif (!empty($lead['preferred_slot'])) {
        $preferred = (string)$lead['preferred_slot'];
    }

    $vehicleParts = [];
    foreach (['year', 'make', 'model'] as $key) {
        if (!empty($lead['vehicle'][$key])) {
            $vehicleParts[] = trim((string)$lead['vehicle'][$key]);
        } elseif (!empty($lead[$key])) {
            $vehicleParts[] = trim((string)$lead[$key]);
        }
    }
    $vehicle = $vehicleParts ? implode(' ', $vehicleParts) : '';

    $amount = null;
    if (isset($estimate['amount']) && is_numeric($estimate['amount'])) {
        $amount = (float)$estimate['amount'];
    } elseif (isset($estimate['candidates']['remote']['amount']) && is_numeric($estimate['candidates']['remote']['amount'])) {
        $amount = (float)$estimate['candidates']['remote']['amount'];
    } elseif (isset($estimate['candidates']['local']['amount']) && is_numeric($estimate['candidates']['local']['amount'])) {
        $amount = (float)$estimate['candidates']['local']['amount'];
    }

    $summaryParts = [$name];
    if ($phone !== '') {
        $summaryParts[] = $phone;
    }
    if ($repair !== '') {
        $summaryParts[] = $repair;
    }
    if ($vehicle !== '') {
        $summaryParts[] = $vehicle;
    }
    $body = 'New lead: ' . implode(' | ', array_filter($summaryParts));
    if ($amount !== null && $amount > 0) {
        $body .= ' | Est: $' . number_format($amount, 0);
    }
    if ($location !== '') {
        $body .= ' | Loc: ' . $location;
    }
    if ($preferred) {
        $body .= ' | Pref: ' . $preferred;
    }

    $payloadFields = [
        'To' => $to,
        'Body' => $body,
    ];
    if ($messagingServiceSid !== '') {
        $payloadFields['MessagingServiceSid'] = $messagingServiceSid;
    } else {
        $payloadFields['From'] = $from;
    }

    $url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode((string)TWILIO_ACCOUNT_SID) . '/Messages.json';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($payloadFields),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => (string)TWILIO_ACCOUNT_SID . ':' . (string)TWILIO_AUTH_TOKEN,
    ]);

    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErrno = curl_errno($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return [
            'status' => 'error',
            'reason' => 'twilio_request_failed',
            'curl_errno' => $curlErrno,
            'curl_error' => $curlError,
        ];
    }

    $decoded = json_decode((string)$response, true);
    if ($httpCode >= 200 && $httpCode < 300) {
        $result = [
            'status' => 'sent',
            'http' => $httpCode,
            'sid' => isset($decoded['sid']) ? (string)$decoded['sid'] : null,
            'to' => $to,
        ];
        if ($messagingServiceSid !== '') {
            $result['messaging_service'] = $messagingServiceSid;
        } elseif ($from !== null) {
            $result['from'] = $from;
        }
        return $result;
    }

    return [
        'status' => 'error',
        'reason' => 'twilio_http_' . $httpCode,
        'http' => $httpCode,
        'twilio_response' => $decoded ?: (string)$response,
        'curl_errno' => $curlErrno,
        'curl_error' => $curlError,
    ];
}

/**
 * Email a summary of the quote intake to configured recipients.
 */
function qi_notify_email(array $payload): array
{
    if (!defined('QUOTE_NOTIFICATION_EMAILS') || !QUOTE_NOTIFICATION_EMAILS) {
        return ['status' => 'skipped', 'reason' => 'email_not_configured'];
    }

    $configured = QUOTE_NOTIFICATION_EMAILS;
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

    if (!$recipients) {
        return ['status' => 'skipped', 'reason' => 'email_invalid'];
    }
    if (!function_exists('mail')) {
        return ['status' => 'error', 'reason' => 'mail_function_missing'];
    }

    $lead = $payload['lead'] ?? [];
    $vehicle = $lead['vehicle'] ?? [];
    $address = $lead['address'] ?? [];
    $estimate = $payload['estimate'] ?? [];
    $meta = $payload['meta'] ?? [];

    $leadName = trim((string)($lead['name'] ?? ''));
    $subject = $leadName !== ''
        ? 'New quote request from ' . $leadName
        : 'New quote request received';

    $vehicleParts = [];
    foreach (['year', 'make', 'model', 'engine'] as $part) {
        if (!empty($vehicle[$part])) {
            $vehicleParts[] = trim((string)$vehicle[$part]);
        }
    }
    $vehicleLine = $vehicleParts ? implode(' ', $vehicleParts) : 'Not specified';

    $addressParts = [];
    foreach (['street', 'city', 'state', 'zip'] as $part) {
        if (!empty($address[$part])) {
            $addressParts[] = trim((string)$address[$part]);
        }
    }
    $addressLine = $addressParts ? implode(', ', $addressParts) : 'Not provided';

    $estimateLine = 'Pending';
    if (isset($estimate['amount']) && is_numeric($estimate['amount'])) {
        $estimateLine = '$' . number_format((float)$estimate['amount'], 0);
        if (!empty($estimate['source'])) {
            $estimateLine .= ' (' . $estimate['source'] . ')';
        }
    }

    $preferredSlot = null;
    if (isset($lead['preferred_slot']['display'])) {
        $preferredSlot = $lead['preferred_slot']['display'];
    } elseif (!empty($lead['preferred_slot_display'])) {
        $preferredSlot = $lead['preferred_slot_display'];
    }

    $lines = [];
    $lines[] = 'New quote intake received.';
    $lines[] = 'Received: ' . ($meta['received_at'] ?? date('c'));
    $lines[] = 'Name: ' . ($leadName !== '' ? $leadName : 'N/A');
    $lines[] = 'Phone: ' . ($lead['phone'] ?? 'N/A');
    if (!empty($lead['email'])) {
        $lines[] = 'Email: ' . $lead['email'];
    }
    if (!empty($lead['contact_method'])) {
        $lines[] = 'Preferred Contact: ' . ucfirst((string)$lead['contact_method']);
    }
    $lines[] = 'Repair: ' . ($lead['repair'] ?? 'N/A');
    $lines[] = 'Vehicle: ' . $vehicleLine;
    if (!empty($lead['vehicle_class'])) {
        $lines[] = 'Duty Class: ' . ucfirst((string)$lead['vehicle_class']);
    }
    if ($preferredSlot) {
        $lines[] = 'Preferred Slot: ' . $preferredSlot;
    }
    if (!empty($lead['service_location'])) {
        $lines[] = 'Service Location: ' . ucfirst((string)$lead['service_location']);
    }
    if (!empty($lead['concern'])) {
        $lines[] = 'Customer Notes: ' . $lead['concern'];
    }
    $lines[] = 'Address: ' . $addressLine;
    if (!empty($vehicle['vin'])) {
        $lines[] = 'VIN: ' . $vehicle['vin'];
    }
    if (isset($vehicle['mileage']) && $vehicle['mileage'] !== null && $vehicle['mileage'] !== '') {
        $lines[] = 'Mileage: ' . number_format((int)$vehicle['mileage']) . ' mi';
    }
    if (isset($vehicle['labor_hours']) && $vehicle['labor_hours'] !== null && $vehicle['labor_hours'] !== '') {
        $lines[] = 'Estimated Labor Hours: ' . rtrim(rtrim(number_format((float)$vehicle['labor_hours'], 2, '.', ''), '0'), '.');
    }
    $lines[] = 'Estimate: ' . $estimateLine;

    $smsStatus = $payload['sms']['status'] ?? 'unknown';
    $lines[] = 'SMS: ' . $smsStatus;

    $crmLine = 'not_attempted';
    if (is_array($payload['crm'])) {
        if (isset($payload['crm']['error'])) {
            $crmLine = 'error: ' . $payload['crm']['error'];
        } elseif (isset($payload['crm']['http'])) {
            $crmLine = 'http ' . $payload['crm']['http'];
        } elseif (isset($payload['crm']['status'])) {
            $crmLine = (string)$payload['crm']['status'];
        } else {
            $crmLine = 'ok';
        }
    }
    $lines[] = 'CRM: ' . $crmLine;

    if (isset($payload['platform']['status'])) {
        $lines[] = 'Platform Forward: ' . $payload['platform']['status'];
    }

    if (isset($meta['remote_addr'])) {
        $lines[] = 'Remote IP: ' . $meta['remote_addr'];
    }

    $lines[] = '';
    $lines[] = 'This notification was sent automatically.';

    $body = implode("\n", $lines);

    $headers = "Content-Type: text/plain; charset=UTF-8";
    if (defined('QUOTE_NOTIFICATION_EMAIL_FROM') && QUOTE_NOTIFICATION_EMAIL_FROM && filter_var(QUOTE_NOTIFICATION_EMAIL_FROM, FILTER_VALIDATE_EMAIL)) {
        $headers .= "\r\nFrom: " . QUOTE_NOTIFICATION_EMAIL_FROM;
    }
    if (!empty($lead['email']) && filter_var($lead['email'], FILTER_VALIDATE_EMAIL)) {
        $headers .= "\r\nReply-To: " . $lead['email'];
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
        return ['status' => 'sent', 'sent' => $sent];
    }
    if (!$sent) {
        return ['status' => 'error', 'reason' => 'mail_failed', 'failed' => $failed];
    }

    return ['status' => 'partial', 'sent' => $sent, 'failed' => $failed];
}

/**
 * POST quote intake data to an external webhook for status callbacks.
 */
function qi_notify_status_webhook(array $payload): array
{
    if (!defined('QUOTE_STATUS_WEBHOOK') || !QUOTE_STATUS_WEBHOOK) {
        return ['status' => 'skipped', 'reason' => 'webhook_not_configured'];
    }

    $url = trim((string)QUOTE_STATUS_WEBHOOK);
    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
        return ['status' => 'skipped', 'reason' => 'invalid_webhook_url'];
    }
    if (!function_exists('curl_init')) {
        return ['status' => 'error', 'reason' => 'curl_missing'];
    }

    $body = json_encode($payload);
    if ($body === false) {
        return ['status' => 'error', 'reason' => 'json_encode_failed'];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($body),
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_CONNECTTIMEOUT => 2,
    ]);

    $response = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($errno !== 0) {
        return ['status' => 'error', 'reason' => 'curl', 'errno' => $errno, 'error' => $error];
    }

    if ($http < 200 || $http >= 300) {
        return ['status' => 'error', 'http' => $http, 'body' => $response];
    }

    $decoded = json_decode((string)$response, true);
    return ['status' => 'ok', 'http' => $http, 'response' => $decoded];
}

/**
 * Normalize the preferred slot into ISO + localized display string.
 */
function qi_normalize_slot($value): ?array
{
    if (!is_string($value) || trim($value) === '') {
        return null;
    }
    $ts = strtotime($value);
    if ($ts === false || $ts <= time()) {
        return null;
    }
    try {
        $utc = (new DateTimeImmutable('@' . $ts))->setTimezone(new DateTimeZone('UTC'));
        $local = $utc->setTimezone(new DateTimeZone('America/New_York'));
        return [
            'iso' => $utc->format(DateTimeInterface::ATOM),
            'display' => $local->format('D, M j \a\t g:ia T'),
        ];
    } catch (Throwable $e) {
        return null;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Read JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// Extract optional meta overrides (e.g., skip CRM, source tags)
$metaOverrides = [];
if (isset($data['meta']) && is_array($data['meta'])) {
    $metaOverrides = $data['meta'];
    unset($data['meta']);
}

// Validate required fields
$required = ['name', 'phone'];
foreach ($required as $field) {
    if (empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit;
    }
}

$textOptIn = false;
foreach (['text_opt_in', 'textQuote', 'sms_opt_in', 'notify_by_text'] as $optKey) {
    if (array_key_exists($optKey, $data)) {
        $textOptIn = qi_to_bool($data[$optKey]);
        break;
    }
}

// --- Normalize additional optional fields ---
$street = isset($data['street']) ? trim((string)$data['street']) : '';
$street = $street !== '' ? $street : '';
if ($street !== '') {
    $data['street'] = $street;
} else {
    unset($data['street']);
}

$city = isset($data['city']) ? trim((string)$data['city']) : '';
if ($city !== '') {
    $data['city'] = $city;
} else {
    unset($data['city']);
}

$state = isset($data['state']) ? strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string)$data['state']), 0, 2)) : '';
if ($state !== '') {
    $data['state'] = $state;
} else {
    unset($data['state']);
}

$zip = isset($data['zip']) ? preg_replace('/[^0-9]/', '', (string)$data['zip']) : '';
if ($zip !== '') {
    $data['zip'] = $zip;
} else {
    unset($data['zip']);
}

$vehicleClass = isset($data['vehicle_class']) ? strtolower(trim((string)$data['vehicle_class'])) : '';
if (!in_array($vehicleClass, ['light', 'medium', 'heavy'], true)) {
    $vehicleClass = '';
}
if ($vehicleClass !== '') {
    $data['vehicle_class'] = $vehicleClass;
} else {
    unset($data['vehicle_class']);
}

$serviceLocation = isset($data['service_location']) ? strtolower((string)$data['service_location']) : '';
if (!in_array($serviceLocation, ['home', 'work', 'roadside'], true)) {
    $serviceLocation = '';
}
if ($serviceLocation !== '') {
    $data['service_location'] = $serviceLocation;
} else {
    unset($data['service_location']);
}

$contactMethod = isset($data['contact_method']) ? strtolower((string)$data['contact_method']) : '';
if (!in_array($contactMethod, ['call', 'text', 'email'], true)) {
    $contactMethod = '';
}
if ($contactMethod !== '') {
    $data['contact_method'] = $contactMethod;
} else {
    unset($data['contact_method']);
}

$mileage = null;
if (isset($data['mileage']) && $data['mileage'] !== '') {
    $mileageDigits = preg_replace('/[^0-9]/', '', (string)$data['mileage']);
    if ($mileageDigits !== '') {
        $mileage = (int)$mileageDigits;
    }
}
if ($mileage !== null) {
    $data['mileage'] = $mileage;
} else {
    unset($data['mileage']);
}

$laborHours = null;
if (isset($data['labor_hours']) && $data['labor_hours'] !== '') {
    $laborHours = (float)$data['labor_hours'];
    if (!is_finite($laborHours) || $laborHours <= 0) {
        $laborHours = null;
    }
}
if ($laborHours !== null) {
    $data['labor_hours'] = $laborHours;
} else {
    unset($data['labor_hours']);
}

$vin = '';
if (isset($data['vin']) && $data['vin'] !== '') {
    $vin = strtoupper(preg_replace('/[^A-HJ-NPR-Z0-9]/', '', (string)$data['vin']));
    if (strlen($vin) >= 11 && strlen($vin) <= 17) {
        $data['vin'] = $vin;
    } else {
        unset($data['vin']);
        $vin = '';
    }
}

$concern = isset($data['concern']) ? trim((string)$data['concern']) : '';
if ($concern !== '') {
    $concern = strip_tags($concern);
    $data['concern'] = $concern;
} else {
    unset($data['concern']);
}

$preferredSlot = isset($data['preferred_slot']) ? qi_normalize_slot($data['preferred_slot']) : null;

// Extract repair/service info for estimate
$estimate_payload = [
    'repair' => $data['repair'] ?? '',
    'service' => $data['service'] ?? '',
    'year' => (int)($data['year'] ?? 0),
    'make' => $data['make'] ?? '',
    'model' => $data['model'] ?? '',
    'engine' => $data['engine'] ?? '',
    'zip' => $data['zip'] ?? '',
    'vehicle_class' => $data['vehicle_class'] ?? '',
];

// Call internal estimate API
$estimate_result = null;
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8091/api/estimate');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($estimate_payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);

$estimate_response = curl_exec($ch);
$estimate_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($estimate_response && $estimate_http_code === 200) {
    $estimate_result = json_decode($estimate_response, true);
}

$estimate_summary = qi_build_estimate_summary($estimate_result, $data);
$sms_lead_context = $data;
$sms_lead_context['_preferred_slot'] = $preferredSlot;
$sms_result = $textOptIn
    ? qi_send_sms_quote($sms_lead_context, $estimate_summary)
    : ['status' => 'skipped', 'reason' => 'not_opted_in'];

$receivedAt = date('c');

// Log the quote request (structured for easy parsing)
$log_entry = [
    'timestamp' => $receivedAt,
    'level' => 'info',
    'type' => 'quote_intake',
    'meta_overrides' => $metaOverrides,
    'data' => [
        'name' => $data['name'],
        'phone' => $data['phone'],
        'email' => $data['email'] ?? '',
        'year' => $data['year'] ?? '',
        'make' => $data['make'] ?? '',
        'model' => $data['model'] ?? '',
        'engine' => $data['engine'] ?? '',
        'repair' => $data['repair'] ?? '',
        'zip' => $data['zip'] ?? '',
        'street' => $data['street'] ?? '',
        'city' => $data['city'] ?? '',
        'state' => $data['state'] ?? '',
        'vehicle_class' => $data['vehicle_class'] ?? '',
        'text_opt_in' => $textOptIn,
        'preferred_slot' => $preferredSlot,
        'service_location' => $serviceLocation,
        'contact_method' => $contactMethod,
        'mileage' => $mileage,
        'labor_hours' => $laborHours,
        'vin' => $vin,
        'concern' => $concern,
        'preferred_slot_iso' => $preferredSlot['iso'] ?? null,
        'estimate' => $estimate_summary,
        'estimate_raw' => $estimate_result,
        'sms' => $sms_result,
    ]
];

$platformPayload = [
    'name' => $data['name'],
    'phone' => $data['phone'],
    'email' => $data['email'] ?? '',
    'vehicle' => [
        'year' => $data['year'] ?? '',
        'make' => $data['make'] ?? '',
        'model' => $data['model'] ?? '',
        'engine' => $data['engine'] ?? '',
        'duty_class' => $data['vehicle_class'] ?? '',
    ],
    'location' => [
        'street' => $street,
        'city' => $city,
        'state' => $state,
        'zip' => $zip,
        'service_location' => $serviceLocation,
    ],
    'repair' => $data['repair'] ?? '',
    'mileage' => $mileage,
    'labor_hours' => $laborHours,
    'vin' => $vin,
    'contact_method' => $contactMethod,
    'preferred_slot' => $preferredSlot,
    'concern' => $concern,
    'estimate' => $estimate_summary,
    'extra' => [
        'text_opt_in' => $textOptIn,
        'sms_status'  => $sms_result['status'] ?? null,
    ],
    'source' => 'php_quote_intake',
];
$platformForward = qi_forward_to_platform($platformPayload);
$log_entry['data']['platform_forward'] = $platformForward['status'] ?? 'skipped';

// Log to error_log (will go to PHP error log or syslog)
error_log('QUOTE_INTAKE: ' . json_encode($log_entry));

// Mirror log locally so we can tail it without server access hurdles
@file_put_contents(__DIR__ . '/../api/quote_intake.log', json_encode($log_entry, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);

// --- Create CRM Lead (if CRM config present) ---
$crm_result = null;
if (!empty($metaOverrides['skip_crm'])) {
    $crm_result = ['status' => 'skipped', 'reason' => 'skip_crm'];
} elseif (
    defined('CRM_API_URL') && CRM_API_URL &&
    defined('CRM_LEADS_ENTITY_ID') && (int)CRM_LEADS_ENTITY_ID > 0
) {
    if (!function_exists('curl_init')) {
        $crm_result = ['skipped' => true, 'reason' => 'curl extension not available'];
    } else {
        // Split name
        $name = trim((string)$data['name']);
        $first = $name; $last = '';
        if (strpos($name, ' ') !== false) {
            $bits = preg_split('/\s+/', $name);
            $last = array_pop($bits);
            $first = trim(implode(' ', $bits)) ?: $name;
        }

        // Build field values based on mapping
        $fieldValues = [];
        if (defined('CRM_FIELD_MAP') && is_array(CRM_FIELD_MAP)) {
            $mapf = CRM_FIELD_MAP;
            $assign = function(string $key, $val) use (&$fieldValues, $mapf) {
                if (isset($mapf[$key]) && (int)$mapf[$key] > 0 && $val !== '' && $val !== null) {
                    $fieldValues[(int)$mapf[$key]] = (string)$val;
                }
            };
            $assign('first_name', $first);
            $assign('last_name',  $last ?: $first);
            $assign('name',       $name);
            $assign('phone',      (string)($data['phone'] ?? ''));
            $assign('email',      (string)($data['email'] ?? ''));
            $assign('year',       !empty($data['year']) ? (string)$data['year'] : '');
            $assign('make',       (string)($data['make'] ?? ''));
            $assign('model',      (string)($data['model'] ?? ''));
            $assign('engine',     (string)($data['engine'] ?? ''));
            $assign('engine_size',(string)($data['engine'] ?? ''));
            $assign('repair',     (string)($data['repair'] ?? ''));
            $addressParts = [];
            if (!empty($street) || !empty($city) || !empty($state) || !empty($zip)) {
                if ($street !== '') { $addressParts[] = $street; }
                $cityState = trim(($city !== '' ? $city : '') . ($state !== '' ? ', ' . $state : ''));
                if ($cityState !== '') { $addressParts[] = ltrim($cityState, ', '); }
                if ($zip !== '') { $addressParts[] = $zip; }
                if (!empty($addressParts)) {
                    $assign('address', implode(', ', $addressParts));
                }
            }
            $assign('street',     $street);
            $assign('city',       $city);
            $assign('state',      $state);
            $assign('zip',        $zip);
            if ($preferredSlot && isset($preferredSlot['display'])) {
                $assign('preferred_slot', $preferredSlot['display']);
            }
            if (isset($estimate_summary['amount']) && $estimate_summary['amount'] !== null) {
                $assign('estimate', (string)round((float)$estimate_summary['amount']));
            } elseif (is_array($estimate_result)) {
                $assign('estimate', (string)($estimate_result['total_high'] ?? $estimate_result['total_low'] ?? ''));
            }
            if ($mileage !== null) {
                $assign('mileage', (string)$mileage);
            }
            if ($laborHours !== null) {
                $assign('labor_hours', rtrim(rtrim(number_format($laborHours, 2, '.', ''), '0'), '.'));
            }
            if ($vin !== '') {
                $assign('vin', $vin);
            }
            if ($serviceLocation !== '') {
                $assign('service_location', $serviceLocation);
            }
            if ($contactMethod !== '') {
                $assign('contact_method', $contactMethod);
            }
            if ($concern !== '') {
                $assign('concern', $concern);
            }
            // If a 'notes' field is mapped, include a compact summary
            if (isset($mapf['notes'])) {
                $estimateNote = '';
                if (isset($estimate_summary['amount']) && $estimate_summary['amount'] !== null) {
                    $estimateNote = '$' . number_format((float)$estimate_summary['amount'], 0) . ' (' . ($estimate_summary['source'] ?? 'estimate') . ')';
                } elseif (is_array($estimate_result)) {
                    $estimateNote = json_encode($estimate_result);
                }
                $notesLines = [
                    'Quote via website',
                    'Phone: ' . ($data['phone'] ?? ''),
                    'Email: ' . ($data['email'] ?? ''),
                    'Vehicle: ' . trim(($data['year'] ?? '') . ' ' . ($data['make'] ?? '') . ' ' . ($data['model'] ?? '') . ' ' . ($data['engine'] ?? '')),
                    'Repair: ' . ($data['repair'] ?? ''),
                ];
                if (!empty($addressParts)) {
                    $notesLines[] = 'Address: ' . implode(', ', $addressParts);
                }
                if ($serviceLocation !== '') {
                    $notesLines[] = 'Service Location: ' . ucfirst($serviceLocation);
                }
                if ($contactMethod !== '') {
                    $notesLines[] = 'Preferred Contact: ' . ucfirst($contactMethod);
                }
                if ($mileage !== null) {
                    $notesLines[] = 'Mileage: ' . number_format($mileage) . ' mi';
                }
                if ($laborHours !== null) {
                    $notesLines[] = 'Estimated Labor Hours: ' . rtrim(rtrim(number_format($laborHours, 2, '.', ''), '0'), '.');
                }
                if ($vin !== '') {
                    $notesLines[] = 'VIN: ' . $vin;
                }
                $notesLines[] = 'Preferred Slot: ' . ($preferredSlot['display'] ?? 'not selected');
                $notesLines[] = 'Estimate: ' . $estimateNote;
                if ($concern !== '') {
                    $notesLines[] = 'Customer Notes: ' . $concern;
                }
                $notes = implode("\n", $notesLines);
                $assign('notes', $notes);
            }
        }

        // Prepare POST
        $post = [
            'action'    => 'insert',
            'entity_id' => (int)CRM_LEADS_ENTITY_ID,
        ];

        // Authenticate (token via username/password preferred, else API key if configured)
        if (defined('CRM_USERNAME') && CRM_USERNAME && defined('CRM_PASSWORD') && CRM_PASSWORD) {
            $loginCh = curl_init(CRM_API_URL);
            curl_setopt_array($loginCh, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => [
                    'action'   => 'login',
                    'username' => CRM_USERNAME,
                    'password' => CRM_PASSWORD,
                    // Some Rukovoditel setups require API key even for login
                    'key'      => (defined('CRM_API_KEY') ? CRM_API_KEY : ''),
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT => 15,
            ]);
            $loginResp = curl_exec($loginCh);
            $loginHttp = (int)curl_getinfo($loginCh, CURLINFO_HTTP_CODE);
            $loginErr = curl_error($loginCh);
            curl_close($loginCh);
            $loginJson = json_decode((string)$loginResp, true);
            if (is_array($loginJson) && !empty($loginJson['token'])) {
                $post['token'] = (string)$loginJson['token'];
                $post['username'] = CRM_USERNAME;
                $post['password'] = CRM_PASSWORD;
            } elseif (defined('CRM_API_KEY') && CRM_API_KEY) {
                $post['key'] = CRM_API_KEY;
            } else {
                $crm_result = ['error' => 'CRM auth failed', 'login_http' => $loginHttp, 'login_body' => $loginResp, 'login_err' => $loginErr];
            }
        } elseif (defined('CRM_API_KEY') && CRM_API_KEY) {
            $post['key'] = CRM_API_KEY;
            $post['username'] = CRM_USERNAME;
            $post['password'] = CRM_PASSWORD;
        }

        // Attach fields
        $itemPayload = [];
        foreach ($fieldValues as $fid => $val) {
            $itemPayload['field_' . (int)$fid] = $val;
        }
        if (defined('CRM_CREATED_BY_USER_ID')) {
            $itemPayload['created_by'] = (int)CRM_CREATED_BY_USER_ID;
        }
        if (!empty($itemPayload)) {
            foreach ($itemPayload as $fieldKey => $value) {
                $post["items[0][$fieldKey]"] = $value;
            }
        }

        if (!isset($crm_result['error'])) {
            $post['username'] = CRM_USERNAME ?? '';
            $post['password'] = CRM_PASSWORD ?? '';

            $ch2 = curl_init(CRM_API_URL);
            curl_setopt_array($ch2, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $post,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT => 15,
            ]);
            $resp2 = curl_exec($ch2);
            $errno2 = curl_errno($ch2);
            $err2  = curl_error($ch2);
            $http2 = (int)curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            curl_close($ch2);
            $crm_result = [ 'http' => $http2, 'curl_errno' => $errno2, 'curl_error' => $err2, 'body' => $resp2 ];
        }
    }
}

$notificationPayload = [
    'lead' => [
        'name' => $data['name'],
        'phone' => $data['phone'],
        'email' => $data['email'] ?? '',
        'contact_method' => $contactMethod,
        'service_location' => $serviceLocation,
        'repair' => $data['repair'] ?? '',
        'preferred_slot' => $preferredSlot,
        'concern' => $concern,
        'text_opt_in' => $textOptIn,
        'vehicle' => [
            'year' => $data['year'] ?? '',
            'make' => $data['make'] ?? '',
            'model' => $data['model'] ?? '',
            'engine' => $data['engine'] ?? '',
            'vin' => $vin,
            'mileage' => $mileage,
            'labor_hours' => $laborHours,
            'duty_class' => $data['vehicle_class'] ?? '',
        ],
        'address' => [
            'street' => $street,
            'city' => $city,
            'state' => $state,
            'zip' => $zip,
        ],
    ],
    'estimate' => $estimate_summary,
    'estimate_raw' => $estimate_result,
    'sms' => $sms_result,
    'crm' => $crm_result,
    'platform' => $platformForward,
    'meta' => array_merge([
        'received_at' => $receivedAt,
        'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'text_opt_in' => $textOptIn,
    ], $metaOverrides),
];

$email_notification = qi_notify_email($notificationPayload);
$owner_sms_notification = qi_notify_owner_sms($notificationPayload);
$webhook_notification = qi_notify_status_webhook($notificationPayload);

// Build response
$response = [
    'success' => true,
    'message' => 'Quote request received',
    'data' => [
        'name' => $data['name'],
        'phone' => $data['phone'],
        'email' => $data['email'] ?? '',
        'vehicle' => [
            'year' => $data['year'] ?? '',
            'make' => $data['make'] ?? '',
            'model' => $data['model'] ?? '',
            'engine' => $data['engine'] ?? '',
            'duty_class' => $data['vehicle_class'] ?? ''
        ],
        'location' => [
            'street' => $street,
            'city' => $city,
            'state' => $state,
            'zip' => $zip,
            'service_location' => $serviceLocation,
        ],
        'repair' => $data['repair'] ?? '',
        'mileage' => $mileage,
        'labor_hours' => $laborHours,
        'vin' => $vin,
        'contact_method' => $contactMethod,
        'text_opt_in' => $textOptIn,
        'preferred_slot' => $preferredSlot,
        'concern' => $concern,
        'estimate' => $estimate_summary,
        'estimate_raw' => $estimate_result,
        'sms' => $sms_result,
        'crm' => $crm_result,
        'platform' => $platformForward,
        'notifications' => [
            'email' => $email_notification,
            'owner_sms' => $owner_sms_notification,
            'webhook' => $webhook_notification,
        ],
        'meta' => array_merge([
            'received_at' => $receivedAt,
        ], $metaOverrides),
    ]
];

echo json_encode($response, JSON_PRETTY_PRINT);
?>
