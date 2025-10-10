<?php
declare(strict_types=1);

require_once __DIR__ . '/../crm/config/database.php';

$mysqli = @new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($mysqli->connect_errno) {
    http_response_code(500);
    die('<h1>Database connection failed</h1>');
}

$mysqli->query("CREATE TABLE IF NOT EXISTS dispatch_jobs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lead_id INT UNSIGNED NOT NULL,
    job_date DATETIME NOT NULL,
    arrival_window VARCHAR(64) DEFAULT NULL,
    technician VARCHAR(120) DEFAULT NULL,
    status ENUM('scheduled','confirmed','en_route','on_site','completed','cancelled') NOT NULL DEFAULT 'scheduled',
    notes TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_lead_datetime (lead_id, job_date),
    KEY idx_job_date (job_date),
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$statusOptions = [
    'scheduled' => 'Scheduled',
    'confirmed' => 'Confirmed',
    'en_route' => 'En Route',
    'on_site' => 'On Site',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
];

$message = '';
$error = '';

function normalizeDateTime(?string $input): ?string
{
    if (!$input) {
        return null;
    }
    $input = trim($input);
    if ($input === '') {
        return null;
    }
    // Accept values from datetime-local inputs
    $timestamp = strtotime(str_replace('T', ' ', $input));
    if ($timestamp === false) {
        return null;
    }
    return date('Y-m-d H:i:s', $timestamp);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_job'])) {
        $leadId = (int)($_POST['lead_id'] ?? 0);
        $jobDate = normalizeDateTime($_POST['job_datetime'] ?? null);
        $arrivalWindow = trim($_POST['arrival_window'] ?? '');
        $technician = trim($_POST['technician'] ?? '');
        $status = $_POST['status'] ?? 'scheduled';
        $notes = trim($_POST['notes'] ?? '');

        if (!isset($statusOptions[$status])) {
            $status = 'scheduled';
        }

        if ($leadId > 0 && $jobDate) {
            $stmt = $mysqli->prepare("INSERT INTO dispatch_jobs (lead_id, job_date, arrival_window, technician, status, notes)
                VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('isssss', $leadId, $jobDate, $arrivalWindow, $technician, $status, $notes);
            if ($stmt->execute()) {
                $message = 'Scheduled visit for lead #' . $leadId . '.';
            } else {
                $error = 'Unable to schedule visit. Please confirm the lead ID exists.';
            }
            $stmt->close();
        } else {
            $error = 'Lead ID and date/time are required.';
        }
    } elseif (isset($_POST['update_job'])) {
        $jobId = (int)($_POST['job_id'] ?? 0);
        if ($jobId > 0) {
            $jobDate = normalizeDateTime($_POST['job_datetime'] ?? null);
            $arrivalWindow = trim($_POST['arrival_window'] ?? '');
            $technician = trim($_POST['technician'] ?? '');
            $status = $_POST['status'] ?? 'scheduled';
            $notes = trim($_POST['notes'] ?? '');

            if (!isset($statusOptions[$status])) {
                $status = 'scheduled';
            }
            if (!$jobDate) {
                $error = 'A valid date/time is required to update the job.';
            } else {
                $stmt = $mysqli->prepare("UPDATE dispatch_jobs SET job_date=?, arrival_window=?, technician=?, status=?, notes=?, updated_at=NOW() WHERE id=?");
                $stmt->bind_param('sssssi', $jobDate, $arrivalWindow, $technician, $status, $notes, $jobId);
                if ($stmt->execute()) {
                    $message = 'Dispatch job updated.';
                } else {
                    $error = 'Failed to update dispatch job.';
                }
                $stmt->close();
            }
        }
    } elseif (isset($_POST['delete_job'])) {
        $jobId = (int)($_POST['job_id'] ?? 0);
        if ($jobId > 0) {
            $stmt = $mysqli->prepare("DELETE FROM dispatch_jobs WHERE id=?");
            $stmt->bind_param('i', $jobId);
            if ($stmt->execute()) {
                $message = 'Dispatch job removed.';
            } else {
                $error = 'Unable to delete dispatch job.';
            }
            $stmt->close();
        }
    }
}

$query = $mysqli->query("SELECT dj.*, lead.field_219 AS first_name, lead.field_220 AS last_name, lead.field_227 AS phone,
       lead.field_232 AS make, lead.field_233 AS model, lead.field_231 AS vehicle_year,
       po.status AS parts_status, po.received_at AS parts_received_at, po.id AS parts_order_id
    FROM dispatch_jobs dj
    LEFT JOIN app_entity_26 lead ON lead.id = dj.lead_id
    LEFT JOIN parts_orders po ON po.lead_id = dj.lead_id
    ORDER BY dj.job_date ASC");
$jobs = $query ? $query->fetch_all(MYSQLI_ASSOC) : [];
if ($query) {
    $query->free();
}

$mysqli->close();

function formatDateTime(string $datetime): string
{
    $ts = strtotime($datetime);
    if ($ts === false) {
        return $datetime;
    }
    return date('M j, Y g:i A', $ts);
}

function statusClass(string $status): string
{
    return 'status-' . str_replace('_', '-', $status);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dispatch Board · Mechanics Saint Augustine</title>
  <style>
    :root {
      color-scheme: light dark;
      --bg: #0f172a;
      --panel: rgba(15, 23, 42, 0.85);
      --text: #e2e8f0;
      --muted: #94a3b8;
      --accent: #34d399;
      --border: rgba(148, 163, 184, 0.22);
      font-family: "Inter", "Segoe UI", system-ui, -apple-system, sans-serif;
    }
    body {
      margin: 0;
      min-height: 100vh;
      background: radial-gradient(circle at top, rgba(52, 211, 153, 0.2), transparent 60%),
                  radial-gradient(circle at bottom, rgba(14, 165, 233, 0.2), transparent 60%),
                  var(--bg);
      color: var(--text);
      padding: 48px 18px 72px;
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
      display: grid;
      gap: 28px;
    }
    section {
      background: var(--panel);
      border: 1px solid var(--border);
      border-radius: 18px;
      padding: 24px 28px;
      box-shadow: 0 24px 60px rgba(15, 23, 42, 0.45);
      backdrop-filter: blur(10px);
    }
    section h2 { margin: 0 0 16px; font-size: 1.35rem; letter-spacing: 0.03em; }
    form.create, form.update {
      display: grid;
      gap: 12px;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      align-items: end;
    }
    form textarea {
      grid-column: 1 / -1;
      min-height: 90px;
      resize: vertical;
    }
    label {
      display: flex;
      flex-direction: column;
      gap: 6px;
      font-size: 0.85rem;
      color: var(--muted);
    }
    input, select, textarea {
      border-radius: 10px;
      border: 1px solid var(--border);
      background: rgba(15, 23, 42, 0.65);
      color: var(--text);
      padding: 8px 10px;
      font-size: 0.92rem;
    }
    button {
      padding: 10px 16px;
      border-radius: 12px;
      border: 1px solid rgba(52, 211, 153, 0.45);
      background: rgba(52, 211, 153, 0.15);
      color: var(--text);
      font-size: 0.92rem;
      cursor: pointer;
      justify-self: start;
    }
    button:hover { background: rgba(52, 211, 153, 0.25); }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 12px;
    }
    th, td {
      padding: 12px;
      border-bottom: 1px solid rgba(148, 163, 184, 0.16);
      vertical-align: top;
      text-align: left;
    }
    th { color: var(--muted); font-size: 0.85rem; letter-spacing: 0.04em; }
    .status-badge {
      display: inline-flex;
      align-items: center;
      padding: 3px 12px;
      border-radius: 999px;
      font-size: 0.78rem;
      letter-spacing: 0.05em;
      text-transform: uppercase;
    }
    .status-scheduled { background: rgba(37, 99, 235, 0.16); color: #93c5fd; }
    .status-confirmed { background: rgba(59, 130, 246, 0.16); color: #bfdbfe; }
    .status-en-route { background: rgba(56, 189, 248, 0.16); color: #bae6fd; }
    .status-on-site { background: rgba(245, 158, 11, 0.18); color: #fcd34d; }
    .status-completed { background: rgba(52, 211, 153, 0.18); color: #6ee7b7; }
    .status-cancelled { background: rgba(248, 113, 113, 0.16); color: #fca5a5; }
    .message, .error {
      padding: 12px 16px;
      border-radius: 12px;
      margin-bottom: 12px;
      text-align: center;
    }
    .message { border: 1px solid rgba(52, 211, 153, 0.4); background: rgba(52, 211, 153, 0.12); color: var(--accent); }
    .error { border: 1px solid rgba(248, 113, 113, 0.45); background: rgba(248, 113, 113, 0.12); color: #fca5a5; }
    .jobs-empty { color: var(--muted); font-size: 0.9rem; }
    .job-links {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      margin-top: 8px;
    }
    .job-links a {
      color: var(--accent);
      text-decoration: none;
      font-size: 0.86rem;
    }
    .job-links a:hover { text-decoration: underline; }
    .parts-pill {
      display: inline-block;
      margin-top: 6px;
      padding: 2px 8px;
      border-radius: 999px;
      font-size: 0.75rem;
      background: rgba(251, 191, 36, 0.18);
      color: #fbbf24;
    }
    @media (max-width: 840px) {
      form.create, form.update { grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); }
      th, td { padding: 10px; }
    }
  </style>
</head>
<body>
  <header>
    <h1>Dispatch Board</h1>
    <p>Schedule visits, assign technicians, and keep tabs on job progress.</p>
  </header>

  <main>
    <section>
      <h2>Schedule a Visit</h2>
      <?php if ($message): ?><div class="message"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
      <?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
      <form method="post" class="create">
        <label>
          Lead ID
          <input type="number" name="lead_id" min="1" required />
        </label>
        <label>
          Visit Date &amp; Time
          <input type="datetime-local" name="job_datetime" required />
        </label>
        <label>
          Arrival Window
          <input type="text" name="arrival_window" placeholder="e.g. 9-11 AM" />
        </label>
        <label>
          Technician
          <input type="text" name="technician" placeholder="Assign tech" />
        </label>
        <label>
          Status
          <select name="status">
            <?php foreach ($statusOptions as $value => $label): ?>
              <option value="<?php echo htmlspecialchars($value); ?>"><?php echo htmlspecialchars($label); ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <textarea name="notes" placeholder="Job notes, parts staging, customer nuances"></textarea>
        <button type="submit" name="create_job" value="1">Add to Dispatch</button>
      </form>
    </section>

    <section>
      <h2>Upcoming Jobs</h2>
      <?php if (empty($jobs)): ?>
        <p class="jobs-empty">Nothing on the board yet. Schedule a visit above.</p>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>When</th>
              <th>Customer / Vehicle</th>
              <th>Technician</th>
              <th>Status</th>
              <th>Notes &amp; Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($jobs as $job): ?>
              <?php
                $jobId = (int)$job['id'];
                $isPast = strtotime($job['job_date']) < time();
                $partsStatus = $job['parts_status'] ? ucfirst($job['parts_status']) : null;
                $partsReceived = !empty($job['parts_received_at']);
                $partsOrderId = isset($job['parts_order_id']) ? (int)$job['parts_order_id'] : 0;
              ?>
              <tr id="job-<?php echo $jobId; ?>" class="<?php echo $isPast ? 'past-job' : ''; ?>">
                <td>
                  <strong><?php echo htmlspecialchars(formatDateTime($job['job_date'])); ?></strong><br>
                  <?php if (!empty($job['arrival_window'])): ?>
                    <small><?php echo htmlspecialchars($job['arrival_window']); ?></small><br>
                  <?php endif; ?>
                  <small>Lead #<?php echo (int)$job['lead_id']; ?></small>
                  <?php if ($partsStatus): ?>
                    <div class="parts-pill">Parts: <?php echo htmlspecialchars($partsStatus); ?><?php echo $partsReceived ? ' ✓' : ''; ?></div>
                  <?php endif; ?>
                  <div class="job-links">
                    <a href="/admin/leads_approval.php?focus=<?php echo (int)$job['lead_id']; ?>">Lead Approval</a>
                    <?php if ($partsOrderId): ?>
                      <a href="/admin/parts_orders.php?focus=<?php echo (int)$job['lead_id']; ?>#order-lead-<?php echo (int)$job['lead_id']; ?>">Parts Order</a>
                    <?php else: ?>
                      <a href="/admin/parts_orders.php">Parts Order</a>
                    <?php endif; ?>
                  </div>
                </td>
                <td>
                  <?php echo htmlspecialchars(trim(($job['first_name'] ?? '') . ' ' . ($job['last_name'] ?? '')) ?: '—'); ?><br>
                  <small><?php echo htmlspecialchars($job['phone'] ?? ''); ?></small><br>
                  <small><?php echo htmlspecialchars(trim(($job['vehicle_year'] ?? '') . ' ' . ($job['make'] ?? '') . ' ' . ($job['model'] ?? '')) ?: '—'); ?></small>
                </td>
                <td><?php echo htmlspecialchars($job['technician'] ?: 'Unassigned'); ?></td>
                <td>
                  <span class="status-badge <?php echo htmlspecialchars(statusClass($job['status'])); ?>">
                    <?php echo htmlspecialchars($statusOptions[$job['status']] ?? ucfirst($job['status'])); ?>
                  </span>
                </td>
                <td>
                  <form method="post" class="update">
                    <input type="hidden" name="job_id" value="<?php echo $jobId; ?>" />
                    <?php
                      $dtValue = '';
                      $dtTimestamp = strtotime($job['job_date']);
                      if ($dtTimestamp !== false) {
                          $dtValue = date('Y-m-d\TH:i', $dtTimestamp);
                      }
                    ?>
                    <label>
                      Visit Date &amp; Time
                      <input type="datetime-local" name="job_datetime" value="<?php echo htmlspecialchars($dtValue); ?>" required />
                    </label>
                    <label>
                      Arrival Window
                      <input type="text" name="arrival_window" value="<?php echo htmlspecialchars($job['arrival_window'] ?? ''); ?>" />
                    </label>
                    <label>
                      Technician
                      <input type="text" name="technician" value="<?php echo htmlspecialchars($job['technician'] ?? ''); ?>" />
                    </label>
                    <label>
                      Status
                      <select name="status">
                        <?php foreach ($statusOptions as $value => $label): ?>
                          <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $job['status'] === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                      </select>
                    </label>
                    <textarea name="notes" placeholder="Update notes"><?php echo htmlspecialchars($job['notes'] ?? ''); ?></textarea>
                    <button type="submit" name="update_job" value="1">Save Changes</button>
                    <button type="submit" name="delete_job" value="1" onclick="return confirm('Remove this dispatch job?');" style="border-color: rgba(248, 113, 113, 0.45); background: rgba(248, 113, 113, 0.12);">Remove</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>
  </main>

  <footer style="color: var(--muted); font-size: 0.85rem; text-align: center;">
    <a href="/admin/" style="color: var(--accent); text-decoration: none;">&larr; Back to Admin Dashboard</a>
  </footer>
</body>
</html>
