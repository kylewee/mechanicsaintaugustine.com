<?php
// Quote intake endpoint: validates input, calls estimator, and (new) creates a CRM lead
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Load CRM config if available
$envFile = __DIR__ . '/.env.local.php';
if (is_file($envFile)) {
    require $envFile;
}

// Helper functions for CRM DB fallback
function crm_db_config(): ?array {
  $file = __DIR__ . '/../crm/config/database.php';
  if (!is_file($file)) return null;
  require_once $file;
  
  if (defined('DB_SERVER') && defined('DB_SERVER_USERNAME') && defined('DB_DATABASE')) {
    return [
      'db_host' => (string)DB_SERVER,
      'db_username' => (string)DB_SERVER_USERNAME,
      'db_password' => (string)(defined('DB_SERVER_PASSWORD') ? DB_SERVER_PASSWORD : ''),
      'db_name' => (string)DB_DATABASE,
    ];
  }
  
  if (defined('DB_HOST') && defined('DB_USERNAME') && defined('DB_NAME')) {
    return [
      'db_host' => (string)DB_HOST,
      'db_username' => (string)DB_USERNAME, 
      'db_password' => (string)(defined('DB_PASSWORD') ? DB_PASSWORD : ''),
      'db_name' => (string)DB_NAME,
    ];
  }
  
  return null;
}

function resolve_field_map(): array {
  $map = [];
  if (defined('CRM_FIELD_MAP') && is_array(CRM_FIELD_MAP)) {
    $map = CRM_FIELD_MAP;
  }
  return $map;
}

function create_crm_lead_db_insert(array $leadData): array {
  if (!defined('CRM_LEADS_ENTITY_ID') || (int)CRM_LEADS_ENTITY_ID <= 0) {
    return ['ok'=>false,'error'=>'leads_entity_id_missing'];
  }

  try {
    $cfg = crm_db_config();
    if (!$cfg) {
      return ['ok'=>false,'error'=>'db_config_missing'];
    }

    $mysqli = @new mysqli($cfg['db_host'], $cfg['db_username'], $cfg['db_password'], $cfg['db_name']);
    if ($mysqli->connect_errno) {
      return ['ok'=>false,'error'=>'db_connect','detail'=>$mysqli->connect_error];
    }

    $entityId = (int)CRM_LEADS_ENTITY_ID;
    $table = 'app_entity_' . $entityId;

    $columns = [];
    $values  = [];
    $types   = '';
    $seenCols = [];

    // Base columns
    $createdBy = defined('CRM_CREATED_BY_USER_ID') ? (int)CRM_CREATED_BY_USER_ID : 1;
    $dateAdded = time();
    foreach ([
      'created_by'     => $createdBy,
      'date_added'     => $dateAdded,
      'parent_item_id' => 0,
      'sort_order'     => 0,
    ] as $col => $val) {
      $columns[] = "`$col`";
      $values[]  = $val;
      $types    .= 'i';
      $seenCols[$col] = true;
    }

    // Synthesize name if missing
    if (empty($leadData['name'])) {
      $fn = trim((string)($leadData['first_name'] ?? ''));
      $ln = trim((string)($leadData['last_name'] ?? ''));
      if ($fn || $ln) $leadData['name'] = trim($fn . ' ' . $ln);
    }

    // Map fields
    $fieldMap = resolve_field_map();
    if (is_array($fieldMap)) {
      foreach ($fieldMap as $key => $fid) {
        $fid = (int)$fid;
        if ($fid <= 0 || !array_key_exists($key, $leadData)) continue;
        $val = (string)$leadData[$key];
        if ($key === 'phone') {
          $val = preg_replace('/[^\d\+]/', '', $val);
        }
        if ($val === '') continue;
        $col = 'field_' . $fid;
        if (isset($seenCols[$col])) continue;
        $seenCols[$col] = true;
        $columns[] = '`' . $col . '`';
        $values[]  = $val;
        $types    .= 's';
      }
    }

    // Handle non-nullable columns without defaults
    $colInfo = $mysqli->query('SHOW COLUMNS FROM `' . $mysqli->real_escape_string($table) . '`');
    if ($colInfo) {
      while ($col = $colInfo->fetch_assoc()) {
        $cname = $col['Field'] ?? '';
        if (strpos($cname, 'field_') !== 0) continue;
        if (isset($seenCols[$cname])) continue;
        $nullable = strtolower((string)($col['Null'] ?? ''));
        $default  = $col['Default'] ?? null;
        if ($nullable === 'no' && $default === null) {
          $ctype = strtolower((string)($col['Type'] ?? ''));
          $isNumeric = (strpos($ctype, 'int') !== false) || (strpos($ctype, 'decimal') !== false);
          $fallbackVal = $isNumeric ? 0 : '';
          $columns[] = '`' . $cname . '`';
          $values[]  = $fallbackVal;
          $types    .= $isNumeric ? 'i' : 's';
          $seenCols[$cname] = true;
        }
      }
      $colInfo->close();
    }

    $placeholders = implode(',', array_fill(0, count($values), '?'));
    $sql = 'INSERT INTO `' . $mysqli->real_escape_string($table) . '` (' . implode(',', $columns) . ') VALUES (' . $placeholders . ')';
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
      $err = $mysqli->error;
      $mysqli->close();
      return ['ok'=>false,'error'=>'prepare','detail'=>$err];
    }

    $stmt->bind_param($types, ...$values);
    if (!$stmt->execute()) {
      $err = $stmt->error;
      $stmt->close();
      $mysqli->close();
      return ['ok'=>false,'error'=>'execute','detail'=>$err];
    }

    $id = $stmt->insert_id;
    $stmt->close();
    $mysqli->close();
    return ['ok'=>true,'id'=>$id];
  } catch (Throwable $e) {
    return ['ok'=>false,'error'=>'exception','detail'=>$e->getMessage()];
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

// Validate required fields
$required = ['name', 'phone'];
foreach ($required as $field) {
    if (empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit;
    }
}

// Extract repair/service info for estimate
$estimate_payload = [
    'repair' => $data['repair'] ?? '',
    'service' => $data['service'] ?? '',
    'year' => (int)($data['year'] ?? 0),
    'make' => $data['make'] ?? '',
    'model' => $data['model'] ?? '',
    'engine' => $data['engine'] ?? '',
    'zip' => $data['zip'] ?? ''
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

// Log the quote request (structured for easy parsing)
$log_entry = [
    'timestamp' => date('c'),
    'level' => 'info',
    'type' => 'quote_intake',
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
        'estimate' => $estimate_result
    ]
];

// Log to error_log (will go to PHP error log or syslog)
error_log('QUOTE_INTAKE: ' . json_encode($log_entry));

// --- Create CRM Lead (if CRM config present) ---
$crm_result = null;
if (
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
            $assign('repair',     (string)($data['repair'] ?? ''));
            if (is_array($estimate_result)) {
                $assign('estimate', (string)($estimate_result['total_high'] ?? $estimate_result['total_low'] ?? ''));
            }
            // If a 'notes' field is mapped, include a compact summary
            if (isset($mapf['notes'])) {
                $notes = 'Quote via website' . "\n" .
                         'Phone: ' . ($data['phone'] ?? '') . "\n" .
                         'Email: ' . ($data['email'] ?? '') . "\n" .
                         'Vehicle: ' . ($data['year'] ?? '') . ' ' . ($data['make'] ?? '') . ' ' . ($data['model'] ?? '') . ' ' . ($data['engine'] ?? '') . "\n" .
                         'Repair: ' . ($data['repair'] ?? '') . "\n" .
                         'Estimate: ' . (is_array($estimate_result) ? json_encode($estimate_result) : '');
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
            } elseif (defined('CRM_API_KEY') && CRM_API_KEY) {
                $post['key'] = CRM_API_KEY;
            } else {
                $crm_result = ['error' => 'CRM auth failed', 'login_http' => $loginHttp, 'login_body' => $loginResp, 'login_err' => $loginErr];
            }
        } elseif (defined('CRM_API_KEY') && CRM_API_KEY) {
            $post['key'] = CRM_API_KEY;
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
            
            $apiJson = $resp2 ? json_decode($resp2, true) : null;
            $crm_result = [ 'http' => $http2, 'curl_errno' => $errno2, 'curl_error' => $err2, 'body' => $resp2 ];
            
            // Check if we should fallback to direct DB insert
            $shouldFallback = ($errno2 !== 0) || ($http2 >= 500) || ($resp2 === false);
            if (!$shouldFallback) {
                if (!is_array($apiJson)) {
                    $shouldFallback = true;
                } elseif (isset($apiJson['status']) && strtolower((string)$apiJson['status']) !== 'success') {
                    $shouldFallback = true;
                    $crm_result['api_error'] = $apiJson;
                }
            }
            
            // Fallback to direct DB insert if API failed
            if ($shouldFallback) {
                $dbRes = create_crm_lead_db_insert([
                    'first_name' => $first,
                    'last_name' => $last ?: $first,
                    'name' => $name,
                    'phone' => $data['phone'] ?? '',
                    'email' => $data['email'] ?? '',
                    'year' => $data['year'] ?? '',
                    'make' => $data['make'] ?? '',
                    'model' => $data['model'] ?? '',
                    'engine' => $data['engine'] ?? '',
                    'notes' => 'Quote via website' . "\n" .
                               'Repair: ' . ($data['repair'] ?? '') . "\n" .
                               'Estimate: ' . (is_array($estimate_result) ? json_encode($estimate_result) : ''),
                ]);
                $crm_result['fallback'] = $dbRes;
            }
        }
    }
}

// Send SMS if opted in
$sms_result = null;
$text_opt_in = !empty($data['text_opt_in']);
if ($text_opt_in && $estimate_result) {
    $twilio_sid = 'REDACTED_TWILIO_SID';
    $twilio_token = getenv('TWILIO_AUTH_TOKEN') ?: '';
    $twilio_from = 'REDACTED_TWILIO_FROM';
    
    if ($twilio_token) {
        // Format estimate message
        $est_low = $estimate_result['total_low'] ?? 0;
        $est_high = $estimate_result['total_high'] ?? 0;
        $message = "Hi {$data['name']}, your {$data['repair']} estimate: $" . number_format($est_low, 2) . " - $" . number_format($est_high, 2) . ". We'll call shortly! - Mechanic St Augustine";
        
        // Ensure phone has +1 prefix
        $phone = $data['phone'];
        if (!str_starts_with($phone, '+')) {
            $phone = '+1' . preg_replace('/[^0-9]/', '', $phone);
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.twilio.com/2010-04-01/Accounts/{$twilio_sid}/Messages.json");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'From' => $twilio_from,
            'To' => $phone,
            'Body' => $message
        ]));
        curl_setopt($ch, CURLOPT_USERPWD, "{$twilio_sid}:{$twilio_token}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $sms_response = curl_exec($ch);
        $sms_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($sms_http_code === 201) {
            $sms_result = ['status' => 'sent'];
        } else {
            $sms_result = [
                'status' => 'error', 
                'reason' => 'twilio_http_' . $sms_http_code,
                'curl_error' => $curl_error,
                'response' => substr($sms_response, 0, 200)
            ];
        }
    } else {
        $sms_result = ['status' => 'error', 'reason' => 'twilio_config_missing'];
    }
}

// Build response with estimate.amount for frontend
$response = [
    'success' => true,
    'message' => 'Quote request received',
    'data' => [
        'name' => $data['name'],
        'phone' => $data['phone'],
        'email' => $data['email'] ?? '',
        'text_opt_in' => $text_opt_in,
        'vehicle' => [
            'year' => $data['year'] ?? '',
            'make' => $data['make'] ?? '',
            'model' => $data['model'] ?? '',
            'engine' => $data['engine'] ?? ''
        ],
        'repair' => $data['repair'] ?? '',
        'estimate' => $estimate_result ? ['amount' => round(($estimate_result['total_low'] + $estimate_result['total_high']) / 2, 2)] : null,
        'estimate_raw' => $estimate_result,
        'sms' => $sms_result,
        'crm' => $crm_result
    ]
];

echo json_encode($response, JSON_PRETTY_PRINT);
?>
