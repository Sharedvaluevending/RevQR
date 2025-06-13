<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';

// Simulate business session for testing
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'business';

echo "<h2>🗑️ PROMOTION DELETE FUNCTIONALITY TEST</h2>";

// Test current promotions with delete buttons
echo "<h3>📋 Current Promotions (with Delete Option):</h3>";
try {
    $stmt = $pdo->prepare("
        SELECT p.*, vli.item_name, vl.name as list_name
        FROM promotions p
        JOIN voting_list_items vli ON p.item_id = vli.id
        JOIN voting_lists vl ON p.list_id = vl.id
        ORDER BY p.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $promotions = $stmt->fetchAll();
    
    if (empty($promotions)) {
        echo "<div style='color: orange; background: #fff3cd; padding: 15px; border-radius: 5px;'>";
        echo "❌ <strong>No promotions found</strong><br>";
        echo "Create some promotions first: <a href='business/promotions.php' target='_blank'>Promotions Management</a>";
        echo "</div>";
    } else {
        echo "<div style='color: green; background: #d1edff; padding: 15px; border-radius: 5px;'>";
        echo "✅ <strong>Found " . count($promotions) . " promotions</strong>";
        echo "</div>";
        
        echo "<div style='margin: 20px 0;'>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #f8f9fa;'>";
        echo "<th style='padding: 10px;'>ID</th>";
        echo "<th style='padding: 10px;'>Item</th>";
        echo "<th style='padding: 10px;'>Discount</th>";
        echo "<th style='padding: 10px;'>Code</th>";
        echo "<th style='padding: 10px;'>Status</th>";
        echo "<th style='padding: 10px;'>Valid Until</th>";
        echo "<th style='padding: 10px;'>Actions</th>";
        echo "</tr>";
        
        foreach ($promotions as $promo) {
            $sale_price = $promo['discount_type'] === 'percentage' 
                ? $promo['retail_price'] * (1 - $promo['discount_value'] / 100)
                : $promo['retail_price'] - $promo['discount_value'];
                
            $is_expired = $promo['end_date'] < date('Y-m-d');
            $row_color = $is_expired ? '#ffebee' : '#f1f8e9';
                
            echo "<tr style='background: {$row_color};'>";
            echo "<td style='padding: 8px; text-align: center;'>{$promo['id']}</td>";
            echo "<td style='padding: 8px;'>{$promo['item_name']}</td>";
            echo "<td style='padding: 8px;'>";
            if ($promo['discount_type'] === 'percentage') {
                echo "<span style='color: green; font-weight: bold;'>{$promo['discount_value']}% OFF</span>";
            } else {
                echo "<span style='color: green; font-weight: bold;'>\${$promo['discount_value']} OFF</span>";
            }
            echo "</td>";
            echo "<td style='padding: 8px; font-family: monospace;'>{$promo['promo_code']}</td>";
            echo "<td style='padding: 8px; text-align: center;'>";
            echo "<span style='padding: 4px 8px; border-radius: 4px; color: white; background: " . ($is_expired ? '#d32f2f' : ($promo['status'] === 'active' ? '#4caf50' : '#ff9800')) . ";'>";
            echo $is_expired ? 'Expired' : ucfirst($promo['status']);
            echo "</span>";
            echo "</td>";
            echo "<td style='padding: 8px;'>" . date('M j, Y', strtotime($promo['end_date'])) . "</td>";
            echo "<td style='padding: 8px; text-align: center;'>";
            echo "<button onclick='testDelete({$promo['id']}, \"{$promo['promo_code']}\", \"{$promo['item_name']}\")' ";
            echo "style='background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer;'>";
            echo "🗑️ Delete</button>";
            echo "</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "</div>";
    }
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ Error: " . $e->getMessage() . "</div>";
}

// Show how to access full management
echo "<h3>🎯 Full Promotions Management:</h3>";
echo "<div style='background: #e7f3ff; padding: 15px; border-radius: 5px;'>";
echo "<p><strong>Access the full promotions management interface:</strong></p>";
echo "<p><a href='business/promotions.php' target='_blank' style='color: #0066cc; font-weight: bold;'>→ Open Promotions Management</a></p>";
echo "<p>In the full interface you can:</p>";
echo "<ul>";
echo "<li>✅ Create new promotions</li>";
echo "<li>✅ Generate QR codes for each promotion</li>";
echo "<li>✅ Toggle promotions active/inactive</li>";
echo "<li>✅ Delete promotions (with confirmation)</li>";
echo "<li>✅ View promotion statistics</li>";
echo "</ul>";
echo "</div>";

// Test form for actual deletion
echo "<h3>🧪 Test Deletion:</h3>";
echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px;'>";
echo "<p><strong>Note:</strong> Deletion requires confirmation and is permanent.</p>";
echo "<p>The delete buttons above will show you the confirmation dialog.</p>";
echo "</div>";

?>

<script>
function testDelete(promoId, promoCode, itemName) {
    if (confirm(`Are you sure you want to delete the promotion '${promoCode}' for '${itemName}'? This action cannot be undone.`)) {
        // Create form and submit for actual deletion
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'business/promotions.php';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_promotion">
            <input type="hidden" name="promo_id" value="${promoId}">
        `;
        
        document.body.appendChild(form);
        form.submit();
    } else {
        alert('Deletion cancelled.');
    }
}
</script>

<style>
body { font-family: Arial, sans-serif; padding: 20px; max-width: 1200px; margin: 0 auto; }
h2 { color: #d32f2f; border-bottom: 2px solid #d32f2f; padding-bottom: 10px; }
h3 { color: #616161; margin-top: 30px; }
table { font-size: 14px; }
button:hover { opacity: 0.8; }
</style> 