<?php
session_start();
require_once __DIR__ . '/core/config.php';

// Simple debug page for discount store issues
$user_logged_in = isset($_SESSION['user_id']);
$user_id = $_SESSION['user_id'] ?? null;
$user_balance = 0;

// Get user balance if logged in
if ($user_logged_in) {
    try {
        $user_balance = QRCoinManager::getBalance($user_id);
    } catch (Exception $e) {
        $user_balance = 0;
    }
}

// Test discount items
$discount_items = [
    [
        'id' => 1,
        'item_name' => 'Test Discount 1',
        'item_description' => 'Test 10% off any item',
        'discount_percent' => 10,
        'qr_coin_price' => 50,
        'business_name' => 'Test Business'
    ],
    [
        'id' => 2,
        'item_name' => 'Test Discount 2',
        'item_description' => 'Test 20% off any item',
        'discount_percent' => 20,
        'qr_coin_price' => 100,
        'business_name' => 'Test Business'
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>Debug Discount Store | RevenueQR</title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .debug-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .debug-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
        }
        
        .status-good { color: #28a745; }
        .status-warning { color: #ffc107; }
        .status-error { color: #dc3545; }
        
        .test-button {
            background: linear-gradient(45deg, #4a90e2, #f39c12);
            border: none;
            color: white;
            padding: 12px 24px;
            border-radius: 25px;
            font-weight: bold;
            cursor: pointer;
            margin: 5px;
            min-width: 120px;
        }
        
        .test-button:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .test-button:disabled {
            background: #6c757d !important;
            cursor: not-allowed !important;
            opacity: 0.6;
        }
        
        .debug-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
            font-family: monospace;
            font-size: 0.9em;
        }
        
        .mobile-test {
            border: 2px solid #007bff;
            border-radius: 10px;
            padding: 15px;
            margin: 10px 0;
        }
        
        @media (max-width: 768px) {
            .debug-container {
                padding: 10px;
            }
            
            .mobile-test {
                background: #e3f2fd;
                border-color: #2196f3;
            }
        }
    </style>
</head>
<body>
    <div class="debug-container">
        <div class="debug-card">
            <h2><i class="bi bi-bug"></i> Discount Store Debug Tool</h2>
            <p class="text-muted">This page helps diagnose issues with discount purchasing and mobile responsiveness.</p>
        </div>
        
        <!-- Authentication Status -->
        <div class="debug-card">
            <h4><i class="bi bi-person-check"></i> Authentication Status</h4>
            <div class="debug-info">
                <strong>User Logged In:</strong> 
                <span class="<?= $user_logged_in ? 'status-good' : 'status-error' ?>">
                    <?= $user_logged_in ? 'YES' : 'NO' ?>
                </span><br>
                
                <?php if ($user_logged_in): ?>
                <strong>User ID:</strong> <?= htmlspecialchars($user_id) ?><br>
                <strong>QR Coin Balance:</strong> 
                <span class="<?= $user_balance > 0 ? 'status-good' : 'status-warning' ?>">
                    <?= number_format($user_balance) ?> coins
                </span><br>
                <?php endif; ?>
            </div>
            
            <?php if (!$user_logged_in): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i> 
                You need to log in to test discount purchasing. 
                <a href="/html/auth/login.php" class="btn btn-primary btn-sm ms-2">Login</a>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Mobile Responsiveness Test -->
        <div class="debug-card">
            <h4><i class="bi bi-phone"></i> Mobile Responsiveness Test</h4>
            <div class="mobile-test">
                <p><strong>This box should have a blue background on mobile devices.</strong></p>
                <p>If you're on a phone and this box is still white, the mobile CSS isn't working properly.</p>
            </div>
            
            <div class="debug-info" id="deviceInfo">
                <strong>Loading device information...</strong>
            </div>
        </div>
        
        <!-- Button Interaction Test -->
        <div class="debug-card">
            <h4><i class="bi bi-hand-index"></i> Button Interaction Test</h4>
            <p>Test if buttons are clickable and responsive:</p>
            
            <button class="test-button" onclick="testButtonClick(this, 'enabled')">
                <i class="bi bi-check-circle"></i> Enabled Button
            </button>
            
            <button class="test-button" disabled onclick="testButtonClick(this, 'disabled')">
                <i class="bi bi-x-circle"></i> Disabled Button
            </button>
            
            <button class="test-button" onclick="testPurchaseAPI()">
                <i class="bi bi-cart"></i> Test Purchase API
            </button>
            
            <div id="buttonTestResults" class="debug-info mt-3" style="display: none;">
                <strong>Button Test Results:</strong><br>
            </div>
        </div>
        
        <!-- Sample Discount Items -->
        <?php if ($user_logged_in): ?>
        <div class="debug-card">
            <h4><i class="bi bi-tags"></i> Sample Discount Items</h4>
            <div class="row">
                <?php foreach ($discount_items as $item): ?>
                <div class="col-md-6 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="badge bg-success mb-2"><?= $item['discount_percent'] ?>% OFF</div>
                            <h5 class="card-title"><?= htmlspecialchars($item['item_name']) ?></h5>
                            <p class="card-text text-muted"><?= htmlspecialchars($item['item_description']) ?></p>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="bi bi-coin"></i> <?= number_format($item['qr_coin_price']) ?> coins
                                </div>
                                <button class="test-button purchase-btn" 
                                        onclick="testPurchaseDiscount(<?= $item['id'] ?>, '<?= htmlspecialchars($item['item_name']) ?>', <?= $item['qr_coin_price'] ?>)"
                                        <?= ($user_balance < $item['qr_coin_price']) ? 'disabled' : '' ?>>
                                    <?php if ($user_balance < $item['qr_coin_price']): ?>
                                        <i class="bi bi-lock"></i> Need More Coins
                                    <?php else: ?>
                                        <i class="bi bi-cart-plus"></i> Test Purchase
                                    <?php endif; ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- API Test Results -->
        <div class="debug-card">
            <h4><i class="bi bi-server"></i> API Test Results</h4>
            <div id="apiTestResults" class="debug-info">
                <em>No API tests run yet. Click "Test Purchase API" above to test.</em>
            </div>
        </div>
    </div>

    <script>
        // Device information display
        document.addEventListener('DOMContentLoaded', function() {
            const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
            const viewportWidth = window.innerWidth;
            const screenWidth = window.screen.width;
            const isTouchDevice = 'ontouchstart' in window;
            
            document.getElementById('deviceInfo').innerHTML = `
                <strong>Device Information:</strong><br>
                <strong>User Agent:</strong> ${navigator.userAgent.substring(0, 100)}...<br>
                <strong>Is Mobile Device:</strong> ${isMobile ? 'YES' : 'NO'}<br>
                <strong>Viewport Width:</strong> ${viewportWidth}px<br>
                <strong>Screen Width:</strong> ${screenWidth}px<br>
                <strong>Touch Support:</strong> ${isTouchDevice ? 'YES' : 'NO'}<br>
                <strong>Orientation:</strong> ${window.screen.orientation ? window.screen.orientation.type : 'Unknown'}<br>
                <strong>Pixel Ratio:</strong> ${window.devicePixelRatio || 1}
            `;
        });
        
        // Test button click
        function testButtonClick(button, type) {
            const results = document.getElementById('buttonTestResults');
            results.style.display = 'block';
            results.innerHTML += `<span class="status-good">✓ ${type} button clicked successfully</span><br>`;
            
            if (type === 'enabled') {
                button.innerHTML = '<i class="bi bi-check"></i> Clicked!';
                setTimeout(() => {
                    button.innerHTML = '<i class="bi bi-check-circle"></i> Enabled Button';
                }, 1000);
            }
        }
        
        // Test purchase API
        async function testPurchaseAPI() {
            const results = document.getElementById('apiTestResults');
            results.innerHTML = '<em>Testing API endpoints...</em>';
            
            try {
                // Test the new diagnostic endpoint first
                const diagResponse = await fetch('/html/api/test-discount-purchase.php');
                const diagResult = await diagResponse.json();
                
                let output = `<strong>Diagnostic API Test:</strong><br>`;
                output += `Status: ${diagResponse.status}<br>`;
                output += `Response: ${JSON.stringify(diagResult, null, 2)}<br><br>`;
                
                // Test balance endpoint
                const balanceResponse = await fetch('/html/api/user-balance.php');
                const balanceResult = await balanceResponse.json();
                
                output += `<strong>Balance API Test:</strong><br>`;
                output += `Status: ${balanceResponse.status}<br>`;
                output += `Response: ${JSON.stringify(balanceResult, null, 2)}<br><br>`;
                
                // Test purchase endpoint with actual data from diagnostic
                if (diagResult.debug && diagResult.debug.database && diagResult.debug.database.available_items.length > 0) {
                    const testItem = diagResult.debug.database.available_items[0];
                    
                    const purchaseResponse = await fetch('/html/api/test-discount-purchase.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            item_id: testItem.id,
                            machine_id: null,
                            source: 'debug_test'
                        })
                    });
                    
                    const purchaseResult = await purchaseResponse.json();
                    
                    output += `<strong>Test Purchase API:</strong><br>`;
                    output += `Status: ${purchaseResponse.status}<br>`;
                    output += `Response: ${JSON.stringify(purchaseResult, null, 2)}<br>`;
                } else {
                    output += `<strong>Test Purchase API:</strong><br>`;
                    output += `<span class="status-warning">No test items available in database</span><br>`;
                }
                
                results.innerHTML = `<pre>${output}</pre>`;
                
            } catch (error) {
                results.innerHTML = `<span class="status-error">API Test Error: ${error.message}</span>`;
            }
        }
        
        // Test purchase discount (same as real function but with debug info)
        async function testPurchaseDiscount(itemId, itemName, price) {
            const button = event.target.closest('.purchase-btn');
            if (!button || button.disabled) return;
            
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="bi bi-hourglass-split"></i> Testing...';
            button.disabled = true;
            
            try {
                const response = await fetch('/html/api/purchase-discount.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        item_id: itemId,
                        machine_id: null,
                        source: 'debug_test'
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    button.innerHTML = '<i class="bi bi-check"></i> Success!';
                    alert('🎉 Test purchase successful! This is just a test - no real purchase was made.');
                } else {
                    throw new Error(result.error || 'Test purchase failed');
                }
                
            } catch (error) {
                alert('❌ Test purchase failed: ' + error.message);
                button.innerHTML = originalText;
                button.disabled = false;
            }
        }
        
        // Enhanced touch feedback
        document.addEventListener('touchstart', function(e) {
            if (e.target.classList.contains('test-button') && !e.target.disabled) {
                e.target.style.transform = 'scale(0.95)';
            }
        });
        
        document.addEventListener('touchend', function(e) {
            if (e.target.classList.contains('test-button')) {
                e.target.style.transform = '';
            }
        });
    </script>
</body>
</html> 