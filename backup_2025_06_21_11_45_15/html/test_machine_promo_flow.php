<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';

// Simulate business session for testing
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'business';

echo "<h2>🎯 COMPLETE MACHINE PROMOTION QR CODE FLOW</h2>";
echo "<p><strong>Now working like the vote system but for promotions!</strong></p>";

// 1. Show the flow
echo "<h3>📋 Complete Flow:</h3>";
echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 10px 0;'>";
echo "<ol>";
echo "<li><strong>Business creates promotions</strong> → <a href='business/promotions.php' target='_blank'>Promotions Management</a></li>";
echo "<li><strong>Business generates QR code</strong> → <a href='qr-generator.php' target='_blank'>QR Generator</a> → Select 'Dynamic Promotion QR Code' → Enter machine name</li>";
echo "<li><strong>Customer scans QR</strong> → Goes to machine promotions page (like vote page)</li>";
echo "<li><strong>Customer views current sales</strong> → Can scan multiple times</li>";
echo "<li><strong>System tracks engagement</strong> → Records each scan for analytics</li>";
echo "</ol>";
echo "</div>";

// 2. Test current promotions
echo "<h3>🎁 Current Active Promotions:</h3>";
try {
    $stmt = $pdo->prepare("
        SELECT p.*, vli.item_name, vl.name as list_name
        FROM promotions p
        JOIN voting_list_items vli ON p.item_id = vli.id
        JOIN voting_lists vl ON p.list_id = vl.id
        WHERE p.status = 'active'
        AND CURDATE() BETWEEN p.start_date AND p.end_date
        ORDER BY p.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $promotions = $stmt->fetchAll();
    
    if (empty($promotions)) {
        echo "<div style='color: orange; background: #fff3cd; padding: 15px; border-radius: 5px;'>";
        echo "❌ <strong>No active promotions found</strong><br>";
        echo "Create some promotions first: <a href='business/promotions.php' target='_blank'>Promotions Management</a>";
        echo "</div>";
    } else {
        echo "<div style='color: green; background: #d1edff; padding: 15px; border-radius: 5px;'>";
        echo "✅ <strong>Found " . count($promotions) . " active promotions</strong>";
        echo "</div>";
        
        foreach ($promotions as $promo) {
            $sale_price = $promo['discount_type'] === 'percentage' 
                ? $promo['retail_price'] * (1 - $promo['discount_value'] / 100)
                : $promo['retail_price'] - $promo['discount_value'];
                
            echo "<div style='border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
            echo "<h5>{$promo['item_name']} - <span style='color: green;'>";
            if ($promo['discount_type'] === 'percentage') {
                echo "{$promo['discount_value']}% OFF";
            } else {
                echo "\${$promo['discount_value']} OFF";
            }
            echo "</span></h5>";
            echo "<p><del>\${$promo['retail_price']}</del> → <strong style='color: green;'>\$" . number_format(max(0, $sale_price), 2) . "</strong></p>";
            echo "<p><strong>Code:</strong> {$promo['promo_code']} | <strong>Valid until:</strong> " . date('M j, Y', strtotime($promo['end_date'])) . "</p>";
            echo "</div>";
        }
    }
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ Error: " . $e->getMessage() . "</div>";
}

// 3. Test machine page URLs
echo "<h3>🔗 Test Machine Promotion Pages:</h3>";
echo "<div style='background: #e7f3ff; padding: 15px; border-radius: 5px;'>";
echo "<p><strong>These are the URLs that QR codes will generate:</strong></p>";
echo "<ul>";
echo "<li><a href='public/machine-sales.php?machine=TestMachine1' target='_blank'>Machine View</a> - Shows all promotions + items</li>";
echo "<li><a href='public/machine-sales.php?machine=TestMachine1&view=promotions' target='_blank'>Promotions Only View</a> - Shows only promotions</li>";
echo "</ul>";
echo "</div>";

// 4. QR Generator Test
echo "<h3>🎯 QR Generator:</h3>";
echo "<div style='background: #fff2e7; padding: 15px; border-radius: 5px;'>";
echo "<ol>";
echo "<li><a href='qr-generator.php' target='_blank'><strong>Open QR Generator</strong></a></li>";
echo "<li>Select <strong>'Dynamic Promotion QR Code'</strong></li>";
echo "<li>Enter machine name: <code>TestMachine1</code></li>";
echo "<li>Enter location: <code>Test Location</code></li>";
echo "<li>Generate QR code!</li>";
echo "</ol>";
echo "</div>";

// 5. Engagement tracking test
echo "<h3>📊 Engagement Tracking:</h3>";
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'machine_engagement'");
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->query("SELECT COUNT(*) as total, view_type, COUNT(DISTINCT user_ip) as unique_visitors FROM machine_engagement GROUP BY view_type");
        $tracking = $stmt->fetchAll();
        
        echo "<div style='color: green; background: #d1edff; padding: 15px; border-radius: 5px;'>";
        echo "✅ <strong>Engagement tracking is working!</strong><br>";
        if (empty($tracking)) {
            echo "No scans recorded yet. Visit the machine pages above to test tracking.";
        } else {
            foreach ($tracking as $track) {
                echo "<strong>{$track['view_type']}:</strong> {$track['total']} views from {$track['unique_visitors']} unique IPs<br>";
            }
        }
        echo "</div>";
    } else {
        echo "<div style='color: orange; background: #fff3cd; padding: 15px; border-radius: 5px;'>";
        echo "⚠️ Engagement tracking table will be created on first scan";
        echo "</div>";
    }
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ Tracking check error: " . $e->getMessage() . "</div>";
}

// 6. Comparison with vote system
echo "<h3>🗳️ How This Compares to Vote System:</h3>";
echo "<div style='background: #f0f8f0; padding: 15px; border-radius: 5px;'>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background: #e8f5e8;'><th style='padding: 10px;'>Feature</th><th style='padding: 10px;'>Vote System</th><th style='padding: 10px;'>Promotion System</th></tr>";
echo "<tr><td style='padding: 8px;'><strong>QR Purpose</strong></td><td style='padding: 8px;'>Vote for items</td><td style='padding: 8px;'>View current promotions</td></tr>";
echo "<tr><td style='padding: 8px;'><strong>URL Format</strong></td><td style='padding: 8px;'>vote.php?campaign=X</td><td style='padding: 8px;'>machine-sales.php?machine=X</td></tr>";
echo "<tr><td style='padding: 8px;'><strong>Multiple Scans</strong></td><td style='padding: 8px;'>Limited (2 per week)</td><td style='padding: 8px;'>Unlimited (engagement tracking)</td></tr>";
echo "<tr><td style='padding: 8px;'><strong>Dynamic Content</strong></td><td style='padding: 8px;'>Shows voting options</td><td style='padding: 8px;'>Shows current sales/promotions</td></tr>";
echo "<tr><td style='padding: 8px;'><strong>Analytics</strong></td><td style='padding: 8px;'>Vote counts, winners</td><td style='padding: 8px;'>Scan counts, engagement</td></tr>";
echo "</table>";
echo "</div>";

?>

<style>
body { font-family: Arial, sans-serif; padding: 20px; max-width: 1200px; margin: 0 auto; }
h2 { color: #2c5530; border-bottom: 2px solid #2c5530; padding-bottom: 10px; }
h3 { color: #4a6741; margin-top: 30px; }
a { color: #0066cc; text-decoration: none; }
a:hover { text-decoration: underline; }
code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
</style> 