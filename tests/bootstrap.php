<?php
/**
 * PHPUnit Bootstrap File for Reviewer Certificate Plugin
 *
 * This file initializes the test environment and sets up necessary
 * mocks and stubs for OJS integration testing.
 */

// Prevent direct execution
if (!defined('PHPUNIT_TEST')) {
    define('PHPUNIT_TEST', true);
}

// Set error reporting for tests
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Define base paths
define('BASE_SYS_DIR', dirname(__FILE__, 2));
define('TESTS_DIR', __DIR__);
define('FIXTURES_DIR', TESTS_DIR . '/fixtures');
define('MOCKS_DIR', TESTS_DIR . '/mocks');

// Auto-detect OJS version from environment or use default
$ojsVersion = getenv('OJS_VERSION') ?: '3.4';
define('OJS_VERSION', $ojsVersion);

// Mock OJS installation path (can be overridden by environment variable)
$ojsPath = getenv('OJS_PATH');
if (!$ojsPath) {
    // Default mock path for testing
    $ojsPath = BASE_SYS_DIR . '/tests/mocks/ojs';
}
define('OJS_PATH', $ojsPath);

// Load the test helper classes first
require_once MOCKS_DIR . '/OJSMockLoader.php';
require_once MOCKS_DIR . '/DatabaseMock.php';
require_once TESTS_DIR . '/TestCase.php';

// Initialize OJS mocks
OJSMockLoader::initialize(OJS_VERSION);

// Autoloader for plugin classes
spl_autoload_register(function ($className) {
    // Remove namespace prefix if present
    $className = str_replace('APP\\plugins\\generic\\reviewerCertificate\\', '', $className);

    // Convert class name to file path
    $possiblePaths = [
        BASE_SYS_DIR . '/' . $className . '.inc.php',
        BASE_SYS_DIR . '/classes/' . $className . '.inc.php',
        BASE_SYS_DIR . '/classes/form/' . $className . '.inc.php',
    ];

    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return true;
        }
    }

    return false;
});

// Create necessary test directories
$testDirs = [
    TESTS_DIR . '/coverage',
    TESTS_DIR . '/logs',
    TESTS_DIR . '/tmp',
];

foreach ($testDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Set up test database configuration
if (!defined('DB_TEST_CONFIG')) {
    define('DB_TEST_CONFIG', [
        'driver' => getenv('DB_DRIVER') ?: 'sqlite',
        'host' => getenv('DB_HOST') ?: ':memory:',
        'username' => getenv('DB_USERNAME') ?: '',
        'password' => getenv('DB_PASSWORD') ?: '',
        'database' => getenv('DB_DATABASE') ?: ':memory:',
        'prefix' => 'ojs_',
    ]);
}

// Clean up function for test isolation
function cleanupTestEnvironment() {
    $tmpDir = TESTS_DIR . '/tmp';
    if (is_dir($tmpDir)) {
        $files = glob($tmpDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}

// Register shutdown function
register_shutdown_function('cleanupTestEnvironment');

echo "\n";
echo "========================================\n";
echo "Reviewer Certificate Plugin Test Suite\n";
echo "========================================\n";
echo "OJS Version: " . OJS_VERSION . "\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Test Mode: " . (getenv('TEST_MODE') ?: 'standard') . "\n";
echo "========================================\n\n";
