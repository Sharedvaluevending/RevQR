<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';

// Simulate business session for testing
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'business';

echo "<h1>🎯 COMPLETE PROMOTION QR CODE SYSTEM TEST</h1>";
echo "<p><strong>Testing the entire promotion QR code functionality!</strong></p>";

// 1. Check promotions exist
echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<h2>1. ✅ Current Active Promotions</h2>";
try {
    $stmt = $pdo->prepare("
        SELECT p.*, vli.item_name, vli.retail_price, vl.name as list_name, b.name as business_name
        FROM promotions p
        JOIN voting_list_items vli ON p.item_id = vli.id
        JOIN voting_lists vl ON p.list_id = vl.id
        JOIN businesses b ON p.business_id = b.id
        WHERE p.status = 'active'
        AND CURDATE() BETWEEN p.start_date AND p.end_date
        ORDER BY p.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $promotions = $stmt->fetchAll();
    
    if (empty($promotions)) {
        echo "<div style='color: orange; border: 2px solid orange; padding: 15px; border-radius: 5px;'>";
        echo "❌ <strong>NO ACTIVE PROMOTIONS FOUND</strong><br>";
        echo "To test the system properly, you need to create promotions first!<br>";
        echo "<a href='business/promotions.php' style='color: blue; text-decoration: underline;'>→ Create Promotions Here</a>";
        echo "</div>";
    } else {
        echo "<div style='color: green; border: 2px solid green; padding: 15px; border-radius: 5px;'>";
        echo "✅ <strong>Found " . count($promotions) . " active promotions</strong><br>";
        foreach ($promotions as $promo) {
            $sale_price = $promo['discount_type'] === 'percentage' 
                ? $promo['retail_price'] * (1 - $promo['discount_value'] / 100)
                : $promo['retail_price'] - $promo['discount_value'];
                
            echo "<div style='margin: 10px 0; padding: 10px; background: white; border-radius: 3px;'>";
            echo "<strong>{$promo['item_name']}</strong> - Code: <code>{$promo['promo_code']}</code><br>";
            echo "Business: {$promo['business_name']}<br>";
            echo "Discount: ";
            if ($promo['discount_type'] === 'percentage') {
                echo "{$promo['discount_value']}% OFF";
            } else {
                echo "\${$promo['discount_value']} OFF";
            }
            echo " | Price: \${$promo['retail_price']} → \$" . number_format(max(0, $sale_price), 2) . "<br>";
            echo "Valid until: " . date('M j, Y', strtotime($promo['end_date'])) . "<br>";
            echo "<a href='redeem.php?code={$promo['promo_code']}' target='_blank' style='color: green; font-weight: bold;'>🎯 Test Redemption</a>";
            echo "</div>";
        }
        echo "</div>";
    }
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ Error: " . $e->getMessage() . "</div>";
}
echo "</div>";

// 2. Test QR Generator Types
echo "<div style='background: #e7f3ff; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<h2>2. 🎨 QR Generator Types</h2>";
echo "<p>The QR generator now supports these promotion-related types:</p>";
echo "<table border='1' style='border-collapse: collapse; width: 100%; background: white;'>";
echo "<tr style='background: #f0f8ff;'>";
echo "<th style='padding: 10px;'>QR Type</th>";
echo "<th style='padding: 10px;'>Purpose</th>";
echo "<th style='padding: 10px;'>Generated URL</th>";
echo "<th style='padding: 10px;'>Test</th>";
echo "</tr>";

$test_machine = 'TestMachine1';
$qr_types = [
    'machine_sales' => [
        'purpose' => 'Shows machine promotions + items',
        'url' => '/public/promotions.php?machine=' . urlencode($test_machine),
    ],
    'promotion' => [
        'purpose' => 'Shows only promotions for machine',
        'url' => '/public/promotions.php?machine=' . urlencode($test_machine) . '&view=promotions',
    ]
];

foreach ($qr_types as $type => $info) {
    echo "<tr>";
    echo "<td style='padding: 8px;'><strong>{$type}</strong></td>";
    echo "<td style='padding: 8px;'>{$info['purpose']}</td>";
    echo "<td style='padding: 8px; font-family: monospace;'>{$info['url']}</td>";
    echo "<td style='padding: 8px;'><a href='{$info['url']}' target='_blank' style='color: blue;'>🔗 Test Page</a></td>";
    echo "</tr>";
}
echo "</table>";

echo "<div style='margin-top: 15px; padding: 10px; background: #fff3cd; border-radius: 5px;'>";
echo "<strong>🎯 To Generate QR Codes:</strong><br>";
echo "1. <a href='qr-generator.php' target='_blank'>Open QR Generator</a><br>";
echo "2. Select QR Type: 'Machine Sales QR Code' or 'Promotion Display QR Code'<br>";
echo "3. Enter Machine Name: <code>{$test_machine}</code><br>";
echo "4. Generate and test!";
echo "</div>";
echo "</div>";

// 3. Test Machine Promotion Pages
echo "<div style='background: #e8f5e8; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<h2>3. 📱 Public Promotion Pages</h2>";
echo "<div style='display: grid; grid-template-columns: 1fr 1fr; gap: 20px;'>";

// Test Machine View
echo "<div style='background: white; padding: 15px; border-radius: 5px; border: 2px solid #28a745;'>";
echo "<h3>🏪 Machine View</h3>";
echo "<p><strong>URL:</strong> <code>/public/promotions.php?machine={$test_machine}</code></p>";
echo "<p>Shows: All promotions + available items</p>";
echo "<a href='public/promotions.php?machine={$test_machine}' target='_blank' class='test-btn' style='display: inline-block; background: #28a745; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px;'>🔗 Test Machine View</a>";
echo "</div>";

// Test Promotions Only View
echo "<div style='background: white; padding: 15px; border-radius: 5px; border: 2px solid #ffc107;'>";
echo "<h3>🎁 Promotions Only View</h3>";
echo "<p><strong>URL:</strong> <code>/public/promotions.php?machine={$test_machine}&view=promotions</code></p>";
echo "<p>Shows: Only current promotions</p>";
echo "<a href='public/promotions.php?machine={$test_machine}&view=promotions' target='_blank' class='test-btn' style='display: inline-block; background: #ffc107; color: black; padding: 10px 15px; text-decoration: none; border-radius: 5px;'>🔗 Test Promotions View</a>";
echo "</div>";

echo "</div>";
echo "</div>";

// 4. Test Individual Promotion Redemption
if (!empty($promotions)) {
    echo "<div style='background: #fff2e7; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h2>4. 🎟️ Individual Promotion Redemption</h2>";
    echo "<p>Test the individual promotion QR codes that businesses create:</p>";
    
    foreach ($promotions as $promo) {
        echo "<div style='background: white; padding: 15px; margin: 10px 0; border-radius: 5px; border: 2px solid #17a2b8;'>";
        echo "<h4>{$promo['item_name']} - {$promo['promo_code']}</h4>";
        echo "<p><strong>Redemption URL:</strong> <code>/redeem.php?code={$promo['promo_code']}</code></p>";
        echo "<div style='display: flex; gap: 10px; margin-top: 10px;'>";
        echo "<a href='redeem.php?code={$promo['promo_code']}' target='_blank' style='background: #28a745; color: white; padding: 8px 12px; text-decoration: none; border-radius: 3px;'>🎯 Test Redemption</a>";
        echo "<button onclick='generateTestQR(\"{$promo['promo_code']}\", \"{$promo['item_name']}\")' style='background: #17a2b8; color: white; padding: 8px 12px; border: none; border-radius: 3px; cursor: pointer;'>📱 Generate QR</button>";
        echo "</div>";
        echo "</div>";
    }
    echo "</div>";
}

// 5. System Integration Status
echo "<div style='background: #f3e5f5; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<h2>5. ⚙️ System Integration Status</h2>";

// Test engagement tracking
echo "<h3>📊 Engagement Tracking</h3>";
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'machine_engagement'");
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->query("
            SELECT view_type, COUNT(*) as total, COUNT(DISTINCT user_ip) as unique_visitors 
            FROM machine_engagement 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY view_type
        ");
        $tracking = $stmt->fetchAll();
        
        echo "<div style='color: green; background: white; padding: 15px; border-radius: 5px;'>";
        echo "✅ <strong>Engagement tracking is active!</strong><br>";
        if (empty($tracking)) {
            echo "No recent scans recorded. Visit the test pages above to generate tracking data.";
        } else {
            echo "<strong>Last 7 days:</strong><br>";
            foreach ($tracking as $track) {
                echo "• {$track['view_type']}: {$track['total']} views from {$track['unique_visitors']} unique IPs<br>";
            }
        }
        echo "</div>";
    } else {
        echo "<div style='color: orange; background: white; padding: 15px; border-radius: 5px;'>";
        echo "⚠️ Engagement tracking table will be created automatically on first page visit";
        echo "</div>";
    }
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ Tracking check error: " . $e->getMessage() . "</div>";
}

// Test QR codes table
echo "<h3>🔲 QR Codes Database</h3>";
try {
    $stmt = $pdo->query("
        SELECT qr_type, COUNT(*) as count 
        FROM qr_codes 
        WHERE status = 'active' 
        GROUP BY qr_type
    ");
    $qr_stats = $stmt->fetchAll();
    
    if (empty($qr_stats)) {
        echo "<div style='color: orange; background: white; padding: 15px; border-radius: 5px;'>";
        echo "⚠️ No QR codes generated yet. Use the QR Generator to create some!";
        echo "</div>";
    } else {
        echo "<div style='color: green; background: white; padding: 15px; border-radius: 5px;'>";
        echo "✅ <strong>Active QR codes by type:</strong><br>";
        foreach ($qr_stats as $stat) {
            echo "• {$stat['qr_type']}: {$stat['count']} codes<br>";
        }
        echo "</div>";
    }
} catch (Exception $e) {
    echo "<div style='color: orange; background: white; padding: 15px; border-radius: 5px;'>";
    echo "⚠️ QR codes table status: " . $e->getMessage();
    echo "</div>";
}

echo "</div>";

// 6. Complete Workflow
echo "<div style='background: #e6f3ff; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<h2>6. 🔄 Complete Workflow Test</h2>";
echo "<ol style='background: white; padding: 20px; border-radius: 5px;'>";
echo "<li><strong>Create Promotions:</strong> <a href='business/promotions.php' target='_blank'>business/promotions.php</a></li>";
echo "<li><strong>Generate Machine QR:</strong> <a href='qr-generator.php' target='_blank'>qr-generator.php</a> → 'Machine Sales QR Code'</li>";
echo "<li><strong>Generate Promotion QR:</strong> <a href='qr-generator.php' target='_blank'>qr-generator.php</a> → 'Promotion Display QR Code'</li>";
echo "<li><strong>Customer Scans Machine QR:</strong> Goes to promotions page → sees all current deals</li>";
echo "<li><strong>Customer Scans Individual QR:</strong> Goes to redemption page → claims specific discount</li>";
echo "<li><strong>Business Gets Analytics:</strong> Track engagement and redemptions</li>";
echo "</ol>";

echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin-top: 15px;'>";
echo "<strong>🎯 Key Improvements Made:</strong><br>";
echo "• ✅ Created missing <code>/public/promotions.php</code> page<br>";
echo "• ✅ Fixed QR URL generation logic<br>";
echo "• ✅ Added 'Promotion Display QR Code' type<br>";
echo "• ✅ Unified machine and promotion QR functionality<br>";
echo "• ✅ Automatic engagement tracking<br>";
echo "• ✅ Beautiful mobile-friendly promotion display";
echo "</div>";
echo "</div>";

?>

<!-- QR Code Modal for testing -->
<div id="qrModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; justify-content: center; align-items: center;">
    <div style="background: white; padding: 30px; border-radius: 10px; text-align: center; max-width: 400px;">
        <h3 id="qrTitle">Promotion QR Code</h3>
        <div id="qrcode" style="margin: 20px 0;"></div>
        <p style="font-family: monospace; background: #f8f9fa; padding: 10px; border-radius: 5px; margin: 15px 0;" id="qrUrl"></p>
        <button onclick="closeQRModal()" style="background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">Close</button>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
function generateTestQR(promoCode, itemName) {
    const modal = document.getElementById('qrModal');
    const qrContainer = document.getElementById('qrcode');
    const qrTitle = document.getElementById('qrTitle');
    const qrUrl = document.getElementById('qrUrl');
    
    // Clear previous QR code
    qrContainer.innerHTML = '';
    
    // Set title and URL
    qrTitle.textContent = `${itemName} - QR Code`;
    const redemptionUrl = `${window.location.origin}/redeem.php?code=${promoCode}`;
    qrUrl.textContent = redemptionUrl;
    
    // Generate QR code
    new QRCode(qrContainer, {
        text: redemptionUrl,
        width: 200,
        height: 200,
        colorDark: "#000000",
        colorLight: "#ffffff"
    });
    
    // Show modal
    modal.style.display = 'flex';
}

function closeQRModal() {
    document.getElementById('qrModal').style.display = 'none';
}

// Close modal when clicking outside
document.getElementById('qrModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeQRModal();
    }
});
</script>

<style>
body { 
    font-family: Arial, sans-serif; 
    padding: 20px; 
    max-width: 1200px; 
    margin: 0 auto; 
    line-height: 1.6;
}
h1 { 
    color: #2c5530; 
    border-bottom: 3px solid #2c5530; 
    padding-bottom: 10px; 
}
h2 { 
    color: #4a6741; 
    margin-top: 30px; 
}
h3 {
    color: #5a7a61;
}
a { 
    color: #0066cc; 
    text-decoration: none; 
}
a:hover { 
    text-decoration: underline; 
}
code { 
    background: #f4f4f4; 
    padding: 2px 6px; 
    border-radius: 3px; 
    font-family: 'Courier New', monospace;
}
table {
    margin: 15px 0;
}
th, td {
    text-align: left;
}
.test-btn {
    display: inline-block;
    margin: 5px 0;
}
.test-btn:hover {
    opacity: 0.9;
    text-decoration: none !important;
}
</style> 