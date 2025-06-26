<?php
/**
 * Diagnostic script to check discount purchase status
 * This will help identify why purchases might appear as "inactive"
 */

require_once 'html/core/config.php';
require_once 'core/qr_coin_manager.php';
require_once 'html/core/qr_code_manager.php';

// Set content type
header('Content-Type: text/html; charset=UTF-8');

echo "<h1>🔍 Discount Purchase Diagnostic</h1>";
echo "<p>Checking the status of recent discount purchases...</p>";

try {
    // Check recent business purchases
    echo "<h2>📊 Recent Business Purchases</h2>";
    $stmt = $pdo->prepare("
        SELECT 
            bp.id,
            bp.user_id,
            bp.status as db_status,
            bp.expires_at,
            bp.created_at,
            bp.qr_code_data IS NOT NULL as has_qr_code,
            bp.scan_count,
            bp.redeemed_at,
            bsi.item_name,
            b.name as business_name,
            u.username,
            CASE 
                WHEN bp.expires_at <= NOW() AND bp.status != 'redeemed' THEN 'EXPIRED'
                WHEN bp.status = 'redeemed' THEN 'REDEEMED'
                WHEN bp.status = 'pending' AND bp.expires_at > NOW() THEN 'ACTIVE'
                ELSE 'UNKNOWN'
            END as computed_status,
            TIMESTAMPDIFF(DAY, NOW(), bp.expires_at) as days_until_expiry
        FROM business_purchases bp
        LEFT JOIN business_store_items bsi ON bp.business_store_item_id = bsi.id
        LEFT JOIN businesses b ON bp.business_id = b.id
        LEFT JOIN users u ON bp.user_id = u.id
        ORDER BY bp.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($purchases)) {
        echo "<p>❌ No business purchases found.</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%; font-family: Arial, sans-serif;'>";
        echo "<tr style='background: #f0f0f0;'>";
        echo "<th style='padding: 8px;'>ID</th>";
        echo "<th style='padding: 8px;'>User</th>";
        echo "<th style='padding: 8px;'>Item</th>";
        echo "<th style='padding: 8px;'>Business</th>";
        echo "<th style='padding: 8px;'>DB Status</th>";
        echo "<th style='padding: 8px;'>Computed Status</th>";
        echo "<th style='padding: 8px;'>Has QR Code</th>";
        echo "<th style='padding: 8px;'>Days Until Expiry</th>";
        echo "<th style='padding: 8px;'>Scan Count</th>";
        echo "<th style='padding: 8px;'>Created</th>";
        echo "</tr>";
        
        foreach ($purchases as $purchase) {
            $status_color = match($purchase['computed_status']) {
                'ACTIVE' => '#28a745',
                'REDEEMED' => '#6c757d', 
                'EXPIRED' => '#dc3545',
                default => '#ffc107'
            };
            
            echo "<tr>";
            echo "<td style='padding: 8px;'>{$purchase['id']}</td>";
            echo "<td style='padding: 8px;'>{$purchase['username']}</td>";
            echo "<td style='padding: 8px;'>{$purchase['item_name']}</td>";
            echo "<td style='padding: 8px;'>{$purchase['business_name']}</td>";
            echo "<td style='padding: 8px;'>{$purchase['db_status']}</td>";
            echo "<td style='padding: 8px; background: {$status_color}; color: white; font-weight: bold;'>{$purchase['computed_status']}</td>";
            echo "<td style='padding: 8px;'>" . ($purchase['has_qr_code'] ? '✅ Yes' : '❌ No') . "</td>";
            echo "<td style='padding: 8px;'>{$purchase['days_until_expiry']} days</td>";
            echo "<td style='padding: 8px;'>{$purchase['scan_count']}</td>";
            echo "<td style='padding: 8px;'>" . date('M j, Y H:i', strtotime($purchase['created_at'])) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<hr>";
    
    // Check QR coin balances for users with purchases
    echo "<h2>💰 User QR Coin Balances</h2>";
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.username,
            SUM(qct.amount) as balance,
            COUNT(CASE WHEN qct.transaction_type = 'business_discount_purchase' THEN 1 END) as discount_purchases
        FROM users u
        LEFT JOIN qr_coin_transactions qct ON u.id = qct.user_id
        WHERE u.id IN (SELECT DISTINCT user_id FROM business_purchases)
        GROUP BY u.id, u.username
        ORDER BY u.id
    ");
    $stmt->execute();
    $balances = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($balances)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%; font-family: Arial, sans-serif;'>";
        echo "<tr style='background: #f0f0f0;'>";
        echo "<th style='padding: 8px;'>User ID</th>";
        echo "<th style='padding: 8px;'>Username</th>";
        echo "<th style='padding: 8px;'>QR Coin Balance</th>";
        echo "<th style='padding: 8px;'>Discount Purchases</th>";
        echo "</tr>";
        
        foreach ($balances as $balance) {
            echo "<tr>";
            echo "<td style='padding: 8px;'>{$balance['id']}</td>";
            echo "<td style='padding: 8px;'>{$balance['username']}</td>";
            echo "<td style='padding: 8px;'>{$balance['balance']}</td>";
            echo "<td style='padding: 8px;'>{$balance['discount_purchases']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<hr>";
    
    // Test QR code validation for active purchases
    echo "<h2>🔍 QR Code Validation Test</h2>";
    $stmt = $pdo->prepare("
        SELECT id, qr_code_content, purchase_code
        FROM business_purchases 
        WHERE status = 'pending' AND expires_at > NOW() AND qr_code_content IS NOT NULL
        LIMIT 3
    ");
    $stmt->execute();
    $test_purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($test_purchases)) {
        foreach ($test_purchases as $test_purchase) {
            echo "<h4>Testing Purchase ID: {$test_purchase['id']}</h4>";
            echo "<p><strong>Purchase Code:</strong> {$test_purchase['purchase_code']}</p>";
            
            if ($test_purchase['qr_code_content']) {
                $validation_result = QRCodeManager::validateQRCode($test_purchase['qr_code_content']);
                
                if ($validation_result['success']) {
                    echo "<p>✅ <strong>QR Code Validation: PASSED</strong></p>";
                    echo "<p>Business: {$validation_result['purchase']['business_name']}</p>";
                    echo "<p>Discount: {$validation_result['discount_percentage']}%</p>";
                } else {
                    echo "<p>❌ <strong>QR Code Validation: FAILED</strong></p>";
                    echo "<p>Error: {$validation_result['message']}</p>";
                }
            } else {
                echo "<p>⚠️ No QR code content found</p>";
            }
            echo "<hr>";
        }
    } else {
        echo "<p>No active purchases with QR codes found for testing.</p>";
    }
    
    // Summary and recommendations
    echo "<h2>📋 Summary & Recommendations</h2>";
    
    $active_count = 0;
    $inactive_count = 0;
    $no_qr_count = 0;
    
    foreach ($purchases as $purchase) {
        if ($purchase['computed_status'] === 'ACTIVE') {
            $active_count++;
        } else {
            $inactive_count++;
        }
        
        if (!$purchase['has_qr_code']) {
            $no_qr_count++;
        }
    }
    
    echo "<ul>";
    echo "<li><strong>Active Purchases:</strong> {$active_count}</li>";
    echo "<li><strong>Inactive/Expired Purchases:</strong> {$inactive_count}</li>";
    echo "<li><strong>Purchases Missing QR Codes:</strong> {$no_qr_count}</li>";
    echo "</ul>";
    
    if ($no_qr_count > 0) {
        echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px;'>";
        echo "<h4>⚠️ Issue Found: Missing QR Codes</h4>";
        echo "<p>Some purchases don't have QR codes generated. This could cause them to appear as 'inactive' in the user interface.</p>";
        echo "</div>";
    }
    
    if ($active_count === 0 && count($purchases) > 0) {
        echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px;'>";
        echo "<h4>❌ Issue Found: No Active Purchases</h4>";
        echo "<p>All recent purchases appear to be expired or redeemed. Users may see 'inactive transactions' because their purchases have expired.</p>";
        echo "</div>";
    }
    
    if ($active_count > 0) {
        echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px;'>";
        echo "<h4>✅ Good News: Active Purchases Found</h4>";
        echo "<p>There are {$active_count} active purchases. If users are seeing 'inactive transactions', the issue might be in the user interface display logic.</p>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<ol>";
echo "<li>If purchases show as ACTIVE here but appear inactive to users, check the UI display logic</li>";
echo "<li>If QR codes are missing, check the QR code generation process</li>";
echo "<li>If all purchases are expired, users need to make new purchases</li>";
echo "<li>Test the actual QR code scanning process at a vending machine</li>";
echo "</ol>";

echo "<p><a href='html/user/my-discount-qr-codes.php'>👀 View User's Discount Codes Page</a> | ";
echo "<a href='html/nayax/discount-store.php'>🛒 Visit Discount Store</a></p>";
?> 