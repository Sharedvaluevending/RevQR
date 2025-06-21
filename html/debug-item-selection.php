<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/auth.php';

// Require user role (this handles session properly)
require_role('user');

echo "<h2>🔍 Debug: Item Selection Issue</h2>";

try {
    // Get business store items with full debug info
    $stmt = $pdo->prepare("
        SELECT bsi.*, b.name as business_name, b.id as business_id
        FROM business_store_items bsi
        JOIN businesses b ON bsi.business_id = b.id
        WHERE bsi.is_active = 1
        ORDER BY b.name, bsi.item_name
    ");
    $stmt->execute();
    $items = $stmt->fetchAll();
    
    echo "<h3>📋 All Available Items (with IDs)</h3>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%; font-family: Arial, sans-serif;'>";
    echo "<tr style='background: #f0f0f0;'>";
    echo "<th style='padding: 10px;'>Item ID</th>";
    echo "<th style='padding: 10px;'>Business</th>";
    echo "<th style='padding: 10px;'>Item Name</th>";
    echo "<th style='padding: 10px;'>QR Cost</th>";
    echo "<th style='padding: 10px;'>Discount %</th>";
    echo "<th style='padding: 10px;'>Test Button</th>";
    echo "</tr>";
    
    foreach ($items as $item) {
        echo "<tr>";
        echo "<td style='padding: 8px; text-align: center; font-weight: bold; background: #e7f3ff;'>{$item['id']}</td>";
        echo "<td style='padding: 8px;'>{$item['business_name']}</td>";
        echo "<td style='padding: 8px;'><strong>{$item['item_name']}</strong></td>";
        echo "<td style='padding: 8px; text-align: center;'>{$item['qr_coin_cost']}</td>";
        echo "<td style='padding: 8px; text-align: center;'>{$item['discount_percentage']}%</td>";
        echo "<td style='padding: 8px; text-align: center;'>";
        echo "<button onclick='testPurchase({$item['id']}, \"{$item['item_name']}\", {$item['qr_coin_cost']})' ";
        echo "style='background: #28a745; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer;'>";
        echo "Test Purchase</button>";
        echo "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Show your recent purchases for comparison
    echo "<hr><h3>🛒 Your Recent Purchases (Last 10)</h3>";
    $stmt = $pdo->prepare("
        SELECT bp.*, bsi.item_name, bsi.qr_coin_cost as expected_cost, b.name as business_name
        FROM business_purchases bp
        JOIN business_store_items bsi ON bp.business_store_item_id = bsi.id
        JOIN businesses b ON bp.business_id = b.id
        WHERE bp.user_id = ?
        ORDER BY bp.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $purchases = $stmt->fetchAll();
    
    if (!empty($purchases)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%; font-family: Arial, sans-serif;'>";
        echo "<tr style='background: #f0f0f0;'>";
        echo "<th style='padding: 10px;'>Purchase Time</th>";
        echo "<th style='padding: 10px;'>Item ID Used</th>";
        echo "<th style='padding: 10px;'>Item Name</th>";
        echo "<th style='padding: 10px;'>QR Coins Spent</th>";
        echo "<th style='padding: 10px;'>Expected Cost</th>";
        echo "<th style='padding: 10px;'>Match?</th>";
        echo "<th style='padding: 10px;'>Purchase Code</th>";
        echo "</tr>";
        
        foreach ($purchases as $purchase) {
            $cost_match = ($purchase['qr_coins_spent'] == $purchase['expected_cost']);
            $match_color = $cost_match ? '#28a745' : '#dc3545';
            $match_text = $cost_match ? '✅ YES' : '❌ NO';
            
            echo "<tr>";
            echo "<td style='padding: 8px;'>" . date('M j, Y g:i A', strtotime($purchase['created_at'])) . "</td>";
            echo "<td style='padding: 8px; text-align: center; font-weight: bold;'>{$purchase['business_store_item_id']}</td>";
            echo "<td style='padding: 8px;'><strong>{$purchase['item_name']}</strong></td>";
            echo "<td style='padding: 8px; text-align: center;'>{$purchase['qr_coins_spent']}</td>";
            echo "<td style='padding: 8px; text-align: center;'>{$purchase['expected_cost']}</td>";
            echo "<td style='padding: 8px; text-align: center; color: {$match_color}; font-weight: bold;'>{$match_text}</td>";
            echo "<td style='padding: 8px; text-align: center; font-family: monospace;'>{$purchase['purchase_code']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?>

<script>
function testPurchase(itemId, itemName, qrCost) {
    console.log('🧪 Test Purchase Debug:');
    console.log('Item ID:', itemId);
    console.log('Item Name:', itemName);
    console.log('QR Cost:', qrCost);
    
    const confirmed = confirm(`Test Purchase Debug:\n\nItem ID: ${itemId}\nItem Name: ${itemName}\nQR Cost: ${qrCost}\n\nThis will make a REAL purchase. Continue?`);
    
    if (confirmed) {
        // Log what we're sending
        const requestData = { item_id: itemId };
        console.log('📤 Sending request data:', requestData);
        
        fetch('debug-purchase.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(requestData)
        })
        .then(response => {
            console.log('📥 Response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('📥 Response data:', data);
            
            if (data.success) {
                alert(`✅ Purchase Successful!\n\nItem: ${itemName}\nActual Item ID: ${itemId}\nPurchase Code: ${data.purchase_code}\n\nCheck if this matches what you expected!`);
            } else {
                alert('❌ Purchase failed: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('❌ Purchase error:', error);
            alert('Purchase failed: ' + error.message);
        });
    }
}

// Add debugging to the console
console.log('🔍 Debug page loaded. Check the table above to see all item IDs.');
console.log('💡 Use the "Test Purchase" buttons to see exactly what item ID gets sent.');
</script>

<style>
table { font-size: 14px; }
th { background: #f8f9fa !important; }
tr:nth-child(even) { background: #f8f9fa; }
button:hover { opacity: 0.8; }
</style>

<p><a href="/user/business-stores.php">🔙 Back to Business Stores</a></p> 