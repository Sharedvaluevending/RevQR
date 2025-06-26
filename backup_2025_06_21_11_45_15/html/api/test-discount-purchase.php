<?php
/**
 * Test Discount Purchase API
 * Provides detailed debugging for discount purchase issues
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../core/config.php';
    require_once __DIR__ . '/../core/nayax_discount_manager.php';
    
    // Check authentication
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        echo json_encode([
            'success' => false,
            'error' => 'Not authenticated',
            'debug' => 'User not logged in'
        ]);
        exit;
    }
    
    // Get user info
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get user balance
    $balance = QRCoinManager::getBalance($user_id);
    
    // Check if we have any QR store items
    $stmt = $pdo->prepare("
        SELECT qsi.*, bsi.nayax_machine_id, bsi.discount_percent,
               b.id as business_id, b.name as business_name
        FROM qr_store_items qsi
        LEFT JOIN business_store_items bsi ON qsi.business_store_item_id = bsi.id
        LEFT JOIN businesses b ON bsi.business_id = b.id
        WHERE qsi.nayax_compatible = 1 AND qsi.is_active = 1
        LIMIT 5
    ");
    $stmt->execute();
    $available_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Test database connectivity
    $db_test = $pdo->query("SELECT 1 as test")->fetch();
    
    // Test discount manager initialization
    $discount_manager = new NayaxDiscountManager($pdo);
    
    $debug_info = [
        'user_info' => [
            'user_id' => $user_id,
            'username' => $user['username'] ?? 'Unknown',
            'balance' => $balance
        ],
        'database' => [
            'connected' => $db_test['test'] === 1,
            'available_items_count' => count($available_items),
            'available_items' => $available_items
        ],
        'system' => [
            'discount_manager_initialized' => isset($discount_manager),
            'session_active' => session_status() === PHP_SESSION_ACTIVE,
            'pdo_in_transaction' => $pdo->inTransaction()
        ]
    ];
    
    // If POST request, try a test purchase
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $item_id = $input['item_id'] ?? (count($available_items) > 0 ? $available_items[0]['id'] : null);
        
        if (!$item_id) {
            echo json_encode([
                'success' => false,
                'error' => 'No items available for testing',
                'debug' => $debug_info
            ]);
            exit;
        }
        
        // Test the purchase
        $result = $discount_manager->purchaseDiscountCode($user_id, $item_id);
        
        echo json_encode([
            'success' => $result['success'],
            'purchase_result' => $result,
            'debug' => $debug_info
        ]);
        
    } else {
        // GET request - just return debug info
        echo json_encode([
            'success' => true,
            'message' => 'Test endpoint ready',
            'debug' => $debug_info
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'exception_trace' => $e->getTraceAsString(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}
?> 