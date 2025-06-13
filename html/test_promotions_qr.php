<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';

// Simulate business session for testing
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'business';

echo "<h2>🎯 COMPLETE PROMOTION QR CODE FLOW TEST</h2>";

// 1. Check if we have promotions
echo "<h3>1. Available Promotions:</h3>";
try {
    $stmt = $pdo->prepare("
        SELECT p.*, vli.item_name, vl.name as list_name
        FROM promotions p
        JOIN voting_list_items vli ON p.item_id = vli.id
        JOIN voting_lists vl ON p.list_id = vl.id
        WHERE p.status = 'active'
        AND CURDATE() BETWEEN p.start_date AND p.end_date
        LIMIT 5
    ");
    $stmt->execute();
    $promotions = $stmt->fetchAll();
    
    if (empty($promotions)) {
        echo "<div style='color: orange;'>❌ No active promotions found</div>";
        echo "<p>To test the full flow:</p>";
        echo "<ol>";
        echo "<li>Go to <a href='business/promotions.php'>Promotions Management</a></li>";
        echo "<li>Create a new promotion</li>";
        echo "<li>Then test QR generation</li>";
        echo "</ol>";
    } else {
        echo "<div style='color: green;'>✅ Found " . count($promotions) . " active promotions</div>";
        foreach ($promotions as $promo) {
            echo "<div style='border: 1px solid #ddd; padding: 10px; margin: 5px;'>";
            echo "<strong>Code:</strong> {$promo['promo_code']}<br>";
            echo "<strong>Item:</strong> {$promo['item_name']}<br>";
            echo "<strong>Discount:</strong> ";
            if ($promo['discount_type'] === 'percentage') {
                echo "{$promo['discount_value']}% off";
            } else {
                echo "\${$promo['discount_value']} off";
            }
            echo "<br><strong>Test URL:</strong> <a href='redeem.php?code={$promo['promo_code']}' target='_blank'>redeem.php?code={$promo['promo_code']}</a>";
            echo "</div>";
        }
    }
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ Error: " . $e->getMessage() . "</div>";
}

// 2. Test API endpoint
echo "<h3>2. API Endpoint Test:</h3>";
echo "<button onclick='testPromotionsAPI()'>Test Get Promotions API</button>";
echo "<div id='apiResult'></div>";

// 3. QR Generator Links
echo "<h3>3. QR Generator:</h3>";
echo "<p><a href='qr-generator.php' target='_blank'>🔗 Open QR Generator</a></p>";
echo "<p>Select 'Dynamic Promotion QR Code' type and you should see your promotions dropdown!</p>";

// 4. Instructions
echo "<h3>4. Complete Flow Instructions:</h3>";
echo "<ol>";
echo "<li><strong>Create Promotion:</strong> business/promotions.php → Create new promotion</li>";
echo "<li><strong>Generate QR:</strong> qr-generator.php → Select 'Dynamic Promotion QR Code' → Choose promotion</li>";
echo "<li><strong>Customer Scans:</strong> QR leads to redeem.php?code=XXX</li>";
echo "<li><strong>Customer Redeems:</strong> Shows discount, click redeem button</li>";
echo "<li><strong>Show Business:</strong> Success screen is proof for business</li>";
echo "</ol>";

?>

<script>
async function testPromotionsAPI() {
    const resultDiv = document.getElementById('apiResult');
    resultDiv.innerHTML = 'Loading...';
    
    try {
        const response = await fetch('/api/get-promotions.php');
        const data = await response.json();
        
        if (response.ok) {
            resultDiv.innerHTML = '<div style="color: green;">✅ API Success: ' + JSON.stringify(data, null, 2) + '</div>';
        } else {
            resultDiv.innerHTML = '<div style="color: red;">❌ API Error: ' + JSON.stringify(data) + '</div>';
        }
    } catch (error) {
        resultDiv.innerHTML = '<div style="color: red;">❌ Network Error: ' + error.message + '</div>';
    }
}
</script>

<style>
body { font-family: Arial, sans-serif; padding: 20px; }
h2 { color: #333; }
h3 { color: #666; margin-top: 30px; }
pre { background: #f4f4f4; padding: 10px; }
</style> 