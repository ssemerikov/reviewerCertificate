<?php
/**
 * @file tests/Unit/CompatAutoloaderTest.php
 *
 * Copyright (c) 2024
 * Distributed under the GNU GPL v3.
 *
 * @brief Tests for the OJS 3.3 Compatibility Autoloader
 */

namespace APP\plugins\generic\reviewerCertificate\tests\Unit;

use PHPUnit\Framework\TestCase;

class CompatAutoloaderTest extends TestCase {

    /**
     * Path to the autoloader file
     */
    private $autoloaderPath;

    /**
     * Path to the main plugin file
     */
    private $mainPluginPath;

    /**
     * Path to the core plugin implementation
     */
    private $corePluginPath;

    protected function setUp(): void {
        parent::setUp();
        $this->autoloaderPath = dirname(__DIR__, 2) . '/compat_autoloader.php';
        $this->mainPluginPath = dirname(__DIR__, 2) . '/ReviewerCertificatePlugin.php';
        $this->corePluginPath = dirname(__DIR__, 2) . '/classes/ReviewerCertificatePluginCore.php';
    }

    /**
     * Test that autoloader file exists
     */
    public function testAutoloaderFileExists(): void {
        $this->assertFileExists(
            $this->autoloaderPath,
            'compat_autoloader.php should exist'
        );
    }

    /**
     * Test that main plugin file exists and is a loader
     */
    public function testMainPluginIsLoader(): void {
        $this->assertFileExists($this->mainPluginPath);

        $content = file_get_contents($this->mainPluginPath);

        // Main file should NOT have a namespace declaration
        $this->assertDoesNotMatchRegularExpression(
            '/^namespace\s+/m',
            $content,
            'Main plugin file should not have a namespace (it is a loader)'
        );

        // Main file should include the autoloader
        $this->assertMatchesRegularExpression(
            '/require_once.*compat_autoloader\.php/',
            $content,
            'Main plugin file should include the autoloader'
        );

        // Main file should include the core implementation
        $this->assertMatchesRegularExpression(
            '/require_once.*ReviewerCertificatePluginCore\.php/',
            $content,
            'Main plugin file should include the core implementation'
        );
    }

    /**
     * Test that core plugin implementation exists with namespace
     */
    public function testCorePluginHasNamespace(): void {
        $this->assertFileExists($this->corePluginPath);

        $content = file_get_contents($this->corePluginPath);

        // Core file SHOULD have a namespace declaration
        $this->assertMatchesRegularExpression(
            '/^namespace\s+APP\\\\plugins\\\\generic\\\\reviewerCertificate;/m',
            $content,
            'Core plugin file should have the correct namespace'
        );
    }

    /**
     * Test autoloader has guard constant
     */
    public function testAutoloaderHasGuardConstant(): void {
        $content = file_get_contents($this->autoloaderPath);

        $this->assertStringContainsString(
            'REVIEWER_CERTIFICATE_COMPAT_AUTOLOADER',
            $content,
            'Autoloader should use a guard constant to prevent duplicate registration'
        );

        $this->assertMatchesRegularExpression(
            '/if\s*\(\s*!defined\s*\(\s*[\'"]REVIEWER_CERTIFICATE_COMPAT_AUTOLOADER[\'"]\s*\)\s*\)/',
            $content,
            'Autoloader should check if guard constant is defined'
        );
    }

    /**
     * Test autoloader uses spl_autoload_register with prepend
     */
    public function testAutoloaderUsesPrependFlag(): void {
        $content = file_get_contents($this->autoloaderPath);

        // Should use spl_autoload_register with prepend=true (comment at end of line)
        $this->assertStringContainsString(
            'true, true);',
            $content,
            'Autoloader should use spl_autoload_register with prepend=true'
        );
    }

    /**
     * Test class map contains essential OJS classes
     */
    public function testClassMapContainsEssentialClasses(): void {
        $content = file_get_contents($this->autoloaderPath);

        // Classes as they appear literally in the PHP source file (with escaped backslashes)
        $essentialClasses = [
            "PKP\\\\plugins\\\\GenericPlugin",
            "PKP\\\\db\\\\DAORegistry",
            "PKP\\\\db\\\\DAO",
            "PKP\\\\plugins\\\\Hook",
            "PKP\\\\config\\\\Config",
            "PKP\\\\core\\\\Core",
            "PKP\\\\core\\\\JSONMessage",
            "PKP\\\\core\\\\DataObject",
            "PKP\\\\form\\\\Form",
            "APP\\\\handler\\\\Handler",
            "APP\\\\core\\\\Application",
            "APP\\\\template\\\\TemplateManager",
        ];

        foreach ($essentialClasses as $class) {
            $this->assertStringContainsString(
                "'" . $class . "'",
                $content,
                "Class map should contain mapping for $class"
            );
        }
    }

    /**
     * Test class map has correct OJS 3.3 global class names
     */
    public function testClassMapHasCorrectGlobalNames(): void {
        $content = file_get_contents($this->autoloaderPath);

        // Mappings as they appear literally in the PHP source file
        $mappings = [
            "PKP\\\\plugins\\\\GenericPlugin" => 'GenericPlugin',
            "PKP\\\\db\\\\DAORegistry" => 'DAORegistry',
            "PKP\\\\db\\\\DAO" => 'DAO',
            "PKP\\\\plugins\\\\Hook" => 'HookRegistry',  // Note: Hook maps to HookRegistry
            "PKP\\\\core\\\\Core" => 'Core',
            "PKP\\\\form\\\\Form" => 'Form',
            "APP\\\\handler\\\\Handler" => 'Handler',
            "APP\\\\core\\\\Application" => 'Application',
        ];

        foreach ($mappings as $namespaced => $global) {
            // Check that the mapping exists with the correct global class name
            $this->assertStringContainsString(
                "'" . $namespaced . "' => ['" . $global . "'",
                $content,
                "$namespaced should map to global class '$global'"
            );
        }
    }

    /**
     * Test autoloader calls import() for OJS 3.3
     */
    public function testAutoloaderCallsImport(): void {
        $content = file_get_contents($this->autoloaderPath);

        $this->assertStringContainsString(
            "function_exists('import')",
            $content,
            'Autoloader should check if import() function exists'
        );

        $this->assertStringContainsString(
            'import($importPath)',
            $content,
            'Autoloader should call import() with the import path'
        );
    }

    /**
     * Test autoloader uses class_alias
     */
    public function testAutoloaderUsesClassAlias(): void {
        $content = file_get_contents($this->autoloaderPath);

        $this->assertStringContainsString(
            'class_alias($globalClass, $class)',
            $content,
            'Autoloader should use class_alias to create the namespace alias'
        );
    }

    /**
     * Test two-file architecture loads in correct order
     */
    public function testTwoFileArchitectureOrder(): void {
        $content = file_get_contents($this->mainPluginPath);

        // Find positions of the two require_once statements
        $autoloaderPos = strpos($content, 'compat_autoloader.php');
        $corePos = strpos($content, 'ReviewerCertificatePluginCore.php');

        $this->assertNotFalse($autoloaderPos, 'Autoloader include should be present');
        $this->assertNotFalse($corePos, 'Core include should be present');
        $this->assertLessThan(
            $corePos,
            $autoloaderPos,
            'Autoloader must be included BEFORE the core implementation'
        );
    }

    /**
     * Test main plugin creates global alias for OJS 3.3
     */
    public function testMainPluginCreatesGlobalAlias(): void {
        $content = file_get_contents($this->mainPluginPath);

        $this->assertStringContainsString(
            'class_alias',
            $content,
            'Main plugin should create a class alias for OJS 3.3 compatibility'
        );

        $this->assertMatchesRegularExpression(
            '/class_alias\s*\(\s*[\'"]APP\\\\\\\\plugins\\\\\\\\generic\\\\\\\\reviewerCertificate\\\\\\\\ReviewerCertificatePlugin[\'"]/',
            $content,
            'Main plugin should alias the namespaced class to global'
        );
    }

    /**
     * Test all PHP files have valid syntax
     */
    public function testAllNewFilesHaveValidSyntax(): void {
        $files = [
            $this->autoloaderPath,
            $this->mainPluginPath,
            $this->corePluginPath,
        ];

        foreach ($files as $file) {
            $output = [];
            $exitCode = 0;
            exec("php -l " . escapeshellarg($file) . " 2>&1", $output, $exitCode);

            $this->assertEquals(
                0,
                $exitCode,
                "PHP syntax check failed for " . basename($file) . ": " . implode("\n", $output)
            );
        }
    }

    /**
     * Test import paths in class map are valid OJS paths
     */
    public function testImportPathsAreValid(): void {
        $content = file_get_contents($this->autoloaderPath);

        // Extract all import paths from the class map
        preg_match_all("/=>\s*\[['\"][^'\"]+['\"],\s*['\"]([^'\"]+)['\"]\]/", $content, $matches);

        $this->assertNotEmpty($matches[1], 'Should find import paths in class map');

        foreach ($matches[1] as $importPath) {
            // Import paths should follow OJS convention: lib.pkp.classes.xxx or classes.xxx
            $this->assertMatchesRegularExpression(
                '/^(lib\.pkp\.classes|classes)\.[a-zA-Z.]+$/',
                $importPath,
                "Import path '$importPath' should follow OJS naming convention"
            );
        }
    }

    /**
     * Test core plugin class extends GenericPlugin correctly
     */
    public function testCorePluginExtendsGenericPlugin(): void {
        $content = file_get_contents($this->corePluginPath);

        $this->assertMatchesRegularExpression(
            '/class\s+ReviewerCertificatePlugin\s+extends\s+GenericPlugin/',
            $content,
            'Core plugin should extend GenericPlugin'
        );

        $this->assertMatchesRegularExpression(
            '/use\s+PKP\\\\plugins\\\\GenericPlugin;/',
            $content,
            'Core plugin should import GenericPlugin via use statement'
        );
    }

    /**
     * Test autoloader handles class normalization
     */
    public function testAutoloaderNormalizesClassName(): void {
        $content = file_get_contents($this->autoloaderPath);

        $this->assertStringContainsString(
            "str_replace('/', '\\\\', \$class)",
            $content,
            'Autoloader should normalize forward slashes to backslashes'
        );
    }

    /**
     * Test autoloader returns appropriate values
     */
    public function testAutoloaderReturnsCorrectly(): void {
        $content = file_get_contents($this->autoloaderPath);

        // Should return true when alias is created
        $this->assertStringContainsString(
            'return true;',
            $content,
            'Autoloader should return true when alias is created'
        );

        // Should return false when class not in map
        $this->assertStringContainsString(
            'return false;',
            $content,
            'Autoloader should return false when class is not handled'
        );
    }

    /**
     * Test form validation classes are in class map
     */
    public function testFormValidationClassesInMap(): void {
        $content = file_get_contents($this->autoloaderPath);

        // Classes as they appear literally in PHP source
        $formClasses = [
            "PKP\\\\form\\\\validation\\\\FormValidator",
            "PKP\\\\form\\\\validation\\\\FormValidatorPost",
            "PKP\\\\form\\\\validation\\\\FormValidatorCSRF",
        ];

        foreach ($formClasses as $class) {
            $this->assertStringContainsString(
                "'" . $class . "'",
                $content,
                "Class map should contain $class for form validation support"
            );
        }
    }

    /**
     * Test link action classes are in class map
     */
    public function testLinkActionClassesInMap(): void {
        $content = file_get_contents($this->autoloaderPath);

        // Classes as they appear literally in PHP source
        $linkClasses = [
            "PKP\\\\linkAction\\\\LinkAction",
            "PKP\\\\linkAction\\\\request\\\\AjaxModal",
        ];

        foreach ($linkClasses as $class) {
            $this->assertStringContainsString(
                "'" . $class . "'",
                $content,
                "Class map should contain $class for UI action support"
            );
        }
    }
}
