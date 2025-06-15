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
    $stmt = $pdo->prepare("SELECT id FROM businesses WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $business = $stmt->fetch();
    $business_id = $business ? $business['id'] : 0;

    if (!$business) {
        echo json_encode(['success' => false, 'message' => 'Business not found']);
        exit;
    }

    // Get search parameter for filtering
    $search = $_GET['search'] ?? '';
    
    // Build query with optional search filter
    $searchCondition = '';
    $params = [];
    
    if (!empty($search)) {
        $searchCondition = "WHERE (name LIKE ? OR category LIKE ? OR brand LIKE ?)";
        $searchTerm = "%{$search}%";
        $params = [$searchTerm, $searchTerm, $searchTerm];
    }

    // Get master items with optional search filter
    $stmt = $pdo->prepare("
        SELECT 
            id,
            name,
            category,
            brand,
            suggested_price,
            suggested_cost,
            description,
            image_url
        FROM master_items 
        {$searchCondition}
        ORDER BY 
            category ASC, 
            name ASC
        LIMIT 1000
    ");
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group items by category for better organization
    $categorizedItems = [];
    foreach ($items as $item) {
        $category = $item['category'] ?: 'Uncategorized';
        if (!isset($categorizedItems[$category])) {
            $categorizedItems[$category] = [];
        }
        $categorizedItems[$category][] = $item;
    }

    echo json_encode([
        'success' => true,
        'items' => $items,
        'categorized' => $categorizedItems,
        'total_count' => count($items)
    ]);

} catch (PDOException $e) {
    error_log("Error fetching master items: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error loading items'
    ]);
}
?> 