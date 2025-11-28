<?php
// Simple git pull updater
// Visit this page to update the site with latest code
// For security, delete this file after using it

set_time_limit(120);
header('Content-Type: text/plain');

echo "=== Site Updater ===\n\n";

$siteDir = '/home/kylewee/mechanicsaintaugustine.com/site';
$branch = 'claude/continue-site-improvements-013d3F6yDXGdPXjhrsCcrKRk';

// Check if directory exists
if (!is_dir($siteDir)) {
    echo "ERROR: Site directory not found at $siteDir\n";
    echo "Looking for alternatives...\n";

    // Try current directory
    $siteDir = __DIR__;
    echo "Using current directory: $siteDir\n\n";
}

echo "Changing to directory: $siteDir\n";
chdir($siteDir);

echo "\n--- Current Status ---\n";
echo shell_exec('git status 2>&1');

echo "\n--- Fetching Latest Changes ---\n";
echo shell_exec('git fetch origin 2>&1');

echo "\n--- Pulling Branch: $branch ---\n";
$output = shell_exec("git pull origin $branch 2>&1");
echo $output;

if (strpos($output, 'Already up to date') !== false) {
    echo "\n✅ Site is already up to date!\n";
} elseif (strpos($output, 'error') !== false || strpos($output, 'fatal') !== false) {
    echo "\n❌ Update failed! Check errors above.\n";
} else {
    echo "\n✅ Site updated successfully!\n";
    echo "\nNow go to Cloudflare and purge the cache.\n";
}

echo "\n--- Latest Commit ---\n";
echo shell_exec('git log -1 --oneline 2>&1');

echo "\n\n=== DONE ===\n";
echo "For security, delete this file: rm update.php\n";
?>
