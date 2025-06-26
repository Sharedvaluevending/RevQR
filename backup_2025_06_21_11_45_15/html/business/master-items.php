<?php
// Enable error reporting for development only
if (defined('DEVELOPMENT') && DEVELOPMENT) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/csrf.php';

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

if (!$business) {
    header('Location: /business/dashboard.php?error=no_business');
    exit;
}

// Get filter parameters with validation
$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
$priceRange = trim($_GET['price_range'] ?? '');
$sort = in_array($_GET['sort'] ?? '', ['name', 'price_asc', 'price_desc', 'margin_desc', 'sales_desc', 'revenue_desc']) ? $_GET['sort'] : 'name';
$page = max(1, intval($_GET['page'] ?? 1));
$itemsPerPage = max(10, min(100, intval($_GET['per_page'] ?? 25)));

// Build the base query with sales integration
$query = "
    SELECT 
        mi.id,
        mi.name as item_name,
        mi.category,
        mi.type as item_type,
        mi.brand,
        mi.suggested_price as retail_price,
        mi.suggested_cost as cost_price,
        mi.popularity,
        mi.shelf_life,
        mi.is_seasonal,
        mi.is_imported,
        mi.is_healthy,
        mi.status,
        mi.created_at,
        (mi.suggested_price - mi.suggested_cost) as suggested_margin,
        -- Real sales data from the last 30 days
        COALESCE(SUM(s.sale_price * s.quantity), 0) as actual_revenue,
        COALESCE(SUM(s.quantity), 0) as units_sold,
        COALESCE(AVG(s.sale_price), mi.suggested_price) as avg_sale_price,
        COALESCE(SUM((s.sale_price - mi.suggested_cost) * s.quantity), 0) as actual_profit,
        COALESCE(COUNT(DISTINCT DATE(s.sale_time)), 0) as active_days,
        -- Current inventory
        COALESCE(SUM(i.inventory), 0) as current_stock,
        -- Performance indicators
        CASE 
            WHEN SUM(s.quantity) > 100 THEN 'high'
            WHEN SUM(s.quantity) > 50 THEN 'medium'
            WHEN SUM(s.quantity) > 0 THEN 'low'
            ELSE 'none'
        END as sales_performance,
        CASE 
            WHEN (mi.suggested_price - mi.suggested_cost) > 1.00 THEN 1 
            ELSE 0 
        END as high_margin
    FROM master_items mi
    LEFT JOIN item_mapping im ON mi.id = im.master_item_id
    LEFT JOIN items i ON im.item_id = i.id AND i.machine_id IN (
        SELECT id FROM machines WHERE business_id = ?
    )
    LEFT JOIN sales s ON i.id = s.item_id 
        AND s.business_id = ? 
        AND s.sale_time >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    WHERE 1=1
";
$params = [$business_id, $business_id];

// Add search condition with better performance
if (!empty($search)) {
    $query .= " AND (mi.name LIKE ? OR mi.brand LIKE ? OR mi.category LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// Add category filter
if (!empty($category)) {
    $query .= " AND mi.category = ?";
    $params[] = $category;
}

// Add price range filter with validation
if (!empty($priceRange)) {
    if ($priceRange === '3+') {
        $query .= " AND mi.suggested_price >= ?";
        $params[] = 3.00;
    } else {
        $rangeParts = explode('-', $priceRange);
        if (count($rangeParts) === 2 && is_numeric($rangeParts[0]) && is_numeric($rangeParts[1])) {
            $query .= " AND mi.suggested_price BETWEEN ? AND ?";
            $params[] = floatval($rangeParts[0]);
            $params[] = floatval($rangeParts[1]);
        }
    }
}

// Add GROUP BY clause for aggregated sales data
$query .= " GROUP BY mi.id, mi.name, mi.category, mi.type, mi.brand, mi.suggested_price, mi.suggested_cost, mi.popularity, mi.shelf_life, mi.is_seasonal, mi.is_imported, mi.is_healthy, mi.status, mi.created_at";

// Add sorting with validation
switch ($sort) {
    case 'price_asc':
        $query .= " ORDER BY mi.suggested_price ASC, mi.name ASC";
        break;
    case 'price_desc':
        $query .= " ORDER BY mi.suggested_price DESC, mi.name ASC";
        break;
    case 'margin_desc':
        $query .= " ORDER BY (mi.suggested_price - mi.suggested_cost) DESC, mi.name ASC";
        break;
    case 'sales_desc':
        $query .= " ORDER BY units_sold DESC, mi.name ASC";
        break;
    case 'revenue_desc':
        $query .= " ORDER BY actual_revenue DESC, mi.name ASC";
        break;
    default:
        $query .= " ORDER BY mi.name ASC";
}

// Get total count for pagination - simplified count query
$countQuery = "
    SELECT COUNT(DISTINCT mi.id)
    FROM master_items mi
    WHERE 1=1
";
$countParams = [];

// Add search condition to count query
if (!empty($search)) {
    $countQuery .= " AND (mi.name LIKE ? OR mi.brand LIKE ? OR mi.category LIKE ?)";
    $searchTerm = "%$search%";
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
}

// Add category filter to count query
if (!empty($category)) {
    $countQuery .= " AND mi.category = ?";
    $countParams[] = $category;
}

// Add price range filter to count query
if (!empty($priceRange)) {
    if ($priceRange === '3+') {
        $countQuery .= " AND mi.suggested_price >= ?";
        $countParams[] = 3.00;
    } else {
        $rangeParts = explode('-', $priceRange);
        if (count($rangeParts) === 2 && is_numeric($rangeParts[0]) && is_numeric($rangeParts[1])) {
            $countQuery .= " AND mi.suggested_price BETWEEN ? AND ?";
            $countParams[] = floatval($rangeParts[0]);
            $countParams[] = floatval($rangeParts[1]);
        }
    }
}

try {
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($countParams);
    $totalItems = $countStmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Error getting item count: " . $e->getMessage());
    $totalItems = 0;
}

$totalPages = ceil($totalItems / $itemsPerPage);

// Add pagination to main query
$query .= " LIMIT ? OFFSET ?";
$params[] = $itemsPerPage;
$params[] = ($page - 1) * $itemsPerPage;

// Execute the main query
try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching items: " . $e->getMessage());
    $items = [];
    $message = "Error loading items. Please try again.";
    $message_type = "danger";
}

// Get all categories for the filter dropdown
try {
    $categories_stmt = $pdo->query("SELECT DISTINCT category FROM master_items WHERE category IS NOT NULL ORDER BY category ASC");
    $categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching categories: " . $e->getMessage());
    $categories = [];
}

// Get categories that have items in current result set
$currentPageCategories = [];
foreach ($items as $item) {
    $cat = $item['category'] ?: 'Other';
    if (!in_array($cat, $currentPageCategories)) {
        $currentPageCategories[] = $cat;
    }
}

// Calculate additional statistics
$totalCategoriesInDB = count($categories);
$categoriesOnCurrentPage = count($currentPageCategories);
$startItem = ($page - 1) * $itemsPerPage + 1;
$endItem = min($page * $itemsPerPage, $totalItems);

// Calculate sales statistics from current page items
$totalSalesCurrentPage = array_sum(array_column($items, 'units_sold'));
$totalRevenueCurrentPage = array_sum(array_column($items, 'actual_revenue'));
$itemsWithSales = count(array_filter($items, function($item) { return $item['units_sold'] > 0; }));
$lowStockItems = count(array_filter($items, function($item) { return $item['current_stock'] <= 5; }));

require_once __DIR__ . '/../core/includes/header.php';
?>

<style>
/* Custom table styling to fix visibility issues */
#itemsTable {
    background: rgba(255, 255, 255, 0.15) !important;
    backdrop-filter: blur(20px) !important;
    border-radius: 12px !important;
    overflow: hidden !important;
}

#itemsTable thead th {
    background: rgba(30, 60, 114, 0.6) !important;
    color: #ffffff !important;
    font-weight: 600 !important;
    border-bottom: 2px solid rgba(255, 255, 255, 0.3) !important;
    padding: 1rem 0.75rem !important;
}

#itemsTable tbody td {
    background: rgba(255, 255, 255, 0.12) !important;
    color: rgba(255, 255, 255, 0.95) !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.15) !important;
    padding: 0.875rem 0.75rem !important;
}

#itemsTable tbody tr:hover td {
    background: rgba(255, 255, 255, 0.18) !important;
    color: #ffffff !important;
}

/* Fix form controls inside table */
#itemsTable .form-control {
    background: rgba(255, 255, 255, 0.9) !important;
    color: #333333 !important;
    border: 1px solid rgba(255, 255, 255, 0.3) !important;
}

#itemsTable .form-control:focus {
    background: #ffffff !important;
    color: #333333 !important;
    border-color: #64b5f6 !important;
    box-shadow: 0 0 0 0.25rem rgba(100, 181, 246, 0.25) !important;
}

/* Input group text styling */
#itemsTable .input-group-text {
    background: rgba(255, 255, 255, 0.9) !important;
    color: #333333 !important;
    border: 1px solid rgba(255, 255, 255, 0.3) !important;
}

/* Badge styling improvements */
#itemsTable .badge {
    font-weight: 500 !important;
    padding: 0.375rem 0.5rem !important;
}

/* Checkbox styling */
#itemsTable .form-check-input {
    background: rgba(255, 255, 255, 0.9) !important;
    border: 1px solid rgba(255, 255, 255, 0.5) !important;
}

#itemsTable .form-check-input:checked {
    background: #1976d2 !important;
    border-color: #1976d2 !important;
}

/* Button styling inside table */
#itemsTable .btn-outline-secondary {
    background: rgba(255, 255, 255, 0.1) !important;
    border-color: rgba(255, 255, 255, 0.3) !important;
    color: rgba(255, 255, 255, 0.9) !important;
}

#itemsTable .btn-outline-secondary:hover {
    background: rgba(255, 255, 255, 0.2) !important;
    border-color: rgba(255, 255, 255, 0.5) !important;
    color: #ffffff !important;
}

/* Enhanced styles for improved UX */
.sticky-top {
    position: sticky;
    top: 0;
    z-index: 10;
}

.card {
    border: none;
    border-radius: 0.5rem;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.table th {
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #dee2e6;
}

.badge {
    font-size: 0.75rem;
    padding: 0.25em 0.5em;
}

.btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
}

.form-control:focus, .form-select:focus {
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

.pagination .page-link {
    padding: 0.375rem 0.75rem;
}

.input-group-sm > .form-control,
.input-group-sm > .input-group-text {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

/* Loading state */
.loading {
    opacity: 0.6;
    pointer-events: none;
}

/* Bulk actions bar */
#bulkActionsBar {
    position: sticky;
    top: 0;
    z-index: 20;
}

/* Custom darker colors for master items table */
.table .text-success {
    color: #2e7d32 !important; /* Darker green for actual profit and revenue */
}

.table .text-primary {
    color: #1565c0 !important; /* Darker blue for sales numbers */
}

.table .text-info {
    color: #01579b !important; /* Darker blue for revenue totals */
}
</style>

<div class="container-fluid px-0">
    <!-- Header Section with Enhanced Stats -->
    <div class="bg-white border-bottom">
        <div class="container py-4">
            <div class="row align-items-center">
                <div class="col">
                    <h1 class="h3 mb-1">Master Items Catalog</h1>
                    <p class="text-muted mb-0">
                        <?php if (!empty($search) || !empty($category) || !empty($priceRange)): ?>
                            Filtered results • <a href="master-items.php" class="text-decoration-none">Clear filters</a>
                        <?php else: ?>
                            Complete product inventory management with real-time sales data
                        <?php endif; ?>
                    </p>
                </div>
                <div class="col-auto">
                    <div class="row g-3 text-center">
                        <div class="col">
                            <div class="card border-0 bg-light">
                                <div class="card-body py-2 px-3">
                                    <h4 class="h5 mb-0 text-primary"><?php echo number_format($totalItems); ?></h4>
                                    <small class="text-muted">Total Items</small>
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="card border-0 bg-light">
                                <div class="card-body py-2 px-3">
                                    <h4 class="h5 mb-0 text-success"><?php echo number_format($totalSalesCurrentPage); ?></h4>
                                    <small class="text-muted">Sales (30d)</small>
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="card border-0 bg-light">
                                <div class="card-body py-2 px-3">
                                    <h4 class="h5 mb-0 text-info">$<?php echo number_format($totalRevenueCurrentPage, 0); ?></h4>
                                    <small class="text-muted">Revenue (30d)</small>
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="card border-0 bg-light">
                                <div class="card-body py-2 px-3">
                                    <h4 class="h5 mb-0 text-warning"><?php echo $itemsWithSales; ?>/<?php echo count($items); ?></h4>
                                    <small class="text-muted">Selling</small>
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="card border-0 bg-light">
                                <div class="card-body py-2 px-3">
                                    <h4 class="h5 mb-0 text-danger"><?php echo $lowStockItems; ?></h4>
                                    <small class="text-muted">Low Stock</small>
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
            <div class="col-12">
                <div class="card shadow-sm">
                    <!-- Enhanced Header with Search and Actions -->
                    <div class="card-header bg-white py-3">
                        <div class="row align-items-center g-3">
                            <div class="col-md-6">
                                <h5 class="mb-0">Product Catalog</h5>
                                <small class="text-muted">
                                    <?php if ($totalItems > 0): ?>
                                        Showing <?php echo $startItem; ?>-<?php echo $endItem; ?> of <?php echo number_format($totalItems); ?> items
                                    <?php else: ?>
                                        No items found
                                    <?php endif; ?>
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
                                                   placeholder="Search products..." 
                                                   value="<?php echo htmlspecialchars($search); ?>"
                                                   aria-label="Search products"
                                                   autocomplete="off">
                                            <?php if (!empty($search)): ?>
                                                <button class="btn btn-outline-secondary" 
                                                        type="button" 
                                                        id="clearSearch"
                                                        aria-label="Clear search">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </form>
                                    <button type="button" class="btn btn-primary" id="saveAllChanges">
                                        <i class="bi bi-save me-2"></i>Save Changes
                                        <span class="badge bg-secondary ms-2" id="modifiedCount" style="display: none;">0</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Enhanced Filters -->
                    <div class="bg-light p-3 border-bottom">
                        <form id="filterForm" class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label for="categoryFilter" class="form-label small text-muted mb-1">Category</label>
                                <select class="form-select" 
                                        id="categoryFilter" 
                                        name="category" 
                                        autocomplete="off">
                                    <option value="">All Categories (<?php echo $totalCategoriesInDB; ?>)</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat['category']); ?>"
                                                <?php echo $category === $cat['category'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['category']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="priceRangeFilter" class="form-label small text-muted mb-1">Price Range</label>
                                <select class="form-select" 
                                        id="priceRangeFilter" 
                                        name="price_range" 
                                        autocomplete="off">
                                    <option value="">All Prices</option>
                                    <option value="0-1" <?php echo $priceRange === '0-1' ? 'selected' : ''; ?>>Under $1.00</option>
                                    <option value="1-2" <?php echo $priceRange === '1-2' ? 'selected' : ''; ?>>$1.00 - $2.00</option>
                                    <option value="2-3" <?php echo $priceRange === '2-3' ? 'selected' : ''; ?>>$2.00 - $3.00</option>
                                    <option value="3+" <?php echo $priceRange === '3+' ? 'selected' : ''; ?>>Over $3.00</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="sortFilter" class="form-label small text-muted mb-1">Sort By</label>
                                <select class="form-select" 
                                        id="sortFilter" 
                                        name="sort" 
                                        autocomplete="off">
                                    <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Name A-Z</option>
                                    <option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Price: Low to High</option>
                                    <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Price: High to Low</option>
                                    <option value="margin_desc" <?php echo $sort === 'margin_desc' ? 'selected' : ''; ?>>Highest Margin</option>
                                    <option value="sales_desc" <?php echo $sort === 'sales_desc' ? 'selected' : ''; ?>>Sales: High to Low</option>
                                    <option value="revenue_desc" <?php echo $sort === 'revenue_desc' ? 'selected' : ''; ?>>Revenue: High to Low</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="itemsPerPage" class="form-label small text-muted mb-1">Items per page</label>
                                <select class="form-select" 
                                        id="itemsPerPage" 
                                        name="per_page" 
                                        autocomplete="off">
                                    <option value="10" <?php echo $itemsPerPage === 10 ? 'selected' : ''; ?>>10</option>
                                    <option value="25" <?php echo $itemsPerPage === 25 ? 'selected' : ''; ?>>25</option>
                                    <option value="50" <?php echo $itemsPerPage === 50 ? 'selected' : ''; ?>>50</option>
                                    <option value="100" <?php echo $itemsPerPage === 100 ? 'selected' : ''; ?>>100</option>
                                </select>
                            </div>
                        </form>
                    </div>

                    <div class="card-body p-0">
                        <?php if (empty($items)): ?>
                            <!-- Empty State -->
                            <div class="text-center py-5">
                                <i class="bi bi-inbox display-1 text-muted"></i>
                                <h4 class="mt-3">No items found</h4>
                                <p class="text-muted">
                                    <?php if (!empty($search) || !empty($category) || !empty($priceRange)): ?>
                                        Try adjusting your filters or <a href="master-items.php">clear all filters</a>
                                    <?php else: ?>
                                        No items are available in the catalog
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <!-- Items Table -->
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0" id="itemsTable">
                                    <thead class="bg-light sticky-top">
                                        <tr>
                                            <th scope="col" class="ps-4">
                                                <div class="d-flex align-items-center gap-2">
                                                    <input type="checkbox" id="selectAll" class="form-check-input">
                                                    <span>Item</span>
                                                </div>
                                            </th>
                                            <th scope="col">Category</th>
                                            <th scope="col">Retail Price</th>
                                            <th scope="col">Cost Price</th>
                                            <th scope="col">Margin</th>
                                            <th scope="col">Sales (30d)</th>
                                            <th scope="col">Revenue (30d)</th>
                                            <th scope="col">Stock</th>
                                            <th scope="col">Status</th>
                                            <th scope="col">Flags</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($items as $item): ?>
                                            <tr data-item-id="<?php echo $item['id']; ?>" class="border-bottom">
                                                <td class="ps-4">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <input type="checkbox" class="form-check-input item-checkbox" data-item-id="<?php echo $item['id']; ?>">
                                                        <div>
                                                            <div class="fw-medium"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                                            <?php if (!empty($item['brand'])): ?>
                                                                <small class="text-muted"><?php echo htmlspecialchars($item['brand']); ?></small>
                                                            <?php endif; ?>
                                                            <!-- Sales performance indicator -->
                                                            <?php if ($item['sales_performance'] !== 'none'): ?>
                                                                <div class="mt-1">
                                                                    <span class="badge bg-<?php 
                                                                        echo $item['sales_performance'] === 'high' ? 'success' : 
                                                                            ($item['sales_performance'] === 'medium' ? 'warning' : 'info'); 
                                                                    ?> badge-sm">
                                                                        <?php echo ucfirst($item['sales_performance']); ?> Seller
                                                                    </span>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($item['category'] ?: 'Other'); ?></span>
                                                </td>
                                                <td>
                                                    <div class="input-group input-group-sm">
                                                        <span class="input-group-text">$</span>
                                                        <input type="number" 
                                                               class="form-control retail-price" 
                                                               id="retail_price_<?php echo $item['id']; ?>"
                                                               name="retail_price[<?php echo $item['id']; ?>]"
                                                               value="<?php echo number_format($item['retail_price'], 2); ?>" 
                                                               step="0.01" 
                                                               min="0"
                                                               max="999.99"
                                                               autocomplete="off">
                                                    </div>
                                                    <?php if ($item['avg_sale_price'] != $item['retail_price'] && $item['units_sold'] > 0): ?>
                                                        <small class="text-muted">Avg: $<?php echo number_format($item['avg_sale_price'], 2); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="input-group input-group-sm">
                                                        <span class="input-group-text">$</span>
                                                        <input type="number" 
                                                               class="form-control cost-price" 
                                                               id="cost_price_<?php echo $item['id']; ?>"
                                                               name="cost_price[<?php echo $item['id']; ?>]"
                                                               value="<?php echo number_format($item['cost_price'], 2); ?>" 
                                                               step="0.01" 
                                                               min="0"
                                                               max="999.99"
                                                               autocomplete="off">
                                                    </div>
                                                </td>
                                                <td class="margin-display">
                                                    <?php 
                                                    $margin = $item['retail_price'] - $item['cost_price'];
                                                    $marginPercent = $item['cost_price'] > 0 ? ($margin / $item['cost_price'] * 100) : 0;
                                                    $badgeClass = $margin > 1.00 ? 'bg-success' : 'bg-light text-dark';
                                                    ?>
                                                    <span class="badge <?php echo $badgeClass; ?>">
                                                        $<?php echo number_format($margin, 2); ?>
                                                        (<?php echo number_format($marginPercent, 1); ?>%)
                                                    </span>
                                                    <?php if ($item['actual_profit'] > 0): ?>
                                                        <br><small class="text-success">Actual: $<?php echo number_format($item['actual_profit'], 2); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <div class="fw-bold text-primary"><?php echo number_format($item['units_sold']); ?></div>
                                                    <?php if ($item['active_days'] > 0): ?>
                                                        <small class="text-muted"><?php echo number_format($item['units_sold'] / $item['active_days'], 1); ?>/day</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <div class="fw-bold text-success">$<?php echo number_format($item['actual_revenue'], 2); ?></div>
                                                    <?php if ($item['active_days'] > 0): ?>
                                                        <small class="text-muted">$<?php echo number_format($item['actual_revenue'] / $item['active_days'], 2); ?>/day</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php 
                                                    $stockClass = $item['current_stock'] <= 5 ? 'text-danger' : ($item['current_stock'] <= 20 ? 'text-warning' : 'text-success');
                                                    ?>
                                                    <span class="fw-bold <?php echo $stockClass; ?>"><?php echo $item['current_stock']; ?></span>
                                                    <br><small class="text-muted">units</small>
                                                </td>
                                                <td>
                                                    <select class="form-select form-select-sm item-status" 
                                                            id="status_<?php echo $item['id']; ?>"
                                                            name="status[<?php echo $item['id']; ?>]"
                                                            autocomplete="off">
                                                        <option value="active" <?php echo $item['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                                        <option value="inactive" <?php echo $item['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <div class="d-flex gap-1 align-items-center">
                                                        <?php if ($margin > 1.00): ?>
                                                            <span class="badge bg-success" data-flag-badge="high_margin" title="High Margin">HM</span>
                                                        <?php endif; ?>
                                                        <?php if ($item['is_seasonal']): ?>
                                                            <span class="badge bg-info" data-flag-badge="is_seasonal" title="Seasonal">S</span>
                                                        <?php endif; ?>
                                                        <?php if ($item['is_imported']): ?>
                                                            <span class="badge bg-warning text-dark" data-flag-badge="is_imported" title="Imported">I</span>
                                                        <?php endif; ?>
                                                        <?php if ($item['is_healthy']): ?>
                                                            <span class="badge bg-primary" data-flag-badge="is_healthy" title="Healthy">H</span>
                                                        <?php endif; ?>
                                                        <button type="button" 
                                                                class="btn btn-sm btn-outline-secondary" 
                                                                data-bs-toggle="popover" 
                                                                data-bs-placement="left" 
                                                                data-bs-html="true"
                                                                data-bs-content='
                                                                    <div class="p-2">
                                                                        <div class="form-check form-switch">
                                                                            <input class="form-check-input item-flag" type="checkbox" 
                                                                                   id="is_imported_<?php echo $item['id']; ?>"
                                                                                   name="is_imported[<?php echo $item['id']; ?>]"
                                                                                   data-flag="is_imported"
                                                                                   <?php echo $item['is_imported'] ? 'checked' : ''; ?>>
                                                                            <label class="form-check-label" for="is_imported_<?php echo $item['id']; ?>">Imported</label>
                                                                        </div>
                                                                        <div class="form-check form-switch">
                                                                            <input class="form-check-input item-flag" type="checkbox" 
                                                                                   id="is_healthy_<?php echo $item['id']; ?>"
                                                                                   name="is_healthy[<?php echo $item['id']; ?>]"
                                                                                   data-flag="is_healthy"
                                                                                   <?php echo $item['is_healthy'] ? 'checked' : ''; ?>>
                                                                            <label class="form-check-label" for="is_healthy_<?php echo $item['id']; ?>">Healthy</label>
                                                                        </div>
                                                                    </div>'>
                                                            <i class="bi bi-gear-fill"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Enhanced Pagination -->
                            <?php if ($totalPages > 1): ?>
                                <div class="d-flex justify-content-between align-items-center p-3 bg-light border-top">
                                    <div class="text-muted small">
                                        Page <?php echo $page; ?> of <?php echo $totalPages; ?> 
                                        (<?php echo number_format($totalItems); ?> total items)
                                    </div>
                                    <nav aria-label="Items pagination">
                                        <ul class="pagination pagination-sm mb-0">
                                            <li class="page-item <?php echo $page === 1 ? 'disabled' : ''; ?>">
                                                <a class="page-link" 
                                                   href="?page=1&per_page=<?php echo $itemsPerPage; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&price_range=<?php echo urlencode($priceRange); ?>&sort=<?php echo urlencode($sort); ?>" 
                                                   aria-label="First page">
                                                    <span aria-hidden="true">&laquo;&laquo;</span>
                                                </a>
                                            </li>
                                            <li class="page-item <?php echo $page === 1 ? 'disabled' : ''; ?>">
                                                <a class="page-link" 
                                                   href="?page=<?php echo $page - 1; ?>&per_page=<?php echo $itemsPerPage; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&price_range=<?php echo urlencode($priceRange); ?>&sort=<?php echo urlencode($sort); ?>" 
                                                   aria-label="Previous page">
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
                                                echo '<li class="page-item ' . $active . '">';
                                                echo '<a class="page-link" href="?page=' . $i . '&per_page=' . $itemsPerPage . '&search=' . urlencode($search) . '&category=' . urlencode($category) . '&price_range=' . urlencode($priceRange) . '&sort=' . urlencode($sort) . '">' . $i . '</a>';
                                                echo '</li>';
                                            }
                                            
                                            if ($end < $totalPages) {
                                                echo '<li class="page-item"><span class="page-link">...</span></li>';
                                            }
                                            ?>
                                            <li class="page-item <?php echo $page === $totalPages ? 'disabled' : ''; ?>">
                                                <a class="page-link" 
                                                   href="?page=<?php echo $page + 1; ?>&per_page=<?php echo $itemsPerPage; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&price_range=<?php echo urlencode($priceRange); ?>&sort=<?php echo urlencode($sort); ?>"
                                                   aria-label="Next page">
                                                    <span aria-hidden="true">&raquo;</span>
                                                </a>
                                            </li>
                                            <li class="page-item <?php echo $page === $totalPages ? 'disabled' : ''; ?>">
                                                <a class="page-link" 
                                                   href="?page=<?php echo $totalPages; ?>&per_page=<?php echo $itemsPerPage; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&price_range=<?php echo urlencode($priceRange); ?>&sort=<?php echo urlencode($sort); ?>"
                                                   aria-label="Last page">
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

<!-- Include the existing CSS and JavaScript from the original file -->
<style>
/* Enhanced styles for improved UX */
.sticky-top {
    position: sticky;
    top: 0;
    z-index: 10;
}

.card {
    border: none;
    border-radius: 0.5rem;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.table th {
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #dee2e6;
}

.badge {
    font-size: 0.75rem;
    padding: 0.25em 0.5em;
}

.btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
}

.form-control:focus, .form-select:focus {
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

.pagination .page-link {
    padding: 0.375rem 0.75rem;
}

.input-group-sm > .form-control,
.input-group-sm > .input-group-text {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

/* Loading state */
.loading {
    opacity: 0.6;
    pointer-events: none;
}

/* Bulk actions bar */
#bulkActionsBar {
    position: sticky;
    top: 0;
    z-index: 20;
}

/* Custom darker colors for master items table */
.table .text-success {
    color: #2e7d32 !important; /* Darker green for actual profit and revenue */
}

.table .text-primary {
    color: #1565c0 !important; /* Darker blue for sales numbers */
}

.table .text-info {
    color: #01579b !important; /* Darker blue for revenue totals */
}
</style>

<script>
// Enhanced JavaScript functionality
document.addEventListener('DOMContentLoaded', function() {
    // Track modified items
    const modifiedItems = new Set();
    const modifiedCount = document.getElementById('modifiedCount');

    // Initialize popovers
    const popovers = document.querySelectorAll('[data-bs-toggle="popover"]');
    popovers.forEach(popover => {
        new bootstrap.Popover(popover, {
            trigger: 'click',
            html: true,
            container: 'body',
            sanitize: false
        });
    });

    // Bulk selection functionality
    const selectAllCheckbox = document.getElementById('selectAll');
    const itemCheckboxes = document.querySelectorAll('.item-checkbox');

    selectAllCheckbox?.addEventListener('change', function() {
        itemCheckboxes.forEach(cb => cb.checked = this.checked);
        updateBulkActions();
    });

    itemCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            console.log('Checkbox changed:', this.dataset.itemId, this.checked);
            updateBulkActions();
        });
    });

    function updateBulkActions() {
        const selectedItems = document.querySelectorAll('.item-checkbox:checked');
        const totalCheckboxes = document.querySelectorAll('.item-checkbox');
        console.log('updateBulkActions: selected =', selectedItems.length, 'total =', totalCheckboxes.length);
        
        // Update select all state
        if (selectedItems.length === 0) {
            selectAllCheckbox.indeterminate = false;
            selectAllCheckbox.checked = false;
        } else if (selectedItems.length === totalCheckboxes.length) {
            selectAllCheckbox.indeterminate = false;
            selectAllCheckbox.checked = true;
        } else {
            selectAllCheckbox.indeterminate = true;
        }
        
        // Show/hide bulk actions
        showBulkActions(selectedItems.length);
    }

    function showBulkActions(count) {
        console.log('showBulkActions called with count:', count);
        let bulkActionsBar = document.getElementById('bulkActionsBar');
        if (count > 0) {
            if (!bulkActionsBar) {
                console.log('Creating new bulk actions bar');
                bulkActionsBar = document.createElement('div');
                bulkActionsBar.id = 'bulkActionsBar';
                bulkActionsBar.className = 'alert alert-info d-flex justify-content-between align-items-center mx-4 mt-4';
                bulkActionsBar.innerHTML = `
                    <span><strong>${count}</strong> item(s) selected</span>
                                                         <div class="btn-group">
                                         <button type="button" class="btn btn-sm btn-outline-primary" onclick="bulkUpdateStatus('active')">
                                             <i class="bi bi-check-circle me-1"></i>Activate
                                         </button>
                                         <button type="button" class="btn btn-sm btn-outline-secondary" onclick="bulkUpdateStatus('inactive')">
                                             <i class="bi bi-x-circle me-1"></i>Deactivate
                                         </button>
                                         <button type="button" class="btn btn-sm btn-outline-info" onclick="addToMyCatalog()">
                                             <i class="bi bi-bookmark-plus me-1"></i>Add to My Catalog
                                         </button>
                                         <button type="button" class="btn btn-sm btn-outline-success" onclick="exportSelectedItems()">
                                             <i class="bi bi-download me-1"></i>Export CSV
                                         </button>
                                     </div>
                `;
                const targetContainer = document.querySelector('.container-fluid.py-4');
                if (targetContainer) {
                    targetContainer.insertBefore(bulkActionsBar, targetContainer.querySelector('.row'));
                } else {
                    // Fallback: insert after the header
                    const headerSection = document.querySelector('.bg-gradient-primary');
                    if (headerSection) {
                        headerSection.parentNode.insertBefore(bulkActionsBar, headerSection.nextSibling);
                    }
                }
            } else {
                bulkActionsBar.querySelector('span').innerHTML = `<strong>${count}</strong> item(s) selected`;
            }
        } else if (bulkActionsBar) {
            bulkActionsBar.remove();
        }
    }

    // Form handling with debounce
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

    // Change tracking and saving
    document.querySelectorAll('.retail-price, .cost-price, .item-type, .item-status, .item-flag').forEach(input => {
        input.addEventListener('change', function() {
            const row = this.closest('tr');
            const itemId = row.dataset.itemId;
            
            // Validate price inputs
            if (this.classList.contains('retail-price') || this.classList.contains('cost-price')) {
                const value = parseFloat(this.value);
                if (isNaN(value) || value < 0) {
                    this.value = '0.00';
                    showAlert('warning', 'Price must be a valid positive number.');
                    return;
                }
                if (value > 999.99) {
                    this.value = '999.99';
                    showAlert('warning', 'Price cannot exceed $999.99.');
                    return;
                }
                
                // Update margin display in real-time
                updateMarginDisplay(row);
            }
            
            modifiedItems.add(itemId);
            updateModifiedCount();
        });
    });

    function updateModifiedCount() {
        const count = modifiedItems.size;
        modifiedCount.textContent = count;
        modifiedCount.style.display = count > 0 ? 'inline' : 'none';
    }

    function updateMarginDisplay(row) {
        const retailPrice = parseFloat(row.querySelector('.retail-price').value) || 0;
        const costPrice = parseFloat(row.querySelector('.cost-price').value) || 0;
        const margin = retailPrice - costPrice;
        const marginPercent = costPrice > 0 ? (margin / costPrice * 100) : 0;
        
        const marginDisplay = row.querySelector('.margin-display span');
        if (marginDisplay) {
            marginDisplay.textContent = `$${margin.toFixed(2)} (${marginPercent.toFixed(1)}%)`;
            marginDisplay.className = margin > 1.00 ? 'badge bg-success' : 'badge bg-light text-dark';
        }
    }

    // Global functions for bulk actions
    window.bulkUpdateStatus = function(status) {
        const selectedItems = document.querySelectorAll('.item-checkbox:checked');
        if (selectedItems.length === 0) return;
        
        if (!confirm(`Are you sure you want to ${status === 'active' ? 'activate' : 'deactivate'} ${selectedItems.length} item(s)?`)) {
            return;
        }
        
        selectedItems.forEach(checkbox => {
            const row = checkbox.closest('tr');
            const statusSelect = row.querySelector('.item-status');
            if (statusSelect) {
                statusSelect.value = status;
                statusSelect.dispatchEvent(new Event('change'));
            }
        });
        
        showAlert('success', `Updated status for ${selectedItems.length} item(s)`);
    };

    window.exportSelectedItems = function() {
        const selectedItems = document.querySelectorAll('.item-checkbox:checked');
        if (selectedItems.length === 0) return;
        
        const csvData = [];
        csvData.push(['Name', 'Category', 'Brand', 'Retail Price', 'Cost Price', 'Margin', 'Status']);
        
        selectedItems.forEach(checkbox => {
            const row = checkbox.closest('tr');
            const name = row.querySelector('td:first-child .fw-medium').textContent.trim();
            const category = row.querySelector('.badge').textContent.trim();
            const brand = row.querySelector('td:first-child small')?.textContent.trim() || '';
            const retailPrice = row.querySelector('.retail-price').value;
            const costPrice = row.querySelector('.cost-price').value;
            const margin = (parseFloat(retailPrice) - parseFloat(costPrice)).toFixed(2);
            const status = row.querySelector('.item-status').value;
            
            csvData.push([name, category, brand, retailPrice, costPrice, margin, status]);
        });
        
        const csvContent = csvData.map(row => row.map(cell => `"${cell}"`).join(',')).join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `master-items-export-${new Date().toISOString().split('T')[0]}.csv`;
        a.click();
        window.URL.revokeObjectURL(url);
        
        showAlert('success', `Exported ${selectedItems.length} item(s) to CSV`);
    };

         window.showItemDetails = function(itemId) {
         // Placeholder for item details modal
         alert('Item details functionality would be implemented here for item ID: ' + itemId);
     };

     window.addToMyCatalog = function() {
         const selectedItems = document.querySelectorAll('.item-checkbox:checked');
         if (selectedItems.length === 0) {
             showAlert('warning', 'Please select items to add to your catalog.');
             return;
         }
         
         if (!confirm(`Add ${selectedItems.length} item(s) to My Catalog?`)) {
             return;
         }
         
         const itemIds = Array.from(selectedItems).map(cb => cb.dataset.itemId);
         
         fetch('add_to_catalog.php', {
             method: 'POST',
             headers: {
                 'Content-Type': 'application/json',
                 'X-CSRF-Token': '<?php echo $csrf_token; ?>'
             },
             body: JSON.stringify({ 
                 item_ids: itemIds,
                 csrf_token: '<?php echo $csrf_token; ?>'
             })
         })
         .then(response => response.json())
         .then(data => {
             if (data.success) {
                 showAlert('success', `Successfully added ${data.added_count} item(s) to My Catalog!`);
                 if (data.skipped_count > 0) {
                     showAlert('info', `${data.skipped_count} item(s) were already in your catalog.`);
                 }
                 // Clear selections
                 selectedItems.forEach(cb => cb.checked = false);
                 document.getElementById('selectAll').checked = false;
                 updateBulkActions();
             } else {
                 showAlert('danger', 'Error adding items to catalog: ' + (data.message || 'Unknown error'));
             }
         })
         .catch(error => {
             console.error('Error:', error);
             showAlert('danger', 'Error adding items to catalog. Please try again.');
         });
     };

    // Alert function
    window.showAlert = function(type, message) {
        const existingAlert = document.querySelector('.alert');
        if (existingAlert && existingAlert.classList.contains('alert-' + type)) {
            existingAlert.remove();
        }

        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show mx-4 mt-4`;
        alertDiv.setAttribute('role', 'alert');
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        const container = document.querySelector('.container-fluid');
        container.insertBefore(alertDiv, container.firstChild);

        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alertDiv);
            bsAlert.close();
        }, 5000);
    };

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            document.getElementById('saveAllChanges')?.click();
        }
        
        if (e.ctrlKey && e.key === 'f') {
            e.preventDefault();
            searchInput?.focus();
            searchInput?.select();
        }
        
        if (e.key === 'Escape' && document.activeElement === searchInput) {
            document.getElementById('clearSearch')?.click();
        }
    });

    // Save all changes with loading state and confirmation
    document.getElementById('saveAllChanges').addEventListener('click', function() {
        if (modifiedItems.size === 0) {
            showAlert('warning', 'No changes to save.');
            return;
        }

        // Show confirmation dialog
        if (!confirm(`Are you sure you want to save changes to ${modifiedItems.size} item(s)?`)) {
            return;
        }

        const button = this;
        const originalText = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';

        const changes = [];
        let hasErrors = false;

        document.querySelectorAll('#itemsTable tbody tr').forEach(row => {
            const itemId = row.dataset.itemId;
            if (modifiedItems.has(itemId)) {
                const itemName = row.querySelector('td:first-child .fw-medium').textContent.trim();
                const retailPrice = parseFloat(row.querySelector('.retail-price').value) || 0;
                const costPrice = parseFloat(row.querySelector('.cost-price').value) || 0;

                // Validate prices
                if (retailPrice < 0 || costPrice < 0) {
                    showAlert('danger', `Invalid prices for ${itemName}. Prices must be positive.`);
                    hasErrors = true;
                    return;
                }

                if (retailPrice > 999.99 || costPrice > 999.99) {
                    showAlert('danger', `Prices for ${itemName} exceed maximum allowed value ($999.99).`);
                    hasErrors = true;
                    return;
                }

                changes.push({
                    id: itemId,
                    name: itemName,
                    category: row.querySelector('.item-type').value,
                    suggested_price: retailPrice,
                    suggested_cost: costPrice,
                    status: row.querySelector('.item-status').value,
                    is_imported: row.querySelector('#is_imported_' + itemId)?.checked || false,
                    is_healthy: row.querySelector('#is_healthy_' + itemId)?.checked || false
                });
            }
        });

        if (hasErrors) {
            button.disabled = false;
            button.innerHTML = originalText;
            return;
        }

        // Send changes to server with CSRF token and timeout
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 30000); // 30 second timeout

        fetch('update_items.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': '<?php echo $csrf_token; ?>'
            },
            body: JSON.stringify({ 
                changes: changes,
                csrf_token: '<?php echo $csrf_token; ?>'
            }),
            signal: controller.signal
        })
        .then(response => {
            clearTimeout(timeoutId);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                modifiedItems.clear();
                updateModifiedCount();
                showAlert('success', `Successfully saved changes to ${changes.length} item(s)!`);
            } else {
                showAlert('danger', 'Error saving changes: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            clearTimeout(timeoutId);
            console.error('Error:', error);
            if (error.name === 'AbortError') {
                showAlert('danger', 'Request timed out. Please try again.');
            } else {
                showAlert('danger', 'Error saving changes. Please check your connection and try again.');
            }
        })
        .finally(() => {
            button.disabled = false;
            button.innerHTML = originalText;
        });
    });
});
</script> 