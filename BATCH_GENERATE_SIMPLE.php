#!/usr/bin/env php
<?php
/**
 * Standalone batch certificate generator (no OJS bootstrap required)
 * Connects directly to database using config.inc.php
 *
 * Usage:
 *   php BATCH_GENERATE_SIMPLE.php [journal_id] [reviewer_id1] [reviewer_id2] ...
 *
 * Example:
 *   php BATCH_GENERATE_SIMPLE.php 4 33 59 5
 */

// Find config.inc.php
$configPaths = array(
    '/home/easyscie/acnsci.org/journal/config.inc.php',
    dirname(dirname(dirname(__FILE__))) . '/config.inc.php',
    dirname(dirname(dirname(dirname(__FILE__)))) . '/config.inc.php',
);

$configPath = null;
foreach ($configPaths as $path) {
    if (file_exists($path)) {
        $configPath = $path;
        break;
    }
}

if (!$configPath) {
    echo "ERROR: Cannot find config.inc.php\n\n";
    echo "Tried:\n";
    foreach ($configPaths as $path) {
        echo "  - $path\n";
    }
    echo "\nPlease run from: /home/easyscie/acnsci.org/journal/plugins/generic/reviewerCertificate/\n\n";
    exit(1);
}

// Parse config.inc.php to get database credentials
$configContent = file_get_contents($configPath);

// Extract database settings
preg_match('/driver\s*=\s*(\w+)/', $configContent, $driverMatch);
preg_match('/host\s*=\s*([^\s]+)/', $configContent, $hostMatch);
preg_match('/username\s*=\s*([^\s]+)/', $configContent, $userMatch);
preg_match('/password\s*=\s*"?([^"\n]+)"?/', $configContent, $passMatch);
preg_match('/name\s*=\s*([^\s]+)/', $configContent, $nameMatch);

$dbDriver = isset($driverMatch[1]) ? $driverMatch[1] : 'mysqli';
$dbHost = isset($hostMatch[1]) ? $hostMatch[1] : 'localhost';
$dbUser = isset($userMatch[1]) ? $userMatch[1] : '';
$dbPass = isset($passMatch[1]) ? trim($passMatch[1], '"') : '';
$dbName = isset($nameMatch[1]) ? $nameMatch[1] : '';

if (empty($dbName) || empty($dbUser)) {
    echo "ERROR: Could not parse database credentials from config.inc.php\n";
    echo "Config path: $configPath\n\n";
    exit(1);
}

echo "\n=== Batch Certificate Generator (Simple) ===\n\n";

// Parse arguments
if ($argc < 3) {
    echo "Usage: php BATCH_GENERATE_SIMPLE.php [journal_id] [reviewer_id1] [reviewer_id2] ...\n\n";
    echo "Example:\n";
    echo "  php BATCH_GENERATE_SIMPLE.php 4 33 59 5\n\n";
    exit(1);
}

$contextId = (int) $argv[1];
$reviewerIds = array_slice($argv, 2);

echo "Database: $dbName@$dbHost\n";
echo "User: $dbUser\n";
echo "Journal/Context ID: $contextId\n";
echo "Reviewer IDs: " . implode(', ', $reviewerIds) . "\n";
echo "Total reviewers: " . count($reviewerIds) . "\n\n";

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Connect to database
echo "Attempting database connection...\n";
flush();

$conn = @new mysqli($dbHost, $dbUser, $dbPass, $dbName);

if ($conn->connect_error) {
    echo "ERROR: Database connection failed\n";
    echo "Error (#" . $conn->connect_errno . "): " . $conn->connect_error . "\n";
    echo "\nPlease check:\n";
    echo "  - Database host: $dbHost\n";
    echo "  - Database user: $dbUser\n";
    echo "  - Database name: $dbName\n";
    echo "  - Password is set: " . (empty($dbPass) ? "NO" : "YES") . "\n\n";
    exit(1);
}

echo "✓ Database connected successfully\n\n";
echo "Starting certificate generation...\n";
echo str_repeat('-', 80) . "\n\n";

$generated = 0;
$skipped = 0;
$errors = 0;

foreach ($reviewerIds as $reviewerId) {
    $reviewerId = (int) $reviewerId;
    echo "Processing reviewer ID: $reviewerId\n";

    // Query for completed reviews without certificates
    $sql = "SELECT ra.review_id, ra.reviewer_id, ra.submission_id
            FROM review_assignments ra
            INNER JOIN submissions s ON ra.submission_id = s.submission_id
            LEFT JOIN reviewer_certificates rc ON ra.review_id = rc.review_id
            WHERE ra.reviewer_id = ?
                  AND s.context_id = ?
                  AND ra.date_completed IS NOT NULL
                  AND rc.certificate_id IS NULL";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo "  ✗ SQL prepare failed: " . $conn->error . "\n\n";
        $errors++;
        continue;
    }

    $stmt->bind_param('ii', $reviewerId, $contextId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        echo "  ⚠ No completed reviews without certificates\n\n";
        continue;
    }

    while ($row = $result->fetch_assoc()) {
        $reviewId = $row['review_id'];
        $submissionId = $row['submission_id'];

        echo "  Review ID $reviewId: ";

        // Generate certificate code
        $certCode = strtoupper(substr(md5($reviewId . time() . uniqid()), 0, 12));
        $dateIssued = date('Y-m-d H:i:s');

        // Insert certificate
        $insertSql = "INSERT INTO reviewer_certificates
                      (reviewer_id, submission_id, review_id, context_id, template_id,
                       date_issued, certificate_code, download_count)
                      VALUES (?, ?, ?, ?, NULL, ?, ?, 0)";

        $insertStmt = $conn->prepare($insertSql);
        if (!$insertStmt) {
            echo "✗ FAILED (SQL prepare error)\n";
            echo "     Error: " . $conn->error . "\n";
            $errors++;
            continue;
        }

        $insertStmt->bind_param('iiiiss',
            $reviewerId,
            $submissionId,
            $reviewId,
            $contextId,
            $dateIssued,
            $certCode
        );

        $startTime = microtime(true);
        if ($insertStmt->execute()) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $insertId = $conn->insert_id;
            echo "✓ SUCCESS (ID: $insertId, code: $certCode, {$duration}ms)\n";
            $generated++;
        } else {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            if (strpos($insertStmt->error, 'Duplicate entry') !== false) {
                echo "⊙ SKIPPED (already exists)\n";
                $skipped++;
            } else {
                echo "✗ FAILED ({$duration}ms)\n";
                echo "     Error: " . $insertStmt->error . "\n";
                $errors++;
            }
        }

        $insertStmt->close();
    }

    $stmt->close();
    echo "\n";
}

$conn->close();

echo str_repeat('-', 80) . "\n";
echo "\nSUMMARY:\n";
echo "  Generated: $generated certificate(s)\n";
echo "  Skipped:   $skipped certificate(s) (already exist)\n";
echo "  Errors:    $errors certificate(s)\n\n";

if ($generated > 0) {
    echo "✓ SUCCESS! $generated certificate(s) created.\n";
    echo "  Reviewers can now download their certificates.\n\n";
} else if ($skipped > 0 && $errors == 0) {
    echo "✓ All reviewers already have certificates.\n\n";
} else if ($errors > 0) {
    echo "⚠ Some certificates failed. Check errors above.\n\n";
    exit(1);
} else {
    echo "ℹ No certificates generated. Selected reviewers may not have completed reviews.\n\n";
}
