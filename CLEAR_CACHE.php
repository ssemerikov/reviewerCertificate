<!DOCTYPE html>
<html>
<head>
    <title>Clear PHP OPcache</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 5px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 4px; margin: 20px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 4px; margin: 20px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; border-radius: 4px; margin: 20px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 4px; margin: 20px 0; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; }
        .button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
        .button:hover { background: #0056b3; }
        .danger { background: #dc3545; }
        .danger:hover { background: #c82333; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Clear PHP OPcache</h1>

        <?php
        $cleared = false;
        $message = '';

        if (isset($_GET['action']) && $_GET['action'] === 'clear') {
            if (function_exists('opcache_reset')) {
                $result = opcache_reset();
                if ($result) {
                    $cleared = true;
                    $message = '✅ OPcache cleared successfully!';
                } else {
                    $message = '❌ OPcache reset failed. This might happen if OPcache is disabled or restricted.';
                }
            } else {
                $message = '⚠️ OPcache functions are not available. OPcache might not be enabled on this server.';
            }
        }

        // Check OPcache status
        $opcache_enabled = function_exists('opcache_get_status');
        $status = $opcache_enabled ? @opcache_get_status() : null;
        ?>

        <?php if ($cleared): ?>
            <div class="success">
                <strong><?php echo $message; ?></strong>
                <p>The old PHP code cache has been cleared. The server will now use the latest code files.</p>
                <p><strong>Next steps:</strong></p>
                <ol>
                    <li>Clear OJS template cache (delete files in <code>cache/t_cache/</code> and <code>cache/t_compile/</code>)</li>
                    <li>Clear your browser cache (Ctrl+Shift+R)</li>
                    <li>Test the plugin again</li>
                    <li><strong style="color: red;">DELETE THIS FILE</strong> for security reasons</li>
                </ol>
            </div>
        <?php elseif (!empty($message)): ?>
            <div class="error">
                <strong><?php echo $message; ?></strong>
            </div>
        <?php endif; ?>

        <div class="info">
            <h3>OPcache Status</h3>
            <?php if ($opcache_enabled && $status): ?>
                <p><strong>✅ OPcache is ENABLED</strong></p>
                <pre><?php
                echo "Cache full: " . ($status['cache_full'] ? 'Yes' : 'No') . "\n";
                echo "Cached scripts: " . ($status['opcache_statistics']['num_cached_scripts'] ?? 'N/A') . "\n";
                echo "Cached keys: " . ($status['opcache_statistics']['num_cached_keys'] ?? 'N/A') . "\n";
                echo "Max cached keys: " . ($status['opcache_statistics']['max_cached_keys'] ?? 'N/A') . "\n";
                echo "Hit rate: " . (isset($status['opcache_statistics']['opcache_hit_rate']) ? round($status['opcache_statistics']['opcache_hit_rate'], 2) . '%' : 'N/A') . "\n";
                echo "Memory used: " . (isset($status['memory_usage']['used_memory']) ? round($status['memory_usage']['used_memory'] / 1024 / 1024, 2) . ' MB' : 'N/A') . "\n";
                echo "Memory free: " . (isset($status['memory_usage']['free_memory']) ? round($status['memory_usage']['free_memory'] / 1024 / 1024, 2) . ' MB' : 'N/A') . "\n";
                ?></pre>
                <p><strong>⚠️ This is why your server is running old code!</strong> PHP is caching the old bytecode.</p>
            <?php elseif ($opcache_enabled): ?>
                <p><strong>⚠️ OPcache extension is loaded but not active</strong></p>
            <?php else: ?>
                <p><strong>❌ OPcache is NOT enabled</strong></p>
                <p>If OPcache is not enabled, the issue might be another cache layer (APC, file-based cache, etc.)</p>
            <?php endif; ?>
        </div>

        <?php if (!$cleared): ?>
            <div class="warning">
                <h3>⚠️ Warning</h3>
                <p>Clearing OPcache will temporarily slow down your website as PHP files need to be re-compiled. This is normal and will only last a few seconds.</p>
            </div>

            <p>
                <a href="?action=clear" class="button">Clear OPcache Now</a>
            </p>
        <?php endif; ?>

        <hr style="margin: 30px 0;">

        <div class="warning">
            <h3>🔒 Security Warning</h3>
            <p><strong>DELETE THIS FILE IMMEDIATELY after use!</strong></p>
            <p>This file can be used by anyone to clear your server's cache and should not be left accessible.</p>
            <p>Delete it by running:</p>
            <pre>rm /home/easyscie/acnsci.org/journal/plugins/generic/reviewerCertificate/CLEAR_CACHE.php</pre>
            <p>Or via FTP/File Manager: Delete <code>plugins/generic/reviewerCertificate/CLEAR_CACHE.php</code></p>
        </div>

        <div class="info">
            <h3>📋 System Information</h3>
            <pre><?php
            echo "PHP Version: " . phpversion() . "\n";
            echo "Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "\n";
            echo "OPcache Extension Loaded: " . (extension_loaded('opcache') ? 'Yes' : 'No') . "\n";
            echo "APC Extension Loaded: " . (extension_loaded('apc') ? 'Yes' : 'No') . "\n";
            echo "APCu Extension Loaded: " . (extension_loaded('apcu') ? 'Yes' : 'No') . "\n";
            ?></pre>
        </div>

        <hr style="margin: 30px 0;">

        <h3>Alternative: Contact Hosting Provider</h3>
        <p>If clearing OPcache via this script doesn't work, contact your hosting provider and ask them to:</p>
        <ol>
            <li><strong>Restart PHP-FPM service</strong> - This will clear all PHP caches</li>
            <li>OR restart Apache/Nginx</li>
            <li>Provide you with access to restart PHP yourself</li>
        </ol>

        <p><strong>Tell them:</strong> "The website is serving cached PHP bytecode from OPcache. I need to clear the cache or restart PHP-FPM to load new code files."</p>
    </div>
</body>
</html>
