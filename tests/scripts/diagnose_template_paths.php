#!/usr/bin/env php
<?php
/**
 * @file tests/scripts/diagnose_template_paths.php
 *
 * Template Path Diagnostic Tool
 *
 * This script helps diagnose why the certificate button is not appearing
 * in OJS 3.5 by enabling detailed template logging.
 *
 * INSTRUCTIONS FOR DR. KOÇAK:
 *
 * 1. Enable this diagnostic mode:
 *    - Open ReviewerCertificatePlugin.php
 *    - Find the addCertificateButton() method
 *    - The debug logging is already enabled in version 1.0.3+
 *
 * 2. Perform a review workflow in OJS 3.5:
 *    - Log in as a reviewer
 *    - Go to an assigned review
 *    - Complete the review (all steps)
 *    - Submit the review
 *
 * 3. Check your PHP error log:
 *    - Location varies by server:
 *      * Common paths: /var/log/php/error.log, /var/log/apache2/error.log
 *      * Check php.ini for: error_log = /path/to/file
 *
 * 4. Look for these log entries:
 *    [timestamp] ReviewerCertificate: Template displayed: [template-path]
 *    [timestamp] ReviewerCertificate: Matched template for certificate button: [template-path]
 *
 * 5. Report findings:
 *    - Which templates were displayed during the review workflow?
 *    - Did any template get "Matched" for the certificate button?
 *    - At what step of the review were these templates shown?
 */

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║  Reviewer Certificate - Template Path Diagnostic Tool     ║\n";
echo "║  For OJS 3.5 Compatibility Investigation                  ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
echo "\n";

echo "This diagnostic tool helps identify the correct template paths\n";
echo "in OJS 3.5 for displaying the certificate download button.\n";
echo "\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "STEP 1: Verify Plugin Version\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\n";

$baseDir = dirname(__DIR__, 2);
$versionFile = $baseDir . '/version.xml';

if (file_exists($versionFile)) {
    $xml = simplexml_load_file($versionFile);
    $version = (string) $xml->release;
    
    echo "Plugin Version: $version\n";
    
    if (version_compare($version, '1.0.3', '>=')) {
        echo "✓ Version 1.0.3+ includes template debugging\n";
    } else {
        echo "✗ WARNING: Version is below 1.0.3\n";
        echo "  Please upgrade to version 1.0.3 or higher for debug logging\n";
    }
} else {
    echo "✗ ERROR: version.xml not found\n";
    exit(1);
}

echo "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "STEP 2: Check Debug Logging is Enabled\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\n";

$pluginFile = $baseDir . '/ReviewerCertificatePlugin.php';
$content = file_get_contents($pluginFile);

if (strpos($content, 'error_log("ReviewerCertificate: Template displayed:') !== false) {
    echo "✓ Template debugging is enabled in plugin code\n";
} else {
    echo "✗ Template debugging NOT found in plugin code\n";
    echo "  Please ensure you have version 1.0.3+\n";
}

echo "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "STEP 3: Find Your PHP Error Log Location\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\n";

$errorLog = ini_get('error_log');
if ($errorLog && $errorLog !== 'syslog') {
    echo "PHP Error Log: $errorLog\n";
    if (file_exists($errorLog)) {
        echo "✓ Log file exists and is writable: " . (is_writable($errorLog) ? "YES" : "NO") . "\n";
        
        // Show last few ReviewerCertificate log entries
        echo "\nRecent ReviewerCertificate log entries:\n";
        echo "─────────────────────────────────────────────────────────────\n";
        $cmd = "grep 'ReviewerCertificate:' " . escapeshellarg($errorLog) . " 2>/dev/null | tail -10";
        $output = shell_exec($cmd);
        if ($output) {
            echo $output;
        } else {
            echo "(No ReviewerCertificate log entries found yet)\n";
        }
    } else {
        echo "⚠ Log file does not exist: $errorLog\n";
    }
} else {
    echo "Error log location: Not configured or using syslog\n";
    echo "\nCommon locations to check:\n";
    echo "  • /var/log/php/error.log\n";
    echo "  • /var/log/apache2/error.log\n";
    echo "  • /var/log/nginx/error.log\n";
    echo "  • Check your php.ini for error_log setting\n";
}

echo "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "STEP 4: Test Review Workflow\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\n";

echo "Now perform the following test in your OJS 3.5 installation:\n";
echo "\n";
echo "1. Log in to OJS as a reviewer\n";
echo "2. Navigate to an assigned review\n";
echo "3. Complete all review steps (1, 2, 3, 4)\n";
echo "4. Submit the review\n";
echo "5. Return to your reviewer dashboard\n";
echo "6. Look for the certificate download button\n";
echo "\n";
echo "While doing this, the plugin will log all templates being rendered.\n";
echo "\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "STEP 5: Collect Diagnostic Data\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\n";

echo "After completing the review workflow, check your error log:\n";
echo "\n";
if ($errorLog && $errorLog !== 'syslog' && file_exists($errorLog)) {
    echo "Command to view logs:\n";
    echo "  tail -f $errorLog | grep ReviewerCertificate\n";
    echo "\n";
    echo "Or to see the last 50 lines:\n";
    echo "  grep 'ReviewerCertificate:' $errorLog | tail -50\n";
} else {
    echo "  grep 'ReviewerCertificate:' /path/to/your/error.log | tail -50\n";
}
echo "\n";

echo "Look for these patterns:\n";
echo "  • 'Template displayed: reviewer/review/[something].tpl'\n";
echo "  • 'Matched template for certificate button: [template]'\n";
echo "  • 'Certificate is available or reviewer is eligible'\n";
echo "  • 'Injected via params[2] modification' or 'Injected via echo'\n";
echo "\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "STEP 6: Report Findings\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\n";

echo "Please report the following information:\n";
echo "\n";
echo "1. OJS Version: _________ (e.g., 3.5.0-1)\n";
echo "2. Templates displayed during review workflow:\n";
echo "   ________________________________________________\n";
echo "   ________________________________________________\n";
echo "   ________________________________________________\n";
echo "3. Was any template 'Matched'? YES / NO\n";
echo "   If YES, which one: ______________________________\n";
echo "4. Was certificate eligibility checked? YES / NO\n";
echo "5. Was button injection attempted? YES / NO\n";
echo "6. Which injection method was used: params[2] / echo / NONE\n";
echo "7. Is the button visible on the page? YES / NO\n";
echo "\n";

echo "Please copy the relevant log entries and post them to:\n";
echo "  • GitHub Issues: https://github.com/ssemerikov/reviewerCertificate/issues\n";
echo "  • Or email: semerikov@gmail.com\n";
echo "\n";

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║  Expected Log Output (Example)                             ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "Here's what you should see in a working configuration:\n";
echo "\n";
echo "[timestamp] ReviewerCertificate: Template displayed: reviewer/review/step1.tpl\n";
echo "[timestamp] ReviewerCertificate: Template displayed: reviewer/review/step2.tpl\n";
echo "[timestamp] ReviewerCertificate: Template displayed: reviewer/review/step3.tpl\n";
echo "[timestamp] ReviewerCertificate: Template displayed: reviewer/review/step4.tpl\n";
echo "[timestamp] ReviewerCertificate: Matched template for certificate button: reviewer/review/step4.tpl\n";
echo "[timestamp] ReviewerCertificate: Certificate is available or reviewer is eligible - showing button\n";
echo "[timestamp] ReviewerCertificate: Injected via echo (output buffering)\n";
echo "\n";

echo "If you see different template names, that's the key finding!\n";
echo "\n";
