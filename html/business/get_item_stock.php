<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';

// Require business role
require_role('business');

// Set JSON header
header('Content-Type: application/json');

try {
    // Get business details
    $stmt = $pdo->prepare("SELECT b.id FROM businesses b JOIN users u ON b.id = u.business_id WHERE u.id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $business = $stmt->fetch();
    $business_id = $business ? $business['id'] : 0;
    
    if (!$business) {
        echo json_encode(['success' => false, 'message' => 'Business not found']);
        exit;
    }
    
    $item_id = $_GET['item_id'] ?? 0;
    
    if (!$item_id) {
        echo json_encode(['success' => false, 'message' => 'Item ID required']);
        exit;
    }
    
    // Get current stock levels for this item across all machines
    $stmt = $pdo->prepare("
        SELECT 
            vl.id as machine_id,
            vl.name as machine_name,
            vl.description as location,
            vli.inventory as current_stock
        FROM voting_lists vl
        JOIN voting_list_items vli ON vl.id = vli.voting_list_id
        WHERE vl.business_id = ? 
            AND vli.master_item_id = ?
        ORDER BY vl.name ASC
    ");
    $stmt->execute([$business_id, $item_id]);
    $stock_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'stock' => $stock_data
    ]);
    
} catch (PDOException $e) {
    error_log("Error fetching item stock: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error loading stock data'
    ]);
}
?> 