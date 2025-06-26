<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/auth.php';

// Require business role
require_role('business');

$nav_info = [
    'current_page' => $_SERVER['REQUEST_URI'],
    'header_file' => __DIR__ . '/core/includes/header.php',
    'navbar_file' => __DIR__ . '/core/includes/navbar.php',
    'session_data' => $_SESSION,
    'app_url' => APP_URL
];

require_once __DIR__ . '/core/includes/header.php';
?>

<div class="container mt-5 pt-4">
    <div class="row">
        <div class="col-12">
            <h1>🔍 Navigation Diagnostic Page</h1>
            <p class="text-muted">This page helps diagnose navigation inconsistencies</p>
            
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Navigation File Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Current Page:</h6>
                            <code><?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?></code>
                            
                            <h6 class="mt-3">Header File:</h6>
                            <code><?php echo htmlspecialchars($nav_info['header_file']); ?></code>
                            <br><small class="text-muted">Exists: <?php echo file_exists($nav_info['header_file']) ? '✅ Yes' : '❌ No'; ?></small>
                            
                            <h6 class="mt-3">Navbar File:</h6>
                            <code><?php echo htmlspecialchars($nav_info['navbar_file']); ?></code>
                            <br><small class="text-muted">Exists: <?php echo file_exists($nav_info['navbar_file']) ? '✅ Yes' : '❌ No'; ?></small>
                            
                            <h6 class="mt-3">APP_URL:</h6>
                            <code><?php echo htmlspecialchars(APP_URL); ?></code>
                        </div>
                        <div class="col-md-6">
                            <h6>User Session:</h6>
                            <ul class="list-unstyled">
                                <li><strong>User ID:</strong> <?php echo $_SESSION['user_id'] ?? 'Not set'; ?></li>
                                <li><strong>Role:</strong> <?php echo $_SESSION['role'] ?? 'Not set'; ?></li>
                                <li><strong>Business ID:</strong> <?php echo $_SESSION['business_id'] ?? 'Not set'; ?></li>
                                <li><strong>User Name:</strong> <?php echo $_SESSION['user_name'] ?? 'Not set'; ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Expected QR Navigation Items</h5>
                </div>
                <div class="card-body">
                    <p>The QR Codes dropdown should contain these items (in this order):</p>
                    <ol>
                        <li><strong>QR Manager</strong> <span class="badge bg-primary">New</span> - Primary option</li>
                        <li><em>--- Divider ---</em></li>
                        <li><strong>Quick Generator</strong> - Basic QR generator</li>
                        <li><strong>Enhanced Generator</strong> - Advanced QR generator</li>
                        <li><em>--- Divider ---</em></li>
                        <li><strong>Display Mode</strong> - Fullscreen QR display</li>
                    </ol>
                    
                    <div class="alert alert-info mt-3">
                        <strong>🔍 Check your navigation:</strong> Look at the "QR Codes" dropdown in the top navigation. 
                        If you see different items or the old "My QR Codes" link, there may be a caching issue.
                    </div>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Navigation Links Test</h5>
                </div>
                <div class="card-body">
                    <p>Click these links to test the navigation system:</p>
                    <div class="btn-group-vertical d-grid gap-2">
                        <a href="<?php echo APP_URL; ?>/qr_manager.php" class="btn btn-primary">
                            🎯 QR Manager (New Unified Page)
                        </a>
                        <a href="<?php echo APP_URL; ?>/qr-generator.php" class="btn btn-outline-secondary">
                            ⚡ Quick Generator
                        </a>
                        <a href="<?php echo APP_URL; ?>/qr-generator-enhanced.php" class="btn btn-outline-secondary">
                            🎨 Enhanced Generator
                        </a>
                        <a href="<?php echo APP_URL; ?>/qr-display.php" class="btn btn-outline-secondary">
                            📺 Display Mode
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h5>Troubleshooting Steps</h5>
                </div>
                <div class="card-body">
                    <ol>
                        <li><strong>Hard Refresh:</strong> Press Ctrl+F5 (or Cmd+Shift+R on Mac)</li>
                        <li><strong>Clear Browser Cache:</strong> Clear your browser's cache and cookies</li>
                        <li><strong>Check Different Pages:</strong> Navigate to different business pages and check if navigation is consistent</li>
                        <li><strong>Incognito Mode:</strong> Try opening the site in an incognito/private browser window</li>
                    </ol>
                    
                    <div class="alert alert-warning mt-3">
                        <strong>⚠️ If navigation is still inconsistent:</strong><br>
                        Some pages might be using cached versions or there might be multiple header systems. 
                        Please note which specific pages show the old navigation.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-refresh page info
document.addEventListener('DOMContentLoaded', function() {
    console.log('Navigation Diagnostic Page Loaded');
    console.log('Current URL:', window.location.href);
    console.log('Referrer:', document.referrer);
    
    // Check if QR Manager link exists in navigation
    const qrManagerLink = document.querySelector('a[href*="qr_manager.php"]');
    if (qrManagerLink) {
        console.log('✅ QR Manager link found in navigation');
    } else {
        console.log('❌ QR Manager link NOT found in navigation');
    }
    
    // Check for old "My QR Codes" link
    const oldQRLink = document.querySelector('a[href*="qr-codes.php"]');
    if (oldQRLink) {
        console.log('⚠️ Old "qr-codes.php" link still found');
    } else {
        console.log('✅ Old QR codes link properly removed');
    }
});
</script>

<?php require_once __DIR__ . '/core/includes/footer.php'; ?> 