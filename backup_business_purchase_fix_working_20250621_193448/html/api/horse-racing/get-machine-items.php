<?php
/**
 * Horse Racing API - Get Machine Items
 * Returns items for a specific machine that can be used as horses
 */

require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/auth.php';

header('Content-Type: application/json');

// Require business role
if (!has_role('business')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$machine_id = intval($_GET['machine_id'] ?? 0);
$business_id = $_SESSION['business_id'];

if (!$machine_id) {
    echo json_encode(['success' => false, 'message' => 'Machine ID required']);
    exit;
}

try {
    // Get items for this machine (ensure it belongs to the business)
    $stmt = $pdo->prepare("
        SELECT vli.*, 
               COALESCE(sales_24h.total_sold, 0) as sales_24h,
               COALESCE(sales_24h.total_revenue, 0) as revenue_24h,
               (vli.retail_price - COALESCE(vli.cost_price, 0)) as profit_margin
        FROM voting_list_items vli
        JOIN voting_lists vl ON vli.voting_list_id = vl.id
        LEFT JOIN (
            SELECT item_id, 
                   SUM(quantity) as total_sold,
                   SUM(quantity * sale_price) as total_revenue
            FROM sales 
            WHERE sale_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY item_id
        ) sales_24h ON vli.id = sales_24h.item_id
        WHERE vl.id = ? AND vl.business_id = ?
        ORDER BY vli.item_name
    ");
    $stmt->execute([$machine_id, $business_id]);
    $items = $stmt->fetchAll();
    
    // Add performance indicators for each item
    foreach ($items as &$item) {
        // Calculate basic performance score for preview
        $sales_score = min(100, $item['sales_24h'] * 10);
        $profit_score = min(100, $item['profit_margin'] * 50);
        $inventory_score = min(100, $item['inventory'] * 2);
        
        $item['performance_preview'] = round(($sales_score + $profit_score + $inventory_score) / 3);
        $item['racing_potential'] = $item['performance_preview'] > 70 ? 'High' : 
                                   ($item['performance_preview'] > 40 ? 'Medium' : 'Low');
    }
    
    echo json_encode([
        'success' => true,
        'items' => $items,
        'count' => count($items)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?> 