#!/usr/bin/env php
<?php
/**
 * Manual batch certificate generator with database credentials as parameters
 *
 * Usage:
 *   php BATCH_GENERATE_MANUAL.php [db_name] [db_user] [db_pass] [db_host] [context_id] [reviewer_ids...]
 *
 * Example:
 *   php BATCH_GENERATE_MANUAL.php easyscie_ojsdb easyscie_ojs554 'mypassword' localhost 4 33 59 5
 *
 * Or if password contains special characters, use quotes:
 *   php BATCH_GENERATE_MANUAL.php easyscie_ojsdb easyscie_ojs554 'p@ssw0rd!' localhost 4 33 59 5
 */

if ($argc < 7) {
    echo "\n=== Manual Batch Certificate Generator ===\n\n";
    echo "Usage:\n";
    echo "  php BATCH_GENERATE_MANUAL.php [db_name] [db_user] [db_pass] [db_host] [context_id] [reviewer_ids...]\n\n";
    echo "Parameters:\n";
    echo "  db_name      Database name\n";
    echo "  db_user      Database username\n";
    echo "  db_pass      Database password (use quotes if it has special chars)\n";
    echo "  db_host      Database host (usually 'localhost')\n";
    echo "  context_id   Journal/Context ID\n";
    echo "  reviewer_ids One or more reviewer IDs to generate certificates for\n\n";
    echo "Example:\n";
    echo "  php BATCH_GENERATE_MANUAL.php easyscie_ojsdb easyscie_ojs554 'password123' localhost 4 33 59 5\n\n";
    echo "To find your database credentials, check:\n";
    echo "  /home/easyscie/acnsci.org/journal/config.inc.php\n";
    echo "  Look in the [database] section\n\n";
    exit(1);
}

// Parse arguments
$dbName = $argv[1];
$dbUser = $argv[2];
$dbPass = $argv[3];
$dbHost = $argv[4];
$contextId = (int) $argv[5];
$reviewerIds = array_slice($argv, 6);

echo "\n=== Manual Batch Certificate Generator ===\n\n";
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
    echo "✗ ERROR: Database connection failed\n";
    echo "Error (#" . $conn->connect_errno . "): " . $conn->connect_error . "\n\n";
    echo "Please check:\n";
    echo "  1. Database name is correct: $dbName\n";
    echo "  2. Database user has access: $dbUser\n";
    echo "  3. Password is correct (length: " . strlen($dbPass) . " chars)\n";
    echo "  4. Host is correct: $dbHost\n\n";
    echo "To verify, check: /home/easyscie/acnsci.org/journal/config.inc.php\n\n";
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
        flush();

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
