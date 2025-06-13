<?php
/**
 * Navigation Debug Page
 * Tests if the main navbar is loading correctly with Nayax links
 */

require_once __DIR__ . '/html/core/config.php';
require_once __DIR__ . '/html/core/session.php';
require_once __DIR__ . '/html/core/auth.php';

// Start output buffering to capture navbar content
ob_start();
include __DIR__ . '/html/core/includes/navbar.php';
$navbar_content = ob_get_clean();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Navigation Debug - RevenueQR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-dark text-light">
    <div class="container mt-5">
        <h1>🔧 Navigation Debug</h1>
        
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card bg-secondary">
                    <div class="card-header">
                        <h5>🔍 Debug Information</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>User Role:</strong> 
                            <?php 
                            if (is_logged_in()) {
                                if (has_role('admin')) echo 'Admin';
                                elseif (has_role('business')) echo 'Business';
                                else echo 'User';
                            } else {
                                echo 'Not logged in';
                            }
                            ?>
                        </p>
                        <p><strong>APP_URL:</strong> <?php echo APP_URL; ?></p>
                        <p><strong>Navbar File:</strong> 
                            <?php echo file_exists(__DIR__ . '/html/core/includes/navbar.php') ? '✅ Exists' : '❌ Missing'; ?>
                        </p>
                        <p><strong>Nayax in Navbar:</strong>
                            <?php 
                            $navbar_file = file_get_contents(__DIR__ . '/html/core/includes/navbar.php');
                            echo strpos($navbar_file, 'Nayax') !== false ? '✅ Found' : '❌ Not found'; 
                            ?>
                        </p>
                    </div>
                </div>
                
                <div class="card bg-secondary mt-3">
                    <div class="card-header">
                        <h5>🔗 Expected Nayax URLs</h5>
                    </div>
                    <div class="card-body small">
                        <div class="mb-2">
                            <strong>Business Links:</strong><br>
                            • <a href="<?php echo APP_URL; ?>/business/nayax-analytics.php" target="_blank" class="text-info">Advanced Analytics</a><br>
                            • <a href="<?php echo APP_URL; ?>/business/nayax-customers.php" target="_blank" class="text-info">Customer Intelligence</a><br>
                            • <a href="<?php echo APP_URL; ?>/business/mobile-dashboard.php" target="_blank" class="text-info">Mobile Dashboard</a><br>
                        </div>
                        <div>
                            <strong>Admin Links:</strong><br>
                            • <a href="<?php echo APP_URL; ?>/admin/nayax-overview.php" target="_blank" class="text-warning">System Overview</a><br>
                            • <a href="<?php echo APP_URL; ?>/admin/nayax-machines.php" target="_blank" class="text-warning">Machine Management</a><br>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card bg-secondary">
                    <div class="card-header">
                        <h5>📋 Navbar Preview</h5>
                    </div>
                    <div class="card-body">
                        <?php echo $navbar_content; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="alert alert-info mt-4">
            <h6>📝 Instructions:</h6>
            <ol>
                <li>Check if you can see the Nayax dropdown in the navigation above</li>
                <li>Try clicking the test links to verify they work</li>
                <li>If navigation doesn't appear, try refreshing your browser (Ctrl+F5)</li>
                <li>Check if you're logged in with the correct role (Business/Admin)</li>
            </ol>
        </div>
        
        <div class="mt-4">
            <a href="<?php echo APP_URL; ?>/business/dashboard.php" class="btn btn-primary">
                <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
            </a>
            <button onclick="location.reload()" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
            </button>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 