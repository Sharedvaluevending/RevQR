<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';

// Require business role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'business') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

// Get business details
$stmt = $pdo->prepare("
    SELECT b.*, u.username 
    FROM businesses b 
    JOIN users u ON b.id = u.business_id 
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$business = $stmt->fetch();

if (!$business) {
    http_response_code(404);
    echo json_encode(['error' => 'Business not found']);
    exit;
}

$business_id = $business['id'];
$list_id = $_GET['list_id'] ?? null;

if (!$list_id) {
    http_response_code(400);
    echo json_encode(['error' => 'List ID is required']);
    exit;
}

try {
    // Get items for the specified voting list
    $stmt = $pdo->prepare("
        SELECT vli.id, vli.item_name, vli.retail_price, vli.cost_price, vli.popularity
        FROM voting_list_items vli
        JOIN voting_lists vl ON vli.list_id = vl.id
        WHERE vl.id = ? AND vl.business_id = ?
        ORDER BY vli.item_name
    ");
    $stmt->execute([$list_id, $business_id]);
    $items = $stmt->fetchAll();

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'items' => $items
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to fetch items: ' . $e->getMessage()
    ]);
} 