<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

// Debug headers (only in development)
if (defined('DEVELOPMENT') && DEVELOPMENT) {
    header('X-Debug-Session-User: ' . ($_SESSION['user_id'] ?? 'none'));
    header('X-Debug-Session-Role: ' . ($_SESSION['role'] ?? 'none'));
}

if (!isset($_GET['list_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'List ID is required']);
    exit;
}

$list_id = (int)$_GET['list_id'];

try {
    // Verify business access (if logged in as business)
    $business_check = "";
    $params = [$list_id];
    
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'business' && isset($_SESSION['user_id'])) {
        // Get business ID for the logged-in user
        $stmt = $pdo->prepare("SELECT business_id FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user && $user['business_id']) {
            $business_check = " AND vl.business_id = ?";
            $params[] = $user['business_id'];
        } else {
            echo json_encode(['error' => 'Business not found for user']);
            exit;
        }
    }
    
    // Get items for the list
    $stmt = $pdo->prepare("
        SELECT vli.id, vli.item_name, vli.retail_price
        FROM voting_list_items vli
        JOIN voting_lists vl ON vli.voting_list_id = vl.id
        WHERE vli.voting_list_id = ?
        $business_check
        ORDER BY vli.item_name ASC
    ");
    
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ensure numeric values are properly formatted
    foreach ($items as &$item) {
        $item['id'] = (int)$item['id'];
        $item['retail_price'] = number_format((float)$item['retail_price'], 2, '.', '');
    }
    
    echo json_encode($items);
    
} catch (Exception $e) {
    error_log("Error in get-list-items.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}
?> 