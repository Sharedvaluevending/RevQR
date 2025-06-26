<?php
require_once __DIR__ . '/core/config.php';

echo "<h2>🏪 Business Store Items Test</h2>";

try {
    // Check available business store items
    $stmt = $pdo->prepare("
        SELECT bsi.id, bsi.item_name, bsi.qr_coin_cost, bsi.discount_percentage, 
               bsi.is_active, b.name as business_name, b.id as business_id
        FROM business_store_items bsi 
        JOIN businesses b ON bsi.business_id = b.id 
        ORDER BY bsi.is_active DESC, bsi.qr_coin_cost ASC
        LIMIT 10
    ");
    $stmt->execute();
    $items = $stmt->fetchAll();
    
    if (empty($items)) {
        echo "<p>❌ No business store items found in database</p>";
        
        // Check if businesses exist
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM businesses");
        $business_count = $stmt->fetchColumn();
        echo "<p>📊 Total businesses in database: {$business_count}</p>";
        
        // Check if business_store_items table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'business_store_items'");
        $table_exists = $stmt->fetchColumn();
        echo "<p>📋 business_store_items table exists: " . ($table_exists ? "Yes" : "No") . "</p>";
        
        if ($table_exists) {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM business_store_items");
            $total_items = $stmt->fetchColumn();
            echo "<p>📦 Total items in business_store_items: {$total_items}</p>";
        }
        
    } else {
        echo "<p>✅ Found " . count($items) . " business store items:</p>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Business</th><th>Item Name</th><th>QR Cost</th><th>Discount %</th><th>Active</th></tr>";
        
        foreach ($items as $item) {
            $status = $item['is_active'] ? '✅ Active' : '❌ Inactive';
            echo "<tr>";
            echo "<td>{$item['id']}</td>";
            echo "<td>{$item['business_name']}</td>";
            echo "<td>{$item['item_name']}</td>";
            echo "<td>{$item['qr_coin_cost']}</td>";
            echo "<td>{$item['discount_percentage']}%</td>";
            echo "<td>{$status}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Check current user's balance if logged in
    session_start();
    if (isset($_SESSION['user_id'])) {
        require_once __DIR__ . '/core/qr_coin_manager.php';
        $balance = QRCoinManager::getBalance($_SESSION['user_id']);
        echo "<p>💰 Your current QR coin balance: <strong>{$balance}</strong></p>";
        
        // Show which items you can afford
        $affordable_items = array_filter($items, function($item) use ($balance) {
            return $item['is_active'] && $item['qr_coin_cost'] <= $balance;
        });
        
        if (!empty($affordable_items)) {
            echo "<p>🛒 Items you can afford:</p>";
            echo "<ul>";
            foreach ($affordable_items as $item) {
                echo "<li>{$item['item_name']} ({$item['qr_coin_cost']} coins) - {$item['discount_percentage']}% off at {$item['business_name']}</li>";
            }
            echo "</ul>";
        } else {
            echo "<p>💸 You cannot afford any items currently. Earn more QR coins by voting and spinning!</p>";
        }
    } else {
        echo "<p>🔐 <a href='/login.php'>Login</a> to see your balance and make purchases</p>";
    }
    
    echo "<hr>";
    echo "<p><a href='/user/business-stores.php'>🛒 Go to Business Stores</a></p>";
    echo "<p><a href='/user/dashboard.php'>🏠 Back to Dashboard</a></p>";
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?> 