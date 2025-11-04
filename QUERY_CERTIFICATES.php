#!/usr/bin/env php
<?php
/**
 * Query certificates for testing
 */

// Parse config.inc.php to get database credentials
// Plugin is in plugins/generic/reviewerCertificate, so go up 3 levels to OJS root
$configPath = __DIR__ . '/../../../config.inc.php';
if (!file_exists($configPath)) {
    echo "ERROR: config.inc.php not found at: $configPath\n";
    exit(1);
}

$configContent = file_get_contents($configPath);

// Extract [database] section
if (preg_match('/\[database\](.*?)(?=\[|$)/s', $configContent, $dbSection)) {
    $dbSectionContent = $dbSection[1];
} else {
    echo "ERROR: Could not find [database] section in config.inc.php\n";
    exit(1);
}

// Extract database settings
preg_match('/name\s*=\s*([^\s]+)/', $dbSectionContent, $nameMatch);
preg_match('/username\s*=\s*([^\s]+)/', $dbSectionContent, $userMatch);
preg_match('/password\s*=\s*"?([^"\s]+)"?/', $dbSectionContent, $passMatch);
preg_match('/host\s*=\s*([^\s]+)/', $dbSectionContent, $hostMatch);

$dbName = isset($nameMatch[1]) ? $nameMatch[1] : '';
$dbUser = isset($userMatch[1]) ? $userMatch[1] : '';
$dbPass = isset($passMatch[1]) ? $passMatch[1] : '';
$dbHost = isset($hostMatch[1]) ? $hostMatch[1] : 'localhost';

if (empty($dbName) || empty($dbUser)) {
    echo "ERROR: Could not parse database credentials from config.inc.php\n";
    exit(1);
}

// Connect to database
$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error . "\n");
}

echo "=== Recent Certificates ===\n\n";

$sql = "SELECT
    c.certificate_id,
    c.certificate_code,
    c.review_id,
    c.reviewer_id,
    CONCAT(u.first_name, ' ', u.last_name) as reviewer_name,
    c.submission_id,
    c.date_issued,
    c.download_count
FROM reviewer_certificates c
JOIN users u ON c.reviewer_id = u.user_id
ORDER BY c.certificate_id DESC
LIMIT 10";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    printf("%-5s %-15s %-10s %-10s %-25s %-15s %-12s %s\n",
        "ID", "Code", "Review", "Reviewer", "Name", "Submission", "Date", "Downloads");
    echo str_repeat("-", 120) . "\n";

    while ($row = $result->fetch_assoc()) {
        printf("%-5d %-15s %-10d %-10d %-25s %-15d %-12s %d\n",
            $row['certificate_id'],
            $row['certificate_code'],
            $row['review_id'],
            $row['reviewer_id'],
            substr($row['reviewer_name'], 0, 24),
            $row['submission_id'],
            $row['date_issued'],
            $row['download_count']
        );
    }

    echo "\n✓ Found " . $result->num_rows . " certificates\n";
} else {
    echo "No certificates found\n";
}

$conn->close();
