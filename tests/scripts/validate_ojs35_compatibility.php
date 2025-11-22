#!/usr/bin/env php
<?php
/**
 * @file tests/scripts/validate_ojs35_compatibility.php
 *
 * OJS 3.5 Compatibility Validation Script
 *
 * This script performs automated validation of OJS 3.5 compatibility
 * for the Reviewer Certificate Plugin. It checks for common issues
 * that would prevent the plugin from working correctly in OJS 3.5.
 *
 * Usage: php validate_ojs35_compatibility.php
 */

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║  Reviewer Certificate Plugin - OJS 3.5 Compatibility Check ║\n";
echo "║  Version 1.0.3                                             ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
echo "\n";

// Change to plugin root directory
$scriptDir = dirname(__FILE__);
$pluginDir = dirname($scriptDir, 2);
chdir($pluginDir);

$allPassed = true;

// Test 1: Run Class Loading Tests
echo "► Running Class Loading Tests...\n";
echo "─────────────────────────────────────────────────────────────\n";

require_once($pluginDir . '/tests/compatibility/ClassLoadingTest.php');
$classLoadingTest = new \APP\plugins\generic\reviewerCertificate\tests\compatibility\ClassLoadingTest();
$result = $classLoadingTest->runAll();

if (!$result) {
    $allPassed = false;
}

echo "\n";

// Test 2: File Structure Validation
echo "► Validating File Structure...\n";
echo "─────────────────────────────────────────────────────────────\n";

$requiredFiles = [
    'version.xml',
    'ReviewerCertificatePlugin.inc.php',
    'classes/Certificate.inc.php',
    'classes/CertificateDAO.inc.php',
    'classes/CertificateGenerator.inc.php',
    'classes/form/CertificateSettingsForm.inc.php',
    'classes/migration/ReviewerCertificateInstallMigration.inc.php',
    'controllers/CertificateHandler.inc.php',
];

$missingFiles = [];
foreach ($requiredFiles as $file) {
    if (!file_exists($pluginDir . '/' . $file)) {
        $missingFiles[] = $file;
        echo "  ✗ Missing: $file\n";
    } else {
        echo "  ✓ Found: $file\n";
    }
}

if (count($missingFiles) > 0) {
    $allPassed = false;
    echo "\n✗ ERROR: Missing required files\n";
} else {
    echo "\n✓ All required files present\n";
}

echo "\n";

// Test 3: Version Check
echo "► Validating Version Information...\n";
echo "─────────────────────────────────────────────────────────────\n";

$versionFile = $pluginDir . '/version.xml';
if (file_exists($versionFile)) {
    $xml = simplexml_load_file($versionFile);
    
    $version = (string) $xml->release;
    echo "  • Version: $version\n";
    
    // Check for OJS 3.5 compatibility declaration
    $ojs35Compatible = false;
    foreach ($xml->compatibility->version as $compatVersion) {
        if ((string) $compatVersion === '3.5.0.0') {
            $ojs35Compatible = true;
            break;
        }
    }
    
    if ($ojs35Compatible) {
        echo "  ✓ OJS 3.5 compatibility declared\n";
    } else {
        echo "  ✗ OJS 3.5 compatibility NOT declared\n";
        $allPassed = false;
    }
    
    if (version_compare($version, '1.0.3', '>=')) {
        echo "  ✓ Version is 1.0.3 or higher (includes OJS 3.5 fixes)\n";
    } else {
        echo "  ⚠ Version is below 1.0.3 (may have OJS 3.5 issues)\n";
    }
} else {
    echo "  ✗ version.xml not found\n";
    $allPassed = false;
}

echo "\n";

// Test 4: Namespace Usage Validation
echo "► Validating Namespace Usage...\n";
echo "─────────────────────────────────────────────────────────────\n";

$namespaceChecks = [
    'Certificate.inc.php' => [
        'pattern' => '/class\s+Certificate\s+extends\s+\\\\PKP\\\\core\\\\DataObject/',
        'description' => 'Certificate extends \\PKP\\core\\DataObject'
    ],
    'CertificateDAO.inc.php' => [
        'pattern' => '/class\s+CertificateDAO\s+extends\s+\\\\PKP\\\\db\\\\DAO/',
        'description' => 'CertificateDAO extends \\PKP\\db\\DAO'
    ],
    'ReviewerCertificatePlugin.inc.php' => [
        'pattern' => '/class\s+ReviewerCertificatePlugin\s+extends\s+\\\\PKP\\\\plugins\\\\GenericPlugin/',
        'description' => 'Plugin extends \\PKP\\plugins\\GenericPlugin'
    ]
];

foreach ($namespaceChecks as $file => $check) {
    $filePath = file_exists($pluginDir . '/classes/' . $file) 
        ? $pluginDir . '/classes/' . $file 
        : $pluginDir . '/' . $file;
    
    if (file_exists($filePath)) {
        $content = file_get_contents($filePath);
        if (preg_match($check['pattern'], $content)) {
            echo "  ✓ {$check['description']}\n";
        } else {
            echo "  ✗ {$check['description']} - FAILED\n";
            $allPassed = false;
        }
    }
}

echo "\n";

// Test 5: Database Schema Validation
echo "► Validating Database Schema...\n";
echo "─────────────────────────────────────────────────────────────\n";

$migrationFile = $pluginDir . '/classes/migration/ReviewerCertificateInstallMigration.inc.php';
if (file_exists($migrationFile)) {
    $content = file_get_contents($migrationFile);
    
    // Check for required table creation
    $requiredTables = [
        'reviewer_certificates',
        'reviewer_certificate_templates',
        'reviewer_certificate_settings'
    ];
    
    foreach ($requiredTables as $table) {
        if (strpos($content, $table) !== false) {
            echo "  ✓ Migration includes table: $table\n";
        } else {
            echo "  ✗ Migration missing table: $table\n";
            $allPassed = false;
        }
    }
} else {
    echo "  ✗ Migration file not found\n";
    $allPassed = false;
}

echo "\n";

// Test 6: Template Files Validation
echo "► Validating Template Files...\n";
echo "─────────────────────────────────────────────────────────────\n";

$templateFiles = [
    'templates/settingsForm.tpl',
    'templates/reviewerDashboard.tpl',
    'templates/verify.tpl'
];

foreach ($templateFiles as $file) {
    if (file_exists($pluginDir . '/' . $file)) {
        echo "  ✓ Template found: $file\n";
    } else {
        echo "  ⚠ Template not found: $file (may be optional)\n";
    }
}

echo "\n";

// Test 7: Locale Files Validation
echo "► Validating Locale Files...\n";
echo "─────────────────────────────────────────────────────────────\n";

$localeFiles = [
    'locale/en/locale.po',
    'locale/en_US/locale.po'
];

$hasLocale = false;
foreach ($localeFiles as $file) {
    if (file_exists($pluginDir . '/' . $file)) {
        echo "  ✓ Locale found: $file\n";
        $hasLocale = true;
    }
}

if (!$hasLocale) {
    // Try XML locale files (older format)
    $localeFiles = [
        'locale/en/locale.xml',
        'locale/en_US/locale.xml'
    ];
    foreach ($localeFiles as $file) {
        if (file_exists($pluginDir . '/' . $file)) {
            echo "  ✓ Locale found: $file\n";
            $hasLocale = true;
        }
    }
}

if (!$hasLocale) {
    echo "  ✗ No locale files found\n";
    $allPassed = false;
}

echo "\n";

// Final Summary
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║  VALIDATION SUMMARY                                        ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
echo "\n";

if ($allPassed) {
    echo "✓ ALL VALIDATION CHECKS PASSED\n";
    echo "\n";
    echo "The Reviewer Certificate Plugin appears to be fully compatible\n";
    echo "with OJS 3.5. All critical checks have passed successfully.\n";
    echo "\n";
    echo "Next Steps:\n";
    echo "  1. Install the plugin on your OJS 3.5 instance\n";
    echo "  2. Run manual testing checklist (tests/manual/OJS_3.5_TESTING_CHECKLIST.md)\n";
    echo "  3. Verify certificate generation in a test environment\n";
    echo "\n";
    exit(0);
} else {
    echo "✗ VALIDATION FAILED\n";
    echo "\n";
    echo "One or more compatibility checks failed. Please review the\n";
    echo "errors above and fix them before deploying to OJS 3.5.\n";
    echo "\n";
    echo "Common fixes:\n";
    echo "  • Ensure all classes use fully qualified namespaces\n";
    echo "  • Update version.xml to declare OJS 3.5 compatibility\n";
    echo "  • Verify all required files are present\n";
    echo "\n";
    echo "For assistance, see:\n";
    echo "  • GitHub: https://github.com/ssemerikov/reviewerCertificate/issues\n";
    echo "  • Email: semerikov@gmail.com\n";
    echo "\n";
    exit(1);
}
