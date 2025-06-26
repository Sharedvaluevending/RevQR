<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    die('Please log in first');
}

echo "<h2>🛒 Your Recent Business Purchases</h2>";

try {
    // Get recent business purchases for the current user
    $stmt = $pdo->prepare("
        SELECT bp.*, bsi.item_name, bsi.item_description, bsi.discount_percentage as item_discount,
               b.name as business_name, bp.created_at
        FROM business_purchases bp
        JOIN business_store_items bsi ON bp.business_store_item_id = bsi.id
        JOIN businesses b ON bp.business_id = b.id
        WHERE bp.user_id = ?
        ORDER BY bp.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $purchases = $stmt->fetchAll();
    
    if (empty($purchases)) {
        echo "<p>❌ No business purchases found</p>";
    } else {
        echo "<p>✅ Found " . count($purchases) . " recent purchases:</p>";
        
        echo "<table border='1' style='border-collapse: collapse; width: 100%; font-family: Arial, sans-serif;'>";
        echo "<tr style='background: #f0f0f0;'>";
        echo "<th style='padding: 10px;'>Purchase Time</th>";
        echo "<th style='padding: 10px;'>Business</th>";
        echo "<th style='padding: 10px;'>Item Name</th>";
        echo "<th style='padding: 10px;'>Description</th>";
        echo "<th style='padding: 10px;'>Discount %</th>";
        echo "<th style='padding: 10px;'>QR Coins Spent</th>";
        echo "<th style='padding: 10px;'>Purchase Code</th>";
        echo "<th style='padding: 10px;'>Status</th>";
        echo "</tr>";
        
        foreach ($purchases as $purchase) {
            $time_ago = date('M j, Y g:i A', strtotime($purchase['created_at']));
            $status_color = $purchase['status'] === 'pending' ? '#28a745' : ($purchase['status'] === 'redeemed' ? '#6c757d' : '#dc3545');
            
            echo "<tr>";
            echo "<td style='padding: 8px;'>{$time_ago}</td>";
            echo "<td style='padding: 8px;'><strong>{$purchase['business_name']}</strong></td>";
            echo "<td style='padding: 8px;'><strong>{$purchase['item_name']}</strong></td>";
            echo "<td style='padding: 8px;'>" . ($purchase['item_description'] ?: 'No description') . "</td>";
            echo "<td style='padding: 8px; text-align: center;'><span style='background: #28a745; color: white; padding: 2px 6px; border-radius: 3px;'>{$purchase['item_discount']}% OFF</span></td>";
            echo "<td style='padding: 8px; text-align: center;'>{$purchase['qr_coins_spent']}</td>";
            echo "<td style='padding: 8px; text-align: center; font-family: monospace; background: #f8f9fa;'><strong>{$purchase['purchase_code']}</strong></td>";
            echo "<td style='padding: 8px; text-align: center;'><span style='color: {$status_color}; font-weight: bold;'>" . strtoupper($purchase['status']) . "</span></td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Show the most recent purchase details
        $latest = $purchases[0];
        echo "<div style='background: #e7f3ff; padding: 20px; margin: 20px 0; border-radius: 8px; border-left: 4px solid #007cba;'>";
        echo "<h3>🎫 Most Recent Purchase Details</h3>";
        echo "<p><strong>Item:</strong> {$latest['item_name']}</p>";
        echo "<p><strong>Business:</strong> {$latest['business_name']}</p>";
        echo "<p><strong>Discount:</strong> {$latest['item_discount']}% OFF</p>";
        echo "<p><strong>Purchase Code:</strong> <code style='background: white; padding: 4px 8px; border-radius: 4px; font-size: 16px; font-weight: bold;'>{$latest['purchase_code']}</code></p>";
        echo "<p><strong>Status:</strong> {$latest['status']}</p>";
        echo "<p><strong>Purchased:</strong> " . date('F j, Y \a\t g:i A', strtotime($latest['created_at'])) . "</p>";
        if ($latest['item_description']) {
            echo "<p><strong>Description:</strong> {$latest['item_description']}</p>";
        }
        echo "</div>";
    }
    
    // Check available business store items to see what's supposed to be there
    echo "<hr><h3>🏪 Available Business Store Items</h3>";
    $stmt = $pdo->prepare("
        SELECT bsi.id, bsi.item_name, bsi.item_description, bsi.discount_percentage, 
               bsi.qr_coin_cost, b.name as business_name, bsi.is_active
        FROM business_store_items bsi
        JOIN businesses b ON bsi.business_id = b.id
        ORDER BY bsi.is_active DESC, bsi.item_name
    ");
    $stmt->execute();
    $available_items = $stmt->fetchAll();
    
    if (!empty($available_items)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%; font-family: Arial, sans-serif;'>";
        echo "<tr style='background: #f0f0f0;'>";
        echo "<th style='padding: 10px;'>ID</th>";
        echo "<th style='padding: 10px;'>Business</th>";
        echo "<th style='padding: 10px;'>Item Name</th>";
        echo "<th style='padding: 10px;'>Description</th>";
        echo "<th style='padding: 10px;'>Discount %</th>";
        echo "<th style='padding: 10px;'>QR Cost</th>";
        echo "<th style='padding: 10px;'>Status</th>";
        echo "</tr>";
        
        foreach ($available_items as $item) {
            $status = $item['is_active'] ? '✅ Active' : '❌ Inactive';
            $row_style = $item['is_active'] ? '' : 'opacity: 0.6;';
            
            echo "<tr style='{$row_style}'>";
            echo "<td style='padding: 8px;'>{$item['id']}</td>";
            echo "<td style='padding: 8px;'>{$item['business_name']}</td>";
            echo "<td style='padding: 8px;'><strong>{$item['item_name']}</strong></td>";
            echo "<td style='padding: 8px;'>" . ($item['item_description'] ?: 'No description') . "</td>";
            echo "<td style='padding: 8px; text-align: center;'>{$item['discount_percentage']}%</td>";
            echo "<td style='padding: 8px; text-align: center;'>{$item['qr_coin_cost']}</td>";
            echo "<td style='padding: 8px;'>{$status}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<hr>";
    echo "<p><a href='/user/business-stores.php'>🛒 Back to Business Stores</a></p>";
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?> 