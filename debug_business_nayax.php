<?php
/**
 * Debug Business Nayax Links
 * Tests all business Nayax URLs to identify why they're not working
 */

require_once __DIR__ . '/html/core/config.php';
require_once __DIR__ . '/html/core/session.php';
require_once __DIR__ . '/html/core/auth.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Nayax Debug - RevenueQR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-dark text-light">
    <div class="container mt-5">
        <h1>🔧 Business Nayax Debug</h1>
        
        <div class="alert alert-info">
            <h6>📋 Debug Information</h6>
            <p><strong>User Status:</strong> 
                <?php 
                if (is_logged_in()) {
                    echo '✅ Logged in as ';
                    if (has_role('admin')) echo 'Admin';
                    elseif (has_role('business')) echo 'Business';
                    else echo 'User';
                } else {
                    echo '❌ Not logged in';
                }
                ?>
            </p>
            <p><strong>APP_URL:</strong> <?php echo APP_URL; ?></p>
            <p><strong>Business ID:</strong> 
                <?php 
                if (is_logged_in() && has_role('business')) {
                    echo get_business_id() ?: 'Not found';
                } else {
                    echo 'N/A (not business user)';
                }
                ?>
            </p>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card bg-secondary">
                    <div class="card-header">
                        <h5>🔗 Business Nayax Pages Test</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $business_pages = [
                            'nayax-analytics.php' => 'Advanced Analytics',
                            'nayax-customers.php' => 'Customer Intelligence', 
                            'mobile-dashboard.php' => 'Mobile Dashboard',
                            'nayax-machines.php' => 'Machine Status',
                            'nayax-settings.php' => 'Nayax Settings'
                        ];
                        
                        foreach ($business_pages as $page => $title) {
                            $full_path = __DIR__ . '/html/business/' . $page;
                            $exists = file_exists($full_path);
                            $url = APP_URL . '/business/' . $page;
                            
                            echo '<div class="mb-3">';
                            echo '<div class="d-flex justify-content-between align-items-center">';
                            echo '<strong>' . $title . '</strong>';
                            echo $exists ? '<span class="badge bg-success">✅ Exists</span>' : '<span class="badge bg-danger">❌ Missing</span>';
                            echo '</div>';
                            echo '<div class="small text-muted">File: /business/' . $page . '</div>';
                            echo '<div class="mt-1">';
                            echo '<a href="' . $url . '" target="_blank" class="btn btn-sm btn-outline-primary me-2">Test Link</a>';
                            if ($exists) {
                                echo '<span class="badge bg-info">Readable</span>';
                            }
                            echo '</div>';
                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card bg-secondary">
                    <div class="card-header">
                        <h5>⚙️ Server Environment</h5>
                    </div>
                    <div class="card-body">
                        <div class="small">
                            <p><strong>Document Root:</strong> <?php echo $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown'; ?></p>
                            <p><strong>Script Name:</strong> <?php echo $_SERVER['SCRIPT_NAME'] ?? 'Unknown'; ?></p>
                            <p><strong>Server Name:</strong> <?php echo $_SERVER['SERVER_NAME'] ?? 'Unknown'; ?></p>
                            <p><strong>Request URI:</strong> <?php echo $_SERVER['REQUEST_URI'] ?? 'Unknown'; ?></p>
                            <p><strong>Current Dir:</strong> <?php echo __DIR__; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="card bg-secondary mt-3">
                    <div class="card-header">
                        <h5>🧪 URL Test</h5>
                    </div>
                    <div class="card-body">
                        <form id="urlTest">
                            <div class="input-group mb-2">
                                <input type="text" class="form-control" id="testUrl" value="<?php echo APP_URL; ?>/business/nayax-analytics.php">
                                <button class="btn btn-outline-primary" type="button" onclick="testUrl()">Test</button>
                            </div>
                        </form>
                        <div id="testResult"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mt-4">
            <div class="alert alert-warning">
                <h6>📝 Troubleshooting Steps:</h6>
                <ol>
                    <li>Click the "Test Link" buttons above to see which pages load</li>
                    <li>If you get redirected to login, ensure you're logged in as a business user</li>
                    <li>If you get 404 errors, the file path might be incorrect</li>
                    <li>If pages show errors, check the error logs</li>
                    <li>If nothing happens when clicking navigation, check browser console for JavaScript errors</li>
                </ol>
            </div>
        </div>
        
        <div class="mt-4">
            <a href="<?php echo APP_URL; ?>/business/dashboard.php" class="btn btn-primary">
                <i class="bi bi-arrow-left me-1"></i>Back to Business Dashboard
            </a>
            <a href="<?php echo APP_URL; ?>/admin/dashboard_modular.php" class="btn btn-warning">
                <i class="bi bi-shield-check me-1"></i>Test Admin (Working)
            </a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function testUrl() {
        const url = document.getElementById('testUrl').value;
        const result = document.getElementById('testResult');
        
        result.innerHTML = '<div class="text-info">Testing...</div>';
        
        fetch(url)
            .then(response => {
                if (response.ok) {
                    result.innerHTML = '<div class="text-success">✅ URL accessible (Status: ' + response.status + ')</div>';
                } else {
                    result.innerHTML = '<div class="text-danger">❌ Error (Status: ' + response.status + ')</div>';
                }
            })
            .catch(error => {
                result.innerHTML = '<div class="text-danger">❌ Failed to fetch: ' + error.message + '</div>';
            });
    }
    </script>
</body>
</html> 