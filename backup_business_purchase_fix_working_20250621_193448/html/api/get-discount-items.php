<?php
// Start session and check auth
session_start();
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../core/config.php';
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit;
    }
    
    // Get available discount items from business_store_items
    $stmt = $pdo->prepare("
        SELECT bsi.id, bsi.item_name, bsi.item_description, bsi.qr_coin_cost,
               bsi.discount_percent, b.name as business_name
        FROM business_store_items bsi
        LEFT JOIN businesses b ON bsi.business_id = b.id
        WHERE bsi.category = 'discount' AND bsi.is_active = 1
        LIMIT 10
    ");
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'items' => $items,
        'count' => count($items)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 