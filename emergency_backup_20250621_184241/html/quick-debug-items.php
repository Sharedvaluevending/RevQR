<?php
require_once __DIR__ . '/core/config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simple check - don't redirect, just show info
if (!isset($_SESSION['user_id'])) {
    echo "<h2>❌ Not logged in</h2>";
    echo "<p><a href='/user/login.php'>Please log in first</a></p>";
    exit;
}

echo "<h2>🚨 CRITICAL BUG ANALYSIS</h2>";
echo "<p><strong>User ID:</strong> {$_SESSION['user_id']}</p>";

try {
    // Show the exact problem with your recent purchases
    echo "<h3>🔍 Your Recent Purchases (Showing the Bug)</h3>";
    $stmt = $pdo->prepare("
        SELECT 
            bp.id,
            bp.business_store_item_id,
            bp.qr_coins_spent,
            bp.purchase_code,
            bp.created_at,
            bsi.item_name,
            bsi.qr_coin_cost as correct_cost,
            CASE 
                WHEN bp.qr_coins_spent = bsi.qr_coin_cost THEN 'CORRECT'
                ELSE 'BUG DETECTED'
            END as status
        FROM business_purchases bp
        JOIN business_store_items bsi ON bp.business_store_item_id = bsi.id
        WHERE bp.user_id = ?
        ORDER BY bp.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $purchases = $stmt->fetchAll();
    
    if (!empty($purchases)) {
        echo "<table border='2' style='border-collapse: collapse; width: 100%; font-size: 16px;'>";
        echo "<tr style='background: #dc3545; color: white;'>";
        echo "<th style='padding: 12px;'>Purchase Time</th>";
        echo "<th style='padding: 12px;'>Item You Got</th>";
        echo "<th style='padding: 12px;'>You Paid</th>";
        echo "<th style='padding: 12px;'>Should Cost</th>";
        echo "<th style='padding: 12px;'>BUG STATUS</th>";
        echo "<th style='padding: 12px;'>Code</th>";
        echo "</tr>";
        
        foreach ($purchases as $purchase) {
            $bg_color = ($purchase['status'] === 'BUG DETECTED') ? '#ffebee' : '#e8f5e8';
            $text_color = ($purchase['status'] === 'BUG DETECTED') ? '#c62828' : '#2e7d32';
            
            echo "<tr style='background: {$bg_color};'>";
            echo "<td style='padding: 10px;'>" . date('M j g:i A', strtotime($purchase['created_at'])) . "</td>";
            echo "<td style='padding: 10px; font-weight: bold;'>{$purchase['item_name']}</td>";
            echo "<td style='padding: 10px; text-align: center; font-weight: bold; color: {$text_color};'>{$purchase['qr_coins_spent']}</td>";
            echo "<td style='padding: 10px; text-align: center;'>{$purchase['correct_cost']}</td>";
            echo "<td style='padding: 10px; text-align: center; font-weight: bold; color: {$text_color};'>{$purchase['status']}</td>";
            echo "<td style='padding: 10px; text-align: center; font-family: monospace;'>{$purchase['purchase_code']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Show what items SHOULD exist
    echo "<hr><h3>📋 Available Items (What Should Be Purchasable)</h3>";
    $stmt = $pdo->prepare("
        SELECT id, item_name, qr_coin_cost, discount_percentage
        FROM business_store_items 
        WHERE is_active = 1
        ORDER BY qr_coin_cost DESC
    ");
    $stmt->execute();
    $items = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #f0f0f0;'>";
    echo "<th style='padding: 10px;'>Item ID</th>";
    echo "<th style='padding: 10px;'>Item Name</th>";
    echo "<th style='padding: 10px;'>QR Cost</th>";
    echo "<th style='padding: 10px;'>Discount</th>";
    echo "</tr>";
    
    foreach ($items as $item) {
        // Highlight the items you should have gotten
        $highlight = '';
        if ($item['qr_coin_cost'] == 450) { // Mr. Big
            $highlight = 'background: #fff3cd; border: 3px solid #ffc107;';
        } elseif ($item['item_name'] === 'Energy Drink') {
            $highlight = 'background: #d1ecf1; border: 2px solid #bee5eb;';
        } elseif ($item['item_name'] === "Lay's Chips") {
            $highlight = 'background: #f8d7da; border: 2px solid #dc3545;';
        }
        
        echo "<tr style='{$highlight}'>";
        echo "<td style='padding: 8px; text-align: center; font-weight: bold;'>{$item['id']}</td>";
        echo "<td style='padding: 8px;'><strong>{$item['item_name']}</strong></td>";
        echo "<td style='padding: 8px; text-align: center;'>{$item['qr_coin_cost']}</td>";
        echo "<td style='padding: 8px; text-align: center;'>{$item['discount_percentage']}%</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<div style='background: #dc3545; color: white; padding: 20px; margin: 20px 0; border-radius: 8px;'>";
    echo "<h3>🚨 BUG SUMMARY</h3>";
    echo "<ul style='font-size: 16px; line-height: 1.6;'>";
    echo "<li><strong>PROBLEM:</strong> You paid for expensive items but got cheap ones</li>";
    echo "<li><strong>EVIDENCE:</strong> You paid 450 coins (Mr. Big price) but got Lay's Chips</li>";
    echo "<li><strong>CAUSE:</strong> Wrong item ID being sent in purchase request</li>";
    echo "<li><strong>IMPACT:</strong> You're losing coins and getting wrong discounts</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<p style='color: red; font-size: 18px;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { margin: 20px 0; }
th, td { border: 1px solid #ddd; }
</style>

<p><a href="/user/business-stores.php" style="background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">🔙 Back to Business Stores</a></p> 