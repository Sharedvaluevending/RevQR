<?php
require_once __DIR__ . '/core/config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "<h2>🧪 PURCHASE BUG TEST</h2>";

// Test direct purchase of Mr. Big (Item ID 9)
if (isset($_POST['test_purchase'])) {
    $item_id = (int)$_POST['item_id'];
    
    echo "<h3>🔍 Testing Purchase of Item ID: {$item_id}</h3>";
    
    // Simulate the exact same request that would be sent
    $request_data = json_encode(['item_id' => $item_id]);
    
    echo "<p><strong>Request Data:</strong> <code>{$request_data}</code></p>";
    
    // Make the request to debug-purchase.php
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://149.248.57.232/debug-purchase.php');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $request_data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-Requested-With: XMLHttpRequest',
        'Cookie: ' . $_SERVER['HTTP_COOKIE'] ?? ''
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "<p><strong>HTTP Response Code:</strong> {$http_code}</p>";
    echo "<p><strong>Response:</strong></p>";
    echo "<pre style='background: #f8f9fa; padding: 15px; border-radius: 5px;'>" . htmlspecialchars($response) . "</pre>";
    
    // Check what was actually recorded in the database
    try {
        $stmt = $pdo->prepare("
            SELECT bp.*, bsi.item_name 
            FROM business_purchases bp 
            JOIN business_store_items bsi ON bp.business_store_item_id = bsi.id 
            ORDER BY bp.created_at DESC 
            LIMIT 1
        ");
        $stmt->execute();
        $latest_purchase = $stmt->fetch();
        
        if ($latest_purchase) {
            echo "<h4>📋 Latest Purchase Record:</h4>";
            echo "<ul>";
            echo "<li><strong>Item ID Used:</strong> {$latest_purchase['business_store_item_id']}</li>";
            echo "<li><strong>Item Name:</strong> {$latest_purchase['item_name']}</li>";
            echo "<li><strong>QR Coins Spent:</strong> {$latest_purchase['qr_coins_spent']}</li>";
            echo "<li><strong>Purchase Code:</strong> {$latest_purchase['purchase_code']}</li>";
            echo "<li><strong>Created:</strong> {$latest_purchase['created_at']}</li>";
            echo "</ul>";
            
            if ($latest_purchase['business_store_item_id'] == $item_id) {
                echo "<p style='color: green; font-weight: bold;'>✅ CORRECT: Item ID matches what was requested!</p>";
            } else {
                echo "<p style='color: red; font-weight: bold;'>❌ BUG: Item ID {$latest_purchase['business_store_item_id']} does not match requested ID {$item_id}!</p>";
            }
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error checking database: " . $e->getMessage() . "</p>";
    }
}

// Show available items for testing
try {
    $stmt = $pdo->prepare("SELECT id, item_name, qr_coin_cost FROM business_store_items WHERE is_active = 1 ORDER BY qr_coin_cost DESC LIMIT 5");
    $stmt->execute();
    $items = $stmt->fetchAll();
    
    echo "<h3>🎯 Test Purchase Buttons</h3>";
    echo "<p>Click these buttons to test purchasing specific items:</p>";
    
    foreach ($items as $item) {
        $highlight = '';
        if ($item['id'] == 9) { // Mr. Big
            $highlight = 'style="background: #ffc107; color: black; font-weight: bold;"';
        } elseif ($item['id'] == 14) { // Lay's Chips
            $highlight = 'style="background: #dc3545; color: white; font-weight: bold;"';
        }
        
        echo "<form method='POST' style='display: inline-block; margin: 5px;'>";
        echo "<input type='hidden' name='item_id' value='{$item['id']}'>";
        echo "<button type='submit' name='test_purchase' {$highlight} style='padding: 10px 15px; border: none; border-radius: 5px; cursor: pointer;'>";
        echo "Test Item ID {$item['id']}: {$item['item_name']} ({$item['qr_coin_cost']} coins)";
        echo "</button>";
        echo "</form>";
    }
    
    echo "<hr>";
    echo "<h3>📊 Recent Purchase Pattern</h3>";
    $stmt = $pdo->prepare("
        SELECT bp.business_store_item_id, bsi.item_name, COUNT(*) as count
        FROM business_purchases bp 
        JOIN business_store_items bsi ON bp.business_store_item_id = bsi.id 
        WHERE bp.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
        GROUP BY bp.business_store_item_id, bsi.item_name
        ORDER BY count DESC
    ");
    $stmt->execute();
    $patterns = $stmt->fetchAll();
    
    if (!empty($patterns)) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th style='padding: 8px;'>Item ID</th><th style='padding: 8px;'>Item Name</th><th style='padding: 8px;'>Purchase Count</th></tr>";
        foreach ($patterns as $pattern) {
            $bg = ($pattern['business_store_item_id'] == 14) ? 'background: #ffebee;' : '';
            echo "<tr style='{$bg}'>";
            echo "<td style='padding: 8px; text-align: center;'>{$pattern['business_store_item_id']}</td>";
            echo "<td style='padding: 8px;'>{$pattern['item_name']}</td>";
            echo "<td style='padding: 8px; text-align: center;'>{$pattern['count']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        if (count($patterns) == 1 && $patterns[0]['business_store_item_id'] == 14) {
            echo "<p style='color: red; font-weight: bold; font-size: 18px;'>🚨 BUG CONFIRMED: ALL purchases are Item ID 14 (Lay's Chips)!</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { margin: 20px 0; }
th, td { border: 1px solid #ddd; }
</style>

<p><a href="/user/business-stores.php">🔙 Back to Business Stores</a></p> 