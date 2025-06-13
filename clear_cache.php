<?php
/**
 * Cache Clearing Utility
 * Clears various types of caching to ensure navigation updates are visible
 */

echo "<h2>🔧 Cache Clearing Utility</h2>\n";

// 1. Clear OPcache
if (function_exists('opcache_reset')) {
    if (opcache_reset()) {
        echo "✅ OPcache cleared successfully!\n";
    } else {
        echo "❌ Failed to clear OPcache\n";
    }
} else {
    echo "ℹ️  OPcache function not available\n";
}

// 2. Clear file-based cache
$cache_dir = __DIR__ . '/html/storage/cache/';
if (is_dir($cache_dir)) {
    $files = glob($cache_dir . '*.{json,cache,tmp}', GLOB_BRACE);
    $cleared = 0;
    foreach ($files as $file) {
        if (unlink($file)) {
            $cleared++;
        }
    }
    echo "✅ Cleared $cleared cache files from storage\n";
} else {
    echo "ℹ️  No storage cache directory found\n";
}

// 3. Check for any session-based caching
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (isset($_SESSION['nav_cache'])) {
    unset($_SESSION['nav_cache']);
    echo "✅ Cleared navigation session cache\n";
}

// 4. Force refresh of included files by touching them
$files_to_refresh = [
    __DIR__ . '/html/core/includes/navbar.php',
    __DIR__ . '/html/core/includes/header.php',
    __DIR__ . '/html/business/includes/cards/nayax_analytics.php'
];

foreach ($files_to_refresh as $file) {
    if (file_exists($file)) {
        touch($file);
        echo "✅ Refreshed: " . basename($file) . "\n";
    }
}

// 5. Clear any PHP APC cache if available
if (function_exists('apc_clear_cache')) {
    apc_clear_cache();
    echo "✅ APC cache cleared\n";
}

// 6. Clear APCu cache if available
if (function_exists('apcu_clear_cache')) {
    apcu_clear_cache();
    echo "✅ APCu cache cleared\n";
}

echo "\n<h3>📋 Cache Status</h3>\n";
echo "Navbar file modified: " . date('Y-m-d H:i:s', filemtime(__DIR__ . '/html/core/includes/navbar.php')) . "\n";
echo "Current time: " . date('Y-m-d H:i:s') . "\n";

// 7. Verify Nayax content is in navbar
$navbar_content = file_get_contents(__DIR__ . '/html/core/includes/navbar.php');
if (strpos($navbar_content, 'Nayax') !== false) {
    echo "✅ Nayax navigation found in navbar file\n";
} else {
    echo "❌ Nayax navigation NOT found in navbar file\n";
}

echo "\n<h3>🔄 Next Steps</h3>\n";
echo "1. Hard refresh your browser (Ctrl+F5 or Cmd+Shift+R)\n";
echo "2. Try accessing the site in incognito/private mode\n";
echo "3. Check if you're logged in with Business or Admin role\n";
echo "4. Look for 'Nayax' dropdown in the main navigation bar\n";

echo "\n✅ Cache clearing complete! Please refresh your browser.\n";
?> 