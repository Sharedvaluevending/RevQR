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
$stmt = $pdo->prepare("SELECT id FROM businesses WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$business = $stmt->fetch();

if (!$business) {
    header('Location: /business/dashboard.php?error=no_business');
    exit;
}

// Get filter parameters with validation
$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
$priceRange = trim($_GET['price_range'] ?? '');
$sort = in_array($_GET['sort'] ?? '', ['name', 'price_asc', 'price_desc', 'margin_desc']) ? $_GET['sort'] : 'name';
$page = max(1, intval($_GET['page'] ?? 1));
$itemsPerPage = max(10, min(200, intval($_GET['per_page'] ?? 25)));

// Build the base query with proper indexing
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
        (mi.suggested_price - mi.suggested_cost) as margin,
        CASE 
            WHEN (mi.suggested_price - mi.suggested_cost) > 1.00 THEN 1 
            ELSE 0 
        END as high_margin
    FROM master_items mi
    WHERE 1=1
";
$params = [];

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
    default:
        $query .= " ORDER BY mi.name ASC";
}

// Get total count for pagination
$countQuery = str_replace(
    "SELECT mi.id, mi.name as item_name, mi.category, mi.type as item_type, mi.brand, mi.suggested_price as retail_price, mi.suggested_cost as cost_price, mi.popularity, mi.shelf_life, mi.is_seasonal, mi.is_imported, mi.is_healthy, mi.status, mi.created_at, (mi.suggested_price - mi.suggested_cost) as margin, CASE WHEN (mi.suggested_price - mi.suggested_cost) > 1.00 THEN 1 ELSE 0 END as high_margin FROM master_items mi",
    "SELECT COUNT(*) FROM master_items mi",
    $query
);
$countQuery = preg_replace('/ORDER BY.*$/', '', $countQuery);

try {
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($params);
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

// Get all categories for the filter dropdown with caching
try {
    $categories_stmt = $pdo->query("SELECT DISTINCT category FROM master_items WHERE category IS NOT NULL ORDER BY category ASC");
    $categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching categories: " . $e->getMessage());
    $categories = [];
}

// Get categories that have items in current result set and group items
$currentPageCategories = [];
$items_list = [];
foreach ($items as $item) {
    $cat = $item['category'] ?: 'Other';
    if (!in_array($cat, $currentPageCategories)) {
        $currentPageCategories[] = $cat;
    }
    if (!isset($items_list[$cat])) {
        $items_list[$cat] = [];
    }
    $items_list[$cat][] = $item;
}

$totalCategoriesDisplay = count($currentPageCategories);

require_once __DIR__ . '/../core/includes/header.php';
?>

<div class="container-fluid px-0">
    <!-- Header Section with Stats -->
    <div class="bg-white border-bottom">
        <div class="container py-4">
            <div class="row align-items-center">
                <div class="col">
                    <h1 class="h3 mb-1">Master Items List</h1>
                    <p class="text-muted mb-0">Manage your product catalog</p>
                </div>
                <div class="col-auto">
                    <div class="d-flex gap-3">
                        <div class="text-center">
                            <h3 class="h4 mb-0 text-primary"><?php echo $totalItems; ?></h3>
                            <small class="text-muted">Total Items</small>
                        </div>
                        <div class="text-center">
                            <h3 class="h4 mb-0 text-success"><?php echo $totalCategoriesDisplay; ?></h3>
                            <small class="text-muted">Categories on Page</small>
                        </div>
                        <div class="text-center">
                            <h3 class="h4 mb-0 text-info"><?php echo $totalPages; ?></h3>
                            <small class="text-muted">Total Pages</small>
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
            <!-- Master Items List -->
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-white py-3">
                        <div class="row align-items-center g-3">
                            <div class="col">
                                <h5 class="mb-0">Product Catalog</h5>
                            </div>
                            <div class="col-auto">
                                <form id="searchForm" class="d-flex">
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
                                        <button class="btn btn-outline-secondary" 
                                                type="button" 
                                                id="clearSearch"
                                                aria-label="Clear search">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </div>
                                </form>
                            </div>
                            <div class="col-auto">
                                <button type="button" class="btn btn-primary" id="saveAllChanges">
                                    <i class="bi bi-save me-2"></i>Save Changes
                                    <span class="badge bg-secondary ms-2" id="modifiedCount" style="display: none;">0</span>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <!-- Filters -->
                        <div class="bg-light p-3 border-bottom">
                            <form id="filterForm" class="row g-3">
                                <div class="col-md-3">
                                    <label for="categoryFilter" class="form-label small text-muted">Category</label>
                                    <select class="form-select" 
                                            id="categoryFilter" 
                                            name="category" 
                                            autocomplete="off"
                                            aria-label="Filter by category">
                                        <option value="">All Categories</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo htmlspecialchars($cat['category']); ?>"
                                                    <?php echo $category === $cat['category'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cat['category']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="priceRangeFilter" class="form-label small text-muted">Price Range</label>
                                    <select class="form-select" 
                                            id="priceRangeFilter" 
                                            name="price_range" 
                                            autocomplete="off"
                                            aria-label="Filter by price range">
                                        <option value="">All Prices</option>
                                        <option value="0-1" <?php echo $priceRange === '0-1' ? 'selected' : ''; ?>>Under $1.00</option>
                                        <option value="1-2" <?php echo $priceRange === '1-2' ? 'selected' : ''; ?>>$1.00 - $2.00</option>
                                        <option value="2-3" <?php echo $priceRange === '2-3' ? 'selected' : ''; ?>>$2.00 - $3.00</option>
                                        <option value="3+" <?php echo $priceRange === '3+' ? 'selected' : ''; ?>>Over $3.00</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="sortFilter" class="form-label small text-muted">Sort By</label>
                                    <select class="form-select" 
                                            id="sortFilter" 
                                            name="sort" 
                                            autocomplete="off"
                                            aria-label="Sort items by">
                                        <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Name</option>
                                        <option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Price: Low to High</option>
                                        <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Price: High to Low</option>
                                    </select>
                                </div>
                            </form>
                        </div>

                        <!-- Items Table -->
                        <div class="table-responsive">
                            <div class="d-flex justify-content-between align-items-center p-3 bg-light border-bottom">
                                <small class="text-muted">
                                    Showing <?php echo ($page - 1) * $itemsPerPage + 1; ?>-<?php echo min($page * $itemsPerPage, $totalItems); ?> 
                                    of <?php echo $totalItems; ?> items
                                </small>
                                <div class="d-flex align-items-center gap-2">
                                    <label for="itemsPerPage" class="small text-muted mb-0">Items per page:</label>
                                    <select class="form-select form-select-sm" 
                                            id="itemsPerPage" 
                                            name="per_page" 
                                            style="width: auto;"
                                            autocomplete="off"
                                            aria-label="Select number of items per page">
                                        <option value="10" <?php echo $itemsPerPage === 10 ? 'selected' : ''; ?>>10</option>
                                        <option value="25" <?php echo $itemsPerPage === 25 ? 'selected' : ''; ?>>25</option>
                                        <option value="50" <?php echo $itemsPerPage === 50 ? 'selected' : ''; ?>>50</option>
                                        <option value="100" <?php echo $itemsPerPage === 100 ? 'selected' : ''; ?>>100</option>
                                    </select>
                                </div>
                            </div>
                            <table class="table table-hover align-middle mb-0" id="itemsTable">
                                <thead class="bg-light">
                                    <tr>
                                        <th scope="col" class="ps-4">Item</th>
                                        <th scope="col">Category</th>
                                        <th scope="col">Type</th>
                                        <th scope="col">Retail Price</th>
                                        <th scope="col">Cost Price</th>
                                        <th scope="col">Margin</th>
                                        <th scope="col">Status</th>
                                        <th scope="col">Flags</th>
                                        <th scope="col" class="pe-4">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                        <tr data-item-id="<?php echo $item['id']; ?>" class="border-bottom">
                                            <td class="ps-4">
                                                <div class="fw-medium"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                                <?php if (!empty($item['brand'])): ?>
                                                    <small class="text-muted"><?php echo htmlspecialchars($item['brand']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars($item['category'] ?: 'Other'); ?></span>
                                            </td>
                                                <td>
                                                    <select class="form-select form-select-sm item-type" 
                                                            id="type_<?php echo $item['id']; ?>"
                                                            name="type[<?php echo $item['id']; ?>]"
                                                            autocomplete="off">
                                                        <option value="regular" <?php echo $item['category'] === 'regular' ? 'selected' : ''; ?>>Regular</option>
                                                        <option value="in" <?php echo $item['category'] === 'in' ? 'selected' : ''; ?>>In</option>
                                                        <option value="vote_in" <?php echo $item['category'] === 'vote_in' ? 'selected' : ''; ?>>Vote In</option>
                                                        <option value="vote_out" <?php echo $item['category'] === 'vote_out' ? 'selected' : ''; ?>>Vote Out</option>
                                                        <option value="showcase" <?php echo $item['category'] === 'showcase' ? 'selected' : ''; ?>>Showcase</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <div class="input-group input-group-sm">
                                                        <span class="input-group-text">$</span>
                                                        <input type="number" 
                                                               class="form-control retail-price" 
                                                               id="retail_price_<?php echo $item['id']; ?>"
                                                               name="retail_price[<?php echo $item['id']; ?>]"
                                                               value="<?php echo $item['retail_price']; ?>" 
                                                               step="0.01" 
                                                               min="0"
                                                               autocomplete="off">
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="input-group input-group-sm">
                                                        <span class="input-group-text">$</span>
                                                        <input type="number" 
                                                               class="form-control cost-price" 
                                                               id="cost_price_<?php echo $item['id']; ?>"
                                                               name="cost_price[<?php echo $item['id']; ?>]"
                                                               value="<?php echo $item['cost_price']; ?>" 
                                                               step="0.01" 
                                                               min="0"
                                                               autocomplete="off">
                                                    </div>
                                                </td>
                                                <td class="margin-display">
                                                    <span class="badge bg-light text-dark">
                                                        $<?php echo number_format($item['retail_price'] - $item['cost_price'], 2); ?>
                                                        (<?php echo $item['cost_price'] > 0 ? number_format(($item['retail_price'] - $item['cost_price']) / $item['cost_price'] * 100, 1) : '0'; ?>%)
                                                    </span>
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
                                                        <?php if (($item['retail_price'] - $item['cost_price']) > 1.00): ?>
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
                                                <td class="pe-4">
                                                    <a href="list-maker.php?item_id=<?php echo $item['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary"
                                                       aria-label="Add <?php echo htmlspecialchars($item['item_name']); ?> to list">
                                                        <i class="bi bi-list-plus"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="d-flex justify-content-between align-items-center p-3 bg-light border-top">
                            <div class="text-muted small">
                                Page <?php echo $page; ?> of <?php echo $totalPages; ?>
                            </div>
                            <nav aria-label="Items pagination">
                                <ul class="pagination pagination-sm mb-0">
                                    <?php if ($totalPages > 1): ?>
                                        <li class="page-item <?php echo $page === 1 ? 'disabled' : ''; ?>">
                                            <a class="page-link" 
                                               href="?page=<?php echo $page - 1; ?>&per_page=<?php echo $itemsPerPage; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&price_range=<?php echo urlencode($priceRange); ?>&sort=<?php echo urlencode($sort); ?>" 
                                               aria-label="Previous page">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>
                                        <?php
                                        $ellipsisShown = false;
                                        $window = 2;
                                        for ($i = 1; $i <= $totalPages; $i++) {
                                            if (
                                                $i == 1 || $i == $totalPages ||
                                                ($i >= $page - $window && $i <= $page + $window)
                                            ) {
                                                $ellipsisShown = false;
                                                ?>
                                                <li class="page-item <?php echo $page === $i ? 'active' : ''; ?>">
                                                    <a class="page-link" 
                                                       href="?page=<?php echo $i; ?>&per_page=<?php echo $itemsPerPage; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&price_range=<?php echo urlencode($priceRange); ?>&sort=<?php echo urlencode($sort); ?>"
                                                       aria-label="Page <?php echo $i; ?>">
                                                        <?php echo $i; ?>
                                                    </a>
                                                </li>
                                                <?php
                                            } elseif (!$ellipsisShown) {
                                                $ellipsisShown = true;
                                                ?>
                                                <li class="page-item disabled">
                                                    <span class="page-link" aria-hidden="true">&hellip;</span>
                                                </li>
                                                <?php
                                            }
                                        }
                                        ?>
                                        <li class="page-item <?php echo $page === $totalPages ? 'disabled' : ''; ?>">
                                            <a class="page-link" 
                                               href="?page=<?php echo $page + 1; ?>&per_page=<?php echo $itemsPerPage; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&price_range=<?php echo urlencode($priceRange); ?>&sort=<?php echo urlencode($sort); ?>"
                                               aria-label="Next page">
                                                <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Modern UI Improvements */
.container-fluid {
    max-width: 100%;
    padding: 0;
}

/* Card Improvements */
.card {
    border: none;
    border-radius: 0.5rem;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.card-header {
    border-bottom: 1px solid rgba(0, 0, 0, 0.125);
    background-color: #fff;
}

/* Table Improvements */
.table {
    margin-bottom: 0;
}

.table th {
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.5px;
}

.table td {
    vertical-align: middle;
    padding: 0.75rem 1rem;
}

.category-header td {
    padding: 0.5rem 1rem;
    background-color: #f8f9fa;
}

/* Form Control Improvements */
.form-control, .form-select {
    border-radius: 0.375rem;
    border-color: #dee2e6;
}

.form-control:focus, .form-select:focus {
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

.input-group-text {
    background-color: #f8f9fa;
    border-color: #dee2e6;
}

/* Button Improvements */
.btn {
    border-radius: 0.375rem;
    font-weight: 500;
}

.btn-primary {
    background-color: #0d6efd;
    border-color: #0d6efd;
}

.btn-primary:hover {
    background-color: #0b5ed7;
    border-color: #0a58ca;
}

/* Badge Improvements */
.badge {
    font-weight: 500;
    padding: 0.35em 0.65em;
}

/* Search Bar Improvements */
.input-group .form-control {
    border-left: 0;
}

.input-group .input-group-text {
    border-right: 0;
}

/* Pagination Improvements */
.pagination {
    margin-bottom: 0;
}

.page-link {
    padding: 0.375rem 0.75rem;
    color: #0d6efd;
    background-color: #fff;
    border: 1px solid #dee2e6;
}

.page-item.active .page-link {
    background-color: #0d6efd;
    border-color: #0d6efd;
}

/* Responsive Improvements */
@media (max-width: 992px) {
    .col-lg-4 {
        margin-top: 1rem;
    }
}

/* Loading State Improvements */
.spinner-border {
    width: 1rem;
    height: 1rem;
    border-width: 0.15em;
}

/* Alert Improvements */
.alert {
    border-radius: 0.375rem;
    border: none;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

/* Popover Improvements */
.popover {
    border-radius: 0.375rem;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

.popover-body {
    padding: 1rem;
}

/* Form Switch Improvements */
.form-check-input:checked {
    background-color: #0d6efd;
    border-color: #0d6efd;
}

/* Stats Display Improvements */
.h4 {
    font-size: 1.5rem;
    font-weight: 600;
}

/* Search and Filter Improvements */
.bg-light {
    background-color: #f8f9fa !important;
}

/* Table Header Improvements */
.table thead th {
    background-color: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.5px;
}

/* Input Group Improvements */
.input-group-sm > .form-control,
.input-group-sm > .form-select,
.input-group-sm > .input-group-text {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

/* Margin Display Improvements */
.margin-display .badge {
    font-size: 0.75rem;
    padding: 0.25em 0.5em;
}

/* Category Header Improvements */
.category-header td {
    background-color: #f8f9fa;
    font-weight: 600;
    color: #6c757d;
}

/* Remove Button Improvements */
.remove-item {
    padding: 0.25rem 0.5rem;
    line-height: 1;
    border-radius: 0.25rem;
}

.remove-item:hover {
    background-color: #dc3545;
    color: white;
}

/* Save Changes Button Improvements */
#saveAllChanges {
    position: relative;
    overflow: hidden;
}

#saveAllChanges .badge {
    position: absolute;
    top: -8px;
    right: -8px;
    padding: 0.25em 0.5em;
    font-size: 0.75rem;
}

/* Search Input Improvements */
#searchItems {
    padding-left: 0;
}

#searchItems:focus {
    box-shadow: none;
}

/* Filter Form Improvements */
#filterForm .form-label {
    margin-bottom: 0.25rem;
}

/* Table Row Hover Improvements */
.table-hover tbody tr:hover {
    background-color: rgba(13, 110, 253, 0.05);
}

/* Card Body Padding Improvements */
.card-body {
    padding: 1.25rem;
}

/* Form Control Focus Improvements */
.form-control:focus,
.form-select:focus {
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

/* Button Group Improvements */
.btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

/* Badge Improvements */
.badge {
    font-size: 0.75rem;
    padding: 0.25em 0.5em;
}

/* Popover Improvements */
.popover {
    max-width: 200px;
}

.form-check {
    margin-bottom: 0.5rem;
}

.form-check:last-child {
    margin-bottom: 0;
}
</style>

<script>
// Global alert function
function showAlert(type, message) {
    // Remove any existing alerts
    const existingAlert = document.querySelector('.alert');
    if (existingAlert) {
        existingAlert.remove();
    }

    // Create new alert
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show mx-4 mt-4`;
    alertDiv.setAttribute('role', 'alert');
    
    // Create message text node instead of using innerHTML
    const messageText = document.createTextNode(message);
    alertDiv.appendChild(messageText);
    
    // Create close button
    const closeButton = document.createElement('button');
    closeButton.type = 'button';
    closeButton.className = 'btn-close';
    closeButton.setAttribute('data-bs-dismiss', 'alert');
    closeButton.setAttribute('aria-label', 'Close');
    alertDiv.appendChild(closeButton);

    // Insert alert at the top of the container
    const container = document.querySelector('.container-fluid');
    container.insertBefore(alertDiv, container.firstChild);

    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        const bsAlert = new bootstrap.Alert(alertDiv);
        bsAlert.close();
    }, 5000);
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize dropdowns
    var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
    var dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
        return new bootstrap.Dropdown(dropdownToggleEl);
    });

    // Track modified items
    const modifiedItems = new Set();
    const modifiedCount = document.getElementById('modifiedCount');

    // Initialize popovers
    const popovers = document.querySelectorAll('[data-bs-toggle="popover"]');
    popovers.forEach(popover => {
        new bootstrap.Popover(popover, {
            trigger: 'hover',
            html: true,
            container: 'body',
            sanitize: false
        });
    });

    // Add change event listeners to all editable fields including flags in popovers
    document.querySelectorAll('.retail-price, .cost-price, .item-type, .item-status, .item-flag').forEach(input => {
        input.addEventListener('change', function() {
            const row = this.closest('tr');
            const itemName = row.querySelector('td:first-child').textContent.trim();
            
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
            
            modifiedItems.add(itemName);
            updateModifiedCount();
            
            // Update badge display if it's a flag change
            if (this.classList.contains('item-flag')) {
                const flag = this.dataset.flag;
                const badge = row.querySelector(`[data-flag-badge="${flag}"]`);
                if (badge) {
                    badge.style.display = this.checked ? 'inline-block' : 'none';
                }
            }
        });
        
        // Add input validation for price fields
        if (input.classList.contains('retail-price') || input.classList.contains('cost-price')) {
            input.addEventListener('input', function() {
                // Remove non-numeric characters except decimal point
                this.value = this.value.replace(/[^0-9.]/g, '');
                
                // Ensure only one decimal point
                const parts = this.value.split('.');
                if (parts.length > 2) {
                    this.value = parts[0] + '.' + parts.slice(1).join('');
                }
                
                // Limit to 2 decimal places
                if (parts[1] && parts[1].length > 2) {
                    this.value = parts[0] + '.' + parts[1].substring(0, 2);
                }
            });
        }
    });

    function updateModifiedCount() {
        const count = modifiedItems.size;
        modifiedCount.textContent = count;
        modifiedCount.style.display = count > 0 ? 'inline' : 'none';
    }

    // Function to update margin display in real-time
    function updateMarginDisplay(row) {
        const retailPrice = parseFloat(row.querySelector('.retail-price').value) || 0;
        const costPrice = parseFloat(row.querySelector('.cost-price').value) || 0;
        const margin = retailPrice - costPrice;
        const marginPercent = costPrice > 0 ? (margin / costPrice * 100) : 0;
        
        const marginDisplay = row.querySelector('.margin-display span');
        if (marginDisplay) {
            marginDisplay.textContent = `$${margin.toFixed(2)} (${marginPercent.toFixed(1)}%)`;
            
            // Update badge color based on margin
            marginDisplay.className = margin > 1.00 ? 'badge bg-success' : 'badge bg-light text-dark';
        }
        
        // Update high margin badge
        const highMarginBadge = row.querySelector('[data-flag-badge="high_margin"]');
        if (highMarginBadge) {
            highMarginBadge.style.display = margin > 1.00 ? 'inline-block' : 'none';
        }
    }

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
            const itemName = row.querySelector('td:first-child').textContent.trim();
            if (modifiedItems.has(itemName)) {
                const itemId = row.dataset.itemId;
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

    // Handle form submissions
    const filterForm = document.getElementById('filterForm');
    const searchForm = document.getElementById('searchForm');
    const searchInput = document.getElementById('searchItems');

    // Debounce function to limit how often the search updates
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

    // Update search as you type with debounce
    searchInput.addEventListener('input', debounce(() => {
        submitForm(searchForm);
    }, 500)); // 500ms delay

    filterForm.addEventListener('change', () => submitForm(filterForm));
    searchForm.addEventListener('submit', (e) => {
        e.preventDefault();
        submitForm(searchForm);
    });

    document.getElementById('clearSearch').addEventListener('click', function() {
        searchInput.value = '';
        submitForm(searchForm);
    });

    document.getElementById('itemsPerPage').addEventListener('change', function() {
        submitForm(filterForm);
    });

    // Add loading state for AJAX operations
    function showLoading(show) {
        const loadingOverlay = document.getElementById('loadingOverlay');
        if (show) {
            if (!loadingOverlay) {
                const overlay = document.createElement('div');
                overlay.id = 'loadingOverlay';
                overlay.className = 'position-fixed top-0 start-0 w-100 h-100 d-flex justify-content-center align-items-center bg-dark bg-opacity-50';
                overlay.style.zIndex = '9999';
                overlay.innerHTML = `
                    <div class="spinner-border text-light" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                `;
                document.body.appendChild(overlay);
            }
        } else {
            loadingOverlay?.remove();
        }
    }

    // Add loading state to all AJAX operations
    const originalFetch = window.fetch;
    window.fetch = function() {
        showLoading(true);
        return originalFetch.apply(this, arguments)
            .finally(() => showLoading(false));
    };

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl+S to save changes
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            document.getElementById('saveAllChanges').click();
        }
        
        // Ctrl+F to focus search
        if (e.ctrlKey && e.key === 'f') {
            e.preventDefault();
            searchInput.focus();
            searchInput.select();
        }
        
        // Escape to clear search
        if (e.key === 'Escape' && document.activeElement === searchInput) {
            document.getElementById('clearSearch').click();
        }
    });

    // Add tooltips for keyboard shortcuts
    document.getElementById('saveAllChanges').title = 'Save Changes (Ctrl+S)';
    searchInput.title = 'Search items (Ctrl+F to focus, Escape to clear)';

    // Add bulk selection functionality
    const selectAllCheckbox = document.createElement('input');
    selectAllCheckbox.type = 'checkbox';
    selectAllCheckbox.id = 'selectAll';
    selectAllCheckbox.className = 'form-check-input';
    
    // Add select all to table header
    const firstHeaderCell = document.querySelector('#itemsTable thead th:first-child');
    if (firstHeaderCell) {
        const selectAllContainer = document.createElement('div');
        selectAllContainer.className = 'd-flex align-items-center gap-2';
        selectAllContainer.innerHTML = `
            <input type="checkbox" id="selectAll" class="form-check-input">
            <span>Item</span>
        `;
        firstHeaderCell.innerHTML = '';
        firstHeaderCell.appendChild(selectAllContainer);
    }

    // Add individual checkboxes to each row
    document.querySelectorAll('#itemsTable tbody tr[data-item-id]').forEach(row => {
        const firstCell = row.querySelector('td:first-child');
        if (firstCell) {
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'form-check-input item-checkbox me-2';
            checkbox.dataset.itemId = row.dataset.itemId;
            
            const content = firstCell.innerHTML;
            firstCell.innerHTML = '';
            firstCell.appendChild(checkbox);
            
            const contentDiv = document.createElement('div');
            contentDiv.innerHTML = content;
            firstCell.appendChild(contentDiv);
        }
    });

    // Handle select all functionality
    document.getElementById('selectAll').addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.item-checkbox');
        checkboxes.forEach(cb => cb.checked = this.checked);
        updateBulkActions();
    });

    // Handle individual checkbox changes
    document.querySelectorAll('.item-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateBulkActions);
    });

    function updateBulkActions() {
        const selectedItems = document.querySelectorAll('.item-checkbox:checked');
        const selectAllCheckbox = document.getElementById('selectAll');
        const totalCheckboxes = document.querySelectorAll('.item-checkbox');
        
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
        let bulkActionsBar = document.getElementById('bulkActionsBar');
        if (selectedItems.length > 0) {
            if (!bulkActionsBar) {
                bulkActionsBar = document.createElement('div');
                bulkActionsBar.id = 'bulkActionsBar';
                bulkActionsBar.className = 'alert alert-info d-flex justify-content-between align-items-center mx-4 mt-4';
                bulkActionsBar.innerHTML = `
                    <span><strong>${selectedItems.length}</strong> item(s) selected</span>
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="bulkActivate">
                            <i class="bi bi-check-circle me-1"></i>Activate
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="bulkDeactivate">
                            <i class="bi bi-x-circle me-1"></i>Deactivate
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-success" id="bulkExport">
                            <i class="bi bi-download me-1"></i>Export
                        </button>
                    </div>
                `;
                document.querySelector('.container-fluid').insertBefore(bulkActionsBar, document.querySelector('.container-fluid .row'));
                
                // Add bulk action handlers
                document.getElementById('bulkActivate').addEventListener('click', () => bulkUpdateStatus('active'));
                document.getElementById('bulkDeactivate').addEventListener('click', () => bulkUpdateStatus('inactive'));
                document.getElementById('bulkExport').addEventListener('click', exportSelectedItems);
            } else {
                bulkActionsBar.querySelector('span').innerHTML = `<strong>${selectedItems.length}</strong> item(s) selected`;
            }
        } else if (bulkActionsBar) {
            bulkActionsBar.remove();
        }
    }

    function bulkUpdateStatus(status) {
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
    }

    function exportSelectedItems() {
        const selectedItems = document.querySelectorAll('.item-checkbox:checked');
        if (selectedItems.length === 0) return;
        
        const csvData = [];
        csvData.push(['Name', 'Category', 'Brand', 'Retail Price', 'Cost Price', 'Margin', 'Status']);
        
        selectedItems.forEach(checkbox => {
            const row = checkbox.closest('tr');
            const name = row.querySelector('td:first-child div').textContent.trim();
            const category = row.querySelector('td:nth-child(2)').textContent.trim();
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
    }
});
</script> 