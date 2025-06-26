<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/auth.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: /html/user/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Frontend Discount Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h1>Frontend Discount Purchase Test</h1>
        
        <div class="alert alert-info">
            <strong>User ID:</strong> <?= $user_id ?><br>
            <strong>Session ID:</strong> <?= session_id() ?>
        </div>
        
        <!-- Test Item -->
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Test Discount Item</h5>
                <p class="card-text">Testing frontend purchase flow</p>
                <p><strong>Price:</strong> 10 QR Coins</p>
                <button id="testPurchaseBtn" class="btn btn-success" onclick="testPurchase()">
                    <i class="bi bi-cart-plus"></i> Test Purchase
                </button>
            </div>
        </div>
        
        <!-- Results -->
        <div id="results" class="mt-4" style="display: none;">
            <div class="card">
                <div class="card-header">
                    <h5>Test Results</h5>
                </div>
                <div class="card-body">
                    <div id="resultContent"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        async function testPurchase() {
            const button = document.getElementById('testPurchaseBtn');
            const results = document.getElementById('results');
            const resultContent = document.getElementById('resultContent');
            
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Testing...';
            
            let output = '';
            
            try {
                // Test 1: Check current balance
                output += '<h6>Test 1: Check User Balance</h6>';
                try {
                    const balanceResponse = await fetch('/html/api/user-balance.php');
                    const balanceResult = await balanceResponse.json();
                    output += `<p>✅ Balance API: ${balanceResponse.status} - ${JSON.stringify(balanceResult)}</p>`;
                } catch (error) {
                    output += `<p>❌ Balance API Error: ${error.message}</p>`;
                }
                
                // Test 2: Get available discount items
                output += '<h6>Test 2: Get Available Items</h6>';
                try {
                    const itemsResponse = await fetch('/html/api/get-discount-items.php');
                    if (itemsResponse.ok) {
                        const itemsResult = await itemsResponse.json();
                        output += `<p>✅ Items API: ${itemsResponse.status} - Found ${itemsResult.items ? itemsResult.items.length : 0} items</p>`;
                        
                        // Test 3: Try to purchase first available item
                        if (itemsResult.items && itemsResult.items.length > 0) {
                            const testItem = itemsResult.items[0];
                            output += '<h6>Test 3: Purchase Test Item</h6>';
                            output += `<p>Testing with item: ${testItem.item_name} (ID: ${testItem.id}, Cost: ${testItem.qr_coin_cost})</p>`;
                            
                            const purchaseResponse = await fetch('/html/api/purchase-discount.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest'
                                },
                                body: JSON.stringify({
                                    item_id: testItem.id,
                                    machine_id: 'test',
                                    source: 'frontend_test'
                                })
                            });
                            
                            const purchaseResult = await purchaseResponse.json();
                            output += `<p><strong>Purchase Response:</strong> ${purchaseResponse.status}</p>`;
                            output += `<pre style="background: #f5f5f5; padding: 10px; font-size: 12px;">${JSON.stringify(purchaseResult, null, 2)}</pre>`;
                            
                            if (purchaseResult.success) {
                                output += `<div class="alert alert-success">✅ Purchase successful! Code: ${purchaseResult.discount_code}</div>`;
                            } else {
                                output += `<div class="alert alert-danger">❌ Purchase failed: ${purchaseResult.error}</div>`;
                            }
                        } else {
                            output += '<p>❌ No items available for testing</p>';
                        }
                    } else {
                        output += `<p>❌ Items API Error: ${itemsResponse.status}</p>`;
                    }
                } catch (error) {
                    output += `<p>❌ Items API Error: ${error.message}</p>`;
                }
                
                // Test 4: Check session and auth
                output += '<h6>Test 4: Session Check</h6>';
                output += `<p>Session active: ${document.cookie.includes('PHPSESSID') ? 'Yes' : 'No'}</p>`;
                output += `<p>Current URL: ${window.location.href}</p>`;
                
            } catch (error) {
                output += `<div class="alert alert-danger">❌ Test Error: ${error.message}</div>`;
            }
            
            resultContent.innerHTML = output;
            results.style.display = 'block';
            
            button.disabled = false;
            button.innerHTML = '<i class="bi bi-cart-plus"></i> Test Purchase';
        }
    </script>
</body>
</html> 