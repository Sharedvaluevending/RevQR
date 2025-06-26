<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/auth.php';

// Clear PHP OPcache if available
if (function_exists('opcache_reset')) {
    opcache_reset();
    $opcache_cleared = true;
} else {
    $opcache_cleared = false;
}

// Set no-cache headers for this response
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Get current navigation status
$nav_status = [
    'header_file' => __DIR__ . '/core/includes/header.php',
    'navbar_file' => __DIR__ . '/core/includes/navbar.php',
    'header_exists' => file_exists(__DIR__ . '/core/includes/header.php'),
    'navbar_exists' => file_exists(__DIR__ . '/core/includes/navbar.php'),
    'navbar_modified' => file_exists(__DIR__ . '/core/includes/navbar.php') ? filemtime(__DIR__ . '/core/includes/navbar.php') : null,
    'has_qr_manager' => false
];

// Check if navbar contains QR Manager
if (file_exists(__DIR__ . '/core/includes/navbar.php')) {
    $navbar_content = file_get_contents(__DIR__ . '/core/includes/navbar.php');
    $nav_status['has_qr_manager'] = strpos($navbar_content, 'QR Manager') !== false;
}

$page_title = 'Navigation System Refresh';
require_once __DIR__ . '/core/includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-12">
            <div class="card bg-primary text-white mb-4">
                <div class="card-header">
                    <h1 class="card-title mb-0">🔄 Navigation System Refresh</h1>
                </div>
                <div class="card-body">
                    <p class="mb-0">This page forces a refresh of the navigation system and clears caches to ensure consistent navigation across all platform pages.</p>
                </div>
            </div>

            <!-- System Status -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">📁 Navigation Files Status</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled mb-0">
                                <li><strong>Header File:</strong> <?php echo $nav_status['header_exists'] ? '✅ Exists' : '❌ Missing'; ?></li>
                                <li><strong>Navbar File:</strong> <?php echo $nav_status['navbar_exists'] ? '✅ Exists' : '❌ Missing'; ?></li>
                                <li><strong>QR Manager Link:</strong> <?php echo $nav_status['has_qr_manager'] ? '✅ Present' : '❌ Missing'; ?></li>
                                <li><strong>Last Modified:</strong> <?php echo $nav_status['navbar_modified'] ? date('Y-m-d H:i:s', $nav_status['navbar_modified']) : 'N/A'; ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">🧹 Cache Status</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled mb-0">
                                <li><strong>PHP OPcache:</strong> <?php echo $opcache_cleared ? '✅ Cleared' : '⚠️ Not Available'; ?></li>
                                <li><strong>Response Headers:</strong> ✅ No-Cache Set</li>
                                <li><strong>Browser Cache:</strong> ⚠️ User Must Clear</li>
                                <li><strong>Session Active:</strong> <?php echo is_logged_in() ? '✅ Yes' : '❌ No'; ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Test Links -->
            <div class="card mb-4">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">🧪 Test Navigation Consistency</h5>
                </div>
                <div class="card-body">
                    <p>Click these links to test if navigation is now consistent across pages:</p>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="list-group">
                                <a href="<?php echo APP_URL; ?>/qr_manager.php" class="list-group-item list-group-item-action" target="_blank">
                                    🎯 <strong>QR Manager</strong> (Updated Page)
                                </a>
                                <a href="<?php echo APP_URL; ?>/qr-generator-enhanced.php" class="list-group-item list-group-item-action" target="_blank">
                                    🎨 <strong>Enhanced QR Generator</strong> (Should Match)
                                </a>
                                <a href="<?php echo APP_URL; ?>/qr-generator.php" class="list-group-item list-group-item-action" target="_blank">
                                    ⚡ <strong>Quick QR Generator</strong>
                                </a>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="list-group">
                                <a href="<?php echo APP_URL; ?>/business/dashboard_enhanced.php" class="list-group-item list-group-item-action" target="_blank">
                                    📊 <strong>Business Dashboard</strong>
                                </a>
                                <a href="<?php echo APP_URL; ?>/qr-display.php" class="list-group-item list-group-item-action" target="_blank">
                                    📺 <strong>QR Display Mode</strong>
                                </a>
                                <a href="<?php echo APP_URL; ?>/business/analytics/index.php" class="list-group-item list-group-item-action" target="_blank">
                                    📈 <strong>Analytics Dashboard</strong>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- User Instructions -->
            <div class="card mb-4">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">👤 User Action Required</h5>
                </div>
                <div class="card-body">
                    <h6>To see the updated navigation, users need to:</h6>
                    <ol>
                        <li><strong>Hard Refresh:</strong> Press <kbd>Ctrl+F5</kbd> (Windows) or <kbd>Cmd+Shift+R</kbd> (Mac)</li>
                        <li><strong>Clear Browser Cache:</strong> 
                            <ul>
                                <li>Chrome: Settings → Privacy → Clear browsing data</li>
                                <li>Firefox: Settings → Privacy → Clear Data</li>
                                <li>Safari: Develop → Empty Caches</li>
                            </ul>
                        </li>
                        <li><strong>Incognito/Private Mode:</strong> Test in a private browser window</li>
                        <li><strong>Different Browser:</strong> Try opening in a different browser</li>
                    </ol>
                </div>
            </div>

            <!-- Technical Details -->
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">🔧 Technical Details</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Navigation System:</h6>
                            <ul class="small">
                                <li>Both pages use: <code>html/core/includes/header.php</code></li>
                                <li>Header includes: <code>html/core/includes/navbar.php</code></li>
                                <li>QR Manager link is in "QR Codes" dropdown</li>
                                <li>Navigation is role-based (business users only)</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Session Information:</h6>
                            <ul class="small">
                                <li><strong>User ID:</strong> <?php echo $_SESSION['user_id'] ?? 'Not set'; ?></li>
                                <li><strong>Role:</strong> <?php echo $_SESSION['role'] ?? 'Not set'; ?></li>
                                <li><strong>Business ID:</strong> <?php echo $_SESSION['business_id'] ?? 'Not set'; ?></li>
                                <li><strong>Logged In:</strong> <?php echo is_logged_in() ? 'Yes' : 'No'; ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4 text-center">
                <a href="<?php echo APP_URL; ?>/business/dashboard_enhanced.php" class="btn btn-primary btn-lg">
                    ← Return to Dashboard
                </a>
                <button onclick="location.reload(true)" class="btn btn-secondary btn-lg">
                    🔄 Hard Refresh This Page
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Force reload of all navigation-related resources
document.addEventListener('DOMContentLoaded', function() {
    // Add timestamp to all navigation-related requests to bust cache
    var timestamp = new Date().getTime();
    
    // Check if navigation has loaded properly
    var qrManagerLink = document.querySelector('a[href*="qr_manager.php"]');
    if (qrManagerLink) {
        console.log('✅ QR Manager link found in navigation');
    } else {
        console.log('❌ QR Manager link NOT found in navigation');
        console.log('This indicates a caching issue - user should clear browser cache');
    }
    
    // Auto-check navigation consistency
    setTimeout(function() {
        var navDropdowns = document.querySelectorAll('.navbar .dropdown-menu');
        console.log('Found ' + navDropdowns.length + ' navigation dropdowns');
        
        var qrCodesDropdown = null;
        navDropdowns.forEach(function(dropdown) {
            if (dropdown.innerHTML.includes('QR Manager')) {
                qrCodesDropdown = dropdown;
            }
        });
        
        if (qrCodesDropdown) {
            console.log('✅ QR Codes dropdown with QR Manager found');
        } else {
            console.log('❌ QR Codes dropdown with QR Manager NOT found');
        }
    }, 1000);
});

// Function to force refresh with cache busting
function forceFullRefresh() {
    // Clear localStorage and sessionStorage
    if (typeof(Storage) !== "undefined") {
        localStorage.clear();
        sessionStorage.clear();
    }
    
    // Force reload with cache busting
    window.location.href = window.location.href + (window.location.href.includes('?') ? '&' : '?') + 'cache_bust=' + new Date().getTime();
}
</script>

<style>
kbd {
    background-color: #212529;
    color: #fff;
    padding: 0.2rem 0.4rem;
    font-size: 0.875rem;
    border-radius: 0.2rem;
}

.list-group-item-action:hover {
    background-color: rgba(0,123,255,0.1);
}

.small {
    font-size: 0.875rem;
}
</style> 