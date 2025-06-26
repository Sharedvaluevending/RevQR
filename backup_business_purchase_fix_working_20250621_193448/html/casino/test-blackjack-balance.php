<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';

echo "<!DOCTYPE html><html><head><title>Blackjack Balance Test</title>";
echo "<style>body{font-family:Arial,sans-serif;padding:20px;background:#f5f5f5;} .test{background:white;padding:15px;margin:10px 0;border-radius:8px;border-left:4px solid #007bff;} .pass{border-left-color:#28a745;} .fail{border-left-color:#dc3545;}</style>";
echo "</head><body>";

echo "<h1>🃏 Blackjack Balance Test</h1>";

// Check if user is logged in
if (!is_logged_in()) {
    echo "<div class='test fail'>❌ <strong>Not logged in</strong> - Please login to test</div>";
    echo "<p><a href='" . APP_URL . "/login.php'>Login</a></p>";
    echo "</body></html>";
    exit;
}

$user_id = $_SESSION['user_id'];

echo "<div class='test'>📋 <strong>User ID:</strong> $user_id</div>";

// Test balance API
require_once __DIR__ . '/../core/qr_coin_manager.php';
$balance = QRCoinManager::getBalance($user_id);
echo "<div class='test pass'>💰 <strong>Current Balance:</strong> " . number_format($balance) . " QR Coins</div>";

// Test API endpoint
$api_url = APP_URL . "/user/api/get-balance.php";
echo "<div class='test'>🔗 <strong>Balance API URL:</strong> <a href='$api_url' target='_blank'>$api_url</a></div>";

// Test businesses with casino enabled
$stmt = $pdo->prepare("
    SELECT b.id, b.name, bcp.casino_enabled 
    FROM businesses b 
    LEFT JOIN business_casino_participation bcp ON b.id = bcp.business_id 
    WHERE bcp.casino_enabled = 1 
    ORDER BY b.name 
    LIMIT 5
");
$stmt->execute();
$casino_businesses = $stmt->fetchAll();

echo "<div class='test'>";
echo "<strong>🎰 Casino-Enabled Businesses:</strong><br>";
foreach ($casino_businesses as $business) {
    $blackjack_url = APP_URL . "/casino/blackjack.php?business_id=" . $business['id'];
    echo "• <a href='$blackjack_url' target='_blank'>" . htmlspecialchars($business['name']) . "</a> (ID: {$business['id']})<br>";
}
echo "</div>";

// JavaScript test
echo "<div class='test'>";
echo "<strong>🧪 JavaScript Balance Test:</strong><br>";
echo "<button onclick='testBalanceAPI()' class='btn btn-primary'>Test Balance API</button>";
echo "<div id='balanceTestResult' style='margin-top:10px;'></div>";
echo "</div>";

echo "<script>
async function testBalanceAPI() {
    const resultDiv = document.getElementById('balanceTestResult');
    resultDiv.innerHTML = '⏳ Testing...';
    
    try {
        const response = await fetch('" . APP_URL . "/user/api/get-balance.php', {
            method: 'GET',
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            resultDiv.innerHTML = '✅ <strong>API Test PASSED</strong><br>Balance: ' + data.balance.toLocaleString() + ' QR Coins';
            resultDiv.style.color = 'green';
        } else {
            resultDiv.innerHTML = '❌ <strong>API Test FAILED</strong><br>Error: ' + data.error;
            resultDiv.style.color = 'red';
        }
    } catch (error) {
        resultDiv.innerHTML = '❌ <strong>API Test FAILED</strong><br>Error: ' + error.message;
        resultDiv.style.color = 'red';
    }
}
</script>";

echo "<div class='test'>";
echo "<h3>🔧 Blackjack Balance Fix Summary</h3>";
echo "<p><strong>Problem:</strong> Blackjack winnings were being reset to 1680 after connection errors</p>";
echo "<p><strong>Root Cause:</strong> Error handling was 'restoring' balance instead of getting real balance from server</p>";
echo "<p><strong>Fix Applied:</strong></p>";
echo "<ul>";
echo "<li>✅ Removed faulty balance 'restoration' logic</li>";
echo "<li>✅ Added <code>refreshBalanceFromServer()</code> method</li>";
echo "<li>✅ Always use server balance as source of truth</li>";
echo "<li>✅ Proper error handling without balance guessing</li>";
echo "</ul>";
echo "<p><strong>Next Steps:</strong> Play blackjack and verify winnings are properly added to your balance!</p>";
echo "</div>";

echo "</body></html>";
?> 