<?php
declare(strict_types=1);

require_once __DIR__ . '/../crm/config/database.php';

function formatMoney(?float $amount): string
{
    if ($amount === null) {
        return '—';
    }
    return '$' . number_format($amount, 2);
}

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$download = isset($_GET['download']);

if ($orderId <= 0) {
    http_response_code(400);
    echo '<h1>Missing order_id</h1>';
    exit;
}

$mysqli = @new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($mysqli->connect_errno) {
    http_response_code(500);
    echo '<h1>Database connection failed</h1>';
    exit;
}

$orderStmt = $mysqli->prepare("SELECT po.*, lead.field_219 AS first_name, lead.field_220 AS last_name, lead.field_227 AS phone,
       lead.field_232 AS make, lead.field_233 AS model, lead.field_231 AS vehicle_year
    FROM parts_orders po
    LEFT JOIN app_entity_26 lead ON lead.id = po.lead_id
    WHERE po.id = ?");
$orderStmt->bind_param('i', $orderId);
$orderStmt->execute();
$orderResult = $orderStmt->get_result();
$order = $orderResult->fetch_assoc();
$orderStmt->close();

if (!$order) {
    http_response_code(404);
    echo '<h1>Parts order not found</h1>';
    $mysqli->close();
    exit;
}

$itemStmt = $mysqli->prepare("SELECT * FROM parts_order_items WHERE parts_order_id = ? ORDER BY created_at ASC");
$itemStmt->bind_param('i', $orderId);
$itemStmt->execute();
$itemsResult = $itemStmt->get_result();
$items = [];
while ($row = $itemsResult->fetch_assoc()) {
    $items[] = $row;
}
$itemStmt->close();
$mysqli->close();

$customerName = trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''));
$vehicle = trim(($order['vehicle_year'] ?? '') . ' ' . ($order['make'] ?? '') . ' ' . ($order['model'] ?? ''));
$leadId = (int)($order['lead_id'] ?? 0);
$status = ucfirst((string)($order['status'] ?? 'requested'));

$totalQty = 0.0;
$totalCost = 0.0;
foreach ($items as $line) {
    $qty = (float)($line['quantity'] ?? 0);
    $unit = $line['unit_cost'] !== null ? (float)$line['unit_cost'] : 0.0;
    $totalQty += $qty;
    $totalCost += $qty * $unit;
}

$emailLines = [];
foreach ($items as $line) {
    $qty = (float)($line['quantity'] ?? 0);
    $unit = $line['unit_cost'] !== null ? (float)$line['unit_cost'] : 0.0;
    $lineTotal = $qty * $unit;
    $emailLines[] = sprintf('- %s x%s @ %s · %s%s',
        $line['description'] !== '' ? $line['description'] : ($line['part_number'] ?: 'Part'),
        rtrim(rtrim(number_format($qty, 2), '0'), '.'),
        $unit ? '$' . number_format($unit, 2) : 'N/A',
        $line['part_number'] ? 'SKU ' . $line['part_number'] . ' · ' : '',
        $lineTotal ? '$' . number_format($lineTotal, 2) : ''
    );
}

$emailBody = [];
$supplierGreeting = $order['supplier_name'] ? 'Hello ' . $order['supplier_name'] . ',' : 'Hello,';
$emailBody[] = $supplierGreeting;
$emailBody[] = '';
$emailBody[] = 'Please confirm pricing and availability for the following parts:';
$emailBody[] = '';
$emailBody = array_merge($emailBody, $emailLines ?: ['- (No line items yet)']);
$emailBody[] = '';
$emailBody[] = 'Vehicle: ' . ($vehicle ?: 'N/A');
$emailBody[] = 'Lead #: ' . ($leadId ?: 'N/A');
$emailBody[] = 'Customer: ' . ($customerName ?: 'N/A');
$emailBody[] = 'Phone: ' . ($order['phone'] ?? '—');
$emailBody[] = '';
if (!empty($order['notes'])) {
    $emailBody[] = 'Internal Notes: ' . $order['notes'];
    $emailBody[] = '';
}
$emailBody[] = 'Thank you!';
$emailBody[] = 'Mechanics Saint Augustine';

$emailText = implode("\n", $emailBody);
$mailto = 'mailto:';
if (!empty($order['supplier_contact']) && strpos($order['supplier_contact'], '@') !== false) {
    $mailto .= rawurlencode($order['supplier_contact']);
}
$subjectVehicle = $vehicle ?: 'parts order';
$mailto .= '?subject=' . rawurlencode('Parts order request - ' . $subjectVehicle);
$mailto .= '&body=' . rawurlencode($emailText);

if ($download) {
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="parts-order-' . $orderId . '.html"');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Parts Order Export · Lead #<?php echo htmlspecialchars((string)$leadId); ?></title>
  <style>
    :root {
      color-scheme: light dark;
      --bg: #0f172a;
      --panel: rgba(15, 23, 42, 0.92);
      --text: #e2e8f0;
      --muted: #94a3b8;
      --accent: #fbbf24;
      --border: rgba(148, 163, 184, 0.25);
      font-family: "Inter", "Segoe UI", system-ui, -apple-system, sans-serif;
    }
    body {
      margin: 0;
      padding: 36px 18px 64px;
      background: var(--bg);
      color: var(--text);
      display: flex;
      justify-content: center;
    }
    main {
      width: min(880px, 100%);
      background: var(--panel);
      border: 1px solid var(--border);
      border-radius: 18px;
      padding: 32px;
      box-shadow: 0 24px 60px rgba(15, 23, 42, 0.45);
      backdrop-filter: blur(12px);
    }
    header {
      display: flex;
      flex-direction: column;
      gap: 12px;
      margin-bottom: 24px;
    }
    header h1 { margin: 0; font-size: 1.8rem; letter-spacing: 0.05em; }
    header p { margin: 0; color: var(--muted); }
    .meta {
      display: grid;
      gap: 10px;
      margin-bottom: 24px;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    }
    .meta div {
      background: rgba(15, 23, 42, 0.6);
      border: 1px solid rgba(148, 163, 184, 0.2);
      border-radius: 12px;
      padding: 12px 14px;
    }
    .meta div strong { display: block; font-size: 0.78rem; letter-spacing: 0.08em; color: var(--muted); text-transform: uppercase; }
    .meta div span { font-size: 1rem; }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 18px;
    }
    th, td {
      padding: 12px;
      border-bottom: 1px solid rgba(148, 163, 184, 0.18);
      text-align: left;
    }
    th { color: var(--muted); font-size: 0.85rem; letter-spacing: 0.04em; }
    .totals {
      display: flex;
      justify-content: flex-end;
      gap: 24px;
      font-size: 1rem;
      color: var(--muted);
      margin-bottom: 24px;
    }
    .totals strong { color: var(--text); }
    .controls {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      margin-bottom: 24px;
    }
    .controls a, .controls button {
      padding: 10px 16px;
      border-radius: 10px;
      border: 1px solid rgba(251, 191, 36, 0.45);
      background: rgba(251, 191, 36, 0.12);
      color: var(--text);
      text-decoration: none;
      font-size: 0.95rem;
      cursor: pointer;
    }
    .controls button { background: rgba(56, 189, 248, 0.15); border-color: rgba(56, 189, 248, 0.45); }
    pre {
      white-space: pre-wrap;
      background: rgba(15, 23, 42, 0.6);
      border: 1px solid rgba(148, 163, 184, 0.25);
      border-radius: 12px;
      padding: 16px;
      font-size: 0.95rem;
      color: var(--text);
    }
    @media print {
      body { background: #fff; color: #000; }
      main { box-shadow: none; border: none; background: #fff; color: #000; }
      .controls { display: none; }
      th, td { border-color: #d1d5db; }
    }
  </style>
</head>
<body>
  <main>
    <header>
      <h1>Parts Order · Lead #<?php echo htmlspecialchars((string)$leadId); ?></h1>
      <p>Status: <?php echo htmlspecialchars($status); ?> &middot; Generated <?php echo htmlspecialchars(date('Y-m-d H:i')); ?></p>
    </header>

    <div class="controls">
      <a href="<?php echo htmlspecialchars($mailto); ?>">Open Email Draft</a>
      <button type="button" onclick="window.print()">Print / Save as PDF</button>
      <a href="/admin/parts_order_export.php?order_id=<?php echo (int)$orderId; ?>&download=1">Download HTML</a>
      <a href="/admin/parts_orders.php#order-<?php echo (int)$orderId; ?>">Back to Tracker</a>
    </div>

    <div class="meta">
      <div>
        <strong>Supplier</strong>
        <span><?php echo htmlspecialchars($order['supplier_name'] ?: '—'); ?></span><br>
        <span><?php echo htmlspecialchars($order['supplier_contact'] ?: ''); ?></span>
      </div>
      <div>
        <strong>Vehicle</strong>
        <span><?php echo htmlspecialchars($vehicle ?: '—'); ?></span>
      </div>
      <div>
        <strong>Customer</strong>
        <span><?php echo htmlspecialchars($customerName ?: '—'); ?></span><br>
        <span><?php echo htmlspecialchars($order['phone'] ?: ''); ?></span>
      </div>
      <div>
        <strong>Requested</strong>
        <span><?php echo htmlspecialchars($order['requested_at'] ?: '—'); ?></span><br>
        <strong>Ordered</strong>
        <span><?php echo htmlspecialchars($order['ordered_at'] ?: '—'); ?></span><br>
        <strong>Received</strong>
        <span><?php echo htmlspecialchars($order['received_at'] ?: '—'); ?></span>
      </div>
    </div>

    <table>
      <thead>
        <tr>
          <th>Part #</th>
          <th>Description</th>
          <th>Qty</th>
          <th>Unit Cost</th>
          <th>Line Total</th>
          <th>Notes</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($items)): ?>
          <?php foreach ($items as $line): ?>
            <?php
              $qty = (float)($line['quantity'] ?? 0);
              $unit = $line['unit_cost'] !== null ? (float)$line['unit_cost'] : 0.0;
              $lineTotal = $qty * $unit;
            ?>
            <tr>
              <td><?php echo htmlspecialchars($line['part_number'] ?? ''); ?></td>
              <td><?php echo htmlspecialchars($line['description'] ?? ''); ?></td>
              <td><?php echo htmlspecialchars(rtrim(rtrim(number_format($qty, 2), '0'), '.')); ?></td>
              <td><?php echo $unit ? formatMoney($unit) : '—'; ?></td>
              <td><?php echo $lineTotal ? formatMoney($lineTotal) : '—'; ?></td>
              <td><?php echo htmlspecialchars($line['notes'] ?? ''); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="6">No line items recorded.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>

    <div class="totals">
      <span>Total Qty: <strong><?php echo htmlspecialchars(rtrim(rtrim(number_format($totalQty, 2), '0'), '.')); ?></strong></span>
      <span>Parts Total: <strong><?php echo formatMoney($totalCost); ?></strong></span>
    </div>

    <?php if (!empty($order['notes'])): ?>
      <section style="margin-bottom: 24px;">
        <h2 style="margin: 0 0 8px; font-size: 1.1rem;">Internal Notes</h2>
        <pre><?php echo htmlspecialchars($order['notes']); ?></pre>
      </section>
    <?php endif; ?>

    <section id="email-template">
      <h2 style="margin: 0 0 8px; font-size: 1.1rem;">Email Template</h2>
      <pre><?php echo htmlspecialchars($emailText); ?></pre>
    </section>
  </main>
</body>
</html>
