#!/usr/bin/env php
<?php
/**
 * Debug config.inc.php parser
 * Shows what database credentials were extracted
 */

$configPath = '/home/easyscie/acnsci.org/journal/config.inc.php';

if (!file_exists($configPath)) {
    echo "ERROR: config.inc.php not found at: $configPath\n";
    exit(1);
}

echo "=== Config Parser Debug ===\n\n";
echo "Reading: $configPath\n\n";

$configContent = file_get_contents($configPath);

// Show the [database] section
echo "--- [database] section ---\n";
if (preg_match('/\[database\](.*?)(?=\[|$)/s', $configContent, $dbSection)) {
    echo $dbSection[1];
} else {
    echo "Could not find [database] section!\n";
}
echo "\n--- End section ---\n\n";

// Try to parse each field
echo "Extracted values:\n";

preg_match('/driver\s*=\s*(\w+)/', $configContent, $driverMatch);
echo "driver: " . (isset($driverMatch[1]) ? $driverMatch[1] : "NOT FOUND") . "\n";

preg_match('/host\s*=\s*([^\s]+)/', $configContent, $hostMatch);
echo "host: " . (isset($hostMatch[1]) ? $hostMatch[1] : "NOT FOUND") . "\n";

preg_match('/username\s*=\s*([^\s]+)/', $configContent, $userMatch);
echo "username: " . (isset($userMatch[1]) ? $userMatch[1] : "NOT FOUND") . "\n";

preg_match('/password\s*=\s*"?([^"\n]+)"?/', $configContent, $passMatch);
echo "password: " . (isset($passMatch[1]) ? "[REDACTED - length: " . strlen(trim($passMatch[1], '"')) . "]" : "NOT FOUND") . "\n";

preg_match('/name\s*=\s*([^\s]+)/', $configContent, $nameMatch);
echo "name: " . (isset($nameMatch[1]) ? $nameMatch[1] : "NOT FOUND") . "\n";

echo "\nLooking for all 'name =' lines in config:\n";
preg_match_all('/^[^;]*name\s*=\s*(.+)$/m', $configContent, $allNames);
foreach ($allNames[1] as $idx => $name) {
    echo "  Match " . ($idx + 1) . ": " . trim($name) . "\n";
}

echo "\n";
