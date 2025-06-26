<?php
/**
 * Browser-Based Session & Discount Purchase Diagnostic
 * Access via: /html/debug-session-discount.php
 */
session_start();
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/qr_coin_manager.php';

$user_logged_in = isset($_SESSION['user_id']);
$user_id = $_SESSION['user_id'] ?? null;
$diagnostics = [];
$fixes_available = [];

// Get user info if logged in
if ($user_logged_in) {
    try {
        $stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_info = $stmt->fetch();
        
        $user_balance = QRCoinManager::getBalance($user_id);
    } catch (Exception $e) {
        $user_balance = 0;
        $user_info = null;
    }
}

// Check for test purchase
if ($_POST['action'] ?? '' === 'test_purchase') {
    header('Content-Type: application/json');
    
    if (!$user_logged_in) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }
    
    try {
        // Simulate a discount purchase
        $test_cost = 50;
        $current_balance = QRCoinManager::getBalance($user_id);
        
        if ($current_balance < $test_cost) {
            echo json_encode(['success' => false, 'error' => 'Insufficient balance']);
            exit;
        }
        
        // Test the purchase flow without actually spending coins
        echo json_encode([
            'success' => true, 
            'message' => 'Test purchase would succeed',
            'current_balance' => $current_balance,
            'test_cost' => $test_cost,
            'remaining_balance' => $current_balance - $test_cost
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session & Discount Purchase Diagnostic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .diagnostic-card { background: #f8f9fa; border-left: 5px solid #007bff; margin-bottom: 1rem; }
        .status-good { border-left-color: #28a745 !important; }
        .status-warning { border-left-color: #ffc107 !important; }
        .status-error { border-left-color: #dc3545 !important; }
        .test-button { margin: 0.5rem; }
        .session-info { font-family: monospace; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1><i class="bi bi-bug"></i> Session & Discount Purchase Diagnostic</h1>
        <p class="text-muted">Diagnose login sessions and discount purchase issues</p>
        
        <!-- Authentication Status -->
        <div class="diagnostic-card card p-3 <?= $user_logged_in ? 'status-good' : 'status-error' ?>">
            <h4><i class="bi bi-person-check"></i> Authentication Status</h4>
            <?php if ($user_logged_in): ?>
                <div class="alert alert-success">
                    <strong>✅ Logged In Successfully</strong><br>
                    <strong>User ID:</strong> <?= $user_id ?><br>
                    <strong>Username:</strong> <?= htmlspecialchars($user_info['username'] ?? 'Unknown') ?><br>
                    <strong>Email:</strong> <?= htmlspecialchars($user_info['email'] ?? 'Unknown') ?><br>
                    <strong>Session ID:</strong> <code><?= session_id() ?></code><br>
                    <strong>Session Lifetime:</strong> <?= SESSION_LIFETIME ?> seconds (<?= round(SESSION_LIFETIME/3600, 1) ?> hours)
                </div>
                
                <!-- Session Details -->
                <div class="session-info mt-3">
                    <h6>Session Data:</h6>
                    <pre><?= htmlspecialchars(json_encode($_SESSION, JSON_PRETTY_PRINT)) ?></pre>
                </div>
                
            <?php else: ?>
                <div class="alert alert-danger">
                    <strong>❌ Not Logged In</strong><br>
                    You need to log in to purchase discounts.<br>
                    <a href="/html/user/login.php" class="btn btn-primary mt-2">
                        <i class="bi bi-box-arrow-in-right"></i> Login Now
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ($user_logged_in): ?>
        <!-- Balance Status -->
        <div class="diagnostic-card card p-3 <?= ($user_balance >= 50) ? 'status-good' : 'status-warning' ?>">
            <h4><i class="bi bi-coin"></i> QR Coin Balance</h4>
            <div class="alert alert-<?= ($user_balance >= 50) ? 'success' : 'warning' ?>">
                <strong>Current Balance:</strong> <?= number_format($user_balance) ?> QR coins<br>
                <?php if ($user_balance >= 1650): ?>
                    <strong>✅ Excellent!</strong> You have plenty of coins for any discount.
                <?php elseif ($user_balance >= 100): ?>
                    <strong>✅ Good!</strong> You can afford most discounts.
                <?php elseif ($user_balance >= 50): ?>
                    <strong>⚠️ Low Balance:</strong> You can afford basic discounts only.
                <?php else: ?>
                    <strong>❌ Insufficient:</strong> You need more coins to purchase discounts.
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Frontend Purchase Test -->
        <div class="diagnostic-card card p-3">
            <h4><i class="bi bi-cart-check"></i> Frontend Purchase Test</h4>
            <p>Test if discount purchases work from your browser:</p>
            
            <button id="testPurchaseBtn" class="btn btn-primary test-button">
                <i class="bi bi-cart-plus"></i> Test Discount Purchase (50 coins)
            </button>
            
            <div id="testResults" class="mt-3" style="display: none;"></div>
        </div>
        
        <!-- Session Timeout Check -->
        <div class="diagnostic-card card p-3">
            <h4><i class="bi bi-clock"></i> Session Timeout Check</h4>
            <?php 
            $last_activity = $_SESSION['last_activity'] ?? 0;
            $session_age = time() - $last_activity;
            $time_remaining = SESSION_LIFETIME - $session_age;
            ?>
            
            <div class="alert alert-info">
                <strong>Last Activity:</strong> <?= $last_activity > 0 ? date('Y-m-d H:i:s', $last_activity) : 'Unknown' ?><br>
                <strong>Session Age:</strong> <?= round($session_age / 60) ?> minutes<br>
                <strong>Time Remaining:</strong> <?= round($time_remaining / 60) ?> minutes<br>
                
                <?php if ($time_remaining < 300): ?>
                    <div class="text-warning mt-2">
                        <strong>⚠️ Session Expiring Soon!</strong> Your session will expire in less than 5 minutes.
                    </div>
                <?php endif; ?>
            </div>
            
            <button id="refreshSessionBtn" class="btn btn-secondary test-button">
                <i class="bi bi-arrow-clockwise"></i> Refresh Session
            </button>
        </div>
        
        <!-- Browser & JavaScript Check -->
        <div class="diagnostic-card card p-3">
            <h4><i class="bi bi-browser-chrome"></i> Browser & JavaScript Status</h4>
            <div id="browserCheck">
                <div class="alert alert-info">
                    <strong>JavaScript:</strong> <span id="jsStatus">Checking...</span><br>
                    <strong>Cookies:</strong> <span id="cookieStatus">Checking...</span><br>
                    <strong>Local Storage:</strong> <span id="storageStatus">Checking...</span><br>
                    <strong>User Agent:</strong> <code id="userAgent"></code>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Quick Fixes -->
        <div class="diagnostic-card card p-3 status-warning">
            <h4><i class="bi bi-tools"></i> Quick Fixes</h4>
            <div class="row">
                <div class="col-md-6">
                    <h6>If Purchase Buttons Don't Work:</h6>
                    <ul>
                        <li>Clear browser cache and cookies</li>
                        <li>Disable browser extensions temporarily</li>
                        <li>Try incognito/private browsing mode</li>
                        <li>Check browser console for JavaScript errors</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h6>If Session Keeps Expiring:</h6>
                    <ul>
                        <li>Check if cookies are enabled</li>
                        <li>Ensure stable internet connection</li>
                        <li>Don't leave tabs idle for too long</li>
                        <li>Try refreshing the page before purchasing</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Browser capability checks
        document.getElementById('jsStatus').textContent = 'Working ✅';
        document.getElementById('cookieStatus').textContent = navigator.cookieEnabled ? 'Enabled ✅' : 'Disabled ❌';
        document.getElementById('storageStatus').textContent = typeof(Storage) !== "undefined" ? 'Available ✅' : 'Not Available ❌';
        document.getElementById('userAgent').textContent = navigator.userAgent;
        
        // Test purchase functionality
        document.getElementById('testPurchaseBtn')?.addEventListener('click', async function() {
            this.disabled = true;
            this.innerHTML = '<i class="bi bi-hourglass-split"></i> Testing...';
            
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=test_purchase'
                });
                
                const result = await response.json();
                const resultsDiv = document.getElementById('testResults');
                
                if (result.success) {
                    resultsDiv.innerHTML = `
                        <div class="alert alert-success">
                            <strong>✅ Test Purchase Successful!</strong><br>
                            ${result.message}<br>
                            Balance: ${result.current_balance} → ${result.remaining_balance} coins
                        </div>
                    `;
                } else {
                    resultsDiv.innerHTML = `
                        <div class="alert alert-danger">
                            <strong>❌ Test Purchase Failed:</strong><br>
                            ${result.error}
                        </div>
                    `;
                }
                
                resultsDiv.style.display = 'block';
                
            } catch (error) {
                document.getElementById('testResults').innerHTML = `
                    <div class="alert alert-danger">
                        <strong>❌ Network Error:</strong><br>
                        ${error.message}
                    </div>
                `;
                document.getElementById('testResults').style.display = 'block';
            }
            
            this.disabled = false;
            this.innerHTML = '<i class="bi bi-cart-plus"></i> Test Discount Purchase (50 coins)';
        });
        
        // Refresh session
        document.getElementById('refreshSessionBtn')?.addEventListener('click', function() {
            window.location.reload();
        });
        
        // Auto-refresh session activity every 5 minutes
        setInterval(() => {
            fetch(window.location.href + '?ping=1').catch(() => {});
        }, 300000);
    </script>
</body>
</html> 