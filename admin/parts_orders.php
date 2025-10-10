<?php
declare(strict_types=1);

require_once __DIR__ . '/../crm/config/database.php';

$mysqli = @new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($mysqli->connect_errno) {
    http_response_code(500);
    die('<h1>Database connection failed</h1><p>' . htmlspecialchars($mysqli->connect_error) . '</p>');
}

$mysqli->query("CREATE TABLE IF NOT EXISTS parts_orders (
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

$mysqli->query("CREATE TABLE IF NOT EXISTS parts_order_items (
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

$message = '';

function formatMoney(?float $amount): string
{
    if ($amount === null) {
        return '—';
    }
    return '$' . number_format($amount, 2);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_order'])) {
        $leadId = (int)($_POST['lead_id'] ?? 0);
        if ($leadId > 0) {
            $status = $_POST['status'] ?? 'requested';
            if (!in_array($status, ['requested','ordered','received'], true)) {
                $status = 'requested';
            }
            $supplierName = trim($_POST['supplier_name'] ?? '');
            $supplierContact = trim($_POST['supplier_contact'] ?? '');
            $notes = trim($_POST['notes'] ?? '');

            $stmt = $mysqli->prepare("SELECT status, requested_at, ordered_at, received_at FROM parts_orders WHERE lead_id = ?");
            $stmt->bind_param('i', $leadId);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$existing) {
                $stmt = $mysqli->prepare("INSERT INTO parts_orders (lead_id, status, supplier_name, supplier_contact, notes, requested_at, ordered_at, received_at)
                    VALUES (?, ?, ?, ?, ?, NULL, NULL, NULL)");
                $stmt->bind_param('issss', $leadId, $status, $supplierName, $supplierContact, $notes);
                $stmt->execute();
                $stmt->close();
                $existing = ['requested_at' => null, 'ordered_at' => null, 'received_at' => null];
            }

            $requestedAt = $existing['requested_at'];
            $orderedAt = $existing['ordered_at'];
            $receivedAt = $existing['received_at'];

            $now = date('Y-m-d H:i:s');
            if ($status === 'requested' && !$requestedAt) {
                $requestedAt = $now;
                $orderedAt = null;
                $receivedAt = null;
            } elseif ($status === 'ordered') {
                if (!$requestedAt) { $requestedAt = $now; }
                if (!$orderedAt) { $orderedAt = $now; }
                $receivedAt = null;
            } elseif ($status === 'received') {
                if (!$requestedAt) { $requestedAt = $now; }
                if (!$orderedAt) { $orderedAt = $now; }
                if (!$receivedAt) { $receivedAt = $now; }
            }

            $stmt = $mysqli->prepare("UPDATE parts_orders SET status=?, supplier_name=?, supplier_contact=?, notes=?, requested_at=?, ordered_at=?, received_at=?, updated_at=NOW() WHERE lead_id=?");
            $stmt->bind_param('sssssssi', $status, $supplierName, $supplierContact, $notes, $requestedAt, $orderedAt, $receivedAt, $leadId);
            $stmt->execute();
            $stmt->close();

            $message = "Parts order for lead #{$leadId} updated.";
        }
    } elseif (isset($_POST['item_action'])) {
        $orderId = (int)($_POST['order_id'] ?? 0);
        if ($orderId > 0) {
            $action = $_POST['item_action'];
            if ($action === 'add') {
                $partNumber = trim($_POST['part_number'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $quantity = (float)($_POST['quantity'] ?? 1);
                if ($quantity <= 0) {
                    $quantity = 1.0;
                }
                $unitCostRaw = trim($_POST['unit_cost'] ?? '');
                $notes = trim($_POST['item_notes'] ?? '');

                $stmt = $mysqli->prepare("INSERT INTO parts_order_items (parts_order_id, part_number, description, quantity, unit_cost, notes) VALUES (?, ?, ?, ?, NULLIF(?, ''), ?)");
                $stmt->bind_param('issdss', $orderId, $partNumber, $description, $quantity, $unitCostRaw, $notes);
                $stmt->execute();
                $stmt->close();
                $message = "Item added to order #{$orderId}.";
            } elseif ($action === 'update') {
                $itemId = (int)($_POST['item_id'] ?? 0);
                if ($itemId > 0) {
                    $partNumber = trim($_POST['part_number'] ?? '');
                    $description = trim($_POST['description'] ?? '');
                    $quantity = (float)($_POST['quantity'] ?? 1);
                    if ($quantity <= 0) {
                        $quantity = 1.0;
                    }
                    $unitCostRaw = trim($_POST['unit_cost'] ?? '');
                    $notes = trim($_POST['item_notes'] ?? '');

                    $stmt = $mysqli->prepare("UPDATE parts_order_items SET part_number=?, description=?, quantity=?, unit_cost=NULLIF(?, ''), notes=?, updated_at=NOW() WHERE id=? AND parts_order_id=?");
                    $stmt->bind_param('ssdssii', $partNumber, $description, $quantity, $unitCostRaw, $notes, $itemId, $orderId);
                    $stmt->execute();
                    $stmt->close();
                    $message = "Item updated.";
                }
            } elseif ($action === 'delete') {
                $itemId = (int)($_POST['item_id'] ?? 0);
                if ($itemId > 0) {
                    $stmt = $mysqli->prepare("DELETE FROM parts_order_items WHERE id=? AND parts_order_id=?");
                    $stmt->bind_param('ii', $itemId, $orderId);
                    $stmt->execute();
                    $stmt->close();
                    $message = "Item removed.";
                }
            }
        }
    }
}

$result = $mysqli->query("SELECT po.*, lead.field_219 AS first_name, lead.field_220 AS last_name, lead.field_227 AS phone,
       lead.field_232 AS make, lead.field_233 AS model, lead.field_231 AS vehicle_year
     FROM parts_orders po
     LEFT JOIN app_entity_26 lead ON lead.id = po.lead_id
     ORDER BY po.updated_at DESC");
$orders = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
if ($result) { $result->free(); }

$itemsByOrder = [];
if (!empty($orders)) {
    $orderIds = array_column($orders, 'id');
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $types = str_repeat('i', count($orderIds));
    $stmt = $mysqli->prepare("SELECT * FROM parts_order_items WHERE parts_order_id IN ($placeholders) ORDER BY created_at ASC");
    $stmt->bind_param($types, ...$orderIds);
    $stmt->execute();
    $itemsResult = $stmt->get_result();
    while ($row = $itemsResult->fetch_assoc()) {
        $itemsByOrder[$row['parts_order_id']][] = $row;
    }
    $stmt->close();
}

$mysqli->close();

$focusLeadId = isset($_GET['focus']) ? (int)$_GET['focus'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Parts Orders · Mechanics Saint Augustine</title>
  <style>
    :root {
      color-scheme: light dark;
      --bg: #0f172a;
      --panel: rgba(15, 23, 42, 0.85);
      --accent: #fbbf24;
      --text: #e2e8f0;
      --muted: #94a3b8;
      --border: rgba(148, 163, 184, 0.25);
      font-family: "Inter", "Segoe UI", system-ui, -apple-system, sans-serif;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      min-height: 100vh;
      background: radial-gradient(circle at top, rgba(251, 191, 36, 0.2), transparent 60%),
                  radial-gradient(circle at bottom, rgba(59, 130, 246, 0.2), transparent 65%),
                  var(--bg);
      color: var(--text);
      padding: 48px 18px 64px;
      display: flex;
      flex-direction: column;
      gap: 28px;
      align-items: center;
    }
    header { text-align: center; max-width: 960px; }
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
    .message {
      padding: 12px 16px;
      border-radius: 12px;
      border: 1px solid rgba(251, 191, 36, 0.3);
      background: rgba(251, 191, 36, 0.12);
      color: var(--accent);
      margin-bottom: 16px;
      text-align: center;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 12px;
    }
    th, td {
      padding: 12px;
      border-bottom: 1px solid rgba(148, 163, 184, 0.16);
      text-align: left;
      vertical-align: top;
    }
    th { color: var(--muted); font-weight: 600; font-size: 0.85rem; letter-spacing: 0.04em; }
    form.order-form {
      display: grid;
      gap: 10px;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      align-items: start;
    }
    form.order-form textarea {
      grid-column: 1 / -1;
      min-height: 90px;
      resize: vertical;
      border-radius: 10px;
      border: 1px solid var(--border);
      background: rgba(15, 23, 42, 0.65);
      color: var(--text);
      padding: 10px 12px;
      font-size: 0.92rem;
    }
    .items-block {
      margin-top: 18px;
      border: 1px solid rgba(148, 163, 184, 0.2);
      border-radius: 12px;
      padding: 16px;
      background: rgba(15, 23, 42, 0.62);
      display: grid;
      gap: 12px;
    }
    .items-block h4 {
      margin: 0;
      font-size: 1rem;
      letter-spacing: 0.03em;
      text-transform: uppercase;
      color: var(--muted);
    }
    .item-inline-form {
      display: grid;
      gap: 10px;
      grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
      align-items: end;
      background: rgba(15, 23, 42, 0.56);
      border: 1px solid rgba(148, 163, 184, 0.15);
      border-radius: 12px;
      padding: 12px;
    }
    .item-inline-form label {
      display: flex;
      flex-direction: column;
      gap: 6px;
      font-size: 0.82rem;
      color: var(--muted);
    }
    .item-inline-form input,
    .item-inline-form textarea {
      width: 100%;
      border-radius: 8px;
      border: 1px solid var(--border);
      background: rgba(15, 23, 42, 0.65);
      color: var(--text);
      padding: 6px 8px;
      font-size: 0.88rem;
      resize: none;
    }
    .item-inline-form textarea {
      min-height: 60px;
      grid-column: 1 / -1;
    }
    .item-inline-form .item-inline-actions {
      display: flex;
      gap: 10px;
      align-items: center;
      flex-wrap: wrap;
      grid-column: 1 / -1;
    }
    .item-inline-form .line-total {
      font-weight: 600;
      font-size: 0.9rem;
      color: var(--accent);
    }
    .item-inline-form button {
      padding: 6px 12px;
      border-radius: 8px;
      border: 1px solid rgba(251, 191, 36, 0.45);
      background: rgba(251, 191, 36, 0.12);
      color: var(--text);
      font-size: 0.86rem;
      cursor: pointer;
    }
    .item-inline-form button:hover { background: rgba(251, 191, 36, 0.2); }
    .item-inline-form button.danger {
      border-color: rgba(239, 68, 68, 0.45);
      background: rgba(239, 68, 68, 0.12);
    }
    .item-inline-form button.danger:hover { background: rgba(239, 68, 68, 0.2); }
    .item-inline-form--new {
      background: rgba(56, 189, 248, 0.06);
      border-style: dashed;
    }
    .items-empty {
      color: var(--muted);
      font-size: 0.88rem;
    }
    .items-totals {
      display: flex;
      justify-content: space-between;
      gap: 16px;
      flex-wrap: wrap;
      font-size: 0.9rem;
      color: var(--muted);
    }
    .items-totals strong { color: var(--text); }
    .items-actions {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
    }
    .items-actions a {
      padding: 8px 14px;
      border-radius: 10px;
      border: 1px solid rgba(251, 191, 36, 0.45);
      color: var(--text);
      text-decoration: none;
      font-size: 0.9rem;
    }
    .items-actions a:hover {
      background: rgba(251, 191, 36, 0.18);
    }
    input, select {
      width: 100%;
      border-radius: 10px;
      border: 1px solid var(--border);
      background: rgba(15, 23, 42, 0.65);
      color: var(--text);
      padding: 8px 10px;
      font-size: 0.9rem;
    }
    button {
      padding: 8px 14px;
      border-radius: 10px;
      border: 1px solid rgba(251, 191, 36, 0.45);
      background: rgba(251, 191, 36, 0.12);
      color: var(--text);
      font-size: 0.9rem;
      cursor: pointer;
      justify-self: start;
    }
    button:hover { background: rgba(251, 191, 36, 0.18); }
    .dates small { display: block; color: var(--muted); font-size: 0.78rem; }
    tr.focused { outline: 2px solid rgba(59, 130, 246, 0.6); }
    footer { color: var(--muted); font-size: 0.85rem; margin-top: 24px; text-align: center; }
    a { color: var(--accent); text-decoration: none; }
    a:hover { text-decoration: underline; }
  </style>
</head>
<body>
  <header>
    <h1>Parts Orders</h1>
    <p>Track workflow from quote approval through ordered and received parts.</p>
  </header>

  <main>
    <?php if ($message): ?>
      <div class="message"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <table>
      <thead>
        <tr>
          <th>Lead</th>
          <th>Vehicle</th>
          <th>Supplier &amp; Contact</th>
          <th>Status</th>
          <th>Timestamps</th>
          <th>Notes / Update</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($orders as $order): ?>
          <?php
            $orderItems = $itemsByOrder[$order['id']] ?? [];
            $totalQuantity = 0.0;
            $totalCost = 0.0;
            foreach ($orderItems as $item) {
                $qty = (float)($item['quantity'] ?? 0);
                $unit = $item['unit_cost'] !== null ? (float)$item['unit_cost'] : 0.0;
                $totalQuantity += $qty;
                $totalCost += $qty * $unit;
            }
          ?>
          <tr id="order-lead-<?php echo (int)$order['lead_id']; ?>" class="<?php echo $focusLeadId === (int)$order['lead_id'] ? 'focused' : ''; ?>">
            <td>
              #<?php echo (int)$order['lead_id']; ?><br>
              <?php echo htmlspecialchars(trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''))); ?><br>
              <?php if (!empty($order['phone'])): ?>
                <small><?php echo htmlspecialchars($order['phone']); ?></small>
              <?php endif; ?><br>
              <a href="/admin/leads_approval.php?focus=<?php echo (int)$order['lead_id']; ?>">View approval</a>
            </td>
            <td><?php echo htmlspecialchars(($order['vehicle_year'] ?? '') . ' ' . ($order['make'] ?? '') . ' ' . ($order['model'] ?? '')); ?></td>
            <td>
              <strong><?php echo htmlspecialchars($order['supplier_name'] ?? ''); ?></strong><br>
              <small><?php echo htmlspecialchars($order['supplier_contact'] ?? ''); ?></small>
            </td>
            <td>
              <strong><?php echo ucfirst($order['status']); ?></strong>
            </td>
            <td class="dates">
              <small>Requested: <?php echo $order['requested_at'] ? htmlspecialchars($order['requested_at']) : '—'; ?></small>
              <small>Ordered: <?php echo $order['ordered_at'] ? htmlspecialchars($order['ordered_at']) : '—'; ?></small>
              <small>Received: <?php echo $order['received_at'] ? htmlspecialchars($order['received_at']) : '—'; ?></small>
            </td>
            <td>
              <form method="post" class="order-form">
                <input type="hidden" name="lead_id" value="<?php echo (int)$order['lead_id']; ?>">
                <label>
                  Supplier Name
                  <input type="text" name="supplier_name" value="<?php echo htmlspecialchars($order['supplier_name'] ?? ''); ?>" />
                </label>
                <label>
                  Supplier Contact
                  <input type="text" name="supplier_contact" value="<?php echo htmlspecialchars($order['supplier_contact'] ?? ''); ?>" />
                </label>
                <label>
                  Status
                  <select name="status">
                    <option value="requested" <?php echo $order['status'] === 'requested' ? 'selected' : ''; ?>>Requested</option>
                    <option value="ordered" <?php echo $order['status'] === 'ordered' ? 'selected' : ''; ?>>Ordered</option>
                    <option value="received" <?php echo $order['status'] === 'received' ? 'selected' : ''; ?>>Received</option>
                  </select>
                </label>
                <textarea name="notes" placeholder="Notes &amp; updates (ETA, tracking, etc.)"><?php echo htmlspecialchars($order['notes'] ?? ''); ?></textarea>
                <button type="submit">Save</button>
              </form>
              <div class="items-block" id="items-<?php echo (int)$order['id']; ?>">
                <h4>Parts Line Items</h4>
                <?php if (!empty($orderItems)): ?>
                  <?php foreach ($orderItems as $item): ?>
                    <?php
                      $qty = (float)($item['quantity'] ?? 0);
                      $unit = $item['unit_cost'] !== null ? (float)$item['unit_cost'] : 0.0;
                      $lineTotal = $qty * $unit;
                    ?>
                    <form method="post" class="item-inline-form">
                      <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
                      <input type="hidden" name="item_id" value="<?php echo (int)$item['id']; ?>">
                      <label>
                        Part #
                        <input type="text" name="part_number" value="<?php echo htmlspecialchars($item['part_number'] ?? ''); ?>" />
                      </label>
                      <label>
                        Description
                        <input type="text" name="description" value="<?php echo htmlspecialchars($item['description'] ?? ''); ?>" />
                      </label>
                      <label>
                        Quantity
                        <input type="number" step="0.01" name="quantity" value="<?php echo htmlspecialchars((string)($item['quantity'] ?? '1')); ?>" />
                      </label>
                      <label>
                        Unit Cost
                        <input type="number" step="0.01" name="unit_cost" value="<?php echo $item['unit_cost'] !== null ? htmlspecialchars((string)$item['unit_cost']) : ''; ?>" />
                      </label>
                      <textarea name="item_notes" placeholder="Notes"><?php echo htmlspecialchars($item['notes'] ?? ''); ?></textarea>
                      <div class="item-inline-actions">
                        <span class="line-total">Line: <?php echo formatMoney($lineTotal); ?></span>
                        <button type="submit" name="item_action" value="update">Save</button>
                        <button type="submit" name="item_action" value="delete" class="danger" onclick="return confirm('Remove this item?');">Delete</button>
                      </div>
                    </form>
                  <?php endforeach; ?>
                <?php else: ?>
                  <p class="items-empty">No parts listed yet. Add the components you plan to order.</p>
                <?php endif; ?>

                <form method="post" class="item-inline-form item-inline-form--new">
                  <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
                  <input type="hidden" name="item_action" value="add">
                  <label>
                    Part #
                    <input type="text" name="part_number" placeholder="e.g. 123-ABC" />
                  </label>
                  <label>
                    Description
                    <input type="text" name="description" placeholder="Brake rotor front (left)" />
                  </label>
                  <label>
                    Quantity
                    <input type="number" step="0.01" name="quantity" value="1" />
                  </label>
                  <label>
                    Unit Cost
                    <input type="number" step="0.01" name="unit_cost" placeholder="125.00" />
                  </label>
                  <textarea name="item_notes" placeholder="Notes (core fees, supplier SKU, etc.)"></textarea>
                  <div class="item-inline-actions">
                    <span class="line-total">Line: <?php echo formatMoney(0); ?></span>
                    <button type="submit">Add Item</button>
                  </div>
                </form>

                <div class="items-totals">
                  <span>Total Qty: <strong><?php echo rtrim(rtrim(number_format($totalQuantity, 2), '0'), '.'); ?></strong></span>
                  <span>Parts Total: <strong><?php echo formatMoney($totalCost); ?></strong></span>
                </div>
                <div class="items-actions">
                  <a href="/admin/parts_order_export.php?order_id=<?php echo (int)$order['id']; ?>" target="_blank" rel="noopener">Supplier Export / Print</a>
                  <a href="/admin/parts_order_export.php?order_id=<?php echo (int)$order['id']; ?>#email-template" target="_blank" rel="noopener">Email Draft</a>
                </div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($orders)): ?>
          <tr><td colspan="6">No parts orders yet. Approve a quote to create one.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </main>

  <footer>
    <a href="/admin/">&larr; Back to Admin Dashboard</a>
  </footer>
</body>
</html>
