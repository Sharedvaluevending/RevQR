<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';

// Require business role
require_role('business');

// Set JSON header
header('Content-Type: application/json');

try {
    // Get user ID from session
    $user_id = $_SESSION['user_id'];

    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    // Get search parameter for filtering
    $search = $_GET['search'] ?? '';
    $category = $_GET['category'] ?? '';
    
    // Build query with optional search and category filters
    $searchCondition = '';
    $params = [$user_id];
    
    $whereConditions = [];
    
    if (!empty($search)) {
        $whereConditions[] = "(mi.name LIKE ? OR mi.category LIKE ? OR mi.brand LIKE ? OR uci.custom_name LIKE ?)";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if (!empty($category)) {
        $whereConditions[] = "mi.category = ?";
        $params[] = $category;
    }
    
    if (!empty($whereConditions)) {
        $searchCondition = "AND " . implode(" AND ", $whereConditions);
    }

    // Get catalog items with optional filters
    $stmt = $pdo->prepare("
        SELECT 
            uci.id,
            uci.custom_name,
            uci.custom_price,
            uci.custom_cost,
            uci.priority_level,
            uci.performance_rating,
            uci.notes,
            mi.name,
            mi.category,
            mi.brand,
            mi.suggested_price,
            mi.suggested_cost,
            mi.description,
            mi.image_url
        FROM user_catalog_items uci
        JOIN master_items mi ON uci.master_item_id = mi.id
        WHERE uci.user_id = ? 
        {$searchCondition}
        ORDER BY 
            mi.category ASC, 
            COALESCE(uci.custom_name, mi.name) ASC
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
        'total' => count($items)
    ]);

} catch (Exception $e) {
    error_log("Error fetching catalog items: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error loading catalog items',
        'error' => $e->getMessage()
    ]);
}
?> 