<?php
/**
 * Purchase Discount Code API Endpoint
 * Handles QR coin discount code purchases
 */

header('Content-Type: application/json; charset=utf-8');

// Rate limiting
$rate_limit_file = __DIR__ . '/../../logs/purchase_api_rate_limit.json';
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$current_time = time();

if (file_exists($rate_limit_file)) {
    $rate_data = json_decode(file_get_contents($rate_limit_file), true) ?: [];
    $rate_data = array_filter($rate_data, function($timestamp) use ($current_time) {
        return ($current_time - $timestamp) < 60;
    });
    
    if (isset($rate_data[$ip]) && count($rate_data[$ip]) >= 20) {
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => 'Rate limit exceeded']);
        exit;
    }
    
    $rate_data[$ip] = $rate_data[$ip] ?? [];
    $rate_data[$ip][] = $current_time;
} else {
    $rate_data = [$ip => [$current_time]];
}

file_put_contents($rate_limit_file, json_encode($rate_data));

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    require_once __DIR__ . '/../core/config.php';
    require_once __DIR__ . '/../core/nayax_discount_manager.php';
    
    // Check if user is logged in
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'User not authenticated']);
        exit;
    }
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid JSON input']);
        exit;
    }
    
    // Validate required fields
    $item_id = $input['item_id'] ?? null;
    $machine_id = $input['machine_id'] ?? null;
    $source = $input['source'] ?? 'direct';
    
    if (!$item_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing item_id']);
        exit;
    }
    
    // Validate item exists and is available
    $stmt = $pdo->prepare("
        SELECT qsi.*, bsi.nayax_machine_id, bsi.discount_percent,
               b.id as business_id, b.name as business_name
        FROM qr_store_items qsi
        LEFT JOIN business_store_items bsi ON qsi.business_store_item_id = bsi.id
        LEFT JOIN businesses b ON bsi.business_id = b.id
        WHERE qsi.id = ? AND qsi.nayax_compatible = 1 AND qsi.is_active = 1
    ");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Item not found or not available']);
        exit;
    }
    
    // Check machine compatibility if specified
    if ($machine_id && $item['nayax_machine_id'] && $item['nayax_machine_id'] !== $machine_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Item not available for this machine']);
        exit;
    }
    
    // Check user's QR coin balance
    $user_balance = QRCoinManager::getBalance($user_id);
    if ($user_balance < $item['qr_coin_price']) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'error' => 'Insufficient QR coins',
            'required' => $item['qr_coin_price'],
            'available' => $user_balance,
            'shortfall' => $item['qr_coin_price'] - $user_balance
        ]);
        exit;
    }
    
    // Initialize discount manager and make purchase
    $discount_manager = new NayaxDiscountManager($pdo);
    $result = $discount_manager->purchaseDiscountCode($user_id, $item_id, $machine_id);
    
    if ($result['success']) {
        // Log successful purchase
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'user_id' => $user_id,
            'item_id' => $item_id,
            'machine_id' => $machine_id,
            'source' => $source,
            'discount_code' => $result['discount_code'],
            'qr_coins_spent' => $item['qr_coin_price'],
            'ip_address' => $ip
        ];
        
        $log_file = __DIR__ . '/../../logs/discount_purchases.log';
        file_put_contents($log_file, json_encode($log_entry) . "\n", FILE_APPEND | LOCK_EX);
        
        // Track analytics
        try {
            $stmt = $pdo->prepare("
                INSERT INTO purchase_analytics 
                (user_id, item_type, item_id, amount_spent, source, ip_address, created_at)
                VALUES (?, 'discount_code', ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$user_id, $item_id, $item['qr_coin_price'], $source, $ip]);
        } catch (Exception $e) {
            error_log("Failed to track purchase analytics: " . $e->getMessage());
        }
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'purchase_id' => $result['purchase_id'],
            'discount_code' => $result['discount_code'],
            'discount_percent' => $result['discount_percent'],
            'expires_at' => $result['expires_at'],
            'item_name' => $result['item_name'],
            'business_name' => $result['business_name'],
            'qr_coins_spent' => $item['qr_coin_price'],
            'new_balance' => $user_balance - $item['qr_coin_price']
        ]);
        
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $result['message'] ?? 'Purchase failed'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Purchase discount API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}
?> 