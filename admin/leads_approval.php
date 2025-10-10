<?php
declare(strict_types=1);

require_once __DIR__ . '/../crm/config/database.php';

$mysqli = @new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($mysqli->connect_errno) {
    http_response_code(500);
    die('<h1>Database connection failed</h1><p>' . htmlspecialchars($mysqli->connect_error) . '</p>');
}

function ensureColumns(mysqli $db): void
{
    $check = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'app_entity_26' AND COLUMN_NAME = 'quote_approved'");
    $schema = DB_DATABASE;
    $check->bind_param('s', $schema);
    $check->execute();
    $check->bind_result($count);
    $check->fetch();
    $check->close();

    if ((int)$count === 0) {
        $db->query("ALTER TABLE app_entity_26 ADD COLUMN quote_approved TINYINT(1) NOT NULL DEFAULT 0 AFTER id, ADD COLUMN approved_at DATETIME NULL AFTER quote_approved");
    }
}

function ensurePartsTables(mysqli $db): void
{
    $db->query("CREATE TABLE IF NOT EXISTS parts_orders (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        lead_id INT UNSIGNED NOT NULL,
        status ENUM('requested','ordered','received') NOT NULL DEFAULT 'requested',
        supplier_name VARCHAR(255) DEFAULT NULL,
        supplier_contact VARCHAR(255) DEFAULT NULL,
        requested_at DATETIME DEFAULT NULL,
        ordered_at DATETIME DEFAULT NULL,
        received_at DATETIME DEFAULT NULL,
        notes TEXT,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_lead (lead_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS parts_order_items (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        parts_order_id INT UNSIGNED NOT NULL,
        part_number VARCHAR(128) DEFAULT NULL,
        description VARCHAR(255) DEFAULT NULL,
        quantity DECIMAL(10,2) DEFAULT 1,
        unit_cost DECIMAL(10,2) DEFAULT NULL,
        notes TEXT,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_parts_order (parts_order_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

ensureColumns($mysqli);
ensurePartsTables($mysqli);

function ensurePartsOrder(mysqli $db, int $leadId): void
{
    $stmt = $db->prepare("INSERT INTO parts_orders (lead_id, status, requested_at) VALUES (?, 'requested', NOW())
        ON DUPLICATE KEY UPDATE status='requested', requested_at=IFNULL(requested_at, NOW())");
    $stmt->bind_param('i', $leadId);
    $stmt->execute();
    $stmt->close();
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $leadId = (int)($_POST['lead_id'] ?? 0);
    if ($leadId > 0) {
        if (isset($_POST['approve'])) {
            ensurePartsOrder($mysqli, $leadId);
            $stmt = $mysqli->prepare("UPDATE app_entity_26 SET quote_approved = 1, approved_at = NOW() WHERE id = ?");
            $stmt->bind_param('i', $leadId);
            $stmt->execute();
            $stmt->close();
            $message = 'Quote #' . $leadId . ' marked as approved.';
        } elseif (isset($_POST['reopen'])) {
            $stmt = $mysqli->prepare("UPDATE app_entity_26 SET quote_approved = 0, approved_at = NULL WHERE id = ?");
            $stmt->bind_param('i', $leadId);
            $stmt->execute();
            $stmt->close();
            $stmt = $mysqli->prepare("UPDATE parts_orders SET status='requested', ordered_at=NULL, received_at=NULL WHERE lead_id = ?");
            $stmt->bind_param('i', $leadId);
            $stmt->execute();
            $stmt->close();
            $message = 'Quote #' . $leadId . ' reopened.';
        }
    }
}

$focusId = isset($_GET['focus']) ? (int)$_GET['focus'] : 0;

$result = $mysqli->query("SELECT id, field_219 AS first_name, field_220 AS last_name, field_227 AS phone, field_232 AS make, field_233 AS model, field_231 AS vehicle_year, field_230 AS notes, quote_approved, approved_at FROM app_entity_26 ORDER BY id DESC LIMIT 200");
$leads = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
if ($result) { $result->free(); }

$mysqli->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Lead Approvals · Mechanics Saint Augustine</title>
  <style>
    :root {
      color-scheme: light dark;
      --bg: #0f172a;
      --panel: rgba(15, 23, 42, 0.85);
      --panel-muted: rgba(15, 23, 42, 0.55);
      --accent: #2dd4bf;
      --text: #e2e8f0;
      --muted: #94a3b8;
      --border: rgba(148, 163, 184, 0.25);
      font-family: "Inter", "Segoe UI", system-ui, -apple-system, sans-serif;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      min-height: 100vh;
      background: radial-gradient(circle at top, rgba(45, 212, 191, 0.2), transparent 60%),
                  radial-gradient(circle at bottom, rgba(14, 165, 233, 0.2), transparent 60%),
                  var(--bg);
      color: var(--text);
      padding: 48px 18px 64px;
      display: flex;
      flex-direction: column;
      gap: 28px;
      align-items: center;
    }
    header {
      text-align: center;
      max-width: 960px;
    }
    header h1 { margin: 0 0 8px; letter-spacing: 0.05em; }
    header p { margin: 0; color: var(--muted); }
    main {
      width: min(1100px, 100%);
      background: var(--panel);
      border-radius: 18px;
      border: 1px solid var(--border);
      padding: 24px 28px;
      box-shadow: 0 24px 60px rgba(15, 23, 42, 0.45);
      backdrop-filter: blur(10px);
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 16px;
    }
    th, td {
      padding: 12px;
      border-bottom: 1px solid rgba(148, 163, 184, 0.18);
      text-align: left;
      vertical-align: top;
    }
    th { color: var(--muted); font-weight: 600; font-size: 0.85rem; letter-spacing: 0.04em; }
    tr.approved { background: rgba(45, 212, 191, 0.12); }
    tr.focused { outline: 2px solid rgba(59, 130, 246, 0.6); }
    .badge {
      display: inline-flex;
      align-items: center;
      padding: 2px 10px;
      border-radius: 999px;
      background: rgba(45, 212, 191, 0.15);
      color: var(--accent);
      font-size: 0.75rem;
      letter-spacing: 0.04em;
      text-transform: uppercase;
    }
    .actions form {
      display: inline-flex;
      gap: 8px;
    }
    button {
      border: 1px solid rgba(45, 212, 191, 0.45);
      background: rgba(45, 212, 191, 0.12);
      color: var(--text);
      border-radius: 10px;
      padding: 6px 12px;
      cursor: pointer;
      font-size: 0.85rem;
    }
    button:hover { background: rgba(45, 212, 191, 0.2); }
    .message {
      padding: 12px 16px;
      border-radius: 12px;
      border: 1px solid rgba(45, 212, 191, 0.3);
      background: rgba(45, 212, 191, 0.12);
      color: var(--accent);
      margin-bottom: 12px;
      text-align: center;
    }
    footer { color: var(--muted); font-size: 0.85rem; margin-top: 24px; text-align: center; }
    a { color: var(--accent); text-decoration: none; }
    a:hover { text-decoration: underline; }
    @media (max-width: 880px) {
      table { font-size: 0.92rem; }
      th, td { padding: 10px; }
    }
  </style>
</head>
<body>
  <header>
    <h1>Lead Approvals</h1>
    <p>Mark quotes as approved to kick off the parts-order workflow.</p>
  </header>

  <main>
    <?php if ($message): ?>
      <div class="message"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Customer</th>
          <th>Vehicle</th>
          <th>Phone</th>
          <th>Notes</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($leads as $lead): ?>
          <tr class="<?php echo $lead['quote_approved'] ? 'approved' : ''; ?><?php echo ($focusId === (int)$lead['id']) ? ' focused' : ''; ?>">
            <td>#<?php echo (int)$lead['id']; ?></td>
            <td>
              <?php echo htmlspecialchars(trim(($lead['first_name'] ?? '') . ' ' . ($lead['last_name'] ?? ''))); ?><br>
              <?php if (!empty($lead['approved_at'])): ?>
                <small class="badge">Approved <?php echo htmlspecialchars($lead['approved_at']); ?></small>
              <?php endif; ?>
            </td>
            <td>
              <?php echo htmlspecialchars(($lead['vehicle_year'] ?? '') . ' ' . ($lead['make'] ?? '') . ' ' . ($lead['model'] ?? '')); ?>
            </td>
            <td><?php echo htmlspecialchars($lead['phone'] ?? ''); ?></td>
            <td style="max-width: 260px; white-space: pre-wrap;"><?php echo htmlspecialchars($lead['notes'] ?? ''); ?></td>
            <td><?php echo $lead['quote_approved'] ? 'Approved' : 'Pending'; ?></td>
            <td class="actions">
              <form method="post">
                <input type="hidden" name="lead_id" value="<?php echo (int)$lead['id']; ?>" />
                <?php if ($lead['quote_approved']): ?>
                  <button type="submit" name="reopen">Reopen</button>
                <?php else: ?>
                  <button type="submit" name="approve">Approve</button>
                <?php endif; ?>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </main>

  <footer>
    <a href="/admin/">&larr; Back to Admin Dashboard</a>
  </footer>
</body>
</html>
