<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/qr_coin_manager.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "<h1>Quick Purchase Test</h1>";

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo "<p style='color: red;'>❌ Not logged in</p>";
    exit;
}

$user_id = $_SESSION['user_id'];
echo "<p style='color: green;'>✅ Logged in as User ID: $user_id</p>";

// Check balance
try {
    $balance = QRCoinManager::getBalance($user_id);
    echo "<p style='color: green;'>✅ Balance: $balance QR coins</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Balance error: " . $e->getMessage() . "</p>";
}

// Test business purchase endpoint directly
echo "<h2>Testing Business Purchase Endpoint</h2>";

// Get a test item
try {
    $stmt = $pdo->prepare("SELECT id, item_name, qr_coin_cost FROM business_store_items WHERE is_active = 1 LIMIT 1");
    $stmt->execute();
    $test_item = $stmt->fetch();
    
    if ($test_item) {
        echo "<p>Test item: {$test_item['item_name']} - {$test_item['qr_coin_cost']} coins</p>";
        
        if (isset($_POST['test_purchase'])) {
            echo "<h3>Purchase Test Result:</h3>";
            
            // Simulate the purchase request
            $purchase_data = [
                'item_id' => $test_item['id']
            ];
            
            // Test the endpoint
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'http://localhost/user/purchase-business-item.php');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($purchase_data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'X-Requested-With: XMLHttpRequest'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            echo "<p><strong>HTTP Code:</strong> $http_code</p>";
            echo "<p><strong>Response:</strong></p>";
            echo "<pre style='background: #f5f5f5; padding: 10px;'>" . htmlspecialchars($response) . "</pre>";
            
            $result = json_decode($response, true);
            if ($result) {
                if ($result['success']) {
                    echo "<p style='color: green;'>✅ Purchase successful!</p>";
                } else {
                    echo "<p style='color: red;'>❌ Purchase failed: " . ($result['message'] ?? 'Unknown error') . "</p>";
                }
            }
        } else {
            echo "<form method='post'>";
            echo "<button type='submit' name='test_purchase' value='1'>Test Purchase</button>";
            echo "</form>";
        }
    } else {
        echo "<p style='color: red;'>❌ No test items available</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

// Test discount store endpoint
echo "<h2>Testing Discount Store Endpoint</h2>";

try {
    $stmt = $pdo->prepare("SELECT id, item_name, qr_coin_cost FROM qr_store_items WHERE nayax_compatible = 1 AND is_active = 1 LIMIT 1");
    $stmt->execute();
    $discount_item = $stmt->fetch();
    
    if ($discount_item) {
        echo "<p>Discount item: {$discount_item['item_name']} - {$discount_item['qr_coin_cost']} coins</p>";
        
        if (isset($_POST['test_discount'])) {
            echo "<h3>Discount Purchase Test Result:</h3>";
            
            $discount_data = [
                'item_id' => $discount_item['id'],
                'machine_id' => 'test',
                'source' => 'test'
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'http://localhost/api/purchase-discount.php');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($discount_data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'X-Requested-With: XMLHttpRequest'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            echo "<p><strong>HTTP Code:</strong> $http_code</p>";
            echo "<p><strong>Response:</strong></p>";
            echo "<pre style='background: #f5f5f5; padding: 10px;'>" . htmlspecialchars($response) . "</pre>";
            
            $result = json_decode($response, true);
            if ($result) {
                if ($result['success']) {
                    echo "<p style='color: green;'>✅ Discount purchase successful!</p>";
                } else {
                    echo "<p style='color: red;'>❌ Discount purchase failed: " . ($result['error'] ?? 'Unknown error') . "</p>";
                }
            }
        } else {
            echo "<form method='post'>";
            echo "<button type='submit' name='test_discount' value='1'>Test Discount Purchase</button>";
            echo "</form>";
        }
    } else {
        echo "<p style='color: red;'>❌ No discount items available</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<h2>Session Debug</h2>";
echo "<pre>" . print_r($_SESSION, true) . "</pre>";

?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h1, h2 { color: #333; }
</style> 