<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/csrf.php';
require_once __DIR__ . '/../core/business_system_detector.php';
require_once __DIR__ . '/../core/services/UnifiedInventoryManager.php';

// Require business role
require_role('business');

// Generate CSRF token
$csrf_token = generate_csrf_token();

$message = '';
$message_type = '';

// Get business details
$stmt = $pdo->prepare("SELECT b.id FROM businesses b JOIN users u ON b.id = u.business_id WHERE u.id = ?");
$stmt->execute([$_SESSION['user_id']]);
$business = $stmt->fetch();
$business_id = $business ? $business['id'] : 0;

// Initialize business system detection and unified inventory
BusinessSystemDetector::init($pdo);
$capabilities = BusinessSystemDetector::getBusinessCapabilities($business_id);
$inventoryManager = new UnifiedInventoryManager();

// Get filter parameters
$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
$priority = trim($_GET['priority'] ?? '');
$performance = trim($_GET['performance'] ?? '');
$tag = trim($_GET['tag'] ?? '');
$sort = in_array($_GET['sort'] ?? '', ['name', 'performance_desc', 'margin_desc', 'revenue_desc', 'priority_desc', 'recent', 'sales_desc']) ? $_GET['sort'] : 'performance_desc';
$page = max(1, intval($_GET['page'] ?? 1));
$itemsPerPage = max(10, min(100, intval($_GET['per_page'] ?? 20)));

// ENHANCED: Use unified inventory system if available
$catalogItems = [];
$totalItems = 0;

if ($capabilities['is_unified'] || $capabilities['has_nayax']) {
    // Get unified inventory data
    $unifiedFilters = [];
    if (!empty($category)) $unifiedFilters['category'] = $category;
    
    try {
        $unifiedInventory = $inventoryManager->getUnifiedInventory($business_id, $unifiedFilters);
        
        // Map unified data to catalog format for compatibility
        foreach ($unifiedInventory as $item) {
            // Skip items without mapping to user catalog (need to map them first)
            $checkCatalog = $pdo->prepare("
                SELECT uci.*, mi.name as item_name, mi.brand, mi.category as master_category 
                FROM user_catalog_items uci 
                JOIN master_items mi ON uci.master_item_id = mi.id
                WHERE uci.user_id = ? AND mi.name LIKE ?
            ");
            $checkCatalog->execute([$_SESSION['user_id'], '%' . $item['unified_name'] . '%']);
            $catalogEntry = $checkCatalog->fetch();
            
            if ($catalogEntry) {
                // Merge unified data with catalog entry
                $mergedItem = array_merge($catalogEntry, [
                    'unified_name' => $item['unified_name'],
                    'system_type' => $item['system_type'],
                    'unified_stock_total' => $item['total_available_qty'],
                    'manual_stock_qty' => $item['manual_stock_qty'],
                    'nayax_estimated_qty' => $item['nayax_estimated_qty'],
                    'unified_sales_today' => $item['total_sales_today'],
                    'unified_sales_week' => $item['total_sales_week'],
                    'sync_status' => $item['sync_status'],
                    'mapping_confidence' => $item['mapping_confidence'],
                    'low_stock_threshold' => $item['low_stock_threshold'],
                    'calculated_performance' => min(5, max(1, ($item['total_sales_week'] / 7) + 1))
                ]);
                
                $catalogItems[] = $mergedItem;
            }
        }
        
        $totalItems = count($catalogItems);
        
    } catch (Exception $e) {
        error_log("Error fetching unified inventory: " . $e->getMessage());
        // Fallback to traditional query
        $useTraditionalQuery = true;
    }
} 

// Fallback: Traditional catalog query for manual-only systems or errors
if (empty($catalogItems)) {
    $query = "
        SELECT 
            uci.*,
            mi.name as item_name,
            mi.brand,
            mi.category as master_category,
            mi.suggested_price as master_price,
            mi.suggested_cost as master_cost,
            (uci.custom_price - uci.custom_cost) as current_margin,
            CASE 
                WHEN uci.custom_cost > 0 THEN ((uci.custom_price - uci.custom_cost) / uci.custom_cost * 100)
                ELSE 0 
            END as margin_percentage,
            -- Sales data (optional - will be 0 if no sales data)
            COALESCE(SUM(s.sale_price * s.quantity), 0) as recent_revenue,
            COALESCE(SUM((s.sale_price - uci.custom_cost) * s.quantity), 0) as recent_profit,
            COALESCE(SUM(s.quantity), 0) as recent_sales,
            COALESCE(COUNT(DISTINCT DATE(s.sale_time)), 0) as active_days,
            COALESCE(AVG(s.sale_price), uci.custom_price) as avg_sale_price,
            -- Inventory data (optional)
            COALESCE(SUM(vli.inventory), 0) as current_stock,
            -- Performance based on user rating or calculated from sales
            COALESCE(uci.performance_rating, 
                CASE 
                    WHEN SUM(s.quantity) > 50 THEN 5.0
                    WHEN SUM(s.quantity) > 30 THEN 4.0
                    WHEN SUM(s.quantity) > 20 THEN 3.5
                    WHEN SUM(s.quantity) > 10 THEN 3.0
                    WHEN SUM(s.quantity) > 5 THEN 2.5
                    WHEN SUM(s.quantity) > 0 THEN 2.0
                    ELSE 1.0
                END
            ) as calculated_performance,
            'manual_only' as system_type,
            0 as manual_stock_qty,
            0 as nayax_estimated_qty,
            0 as unified_stock_total,
            0 as unified_sales_today,
            0 as unified_sales_week,
            'legacy' as sync_status,
            'n/a' as mapping_confidence,
            5 as low_stock_threshold,
            GROUP_CONCAT(DISTINCT cit.tag_name) as tags
        FROM user_catalog_items uci
        JOIN master_items mi ON uci.master_item_id = mi.id
        LEFT JOIN item_mapping im ON mi.id = im.master_item_id
        LEFT JOIN voting_list_items vli ON im.item_id = vli.id AND vli.voting_list_id IN (
            SELECT id FROM voting_lists WHERE business_id = ?
        )
        LEFT JOIN sales s ON vli.id = s.item_id 
            AND s.business_id = ? 
            AND s.sale_time >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        LEFT JOIN catalog_item_tags cit ON uci.id = cit.catalog_item_id
        WHERE uci.user_id = ?
    ";
    $params = [$business_id, $business_id, $_SESSION['user_id']];

    // Add search condition
    if (!empty($search)) {
        $query .= " AND (mi.name LIKE ? OR mi.brand LIKE ? OR uci.custom_name LIKE ? OR uci.notes LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    // Add filters
    if (!empty($priority)) {
        $query .= " AND uci.priority_level = ?";
        $params[] = $priority;
    }

    if (!empty($performance)) {
        switch ($performance) {
            case 'excellent':
                $query .= " AND uci.performance_rating >= 4.0";
                break;
            case 'good':
                $query .= " AND uci.performance_rating >= 3.0 AND uci.performance_rating < 4.0";
                break;
            case 'average':
                $query .= " AND uci.performance_rating >= 2.0 AND uci.performance_rating < 3.0";
                break;
            case 'poor':
                $query .= " AND uci.performance_rating < 2.0";
                break;
        }
    }

    if (!empty($tag)) {
        $query .= " AND cit.tag_name = ?";
        $params[] = $tag;
    }

    // Add category filter
    if (!empty($category)) {
        $query .= " AND mi.category = ?";
        $params[] = $category;
    }

    $query .= " GROUP BY uci.id, mi.name, mi.brand, mi.category, mi.suggested_price, mi.suggested_cost, uci.custom_price, uci.custom_cost";

    // Add sorting
    switch ($sort) {
        case 'name':
            $query .= " ORDER BY mi.name ASC";
            break;
        case 'margin_desc':
            $query .= " ORDER BY margin_percentage DESC, mi.name ASC";
            break;
        case 'revenue_desc':
            $query .= " ORDER BY recent_revenue DESC, mi.name ASC";
            break;
        case 'sales_desc':
            $query .= " ORDER BY recent_sales DESC, mi.name ASC";
            break;
        case 'priority_desc':
            $query .= " ORDER BY FIELD(uci.priority_level, 'critical', 'high', 'medium', 'low'), mi.name ASC";
            break;
        case 'recent':
            $query .= " ORDER BY uci.updated_at DESC";
            break;
        default:
            $query .= " ORDER BY calculated_performance DESC, margin_percentage DESC";
    }

    // Get total count for pagination
    $countQuery = "
        SELECT COUNT(DISTINCT uci.id)
        FROM user_catalog_items uci
        JOIN master_items mi ON uci.master_item_id = mi.id
        LEFT JOIN catalog_item_tags cit ON uci.id = cit.catalog_item_id
        WHERE uci.user_id = ?
    ";
    $countParams = [$_SESSION['user_id']];

    if (!empty($search)) {
        $countQuery .= " AND (mi.name LIKE ? OR mi.brand LIKE ? OR uci.custom_name LIKE ? OR uci.notes LIKE ?)";
        $countParams[] = $searchTerm;
        $countParams[] = $searchTerm;
        $countParams[] = $searchTerm;
        $countParams[] = $searchTerm;
    }

    if (!empty($priority)) {
        $countQuery .= " AND uci.priority_level = ?";
        $countParams[] = $priority;
    }

    if (!empty($category)) {
        $countQuery .= " AND mi.category = ?";
        $countParams[] = $category;
    }

    try {
        $countStmt = $pdo->prepare($countQuery);
        $countStmt->execute($countParams);
        $totalItems = $countStmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error getting catalog count: " . $e->getMessage());
        $totalItems = 0;
    }

    $totalPages = ceil($totalItems / $itemsPerPage);

    // Add pagination
    $query .= " LIMIT ? OFFSET ?";
    $params[] = $itemsPerPage;
    $params[] = ($page - 1) * $itemsPerPage;

    // Execute main query
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $catalogItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching catalog items: " . $e->getMessage());
        $catalogItems = [];
        $message = "Error loading catalog items. Please try again.";
        $message_type = "danger";
    }
}

// Calculate total pages for unified results
if (!empty($catalogItems) && ($capabilities['is_unified'] || $capabilities['has_nayax'])) {
    $totalPages = max(1, ceil($totalItems / $itemsPerPage));
} else {
    $totalPages = max(1, ceil($totalItems / $itemsPerPage));
}

// Get summary statistics - updated to use real sales data
try {
    $statsQuery = "
        SELECT 
            COUNT(DISTINCT uci.id) as total_items,
            AVG(CASE 
                WHEN uci.performance_rating > 0 THEN uci.performance_rating
                ELSE 1.0
            END) as avg_performance,
            AVG((uci.custom_price - uci.custom_cost) / NULLIF(uci.custom_cost, 0) * 100) as avg_margin,
            COUNT(CASE WHEN uci.priority_level = 'critical' THEN 1 END) as critical_items,
            COUNT(CASE WHEN uci.priority_level = 'high' THEN 1 END) as high_priority_items,
            COUNT(CASE WHEN uci.is_favorite = 1 THEN 1 END) as favorite_items
        FROM user_catalog_items uci
        WHERE uci.user_id = ?
    ";
    
    $statsStmt = $pdo->prepare($statsQuery);
    $statsStmt->execute([$_SESSION['user_id']]);
    $statsRow = $statsStmt->fetch();
    
    // Get sales totals separately
    $salesQuery = "
        SELECT 
            COALESCE(SUM(s.sale_price * s.quantity), 0) as total_revenue,
            COALESCE(SUM(s.quantity), 0) as total_sales
        FROM user_catalog_items uci
        LEFT JOIN master_items mi ON uci.master_item_id = mi.id
        LEFT JOIN item_mapping im ON mi.id = im.master_item_id
        LEFT JOIN voting_list_items vli ON im.item_id = vli.id AND vli.voting_list_id IN (
            SELECT id FROM voting_lists WHERE business_id = ?
        )
        LEFT JOIN sales s ON vli.id = s.item_id 
            AND s.business_id = ? 
            AND s.sale_time >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        WHERE uci.user_id = ?
    ";
    
    $salesStmt = $pdo->prepare($salesQuery);
    $salesStmt->execute([$business_id, $business_id, $_SESSION['user_id']]);
    $salesRow = $salesStmt->fetch();
    
    $stats = [
        'total_items' => intval($statsRow['total_items'] ?? 0),
        'avg_performance' => floatval($statsRow['avg_performance'] ?? 0),
        'avg_margin' => floatval($statsRow['avg_margin'] ?? 0),
        'total_revenue' => floatval($salesRow['total_revenue'] ?? 0),
        'total_sales' => intval($salesRow['total_sales'] ?? 0),
        'critical_items' => intval($statsRow['critical_items'] ?? 0),
        'high_priority_items' => intval($statsRow['high_priority_items'] ?? 0),
        'favorite_items' => intval($statsRow['favorite_items'] ?? 0)
    ];
} catch (PDOException $e) {
    error_log("Error fetching stats: " . $e->getMessage());
    $stats = [
        'total_items' => 0,
        'avg_performance' => 0.0,
        'avg_margin' => 0.0,
        'total_revenue' => 0.0,
        'total_sales' => 0,
        'critical_items' => 0,
        'high_priority_items' => 0,
        'favorite_items' => 0
    ];
}

// Ensure all numeric values are properly set to avoid null
$stats['avg_performance'] = floatval($stats['avg_performance'] ?? 0);
$stats['avg_margin'] = floatval($stats['avg_margin'] ?? 0);
$stats['total_revenue'] = floatval($stats['total_revenue'] ?? 0);
$stats['total_sales'] = intval($stats['total_sales'] ?? 0);
$stats['total_items'] = intval($stats['total_items'] ?? 0);
$stats['critical_items'] = intval($stats['critical_items'] ?? 0);
$stats['high_priority_items'] = intval($stats['high_priority_items'] ?? 0);
$stats['favorite_items'] = intval($stats['favorite_items'] ?? 0);

// Get available tags
try {
    $tagsQuery = "
        SELECT DISTINCT cit.tag_name, COUNT(*) as count
        FROM catalog_item_tags cit
        JOIN user_catalog_items uci ON cit.catalog_item_id = uci.id
        WHERE uci.user_id = ?
        GROUP BY cit.tag_name
        ORDER BY count DESC, cit.tag_name ASC
    ";
    
    $tagsStmt = $pdo->prepare($tagsQuery);
    $tagsStmt->execute([$_SESSION['user_id']]);
    $availableTags = $tagsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching tags: " . $e->getMessage());
    $availableTags = [];
}

require_once __DIR__ . '/../core/includes/header.php';
?>

<div class="container-fluid px-0">
    <!-- Header Section with Advanced Stats -->
    <div class="bg-gradient-primary text-white">
        <div class="container py-4">
            <div class="row align-items-center">
                <div class="col">
                    <h1 class="h3 mb-1 text-white">
                        <i class="bi bi-bookmark-star me-2"></i>My Catalog
                        <!-- ENHANCED: System Type Indicator -->
                        <?php if ($capabilities['is_unified']): ?>
                            <span class="badge bg-warning text-dark">🔗 Unified System</span>
                        <?php elseif ($capabilities['has_nayax']): ?>
                            <span class="badge bg-primary">📡 Nayax System</span>
                        <?php else: ?>
                            <span class="badge bg-success">📱 Manual System</span>
                        <?php endif; ?>
                    </h1>
                    <p class="mb-0 opacity-75">
                        <?php if ($capabilities['is_unified']): ?>
                            Advanced cross-system analytics showing combined manual + Nayax inventory
                        <?php elseif ($capabilities['has_nayax']): ?>
                            Real-time Nayax machine inventory and sales tracking
                        <?php else: ?>
                            Advanced analytics and performance tracking for your manual catalog
                        <?php endif; ?>
                    </p>
                </div>
                <div class="col-auto d-flex align-items-center gap-2">
                    <div class="row g-3 text-center">
                        <div class="col">
                            <div class="card bg-white bg-opacity-10 border-0 text-white">
                                <div class="card-body py-2 px-3">
                                    <h4 class="h5 mb-0"><?php echo number_format($stats['total_items'] ?? 0); ?></h4>
                                    <small class="opacity-75">Total Items</small>
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="card bg-white bg-opacity-10 border-0 text-white">
                                <div class="card-body py-2 px-3">
                                    <h4 class="h5 mb-0"><?php echo number_format($stats['avg_performance'] ?? 0, 1); ?>/5</h4>
                                    <small class="opacity-75">Avg Performance</small>
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="card bg-white bg-opacity-10 border-0 text-white">
                                <div class="card-body py-2 px-3">
                                    <h4 class="h5 mb-0"><?php echo number_format($stats['avg_margin'] ?? 0, 1); ?>%</h4>
                                    <small class="opacity-75">Avg Margin</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show mx-4 mt-4" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="container-fluid py-4">
        <div class="row">
            <!-- Quick Stats Cards -->
            <div class="col-12 mb-4">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="card border-danger">
                            <div class="card-body text-center">
                                <h3 class="text-danger mb-1"><?php echo $stats['critical_items'] ?? 0; ?></h3>
                                <small class="text-muted">Critical Priority</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-warning">
                            <div class="card-body text-center">
                                <h3 class="text-warning mb-1"><?php echo $stats['high_priority_items'] ?? 0; ?></h3>
                                <small class="text-muted">High Priority</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-success">
                            <div class="card-body text-center">
                                <h3 class="text-success mb-1"><?php echo $stats['favorite_items'] ?? 0; ?></h3>
                                <small class="text-muted">Favorites</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-info">
                            <div class="card-body text-center">
                                <h3 class="text-info mb-1"><?php echo number_format($stats['total_sales'] ?? 0); ?></h3>
                                <small class="text-muted">Total Sales</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Catalog Interface -->
            <div class="col-12">
                <div class="card shadow-sm">
                    <!-- Enhanced Header -->
                    <div class="card-header bg-white py-3">
                        <div class="row align-items-center g-3">
                            <div class="col-md-6">
                                <h5 class="mb-0">
                                    <i class="bi bi-grid-3x3-gap me-2"></i>Catalog Items
                                </h5>
                                <small class="text-muted">
                                    Showing <?php echo ($page - 1) * $itemsPerPage + 1; ?>-<?php echo min($page * $itemsPerPage, $totalItems); ?> 
                                    of <?php echo number_format($totalItems ?? 0); ?> items
                                </small>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex gap-2 justify-content-end">
                                    <form id="searchForm" class="flex-grow-1" style="max-width: 300px;">
                                        <div class="input-group">
                                            <span class="input-group-text bg-light border-end-0">
                                                <i class="bi bi-search"></i>
                                            </span>
                                            <input type="text" 
                                                   class="form-control border-start-0" 
                                                   id="searchItems" 
                                                   name="search" 
                                                   placeholder="Search catalog..." 
                                                   value="<?php echo htmlspecialchars($search); ?>"
                                                   autocomplete="off">
                                            <?php if (!empty($search)): ?>
                                                <button class="btn btn-outline-secondary" 
                                                        type="button" 
                                                        id="clearSearch">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </form>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#analyticsModal">
                                            <i class="bi bi-graph-up me-1"></i>Analytics
                                        </button>
                                        <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#promotionsModal">
                                            <i class="bi bi-megaphone me-1"></i>Promotions
                                        </button>
                                        <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#combosModal">
                                            <i class="bi bi-collection me-1"></i>Combos
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Advanced Filters -->
                    <div class="bg-light p-3 border-bottom">
                        <form id="filterForm" class="row g-3 align-items-end">
                            <div class="col-md-2">
                                <label class="form-label small text-muted mb-1">Priority</label>
                                <select class="form-select form-select-sm" name="priority" autocomplete="off">
                                    <option value="">All Priorities</option>
                                    <option value="critical" <?php echo $priority === 'critical' ? 'selected' : ''; ?>>Critical</option>
                                    <option value="high" <?php echo $priority === 'high' ? 'selected' : ''; ?>>High</option>
                                    <option value="medium" <?php echo $priority === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                    <option value="low" <?php echo $priority === 'low' ? 'selected' : ''; ?>>Low</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small text-muted mb-1">Category</label>
                                <select class="form-select form-select-sm" name="category" autocomplete="off">
                                    <option value="">All Categories</option>
                                    <option value="Candy and Chocolate Bars" <?php echo $category === 'Candy and Chocolate Bars' ? 'selected' : ''; ?>>Candy & Chocolate</option>
                                    <option value="Chips and Savory Snacks" <?php echo $category === 'Chips and Savory Snacks' ? 'selected' : ''; ?>>Chips & Snacks</option>
                                    <option value="Cookies" <?php echo $category === 'Cookies' ? 'selected' : ''; ?>>Cookies</option>
                                    <option value="Energy Drinks" <?php echo $category === 'Energy Drinks' ? 'selected' : ''; ?>>Energy Drinks</option>
                                    <option value="Healthy Snacks" <?php echo $category === 'Healthy Snacks' ? 'selected' : ''; ?>>Healthy Snacks</option>
                                    <option value="Juices and Bottled Teas" <?php echo $category === 'Juices and Bottled Teas' ? 'selected' : ''; ?>>Juices & Teas</option>
                                    <option value="Water and Flavored Water" <?php echo $category === 'Water and Flavored Water' ? 'selected' : ''; ?>>Water</option>
                                    <option value="Soft Drinks and Carbonated Beverages" <?php echo $category === 'Soft Drinks and Carbonated Beverages' ? 'selected' : ''; ?>>Soft Drinks</option>
                                    <option value="Protein and Meal Replacement Bars" <?php echo $category === 'Protein and Meal Replacement Bars' ? 'selected' : ''; ?>>Protein Bars</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small text-muted mb-1">Performance</label>
                                <select class="form-select form-select-sm" name="performance" autocomplete="off">
                                    <option value="">All Performance</option>
                                    <option value="excellent" <?php echo $performance === 'excellent' ? 'selected' : ''; ?>>Excellent (4.0+)</option>
                                    <option value="good" <?php echo $performance === 'good' ? 'selected' : ''; ?>>Good (3.0-3.9)</option>
                                    <option value="average" <?php echo $performance === 'average' ? 'selected' : ''; ?>>Average (2.0-2.9)</option>
                                    <option value="poor" <?php echo $performance === 'poor' ? 'selected' : ''; ?>>Poor (<2.0)</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small text-muted mb-1">Tags</label>
                                <select class="form-select form-select-sm" name="tag" autocomplete="off">
                                    <option value="">All Tags</option>
                                    <?php foreach ($availableTags as $tagData): ?>
                                        <option value="<?php echo htmlspecialchars($tagData['tag_name']); ?>"
                                                <?php echo $tag === $tagData['tag_name'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($tagData['tag_name']); ?> (<?php echo $tagData['count']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small text-muted mb-1">Sort By</label>
                                <select class="form-select form-select-sm" name="sort" autocomplete="off">
                                    <option value="performance_desc" <?php echo $sort === 'performance_desc' ? 'selected' : ''; ?>>Best Performance</option>
                                    <option value="margin_desc" <?php echo $sort === 'margin_desc' ? 'selected' : ''; ?>>Highest Margin</option>
                                    <option value="revenue_desc" <?php echo $sort === 'revenue_desc' ? 'selected' : ''; ?>>Highest Revenue</option>
                                    <option value="sales_desc" <?php echo $sort === 'sales_desc' ? 'selected' : ''; ?>>Highest Sales</option>
                                    <option value="priority_desc" <?php echo $sort === 'priority_desc' ? 'selected' : ''; ?>>Priority Level</option>
                                    <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Name A-Z</option>
                                    <option value="recent" <?php echo $sort === 'recent' ? 'selected' : ''; ?>>Recently Added</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small text-muted mb-1">Per Page</label>
                                <select class="form-select form-select-sm" name="per_page" autocomplete="off">
                                    <option value="10" <?php echo $itemsPerPage === 10 ? 'selected' : ''; ?>>10</option>
                                    <option value="20" <?php echo $itemsPerPage === 20 ? 'selected' : ''; ?>>20</option>
                                    <option value="50" <?php echo $itemsPerPage === 50 ? 'selected' : ''; ?>>50</option>
                                    <option value="100" <?php echo $itemsPerPage === 100 ? 'selected' : ''; ?>>100</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm w-100" onclick="clearAllFilters()">
                                    <i class="bi bi-arrow-clockwise me-1"></i>Clear Filters
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="card-body p-0">
                        <?php if (empty($catalogItems)): ?>
                            <!-- Empty State -->
                            <div class="text-center py-5">
                                <i class="bi bi-bookmark display-1 text-muted"></i>
                                <h4 class="mt-3">No items in your catalog</h4>
                                <p class="text-muted">
                                    <?php if (!empty($search) || !empty($priority) || !empty($performance) || !empty($tag)): ?>
                                        No items match your current filters. <button class="btn btn-link p-0" onclick="clearAllFilters()">Clear filters</button>
                                    <?php else: ?>
                                        Start building your catalog by adding items from the <a href="master-items.php">Master Items</a> page.
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <!-- Catalog Items Grid -->
                            <div class="row g-3 p-3">
                                <?php foreach ($catalogItems as $item): ?>
                                    <div class="col-md-6 col-lg-4 col-xl-3">
                                        <div class="card h-100 catalog-item-card" data-item-id="<?php echo $item['id']; ?>" data-system-type="<?php echo $item['system_type'] ?? 'manual_only'; ?>">
                                            <div class="card-header bg-light py-2 px-3">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-1 fw-bold">
                                                            <?php echo htmlspecialchars($item['unified_name'] ?? $item['custom_name'] ?: $item['item_name']); ?>
                                                        </h6>
                                                        <?php if (!empty($item['brand'])): ?>
                                                            <small class="text-muted"><?php echo htmlspecialchars($item['brand']); ?></small>
                                                        <?php endif; ?>
                                                        <!-- ENHANCED: System Type Indicator -->
                                                        <div class="mt-1">
                                                            <?php if (($item['system_type'] ?? 'manual_only') === 'unified'): ?>
                                                                <span class="badge bg-warning text-dark">🔗 Unified</span>
                                                            <?php elseif (($item['system_type'] ?? 'manual_only') === 'nayax_only'): ?>
                                                                <span class="badge bg-primary">📡 Nayax</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-success">📱 Manual</span>
                                                            <?php endif; ?>
                                                            
                                                            <!-- Sync Status Indicator -->
                                                            <?php if (isset($item['sync_status']) && $item['sync_status'] !== 'synced' && $item['sync_status'] !== 'legacy'): ?>
                                                                <span class="badge bg-warning">⚠️ Sync Issue</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex gap-1">
                                                        <button class="btn btn-sm btn-outline-secondary" 
                                                                onclick="toggleFavorite(<?php echo $item['id']; ?>)"
                                                                title="<?php echo $item['is_favorite'] ? 'Remove from favorites' : 'Add to favorites'; ?>">
                                                            <i class="bi bi-heart<?php echo $item['is_favorite'] ? '-fill text-danger' : ''; ?>"></i>
                                                        </button>
                                                        <div class="dropdown">
                                                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown">
                                                                <i class="bi bi-three-dots-vertical"></i>
                                                            </button>
                                                            <ul class="dropdown-menu">
                                                                <li><a class="dropdown-item" href="#" onclick="editItem(<?php echo $item['id']; ?>)">
                                                                    <i class="bi bi-pencil me-2"></i>Edit
                                                                </a></li>
                                                                <li><a class="dropdown-item" href="#" onclick="viewAnalytics(<?php echo $item['id']; ?>)">
                                                                    <i class="bi bi-graph-up me-2"></i>Analytics
                                                                </a></li>
                                                                <li><a class="dropdown-item" href="#" onclick="createPromotion(<?php echo $item['id']; ?>)">
                                                                    <i class="bi bi-megaphone me-2"></i>Create Promotion
                                                                </a></li>
                                                                <li><hr class="dropdown-divider"></li>
                                                                <li><a class="dropdown-item text-danger" href="#" onclick="removeFromCatalog(<?php echo $item['id']; ?>)">
                                                                    <i class="bi bi-trash me-2"></i>Remove
                                                                </a></li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="card-body p-3">
                                                <!-- Performance Rating -->
                                                <div class="mb-2">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <small class="text-muted">Sales Performance</small>
                                                        <div class="performance-stars">
                                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                <i class="bi bi-star<?php echo $i <= ($item['calculated_performance'] ?? 1) ? '-fill text-warning' : ''; ?>"></i>
                                                            <?php endfor; ?>
                                                        </div>
                                                    </div>
                                                    <small class="text-muted">Based on sales volume (30 days)</small>
                                                </div>

                                                <!-- Priority Badge -->
                                                <div class="mb-2">
                                                    <?php
                                                    $priorityColors = [
                                                        'critical' => 'danger',
                                                        'high' => 'warning',
                                                        'medium' => 'info',
                                                        'low' => 'secondary'
                                                    ];
                                                    $priorityColor = $priorityColors[$item['priority_level']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $priorityColor; ?> me-2">
                                                        <?php echo ucfirst($item['priority_level']); ?> Priority
                                                    </span>
                                                    <!-- ENHANCED: Unified Stock Level Indicators -->
                                                    <?php 
                                                    $totalStock = $item['unified_stock_total'] ?? $item['current_stock'] ?? 0;
                                                    $lowStockThreshold = $item['low_stock_threshold'] ?? 5;
                                                    ?>
                                                    <?php if ($totalStock <= $lowStockThreshold): ?>
                                                        <span class="badge bg-danger">🚨 Low Stock</span>
                                                    <?php elseif ($totalStock <= ($lowStockThreshold * 2)): ?>
                                                        <span class="badge bg-warning text-dark">⚠️ Running Low</span>
                                                    <?php endif; ?>
                                                    
                                                    <!-- Show unified stock breakdown for unified systems -->
                                                    <?php if (($item['system_type'] ?? 'manual_only') === 'unified'): ?>
                                                        <small class="text-muted d-block mt-1">
                                                            📱 Manual: <?php echo $item['manual_stock_qty'] ?? 0; ?> | 
                                                            📡 Nayax: <?php echo $item['nayax_estimated_qty'] ?? 0; ?> = 
                                                            <strong><?php echo $totalStock; ?> total</strong>
                                                        </small>
                                                    <?php endif; ?>
                                                </div>

                                                <!-- Pricing Info -->
                                                <div class="row g-2 mb-2">
                                                    <div class="col-6">
                                                        <small class="text-muted d-block">Price</small>
                                                        <strong class="text-success">$<?php echo number_format($item['custom_price'] ?? 0, 2); ?></strong>
                                                        <?php if (($item['avg_sale_price'] ?? 0) > 0 && abs($item['avg_sale_price'] - $item['custom_price']) > 0.01): ?>
                                                            <br><small class="text-info">Avg: $<?php echo number_format($item['avg_sale_price'], 2); ?></small>
                                                        <?php endif; ?>
                                                        <?php if (abs($item['custom_price'] - $item['master_price']) > 0.01): ?>
                                                            <br><small class="text-muted">Master: $<?php echo number_format($item['master_price'], 2); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="col-6">
                                                        <small class="text-muted d-block">Cost</small>
                                                        <span>$<?php echo number_format($item['custom_cost'] ?? 0, 2); ?></span>
                                                        <?php if (abs($item['custom_cost'] - $item['master_cost']) > 0.01): ?>
                                                            <br><small class="text-muted">Master: $<?php echo number_format($item['master_cost'], 2); ?></small>
                                                        <?php endif; ?>
                                                        <!-- ENHANCED: Unified Stock Display -->
                                                        <?php if ($totalStock > 0): ?>
                                                            <br><small class="text-muted">
                                                                <?php if (($item['system_type'] ?? 'manual_only') === 'unified'): ?>
                                                                    Total Stock: <strong><?php echo $totalStock; ?></strong>
                                                                <?php else: ?>
                                                                    Stock: <?php echo $totalStock; ?>
                                                                <?php endif; ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>

                                                <!-- Margin Info -->
                                                <div class="mb-2">
                                                    <small class="text-muted d-block">Margin</small>
                                                    <div class="d-flex justify-content-between">
                                                        <span class="fw-bold text-primary">
                                                            $<?php echo number_format($item['current_margin'] ?? 0, 2); ?>
                                                        </span>
                                                        <span class="badge bg-<?php echo ($item['margin_percentage'] ?? 0) > 50 ? 'success' : (($item['margin_percentage'] ?? 0) > 20 ? 'warning' : 'danger'); ?>">
                                                            <?php echo number_format($item['margin_percentage'] ?? 0, 1); ?>%
                                                        </span>
                                                    </div>
                                                    <?php if (($item['recent_profit'] ?? 0) > 0): ?>
                                                        <small class="text-success">Actual profit: $<?php echo number_format($item['recent_profit'], 2); ?></small>
                                                    <?php endif; ?>
                                                </div>

                                                <!-- ENHANCED: Unified Performance -->
                                                <div class="mb-2">
                                                    <small class="text-muted d-block">
                                                        <?php if (($item['system_type'] ?? 'manual_only') === 'unified'): ?>
                                                            Cross-System Performance
                                                        <?php else: ?>
                                                            30-Day Performance
                                                        <?php endif; ?>
                                                    </small>
                                                    <div class="row g-1">
                                                        <div class="col-4 text-center">
                                                            <small class="d-block text-muted">
                                                                <?php if (($item['system_type'] ?? 'manual_only') === 'unified'): ?>
                                                                    Weekly
                                                                <?php else: ?>
                                                                    Sales 
                                                                <?php endif; ?>
                                                            </small>
                                                            <strong><?php echo $item['unified_sales_week'] ?? $item['recent_sales'] ?? 0; ?></strong>
                                                        </div>
                                                        <div class="col-4 text-center">
                                                            <small class="d-block text-muted">
                                                                <?php if (($item['system_type'] ?? 'manual_only') === 'unified'): ?>
                                                                    Today
                                                                <?php else: ?>
                                                                    Revenue
                                                                <?php endif; ?>
                                                            </small>
                                                            <strong>
                                                                <?php if (($item['system_type'] ?? 'manual_only') === 'unified'): ?>
                                                                    <?php echo $item['unified_sales_today'] ?? 0; ?>
                                                                <?php else: ?>
                                                                    $<?php echo number_format($item['recent_revenue'] ?? 0, 0); ?>
                                                                <?php endif; ?>
                                                            </strong>
                                                        </div>
                                                        <div class="col-4 text-center">
                                                            <small class="d-block text-muted">
                                                                <?php if (($item['system_type'] ?? 'manual_only') === 'unified'): ?>
                                                                    Avg/Day
                                                                <?php else: ?>
                                                                    Days Active
                                                                <?php endif; ?>
                                                            </small>
                                                            <strong>
                                                                <?php if (($item['system_type'] ?? 'manual_only') === 'unified'): ?>
                                                                    <?php echo number_format(($item['unified_sales_week'] ?? 0) / 7, 1); ?>
                                                                <?php else: ?>
                                                                    <?php echo $item['active_days'] ?? 0; ?>
                                                                <?php endif; ?>
                                                            </strong>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Additional unified metrics -->
                                                    <?php if (($item['system_type'] ?? 'manual_only') === 'unified'): ?>
                                                        <div class="mt-1 text-center">
                                                            <small class="text-info">
                                                                🔗 Combined from manual + Nayax systems
                                                            </small>
                                                        </div>
                                                    <?php elseif (($item['active_days'] ?? 0) > 0): ?>
                                                        <div class="mt-1 text-center">
                                                            <small class="text-info">
                                                                Avg: <?php echo number_format(($item['recent_sales'] ?? 0) / $item['active_days'], 1); ?> sales/day
                                                            </small>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>

                                                <!-- Tags -->
                                                <?php if (!empty($item['tags'])): ?>
                                                    <div class="mb-2">
                                                        <?php foreach (explode(',', $item['tags']) as $itemTag): ?>
                                                            <span class="badge bg-light text-dark me-1"><?php echo htmlspecialchars(trim($itemTag)); ?></span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>

                                                <!-- Notes -->
                                                <?php if (!empty($item['notes'])): ?>
                                                    <div class="mt-2">
                                                        <small class="text-muted d-block">Notes</small>
                                                        <small><?php echo htmlspecialchars(substr($item['notes'], 0, 100)); ?><?php echo strlen($item['notes']) > 100 ? '...' : ''; ?></small>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                                <div class="d-flex justify-content-between align-items-center p-3 bg-light border-top">
                                    <div class="text-muted small">
                                        Page <?php echo $page; ?> of <?php echo $totalPages; ?> 
                                                                                                (<?php echo number_format($totalItems ?? 0); ?> total items)
                                    </div>
                                    <nav aria-label="Catalog pagination">
                                        <ul class="pagination pagination-sm mb-0">
                                            <li class="page-item <?php echo $page === 1 ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="?page=1&<?php echo http_build_query(array_filter($_GET, function($k) { return $k !== 'page'; }, ARRAY_FILTER_USE_KEY)); ?>">
                                                    <span aria-hidden="true">&laquo;&laquo;</span>
                                                </a>
                                            </li>
                                            <li class="page-item <?php echo $page === 1 ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query(array_filter($_GET, function($k) { return $k !== 'page'; }, ARRAY_FILTER_USE_KEY)); ?>">
                                                    <span aria-hidden="true">&laquo;</span>
                                                </a>
                                            </li>
                                            <?php
                                            $window = 2;
                                            $start = max(1, $page - $window);
                                            $end = min($totalPages, $page + $window);
                                            
                                            if ($start > 1) {
                                                echo '<li class="page-item"><span class="page-link">...</span></li>';
                                            }
                                            
                                            for ($i = $start; $i <= $end; $i++) {
                                                $active = $page === $i ? 'active' : '';
                                                $queryParams = array_filter($_GET, function($k) { return $k !== 'page'; }, ARRAY_FILTER_USE_KEY);
                                                $queryParams['page'] = $i;
                                                echo '<li class="page-item ' . $active . '">';
                                                echo '<a class="page-link" href="?' . http_build_query($queryParams) . '">' . $i . '</a>';
                                                echo '</li>';
                                            }
                                            
                                            if ($end < $totalPages) {
                                                echo '<li class="page-item"><span class="page-link">...</span></li>';
                                            }
                                            ?>
                                            <li class="page-item <?php echo $page === $totalPages ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query(array_filter($_GET, function($k) { return $k !== 'page'; }, ARRAY_FILTER_USE_KEY)); ?>">
                                                    <span aria-hidden="true">&raquo;</span>
                                                </a>
                                            </li>
                                            <li class="page-item <?php echo $page === $totalPages ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $totalPages; ?>&<?php echo http_build_query(array_filter($_GET, function($k) { return $k !== 'page'; }, ARRAY_FILTER_USE_KEY)); ?>">
                                                    <span aria-hidden="true">&raquo;&raquo;</span>
                                                </a>
                                            </li>
                                        </ul>
                                    </nav>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Analytics Modal -->
<div class="modal fade" id="analyticsModal" tabindex="-1" aria-labelledby="analyticsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="analyticsModalLabel">
                    <i class="bi bi-graph-up me-2"></i>Analytics Dashboard
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center py-4">
                    <i class="bi bi-graph-up display-1 text-muted"></i>
                    <h4 class="mt-3">Analytics Dashboard</h4>
                    <p class="text-muted">Advanced analytics features will be available here.</p>
                    <p class="small text-muted">Coming soon: Performance charts, trend analysis, and detailed metrics.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Promotions Modal -->
<div class="modal fade" id="promotionsModal" tabindex="-1" aria-labelledby="promotionsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="promotionsModalLabel">
                    <i class="bi bi-megaphone me-2"></i>Promotions Manager
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center py-4">
                    <i class="bi bi-megaphone display-1 text-muted"></i>
                    <h4 class="mt-3">Promotions Manager</h4>
                    <p class="text-muted">Create and manage promotional campaigns for your catalog items.</p>
                    <p class="small text-muted">Coming soon: Discount campaigns, bundle deals, and seasonal promotions.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Combos Modal -->
<div class="modal fade" id="combosModal" tabindex="-1" aria-labelledby="combosModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="combosModalLabel">
                    <i class="bi bi-collection me-2"></i>Combo Deals Manager
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center py-4">
                    <i class="bi bi-collection display-1 text-muted"></i>
                    <h4 class="mt-3">Combo Deals Manager</h4>
                    <p class="text-muted">Create combo deals and bundle packages from your catalog items.</p>
                    <p class="small text-muted">Coming soon: Bundle creation, combo pricing, and package management.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
.bg-gradient-primary {
    background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
}

.catalog-item-card {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    border: 1px solid #e9ecef;
    overflow: visible;
    position: relative;
}

.catalog-item-card:hover {
    /* Removed transform and box-shadow to prevent card from moving up and covering dropdown */
}

.performance-stars {
    font-size: 0.8rem;
}

.badge {
    font-size: 0.7rem;
}

.card-header {
    border-bottom: 1px solid #e9ecef;
}

.dropdown-menu {
    border: none;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    z-index: 1055 !important;
    position: absolute !important;
}

.dropdown {
    position: relative;
    z-index: 1000;
}

.dropdown.show {
    z-index: 1056;
}

.form-select-sm {
    font-size: 0.875rem;
}

.btn-group .btn {
    border-radius: 0.375rem;
}

.btn-group .btn:not(:last-child) {
    margin-right: 0.25rem;
}

/* Custom darker colors for catalog items */
.catalog-item-card .text-success {
    color: #2e7d32 !important; /* Darker green for price and actual profit */
}

.catalog-item-card .text-info {
    color: #01579b !important; /* Darker blue for avg sale price and avg sales per day */
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap modals to prevent errors
    try {
        // Wait a bit for DOM to be fully ready
        setTimeout(() => {
            const modalElements = document.querySelectorAll('.modal');
            modalElements.forEach(modalEl => {
                if (modalEl && !modalEl._modal && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    // Initialize with explicit options to prevent backdrop errors
                    modalEl._modal = new bootstrap.Modal(modalEl, {
                        backdrop: true,
                        keyboard: true,
                        focus: true
                    });
                }
            });
        }, 100);
    } catch (error) {
        console.warn('Modal initialization warning:', error);
    }

    // Form handling
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    function submitForm(form) {
        const formData = new FormData(form);
        const params = new URLSearchParams(formData);
        window.location.href = '?' + params.toString();
    }

    // Search functionality
    const searchInput = document.getElementById('searchItems');
    const searchForm = document.getElementById('searchForm');
    const filterForm = document.getElementById('filterForm');

    searchInput?.addEventListener('input', debounce(() => {
        submitForm(searchForm);
    }, 500));

    filterForm?.addEventListener('change', () => submitForm(filterForm));
    searchForm?.addEventListener('submit', (e) => {
        e.preventDefault();
        submitForm(searchForm);
    });

    document.getElementById('clearSearch')?.addEventListener('click', function() {
        searchInput.value = '';
        submitForm(searchForm);
    });

    // Global functions
    window.clearAllFilters = function() {
        window.location.href = 'my-catalog.php';
    };

    window.toggleFavorite = function(itemId) {
        fetch('catalog_actions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': '<?php echo $csrf_token; ?>'
            },
            body: JSON.stringify({
                action: 'toggle_favorite',
                item_id: itemId,
                csrf_token: '<?php echo $csrf_token; ?>'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error updating favorite status: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error updating favorite status. Please try again.');
        });
    };

    window.editItem = function(itemId) {
        // Placeholder for edit functionality - could open a modal in the future
        alert('Edit functionality would open a modal for item ID: ' + itemId);
    };

    window.viewAnalytics = function(itemId) {
        // Placeholder for analytics functionality
        alert('Analytics view would open for item ID: ' + itemId);
    };

    window.createPromotion = function(itemId) {
        // Placeholder for promotion creation
        alert('Promotion creation would open for item ID: ' + itemId);
    };

    window.removeFromCatalog = function(itemId) {
        if (!confirm('Are you sure you want to remove this item from your catalog?')) {
            return;
        }

        fetch('catalog_actions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': '<?php echo $csrf_token; ?>'
            },
            body: JSON.stringify({
                action: 'remove_item',
                item_id: itemId,
                csrf_token: '<?php echo $csrf_token; ?>'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error removing item: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error removing item. Please try again.');
        });
    };
});
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 