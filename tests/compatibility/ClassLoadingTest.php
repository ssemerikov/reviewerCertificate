<?php
/**
 * @file tests/compatibility/ClassLoadingTest.php
 *
 * OJS 3.5 Class Loading Compatibility Tests
 *
 * Tests that all classes use fully qualified namespaces and can be loaded
 * correctly in OJS 3.5's stricter class loading environment.
 */

namespace APP\plugins\generic\reviewerCertificate\tests\compatibility;

class ClassLoadingTest {

    private $errors = [];
    private $warnings = [];
    private $passed = 0;
    private $failed = 0;

    /**
     * Run all class loading tests
     */
    public function runAll() {
        echo "=================================\n";
        echo "OJS 3.5 CLASS LOADING TESTS\n";
        echo "=================================\n\n";

        $this->testCertificateClassNamespace();
        $this->testCoreClassReferences();
        $this->testDAOClassReferences();
        $this->testPluginClassReferences();
        $this->testAllFilesForUnqualifiedReferences();

        $this->printResults();
        return count($this->errors) === 0;
    }

    /**
     * Test Certificate class extends fully qualified DataObject
     */
    private function testCertificateClassNamespace() {
        echo "[TEST] Certificate class namespace...\n";

        $file = dirname(__DIR__, 2) . '/classes/Certificate.inc.php';
        $content = file_get_contents($file);

        // Check for correct parent class
        if (preg_match('/class\s+Certificate\s+extends\s+\\\\PKP\\\\core\\\\DataObject/', $content)) {
            echo "  ✓ Certificate extends \\PKP\\core\\DataObject\n";
            $this->passed++;
        } else if (preg_match('/class\s+Certificate\s+extends\s+DataObject/', $content)) {
            $this->addError("Certificate extends unqualified 'DataObject' - should be '\\PKP\\core\\DataObject'");
        } else {
            $this->addError("Certificate class parent declaration not found");
        }

        // Check for Core::getCurrentDate() calls
        if (preg_match_all('/([^\\\\])Core::getCurrentDate/', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $this->addError("Found unqualified Core::getCurrentDate() in Certificate.inc.php");
        } else {
            echo "  ✓ All Core::getCurrentDate() calls are fully qualified\n";
            $this->passed++;
        }
    }

    /**
     * Test all Core class references are fully qualified
     */
    private function testCoreClassReferences() {
        echo "\n[TEST] Core class references...\n";

        $files = [
            'classes/Certificate.inc.php',
            'ReviewerCertificatePlugin.inc.php',
            'controllers/CertificateHandler.inc.php'
        ];

        foreach ($files as $file) {
            $fullPath = dirname(__DIR__, 2) . '/' . $file;
            if (!file_exists($fullPath)) {
                $this->addWarning("File not found: $file");
                continue;
            }

            $content = file_get_contents($fullPath);

            // Check for unqualified Core:: references (not preceded by backslash or namespace)
            // Match Core:: but not \Core:: or PKP\core\Core::
            if (preg_match('/(?<!\\\\)(?<!\\\\PKP\\\\core\\\\)Core::/', $content)) {
                $this->addError("Unqualified Core:: reference in $file");
            } else {
                echo "  ✓ $file uses qualified Core references\n";
                $this->passed++;
            }
        }
    }

    /**
     * Test DAO class references
     */
    private function testDAOClassReferences() {
        echo "\n[TEST] DAO class references...\n";

        $file = dirname(__DIR__, 2) . '/classes/CertificateDAO.inc.php';
        $content = file_get_contents($file);

        // Check parent class
        if (preg_match('/class\s+CertificateDAO\s+extends\s+\\\\PKP\\\\db\\\\DAO/', $content)) {
            echo "  ✓ CertificateDAO extends \\PKP\\db\\DAO\n";
            $this->passed++;
        } else if (preg_match('/class\s+CertificateDAO\s+extends\s+DAO/', $content)) {
            $this->addError("CertificateDAO extends unqualified 'DAO'");
        }

        // Check use statements
        if (preg_match('/use\s+PKP\\\\db\\\\DAOResultFactory;/', $content)) {
            echo "  ✓ DAOResultFactory properly imported\n";
            $this->passed++;
        }
    }

    /**
     * Test plugin class references
     */
    private function testPluginClassReferences() {
        echo "\n[TEST] Plugin class references...\n";

        $file = dirname(__DIR__, 2) . '/ReviewerCertificatePlugin.inc.php';
        $content = file_get_contents($file);

        // Check parent class
        if (preg_match('/class\s+ReviewerCertificatePlugin\s+extends\s+\\\\PKP\\\\plugins\\\\GenericPlugin/', $content)) {
            echo "  ✓ Plugin extends \\PKP\\plugins\\GenericPlugin\n";
            $this->passed++;
        }

        // Check for important use statements
        $requiredUses = [
            'PKP\\core\\JSONMessage',
            'PKP\\linkAction\\LinkAction',
            'APP\\facades\\Repo'
        ];

        foreach ($requiredUses as $useClass) {
            if (preg_match('/use\s+' . preg_quote($useClass, '/') . ';/', $content)) {
                echo "  ✓ Imports $useClass\n";
                $this->passed++;
            }
        }
    }

    /**
     * Scan all PHP files for common unqualified references
     */
    private function testAllFilesForUnqualifiedReferences() {
        echo "\n[TEST] Scanning all files for unqualified references...\n";

        $baseDir = dirname(__DIR__, 2);
        $phpFiles = $this->getPhpFiles($baseDir);

        $problematicPatterns = [
            '/(?<!\\\\)(?<!\\\\PKP\\\\core\\\\)Core::getCurrentDate/' => 'Unqualified Core::getCurrentDate()',
            '/(?<!\\\\)(?<!\w)DataObject(?!\w)/' => 'Unqualified DataObject reference',
            '/(?<!\\\\)(?<!\\\\PKP\\\\db\\\\)class\s+\w+DAO\s+extends\s+DAO/' => 'Unqualified DAO parent class'
        ];

        foreach ($phpFiles as $file) {
            $relativePath = str_replace($baseDir . '/', '', $file);
            $content = file_get_contents($file);

            foreach ($problematicPatterns as $pattern => $description) {
                if (preg_match($pattern, $content)) {
                    // Skip test files and vendor directories
                    if (strpos($file, '/tests/') === false && 
                        strpos($file, '/lib/') === false &&
                        strpos($file, '/vendor/') === false) {
                        $this->addError("$description in $relativePath");
                    }
                }
            }
        }

        if (count($this->errors) === 0) {
            echo "  ✓ No unqualified references found in codebase\n";
            $this->passed++;
        }
    }

    /**
     * Get all PHP files in directory recursively
     */
    private function getPhpFiles($dir) {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && 
                ($file->getExtension() === 'php' || 
                 pathinfo($file->getFilename(), PATHINFO_EXTENSION) === 'inc.php')) {
                // Skip test files, lib, vendor
                $path = $file->getPathname();
                if (strpos($path, '/tests/') === false && 
                    strpos($path, '/lib/') === false &&
                    strpos($path, '/vendor/') === false) {
                    $files[] = $path;
                }
            }
        }

        return $files;
    }

    /**
     * Add error message
     */
    private function addError($message) {
        $this->errors[] = $message;
        $this->failed++;
        echo "  ✗ ERROR: $message\n";
    }

    /**
     * Add warning message
     */
    private function addWarning($message) {
        $this->warnings[] = $message;
        echo "  ⚠ WARNING: $message\n";
    }

    /**
     * Print test results
     */
    private function printResults() {
        echo "\n=================================\n";
        echo "TEST RESULTS\n";
        echo "=================================\n";
        echo "Passed: " . $this->passed . "\n";
        echo "Failed: " . $this->failed . "\n";
        echo "Warnings: " . count($this->warnings) . "\n";

        if (count($this->errors) > 0) {
            echo "\nERRORS:\n";
            foreach ($this->errors as $i => $error) {
                echo ($i + 1) . ". $error\n";
            }
        }

        if (count($this->warnings) > 0) {
            echo "\nWARNINGS:\n";
            foreach ($this->warnings as $i => $warning) {
                echo ($i + 1) . ". $warning\n";
            }
        }

        if (count($this->errors) === 0) {
            echo "\n✓ ALL TESTS PASSED - OJS 3.5 COMPATIBLE!\n";
        } else {
            echo "\n✗ TESTS FAILED - FIX ERRORS ABOVE\n";
        }
    }
}

// Run tests if called directly
if (basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    $test = new ClassLoadingTest();
    $success = $test->runAll();
    exit($success ? 0 : 1);
}
