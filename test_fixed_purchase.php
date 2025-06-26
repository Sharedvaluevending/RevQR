<?php
echo "=== Fixed Discount Purchase Test ===\n";

try {
    require_once 'html/core/config.php';
    require_once 'core/qr_coin_manager.php';
    
    $user_id = 1; // Test user
    
    echo "Testing fixed discount purchase...\n";
    echo "User ID: $user_id\n\n";
    
    // Check user balance first
    $balance = QRCoinManager::getBalance($user_id);
    echo "User balance: $balance QR coins\n";
    
    // Get available discount items
    $stmt = $pdo->prepare("
        SELECT bsi.id, bsi.item_name, bsi.item_description, bsi.qr_coin_cost,
               bsi.discount_percent, b.name as business_name
        FROM business_store_items bsi
        LEFT JOIN businesses b ON bsi.business_id = b.id
        WHERE bsi.category = 'discount' AND bsi.is_active = 1
        LIMIT 3
    ");
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Available discount items:\n";
    foreach ($items as $item) {
        echo "- ID {$item['id']}: {$item['item_name']} - {$item['qr_coin_cost']} coins ({$item['discount_percent']}% off)\n";
    }
    
    if (empty($items)) {
        echo "❌ No discount items available\n";
        exit;
    }
    
    // Test with first affordable item
    $test_item = null;
    foreach ($items as $item) {
        if ($balance >= $item['qr_coin_cost']) {
            $test_item = $item;
            break;
        }
    }
    
    if (!$test_item) {
        echo "❌ No affordable items found\n";
        exit;
    }
    
    echo "\nTesting purchase of: {$test_item['item_name']} (ID: {$test_item['id']})\n";
    
    // Simulate the API call
    session_start();
    $_SESSION['user_id'] = $user_id;
    
    // Test the purchase
    $purchase_data = [
        'item_id' => $test_item['id'],
        'machine_id' => 'test',
        'source' => 'test'
    ];
    
    // Simulate POST data
    $json_data = json_encode($purchase_data);
    
    // Create a temporary file to simulate php://input
    $temp_file = tempnam(sys_get_temp_dir(), 'test_input');
    file_put_contents($temp_file, $json_data);
    
    // Override php://input
    stream_wrapper_unregister('php');
    stream_wrapper_register('php', 'TestInputWrapper');
    TestInputWrapper::$inputData = $json_data;
    
    // Capture the API output
    ob_start();
    
    // Set up environment
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['CONTENT_TYPE'] = 'application/json';
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
    
    try {
        include 'html/api/purchase-discount.php';
        $output = ob_get_contents();
        
        echo "API Response:\n";
        echo $output . "\n";
        
        // Try to decode the response
        $result = json_decode($output, true);
        if ($result) {
            if ($result['success']) {
                echo "\n✅ Purchase successful!\n";
                echo "Discount Code: " . $result['discount_code'] . "\n";
                echo "Discount: " . $result['discount_percent'] . "%\n";
                echo "Expires: " . $result['expires_at'] . "\n";
                
                // Check new balance
                $new_balance = QRCoinManager::getBalance($user_id);
                echo "New balance: $new_balance QR coins (spent: " . ($balance - $new_balance) . ")\n";
            } else {
                echo "\n❌ Purchase failed: " . ($result['error'] ?? 'Unknown error') . "\n";
            }
        } else {
            echo "\n❌ Invalid JSON response\n";
        }
        
    } catch (Exception $e) {
        echo "\n❌ Exception: " . $e->getMessage() . "\n";
    }
    
    ob_end_clean();
    
    // Restore php wrapper
    stream_wrapper_restore('php');
    unlink($temp_file);
    
} catch (Exception $e) {
    echo "❌ Setup Exception: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

// Test input wrapper class
class TestInputWrapper {
    public static $inputData = '';
    private $position = 0;
    
    public function stream_open($path, $mode, $options, &$opened_path) {
        if ($path === 'php://input') {
            $this->position = 0;
            return true;
        }
        return false;
    }
    
    public function stream_read($count) {
        $data = substr(self::$inputData, $this->position, $count);
        $this->position += strlen($data);
        return $data;
    }
    
    public function stream_eof() {
        return $this->position >= strlen(self::$inputData);
    }
    
    public function stream_stat() {
        return [];
    }
}
?> 