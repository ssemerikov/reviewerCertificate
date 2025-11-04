#!/usr/bin/env php
<?php
/**
 * Force OPcache Reload Script
 *
 * This script forces PHP to reload bytecode cache by touching all PHP files
 * in the plugin directory, updating their modification time.
 *
 * Usage:
 *   php FORCE_RELOAD_OPCACHE.php
 *   OR visit in browser: https://yoursite.com/plugins/generic/reviewerCertificate/FORCE_RELOAD_OPCACHE.php
 */

// Set content type for browser access
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/html; charset=utf-8');
    echo "<html><head><title>OPcache Force Reload</title></head><body>";
    echo "<h1>Force OPcache Reload</h1>";
    echo "<pre>";
}

echo "=== OPcache Force Reload Script ===\n\n";

// Check if OPcache is enabled
if (function_exists('opcache_get_status')) {
    $status = opcache_get_status();
    if ($status !== false) {
        echo "✓ OPcache is ENABLED\n";
        echo "  - Used memory: " . number_format($status['memory_usage']['used_memory'] / 1024 / 1024, 2) . " MB\n";
        echo "  - Cached scripts: " . $status['opcache_statistics']['num_cached_scripts'] . "\n";
        echo "  - Hits: " . number_format($status['opcache_statistics']['hits']) . "\n";
        echo "  - Misses: " . number_format($status['opcache_statistics']['misses']) . "\n\n";
    } else {
        echo "✗ OPcache is installed but currently DISABLED\n\n";
    }
} else {
    echo "✗ OPcache is NOT installed\n\n";
}

// Try to reset OPcache
echo "Attempting to reset OPcache...\n";
if (function_exists('opcache_reset')) {
    if (opcache_reset()) {
        echo "✓ OPcache reset successful!\n\n";
    } else {
        echo "✗ OPcache reset failed (may require server-level permissions)\n\n";
    }
} else {
    echo "✗ opcache_reset() function not available\n\n";
}

// Touch all PHP files to force reload
echo "Touching all PHP files in plugin directory...\n";
$pluginDir = __DIR__;
$count = 0;
$errors = 0;

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($pluginDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $filepath = $file->getPathname();

        // Skip this script itself
        if ($filepath === __FILE__) {
            continue;
        }

        // Touch the file (update modification time)
        if (touch($filepath)) {
            $count++;
            echo "  ✓ " . str_replace($pluginDir . '/', '', $filepath) . "\n";

            // Also try to invalidate this specific file in OPcache
            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($filepath, true);
            }
        } else {
            $errors++;
            echo "  ✗ FAILED: " . str_replace($pluginDir . '/', '', $filepath) . "\n";
        }
    }
}

echo "\n=== Summary ===\n";
echo "✓ Touched $count PHP files\n";
if ($errors > 0) {
    echo "✗ Failed to touch $errors files\n";
}

// Clear OJS cache directories
echo "\nClearing OJS cache directories...\n";
$ojsRoot = dirname(dirname(dirname(__DIR__)));
$cacheDir = $ojsRoot . '/cache';

if (is_dir($cacheDir)) {
    $cleared = clearDirectory($cacheDir);
    echo "  ✓ Cleared $cleared cache files\n";
} else {
    echo "  ✗ Cache directory not found: $cacheDir\n";
}

echo "\n=== DONE ===\n";
echo "\nNow test your plugin functionality:\n";
echo "1. Try batch certificate generation\n";
echo "2. Try certificate download\n";
echo "3. Check error logs for new diagnostic messages\n";

if (php_sapi_name() !== 'cli') {
    echo "</pre>";
    echo "<p><strong>Reload complete!</strong> Close this window and test the plugin.</p>";
    echo "</body></html>";
}

/**
 * Recursively clear a directory
 */
function clearDirectory($dir) {
    $count = 0;

    if (!is_dir($dir)) {
        return 0;
    }

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $file) {
        if ($file->isFile() && $file->getFilename() !== '.htaccess') {
            if (@unlink($file->getPathname())) {
                $count++;
            }
        }
    }

    return $count;
}
