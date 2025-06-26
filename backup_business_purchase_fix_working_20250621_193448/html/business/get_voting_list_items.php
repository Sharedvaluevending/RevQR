<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';

header('Content-Type: application/json');

// Require business role
if (!is_logged_in() || !has_role('business')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Validate voting_list_id parameter
if (!isset($_GET['voting_list_id']) || !is_numeric($_GET['voting_list_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid voting list ID']);
    exit;
}

$voting_list_id = (int)$_GET['voting_list_id'];

try {
    // Get business ID
    $stmt = $pdo->prepare("SELECT b.id FROM businesses b JOIN users u ON b.id = u.business_id WHERE u.id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $business = $stmt->fetch();
    
    if (!$business) {
        echo json_encode(['success' => false, 'message' => 'Business not found']);
        exit;
    }
    
    $business_id = $business['id'];
    
    // Verify voting list belongs to business
    $stmt = $pdo->prepare("SELECT id FROM voting_lists WHERE id = ? AND business_id = ?");
    $stmt->execute([$voting_list_id, $business_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Voting list not found']);
        exit;
    }
    
    // Get items for this voting list - MODIFIED to include items with 0 inventory
    $stmt = $pdo->prepare("
        SELECT 
            id,
            item_name,
            item_category,
            retail_price,
            cost_price,
            inventory
        FROM voting_list_items
        WHERE voting_list_id = ?
        ORDER BY 
            CASE WHEN inventory > 0 THEN 0 ELSE 1 END,
            item_name ASC
    ");
    
    $stmt->execute([$voting_list_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'items' => $items
    ]);
    
} catch (PDOException $e) {
    error_log("Error fetching voting list items: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching items data'
    ]);
}
?> 