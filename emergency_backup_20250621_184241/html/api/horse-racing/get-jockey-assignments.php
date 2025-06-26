<?php
/**
 * Horse Racing API - Get Jockey Assignments
 * Returns jockey assignment data with filtering and enhanced performance metrics
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

$business_id = $_SESSION['business_id'];
$machine_id = intval($_GET['machine_id'] ?? 0);
$limit = intval($_GET['limit'] ?? 50);
$offset = intval($_GET['offset'] ?? 0);

try {
    // Build WHERE clause based on machine filter
    $where_clause = "WHERE vl.business_id = ?";
    $params = [$business_id];
    
    if ($machine_id > 0) {
        $where_clause .= " AND vl.id = ?";
        $params[] = $machine_id;
    }
    
    // Get jockey assignments with enhanced performance data
    $stmt = $pdo->prepare("
        SELECT vli.*, vl.name as machine_name, vl.description as machine_location,
               ija.custom_jockey_name, ija.custom_jockey_avatar_url, ija.custom_jockey_color,
               ja.jockey_name as default_jockey_name, ja.jockey_avatar_url as default_jockey_avatar_url, ja.jockey_color as default_jockey_color,
               -- Enhanced sales data with Nayax integration
               COALESCE(sales_data.units_sold_24h, 0) as sales_24h,
               COALESCE(sales_data.revenue_24h, 0) as revenue_24h,
               COALESCE(sales_data.units_sold_7d, 0) as sales_7d,
               COALESCE(nayax_data.nayax_transactions_24h, 0) as nayax_sales_24h,
               COALESCE(nayax_data.nayax_revenue_24h, 0) as nayax_revenue_24h,
               -- Performance score for horse racing
               COALESCE(hpc.performance_score, 0) as performance_score,
               -- Assignment status
               CASE WHEN ija.custom_jockey_name IS NOT NULL THEN 'custom' ELSE 'default' END as assignment_status
        FROM voting_list_items vli
        JOIN voting_lists vl ON vli.voting_list_id = vl.id
        LEFT JOIN item_jockey_assignments ija ON vli.id = ija.item_id AND ija.business_id = ?
        LEFT JOIN jockey_assignments ja ON LOWER(vli.item_category) = ja.item_type
        -- Sales data aggregation
        LEFT JOIN (
            SELECT 
                item_id,
                SUM(CASE WHEN sale_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN quantity ELSE 0 END) as units_sold_24h,
                SUM(CASE WHEN sale_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN (quantity * sale_price) ELSE 0 END) as revenue_24h,
                SUM(CASE WHEN sale_time >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN quantity ELSE 0 END) as units_sold_7d
            FROM sales 
            GROUP BY item_id
        ) sales_data ON vli.id = sales_data.item_id
        -- Nayax transaction data (if available)
        LEFT JOIN (
            SELECT 
                vli_inner.id as item_id,
                COUNT(CASE WHEN nt.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as nayax_transactions_24h,
                SUM(CASE WHEN nt.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN nt.amount_cents/100 ELSE 0 END) as nayax_revenue_24h
            FROM voting_list_items vli_inner
            JOIN voting_lists vl_inner ON vli_inner.voting_list_id = vl_inner.id
            LEFT JOIN nayax_machines nm ON vl_inner.id = nm.platform_machine_id
            LEFT JOIN nayax_transactions nt ON nm.nayax_machine_id = nt.nayax_machine_id
            WHERE vl_inner.business_id = ?
            GROUP BY vli_inner.id
        ) nayax_data ON vli.id = nayax_data.item_id
        -- Horse performance cache
        LEFT JOIN horse_performance_cache hpc ON vli.id = hpc.item_id AND hpc.cache_date = CURDATE()
        $where_clause
        ORDER BY vl.name, vli.item_name
        LIMIT ? OFFSET ?
    ");
    
    $stmt->execute(array_merge([$business_id, $business_id], $params, [$limit, $offset]));
    $items = $stmt->fetchAll();
    
    // Get total count for pagination
    $count_stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM voting_list_items vli
        JOIN voting_lists vl ON vli.voting_list_id = vl.id
        $where_clause
    ");
    $count_stmt->execute($params);
    $total_count = $count_stmt->fetch()['total'];
    
    // Get summary statistics
    $summary = [
        'total_items' => $total_count,
        'custom_assignments' => count(array_filter($items, function($item) { return !empty($item['custom_jockey_name']); })),
        'total_sales_24h' => array_sum(array_column($items, 'sales_24h')),
        'total_nayax_sales_24h' => array_sum(array_column($items, 'nayax_sales_24h')),
        'total_revenue_24h' => array_sum(array_column($items, 'revenue_24h')) + array_sum(array_column($items, 'nayax_revenue_24h')),
        'avg_performance_score' => count($items) > 0 ? array_sum(array_column($items, 'performance_score')) / count($items) : 0
    ];
    
    // Process items for frontend
    $processed_items = array_map(function($item) {
        return [
            'id' => intval($item['id']),
            'item_name' => $item['item_name'],
            'item_category' => $item['item_category'],
            'retail_price' => floatval($item['retail_price']),
            'machine_name' => $item['machine_name'],
            'machine_location' => $item['machine_location'],
            'custom_jockey' => $item['custom_jockey_name'] ? [
                'name' => $item['custom_jockey_name'],
                'color' => $item['custom_jockey_color'],
                'avatar_url' => $item['custom_jockey_avatar_url']
            ] : null,
            'default_jockey' => [
                'name' => $item['default_jockey_name'] ?: 'Wild Card Willie',
                'color' => $item['default_jockey_color'] ?: '#6f42c1',
                'avatar_url' => $item['default_jockey_avatar_url'] ?: '/horse-racing/assets/img/jockeys/jockey-other.png'
            ],
            'performance' => [
                'sales_24h' => intval($item['sales_24h']),
                'nayax_sales_24h' => intval($item['nayax_sales_24h']),
                'revenue_24h' => floatval($item['revenue_24h']),
                'nayax_revenue_24h' => floatval($item['nayax_revenue_24h']),
                'total_revenue_24h' => floatval($item['revenue_24h']) + floatval($item['nayax_revenue_24h']),
                'performance_score' => floatval($item['performance_score']),
                'sales_7d' => intval($item['sales_7d'])
            ],
            'assignment_status' => $item['assignment_status']
        ];
    }, $items);
    
    echo json_encode([
        'success' => true,
        'items' => $processed_items,
        'summary' => $summary,
        'pagination' => [
            'total' => $total_count,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $total_count
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching jockey assignments: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error loading jockey assignments'
    ]);
}
?> 