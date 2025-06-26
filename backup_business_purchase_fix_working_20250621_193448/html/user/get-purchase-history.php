<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';

// Require user role
require_role('user');

// Set JSON content type
header('Content-Type: application/json');

try {
    $type = $_GET['type'] ?? 'business';
    $limit = (int)($_GET['limit'] ?? 10);
    
    if ($limit > 50) $limit = 50; // Cap at 50
    
    if ($type === 'business') {
        // Get business purchase history
        $stmt = $pdo->prepare("
            SELECT 
                bp.*,
                bsi.item_name,
                b.name as business_name,
                DATE_FORMAT(bp.created_at, '%M %d, %Y at %h:%i %p') as created_at
            FROM business_purchases bp
            JOIN business_store_items bsi ON bp.business_store_item_id = bsi.id
            JOIN businesses b ON bp.business_id = b.id
            WHERE bp.user_id = ?
            ORDER BY bp.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$_SESSION['user_id'], $limit]);
        $purchases = $stmt->fetchAll();
        
    } else {
        // Get regular QR store purchase history
        $stmt = $pdo->prepare("
            SELECT 
                uqsp.*,
                qsi.name as item_name,
                qsi.description as item_description,
                DATE_FORMAT(uqsp.created_at, '%M %d, %Y at %h:%i %p') as created_at
            FROM user_qr_store_purchases uqsp
            JOIN qr_store_items qsi ON uqsp.qr_store_item_id = qsi.id
            WHERE uqsp.user_id = ?
            ORDER BY uqsp.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$_SESSION['user_id'], $limit]);
        $purchases = $stmt->fetchAll();
    }
    
    echo json_encode([
        'success' => true,
        'purchases' => $purchases,
        'count' => count($purchases)
    ]);
    
} catch (Exception $e) {
    error_log("Purchase history error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'purchases' => []
    ]);
}
?> 