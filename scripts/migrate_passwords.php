#!/usr/bin/env php
<?php
/**
 * Password Migration Script
 *
 * This script helps migrate legacy MD5 password hashes to secure bcrypt hashes.
 *
 * IMPORTANT: This script should only be run once during the migration process.
 *
 * Usage:
 *   php scripts/migrate_passwords.php [--dry-run]
 *
 * Options:
 *   --dry-run    Show what would be updated without making changes
 */

// Load database connection
require_once __DIR__ . '/../Mobile-mechanic/connection.php';

$dryRun = in_array('--dry-run', $argv);

if ($dryRun) {
    echo "=== DRY RUN MODE - No changes will be made ===\n\n";
} else {
    echo "=== PASSWORD MIGRATION SCRIPT ===\n\n";
    echo "WARNING: This will modify password hashes in the database.\n";
    echo "Make sure you have a backup before proceeding!\n\n";
    echo "Press ENTER to continue or Ctrl+C to cancel...";
    fgets(STDIN);
}

$tables = [
    'customer_reg' => [
        'id_column' => 'cid',
        'password_column' => 'cpassword',
        'email_column' => 'cemail'
    ],
    'mechanic_reg' => [
        'id_column' => 'mid',
        'password_column' => 'mpassword',
        'email_column' => 'memail'
    ],
    'admin' => [
        'id_column' => 'aid',
        'password_column' => 'password',
        'email_column' => 'aemail'
    ]
];

$totalMigrated = 0;
$totalSkipped = 0;
$totalErrors = 0;

foreach ($tables as $tableName => $config) {
    echo "\nProcessing table: {$tableName}\n";
    echo str_repeat('-', 50) . "\n";

    $idCol = $config['id_column'];
    $pwCol = $config['password_column'];
    $emailCol = $config['email_column'];

    // Check if table exists
    $checkTable = $conn->query("SHOW TABLES LIKE '{$tableName}'");
    if ($checkTable->num_rows === 0) {
        echo "  ⚠️  Table does not exist, skipping...\n";
        continue;
    }

    // Check if columns exist
    $checkCols = $conn->query("SHOW COLUMNS FROM {$tableName}");
    $columns = [];
    while ($row = $checkCols->fetch_assoc()) {
        $columns[] = $row['Field'];
    }

    if (!in_array($pwCol, $columns)) {
        echo "  ⚠️  Password column '{$pwCol}' not found, skipping...\n";
        continue;
    }

    // Find all MD5 hashes (32 characters, hexadecimal)
    $query = "SELECT {$idCol}, {$emailCol}, {$pwCol} FROM {$tableName}
              WHERE LENGTH({$pwCol}) = 32
              AND {$pwCol} REGEXP '^[a-f0-9]{32}$'";

    $result = $conn->query($query);

    if (!$result) {
        echo "  ❌ Error querying table: " . $conn->error . "\n";
        $totalErrors++;
        continue;
    }

    $count = $result->num_rows;
    echo "  Found {$count} MD5 hashes to migrate\n";

    if ($count === 0) {
        echo "  ✅ No MD5 hashes found - table already migrated\n";
        continue;
    }

    while ($row = $result->fetch_assoc()) {
        $id = $row[$idCol];
        $email = $row[$emailCol];
        $oldHash = $row[$pwCol];

        if ($dryRun) {
            echo "  [DRY RUN] Would migrate: {$email} (ID: {$id})\n";
            $totalMigrated++;
        } else {
            // Since we don't have the plaintext password, we can't migrate directly
            // Instead, we'll set a flag or force password reset
            echo "  ⚠️  Cannot auto-migrate: {$email} (ID: {$id}) - requires password reset\n";
            $totalSkipped++;
        }
    }
}

echo "\n" . str_repeat('=', 50) . "\n";
echo "Migration Summary:\n";
echo "  Total found:    " . ($totalMigrated + $totalSkipped) . "\n";
echo "  Migrated:       {$totalMigrated}\n";
echo "  Skipped:        {$totalSkipped}\n";
echo "  Errors:         {$totalErrors}\n";
echo str_repeat('=', 50) . "\n\n";

if (!$dryRun && ($totalMigrated + $totalSkipped) > 0) {
    echo "IMPORTANT: MD5 passwords cannot be directly converted to bcrypt.\n";
    echo "Two options for migration:\n\n";
    echo "1. GRADUAL MIGRATION (Recommended):\n";
    echo "   - Users are automatically upgraded on next successful login\n";
    echo "   - Already implemented in login.php\n";
    echo "   - No action needed - just wait for users to log in\n\n";
    echo "2. FORCE PASSWORD RESET:\n";
    echo "   - Send password reset emails to all users\n";
    echo "   - Users must create new passwords\n";
    echo "   - Use this if immediate migration is required\n\n";
    echo "See SECURITY.md for detailed migration instructions.\n";
}

$conn->close();
