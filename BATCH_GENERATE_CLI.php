#!/usr/bin/env php
<?php
/**
 * Command-line batch certificate generator
 * Run this from SSH/terminal to bypass web server timeouts
 *
 * Usage:
 *   php BATCH_GENERATE_CLI.php [journal_id] [reviewer_id1] [reviewer_id2] ...
 *
 * Example:
 *   php BATCH_GENERATE_CLI.php 4 33 59 5
 *   (Generates certificates for reviewers 33, 59, and 5 in journal ID 4)
 */

// Find OJS installation root
$ojsRoot = dirname(dirname(dirname(__FILE__)));
$toolsPath = $ojsRoot . '/tools/bootstrap.inc.php';

if (!file_exists($toolsPath)) {
    // Try alternative path
    $toolsPath = dirname(dirname(dirname(dirname(__FILE__)))) . '/tools/bootstrap.inc.php';
}

if (!file_exists($toolsPath)) {
    echo "ERROR: Cannot find OJS bootstrap.inc.php\n";
    echo "Tried: $toolsPath\n";
    echo "\nPlease run this script from the plugin directory:\n";
    echo "  cd /path/to/ojs/plugins/generic/reviewerCertificate\n";
    echo "  php BATCH_GENERATE_CLI.php [journal_id] [reviewer_ids...]\n\n";
    exit(1);
}

require_once($toolsPath);

echo "\n=== Batch Certificate Generator (CLI) ===\n\n";

// Parse arguments
if ($argc < 3) {
    echo "Usage: php BATCH_GENERATE_CLI.php [journal_id] [reviewer_id1] [reviewer_id2] ...\n\n";
    echo "Example:\n";
    echo "  php BATCH_GENERATE_CLI.php 4 33 59 5\n";
    echo "  (Generates certificates for reviewers 33, 59, and 5 in journal 4)\n\n";
    exit(1);
}

$contextId = (int) $argv[1];
$reviewerIds = array_slice($argv, 2);

echo "Journal/Context ID: $contextId\n";
echo "Reviewer IDs: " . implode(', ', $reviewerIds) . "\n";
echo "Total reviewers: " . count($reviewerIds) . "\n\n";

// Load plugin
import('lib.pkp.classes.plugins.PluginRegistry');
$plugin = PluginRegistry::getPlugin('generic', 'reviewercertificateplugin');

if (!$plugin) {
    echo "ERROR: ReviewerCertificate plugin not found or not enabled\n\n";
    exit(1);
}

// Load DAO and Certificate class
import('lib.pkp.classes.db.DAORegistry');
$certificateDao = DAORegistry::getDAO('CertificateDAO');

if (!$certificateDao) {
    echo "ERROR: CertificateDAO not loaded\n\n";
    exit(1);
}

$pluginPath = $plugin->getPluginPath();
require_once($pluginPath . '/classes/Certificate.inc.php');

echo "✓ Plugin and DAOs loaded\n\n";
echo "Starting certificate generation...\n";
echo str_repeat('-', 80) . "\n\n";

$generated = 0;
$skipped = 0;
$errors = 0;

foreach ($reviewerIds as $reviewerId) {
    $reviewerId = (int) $reviewerId;
    echo "Processing reviewer ID: $reviewerId\n";

    // Query for completed reviews without certificates
    $result = $certificateDao->retrieve(
        'SELECT ra.review_id, ra.reviewer_id, ra.submission_id
         FROM review_assignments ra
         INNER JOIN submissions s ON ra.submission_id = s.submission_id
         LEFT JOIN reviewer_certificates rc ON ra.review_id = rc.review_id
         WHERE ra.reviewer_id = ?
               AND s.context_id = ?
               AND ra.date_completed IS NOT NULL
               AND rc.certificate_id IS NULL',
        array($reviewerId, $contextId)
    );

    if (!$result) {
        echo "  ⚠ No completed reviews found\n\n";
        continue;
    }

    $reviewCount = 0;
    foreach ($result as $row) {
        $reviewCount++;
        echo "  Review ID {$row->review_id}: ";

        // Double-check certificate doesn't exist
        $existingCert = $certificateDao->getByReviewId($row->review_id);
        if ($existingCert) {
            echo "SKIPPED (already exists)\n";
            $skipped++;
            continue;
        }

        // Create certificate
        $certificate = new Certificate();
        $certificate->setReviewerId($row->reviewer_id);
        $certificate->setSubmissionId($row->submission_id);
        $certificate->setReviewId($row->review_id);
        $certificate->setContextId($contextId);
        $certificate->setDateIssued(Core::getCurrentDate());
        $certificate->setCertificateCode(strtoupper(substr(md5($row->review_id . time() . uniqid()), 0, 12)));
        $certificate->setDownloadCount(0);

        try {
            $startTime = microtime(true);
            $insertId = $certificateDao->insertObject($certificate);
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            echo "✓ SUCCESS (ID: $insertId, {$duration}ms)\n";
            $generated++;

        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            echo "✗ FAILED ({$duration}ms)\n";
            echo "     Error: " . $e->getMessage() . "\n";
            $errors++;
        }
    }

    if ($reviewCount == 0) {
        echo "  ⚠ No reviews without certificates\n";
    }
    echo "\n";
}

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
