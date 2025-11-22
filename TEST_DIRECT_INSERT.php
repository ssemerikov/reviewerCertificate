#!/usr/bin/env php
<?php
/**
 * Direct database INSERT test for reviewer_certificates
 * Run this from command line to test if INSERT works outside web context
 */

require_once(dirname(__FILE__) . '/../../tools/bootstrap.inc.php');

echo "\n=== Direct Certificate INSERT Test ===\n\n";

// Get database connection
$dbconn = DBConnection::getConn();

// Test data - using fake review_id that shouldn't exist
$testReviewId = 999999;
$testReviewerId = 1;
$testSubmissionId = 1;
$testContextId = 4;
$testCode = 'TEST' . strtoupper(substr(md5(time()), 0, 8));

echo "Test data:\n";
echo "  review_id: $testReviewId\n";
echo "  reviewer_id: $testReviewerId\n";
echo "  submission_id: $testSubmissionId\n";
echo "  context_id: $testContextId\n";
echo "  certificate_code: $testCode\n\n";

// First, clean up any existing test certificate
echo "1. Cleaning up any existing test certificate...\n";
$result = $dbconn->query("DELETE FROM reviewer_certificates WHERE review_id = $testReviewId");
if ($result) {
    echo "✓ Cleanup complete\n\n";
} else {
    echo "⚠ Cleanup failed: " . $dbconn->error . "\n\n";
}

// Try direct INSERT
echo "2. Attempting direct INSERT...\n";
$startTime = microtime(true);

$sql = "INSERT INTO reviewer_certificates
    (reviewer_id, submission_id, review_id, context_id, template_id, date_issued, certificate_code, download_count)
VALUES
    ($testReviewerId, $testSubmissionId, $testReviewId, $testContextId, NULL, NOW(), '$testCode', 0)";

echo "SQL: $sql\n\n";
echo "Executing... ";

$result = $dbconn->query($sql);
$duration = round((microtime(true) - $startTime) * 1000, 2);

if ($result) {
    $insertId = $dbconn->insert_id;
    echo "✓ SUCCESS in {$duration}ms!\n";
    echo "  Insert ID: $insertId\n\n";

    // Verify the insert
    echo "3. Verifying inserted record...\n";
    $verifyResult = $dbconn->query("SELECT * FROM reviewer_certificates WHERE certificate_id = $insertId");
    if ($verifyResult && $row = $verifyResult->fetch_assoc()) {
        echo "✓ Record found:\n";
        echo "  certificate_id: {$row['certificate_id']}\n";
        echo "  review_id: {$row['review_id']}\n";
        echo "  certificate_code: {$row['certificate_code']}\n";
        echo "  date_issued: {$row['date_issued']}\n\n";
    } else {
        echo "⚠ Could not verify record\n\n";
    }

    // Clean up
    echo "4. Cleaning up test record...\n";
    $dbconn->query("DELETE FROM reviewer_certificates WHERE certificate_id = $insertId");
    echo "✓ Test record deleted\n\n";

} else {
    echo "✗ FAILED after {$duration}ms\n";
    echo "Error: " . $dbconn->error . "\n";
    echo "Error number: " . $dbconn->errno . "\n\n";
}

// Now test using DAO (same way batch generation does it)
echo "5. Testing via CertificateDAO (same as batch generation)...\n";
import('lib.pkp.classes.db.DAORegistry');
$certificateDao = DAORegistry::getDAO('CertificateDAO');

if (!$certificateDao) {
    echo "✗ Could not load CertificateDAO\n\n";
    exit(1);
}

// Import Certificate class
$pluginPath = dirname(__FILE__);
require_once($pluginPath . '/classes/Certificate.inc.php');

$certificate = new Certificate();
$certificate->setReviewerId($testReviewerId);
$certificate->setSubmissionId($testSubmissionId);
$certificate->setReviewId($testReviewId + 1); // Different review_id
$certificate->setContextId($testContextId);
$certificate->setDateIssued(\PKP\core\Core::getCurrentDate());
$certificate->setCertificateCode($testCode . '2');
$certificate->setDownloadCount(0);

echo "Attempting DAO insertObject()... ";
$startTime = microtime(true);

try {
    $insertId = $certificateDao->insertObject($certificate);
    $duration = round((microtime(true) - $startTime) * 1000, 2);

    echo "✓ SUCCESS in {$duration}ms!\n";
    echo "  Insert ID: $insertId\n\n";

    // Clean up
    echo "6. Cleaning up DAO test record...\n";
    $certificateDao->deleteById($insertId);
    echo "✓ Test record deleted\n\n";

} catch (Exception $e) {
    $duration = round((microtime(true) - $startTime) * 1000, 2);
    echo "✗ FAILED after {$duration}ms\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "Error type: " . get_class($e) . "\n\n";
}

echo "=== Test Complete ===\n\n";

echo "INTERPRETATION:\n";
echo "- If direct INSERT succeeded but DAO failed: Issue is in DAO code\n";
echo "- If both succeeded here but batch fails in browser: Web server timeout\n";
echo "- If both failed: Database configuration or lock issue\n";
echo "- If INSERT hangs here too: Serious database problem\n\n";
