<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/functions.php';

// Debug current session
echo "<h3>Current Session Debug:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Simulate being logged in as a business user for testing
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'business';
    $_SESSION['business_id'] = 1;
    echo "<div class='alert alert-warning'>Simulated business user login for testing</div>";
}

require_once __DIR__ . '/core/includes/header.php';
?>

<div class="container py-4">
    <div class="alert alert-info">
        <h4>Navigation Test - Where to Find Promotions</h4>
        <p>If you're logged in as a business user, you should see promotions in these locations:</p>
        <ul>
            <li><strong>Main Navigation:</strong> "Business" dropdown → "Promotions"</li>
            <li><strong>Some pages:</strong> Direct "Promotions" link in top navigation</li>
            <li><strong>QR Generator:</strong> "QR & Display" dropdown → "QR Code Generator"</li>
        </ul>
        <hr>
        <p><strong>Current Session:</strong></p>
        <ul>
            <li>User ID: <?php echo $_SESSION['user_id'] ?? 'Not set'; ?></li>
            <li>User Role: <?php echo $_SESSION['role'] ?? 'Not set'; ?></li>
            <li>Business ID: <?php echo $_SESSION['business_id'] ?? 'Not set'; ?></li>
        </ul>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Direct Links to Test</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="business/promotions.php" class="btn btn-primary">
                            <i class="bi bi-star me-2"></i>Promotions Management
                        </a>
                        <a href="qr-generator.php" class="btn btn-secondary">
                            <i class="bi bi-qr-code me-2"></i>QR Code Generator
                        </a>
                        <a href="business/dashboard.php" class="btn btn-outline-primary">
                            <i class="bi bi-speedometer2 me-2"></i>Business Dashboard
                        </a>
                        <a href="redeem.php" class="btn btn-outline-success">
                            <i class="bi bi-gift me-2"></i>Promotion Redemption
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>System Status</h5>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            Promotions System
                            <span class="badge bg-success">✅ Active</span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            QR Generator
                            <span class="badge bg-success">✅ Active</span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            Campaign System
                            <span class="badge bg-success">✅ Active</span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            Weekly Automation
                            <span class="badge bg-success">✅ Active</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/core/includes/footer.php'; ?> 